<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Repositories\PurchaseRequisitionRepository;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractingProcurementAndGrnInventoryFixTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $fgProduct;
    protected Product $serviceProduct;
    protected Product $rawMaterial;
    protected Warehouse $warehouse;
    protected Vendor $vendor;
    protected WorkCenter $workCenter;
    protected Routing $routing;
    protected RoutingOperation $rOp10;
    protected RoutingOperation $rOp20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'type' => 'standard',
            'is_active' => true,
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Subcontracting Vendor',
            'code' => 'VEND-SUB-01',
            'is_active' => true,
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Duty Radiator Assembly',
            'code' => 'RAD-750',
            'sku' => 'SKU-RAD-750',
            'item_type' => 'Goods',
            'type' => 'finished_good',
            'unit_cost' => 100.0,
            'is_active' => true,
        ]);

        $this->serviceProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Continuous Brazing Service',
            'code' => 'SVC-BRAZE',
            'sku' => 'SKU-SVC-BRAZE',
            'item_type' => 'Service',
            'type' => 'service',
            'unit_cost' => 15.0,
            'is_active' => true,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Aluminum Sheet',
            'code' => 'ALU-SHEET',
            'sku' => 'SKU-ALU-SHEET',
            'item_type' => 'Goods',
            'type' => 'raw_material',
            'unit_cost' => 20.0,
            'is_active' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subcontract Brazing Center',
            'code' => 'WC-SUB-01',
            'capacity_per_day' => 100,
            'cost_per_hour' => 50,
            'is_active' => true,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fgProduct->id,
            'name' => 'Radiator Routing',
            'code' => 'RT-RAD-750',
            'is_active' => true,
        ]);

        $this->rOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'is_external' => false,
        ]);

        $this->rOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'work_center_id' => $this->workCenter->id,
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 15.0,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'material_supply_type' => 'company_supplied',
        ]);
    }

    public function test_subcontract_pr_generation_retains_operation_context_and_distinguishes_from_material_pr(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-SUB-101',
            'product_id' => $this->fgProduct->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 50.0,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'work_center_id' => $this->workCenter->id,
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 15.0,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'material_supply_type' => 'company_supplied',
            'status' => ProductionOrderOperation::STATUS_RUNNING,
        ]);

        // 1. Generate Subcontract PR
        $orchestrator = app(SubcontractProcurementOrchestrator::class);
        $subcontractPr = $orchestrator->generateSubcontractRequisition($op20, $this->tenant->id, $this->user->id);
        $subcontractPr->update(['status' => 'Approved']);

        $this->assertNotNull($subcontractPr);
        $this->assertStringContainsString('Subcontract Service', $subcontractPr->notes);
        $this->assertStringContainsString('Op #20', $subcontractPr->notes);
        $this->assertStringContainsString('ORD-SUB-101', $subcontractPr->notes);

        // 2. Test Pending Items repository data enrichment
        $repo = app(PurchaseRequisitionRepository::class);
        $pendingData = $repo->getPendingItemsData(['group_by' => 'supplier']);

        $allPending = array_merge($pendingData['assignedItems'], $pendingData['unassignedItems']);
        $this->assertNotEmpty($allPending);

        $subItem = collect($allPending)->firstWhere('requisition_id', $subcontractPr->id);
        $this->assertNotNull($subItem);
        $this->assertTrue($subItem['is_subcontract']);
        $this->assertEquals(20, $subItem['operation_sequence']);
        $this->assertEquals('Continuous Brazing', $subItem['operation_name']);
        $this->assertEquals('ORD-SUB-101', $subItem['production_order_number']);
        $this->assertEquals($this->vendor->id, $subItem['vendor_id']);

        // 3. Create a normal Material PR (originating from MO) and ensure it is NOT marked as subcontract
        $prService = app(PurchaseRequisitionService::class);
        $matPr = $prService->storeRequisition([
            'requisition_date' => now()->toDateString(),
            'source_type' => 'mo',
            'production_order_id' => $order->id,
            'notes' => 'Raw Material Shortage PR',
            'items' => [
                [
                    'product_id' => $this->rawMaterial->id,
                    'quantity' => 10.0,
                    'estimated_cost' => 20.0,
                ]
            ]
        ], $this->tenant->id);
        $matPr->update(['status' => 'Approved']);

        $pendingData2 = $repo->getPendingItemsData([]);
        $allPending2 = array_merge($pendingData2['assignedItems'], $pendingData2['unassignedItems']);
        $matItem = collect($allPending2)->firstWhere('requisition_id', $matPr->id);
        $this->assertNotNull($matItem);
        $this->assertFalse($matItem['is_subcontract']);
    }

    public function test_physical_goods_grn_increases_stock_while_service_and_subcontract_grn_do_not(): void
    {
        $grnService = app(GoodsReceiptNoteService::class);

        // --- Scenario 1: Standard Physical Product PO & GRN ---
        $poPhysical = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-PHYS-001',
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'Approved',
            'date' => now()->toDateString(),
        ]);
        $poiPhysical = PurchaseOrderItem::create([
            'purchase_order_id' => $poPhysical->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 10.0,
            'rate' => 20.0,
            'amount' => 200.0,
            'total_amount' => 200.0,
        ]);

        $initialStock = StockService::getAvailableStock($this->rawMaterial->id, $this->warehouse->id);

        $grnPhysical = $grnService->storeGrn([
            'purchase_order_id' => $poPhysical->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poiPhysical->id,
                    'received_qty' => 10.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $this->assertNotNull($grnPhysical);
        $afterPhysicalStock = StockService::getAvailableStock($this->rawMaterial->id, $this->warehouse->id);
        $this->assertEquals($initialStock + 10.0, $afterPhysicalStock);

        // --- Scenario 2: Generic Service Product PO & GRN ---
        $poService = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-SVC-001',
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'Approved',
            'date' => now()->toDateString(),
        ]);
        $poiService = PurchaseOrderItem::create([
            'purchase_order_id' => $poService->id,
            'product_id' => $this->serviceProduct->id,
            'quantity' => 5.0,
            'rate' => 15.0,
            'amount' => 75.0,
            'total_amount' => 75.0,
        ]);

        $initialSvcStock = StockService::getAvailableStock($this->serviceProduct->id, $this->warehouse->id);

        $grnServiceObj = $grnService->storeGrn([
            'purchase_order_id' => $poService->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poiService->id,
                    'received_qty' => 5.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $afterSvcStock = StockService::getAvailableStock($this->serviceProduct->id, $this->warehouse->id);
        $this->assertEquals(0.0, $initialSvcStock);
        $this->assertEquals(0.0, $afterSvcStock); // MUST NOT increase physical stock
    }

    public function test_subcontract_grn_advances_operation_and_supports_partial_receipts_and_batch_continuity(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-EXEC-002',
            'product_id' => $this->fgProduct->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 20.0,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->fgProduct->id,
            'batch_number' => 'BAT-EXEC-002',
            'planned_quantity' => 20.0,
            'quantity' => 20.0,
            'status' => 'in_progress',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'work_center_id' => $this->workCenter->id,
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'quality_required' => false,
            'target_produced_qty' => 20.0,
            'quantity_produced' => 0.0,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
        ]);

        $poSub = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-SUB-002',
            'vendor_id' => $this->vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
        ]);

        $poiSub = PurchaseOrderItem::create([
            'purchase_order_id' => $poSub->id,
            'product_id' => $this->serviceProduct->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'quantity' => 20.0,
            'rate' => 15.0,
            'amount' => 300.0,
            'total_amount' => 300.0,
        ]);

        $grnService = app(GoodsReceiptNoteService::class);
        $receiptOrchestrator = app(SubcontractReceiptOrchestrator::class);

        // 1. First Partial Receipt of 8 units
        $grn1 = $grnService->storeGrn([
            'purchase_order_id' => $poSub->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poiSub->id,
                    'received_qty' => 8.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $receiptOrchestrator->processSubcontractReceipt($grn1);

        $op20->refresh();
        $this->assertEquals(8.0, $op20->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op20->status); // Partial: running

        // Verify Batch continuity
        $wip = ProductionWip::where('production_order_id', $order->id)->first();
        $this->assertNotNull($wip);
        $this->assertEquals($batch->id, $wip->production_batch_id);

        // 2. Second Partial Receipt of 12 units (Total = 20 units)
        $grn2 = $grnService->storeGrn([
            'purchase_order_id' => $poSub->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poiSub->id,
                    'received_qty' => 12.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $op20->refresh();
        $this->assertEquals(20.0, $op20->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op20->status); // Final: completed

        // 3. Test Idempotency: Processing $grn2 again does NOT increase quantity_produced or duplicate WIP
        $receiptOrchestrator->processSubcontractReceipt($grn2);
        $op20->refresh();
        $this->assertEquals(20.0, $op20->quantity_produced);
    }
}
