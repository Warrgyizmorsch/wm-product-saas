<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionCostAdjustmentService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ReworkService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReworkCostingAndMaterialTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId;
    private Product $finishedGood;
    private Product $rawMaterial;
    private Warehouse $warehouse;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionOrder $order;
    private ProductionNcr $ncr;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'Rework Test Tenant',
            'slug' => 'rework-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->tenantId = $tenant->id;

        $this->user = User::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Rework Supervisor',
            'email' => 'rework@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);
        $this->withHeaders(['X-Tenant' => 'rework-test']);

        $uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Pieces',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Shopfloor Store',
            'code' => 'SF-STORE',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Industrial Pump Model A',
            'sku' => 'FG-PUMP-A',
            'type' => 'finished_good',
            'unit_cost' => 200.00,
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Brass Gasket Ring',
            'sku' => 'RM-GASKET-01',
            'type' => 'raw_material',
            'unit_cost' => 25.00,
            'cost_price' => 25.00,
            'status' => 'active',
            'inventory_valuation_method' => 'FIFO',
        ]);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50.0,
            'available_qty' => 50.0,
            'unit_cost' => 25.00,
        ]);

        // Pre-create incoming stock lot for FIFO
        StockTransaction::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'IN',
            'reference_type' => 'Purchase Receipt',
            'quantity' => 50.0,
            'balance_qty' => 50.0,
            'unit_cost' => 25.00,
            'total_value' => 1250.00,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Assembly & Rework Center',
            'code' => 'WC-RWK-01',
            'cost_per_hour' => 120.00, // 2.00 / minute
            'overhead_rate' => 60.00,  // 1.00 / minute
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Lathe 01',
            'code' => 'M-LATHE-01',
            'status' => 'active',
        ]);

        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->finishedGood->id,
            'routing_number' => 'RT-PUMP-001',
            'name' => 'Pump Assembly Route',
            'version' => '1.0.0',
            'status' => 'active',
        ]);

        $routingOp = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Precision Turning',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 20.0,
            'labor_cost_rate' => 2.00,
            'machine_cost_rate' => 0.00,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-TEST-001',
            'product_id' => $this->finishedGood->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_ordered' => 10.0,
            'quantity_produced' => 0.0,
            'quantity_rejected' => 2.0,
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $this->order->id,
            'routing_operation_id' => $routingOp->id,
            'operation_number' => 'OP-10',
            'name' => 'Precision Turning',
            'sequence' => 10,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'target_produced_qty' => 10.0,
            'quantity_produced' => 8.0,
            'quantity_rejected' => 2.0,
            'status' => 'running',
        ]);

        $this->ncr = ProductionNcr::create([
            'tenant_id' => $this->tenantId,
            'ncr_number' => 'NCR-TEST-001',
            'category' => 'dimensional_error',
            'status' => 'open',
            'disposition_type' => 'rework',
            'production_order_id' => $this->order->id,
            'production_order_operation_id' => $orderOp->id,
            'description' => 'Defective flange diameter requiring rework.',
        ]);

        ProductionWip::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $this->order->id,
            'product_id' => $this->finishedGood->id,
            'current_routing_operation_id' => $routingOp->id,
            'quantity' => 10.0,
            'available_quantity' => 8.0,
            'completed_quantity' => 0.0,
            'rejected_quantity' => 2.0,
            'scrap_quantity' => 0.0,
            'rework_quantity' => 2.0,
            'material_cost' => 500.00,
            'labor_cost' => 100.00,
            'machine_cost' => 50.00,
            'overhead_cost' => 50.00,
            'total_value' => 700.00,
            'status' => 'active',
        ]);
    }

    public function test_dynamic_rework_cost_estimate_derives_from_work_center_rates_without_hardcoded_fallback(): void
    {
        $reworkService = app(ReworkService::class);

        // 1 default operation of 30 minutes (30 mins total)
        // WorkCenter cost_per_hour = 120 (2.00/min), overhead_rate = 60 (1.00/min)
        // Expected dynamic estimate = 30 * (2.00 + 1.00) = 90.00
        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->assertNotEquals(150.00, (float) $rework->cost_estimate, 'Must NOT use the hardcoded 150.00 fallback.');
        $this->assertEquals(90.00, (float) $rework->cost_estimate, 'Dynamic estimate must derive accurately from WorkCenter rates.');
        $this->assertEquals(1, $rework->operations()->count());
    }

    public function test_rework_material_issue_reduces_stock_creates_issue_and_updates_wip_and_rework_cost(): void
    {
        $reworkService = app(ReworkService::class);
        $materialService = app(ProductionMaterialService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $firstOp = $rework->operations->first();

        // Stock before issue
        $stockBefore = ProductWarehouseStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');
        $this->assertEquals(50.0, (float) $stockBefore);

        // Issue 4 units of raw material to rework
        $issue = $materialService->issueReworkMaterial(
            reworkOrderId: $rework->id,
            productId: $this->rawMaterial->id,
            quantity: 4.0,
            warehouseId: $this->warehouse->id,
            reworkOperationId: $firstOp->id,
            remarks: 'Additional gaskets consumed for rework refabrication',
            userId: $this->user->id
        );

        // 1. Stock reduced
        $stockAfter = ProductWarehouseStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');
        $this->assertEquals(46.0, (float) $stockAfter);

        // 2. ProductionOrderIssue created with rework linkage
        $this->assertInstanceOf(ProductionOrderIssue::class, $issue);
        $this->assertEquals($rework->id, $issue->rework_order_id);
        $this->assertEquals($firstOp->id, $issue->rework_operation_id);
        $this->assertEquals('rework', $issue->issue_type);
        $this->assertEquals(4.0, (float) $issue->quantity_issued);
        $this->assertNull($issue->reservation_id);

        // 3. Stock transaction created with outflow
        $this->assertDatabaseHas('stock_transactions', [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'OUT',
            'reference_type' => 'Production Material Issue',
            'reference_id' => $this->order->id,
            'quantity' => 4.0,
        ]);

        // 4. Material cost added to parent WIP sheet (4 * 25.00 = 100.00)
        $wip = ProductionWip::where('production_order_id', $this->order->id)->first();
        $this->assertEquals(600.00, (float) $wip->material_cost); // 500 initial + 100
        $this->assertEquals(800.00, (float) $wip->total_value);

        // 5. Rework actual cost incremented
        $rework->refresh();
        $this->assertEquals(100.00, (float) $rework->actual_cost);
        $this->assertEquals(100.00, (float) $rework->material_cost);
    }

    public function test_rework_operation_completion_calculates_cost_dynamically_without_hardcoded_rates(): void
    {
        $reworkService = app(ReworkService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $firstOp = $rework->operations->first();

        // Start operation
        $reworkService->startOperation($firstOp->id, $this->tenantId);
        $firstOp->refresh();
        $this->assertEquals('running', $firstOp->status);

        // Complete operation with 10 mins setup, 20 mins run = 30 minutes total (0.5 hours)
        // WorkCenter: cost_per_hour = 120 (2.00/min), overhead_rate = 60 (1.00/min)
        // Machine not linked to op -> machine rate 0
        // Expected labor = 30 * 2.00 = 60.00
        // Expected overhead = 30 * 1.00 = 30.00
        // Expected added cost = 90.00
        $reworkService->completeOperation($firstOp->id, [
            'setup_time_actual' => 10.0,
            'processing_time_actual' => 20.0,
        ], $this->tenantId);

        $firstOp->refresh();
        $this->assertEquals('completed', $firstOp->status);
        $this->assertEquals(10.0, (float) $firstOp->setup_time_actual);
        $this->assertEquals(0.3333, round((float) $firstOp->processing_time_actual, 4));

        $rework->refresh();
        $this->assertEquals(90.00, (float) $rework->actual_cost, 'Operation cost must use configured WorkCenter rates (90.00), not hardcoded rates.');
        $this->assertEquals(0.5, (float) $rework->labor_hours_actual);
    }

    public function test_double_counting_protection_for_material_cost_on_rework_completion(): void
    {
        $reworkService = app(ReworkService::class);
        $materialService = app(ProductionMaterialService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        // 1. Issue material worth 100.00 (4 units @ 25.00)
        $materialService->issueReworkMaterial(
            reworkOrderId: $rework->id,
            productId: $this->rawMaterial->id,
            quantity: 4.0,
            warehouseId: $this->warehouse->id,
            remarks: 'Rework raw material',
            userId: $this->user->id
        );

        $rework->refresh();
        $this->assertEquals(100.00, (float) $rework->actual_cost);

        // 2. Complete all operations in the rework order
        foreach ($rework->operations as $op) {
            $reworkService->startOperation($op->id, $this->tenantId);
            $reworkService->completeOperation($op->id, [
                'setup_time_actual' => 5.0,
                'processing_time_actual' => 10.0, // 15 mins total per op -> 15 * 3.00 = 45.00
            ], $this->tenantId);
        }

        $rework->refresh();
        $this->assertEquals('completed', $rework->status);

        // Total rework cost: 100.00 material + (1 op * 45.00 non-material = 45.00) = 145.00
        $this->assertEquals(145.00, (float) $rework->actual_cost);

        // 3. CRITICAL: ProductionCostAdjustment created on parent order MUST NOT double count material!
        $adjustments = ProductionCostAdjustment::where('production_order_id', $this->order->id)
            ->where('category', ProductionCostAdjustment::CATEGORY_REWORK_EXPENSE)
            ->get();

        $this->assertNotEmpty($adjustments);

        // Material component MUST NOT be present in adjustments
        $materialAdj = $adjustments->where('cost_component', ProductionCostAdjustment::COMPONENT_MATERIAL);
        $this->assertTrue($materialAdj->isEmpty(), 'Material cost MUST NOT be duplicated in ProductionCostAdjustment.');

        // Labor and Overhead adjustments should exist for 30.00 and 15.00 respectively
        $laborAdj = $adjustments->firstWhere('cost_component', ProductionCostAdjustment::COMPONENT_LABOR);
        $overheadAdj = $adjustments->firstWhere('cost_component', ProductionCostAdjustment::COMPONENT_OVERHEAD);

        $this->assertNotNull($laborAdj);
        $this->assertEquals(30.00, (float) $laborAdj->amount);

        $this->assertNotNull($overheadAdj);
        $this->assertEquals(15.00, (float) $overheadAdj->amount);

        // 4. Verify Final Costing Summary strictly avoids double counting
        $adjService = app(ProductionCostAdjustmentService::class);
        $automaticCosts = [
            'material' => ['actual' => 600.00], // includes initial 500 + 100 rework material issue
            'labor' => ['actual' => 100.00],
            'machine' => ['actual' => 50.00],
            'overhead' => ['actual' => 50.00],
            'totals' => ['actual' => 800.00],
        ];

        $summary = $adjService->getFinalCostingSummary($this->order, $automaticCosts);

        // Material manual is 0.00, final is 600.00 (not 700.00!)
        $this->assertEquals(0.00, $summary['material']['manual']);
        $this->assertEquals(600.00, $summary['material']['final']);

        // Labor gets auto (100) + manual rework (30) = 130.00
        $this->assertEquals(30.00, $summary['labor']['manual']);
        $this->assertEquals(130.00, $summary['labor']['final']);

        // Overhead gets auto (50) + manual rework (15) = 65.00
        $this->assertEquals(15.00, $summary['overhead']['manual']);
        $this->assertEquals(65.00, $summary['overhead']['final']);
    }

    public function test_rework_failure_converts_rejected_units_permanently_to_scrap(): void
    {
        $reworkService = app(ReworkService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->assertEquals('draft', $rework->status);

        $rework = $reworkService->failRework($rework->id, [
            'reason' => 'Structural crack detected during disassembly, beyond repair limits.',
        ], $this->tenantId);

        $this->assertEquals('failed', $rework->status);

        // Operations should be cancelled
        foreach ($rework->operations as $op) {
            $this->assertEquals('cancelled', $op->status);
        }

        // Scrap disposal record should be registered
        $this->assertDatabaseHas('production_scrap_disposals', [
            'tenant_id' => $this->tenantId,
            'ncr_id' => $this->ncr->id,
            'status' => 'pending_approval',
        ]);
    }

    public function test_issue_material_via_controller_endpoint_web_and_json(): void
    {
        $reworkService = app(ReworkService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        // Test JSON request
        $response = $this->postJson(route('production.quality.rework.issue-material', $rework->id), [
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2.0,
            'remarks' => 'API test issue',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Material successfully issued for rework.',
        ]);

        $this->assertDatabaseHas('production_order_issues', [
            'rework_order_id' => $rework->id,
            'product_id' => $this->rawMaterial->id,
            'quantity_issued' => 2.0,
            'issue_type' => 'rework',
        ]);
    }

    public function test_rework_material_request_generates_store_requisition_slip_without_warehouse(): void
    {
        $reworkService = app(ReworkService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $response = $this->post(route('production.quality.rework.request-material', $rework->id), [
            'product_id' => $this->rawMaterial->id,
            'quantity' => 3.5,
            'remarks' => 'Need extra replacement pipes for rework',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check slip created in production_requisition_slips
        $this->assertDatabaseHas('production_requisition_slips', [
            'tenant_id' => $this->tenantId,
            'production_order_id' => $this->order->id,
            'rework_order_id' => $rework->id,
            'source_type' => 'rework_order',
            'status' => 'pending',
        ]);

        // Slip item should have warehouse_id as null (storekeeper decides)
        $this->assertDatabaseHas('production_requisition_slip_items', [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => null,
            'quantity_planned' => 3.5,
        ]);
    }

    public function test_store_issue_fulfills_rework_requisition_and_updates_costs_and_parent_order(): void
    {
        $reworkService = app(ReworkService::class);

        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $slip = $reworkService->requestMaterial(
            reworkId: $rework->id,
            tenantId: $this->tenantId,
            productId: $this->rawMaterial->id,
            quantity: 2.0,
            reason: 'Store fulfillment test'
        );

        $item = $slip->items->first();
        $this->assertNotNull($item);

        // Storekeeper issues stock from Store Material Request screen
        $matReqService = app(\App\Domains\Sales\Services\MaterialRequestService::class);
        $issuedQty = $matReqService->issue(
            tenantId: $this->tenantId,
            itemId: $item->id,
            warehouseId: $this->warehouse->id,
            quantity: 2.0,
            remarks: 'Issued by Storekeeper'
        );

        $this->assertEquals(2.0, $issuedQty);

        // Verify production_order_issues created with rework_order_id and parent production_order_id
        $this->assertDatabaseHas('production_order_issues', [
            'tenant_id' => $this->tenantId,
            'production_order_id' => $this->order->id,
            'rework_order_id' => $rework->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_issued' => 2.0,
        ]);

        // Verify rework actual cost updated with material cost
        $rework->refresh();
        $this->assertGreaterThan(0.0, (float) $rework->material_cost);
        $this->assertEquals(50.0, (float) $rework->material_cost); // 2 * $25.00
    }

    public function test_rework_operation_inherits_machine_and_logs_machine_hours(): void
    {
        $reworkService = app(ReworkService::class);

        // NCR is linked to this->machine
        $rework = $reworkService->createReworkOrder($this->tenantId, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
        ]);

        $firstOp = $rework->operations->first();
        $this->assertNotNull($firstOp);
        $this->assertEquals($this->machine->id, $firstOp->machine_id);

        // Start Op
        $reworkService->startOperation($firstOp->id, $this->tenantId);

        // Complete Op with 60 minutes run time (1.0 hr)
        $reworkService->completeOperation($firstOp->id, [
            'setup_time_actual' => 0.0,
            'processing_time_actual' => 60.0,
        ], $this->tenantId);

        $rework->refresh();
        $this->assertEquals(1.0, (float) $rework->machine_hours_actual);
        $this->assertEquals(1.0, (float) $rework->labor_hours_actual);
    }
}
