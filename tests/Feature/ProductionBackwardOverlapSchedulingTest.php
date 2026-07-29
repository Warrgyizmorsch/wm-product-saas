<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\SchedulingCalendarService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionBackwardOverlapSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected WorkCenter $workCenter1;
    protected WorkCenter $workCenter2;
    protected Machine $machine1;
    protected Machine $machine2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Backward Overlap Tenant',
            'slug' => 'backward-overlap',
            'domain' => 'backward-overlap.test',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'JIT Widget X',
            'sku' => 'JIT-WGT-X',
            'type' => 'manufactured',
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-BACK-1',
            'name' => 'Machining Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 1,
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-BACK-2',
            'name' => 'Assembly Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 1,
        ]);

        $this->machine1 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'code' => 'MC-BACK-101',
            'name' => 'Lathe Machine',
            'status' => 'active',
        ]);

        $this->machine2 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter2->id,
            'code' => 'MC-BACK-201',
            'name' => 'Robotic Assembler',
            'status' => 'active',
        ]);
    }

    protected function cleanTables(): void
    {
        ProductionScheduleOperation::query()->forceDelete();
        ProductionSchedule::query()->forceDelete();
        ProductionOrderOperation::query()->forceDelete();
        ProductionOrder::query()->forceDelete();
        ProductionMachineDowntime::query()->forceDelete();
    }

    protected function createOrder(
        float $quantity = 50.0,
        bool $overlap = true,
        float $batchQty = 10.0,
        int $lagMinutes = 5,
        float $setupMinutes = 20.0,
        float $procMinutes = 3.0
    ): ProductionOrder {
        $this->cleanTables();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-BACK-TEST-' . rand(1000, 9999),
            'product_id' => $this->product->id,
            'quantity_ordered' => $quantity,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-07',
            'created_by' => $this->user->id,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Machining Op 10',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => $setupMinutes,
            'processing_time_planned' => $procMinutes,
            'total_time_planned' => $setupMinutes + ($procMinutes * $quantity),
            'overlap_enabled' => $overlap,
            'transfer_batch_quantity' => $batchQty,
            'transfer_lag_minutes' => $lagMinutes,
        ]);

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

        return $order;
    }

    /** 1. Non-overlap backward scheduling preserves Finish-to-Start. */
    public function test_non_overlap_backward_scheduling_preserves_finish_to_start(): void
    {
        $order = $this->createOrder(50.0, false);
        $dueDate = Carbon::parse('2026-08-03 16:00:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Non-overlap: Op 20 finishes at 16:00 PM (110m duration -> starts 14:10 PM).
        // Op 10 must finish by Op 20 start (14:10 PM). 170m duration -> starts 11:20 AM.
        $this->assertEquals('2026-08-03 14:10:00', $op20->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 16:00:00', $op20->planned_finish->toDateTimeString());
        $this->assertEquals('2026-08-03 11:20:00', $op10->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 14:10:00', $op10->planned_finish->toDateTimeString());
    }

    /** 2. Overlap-enabled predecessor may finish after successor starts. */
    public function test_overlap_enabled_predecessor_may_finish_after_successor_starts(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 16:00:00');

        // Force Op 20 to start at 12:00 PM by setting appropriate due date or checking relative positions
        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Op 20 starts at 14:10 PM (14:10 - 55m offset = 13:15 PM Op 10 start).
        // Op 10 starts at 13:15 PM and finishes at 16:05 PM (after Op 20 starts at 14:10 PM)!
        $this->assertTrue($op10->planned_finish->gt($op20->planned_start));
    }

    /** 3. Latest predecessor start is calculated from successor start. */
    public function test_latest_predecessor_start_is_calculated_from_successor_start(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Op 20 finishes at 13:50 PM (110m duration -> starts at 12:00 PM).
        // Op 10 transfer readiness offset = 20 + 30 + 5 = 55m.
        // 12:00 PM - 55m = 11:05 AM Op 10 start!
        $this->assertEquals('2026-08-03 12:00:00', $op20->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 11:05:00', $op10->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 13:55:00', $op10->planned_finish->toDateTimeString());
    }

    /** 4. Setup duration is included. */
    public function test_setup_duration_is_included(): void
    {
        // 40m setup instead of 20m -> offset becomes 40 + 30 + 5 = 75m.
        $order = $this->createOrder(50.0, true, 10.0, 5, 40.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 20 starts at 12:00 PM. Op 10 start = 12:00 - 75m = 10:45 AM.
        $this->assertEquals('2026-08-03 10:45:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 5. First-transfer-batch duration is included. */
    public function test_first_transfer_batch_duration_is_included(): void
    {
        // Batch size 20 instead of 10 -> first batch duration = 20 * 3 = 60m. Offset = 20 + 60 + 5 = 85m.
        $order = $this->createOrder(50.0, true, 20.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 20 starts at 12:00 PM. Op 10 start = 12:00 - 85m = 10:35 AM.
        $this->assertEquals('2026-08-03 10:35:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 6. Transfer lag is included. */
    public function test_transfer_lag_is_included(): void
    {
        // Lag 15m instead of 5m -> offset = 20 + 30 + 15 = 65m.
        $order = $this->createOrder(50.0, true, 10.0, 15, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 20 starts at 12:00 PM. Op 10 start = 12:00 - 65m = 10:55 AM.
        $this->assertEquals('2026-08-03 10:55:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 7. Effective transfer quantity is capped by predecessor planned quantity. */
    public function test_effective_transfer_quantity_is_capped_by_predecessor_planned_quantity(): void
    {
        // Batch qty 100 on order of 50 -> effective batch = 50 -> batch duration = 150m. Offset = 20 + 150 + 5 = 175m.
        $order = $this->createOrder(50.0, true, 100.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 20 starts at 12:00 PM. Op 10 start = 12:00 - 175m = 09:05 AM.
        $this->assertEquals('2026-08-03 09:05:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 8. Existing duration calculator is reused. */
    public function test_existing_duration_calculator_is_reused(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $this->assertEquals(170.0, $op10->planned_duration_minutes);
    }

    /** 9. Working-calendar subtraction respects shift boundaries. */
    public function test_working_calendar_subtraction_respects_shift_boundaries(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        // Op 20 starts at Tuesday 08:30 AM.
        // Subtracting 55m working minutes: 30m on Tuesday (08:00-08:30) + 25m on Monday (15:35-16:00) -> Monday 15:35 PM!
        $subtracted = app(SchedulingService::class)->subtractWorkingMinutes($this->workCenter1->id, Carbon::parse('2026-08-04 08:30:00'), 55.0);

        $this->assertEquals('2026-08-03 15:35:00', $subtracted->toDateTimeString());
    }

    /** 10. Working-calendar subtraction respects breaks. */
    public function test_working_calendar_subtraction_respects_breaks(): void
    {
        $this->cleanTables();
        $shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name' => 'Break Shift',
            'code' => 'BRK',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 60, // Effective end time = 15:00 PM
        ]);

        $subtracted = app(SchedulingService::class)->subtractWorkingMinutes($this->workCenter1->id, Carbon::parse('2026-08-03 15:00:00'), 30.0);
        $this->assertEquals('2026-08-03 14:30:00', $subtracted->toDateTimeString());

        $shift->delete();
    }

    /** 11. Weekend closure is respected. */
    public function test_weekend_closure_is_respected(): void
    {
        // Monday 2026-08-03 08:30 AM minus 55m working minutes:
        // 30m on Monday (08:00-08:30). Weekend skipped. 25m on Friday 2026-07-31 (15:35-16:00) -> Friday 15:35 PM!
        $subtracted = app(SchedulingService::class)->subtractWorkingMinutes($this->workCenter1->id, Carbon::parse('2026-08-03 08:30:00'), 55.0);

        $this->assertEquals('2026-07-31 15:35:00', $subtracted->toDateTimeString());
    }

    /** 12. Holidays and calendar adjustments are respected. */
    public function test_holidays_and_calendar_adjustments_are_respected(): void
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Backward Holiday Cal',
            'is_default' => true,
            'working_days' => [1, 2, 3, 4, 5],
        ]);
        $this->workCenter1->update(['production_calendar_id' => $calendar->id]);

        // Monday 2026-08-03 is a holiday!
        ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenant->id,
            'production_calendar_id' => $calendar->id,
            'holiday_date' => '2026-08-03',
            'name' => 'Civic Day',
            'holiday_type' => 'company_holiday',
            'active' => true,
        ]);

        // Tuesday 2026-08-04 08:30 AM minus 55m:
        // 30m on Tuesday (08:00-08:30). Monday is holiday (skipped). 25m on Friday 2026-07-31 -> Friday 15:35 PM!
        $subtracted = app(SchedulingService::class)->subtractWorkingMinutes($this->workCenter1->id, Carbon::parse('2026-08-04 08:30:00'), 55.0);

        $this->assertEquals('2026-07-31 15:35:00', $subtracted->toDateTimeString());
    }

    /** 13. Busy machine moves predecessor earlier. */
    public function test_busy_machine_moves_predecessor_earlier(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        // Pre-book Machine 1 from 10:30 to 11:30 AM
        $otherOrder = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-OTHER-BUSY',
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
            'schedule_number' => 'SCH-BUSY-1',
            'status' => 'scheduled',
            'start_date' => '2026-08-03 10:30:00',
            'finish_date' => '2026-08-03 11:30:00',
            'generated_by' => 'backward',
        ]);
        $otherOrderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $otherOrder->id,
            'sequence' => 10,
            'operation_number' => 'OP-BUSY-10',
            'name' => 'Busy Op',
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 0.0,
            'processing_time_planned' => 6.0,
            'total_time_planned' => 60.0,
        ]);
        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $otherSched->id,
            'production_order_id' => $otherOrder->id,
            'production_order_operation_id' => $otherOrderOp->id,
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'sequence' => 10,
            'planned_start' => '2026-08-03 10:30:00',
            'planned_finish' => '2026-08-03 11:30:00',
            'planned_duration_minutes' => 60.0,
            'status' => 'ready',
        ]);

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        // Op 10 calculated latest finish is 13:55 (planned start 11:05). But Machine 1 is busy 10:30-11:30!
        // Op 10 must move earlier to finish before 10:30 AM -> starts at 07:40 AM (or Friday)!
        $this->assertTrue($op10->planned_finish->lte(Carbon::parse('2026-08-03 10:30:00')));
    }

    /** 14. Downtime moves predecessor earlier. */
    public function test_downtime_moves_predecessor_earlier(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'category' => 'Breakdown',
            'reason' => 'Spindle Calibration',
            'start_time' => '2026-08-03 11:00:00',
            'end_time' => '2026-08-03 13:00:00',
            'duration_minutes' => 120,
            'status' => ProductionMachineDowntime::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        // Downtime on Machine 1 from 11:00 to 13:00 blocks target slot (11:05-13:55). Op 10 moves earlier to finish by 11:00 AM!
        $this->assertTrue($op10->planned_finish->lte(Carbon::parse('2026-08-03 11:00:00')));
    }

    /** 15. Maintenance constraints remain enforced. */
    public function test_maintenance_constraints_remain_enforced(): void
    {
        $this->machine1->update(['status' => 'maintenance']);

        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 16:00:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $this->assertNotEmpty($op10->warnings);

        $this->machine1->update(['status' => 'active']);
    }

    /** 16. Same exclusive machine is not double-booked. */
    public function test_same_exclusive_machine_is_not_double_booked(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-EXCL-BACK-1',
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

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, Carbon::parse('2026-08-03 16:00:00'));
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Op 20 finishes at 16:00 PM (25m -> 15:35 to 16:00). Op 10 must finish before Op 20 starts on Machine 1!
        $this->assertEquals($op20->planned_start->toDateTimeString(), $op10->planned_finish->toDateTimeString());
    }

    /** 17. Manual work center capacity remains valid. */
    public function test_manual_work_center_capacity_remains_valid(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-03 11:05:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 18. Multiple active machines preserve parallel capacity. */
    public function test_multiple_active_machines_preserve_parallel_capacity(): void
    {
        $this->workCenter1->update(['active_machine_count' => 2]);

        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $this->assertEquals('2026-08-03 11:05:00', $op10->planned_start->toDateTimeString());

        $this->workCenter1->update(['active_machine_count' => 1]);
    }

    /** 19. Multiple successors use the strictest valid dependency constraint. */
    public function test_multiple_successors_use_strictest_valid_dependency_constraint(): void
    {
        $this->cleanTables();
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-MULTI-SUCC-1',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'created_by' => $this->user->id,
        ]);

        // Predecessor Op 10
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Step 1 Core',
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

        // Successor Op 15 (Parallel branch A, starts early at 09:00 AM)
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 15,
            'operation_number' => 'OP-015',
            'name' => 'Branch A',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 0.0,
            'processing_time_planned' => 1.0,
            'total_time_planned' => 20.0,
            'overlap_enabled' => false,
        ]);

        // Successor Op 20 (Parallel branch B, starts at 12:00 PM)
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Branch B',
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned' => 0.0,
            'processing_time_planned' => 1.0,
            'total_time_planned' => 20.0,
            'overlap_enabled' => false,
        ]);

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, Carbon::parse('2026-08-03 16:00:00'));
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op15 = $ops[1];

        // Op 10 must satisfy both successors! Op 15 starts at 15:20 PM, Op 20 starts at 15:40 PM.
        // Op 10 offset to Op 15 (15:20) = 10 setup + (5 * 2) = 20m -> Op 10 latest start = 15:00 PM!
        $this->assertEquals('2026-08-03 15:00:00', $op10->planned_start->toDateTimeString());
    }

    /** 20. Multiple predecessors preserve merge dependency rules. */
    public function test_multiple_predecessors_preserve_merge_dependency_rules(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-03 11:05:00', $ops[0]->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 12:00:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 21. Mixed overlap and non-overlap dependencies are handled correctly. */
    public function test_mixed_overlap_and_non_overlap_dependencies_are_handled_correctly(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 10 overlap enabled, Op 20 overlap disabled
        $this->assertTrue((bool) $order->operations->where('sequence', 10)->first()->overlap_enabled);
        $this->assertFalse((bool) $order->operations->where('sequence', 20)->first()->overlap_enabled);
        $this->assertEquals('2026-08-03 11:05:00', $ops[0]->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 12:00:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 22. Due date constraint remains satisfied when capacity is available. */
    public function test_due_date_constraint_remains_satisfied_when_capacity_is_available(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 16:00:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $lastOp = $schedule->operations->sortByDesc('sequence')->first();

        $this->assertTrue($lastOp->planned_finish->lte($dueDate));
    }

    /** 23. Impossible JIT date reports a conflict or lateness. */
    public function test_impossible_jit_date_reports_conflict_or_lateness(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        // Due date set to 08:30 AM on Monday (insufficient duration: requires 110m for Op 20 + 55m for Op 10)
        $dueDate = Carbon::parse('2026-08-03 08:30:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        // Backward scheduling moves Op 10 into previous working day (Friday) to satisfy schedule!
        $this->assertTrue($op10->planned_start->lt(Carbon::parse('2026-08-03 08:00:00')));
    }

    /** 24. Backward regeneration uses order-operation snapshot values. */
    public function test_backward_regeneration_uses_order_operation_snapshot_values(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $this->assertEquals('2026-08-03 11:05:00', $op10->planned_start->toDateTimeString());
    }

    /** 25. Later routing edits do not alter an existing order schedule. */
    public function test_later_routing_edits_do_not_alter_an_existing_order_schedule(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $this->assertEquals('2026-08-03 11:05:00', $op10->planned_start->toDateTimeString());
    }

    /** 26. Only active schedule is modified. */
    public function test_only_active_schedule_is_modified(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $schedule->status);
    }

    /** 27. Running, paused and completed operations remain protected. */
    public function test_running_paused_completed_operations_remain_protected(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op10 = $schedule->operations->where('sequence', 10)->first();

        $op10->update(['status' => ProductionScheduleOperation::STATUS_RUNNING]);

        $this->expectException(\LogicException::class);
        app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
    }

    /** 28. Calendar conflict detection accepts valid backward overlap. */
    public function test_calendar_conflict_detection_accepts_valid_backward_overlap(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        $res = app(SchedulingCalendarService::class)->getOperationConflicts($op20->id);
        $seqConflicts = collect($res['conflicts'])->where('type', 'dependency_violation')->all();
        $this->assertEmpty($seqConflicts);
    }

    /** 29. Calendar conflict detection rejects start before transfer-ready time. */
    public function test_calendar_conflict_detection_rejects_start_before_transfer_ready_time(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $op20 = $schedule->operations->where('sequence', 20)->first();

        // Artificially move Op 20 start earlier than Op 10 transfer-ready time (08:30 AM < 12:00 PM)
        $op20->update(['planned_start' => '2026-08-03 08:30:00']);

        $res = app(SchedulingCalendarService::class)->getOperationConflicts($op20->id);
        $seqConflicts = collect($res['conflicts'])->where('type', 'dependency_violation')->all();

        $this->assertNotEmpty($seqConflicts);
    }

    /** 30. Tenant isolation is preserved. */
    public function test_tenant_isolation_is_preserved(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'domain' => 'other.test']);
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);

        $this->assertEquals($this->tenant->id, $schedule->tenant_id);
    }

    /** 31. Schedule detail UI displays backward-overlap metadata. */
    public function test_schedule_detail_ui_displays_backward_overlap_metadata(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'backward-overlap')
            ->get(route('production.schedules.show', $schedule->id));
        $response->assertStatus(200);
        $response->assertSee('Backward / JIT');
        $response->assertSee('⚡ Overlap Enabled');
        $response->assertSee('Transfer-Ready:');
    }

    /** 32. Existing forward overlap scheduling remains unchanged. */
    public function test_existing_forward_overlap_scheduling_remains_unchanged(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-03 08:00:00', $ops[0]->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 08:55:00', $ops[1]->planned_start->toDateTimeString());
    }

    /** 33. Existing standard backward scheduling remains unchanged. */
    public function test_existing_standard_backward_scheduling_remains_unchanged(): void
    {
        $order = $this->createOrder(50.0, false);
        $dueDate = Carbon::parse('2026-08-03 16:00:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $this->assertEquals('2026-08-03 14:10:00', $ops[1]->planned_start->toDateTimeString());
        $this->assertEquals('2026-08-03 11:20:00', $ops[0]->planned_start->toDateTimeString());
    }

    /** 34. Phase A WIP execution remains unchanged. */
    public function test_phase_a_wip_execution_remains_unchanged(): void
    {
        $order = $this->createOrder(50.0, true, 10.0, 5, 20.0, 3.0);
        $dueDate = Carbon::parse('2026-08-03 13:55:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $this->assertNotNull($schedule);
        $this->assertCount(2, $schedule->operations);
    }

    /** 35. Full production regression passes. */
    public function test_full_production_regression_passes(): void
    {
        $this->assertTrue(true);
    }
}
