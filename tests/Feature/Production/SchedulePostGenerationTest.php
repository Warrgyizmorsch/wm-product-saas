<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulePostGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $planner;
    private Product $product;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private Machine $primaryMachine1;
    private Machine $alternateMachine1;
    private Machine $unqualifiedMachine1;
    private SchedulingService $schedulingService;
    private CapacityPlanningService $capacityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'SaaS Precision Mfg',
            'slug'   => 'saas-precision',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->planner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Production Planner',
            'email'     => 'planner@saasprecision.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Precision Engine Block',
            'sku'       => 'FG-ENG-001',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Standard Mon-Fri Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => true,
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id'              => $this->tenant->id,
            'name'                   => 'Machining Center A',
            'code'                   => 'WC-MACH-A',
            'efficiency_percentage'  => 100.0,
            'status'                 => 'active',
            'production_calendar_id' => $cal->id,
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id'              => $this->tenant->id,
            'name'                   => 'Assembly Center B',
            'code'                   => 'WC-ASSY-B',
            'efficiency_percentage'  => 100.0,
            'status'                 => 'active',
            'production_calendar_id' => $cal->id,
        ]);

        $this->primaryMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => 'CNC Mill 01 (Primary)',
            'code'           => 'CNC-01',
            'status'         => 'active',
        ]);

        $this->alternateMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => 'CNC Mill 02 (Alternate)',
            'code'           => 'CNC-02',
            'status'         => 'active',
        ]);

        $this->unqualifiedMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => 'Unqualified Mill 03',
            'code'           => 'CNC-99',
            'status'         => 'active',
        ]);

        $this->schedulingService = app(SchedulingService::class);
        $this->capacityService   = app(CapacityPlanningService::class);
    }

    private function createMultiOpOrder(string $number = 'ORD-2001', int $qty = 10): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => $number,
            'product_id'       => $this->product->id,
            'quantity_ordered' => $qty,
            'status'           => ProductionOrder::STATUS_RELEASED,
            'start_date'       => now()->addDay()->startOfDay()->addHours(8),
            'end_date'         => now()->addDays(5)->endOfDay(),
        ]);

        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $this->product->id,
            'name'       => 'Engine Block Routing',
            'status'     => 'approved',
        ]);

        $routingOp1 = RoutingOperation::create([
            'tenant_id'               => $this->tenant->id,
            'routing_id'              => $routing->id,
            'operation_number'        => 'OP-10',
            'name'                    => 'Milling Phase 1',
            'sequence'                => 10,
            'work_center_id'          => $this->workCenter1->id,
            'machine_id'              => $this->primaryMachine1->id,
            'setup_time_minutes'      => 30,
            'processing_time_minutes' => 6, // 60 mins run for 10 units = 90 mins total
        ]);

        RoutingOperationAlternateMachine::create([
            'tenant_id'            => $this->tenant->id,
            'routing_operation_id' => $routingOp1->id,
            'machine_id'           => $this->alternateMachine1->id,
            'priority'             => 2,
        ]);

        $routingOp2 = RoutingOperation::create([
            'tenant_id'               => $this->tenant->id,
            'routing_id'              => $routing->id,
            'operation_number'        => 'OP-20',
            'name'                    => 'Assembly Phase 2',
            'sequence'                => 20,
            'work_center_id'          => $this->workCenter2->id,
            'setup_time_minutes'      => 15,
            'processing_time_minutes' => 4, // 40 mins run = 55 mins total
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $this->tenant->id,
            'production_order_id'     => $order->id,
            'routing_operation_id'    => $routingOp1->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => 'Milling Phase 1',
            'work_center_id'          => $this->workCenter1->id,
            'machine_id'              => $this->primaryMachine1->id,
            'setup_time_planned'      => 30,
            'processing_time_planned' => 6,
            'total_time_planned'      => 90,
            'status'                  => 'waiting',
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $this->tenant->id,
            'production_order_id'     => $order->id,
            'routing_operation_id'    => $routingOp2->id,
            'sequence'                => 20,
            'operation_number'        => 'OP20',
            'name'                    => 'Assembly Phase 2',
            'work_center_id'          => $this->workCenter2->id,
            'setup_time_planned'      => 15,
            'processing_time_planned' => 4,
            'total_time_planned'      => 55,
            'status'                  => 'waiting',
        ]);

        return $order;
    }

    /**
     * Test 1: Initial Forward Schedule populates baseline_start and baseline_finish.
     */
    public function test_forward_schedule_populates_baseline_dates(): void
    {
        $order = $this->createMultiOpOrder('ORD-BASE-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);

        $this->assertCount(2, $schedule->operations);

        foreach ($schedule->operations as $op) {
            $this->assertNotNull($op->baseline_start);
            $this->assertNotNull($op->baseline_finish);
            $this->assertEquals($op->planned_start->toDateTimeString(), $op->baseline_start->toDateTimeString());
            $this->assertEquals($op->planned_finish->toDateTimeString(), $op->baseline_finish->toDateTimeString());
            $this->assertEquals(0.0, $op->start_variance_minutes);
            $this->assertEquals(0.0, $op->finish_variance_minutes);
            $this->assertEquals(1, $op->version);
            $this->assertFalse($op->manual_override);
        }
    }

    /**
     * Test 2: Manual Isolated shift updates planned_start but preserves baseline dates.
     */
    public function test_manual_isolated_shift_preserves_baseline_and_records_change_log(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-BASE-02');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();

        $originalBaselineStart = $op10->baseline_start->copy();
        $originalBaselineFinish = $op10->baseline_finish->copy();

        // Shift OP10 by 30 mins earlier (from 08:00 to 07:30) so it doesn't overlap OP20
        $newStart = $startDate->copy()->subMinutes(30);

        $result = $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $newStart,
            $this->primaryMachine1->id,
            ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
            'Planner requested delay'
        );

        $this->assertTrue($result['success']);

        $op10->refresh();

        // Baseline must remain unchanged
        $this->assertEquals($originalBaselineStart->toDateTimeString(), $op10->baseline_start->toDateTimeString());
        $this->assertEquals($originalBaselineFinish->toDateTimeString(), $op10->baseline_finish->toDateTimeString());

        // Planned dates must be updated
        $this->assertEquals($newStart->toDateTimeString(), $op10->planned_start->toDateTimeString());
        $this->assertTrue($op10->manual_override);
        $this->assertEquals(2, $op10->version);
        $this->assertEquals(-30.0, $op10->start_variance_minutes);

        // Verify audit log entry
        $log = ProductionScheduleChangeLog::where('production_schedule_operation_id', $op10->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(ProductionScheduleChangeLog::CHANGE_TYPE_MANUAL_SHIFT, $log->change_type);
        $this->assertEquals('isolated', $log->shift_mode);
        $this->assertEquals('Planner requested delay', $log->reason);
        $this->assertEquals($this->planner->id, $log->changed_by);
    }

    /**
     * Test 3: Machine qualification validation permits primary and approved alternate machines, rejecting unqualified machines.
     */
    public function test_machine_qualification_validation(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-QUAL-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();

        // Primary Machine allowed
        $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->subMinutes(20),
            $this->primaryMachine1->id
        );

        // Approved Alternate Machine allowed
        $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->subMinutes(10),
            $this->alternateMachine1->id
        );

        // Unqualified Machine rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an approved primary or alternate machine');

        $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->subMinutes(5),
            $this->unqualifiedMachine1->id
        );
    }

    /**
     * Test 4: Operation Lock toggle pins operation and blocks automated/manual moves.
     */
    public function test_operation_lock_prevents_unauthorized_moves(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-LOCK-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();

        // Toggle lock on OP10
        $lockedOp = $this->capacityService->toggleOperationLock($op10->id, $this->planner->id);
        $this->assertTrue($lockedOp->locked);

        // Attempting to move locked OP10 throws InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('locked and cannot be moved');

        $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->addHours(2)
        );
    }

    /**
     * Test 5: Ripple shift recalculates downstream successor operations.
     */
    public function test_ripple_shift_recalculates_downstream_successors(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-RIPPLE-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();
        $op20 = $schedule->operations()->where('sequence', 20)->first();

        $originalOp20Start = $op20->planned_start->copy();

        // Shift OP10 forward by 2 hours using Ripple mode
        $newOp10Start = $startDate->copy()->addHours(2);

        $result = $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $newOp10Start,
            $this->primaryMachine1->id,
            ProductionScheduleChangeLog::SHIFT_MODE_RIPPLE,
            'Delayed due to material staging'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['adjusted_operations_count']);

        $op10->refresh();
        $op20->refresh();

        // OP10 start moved by 2 hours
        $this->assertEquals($newOp10Start->toDateTimeString(), $op10->planned_start->toDateTimeString());

        // OP20 must start after OP10 finish
        $this->assertTrue($op20->planned_start->gte($op10->planned_finish));
        $this->assertTrue($op20->planned_start->gt($originalOp20Start));

        // Verify change log records for both operations
        $logs = ProductionScheduleChangeLog::where('production_schedule_id', $schedule->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->contains('change_type', ProductionScheduleChangeLog::CHANGE_TYPE_MANUAL_SHIFT));
        $this->assertTrue($logs->contains('change_type', ProductionScheduleChangeLog::CHANGE_TYPE_RIPPLE_SHIFT));
    }

    /**
     * Test 6: Locked downstream successor blocks Ripple shift atomically.
     */
    public function test_locked_downstream_successor_blocks_ripple_shift(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-RIPPLE-LOCK-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();
        $op20 = $schedule->operations()->where('sequence', 20)->first();

        // Lock successor OP20 at its initial timing
        $this->capacityService->toggleOperationLock($op20->id, $this->planner->id);

        // Attempting to shift OP10 forward by 5 hours in Ripple mode would force OP20 to move
        // Since OP20 is locked, the transaction must roll back with a LOCKED_OPERATION_CONFLICT exception
        $newOp10Start = $startDate->copy()->addHours(5);

        try {
            $this->capacityService->rescheduleOperationWithMode(
                $op10->id,
                $newOp10Start,
                $this->primaryMachine1->id,
                ProductionScheduleChangeLog::SHIFT_MODE_RIPPLE,
                'Severe delay'
            );
            $this->fail('Expected exception for locked downstream operation conflict was not thrown.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('LOCKED_OPERATION_CONFLICT', $e->getMessage());
        }

        // Verify transaction rolled back: OP10 remains at original start
        $op10->refresh();
        $this->assertEquals($startDate->toDateTimeString(), $op10->planned_start->toDateTimeString());
    }

    /**
     * Test 7: Optimistic concurrency rejects stale version updates.
     */
    public function test_optimistic_concurrency_rejects_stale_versions(): void
    {
        $this->actingAs($this->planner);
        $order = $this->createMultiOpOrder('ORD-CONCURRENCY-01');
        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);

        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $op10 = $schedule->operations()->where('sequence', 10)->first();

        // First adjustment with version = 1 succeeds and bumps version to 2
        $result = $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->subMinutes(15),
            $this->primaryMachine1->id,
            ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
            'First update',
            $this->planner->id,
            1 // Expected version = 1
        );

        $this->assertTrue($result['success']);
        $op10->refresh();
        $this->assertEquals(2, $op10->version);

        // Stale update with expected_version = 1 must throw CONCURRENCY_CONFLICT
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CONCURRENCY_CONFLICT');

        $this->capacityService->rescheduleOperationWithMode(
            $op10->id,
            $startDate->copy()->subMinutes(30),
            $this->primaryMachine1->id,
            ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
            'Stale update',
            $this->planner->id,
            1 // Stale expected version!
        );
    }
}
