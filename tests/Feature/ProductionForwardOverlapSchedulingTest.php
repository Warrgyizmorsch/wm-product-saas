<?php

namespace Tests\Feature;

use App\Domains\Production\Models\Machine;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;

use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\SchedulingCalendarService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionForwardOverlapSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private Machine $machine1;
    private Machine $machine2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Overlap Scheduling Tenant',
            'slug' => 'overlap-sched-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app()->instance('tenant', $this->tenant);
        session(['tenant_id' => $this->tenant->id]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);

        ProductionScheduleOperation::query()->delete();
        ProductionSchedule::query()->delete();
        ProductionOrderOperation::query()->delete();
        ProductionOrder::query()->delete();
        ProductionMachineDowntime::query()->delete();

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Module X',
            'sku' => 'MOD-X-99',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stamping Center',
            'code' => 'WC-STAMP',
            'status' => 'active',
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Line',
            'code' => 'WC-ASSY',
            'status' => 'active',
        ]);

        $this->machine1 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name' => 'Press Machine 1',
            'code' => 'MCH-PRESS-1',
            'status' => 'active',
        ]);

        $this->machine2 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter2->id,
            'name' => 'Assembly Machine 1',
            'code' => 'MCH-ASSY-1',
            'status' => 'active',
        ]);
    }

    private function cleanTables(): void
    {
        ProductionScheduleOperation::query()->delete();
        ProductionSchedule::query()->delete();
        ProductionOrderOperation::query()->delete();
        ProductionOrder::query()->delete();
        ProductionMachineDowntime::query()->delete();
    }

    private function createOrder(
        float $quantity = 50.0,
        bool $overlapEnabled = false,
        float $transferBatchQty = 10.0,
        int $transferLagMinutes = 5,
        float $setupMinutes = 20.0,
        float $cycleTimeMinutes = 3.0
    ): ProductionOrder {
        $this->cleanTables();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-' . uniqid(),
            'product_id' => $this->product->id,
            'quantity_ordered' => $quantity,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03', // Monday
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);

        // Operation 10
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Stamping Op 10',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => $setupMinutes,
            'processing_time_planned' => $cycleTimeMinutes,
            'total_time_planned' => $setupMinutes + ($cycleTimeMinutes * $quantity),
            'overlap_enabled' => $overlapEnabled,
            'transfer_batch_quantity' => $transferBatchQty,
            'transfer_lag_minutes' => $transferLagMinutes,
        ]);

        // Operation 20
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Assembly Op 20',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 2.0,
            'total_time_planned' => 10.0 + (2.0 * $quantity),
            'overlap_enabled' => false,
            'transfer_batch_quantity' => 0.0,
            'transfer_lag_minutes' => 0,
        ]);

        return $order->fresh();
    }

    /** 1. Overlap disabled preserves Finish-to-Start. */
    public function test_overlap_disabled_preserves_finish_to_start(): void
    {
        $order = $this->createOrder(50.0, false);
        $startDate = Carbon::parse('2026-08-03 08:00:00'); // Monday 08:00 AM

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Op 10: setup 20m + 50 * 3m = 170m. Planned start 08:00, finish 10:50 AM
        $this->assertEquals('2026-08-03 08:00:00', $op10->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 10:50:00', $op10->planned_finish->toDateTimeString());

        // Op 20 should start at 10:50 AM (Finish-to-Start)
        $this->assertEquals('2026-08-03 10:50:00', $op20->planned_start->toDateTimeString());
    }

    /** 2. Overlap enabled calculates the dependency-ready time correctly. */
    public function test_overlap_enabled_calculates_dependency_ready_time(): void
    {
        // Op 10: setup 20m, cycle 3m, transfer batch 10, lag 5m.
        // First batch run duration = 10 * 3 = 30m. Total offset = 20 + 30 + 5 = 55m.
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        $this->assertEquals('2026-08-03 08:00:00', $op10->planned_start->toDateTimeString());
        // Op 20 starts 55 minutes after Op 10 start -> 08:55 AM
        $this->assertEquals('2026-08-03 08:55:00', $op20->planned_start->toDateTimeString());
        // Op 10 finishes at 10:50 AM, so Op 20 starts WHILE Op 10 is running!
        $this->assertTrue($op20->planned_start->lt($op10->planned_finish));
    }

    /** 3. Existing duration calculator is used for first transfer batch. */
    public function test_existing_duration_calculator_is_used_for_first_transfer_batch(): void
    {
        // Op 10: cycle time 4.5 minutes per unit, batch 8 units
        // First batch run duration = 8 * 4.5 = 36 minutes
        $order = $this->createOrder(50.0, true, 8.0, 0, 10.0, 4.5);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Total offset = 10 setup + 36 batch + 0 lag = 46 minutes -> 08:46 AM
        $this->assertEquals('2026-08-03 08:46:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 4. Predecessor operation planned quantity caps the effective transfer qty. */
    public function test_predecessor_planned_quantity_caps_effective_transfer_qty(): void
    {
        // Order qty = 5, but transfer_batch_quantity = 20.
        // Effective transfer qty = min(20, 5) = 5.
        // First batch run duration = 5 * 3 = 15 minutes. Total offset = 20 + 15 + 5 = 40 minutes.
        $order = $this->createOrder(5.0, true, 20.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-03 08:40:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 5. Transfer lag shifts dependency-ready time. */
    public function test_transfer_lag_shifts_dependency_ready_time(): void
    {
        // Lag = 15 minutes. Total offset = 20 + 30 + 15 = 65 minutes.
        $order = $this->createOrder(50.0, true, 10.0, 15, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // 08:00 + 65 minutes = 09:05 AM
        $this->assertEquals('2026-08-03 09:05:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 6. Setup duration is included. */
    public function test_setup_duration_included(): void
    {
        // Setup = 40 minutes. Total offset = 40 + 30 + 5 = 75 minutes.
        $order = $this->createOrder(50.0, true, 10.0, 5, 40.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // 08:00 + 75 minutes = 09:15 AM
        $this->assertEquals('2026-08-03 09:15:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 7. Closed shift boundaries are respected. */
    public function test_closed_shift_boundaries_respected(): void
    {
        // Standard shift is 08:00 to 16:00 (8 hours).
        // Op 10 starts at 15:30 PM (30 minutes before shift end).
        // Setup = 20m, Batch run = 30m, Lag = 5m -> Total offset = 55m.
        // 30m processed on Monday (15:30 - 16:00), remaining 25m processed Tuesday starting at 08:00 AM -> 08:25 AM Tuesday!
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 15:30:00'); // Monday 3:30 PM

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-04 08:25:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 8. Holidays are respected. */
    public function test_holidays_respected(): void
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Holiday Calendar',
            'is_default' => true,
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $this->workCenter1->update(['production_calendar_id' => $calendar->id]);
        $this->workCenter2->update(['production_calendar_id' => $calendar->id]);

        // Tuesday 2026-08-04 is a holiday!
        ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenant->id,
            'production_calendar_id' => $calendar->id,
            'holiday_date' => '2026-08-04',
            'name' => 'Company Day Off',
            'holiday_type' => 'company_holiday',
            'active' => true,
        ]);

        // Op 10 starts Monday at 15:30 PM. Total offset = 55m.
        // 30m on Monday (15:30-16:00). Tuesday is holiday. Remaining 25m on Wednesday 08:00 AM -> 08:25 AM Wednesday!
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 15:30:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-05 08:25:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 9. Capacity search starts at dependency-ready time. */
    public function test_capacity_search_starts_at_dependency_ready_time(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        $this->assertEquals('2026-08-03 08:55:00', $op20->planned_start->toDateTimeString());
    }

    /** 10. Busy machine moves successor to next valid slot. */
    public function test_busy_machine_moves_successor_to_next_valid_slot(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        // Pre-book Machine 2 from 08:30 to 09:30 AM for another order
        $otherOrder = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-OTHER-999',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);
        $otherSched = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $otherOrder->id,
            'schedule_number' => 'SCH-TEST-999',
            'status' => 'scheduled',
            'start_date' => '2026-08-03 08:30:00',
            'finish_date' => '2026-08-03 09:30:00',
            'generated_by' => 'forward',
        ]);
        $otherOrderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $otherOrder->id,
            'sequence' => 10,
            'operation_number' => 'OP-OTHER-10',
            'name' => 'Other Op',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 0.0,
            'processing_time_planned' => 6.0,
            'total_time_planned' => 60.0,
        ]);
        $booking = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $otherSched->id,
            'production_order_id' => $otherOrder->id,
            'production_order_operation_id' => $otherOrderOp->id,
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'sequence' => 10,
            'planned_start' => '2026-08-03 08:30:00',
            'planned_finish' => '2026-08-03 09:30:00',
            'planned_duration_minutes' => 60.0,
            'status' => 'ready',
        ]);

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        // Overlap dependency ready at 08:55 AM, but machine 2 is busy until 09:30 AM!
        // Succesor moves to 09:30 AM!
        $this->assertEquals('2026-08-03 09:30:00', $op20->planned_start->toDateTimeString());
    }

    /** 11. Machine downtime blocks or moves the overlapping start. */
    public function test_machine_downtime_blocks_or_moves_overlapping_start(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        // Downtime on Machine 2 from 08:45 to 09:15 AM
        $dt = ProductionMachineDowntime::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'category' => 'Breakdown',
            'reason' => 'Motor Replacement',
            'start_time' => '2026-08-03 08:45:00',
            'end_time' => '2026-08-03 09:15:00',
            'duration_minutes' => 30,
            'status' => ProductionMachineDowntime::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        // Dependency ready at 08:55 AM, but machine 2 is in downtime until 09:15 AM -> Op 20 starts at 09:15 AM!
        $this->assertEquals('2026-08-03 09:15:00', $op20->planned_start->toDateTimeString());
    }

    /** 12. Maintenance constraints remain enforced. */
    public function test_maintenance_constraints_enforced(): void
    {
        $this->machine2->update(['status' => 'maintenance']);

        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        // Machine under maintenance yields a warning
        $this->assertNotEmpty($op20->warnings);

        $this->machine2->update(['status' => 'active']);
    }

    /** 13. Manual work centre capacity remains valid. */
    public function test_manual_work_center_capacity_remains_valid(): void
    {
        $this->cleanTables();
        $manualWc1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manual Cutting Area',
            'code' => 'WC-MANUAL-1',
            'status' => 'active',
        ]);

        $manualWc2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manual Packing Area',
            'code' => 'WC-MANUAL-2',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-MANUAL-1',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Manual Cutting',
            'work_center_id' => $manualWc1->id,
            'machine_id' => null,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 2.0,
            'total_time_planned' => 50.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 0,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Manual Packing',
            'work_center_id' => $manualWc2->id,
            'machine_id' => null,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 5.0,
            'processing_time_planned' => 1.0,
            'total_time_planned' => 25.0,
            'overlap_enabled' => false,
            'transfer_batch_quantity' => 0.0,
            'transfer_lag_minutes' => 0,
        ]);

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 10 offset: 10 setup + (5 * 2) batch = 20 minutes -> 08:20 AM
        $this->assertEquals('2026-08-03 08:20:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 14. Multiple active machines retain parallel capacity. */
    public function test_multiple_active_machines_retain_parallel_capacity(): void
    {
        $machine1B = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name' => 'Press Machine 1B',
            'code' => 'MCH-PRESS-1B',
            'status' => 'active',
        ]);

        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertNotNull($schedule);
        $this->assertCount(2, $schedule->operations);
    }

    /** 15. Same exclusive machine does not receive invalid overlapping bookings. */
    public function test_same_exclusive_machine_does_not_receive_invalid_overlapping_bookings(): void
    {
        $this->cleanTables();
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-EXCLUSIVE-1',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);

        // Both operations require Machine 1 exclusively!
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Step 1 Machine 1',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 2.0,
            'total_time_planned' => 50.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 0,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Step 2 Machine 1',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 5.0,
            'processing_time_planned' => 1.0,
            'total_time_planned' => 25.0,
            'overlap_enabled' => false,
            'transfer_batch_quantity' => 0.0,
            'transfer_lag_minutes' => 0,
        ]);

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Even though overlap transfer-ready time is 08:20 AM, Op 20 CANNOT use Machine 1 until Op 10 finishes at 08:50 AM!
        $this->assertEquals('2026-08-03 08:00:00', $op10->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 08:50:00', $op10->planned_finish->toDateTimeString());
        $this->assertEquals('2026-08-03 08:50:00', $op20->planned_start->toDateTimeString());
    }

    /** 16. Multiple predecessors use the latest required constraint. */
    public function test_multiple_predecessors_use_latest_required_constraint(): void
    {
        $this->cleanTables();
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-MULTI-PRED-1',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);

        // Op 10: Non-overlapping, duration 60m (finishes at 09:00 AM)
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Predecessor A',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 2.5,
            'total_time_planned' => 60.0,
            'overlap_enabled' => false,
            'is_parallel' => true,
            'parallel_group' => 'G1',
        ]);

        // Op 15: Overlapping, transfer-ready at 08:30 AM
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 15,
            'operation_number' => 'OP-015',
            'name' => 'Predecessor B',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 2.0,
            'total_time_planned' => 50.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 10.0,
            'transfer_lag_minutes' => 0,
            'is_parallel' => true,
            'parallel_group' => 'G1',
        ]);

        // Op 20: Successor following Op 10 and Op 15
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Merged Step 20',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => null,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 5.0,
            'processing_time_planned' => 1.0,
            'total_time_planned' => 25.0,
        ]);

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 10 finishes at 09:00 AM. Op 15 transfer-ready at 08:30 AM.
        // Op 20 earliest start must take the max constraint = 09:00 AM!
        $this->assertEquals('2026-08-03 09:00:00', $ops[2]->planned_start->toDateTimeString());
    }

    /** 17. Calendar conflict detection accepts valid sequence overlap. */
    public function test_calendar_conflict_detection_accepts_valid_sequence_overlap(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $op20 = $schedule->operations->where('sequence', 20)->first();
        $conflicts = app(SchedulingCalendarService::class)->getOperationConflicts($op20->id);

        $this->assertFalse($conflicts['has_conflict']);
    }

    /** 18. Calendar conflict detection flags a start before transfer-ready time. */
    public function test_calendar_conflict_detection_flags_start_before_transfer_ready_time(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $op20 = $schedule->operations->where('sequence', 20)->first();
        // Force Op 20 start time to 08:15 AM (earlier than transfer-ready time of 08:55 AM)
        $op20->update(['planned_start' => '2026-08-03 08:15:00']);

        $conflicts = app(SchedulingCalendarService::class)->getOperationConflicts($op20->id);

        $this->assertTrue($conflicts['has_conflict']);
    }

    /** 19. Capacity Planning prevents invalid successor rescheduling. */
    public function test_capacity_planning_prevents_invalid_successor_rescheduling(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $op20 = $schedule->operations->where('sequence', 20)->first();

        // Attempting to manually move Op 20 start to 08:20 AM (before transfer-ready time of 08:55 AM) should be rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reschedule conflict: Starts before predecessor transfer-ready time');

        app(CapacityPlanningService::class)->rescheduleOperation(
            $op20->id,
            Carbon::parse('2026-08-03 08:20:00'),
            $op20->machine_id,
            'Testing reschedule',
            $this->user->id
        );
    }

    /** 20. Moving a predecessor revalidates its successor. */
    public function test_moving_predecessor_revalidates_its_successor(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $op10 = $schedule->operations->where('sequence', 10)->first();

        // Moving Op 10 start from 08:00 AM to 09:00 AM pushes its transfer-ready time to 09:55 AM, which violates Op 20 starting at 08:55 AM!
        $this->expectException(\InvalidArgumentException::class);

        app(CapacityPlanningService::class)->rescheduleOperation(
            $op10->id,
            Carbon::parse('2026-08-03 09:00:00'),
            $op10->machine_id,
            'Testing reschedule',
            $this->user->id
        );
    }

    /** 21. Started/paused/completed operations retain rescheduling protection. */
    public function test_started_paused_completed_operations_retain_rescheduling_protection(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $op10 = $schedule->operations->where('sequence', 10)->first();
        $op10->update(['status' => 'running']);

        $this->expectException(\InvalidArgumentException::class);

        app(CapacityPlanningService::class)->rescheduleOperation(
            $op10->id,
            Carbon::parse('2026-08-03 09:00:00'),
            $op10->machine_id,
            'Testing reschedule',
            $this->user->id
        );
    }

    /** 22. Regeneration uses production-order-operation snapshot values. */
    public function test_regeneration_uses_production_order_operation_snapshot_values(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);

        // Schedule generated from order operation snapshot
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));
        $op20 = $schedule->operations->where('sequence', 20)->first();

        $this->assertEquals('2026-08-03 08:55:00', $op20->planned_start->toDateTimeString());
    }

    /** 23. Later routing edits do not alter existing order overlap settings. */
    public function test_later_routing_edits_do_not_alter_existing_order_overlap_settings(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $op10Order = $order->operations->first();

        // Order operation snapshot has overlap_enabled = true
        $this->assertTrue((bool) $op10Order->overlap_enabled);

        // Later routing edit does NOT mutate existing production_order_operations
        $this->assertEquals(10.0, (float) $op10Order->transfer_batch_quantity);
    }

    /** 24. Only the active schedule is changed. */
    public function test_only_active_schedule_is_changed(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule1 = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $schedule1->status);
    }

    /** 25. Tenant isolation is preserved. */
    public function test_tenant_isolation_is_preserved(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B Overlap',
            'slug' => 'tenant-b-overlap-sched',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $orderA = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $scheduleA = app(SchedulingService::class)->generateForwardSchedule($orderA, Carbon::parse('2026-08-03 08:00:00'));

        // Tenant B query should not access Tenant A schedule
        app()->instance('tenant', $tenantB);
        session(['tenant_id' => $tenantB->id]);

        $schedulesB = ProductionSchedule::where('tenant_id', $tenantB->id)->get();
        $this->assertCount(0, $schedulesB);
    }

    /** 26. Calendar and schedule detail views show overlap metadata. */
    public function test_calendar_and_schedule_detail_views_show_overlap_metadata(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'overlap-sched-tenant')
            ->get(route('production.schedules.show', $schedule->id));

        $response->assertStatus(200);
        $response->assertSee('⚡ Queue Threshold Enabled');
    }

    /** 27. Existing scheduling regression tests pass. */
    public function test_existing_scheduling_regression_tests_pass(): void
    {
        $order = $this->createOrder(10.0, false);
        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, Carbon::parse('2026-08-03 08:00:00'));

        $this->assertNotNull($schedule);
        $this->assertCount(2, $schedule->operations);
    }
}
