<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TableManufacturingProductSeeder;
use Database\Seeders\TableManufacturingProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManufacturingE2EValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected Product $fgTable;
    protected Product $sfgFrame;
    protected Product $sfgLeg;
    protected Product $sfgSupport;
    protected Product $sfgTop;
    protected Product $rmPipe;
    protected Product $rmTopBoard;
    protected Product $rmFastener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Table Demo Tenant',
            'slug' => 'warrgyizmorsch',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        session(['tenant_id' => $this->tenant->id]);

        // Seed fresh Table Manufacturing Demo data
        $this->seed(TableManufacturingProductSeeder::class);
        $this->seed(TableManufacturingProductionSeeder::class);

        $this->fgTable = Product::where('tenant_id', $this->tenant->id)->where('sku', 'FG-TBL-001')->firstOrFail();
        $this->sfgFrame = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-FRAME')->firstOrFail();
        $this->sfgLeg = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-LEG')->firstOrFail();
        $this->sfgSupport = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-SUPPORT')->firstOrFail();
        $this->sfgTop = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-TOP')->firstOrFail();
        $this->rmPipe = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-PIPE')->firstOrFail();
        $this->rmTopBoard = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-TOP-BOARD')->firstOrFail();
        $this->rmFastener = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-FASTENER')->firstOrFail();
    }

    public function test_full_table_manufacturing_e2e_lifecycle(): void
    {
        // 1. Fresh Seeding Assertions
        $this->assertEquals(8, Product::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(5, WorkCenter::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(6, Machine::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(5, ProductionBom::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(5, Routing::where('tenant_id', $this->tenant->id)->count());

        // 2. Create Production Order for 10 Industrial Dining Tables
        $bomFg = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-FG')->firstOrFail();
        $routingFg = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FG')->firstOrFail();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-TBL-10-E2E',
            'product_id' => $this->fgTable->id,
            'bom_id' => $bomFg->id,
            'routing_id' => $routingFg->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        // 3. Snapshot Multi-Level Routings
        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bomFg,
            $routingFg,
            10.0,
            $this->tenant->id,
            $this->user->id
        );

        $order->refresh();

        $op10 = $order->operations()->where('sequence', 10)->firstOrFail();
        $op20 = $order->operations()->where('sequence', 20)->firstOrFail();
        $op30 = $order->operations()->where('sequence', 30)->firstOrFail();
        $op40 = $order->operations()->where('sequence', 40)->firstOrFail();
        $op50 = $order->operations()->where('sequence', 50)->firstOrFail();
        $op60 = $order->operations()->where('sequence', 60)->firstOrFail();

        // Check Targets
        $this->assertEquals(40.0, $op10->target_produced_qty);
        $this->assertEquals($this->sfgLeg->id, $op10->source_product_id);

        $this->assertEquals(20.0, $op20->target_produced_qty);
        $this->assertEquals($this->sfgSupport->id, $op20->source_product_id);

        $this->assertEquals(10.0, $op30->target_produced_qty);
        $this->assertEquals($this->sfgFrame->id, $op30->source_product_id);

        $this->assertEquals(10.0, $op40->target_produced_qty);
        $this->assertEquals($this->sfgTop->id, $op40->source_product_id);

        $this->assertEquals(10.0, $op50->target_produced_qty);
        $this->assertEquals($this->sfgFrame->id, $op50->source_product_id);

        $this->assertEquals(10.0, $op60->target_produced_qty);
        $this->assertEquals($this->fgTable->id, $op60->source_product_id);

        // Initial Order Operations Readiness Verification
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op10->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op40->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op30->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op50->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op60->status);

        // 4. Raw Material Demand Verification
        $pipeDemand = ($op10->target_produced_qty * 0.75) + ($op20->target_produced_qty * 0.60);
        $this->assertEquals(42.0, $pipeDemand);

        $boardDemand = $op40->target_produced_qty * 1.0;
        $this->assertEquals(10.0, $boardDemand);

        $fastenerDemand = $op60->target_produced_qty * 1.0;
        $this->assertEquals(10.0, $fastenerDemand);

        // 5. Component Production Plan UI Route Verification
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertSee('Component Plan');
        $response->assertSee('40.00'); // Leg target
        $response->assertSee('20.00'); // Support target

        // 6. Forward Scheduling Verification
        $schedService = app(SchedulingService::class);
        $schedule = $schedService->generateSchedule($order, now());
        $this->assertNotNull($schedule);

        $sOp10 = $schedule->operations->firstWhere('production_order_operation_id', $op10->id);
        $sOp20 = $schedule->operations->firstWhere('production_order_operation_id', $op20->id);
        $sOp40 = $schedule->operations->firstWhere('production_order_operation_id', $op40->id);
        $sOp30 = $schedule->operations->firstWhere('production_order_operation_id', $op30->id);
        $sOp50 = $schedule->operations->firstWhere('production_order_operation_id', $op50->id);
        $sOp60 = $schedule->operations->firstWhere('production_order_operation_id', $op60->id);

        $this->assertEquals(ProductionScheduleOperation::STATUS_READY, $sOp10->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_READY, $sOp20->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_READY, $sOp40->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_WAITING, $sOp30->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_WAITING, $sOp50->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_WAITING, $sOp60->status);

        $times10 = $schedService->calculateOperationTimes($op10, 40.0);
        $this->assertEquals(90.0, $times10['total_minutes']);

        $times20 = $schedService->calculateOperationTimes($op20, 20.0);
        $this->assertEquals(50.0, $times20['total_minutes']);

        $times30 = $schedService->calculateOperationTimes($op30, 10.0);
        $this->assertEquals(135.0, $times30['total_minutes']);

        $times40 = $schedService->calculateOperationTimes($op40, 10.0);
        $this->assertEquals(90.0, $times40['total_minutes']);

        $times50 = $schedService->calculateOperationTimes($op50, 10.0);
        $this->assertEquals(70.0, $times50['total_minutes']);

        $times60 = $schedService->calculateOperationTimes($op60, 10.0);
        $this->assertEquals(160.0, $times60['total_minutes']);

        // 7. Release Order
        $order->update(['status' => ProductionOrder::STATUS_RELEASED]);
        $this->assertEquals(ProductionOrder::STATUS_RELEASED, $order->status);

        // 8. MES Execution — OP10 Table Legs (Incremental logging: 15, then 10, then 15 = 40)
        $mesService = app(ProductionExecutionService::class);
        $mesService->logProgress(operationId: $op10->id, produced: 15.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'Batch 1 Legs', userId: $this->user->id);
        $op10->refresh();
        $this->assertEquals(15.0, $op10->quantity_produced);

        $mesService->logProgress(operationId: $op10->id, produced: 10.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'Batch 2 Legs', userId: $this->user->id);
        $op10->refresh();
        $this->assertEquals(25.0, $op10->quantity_produced);

        $mesService->logProgress(operationId: $op10->id, produced: 15.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'Batch 3 Legs', userId: $this->user->id);
        $op10->refresh();
        $this->assertEquals(40.0, $op10->quantity_produced);
        // 9. MES Execution — OP20 Supports (20 Supports)
        $mesService->logProgress(operationId: $op20->id, produced: 20.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'All Supports', userId: $this->user->id);
        $op20->refresh();
        $op10->update(['status' => ProductionOrderOperation::STATUS_COMPLETED]);
        $op20->update(['status' => ProductionOrderOperation::STATUS_COMPLETED]);
        app(ProductionOrderService::class)->reconcileOperationReadiness($order);
        $op30->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op30->status);

        // 10. MES Execution — OP30 Frame MIG Welding (10 Frames)
        $mesService->logProgress(operationId: $op30->id, produced: 10.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'All Frames Welded', userId: $this->user->id);
        $op30->refresh();
        $this->assertEquals(10.0, $op30->quantity_produced);
        $this->assertTrue(in_array($op30->status, [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_COMPLETED]));

        // 11. MES Execution — OP50 Frame Surface Finishing (10 Frames)
        $batch50 = app(\App\Domains\Production\Services\BatchProductionService::class)->resolveBatchForProgress($order, $op50, null, 10.0);
        $mesService->logProgress(operationId: $op50->id, produced: 10.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'All Frames Finished', userId: $this->user->id, batchId: $batch50->id);
        $op50->refresh();
        $this->assertEquals(10.0, $op50->quantity_produced);
        $this->assertTrue(in_array($op50->status, [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_COMPLETED]));

        // 12. MES Execution — OP40 Table Top Processing (10 Tops)
        $batch40 = app(\App\Domains\Production\Services\BatchProductionService::class)->resolveBatchForProgress($order, $op40, null, 10.0);
        $mesService->logProgress(operationId: $op40->id, produced: 10.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'All Tops Processed', userId: $this->user->id, batchId: $batch40->id);
        $op40->refresh();
        $this->assertEquals(10.0, $op40->quantity_produced);
        $this->assertTrue(in_array($op40->status, [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_COMPLETED]));

        // 13. MES Execution — OP60 Final Assembly (10 Tables)
        $batch60 = app(\App\Domains\Production\Services\BatchProductionService::class)->resolveBatchForProgress($order, $op60, null, 10.0);
        $mesService->logProgress(operationId: $op60->id, produced: 10.0, rejected: 0.0, scrapped: 0.0, setupMinutes: 0.0, runMinutes: 0.0, remarks: 'Final Assembly Complete', userId: $this->user->id, batchId: $batch60->id);
        $op60->refresh();
        $this->assertEquals(10.0, $op60->quantity_produced);
        $this->assertTrue(in_array($op60->status, [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_COMPLETED]));

        // 14. WIP & Costing Verification
        $costService = app(ProductionCostService::class);
        $costSnapshot = $costService->calculateSubcontractCost($order);
        $this->assertNotNull($costSnapshot);

        // 15. Finished Goods Receipt
        $execService = app(ProductionExecutionService::class);
        $execService->receiveFinishedGoods($order->id, 10.0, 'passed', 'FG Receipt', $this->user->id);

        $orderService = app(ProductionOrderService::class);
        $orderService->evaluateAndAutoCompleteOrder($order, $this->user->id);

        $order->refresh();
        $this->assertEquals(ProductionOrder::STATUS_COMPLETED, $order->status);
        $this->assertEquals(10.0, $order->quantity_produced);

        // 16. Machine Breakdown Scenario Verification
        $machineCut = Machine::where('tenant_id', $this->tenant->id)->where('code', 'MAC-TBL-CUT-01')->firstOrFail();
        $maintService = app(MaintenanceWorkOrderService::class);
        $workOrder = $maintService->createWorkOrder($this->tenant->id, [
            'machine_id' => $machineCut->id,
            'type' => 'breakdown',
            'priority' => 'high',
            'problem_description' => 'Blade Jam & Motor Overheat',
        ]);
        $maintService->startWorkOrder($workOrder->id, $this->tenant->id, $this->user->id);
        $machineCut->refresh();
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $machineCut->status);

        // Complete Work Order restores active status
        $maintService->completeWorkOrder($workOrder->id, $this->tenant->id, $this->user->id);
        $machineCut->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $machineCut->status);
    }
}
