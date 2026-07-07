<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnterpriseSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Product $product;
    private SchedulingService $schedulingService;
    private MesExecutionService $mesService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Enterprise Corp',
            'slug'   => 'enterprise-corp',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin User',
            'email'     => 'admin@enterprise.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Finished Product Model X',
            'sku'       => 'FG-PROD-X',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $this->schedulingService = app(SchedulingService::class);
        $this->mesService        = app(MesExecutionService::class);
    }

    /**
     * Test finite capacity pushes overlapping jobs to the next available slot.
     */
    public function test_finite_capacity_allocations(): void
    {
        $wc = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'CNC Fabrication',
            'code'                  => 'CNC-01',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'CNC Laser Cutter',
            'code'           => 'CNC-LSR',
            'status'         => 'active',
        ]);

        // Create standard Mon-Fri calendar
        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Default Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => true,
        ]);
        $wc->update(['production_calendar_id' => $cal->id]);

        // Create 2 identical orders with 1 operation of 120 mins duration
        $order1 = $this->createMockOrder('ORD-101', $wc, $machine, 120);
        $order2 = $this->createMockOrder('ORD-102', $wc, $machine, 60);

        // Schedule first order starting Monday 2026-07-06 at 09:00:00
        $start = Carbon::parse('2026-07-06 09:00:00');
        $sched1 = $this->schedulingService->generateSchedule($order1, $start, 'forward');

        // Schedule second order starting at same time
        $sched2 = $this->schedulingService->generateSchedule($order2, $start, 'forward');

        $op1 = $sched1->operations->first();
        $op2 = $sched2->operations->first();

        // Op 1 runs from 09:00 to 11:00
        $this->assertEquals('2026-07-06 09:00:00', $op1->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 11:00:00', $op1->planned_finish->toDateTimeString());

        // Op 2 should start at 11:00 (when Machine is free) and finish at 12:00
        $this->assertEquals('2026-07-06 11:00:00', $op2->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 12:00:00', $op2->planned_finish->toDateTimeString());
    }

    /**
     * Test shift capacity calculation.
     */
    public function test_shift_capacity_minutes(): void
    {
        $wc = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Assembly Line',
            'code'                  => 'ASSY-01',
            'efficiency_percentage' => 90.0, // 90% efficiency
            'status'                => 'active',
        ]);

        $shiftA = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Shift A',
            'code'             => 'SH_A',
            'start_time'       => '08:00:00',
            'end_time'         => '16:00:00',
            'break_minutes'    => 30, // 8h - 30m = 7.5h = 450 mins
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        $wc->shifts()->attach($shiftA->id, ['tenant_id' => $this->tenant->id]);

        $date = Carbon::parse('2026-07-06'); // Monday
        $capacity = $this->schedulingService->calculateCapacity($wc->id, $date);

        // Expected: 450 minutes * 90% = 405 minutes
        $this->assertEquals(405.0, $capacity);
    }

    /**
     * Test holiday scheduling skips non-working days.
     */
    public function test_holiday_scheduling_skips(): void
    {
        $wc = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Assembly Line',
            'code'                  => 'ASSY-02',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'CNC Mill',
            'code'           => 'CNC-MIL',
            'status'         => 'active',
        ]);

        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Calendar with Holiday',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => false,
        ]);
        $wc->update(['production_calendar_id' => $cal->id]);

        // Add a holiday on Tuesday 2026-07-07
        ProductionCalendarHoliday::create([
            'tenant_id'              => $this->tenant->id,
            'production_calendar_id' => $cal->id,
            'name'                   => 'National Day',
            'holiday_date'           => '2026-07-07',
            'holiday_type'           => 'public_holiday',
        ]);

        // Operation starts Monday 2026-07-06 at 15:30:00 for 120 mins.
        // Shift finishes at 16:00:00 (so 30 mins are scheduled on Mon, leaving 90 mins).
        // Tuesday is a holiday, so it must skip Tuesday and complete on Wednesday morning.
        $order = $this->createMockOrder('ORD-201', $wc, $machine, 120);
        $start = Carbon::parse('2026-07-06 15:30:00');

        $sched = $this->schedulingService->generateSchedule($order, $start, 'forward');
        $op    = $sched->operations->first();

        // Planned finish should be Wednesday 2026-07-08 at 10:00:00
        $this->assertEquals('2026-07-08 10:00:00', $op->planned_finish->toDateTimeString());
    }

    /**
     * Test alternate machine assignment.
     */
    public function test_alternate_machine_selections(): void
    {
        $wc = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Pressing',
            'code'                  => 'PR-01',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);

        $primary = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Primary Press',
            'code'           => 'PR-PRI',
            'status'         => 'active',
        ]);

        $alternate = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Alternate Press',
            'code'           => 'PR-ALT',
            'status'         => 'active',
        ]);

        $order = $this->createMockOrder('ORD-301', $wc, $primary, 60);
        $routingOp = RoutingOperation::find($order->operations->first()->routing_operation_id);

        // Bind alternate press
        RoutingOperationAlternateMachine::create([
            'tenant_id'            => $this->tenant->id,
            'routing_operation_id' => $routingOp->id,
            'machine_id'           => $alternate->id,
            'priority'             => 1,
        ]);

        // Place a block on Primary Press on Monday 2026-07-06 from 09:00 to 11:00
        $blockOrder = $this->createMockOrder('ORD-BLOCK', $wc, $primary, 120);
        $this->schedulingService->generateSchedule($blockOrder, Carbon::parse('2026-07-06 09:00:00'), 'forward');

        // Schedule new order starting Monday at 09:00:00.
        // Primary is busy until 11:00. Alternate is free at 09:00.
        // Earliest-slot logic should assign it to the Alternate machine immediately at 09:00!
        $sched = $this->schedulingService->generateSchedule($order, Carbon::parse('2026-07-06 09:00:00'), 'forward');
        $op    = $sched->operations->first();

        $this->assertEquals($alternate->id, $op->machine_id);
        $this->assertEquals('2026-07-06 09:00:00', $op->planned_start->toDateTimeString());

        // Warnings array must contain ALTERNATE_MACHINE_USED structured alert
        $warnings = $op->warnings;
        $this->assertNotEmpty($warnings);
        $this->assertEquals('ALTERNATE_MACHINE_USED', $warnings[0]['code']);
    }

    /**
     * Test locked operations are skipped during rescheduling.
     */
    public function test_locked_reschedule_preservation(): void
    {
        $wc = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Finishing',
            'code'                  => 'FIN-01',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Paint Booth',
            'code'           => 'PNT-BTH',
            'status'         => 'active',
        ]);

        $order = $this->createMockOrder('ORD-401', $wc, $machine, 60);
        $sched = $this->schedulingService->generateSchedule($order, Carbon::parse('2026-07-06 09:00:00'), 'forward');

        $op = $sched->operations->first();
        // Lock this operation
        $op->update(['locked' => true]);

        // Reschedule to a new start date (Tuesday 2026-07-07)
        $newStart = Carbon::parse('2026-07-07 09:00:00');
        $this->schedulingService->reschedule($sched->id, $newStart, 'forward');

        $op->refresh();
        // The planned_start should remain unchanged at Monday 09:00:00 because it was locked!
        $this->assertEquals('2026-07-06 09:00:00', $op->planned_start->toDateTimeString());
    }

    /**
     * Test schedule lifecycle transition.
     */
    public function test_schedule_lifecycle_transitions(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Welding',
            'code'      => 'WLD-01',
            'status'    => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Welding Station',
            'code'           => 'WLD-STN',
            'status'         => 'active',
        ]);

        $order = $this->createMockOrder('ORD-501', $wc, $machine, 30);
        $sched = $this->schedulingService->generateSchedule($order, Carbon::parse('2026-07-06 09:00:00'), 'forward');

        // Confirm scheduled status
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $sched->status);

        // Release the schedule
        $sched->update(['status' => ProductionSchedule::STATUS_RELEASED]);

        // Start operation through MES
        $op = $sched->operations->first();
        $this->mesService->startOperation($op->id, $machine->id, $this->admin->id);

        $sched->refresh();
        // Should automatically transition to in_progress
        $this->assertEquals(ProductionSchedule::STATUS_IN_PROGRESS, $sched->status);
    }

    /**
     * Helper to mock routing, order, and operations.
     */
    private function createMockOrder(string $number, WorkCenter $wc, Machine $machine, float $durationMinutes): ProductionOrder
    {
        $routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $this->product->id,
            'name'       => 'Mock Routing',
            'status'     => 'approved',
        ]);

        $routingOp = RoutingOperation::create([
            'tenant_id'               => $this->tenant->id,
            'routing_id'              => $routing->id,
            'sequence'                => 1,
            'operation_number'        => 'OP-10',
            'name'                    => 'Test Cut',
            'operation_type'          => 'manufacturing',
            'work_center_id'          => $wc->id,
            'machine_id'              => $machine->id,
            'setup_time_minutes'      => 0,
            'processing_time_minutes' => $durationMinutes,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => $number,
            'product_id'       => $this->product->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 1.0,
            'start_date'       => now(),
            'end_date'         => now()->addDays(5),
            'status'           => ProductionOrder::STATUS_RELEASED,
        ]);

        ProductionOrderOperation::create([
            'tenant_id'                 => $this->tenant->id,
            'production_order_id'       => $order->id,
            'routing_operation_id'      => $routingOp->id,
            'sequence'                  => 1,
            'operation_number'          => 'OP-10',
            'name'                      => 'Test Cut',
            'work_center_id'            => $wc->id,
            'machine_id'                => $machine->id,
            'setup_time_planned'        => 0,
            'processing_time_planned'   => $durationMinutes,
            'status'                    => 'waiting',
        ]);

        return $order;
    }
}
