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
use App\Domains\Production\Models\ProductionScheduleScenario;
use App\Domains\Production\Models\ProductionScheduleScenarioOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionScheduleScenarioService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleScenarioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $planner;
    private ProductionScheduleScenarioService $scenarioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Scenario Precision Mfg',
            'slug'   => 'scenario-precision',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->planner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Scenario Planner',
            'email'     => 'planner@scenariomfg.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->planner);

        $this->scenarioService = app(ProductionScheduleScenarioService::class);
    }

    public function test_scenario_creation_snapshots_live_schedule_non_destructively(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];

        $originalStart  = $op1->planned_start->toIso8601String();
        $originalFinish = $op1->planned_finish->toIso8601String();
        $originalVer    = $op1->version;

        $scenarioData = [
            'name'        => 'CNC Breakdown Test',
            'description' => 'Test scenario for CNC breakdown simulation',
            'start_date'  => Carbon::now()->format('Y-m-d'),
            'end_date'    => Carbon::now()->addDays(7)->format('Y-m-d'),
            'source_schedule_id' => $setup['schedule']->id,
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        $this->assertNotNull($scenario);
        $this->assertEquals('draft', $scenario->status);
        $this->assertEquals('CNC Breakdown Test', $scenario->name);
        $this->assertCount(2, $scenario->scenarioOperations);

        // Assert live operations remain completely UNCHANGED
        $freshOp1 = ProductionScheduleOperation::find($op1->id);
        $this->assertEquals($originalStart, $freshOp1->planned_start->toIso8601String());
        $this->assertEquals($originalFinish, $freshOp1->planned_finish->toIso8601String());
        $this->assertEquals($originalVer, $freshOp1->version);

        // Assert snapshot fields match
        $sOp1 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $op1->id)
            ->first();

        $this->assertNotNull($sOp1);
        $this->assertEquals($originalStart, $sOp1->planned_start->toIso8601String());
        $this->assertEquals($originalVer, $sOp1->source_version);
    }

    public function test_scenario_simulation_with_temporary_downtime_assumption(): void
    {
        $setup = $this->createTestScheduleSetup();
        $m1 = $setup['machine1'];

        $now = Carbon::now()->startOfDay()->addHours(8);

        // Add temporary machine downtime assumption to scenario
        $scenarioData = [
            'name'          => 'Temporary Downtime Scenario',
            'scenario_type' => ProductionScheduleScenario::TYPE_MACHINE_DOWNTIME,
            'start_date'    => Carbon::now()->format('Y-m-d'),
            'end_date'      => Carbon::now()->addDays(7)->format('Y-m-d'),
            'assumptions'   => [
                'temporary_downtimes' => [
                    [
                        'machine_id' => $m1->id,
                        'start'      => $now->copy()->toIso8601String(),
                        'finish'     => $now->copy()->addHours(6)->toIso8601String(),
                    ]
                ]
            ]
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);
        $this->scenarioService->recalculateScenario($this->tenant->id, $scenario->id);

        $sOp1 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $setup['op1']->id)
            ->first();

        // Scenario operation should have shifted past downtime (after 14:00)
        $this->assertTrue(Carbon::parse($sOp1->planned_start)->gte($now->copy()->addHours(6)));

        // Live operation MUST remain at 08:00
        $liveOp1 = ProductionScheduleOperation::find($setup['op1']->id);
        $this->assertEquals($now->toIso8601String(), $liveOp1->planned_start->toIso8601String());
    }

    public function test_scenario_simulation_with_priority_override_assumption(): void
    {
        $setup = $this->createTestScheduleSetup();
        $order = $setup['order'];

        $scenarioData = [
            'name'        => 'Rush Order Priority Test',
            'start_date'  => Carbon::now()->format('Y-m-d'),
            'end_date'    => Carbon::now()->addDays(7)->format('Y-m-d'),
            'assumptions' => [
                'order_priorities' => [
                    $order->id => 1 // High priority
                ]
            ]
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);
        $this->scenarioService->recalculateScenario($this->tenant->id, $scenario->id);

        $sOp1 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $setup['op1']->id)
            ->first();

        $this->assertEquals(1, $sOp1->priority);

        // Live Production Order priority remains unchanged!
        $freshOrder = ProductionOrder::find($order->id);
        $this->assertNotEquals(1, $freshOrder->priority ?? 0);
    }

    public function test_scenario_capacity_leveling_modifies_scenario_only(): void
    {
        $setup = $this->createTestScheduleSetup();
        $scenarioData = [
            'name'       => 'Scenario Leveling Test',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);
        $res = $this->scenarioService->levelScenarioCapacity($this->tenant->id, $scenario->id);

        $this->assertTrue($res['success']);

        // Live operations remain UNCHANGED
        $liveOp2 = ProductionScheduleOperation::find($setup['op2']->id);
        $this->assertEquals($setup['machine1']->id, $liveOp2->machine_id);
    }

    public function test_scenario_comparison_kpi_metrics(): void
    {
        $setup = $this->createTestScheduleSetup();
        $scenarioData = [
            'name'       => 'Compare Scenario',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);
        $comparison = $this->scenarioService->compareWithLive($this->tenant->id, $scenario->id);

        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('kpis', $comparison);
        $this->assertArrayHasKey('operations_diff', $comparison);
        $this->assertCount(2, $comparison['operations_diff']);
    }

    public function test_scenario_promotion_atomic_apply_audit_log_and_event(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];
        $op2 = $setup['op2'];
        $m2  = $setup['machine2'];

        $originalBaselineStart = $op1->baseline_start->toIso8601String();

        $scenarioData = [
            'name'       => 'Promote Test Scenario',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Modify op2 in scenario (reassign machine to m2)
        $sOp2 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $op2->id)
            ->first();

        $sOp2->update([
            'machine_id' => $m2->id,
            'planned_start' => Carbon::now()->addHours(10),
            'planned_finish' => Carbon::now()->addHours(14),
        ]);

        $res = $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);

        $this->assertTrue($res['success']);
        $this->assertEquals(1, $res['operations_changed']);

        // Assert live op2 updated
        $freshOp2 = ProductionScheduleOperation::find($op2->id);
        $this->assertEquals($m2->id, $freshOp2->machine_id);
        $this->assertEquals(2, $freshOp2->version); // version++
        $this->assertTrue($freshOp2->manual_override);

        // Assert baseline start remains UNCHANGED!
        $freshOp1 = ProductionScheduleOperation::find($op1->id);
        $this->assertEquals($originalBaselineStart, $freshOp1->baseline_start->toIso8601String());

        // Assert audit log created with scenario_promotion
        $log = ProductionScheduleChangeLog::where('tenant_id', $this->tenant->id)
            ->where('change_type', 'scenario_promotion')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($m2->id, $log->new_machine_id);

        // Assert scenario marked promoted
        $freshScenario = ProductionScheduleScenario::find($scenario->id);
        $this->assertEquals('promoted', $freshScenario->status);
    }

    public function test_scenario_promotion_rejects_stale_scenario_with_409_concurrency_conflict(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];

        $scenarioData = [
            'name'       => 'Stale Scenario',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Simulate another planner modifying live op1 version in DB
        $op1->update([
            'version' => 2,
            'planned_start' => Carbon::now()->addHours(3),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SCENARIO_STALE/');

        $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);
    }

    public function test_scenario_promotion_rejects_locked_live_operations(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];

        $scenarioData = [
            'name'       => 'Locked Operation Test',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Modify scenario timing for op1
        $sOp1 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $op1->id)
            ->first();
        $sOp1->update(['planned_start' => Carbon::now()->addHours(5)]);

        // Lock live op1 afterwards
        $op1->update(['locked' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PROMOTION_BLOCKED/');

        $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);
    }

    public function test_scenario_discard_marks_status_discarded(): void
    {
        $setup = $this->createTestScheduleSetup();
        $scenarioData = [
            'name'       => 'Discard Test',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);
        $res = $this->scenarioService->discardScenario($this->tenant->id, $scenario->id);

        $this->assertTrue($res['success']);
        $freshScenario = ProductionScheduleScenario::find($scenario->id);
        $this->assertEquals('discarded', $freshScenario->status);

        // Cannot promote discarded scenario
        $this->expectException(\InvalidArgumentException::class);
        $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);
    }

    public function test_scenario_tenant_isolation(): void
    {
        $setup = $this->createTestScheduleSetup();
        $otherTenant = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b-scenario',
            'status' => 'active',
        ]);

        $scenarioData = [
            'name'       => 'Tenant A Scenario',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Tenant B attempt to access/promote Tenant A scenario
        $this->expectException(\InvalidArgumentException::class);
        $this->scenarioService->promoteScenario($otherTenant->id, $scenario->id, $this->planner->id);
    }

    public function test_scenario_promotion_rejects_in_progress_live_operations(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];

        $scenarioData = [
            'name'       => 'In-Progress Execution Race Test',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Later, live op1 becomes in_progress
        $op1->update(['status' => 'in_progress']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PROMOTION_BLOCKED/');

        $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);
    }

    public function test_scenario_promotion_transaction_atomic_rollback_on_failure(): void
    {
        $setup = $this->createTestScheduleSetup();
        $op1 = $setup['op1'];
        $op2 = $setup['op2'];
        $m2  = $setup['machine2'];

        $scenarioData = [
            'name'       => 'Atomic Rollback Test Scenario',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date'   => Carbon::now()->addDays(7)->format('Y-m-d'),
        ];

        $scenario = $this->scenarioService->createScenario($this->tenant->id, $scenarioData, $this->planner->id);

        // Modify scenario op1 & op2
        $sOp1 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $op1->id)
            ->first();
        $sOp1->update(['planned_start' => Carbon::now()->addHours(2)]);

        $sOp2 = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)
            ->where('source_schedule_operation_id', $op2->id)
            ->first();
        $sOp2->update(['machine_id' => $m2->id]);

        // Force op2 version mismatch to trigger promotion failure mid-transaction
        $op2->update(['version' => 99]);

        try {
            $this->scenarioService->promoteScenario($this->tenant->id, $scenario->id, $this->planner->id);
            $this->fail('Expected exception was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('SCENARIO_STALE', $e->getMessage());
        }

        // Assert op1 was ROLLED BACK and NOT updated in DB!
        $freshOp1 = ProductionScheduleOperation::find($op1->id);
        $this->assertEquals(1, $freshOp1->version);
        $this->assertFalse($freshOp1->manual_override);

        // Assert scenario status remains draft
        $freshScenario = ProductionScheduleScenario::find($scenario->id);
        $this->assertEquals('draft', $freshScenario->status);

        // Assert NO promotion change log was created
        $logCount = ProductionScheduleChangeLog::where('tenant_id', $this->tenant->id)
            ->where('change_type', 'scenario_promotion')
            ->count();
        $this->assertEquals(0, $logCount);
    }

    public function test_scenario_api_endpoints(): void
    {
        $setup = $this->createTestScheduleSetup();

        // 1. POST Create Scenario
        $resCreate = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.scenarios.store'), [
                'name'               => 'API Scenario Test',
                'scenario_type'      => 'custom',
                'source_schedule_id' => $setup['schedule']->id,
                'start_date'         => Carbon::now()->format('Y-m-d'),
                'end_date'           => Carbon::now()->addDays(7)->format('Y-m-d'),
            ]);

        $resCreate->assertStatus(200)->assertJson(['success' => true]);
        $scenarioId = $resCreate->json('scenario.id');

        // 2. GET Compare Scenario
        $resCompare = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('production.schedules.scenarios.compare', ['scenario' => $scenarioId]));

        $resCompare->assertStatus(200)->assertJsonStructure(['kpis', 'operations_diff']);

        // 3. POST Promote Scenario
        $resPromote = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.scenarios.promote', ['scenario' => $scenarioId]));

        $resPromote->assertStatus(200)->assertJson(['success' => true]);
    }

    private function createTestScheduleSetup(): array
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
            'name'      => 'Scenario Shaft Component',
            'sku'       => 'FG-SCEN-001',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Precision Milling WC',
            'code'                  => 'WC-MILL',
            'capacity_per_day'      => 480.0,
            'efficiency_percentage' => 100,
            'status'                => 'active',
        ]);

        $machine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $workCenter->id,
            'name'           => 'CNC Mill 01',
            'code'           => 'CNC-M1',
            'status'         => 'active',
        ]);

        $machine2 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $workCenter->id,
            'name'           => 'CNC Mill 02',
            'code'           => 'CNC-M2',
            'status'         => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $product->id,
            'name'       => 'Routing for Scenario Test',
            'status'     => 'active',
        ]);

        $routingOp1 = RoutingOperation::create([
            'tenant_id'          => $this->tenant->id,
            'routing_id'         => $routing->id,
            'work_center_id'     => $workCenter->id,
            'machine_id'         => $machine1->id,
            'sequence'           => 10,
            'operation_number'   => 'OP10',
            'name'               => 'Rough Milling',
            'setup_time_planned' => 30,
            'run_time_per_unit'  => 2,
        ]);

        $routingOp2 = RoutingOperation::create([
            'tenant_id'          => $this->tenant->id,
            'routing_id'         => $routing->id,
            'work_center_id'     => $workCenter->id,
            'machine_id'         => $machine1->id,
            'sequence'           => 20,
            'operation_number'   => 'OP20',
            'name'               => 'Finish Milling',
            'setup_time_planned' => 20,
            'run_time_per_unit'  => 3,
        ]);

        RoutingOperationAlternateMachine::create([
            'tenant_id'            => $this->tenant->id,
            'routing_operation_id' => $routingOp2->id,
            'machine_id'           => $machine2->id,
            'priority'             => 1,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $product->id,
            'routing_id'       => $routing->id,
            'order_number'     => 'PO-SCEN-100',
            'quantity_ordered' => 50,
            'status'           => 'planned',
            'start_date'       => Carbon::now()->startOfDay(),
            'end_date'         => Carbon::now()->addDays(3)->endOfDay(),
            'due_date'         => Carbon::now()->addDays(3)->endOfDay(),
        ]);

        $orderOp1 = ProductionOrderOperation::create([
            'tenant_id'            => $this->tenant->id,
            'production_order_id'  => $order->id,
            'routing_operation_id' => $routingOp1->id,
            'work_center_id'       => $workCenter->id,
            'machine_id'           => $machine1->id,
            'sequence'             => 10,
            'name'                 => 'Rough Milling',
            'operation_number'     => 'OP10',
            'setup_time_planned'   => 30,
            'run_time_per_unit'    => 2,
            'status'               => 'pending',
        ]);

        $orderOp2 = ProductionOrderOperation::create([
            'tenant_id'            => $this->tenant->id,
            'production_order_id'  => $order->id,
            'routing_operation_id' => $routingOp2->id,
            'work_center_id'       => $workCenter->id,
            'machine_id'           => $machine1->id,
            'sequence'             => 20,
            'name'                 => 'Finish Milling',
            'operation_number'     => 'OP20',
            'setup_time_planned'   => 20,
            'run_time_per_unit'    => 3,
            'status'               => 'pending',
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number'     => 'SCH-SCEN-001',
            'status'              => 'draft',
        ]);

        $now = Carbon::now()->startOfDay()->addHours(8);

        $op1 = ProductionScheduleOperation::create([
            'tenant_id'                      => $this->tenant->id,
            'production_schedule_id'         => $schedule->id,
            'production_order_id'            => $order->id,
            'production_order_operation_id' => $orderOp1->id,
            'sequence'                       => 10,
            'work_center_id'                 => $workCenter->id,
            'machine_id'                     => $machine1->id,
            'planned_start'                  => $now->copy(),
            'planned_finish'                 => $now->copy()->addMinutes(130),
            'baseline_start'                 => $now->copy(),
            'baseline_finish'                => $now->copy()->addMinutes(130),
            'planned_duration_minutes'       => 130,
            'status'                         => 'scheduled',
            'locked'                         => false,
            'manual_override'                => false,
            'version'                        => 1,
            'priority'                       => 3,
        ]);

        $op2 = ProductionScheduleOperation::create([
            'tenant_id'                      => $this->tenant->id,
            'production_schedule_id'         => $schedule->id,
            'production_order_id'            => $order->id,
            'production_order_operation_id' => $orderOp2->id,
            'sequence'                       => 20,
            'work_center_id'                 => $workCenter->id,
            'machine_id'                     => $machine1->id,
            'planned_start'                  => $now->copy()->addMinutes(150),
            'planned_finish'                 => $now->copy()->addMinutes(320),
            'baseline_start'                 => $now->copy()->addMinutes(150),
            'baseline_finish'                => $now->copy()->addMinutes(320),
            'planned_duration_minutes'       => 170,
            'status'                         => 'scheduled',
            'locked'                         => false,
            'manual_override'                => false,
            'version'                        => 1,
            'priority'                       => 3,
        ]);

        return [
            'workCenter' => $workCenter,
            'machine1'   => $machine1,
            'machine2'   => $machine2,
            'order'      => $order,
            'schedule'   => $schedule,
            'op1'        => $op1,
            'op2'        => $op2,
        ];
    }
}
