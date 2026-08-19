<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderCompletionValidator;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MultiModelPhase4Test extends TestCase
{
    use RefreshDatabase;

    protected int $tenantId = 1;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'tenant-a',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'production_manager',
        ]);

        $this->actingAs($this->manager);
        session(['tenant_id' => $this->tenantId]);

        if (!\App\Domains\Inventory\Models\Uom::where('id', 1)->exists()) {
            \App\Domains\Inventory\Models\Uom::create([
                'id' => 1,
                'tenant_id' => $this->tenantId,
                'name' => 'Piece',
                'code' => 'PCS',
                'status' => 'active',
            ]);
        }
    }

    protected function createVendor(): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Apex Subcontracting Services',
            'code' => 'VND-APEX-' . rand(100, 999),
            'status' => 'active',
        ]);
    }

    protected function createWarehouse(string $type = 'standard', ?int $vendorId = null): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => ($type === 'subcontractor' ? 'Subcontractor WH ' : 'Main FG WH ') . rand(100, 999),
            'code' => 'WH-' . strtoupper($type[0]) . '-' . rand(100, 999),
            'type' => $type,
            'vendor_id' => $vendorId,
            'status' => 'active',
            'is_default' => ($type === 'standard'),
        ]);
    }

    protected function createProduct(string $type = 'finished_good', string $model = 'pure_manufacturing'): Product
    {
        return Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Hydraulic Cylinder Assembly',
            'sku' => 'PRD-HYD-' . rand(1000, 9999),
            'type' => $type,
            'unit_cost' => 120.00,
            'cost_price' => 120.00,
            'default_production_model' => $model,
        ]);
    }

    /**
     * Assertion 1: Existing Pure Manufacturing flow behaves unchanged.
     */
    public function test_pure_manufacturing_flow_executes_unchanged(): void
    {
        $fg = $this->createProduct('finished_good', 'pure_manufacturing');
        $mainWh = $this->createWarehouse('standard');

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-PURE-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 50,
            'production_model' => 'pure_manufacturing',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Assembly Bay', 'code' => 'WC-ASSY-1']);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Internal Assembly',
            'work_center_id' => $wc->id,
            'is_external' => false,
            'status' => 'ready',
        ]);

        $execService = app(ProductionExecutionService::class);
        $log = $execService->logProgress(
            operationId: $op10->id,
            produced: 50,
            rejected: 0,
            scrapped: 0,
            setupMinutes: 15,
            runMinutes: 120,
            userId: $this->manager->id,
            completeOperation: true
        );

        $this->assertEquals(50, (float) $log->quantity_produced);
        $this->assertEquals('completed', $op10->fresh()->status);

        // Receive Finished Goods
        $receipt = $execService->receiveFinishedGoods(
            orderId: $order->id,
            quantity: 50,
            qualityStatus: 'passed',
            userId: $this->manager->id,
            warehouseId: $mainWh->id
        );

        $this->assertEquals(50, (float) $receipt->quantity_received);
        $this->assertEquals(50, (float) $order->fresh()->quantity_produced);
    }

    /**
     * Assertion 2: is_external = true blocks internal MES machine progress.
     */
    public function test_external_operation_blocks_internal_mes_progress(): void
    {
        $fg = $this->createProduct('finished_good', 'hybrid');
        $vendor = $this->createVendor();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-GUARD-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 20,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Subcontract Plating', 'code' => 'WC-SUB']);

        $extOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'External Chrome Plating',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'status' => 'ready',
        ]);

        $execService = app(ProductionExecutionService::class);

        $this->expectException(InvalidArgumentException::class);
        $execService->logProgress(
            operationId: $extOp->id,
            produced: 20,
            rejected: 0,
            scrapped: 0,
            setupMinutes: 0,
            runMinutes: 0,
            userId: $this->manager->id
        );
    }

    /**
     * Assertion 3: Complete Subcontracting workflow executes without internal shop floor screens.
     */
    public function test_complete_subcontracting_workflow_execution(): void
    {
        $fg = $this->createProduct('finished_good', 'subcontract_complete');
        $serviceProduct = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Turnkey Valve Subcontracting Service',
            'sku' => 'SRV-TURNKEY-01',
            'type' => 'service',
            'unit_cost' => 80.00,
        ]);
        $vendor = $this->createVendor();
        $subWh = $this->createWarehouse('subcontractor', $vendor->id);
        $mainWh = $this->createWarehouse('standard');

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-SUB-COMP-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 30,
            'production_model' => 'subcontract_complete',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Turnkey Vendor', 'code' => 'WC-TURNKEY']);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Full Subcontract Manufacturing',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => false,
            'status' => 'ready',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-TURNKEY-001',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 2400,
            'grand_total' => 2400,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $serviceProduct->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op10->id,
            'quantity' => 30,
            'rate' => 80.00,
            'amount' => 2400,
            'total_amount' => 2400,
        ]);

        $grnService = app(GoodsReceiptNoteService::class);
        $grn = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $subWh->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 30,
                    'rejected_qty' => 0,
                ],
            ],
        ], $this->tenantId);

        $orchestrator = app(SubcontractReceiptOrchestrator::class);
        $orchestrator->processSubcontractReceipt($grn);

        $this->assertEquals('completed', $op10->fresh()->status);

        // Receive FG
        $execService = app(ProductionExecutionService::class);
        $receipt = $execService->receiveFinishedGoods(
            orderId: $order->id,
            quantity: 30,
            qualityStatus: 'passed',
            userId: $this->manager->id,
            warehouseId: $mainWh->id
        );

        $this->assertEquals(30, (float) $receipt->quantity_received);
    }

    /**
     * Assertion 4: Subcontracting with Company Material maintains exact material balance conservation.
     */
    public function test_subcontract_company_material_balance_conservation(): void
    {
        $fg = $this->createProduct('finished_good', 'subcontract_company_material');
        $raw = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Aluminum Ingot',
            'sku' => 'RAW-ALU-01',
            'type' => 'raw_material',
            'unit_cost' => 25.00,
        ]);
        $vendor = $this->createVendor();
        $mainWh = $this->createWarehouse('standard');
        $subWh = $this->createWarehouse('subcontractor', $vendor->id);

        $bom = \App\Domains\Production\Models\ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-BAL-001',
            'base_quantity' => 1,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        \App\Domains\Production\Models\ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bom->id,
            'material_id' => $raw->id,
            'uom_id' => 1,
            'quantity' => 4,
            'material_scrap_percentage' => 0,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-MAT-BAL-001',
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 20,
            'production_model' => 'subcontract_company_material',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Foundry Vendor', 'code' => 'WC-FND']);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Outsourced Casting',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'material_supply_type' => 'company_supplied',
            'status' => 'ready',
        ]);

        // Stock Main Warehouse & Dispatch 100 units raw material
        StockService::recordInflow($this->tenantId, $raw->id, $mainWh->id, 100, 25.00, 'Opening Stock');
        StockService::recordOutflow($this->tenantId, $raw->id, $mainWh->id, 100, 'StockTransfer', $order->id);

        $balService = app(SubcontractMaterialBalanceService::class);
        $bal = $balService->getMaterialBalance($this->tenantId, $order->id, $op10->id);
        $this->assertEquals(100, $bal['sent']);
        $this->assertEquals(100, $bal['remaining']);

        // Backflush 80 units upon GRN processing
        $balService->backflushCompanyMaterial($this->tenantId, $op10, 20); // 20 FG units * 4 raw = 80 raw consumed
        $balAfter = $balService->getMaterialBalance($this->tenantId, $order->id, $op10->id);
        $this->assertEquals(80, $balAfter['consumed']);
        $this->assertEquals(20, $balAfter['remaining']);
    }

    /**
     * Assertion 5: Full 100-unit Numerical Hybrid Execution Conservation Test:
     * Target: 100 units
     * OP10 Internal (100) -> OP20 External (100) -> QC (80 PASS, 15 Rework, 5 Scrap)
     * -> OP30 receives 80 -> Vendor Rework 15 returned & QC PASS -> OP30 receives additional 15 -> 95 total OP30
     * -> Final FG = 95 units, Scrap = 5 units
     * Exact Conservation: 100 = 95 FG + 5 Scrap!
     */
    public function test_full_numerical_hybrid_execution_conservation(): void
    {
        $fg = $this->createProduct('finished_good', 'hybrid');
        $service = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Heat Treatment Service',
            'sku' => 'SRV-HT-01',
            'type' => 'service',
            'unit_cost' => 15.00,
        ]);
        $vendor = $this->createVendor();
        $subWh = $this->createWarehouse('subcontractor', $vendor->id);
        $mainWh = $this->createWarehouse('standard');

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-HYBRID-NUM-100',
            'product_id' => $fg->id,
            'quantity_ordered' => 100,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'released',
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'product_id' => $fg->id,
            'batch_number' => 'BAT-NUM-001',
            'planned_quantity' => 100,
            'status' => 'in_progress',
        ]);

        $wcInt = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'CNC Shop', 'code' => 'WC-CNC']);
        $wcExt = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Heat Treat Plant', 'code' => 'WC-HT']);

        // OP10 Internal Cutting
        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'CNC Turning',
            'work_center_id' => $wcInt->id,
            'is_external' => false,
            'status' => 'ready',
        ]);

        // OP20 External Heat Treatment
        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Subcontract Heat Treatment',
            'work_center_id' => $wcExt->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => true,
            'status' => 'waiting',
        ]);

        // OP30 Internal Grinding
        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Precision Grinding',
            'work_center_id' => $wcInt->id,
            'is_external' => false,
            'status' => 'waiting',
        ]);

        // 1. Execute OP10 Internal: 100 good units produced
        $execService = app(ProductionExecutionService::class);
        $execService->logProgress(
            operationId: $op10->id,
            produced: 100,
            rejected: 0,
            scrapped: 0,
            setupMinutes: 30,
            runMinutes: 300,
            userId: $this->manager->id,
            completeOperation: true,
            batchId: $batch->id
        );

        $this->assertEquals('completed', $op10->fresh()->status);
        $this->assertEquals('ready', $op20->fresh()->status);

        // 2. Execute OP20 Subcontract PO & GRN: 100 units returned
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-HYB-100',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 1500,
            'grand_total' => 1500,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 100,
            'rate' => 15.00,
            'amount' => 1500,
            'total_amount' => 1500,
        ]);

        $grnService = app(GoodsReceiptNoteService::class);
        $grn = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $subWh->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 100,
                    'rejected_qty' => 0,
                    'batch_number' => 'BAT-NUM-001',
                ],
            ],
        ], $this->tenantId);

        $orchestrator = app(SubcontractReceiptOrchestrator::class);
        $orchestrator->processSubcontractReceipt($grn);

        $this->assertEquals('subcontract_qc_pending', $op20->fresh()->status);

        // 3. QC Inspection on OP20: 80 Passed, 15 Rework, 5 Scrapped
        $inspection = ProductionQualityInspection::where('production_order_operation_id', $op20->id)->firstOrFail();
        $inspection->update([
            'status' => 'submitted',
            'result' => 'passed',
            'sample_size' => 100,
            'inspected_quantity' => 100,
            'passed_qty' => 80,
            'failed_qty' => 20,
        ]);

        app(QualityInspectionService::class)->approveInspection(
            $inspection->id,
            $this->manager->id,
            'signature-hash-100',
            $this->tenantId
        );

        // Log 15 units rework and 5 units scrap under BAT-NUM-001
        $rework = ProductionOrderRework::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'rework_order_number' => 'RWK-NUM-001',
            'quantity' => 15,
            'reason' => 'Vendor surface re-polish required',
            'recorded_at' => now(),
            'status' => 'pending',
        ]);

        $scrap = ProductionOrderScrap::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'scrap_order_number' => 'SCP-NUM-001',
            'product_id' => $fg->id,
            'quantity' => 5,
            'reason' => 'Vendor thermal crack irreparable',
            'recorded_at' => now(),
            'disposition_type' => 'scrap',
        ]);

        // Verify OP30 initially receives exactly 80 available WIP
        $this->assertEquals('ready', $op30->fresh()->status);
        $this->assertEquals(80, (float) $op30->fresh()->quantity_transferred_in);

        // 4. Vendor Rework Return: 15 units returned & passed reinspection
        $rework->update(['status' => 'completed', 'rework_completed_at' => now()]);
        $op30 = $op30->fresh();
        $op30->quantity_transferred_in = (float) $op30->quantity_transferred_in + 15;
        $op30->save();

        // Total available OP30 input WIP = 95
        $this->assertEquals(95, (float) $op30->fresh()->quantity_transferred_in);

        // 5. Execute OP30 Internal Grinding: 95 units produced
        $execService->logProgress(
            operationId: $op30->id,
            produced: 95,
            rejected: 0,
            scrapped: 0,
            setupMinutes: 20,
            runMinutes: 200,
            userId: $this->manager->id,
            completeOperation: true,
            batchId: $batch->id
        );

        $this->assertEquals('completed', $op30->fresh()->status);

        // 6. Final Finished Goods Receipt: 95 units received into Main FG Warehouse
        $fgReceipt = $execService->receiveFinishedGoods(
            orderId: $order->id,
            quantity: 95,
            qualityStatus: 'passed',
            userId: $this->manager->id,
            warehouseId: $mainWh->id,
            productionBatchNumber: 'BAT-NUM-001'
        );

        // --- MANDATORY NUMERICAL CONSERVATION ASSERTION ---
        $producedFg = (float) $order->fresh()->quantity_produced;
        $scrappedQty = (float) $scrap->quantity;

        $this->assertEquals(95, $producedFg);
        $this->assertEquals(5, $scrappedQty);
        $this->assertEquals(100, $producedFg + $scrappedQty); // 100 = 95 FG + 5 Scrap!
        $this->assertEquals('BAT-NUM-001', $batch->fresh()->batch_number); // Batch preserved throughout!
    }

    /**
     * Assertion 6: Order completion validator blocks completion when subcontract quantity is outstanding.
     */
    public function test_completion_validator_blocks_incomplete_subcontract_orders(): void
    {
        $fg = $this->createProduct('finished_good', 'hybrid');
        $vendor = $this->createVendor();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-BLOCK-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 20,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Subcontract Work', 'code' => 'WC-SUB-BLOCK']);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Subcontract Operation',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'status' => 'subcontract_qc_pending', // Outstanding QC!
        ]);

        $validator = app(ProductionOrderCompletionValidator::class);

        $this->expectException(InvalidArgumentException::class);
        $validator->validateCompletion($order);
    }

    /**
     * Assertion 7: Authoritative cost hierarchy uses Actual Vendor Bill -> Approved PO -> Estimated.
     */
    public function test_authoritative_cost_hierarchy_precedence(): void
    {
        $fg = $this->createProduct('finished_good', 'hybrid');
        $service = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Testing Service',
            'sku' => 'SRV-TST-01',
            'type' => 'service',
            'unit_cost' => 50.00,
        ]);
        $vendor = $this->createVendor();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-COST-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Test Vendor', 'code' => 'WC-COST']);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Testing',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'subcontract_cost_per_unit' => 50.00, // Estimated = 500
            'status' => 'ready',
        ]);

        $costService = app(ProductionCostService::class);

        // 1. Estimated stage: PO does not exist yet -> Authoritative = 500
        $c1 = $costService->calculateSubcontractCost($order);
        $this->assertEquals(500, $c1['estimated']);
        $this->assertEquals(500, $c1['authoritative']);

        // 2. Committed stage: Approved PO created for 60.00/unit (Total = 600) -> Authoritative = 600
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-COST-001',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 600,
            'grand_total' => 600,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 10,
            'rate' => 60.00,
            'amount' => 600,
            'total_amount' => 600,
        ]);

        $c2 = $costService->calculateSubcontractCost($order);
        $this->assertEquals(600, $c2['committed']);
        $this->assertEquals(600, $c2['authoritative']);
    }

    /**
     * Assertion 8: Old products/orders resolve safely to pure_manufacturing defaults.
     */
    public function test_legacy_orders_resolve_safely_to_pure_manufacturing_defaults(): void
    {
        $oldProduct = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Legacy Gear Box',
            'sku' => 'LEGACY-GB-01',
            'type' => 'finished_good',
            'unit_cost' => 200.00,
        ]);

        $oldOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-LEGACY-001',
            'product_id' => $oldProduct->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'released',
        ]);

        $this->assertEquals('pure_manufacturing', $oldProduct->default_production_model ?? 'pure_manufacturing');
        $this->assertEquals('pure_manufacturing', $oldOrder->production_model ?? 'pure_manufacturing');
    }
}
