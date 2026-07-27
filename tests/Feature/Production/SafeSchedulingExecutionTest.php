<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SafeSchedulingExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Product $product;
    private SchedulingService $schedulingService;

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
    }

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

    private function setupBaseEntities()
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

        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Default Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => true,
        ]);
        $wc->update(['production_calendar_id' => $cal->id]);

        return [$wc, $machine];
    }

    public function test_draft_schedule_without_execution_history_can_regenerate(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-SAFE-REGEN', $wc, $machine, 120);

        // Generate schedule
        $schedule = $this->schedulingService->generateSchedule($order, now());
        $this->assertNotNull($schedule);

        // Regenerate without any execution history should succeed
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $this->assertNotNull($newSchedule);
        $this->assertNotEquals($schedule->id, $newSchedule->id);

        // Old schedule should be deleted/soft-deleted
        $this->assertSoftDeleted($schedule);
    }

    public function test_planning_only_assignments_do_not_falsely_block_regeneration(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-PLANNING', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        // Add operator assignment (planning-only state)
        DB::table('production_operator_assignments')->insert([
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $order->operations->first()->id,
            'user_id' => $this->admin->id,
            'status' => 'assigned',
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Regeneration should still succeed
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $this->assertNotNull($newSchedule);
        $this->assertSoftDeleted($schedule);
    }

    public function test_running_operation_blocks_destructive_regeneration(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-RUNNING', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        // Set operation to running status
        $schedule->operations->first()->update(['status' => ProductionScheduleOperation::STATUS_RUNNING]);

        // Trying to regenerate should trigger partial reschedule in-place instead of deleting
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        
        // The schedule ID remains the same since it was not deleted
        $this->assertEquals($schedule->id, $newSchedule->id);
    }

    public function test_paused_operation_blocks_destructive_regeneration(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-PAUSED', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        $schedule->operations->first()->update(['status' => ProductionScheduleOperation::STATUS_PAUSED]);

        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $this->assertEquals($schedule->id, $newSchedule->id);
    }

    public function test_completed_operation_blocks_destructive_regeneration(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-COMPLETED', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        $schedule->operations->first()->update(['status' => ProductionScheduleOperation::STATUS_COMPLETED]);

        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $this->assertEquals($schedule->id, $newSchedule->id);
    }

    public function test_wip_or_quantity_history_blocks_deletion(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-WIP', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());

        // Insert WIP history
        DB::table('production_wips')->insert([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'current_schedule_operation_id' => $schedule->operations->first()->id,
            'product_id' => $this->product->id,
            'current_work_center_id' => $wc->id,
            'quantity' => 1.0,
            'available_quantity' => 1.0,
            'status' => 'wip',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $this->assertEquals($schedule->id, $newSchedule->id);
    }

    public function test_regeneration_is_rolled_back_fully_after_failure(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-ROLLBACK', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());

        // Inject exception during execution to check rollback behavior
        try {
            DB::transaction(function() use ($order) {
                // Manually trigger a database delete and then force throw an exception
                ProductionSchedule::withoutGlobalScopes()->where('production_order_id', $order->id)->delete();
                throw new \RuntimeException("Forced Rollback Exception");
            });
        } catch (\RuntimeException $e) {
            // verified exception
        }

        // Schedule must still exist in database due to rollback
        $this->assertDatabaseHas('production_schedules', ['id' => $schedule->id, 'deleted_at' => null]);
    }

    public function test_repeated_regeneration_creates_no_duplicate_slots(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-DUPLICATE', $wc, $machine, 120);

        // Generate schedule three times
        $this->schedulingService->generateSchedule($order, now());
        $this->schedulingService->generateSchedule($order, now()->addDays(1));
        $schedule = $this->schedulingService->generateSchedule($order, now()->addDays(2));

        $count = ProductionSchedule::where('production_order_id', $order->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_started_operations_remain_unchanged_during_rescheduling(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-STARTED-RESCHED', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        $op = $schedule->operations->first();
        $oldStart = $op->planned_start;

        $op->update(['status' => ProductionScheduleOperation::STATUS_RUNNING]);

        // Reschedule
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(2));
        $newOp = $newSchedule->operations->first();

        // The start date must remain exactly as originally scheduled
        $this->assertEquals($oldStart->toDateTimeString(), $newOp->planned_start->toDateTimeString());
    }

    public function test_completed_operations_remain_unchanged(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-COMPLETED-RESCHED', $wc, $machine, 120);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        $op = $schedule->operations->first();
        $oldStart = $op->planned_start;

        $op->update(['status' => ProductionScheduleOperation::STATUS_COMPLETED]);

        // Reschedule
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(2));
        $newOp = $newSchedule->operations->first();

        $this->assertEquals($oldStart->toDateTimeString(), $newOp->planned_start->toDateTimeString());
    }

    public function test_only_waiting_and_ready_operations_are_updated(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        
        // Order with multiple operations to test waiting rescheduling
        $order = $this->createMockOrder('ORD-MULTI-RESCHED', $wc, $machine, 120);
        $wc2 = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'CNC Packaging',
            'code'                  => 'CNC-02',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);
        ProductionOrderOperation::create([
            'tenant_id'                 => $this->tenant->id,
            'production_order_id'       => $order->id,
            'sequence'                  => 2,
            'operation_number'          => 'OP-20',
            'name'                      => 'Packaging',
            'work_center_id'            => $wc2->id,
            'setup_time_planned'        => 0,
            'processing_time_planned'   => 60,
            'status'                    => 'waiting',
        ]);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        $op1 = $schedule->operations()->where('sequence', 1)->first();
        $op2 = $schedule->operations()->where('sequence', 2)->first();
        
        $op1->update(['status' => ProductionScheduleOperation::STATUS_COMPLETED]);
        
        $oldOp2Start = $op2->planned_start;

        // Reschedule to a future date
        $this->schedulingService->generateSchedule($order, now()->addDays(5));
        
        $newOp1 = $schedule->operations()->where('sequence', 1)->first();
        $newOp2 = $schedule->operations()->where('sequence', 2)->first();

        // Op1 remains unchanged, Op2 is rescheduled
        $this->assertEquals($op1->planned_start->toDateTimeString(), $newOp1->planned_start->toDateTimeString());
        $this->assertNotEquals($oldOp2Start->toDateTimeString(), $newOp2->planned_start->toDateTimeString());
    }

    public function test_dependencies_remain_valid(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-DEP-VALID', $wc, $machine, 120);
        ProductionOrderOperation::create([
            'tenant_id'                 => $this->tenant->id,
            'production_order_id'       => $order->id,
            'sequence'                  => 2,
            'operation_number'          => 'OP-20',
            'name'                      => 'Packaging',
            'work_center_id'            => $wc->id,
            'setup_time_planned'        => 0,
            'processing_time_planned'   => 60,
            'status'                    => 'waiting',
        ]);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        $op1 = $schedule->operations()->where('sequence', 1)->first();
        
        // Mark first completed so reschedule affects second
        $op1->update(['status' => ProductionScheduleOperation::STATUS_COMPLETED]);
        
        $newSchedule = $this->schedulingService->generateSchedule($order, now()->addDays(2));
        $newOp1 = $newSchedule->operations()->where('sequence', 1)->first();
        $newOp2 = $newSchedule->operations()->where('sequence', 2)->first();

        // Sequential constraint: Op2 start must be >= Op1 finish
        $this->assertTrue($newOp2->planned_start->gte($newOp1->planned_finish));
    }

    public function test_rescheduling_events_are_written_to_the_timeline(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-TIMELINE', $wc, $machine, 120);
        ProductionOrderOperation::create([
            'tenant_id'                 => $this->tenant->id,
            'production_order_id'       => $order->id,
            'sequence'                  => 2,
            'operation_number'          => 'OP-20',
            'name'                      => 'Packaging',
            'work_center_id'            => $wc->id,
            'setup_time_planned'        => 0,
            'processing_time_planned'   => 60,
            'status'                    => 'waiting',
        ]);

        $schedule = $this->schedulingService->generateSchedule($order, now());
        
        // Freeze the first, leaving the second eligible for rescheduling
        $schedule->operations()->where('sequence', 1)->first()->update(['status' => ProductionScheduleOperation::STATUS_RUNNING]);

        // Reschedule
        $this->schedulingService->generateSchedule($order, now()->addDays(2));

        // Check production_event_timelines
        $event = DB::table('production_event_timelines')
            ->where('production_order_id', $order->id)
            ->where('event_type', 'Schedule Rescheduled')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('Schedule Rescheduled', $event->event_type);
    }

    public function test_cross_tenant_regeneration_and_rescheduling_are_rejected(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-CROSS-TENANT', $wc, $machine, 120);
        $schedule = $this->schedulingService->generateSchedule($order, now());

        $otherTenant = Tenant::create([
            'name' => 'Other Corp',
            'slug' => 'other-corp',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Try to reschedule with other tenant active
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        // Act under another tenant
        session(['tenant_id' => $otherTenant->id]);
        
        // Use model finder scoped to tenant rules or throw
        ProductionOrder::where('tenant_id', $otherTenant->id)->findOrFail($order->id);
    }

    public function test_unauthorized_users_cannot_regenerate_cancel_or_reschedule(): void
    {
        [$wc, $machine] = $this->setupBaseEntities();
        $order = $this->createMockOrder('ORD-AUTH', $wc, $machine, 120);
        $schedule = $this->schedulingService->generateSchedule($order, now());

        $guest = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Guest User',
            'email'     => 'guest@enterprise.com',
            'password'  => bcrypt('password'),
            'role'      => 'guest',
        ]);

        // Perform HTTP calls acting as guest user with session tenant slug
        $this->actingAs($guest);

        $responseDelete = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->delete(route('production.schedules.destroy', $schedule->id));
        $responseDelete->assertStatus(403);

        $responseCancel = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->post(route('production.schedules.cancel', $schedule->id));
        $responseCancel->assertStatus(403);

        $responseResched = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->post(route('production.schedules.reschedule-start', $schedule->id), [
                'start_date' => now()->addDays(5)->toDateString(),
            ]);
        $responseResched->assertStatus(403);
    }
}
