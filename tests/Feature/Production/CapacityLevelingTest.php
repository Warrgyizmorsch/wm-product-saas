<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionScheduleOptimizationRun;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityLevelingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CapacityLevelingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private CapacityLevelingService $capacityLevelingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Leveling Precision Mfg',
            'slug'   => 'leveling-precision',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Planner User',
            'email'     => 'planner@levelingprecision.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->user);

        $this->capacityLevelingService = app(CapacityLevelingService::class);
    }

    public function test_capacity_leveling_preview_is_non_destructive_and_saves_optimization_run(): void
    {
        $setup = $this->createOverloadedScheduleSetup();
        $op = $setup['op1'];

        $originalStart  = $op->planned_start->toIso8601String();
        $originalFinish = $op->planned_finish->toIso8601String();
        $originalVer    = $op->version;

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);

        $this->assertIsArray($preview);
        $this->assertArrayHasKey('run_id', $preview);
        $this->assertEquals('preview', $preview['status']);

        // Assert live operation in database remains UNCHANGED
        $freshOp = ProductionScheduleOperation::find($op->id);
        $this->assertEquals($originalStart, $freshOp->planned_start->toIso8601String());
        $this->assertEquals($originalFinish, $freshOp->planned_finish->toIso8601String());
        $this->assertEquals($originalVer, $freshOp->version);

        // Assert Optimization Run record created in DB
        $run = ProductionScheduleOptimizationRun::find($preview['run_id']);
        $this->assertNotNull($run);
        $this->assertEquals('preview', $run->status);
        $this->assertEquals($this->tenant->id, $run->tenant_id);
    }

    public function test_capacity_leveling_identifies_overload_and_proposes_feasible_slot(): void
    {
        $setup = $this->createOverloadedScheduleSetup();

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);

        $this->assertGreaterThan(0, count($preview['proposed_changes']));
        $change = $preview['proposed_changes'][0];

        $this->assertNotNull($change['new_start']);
        $this->assertNotNull($change['new_finish']);
        $this->assertNotEquals($change['old_start'], $change['new_start']);
    }

    public function test_capacity_leveling_uses_qualified_alternate_machine(): void
    {
        $setup = $this->createOverloadedScheduleSetup();
        $wc = $setup['workCenter'];

        // Create alternate qualified machine
        $altMachine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'CNC Alternate Machine',
            'code'           => 'CNC-02',
            'status'         => 'active',
        ]);

        $orderOp = $setup['op1']->orderOperation;
        if ($orderOp && $orderOp->routing_operation_id) {
            RoutingOperationAlternateMachine::create([
                'tenant_id'            => $this->tenant->id,
                'routing_operation_id' => $orderOp->routing_operation_id,
                'machine_id'           => $altMachine->id,
                'priority'             => 1,
            ]);
        }

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $wc->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);

        $this->assertGreaterThan(0, count($preview['proposed_changes']));
        $change = $preview['proposed_changes'][0];

        $this->assertEquals($altMachine->id, $change['new_machine_id']);
        $this->assertEquals('CNC Alternate Machine', $change['new_machine_name']);
    }

    public function test_capacity_leveling_respects_locked_operations(): void
    {
        $setup = $this->createOverloadedScheduleSetup();
        $lockedOp = $setup['op1'];
        $lockedOp->update(['locked' => true]);

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);

        // Verify lockedOp is NEVER in proposed_changes
        $changedOpIds = collect($preview['proposed_changes'])->pluck('schedule_operation_id')->toArray();
        $this->assertNotContains($lockedOp->id, $changedOpIds);
    }

    public function test_capacity_leveling_respects_in_progress_and_completed_operations(): void
    {
        $setup = $this->createOverloadedScheduleSetup();
        $runningOp = $setup['op1'];
        $runningOp->update(['status' => 'in_progress']);

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);

        $changedOpIds = collect($preview['proposed_changes'])->pluck('schedule_operation_id')->toArray();
        $this->assertNotContains($runningOp->id, $changedOpIds);
    }

    public function test_capacity_leveling_apply_commits_preview_atomically_with_logs(): void
    {
        $setup = $this->createOverloadedScheduleSetup();

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);
        $runId = $preview['run_id'];

        $res = $this->capacityLevelingService->applyPreview($this->tenant->id, $runId, $this->user->id);

        $this->assertTrue($res['success']);
        $this->assertGreaterThan(0, $res['operations_changed']);

        // Assert Optimization Run marked applied
        $run = ProductionScheduleOptimizationRun::find($runId);
        $this->assertEquals(ProductionScheduleOptimizationRun::STATUS_APPLIED, $run->status);

        // Assert Audit Logs written
        $logs = ProductionScheduleChangeLog::where('tenant_id', $this->tenant->id)
            ->where('change_type', 'capacity_leveling')
            ->get();

        $this->assertGreaterThan(0, $logs->count());
    }

    public function test_capacity_leveling_apply_rejects_stale_preview_concurrency_conflict(): void
    {
        $setup = $this->createOverloadedScheduleSetup();

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
            'work_center_id' => $setup['workCenter']->id,
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);
        $runId = $preview['run_id'];

        // Simulate another planner modifying the operation version in DB
        $setup['op1']->update([
            'version' => $setup['op1']->version + 1,
            'planned_start' => Carbon::now()->addHours(5),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/OPTIMIZATION_PREVIEW_STALE/');

        $this->capacityLevelingService->applyPreview($this->tenant->id, $runId, $this->user->id);
    }

    public function test_capacity_leveling_tenant_isolation(): void
    {
        $setup = $this->createOverloadedScheduleSetup();
        $otherTenant = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
        ]);

        $filters = [
            'start_date'     => Carbon::now()->format('Y-m-d'),
            'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $preview = $this->capacityLevelingService->generatePreview($this->tenant->id, $filters, $this->user->id);
        $runId = $preview['run_id'];

        // Attempt apply using Tenant B
        $this->expectException(\InvalidArgumentException::class);
        $this->capacityLevelingService->applyPreview($otherTenant->id, $runId, $this->user->id);
    }

    public function test_dispatch_board_leveling_preview_and_apply_api_endpoints(): void
    {
        $setup = $this->createOverloadedScheduleSetup();

        // 1. Test POST /production/schedules/capacity-leveling/preview
        $responsePreview = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.capacity-leveling.preview'), [
                'start_date'     => Carbon::now()->format('Y-m-d'),
                'end_date'       => Carbon::now()->addDays(7)->format('Y-m-d'),
                'work_center_id' => $setup['workCenter']->id,
            ]);

        $responsePreview->assertStatus(200)
            ->assertJsonStructure(['run_id', 'status', 'summary', 'proposed_changes']);

        $runId = $responsePreview->json('run_id');

        // 2. Test POST /production/schedules/capacity-leveling/apply
        $responseApply = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.capacity-leveling.apply'), [
                'run_id' => $runId,
            ]);

        $responseApply->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    private function createOverloadedScheduleSetup(): array
    {
        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Piece',
            'code'      => 'PCS',
            'type'      => 'unit',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'uom_id'    => $uom->id,
            'name'      => 'Precision Shaft',
            'sku'       => 'FG-SHAFT-001',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $workCenter = WorkCenter::create([
            'tenant_id'          => $this->tenant->id,
            'name'               => 'Cutting & Pressing Work Center',
            'code'               => 'WC-CUT',
            'capacity_per_day'   => 480.0,
            'efficiency_percentage' => 100,
            'status'             => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $workCenter->id,
            'name'           => 'CNC Press Machine 01',
            'code'           => 'CNC-01',
            'status'         => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $product->id,
            'name'       => 'Default Shaft Routing',
            'status'     => 'active',
        ]);

        $routingOp1 = RoutingOperation::create([
            'tenant_id'          => $this->tenant->id,
            'routing_id'         => $routing->id,
            'work_center_id'     => $workCenter->id,
            'sequence'           => 10,
            'operation_number'   => 'OP10',
            'name'               => 'Sheet Cutting',
            'setup_time_planned' => 30,
            'run_time_per_unit'  => 3,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'           => $this->tenant->id,
            'product_id'          => $product->id,
            'routing_id'          => $routing->id,
            'order_number'        => 'PO-LVL-100',
            'quantity_ordered'    => 100,
            'status'              => 'planned',
            'start_date'          => Carbon::now()->startOfDay(),
            'end_date'            => Carbon::now()->addDays(3)->endOfDay(),
            'due_date'            => Carbon::now()->addDays(3)->endOfDay(),
        ]);

        $orderOp1 = ProductionOrderOperation::create([
            'tenant_id'            => $this->tenant->id,
            'production_order_id'  => $order->id,
            'routing_operation_id' => $routingOp1->id,
            'work_center_id'       => $workCenter->id,
            'machine_id'           => $machine->id,
            'sequence'             => 10,
            'name'                 => 'Sheet Cutting',
            'operation_number'     => 'OP10',
            'setup_time_planned'   => 30,
            'run_time_per_unit'    => 3,
            'status'               => 'pending',
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number'     => 'SCH-LVL-001',
            'status'              => 'draft',
        ]);

        $now = Carbon::now()->startOfDay()->addHours(8);

        // Create 2 overlapping operations on CNC-01 exceeding 480 minutes capacity
        $op1 = ProductionScheduleOperation::create([
            'tenant_id'                      => $this->tenant->id,
            'production_schedule_id'         => $schedule->id,
            'production_order_id'            => $order->id,
            'production_order_operation_id' => $orderOp1->id,
            'sequence'                       => 10,
            'work_center_id'                 => $workCenter->id,
            'machine_id'                     => $machine->id,
            'planned_start'                  => $now->copy(),
            'planned_finish'                 => $now->copy()->addMinutes(300),
            'baseline_start'                 => $now->copy(),
            'baseline_finish'                => $now->copy()->addMinutes(300),
            'planned_duration_minutes'       => 300,
            'status'                         => 'scheduled',
            'locked'                         => false,
            'manual_override'                => false,
            'version'                        => 1,
            'priority'                       => 2,
        ]);

        $op2 = ProductionScheduleOperation::create([
            'tenant_id'                      => $this->tenant->id,
            'production_schedule_id'         => $schedule->id,
            'production_order_id'            => $order->id,
            'production_order_operation_id' => $orderOp1->id,
            'sequence'                       => 20,
            'work_center_id'                 => $workCenter->id,
            'machine_id'                     => $machine->id,
            'planned_start'                  => $now->copy()->addMinutes(120),
            'planned_finish'                 => $now->copy()->addMinutes(420),
            'baseline_start'                 => $now->copy()->addMinutes(120),
            'baseline_finish'                => $now->copy()->addMinutes(420),
            'planned_duration_minutes'       => 300,
            'status'                         => 'scheduled',
            'locked'                         => false,
            'manual_override'                => false,
            'version'                        => 1,
            'priority'                       => 3,
        ]);

        return [
            'workCenter' => $workCenter,
            'machine'    => $machine,
            'order'      => $order,
            'schedule'   => $schedule,
            'op1'        => $op1,
            'op2'        => $op2,
        ];
    }
}
