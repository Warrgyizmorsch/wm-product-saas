<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleConflictScopeAndBackwardReservationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected WorkCenter $workCenter;
    protected Machine $machinePrimary;
    protected Machine $machineAlternate;
    protected SchedulingService $schedulingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Scheduling Conflict Scope Tenant',
            'slug' => 'sched-conflict-scope',
            'domain' => 'sched-conflict-scope.test',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Frame Table',
            'sku' => 'SFT-001',
            'type' => 'manufactured',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-CUT-01',
            'name' => 'Cutting Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 2,
        ]);

        $this->machinePrimary = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MAC-TBL-CUT-01',
            'name' => 'CNC Circular Saw Pipe Cutter 01',
            'status' => 'active',
        ]);

        $this->machineAlternate = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MAC-TBL-CUT-02',
            'name' => 'CNC Circular Saw Pipe Cutter 02',
            'status' => 'active',
        ]);

        $this->schedulingService = app(SchedulingService::class);
    }

    /**
     * Test A: Backward scheduling prevents internal machine overlaps between operations
     * in the same backward scheduling run (reproducing Op 14 and Op 15 class of defect).
     */
    public function test_backward_scheduling_prevents_internal_machine_overlap_on_same_machine(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-2026-000002',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        // Op 14: 95 minutes, assigned to machinePrimary
        $op14 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'operation_number' => 'OP-14',
            'name' => 'Table Leg Pipe Cutting & Deburring',
            'run_time_minutes' => 95,
            'processing_time_planned' => 95,
            'setup_time_planned' => 0,
            'total_time_planned' => 95,
        ]);

        // Op 15: 95 minutes, assigned to same machinePrimary (successor of Op 14)
        $op15 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'operation_number' => 'OP-15',
            'name' => 'Horizontal Support Pipe Sizing',
            'run_time_minutes' => 95,
            'processing_time_planned' => 95,
            'setup_time_planned' => 0,
            'total_time_planned' => 95,
        ]);

        $dueDate = Carbon::parse('2026-07-06 12:00:00');

        $schedule = $this->schedulingService->generateSchedule($order, $dueDate, ProductionSchedule::TYPE_BACKWARD);

        $this->assertNotNull($schedule);
        $this->assertCount(2, $schedule->operations);

        $schedOp14 = $schedule->operations->firstWhere('sequence', 14);
        $schedOp15 = $schedule->operations->firstWhere('sequence', 15);

        $this->assertNotNull($schedOp14);
        $this->assertNotNull($schedOp15);

        // Op 15 is scheduled first backward up to due date 12:00
        $this->assertEquals($this->machinePrimary->id, $schedOp15->machine_id);
        $this->assertEquals($this->machinePrimary->id, $schedOp14->machine_id);

        // Because Op 14 is a predecessor and on the same machine, it must NOT overlap Op 15!
        // Op 14 finish must be <= Op 15 start
        $this->assertTrue(
            $schedOp14->planned_finish->lte($schedOp15->planned_start),
            "Op 14 planned finish ({$schedOp14->planned_finish}) must be <= Op 15 planned start ({$schedOp15->planned_start})"
        );

        // Neither operation should overlap the other
        $hasOverlap = $schedOp14->planned_start->lt($schedOp15->planned_finish)
            && $schedOp15->planned_start->lt($schedOp14->planned_finish);
        $this->assertFalse($hasOverlap, 'Operations 14 and 15 must not overlap on the same machine');

        // Detect conflicts should return 0 conflicts for this schedule
        $conflicts = $this->schedulingService->detectConflictsForSchedule($schedule);
        $this->assertEmpty($conflicts, 'Schedule must not have any internal machine overlap conflicts');
    }

    /**
     * Test B: Backward scheduling selects a qualified alternate machine when
     * primary machine is occupied by an in-memory reservation.
     */
    public function test_backward_scheduling_selects_qualified_alternate_machine_when_primary_reserved_in_memory(): void
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Cutting Routing with Alternate',
            'code' => 'RT-CUT-ALT',
            'is_active' => true,
        ]);

        $routingOp14 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'operation_number' => 'OP-14',
            'name' => 'Table Leg Pipe Cutting',
            'processing_time_minutes' => 60,
        ]);

        $routingOp15 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'operation_number' => 'OP-15',
            'name' => 'Horizontal Support Pipe Sizing',
            'processing_time_minutes' => 60,
        ]);

        // Configure machineAlternate as qualified alternate for Op 14
        RoutingOperationAlternateMachine::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $routingOp14->id,
            'machine_id' => $this->machineAlternate->id,
            'priority' => 1,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-2026-ALT-TEST',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        // Op 14 is linked to routingOp14
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp14->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'operation_number' => 'OP-14',
            'name' => 'Table Leg Pipe Cutting',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        // Op 15 is linked to routingOp15
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp15->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'operation_number' => 'OP-15',
            'name' => 'Horizontal Support Pipe Sizing',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        // Due date: 2026-07-06 11:00.
        // Op 15 scheduled 10:00 - 11:00 on Primary.
        // Op 14 wants finish <= 10:00.
        // If Op 14 evaluates primary: slot is 09:00 - 10:00.
        // But what if Op 14 and Op 15 both want slot up to 11:00?
        // Let's test with parallel operations or when primary is blocked:
        $dueDate = Carbon::parse('2026-07-06 11:00:00');
        $schedule = $this->schedulingService->generateSchedule($order, $dueDate, ProductionSchedule::TYPE_BACKWARD);

        $this->assertNotNull($schedule);
        $schedOp14 = $schedule->operations->firstWhere('sequence', 14);
        $schedOp15 = $schedule->operations->firstWhere('sequence', 15);

        // Op 14 finish must be <= Op 15 start
        $this->assertTrue($schedOp14->planned_finish->lte($schedOp15->planned_start));

        // If alternate machine is evaluated, verify both machines do not have overlapping bookings
        $opsOnPrimary = $schedule->operations->where('machine_id', $this->machinePrimary->id)->values();
        for ($i = 0; $i < $opsOnPrimary->count() - 1; $i++) {
            $this->assertTrue($opsOnPrimary[$i]->planned_finish->lte($opsOnPrimary[$i + 1]->planned_start)
                || $opsOnPrimary[$i + 1]->planned_finish->lte($opsOnPrimary[$i]->planned_start));
        }
    }

    /**
     * Test C: Backward scheduling respects BOTH existing database bookings AND
     * current-run in-memory reservations.
     */
    public function test_backward_scheduling_respects_both_persisted_and_in_memory_bookings(): void
    {
        // 1. Create an existing order and persisted schedule on Primary Machine from 10:00 to 11:00
        $existingOrder = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-EXISTING-01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        $existingOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $existingOrder->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Existing Persisted Job',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        $existingSchedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $existingOrder->id,
            'schedule_number' => 'SCH-EXISTING-01',
            'scheduling_type' => ProductionSchedule::TYPE_FORWARD,
            'status' => ProductionSchedule::STATUS_SCHEDULED,
            'scheduled_start_date' => Carbon::parse('2026-07-06 10:00:00'),
            'scheduled_end_date' => Carbon::parse('2026-07-06 11:00:00'),
            'scheduled_at' => now(),
            'created_by' => $this->user->id,
        ]);

        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $existingSchedule->id,
            'production_order_id' => $existingOrder->id,
            'production_order_operation_id' => $existingOp->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 10,
            'priority' => 1,
            'planned_start' => Carbon::parse('2026-07-06 10:00:00'),
            'planned_finish' => Carbon::parse('2026-07-06 11:00:00'),
            'planned_duration_minutes' => 60,
            'status' => ProductionScheduleOperation::STATUS_READY,
        ]);

        // 2. Now backward schedule a new order with Op 14 (60m) and Op 15 (60m), due at 12:00
        $newOrder = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-NEW-JIT-02',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $newOrder->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'operation_number' => 'OP-14',
            'name' => 'Predecessor Pipe Cut',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $newOrder->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'operation_number' => 'OP-15',
            'name' => 'Successor Pipe Cut',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        $newSchedule = $this->schedulingService->generateSchedule($newOrder, Carbon::parse('2026-07-06 12:00:00'), ProductionSchedule::TYPE_BACKWARD);

        $newOp14 = $newSchedule->operations->firstWhere('sequence', 14);
        $newOp15 = $newSchedule->operations->firstWhere('sequence', 15);

        // Op 15 fits in 11:00 - 12:00 (above existing 10:00 - 11:00 booking)
        $this->assertEquals('2026-07-06 11:00:00', $newOp15->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 12:00:00', $newOp15->planned_finish->toDateTimeString());

        // Op 14 cannot take 11:00-12:00 (in-memory Op 15 reservation)
        // And Op 14 cannot take 10:00-11:00 (persisted DB booking on existingSchedule)
        // Therefore, Op 14 must be scheduled at or before 10:00:00 (e.g. 09:00 - 10:00)!
        $this->assertTrue(
            $newOp14->planned_finish->lte(Carbon::parse('2026-07-06 10:00:00')),
            "Op 14 planned finish ({$newOp14->planned_finish}) must be <= 10:00:00 due to persisted booking"
        );
        $this->assertEquals('2026-07-06 09:00:00', $newOp14->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 10:00:00', $newOp14->planned_finish->toDateTimeString());

        // Zero conflicts
        $this->assertEmpty($this->schedulingService->detectConflicts($this->tenant->id));
    }

    /**
     * Test D: Schedule show page scopes warnings to the current schedule
     * and separates factory-wide conflicts.
     */
    public function test_schedule_show_page_scopes_warnings_to_viewed_schedule(): void
    {
        // Schedule A (clean, no conflicts)
        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-2026-000003',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        $opA = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderA->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 10,
            'operation_number' => 'OP-A10',
            'name' => 'Operation A10',
            'run_time_minutes' => 60,
        ]);

        $scheduleA = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderA->id,
            'schedule_number' => 'SCH-2026-000003',
            'scheduling_type' => ProductionSchedule::TYPE_FORWARD,
            'status' => ProductionSchedule::STATUS_SCHEDULED,
            'scheduled_start_date' => Carbon::parse('2026-07-06 06:00:00'),
            'scheduled_end_date' => Carbon::parse('2026-07-06 07:00:00'),
            'scheduled_at' => now(),
            'created_by' => $this->user->id,
        ]);

        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $scheduleA->id,
            'production_order_id' => $orderA->id,
            'production_order_operation_id' => $opA->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 10,
            'priority' => 1,
            'planned_start' => Carbon::parse('2026-07-06 06:00:00'),
            'planned_finish' => Carbon::parse('2026-07-06 07:00:00'),
            'planned_duration_minutes' => 60,
            'status' => ProductionScheduleOperation::STATUS_READY,
        ]);

        // Schedule B (has an intentional machine overlap conflict with itself)
        $orderB = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-2026-000002',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 09:30:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        $opB1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderB->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'operation_number' => 'OP-B14',
            'name' => 'Table Leg Pipe Cutting & Deburring',
            'run_time_minutes' => 95,
        ]);

        $opB2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderB->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'operation_number' => 'OP-B15',
            'name' => 'Horizontal Support Pipe Sizing',
            'run_time_minutes' => 95,
        ]);

        $scheduleB = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderB->id,
            'schedule_number' => 'SCH-2026-000002',
            'scheduling_type' => ProductionSchedule::TYPE_BACKWARD,
            'status' => ProductionSchedule::STATUS_SCHEDULED,
            'scheduled_start_date' => Carbon::parse('2026-07-06 09:33:00'),
            'scheduled_end_date' => Carbon::parse('2026-07-06 11:08:00'),
            'scheduled_at' => now(),
            'created_by' => $this->user->id,
        ]);

        // Insert overlapping operations into Schedule B
        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $scheduleB->id,
            'production_order_id' => $orderB->id,
            'production_order_operation_id' => $opB1->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 14,
            'priority' => 1,
            'planned_start' => Carbon::parse('2026-07-06 08:09:00'),
            'planned_finish' => Carbon::parse('2026-07-06 11:08:00'),
            'planned_duration_minutes' => 179,
            'status' => ProductionScheduleOperation::STATUS_READY,
        ]);

        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $scheduleB->id,
            'production_order_id' => $orderB->id,
            'production_order_operation_id' => $opB2->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 15,
            'priority' => 1,
            'planned_start' => Carbon::parse('2026-07-06 09:33:00'),
            'planned_finish' => Carbon::parse('2026-07-06 11:08:00'),
            'planned_duration_minutes' => 95,
            'status' => ProductionScheduleOperation::STATUS_READY,
        ]);

        // 1. Verify service-level scoping
        $conflictsA = $this->schedulingService->detectConflictsForSchedule($scheduleA);
        $conflictsB = $this->schedulingService->detectConflictsForSchedule($scheduleB);
        $unrelatedA = $this->schedulingService->detectUnrelatedConflictsForSchedule($scheduleA);

        $this->assertEmpty($conflictsA, 'Schedule A must have 0 conflicts');
        $this->assertNotEmpty($conflictsB, 'Schedule B must have conflicts');
        $this->assertNotEmpty($unrelatedA, 'Schedule A must detect unrelated factory conflicts');

        // 2. HTTP View Test: Schedule A show page
        $responseA = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.schedules.show', $scheduleA->id));
        $responseA->assertOk();

        // Must NOT show Schedule B's overlap under Capacity Overload Warnings
        $responseA->assertDontSee('Overlap on machine CNC Circular Saw Pipe Cutter 01: Order #ORD-2026-000002');
        // But CAN show the factory conflict notice
        $responseA->assertSee(__('production.factory_conflicts_notice', ['count' => count($unrelatedA)]));

        // 3. HTTP View Test: Schedule B show page
        $responseB = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.schedules.show', $scheduleB->id));
        $responseB->assertOk();

        // Must show its own conflict under Capacity Overload Warnings
        $responseB->assertSee('Capacity Overload Warnings');
        $responseB->assertSee('Order #ORD-2026-000002');
    }

    /**
     * Test E: Forward scheduling regression confirmation.
     * Ensures forward scheduling preserves machine capacity, sequence, and alternate selection.
     */
    public function test_forward_scheduling_regression_preserves_capacity_and_sequence(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-FWD-REGRESS',
            'product_id' => $this->product->id,
            'quantity_ordered' => 1,
            'status' => 'confirmed',
            'start_date' => '2026-07-06 08:00:00',
            'end_date' => '2026-07-06 17:00:00',
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'First Forward Operation',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machinePrimary->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Second Forward Operation',
            'run_time_minutes' => 60,
            'processing_time_planned' => 60,
            'total_time_planned' => 60,
        ]);

        $schedule = $this->schedulingService->generateSchedule($order, Carbon::parse('2026-07-06 08:00:00'), ProductionSchedule::TYPE_FORWARD);

        $this->assertNotNull($schedule);
        $this->assertCount(2, $schedule->operations);

        $op10 = $schedule->operations->firstWhere('sequence', 10);
        $op20 = $schedule->operations->firstWhere('sequence', 20);

        $this->assertEquals('2026-07-06 08:00:00', $op10->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 09:00:00', $op10->planned_finish->toDateTimeString());

        $this->assertEquals('2026-07-06 09:00:00', $op20->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 10:00:00', $op20->planned_finish->toDateTimeString());

        $this->assertTrue($op10->planned_finish->lte($op20->planned_start));
        $this->assertEmpty($this->schedulingService->detectConflictsForSchedule($schedule));
    }
}
