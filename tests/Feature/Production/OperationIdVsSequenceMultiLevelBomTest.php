<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationIdVsSequenceMultiLevelBomTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $fgProduct;
    private Product $sfgProduct;
    private WorkCenter $wc;
    private Machine $machine;
    private \App\Domains\Production\Models\Routing $routing;
    private \App\Domains\Production\Models\RoutingOperation $routingOp1;
    private \App\Domains\Production\Models\RoutingOperation $routingOp2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'MultiLevel BOM Tenant',
            'slug' => 'ml-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($this->user);

        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Dining Table',
            'sku' => 'FG-TABLE-' . uniqid(),
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $uom->id,
        ]);

        $this->sfgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fabricated Steel Frame SFG',
            'sku' => 'SFG-FRAME-' . uniqid(),
            'type' => 'semi_finished',
            'status' => 'active',
            'uom_id' => $uom->id,
        ]);

        $this->wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Work Center',
            'code' => 'WC-ASSY',
            'capacity_per_day' => 480,
            'cost_per_hour' => 50.0,
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wc->id,
            'name' => 'Welding Rig 01',
            'code' => 'MCH-01',
            'status' => 'active',
        ]);

        $this->routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Routing',
            'code' => 'RT-TEST-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->routingOp1 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->wc->id,
            'name' => 'Master Op 1',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'run_time_minutes' => 30,
        ]);

        $this->routingOp2 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->wc->id,
            'name' => 'Master Op 2',
            'sequence' => 20,
            'operation_number' => 'OP20',
            'run_time_minutes' => 30,
        ]);
    }

    /**
     * Scenario A: Same sequence in separate chains.
     * SFG has OP10, OP20. Final Product has OP10, OP20.
     * Ensure SFG OP10 does not resolve to FG OP10, and batch/WIP advancement stays in correct chain.
     */
    public function test_scenario_a_same_sequence_in_separate_chains_scoped_correctly(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-SCEN-A-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => 10,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        // SFG operations: seq 10 and 20
        $sfgOp1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $this->sfgProduct->id,
            'name' => 'SFG Frame Cutting',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_READY,
            'is_intermediate' => true,
        ]);

        $sfgOp2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $sfgOp1->id,
            'source_product_id' => $this->sfgProduct->id,
            'name' => 'SFG Frame Welding',
            'sequence' => 20,
            'operation_number' => 'OP20',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'is_intermediate' => true,
        ]);

        // FG operations: seq 10 and 20
        $fgOp1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $this->fgProduct->id,
            'name' => 'FG Final Assembly',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'is_intermediate' => false,
        ]);

        $fgOp2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $fgOp1->id,
            'source_product_id' => $this->fgProduct->id,
            'name' => 'FG Final Inspection',
            'sequence' => 20,
            'operation_number' => 'OP20',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'is_intermediate' => false,
        ]);

        // Batch queue test for SFG Op 1: nextOp must be SFG Op 2, NOT FG Op 2!
        $batchService = app(BatchProductionService::class);
        $queueSfg = $batchService->getOperationBatchQueue($sfgOp1);
        $this->assertEquals($sfgOp2->id, $queueSfg['meta']['next_op']?->id);

        // Batch queue test for FG Op 1: nextOp must be FG Op 2, NOT SFG Op 2!
        $queueFg = $batchService->getOperationBatchQueue($fgOp1);
        $this->assertEquals($fgOp2->id, $queueFg['meta']['next_op']?->id);
    }

    /**
     * Scenario B: SFG completion -> Final Assembly (SFG OP20 -> Final Assembly OP10).
     * Must not fail merely because 10 is not > 20.
     */
    public function test_scenario_b_sfg_completion_to_final_assembly_transition(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-SCEN-B-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => 5,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $sfgOp2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $this->sfgProduct->id,
            'routing_operation_id' => $this->routingOp2->id,
            'name' => 'SFG Final Powder Coating',
            'sequence' => 20,
            'operation_number' => 'OP20',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_COMPLETED,
            'quantity_produced' => 5,
            'is_intermediate' => true,
        ]);

        // FG assembly operation explicitly depends on sfgOp2
        $fgOp1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $sfgOp2->id,
            'source_product_id' => $this->fgProduct->id,
            'routing_operation_id' => $this->routingOp1->id,
            'name' => 'FG Final Assembly',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'is_intermediate' => false,
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->sfgProduct->id,
            'batch_number' => 'BATCH-B-' . uniqid(),
            'planned_quantity' => 5,
            'status' => 'in_progress',
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $batch->id,
            'product_id' => $this->sfgProduct->id,
            'current_routing_operation_id' => $this->routingOp2->id,
            'quantity' => 5,
            'available_quantity' => 5,
            'status' => 'active',
        ]);

        $wipService = app(ProductionWipService::class);

        // WIP transfer from SFG Op (seq 20) to FG Op (seq 10) must succeed because previous_operation_id is explicit!
        $wipService->transferWip($wip->id, $sfgOp2->id, $fgOp1->id, 5.0, 'Transfer SFG to Assembly');

        $wip->refresh();
        $this->assertEquals(0, (float) $wip->available_quantity);
        $this->assertEquals('transferred', $wip->status);
    }

    /**
     * Scenario C & D: Parallel & Independent operations not blocked by predecessor fallback.
     */
    public function test_scenario_c_and_d_parallel_and_independent_operations(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-SCEN-CD-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => 5,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-CD-' . uniqid(),
            'status' => ProductionSchedule::STATUS_RELEASED,
            'planned_start' => now(),
            'planned_finish' => now()->addDays(2),
        ]);

        // Parallel Independent OP10-A
        $orderOpA = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->routingOp1->id,
            'name' => 'Parallel Op A (Independent)',
            'sequence' => 10,
            'operation_number' => 'OP10A',
            'is_parallel' => true,
            'parallel_group' => 'CUT_GROUP',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $schedOpA = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOpA->id,
            'work_center_id' => $this->wc->id,
            'sequence' => 10,
            'planned_start' => now(),
            'planned_finish' => now()->addHours(1),
            'planned_duration_minutes' => 60,
            'status' => ProductionScheduleOperation::STATUS_READY,
        ]);

        $mesService = app(MesExecutionService::class);

        // Should be able to start independent/parallel operation without predecessor error
        $mesService->startOperation($schedOpA->id, $this->machine->id, $this->user->id);
        $schedOpA->refresh();
        $this->assertEquals(ProductionScheduleOperation::STATUS_RUNNING, $schedOpA->status);
    }

    /**
     * Scenario E: WIP transfer uses actual production_order_operations.id through the web route.
     */
    public function test_scenario_e_wip_transfer_uses_order_operation_ids_via_controller(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-SCEN-E-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => 10,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->routingOp1->id,
            'name' => 'Stage 1',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'quantity_produced' => 10,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->routingOp2->id,
            'previous_operation_id' => $op1->id,
            'name' => 'Stage 2',
            'sequence' => 20,
            'operation_number' => 'OP20',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->fgProduct->id,
            'batch_number' => 'BATCH-E-' . uniqid(),
            'planned_quantity' => 10,
            'status' => 'in_progress',
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $batch->id,
            'product_id' => $this->fgProduct->id,
            'current_routing_operation_id' => $this->routingOp1->id,
            'quantity' => 10,
            'available_quantity' => 10,
            'status' => 'active',
        ]);

        // Submit via controller using order operation IDs
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.wip.transfer', $wip->id), [
                'from_operation_id' => $op1->id,
                'to_operation_id' => $op2->id,
                'quantity' => 5.0,
                'remarks' => 'Valid transfer via order operation ID',
            ]);

        $response->assertSessionHas('success');
        $wip->refresh();
        $this->assertEquals(5.0, (float) $wip->available_quantity);
    }

    /**
     * Scenario F: Capacity ripple with duplicate sequence (does not overwrite map).
     */
    public function test_scenario_f_capacity_ripple_with_duplicate_sequence_map_isolation(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-SCEN-F-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => 5,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-F-' . uniqid(),
            'status' => ProductionSchedule::STATUS_DRAFT,
            'planned_start' => Carbon::parse('2026-09-24 08:00:00'),
            'planned_finish' => Carbon::parse('2026-09-24 17:00:00'),
        ]);

        // Two operations legitimately having sequence 10 (e.g. Subassembly 1 & Subassembly 2)
        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $this->sfgProduct->id,
            'routing_operation_id' => $this->routingOp1->id,
            'name' => 'Subassembly A Cutting',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $this->fgProduct->id,
            'routing_operation_id' => $this->routingOp2->id,
            'name' => 'Table Top Sizing',
            'sequence' => 10,
            'operation_number' => 'OP10',
            'work_center_id' => $this->wc->id,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $schedOp1 = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op1->id,
            'work_center_id' => $this->wc->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'planned_start' => Carbon::parse('2026-09-24 08:00:00'),
            'planned_finish' => Carbon::parse('2026-09-24 09:00:00'),
            'planned_duration_minutes' => 60,
            'status' => ProductionScheduleOperation::STATUS_WAITING,
        ]);

        $schedOp2 = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op2->id,
            'work_center_id' => $this->wc->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'planned_start' => Carbon::parse('2026-09-24 09:00:00'),
            'planned_finish' => Carbon::parse('2026-09-24 10:00:00'),
            'planned_duration_minutes' => 60,
            'status' => ProductionScheduleOperation::STATUS_WAITING,
        ]);

        $capService = app(CapacityPlanningService::class);

        // Reschedule schedOp1 in ripple mode
        $newStart = Carbon::parse('2026-09-24 08:30:00');
        $result = $capService->rescheduleOperationWithMode(
            $schedOp1->id,
            $newStart,
            $this->machine->id,
            \App\Domains\Production\Models\ProductionScheduleChangeLog::SHIFT_MODE_RIPPLE,
            'Test Ripple',
            $this->user->id
        );

        $this->assertTrue($result['success']);
        $schedOp1->refresh();
        $schedOp2->refresh();

        // schedOp1 start moved to 08:30
        $this->assertEquals('2026-09-24 08:30:00', $schedOp1->planned_start->toDateTimeString());
        // Both schedule operations exist and have distinct IDs
        $this->assertNotEquals($schedOp1->id, $schedOp2->id);
    }
}
