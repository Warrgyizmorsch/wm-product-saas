<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Domains\Purchase\Events\GoodsReceiptNoteApproved;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiModelPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected int $tenantId = 1;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'production_manager',
        ]);
        $this->actingAs($this->user);

        // Create default UOM if needed
        if (!\App\Domains\Inventory\Models\Uom::where('id', 1)->exists()) {
            \App\Domains\Inventory\Models\Uom::create([
                'id' => 1,
                'tenant_id' => $this->tenantId,
                'name' => 'Piece',
                'code' => 'PCS',
                'status' => 'active',
            ]);
        }

        session(['tenant_id' => $this->tenantId]);
    }

    protected function createVendor(): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Anodizing & Plating Co.',
            'code' => 'VND-' . rand(100, 999),
            'status' => 'active',
        ]);
    }

    protected function createSubcontractorWarehouse(Vendor $vendor): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontractor Facility - ' . $vendor->name,
            'code' => 'WH-SUB-' . $vendor->id,
            'type' => 'subcontractor',
            'vendor_id' => $vendor->id,
            'status' => 'active',
        ]);
    }

    protected function createMainWarehouse(): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Main Production Warehouse',
            'code' => 'WH-MAIN-' . rand(100, 999),
            'type' => 'standard',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    protected function createProducts(): array
    {
        $raw = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Aluminum Ingot 6061',
            'sku' => 'RAW-ALU-' . rand(1000, 9999),
            'type' => 'raw_material',
            'unit_cost' => 15.00,
            'cost_price' => 15.00,
        ]);

        $service = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'External Anodizing Service',
            'sku' => 'SRV-ANO-' . rand(1000, 9999),
            'type' => 'service',
            'unit_cost' => 5.00,
            'cost_price' => 5.00,
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Finished Engine Component',
            'sku' => 'FG-ENG-' . rand(1000, 9999),
            'type' => 'finished_good',
            'unit_cost' => 120.00,
            'cost_price' => 120.00,
            'default_production_model' => 'hybrid',
        ]);

        return [$raw, $service, $fg];
    }

    protected function createHybridRouting(Vendor $vendor, Product $service): Routing
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Machining Center',
            'code' => 'WC-MACH-' . rand(100, 999),
            'capacity_per_hour' => 10,
            'labor_rate' => 25.00,
            'overhead_rate' => 15.00,
        ]);

        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Assembly & Packing',
            'code' => 'WC-ASSY-' . rand(100, 999),
            'capacity_per_hour' => 15,
            'labor_rate' => 20.00,
            'overhead_rate' => 10.00,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-HYB-' . rand(100, 999),
            'name' => 'Hybrid Engine Routing',
            'product_id' => $service->id,
            'version' => '1.0.0',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'CNC Milling',
            'work_center_id' => $wc1->id,
            'setup_time_minutes' => 15,
            'processing_time_minutes' => 30,
            'is_external' => false,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'subcontract_lead_time_days' => 3,
            'subcontract_cost_per_unit' => 5.00,
            'subcontract_service_product_id' => $service->id,
            'material_supply_type' => 'company_supplied',
            'dispatch_buffer_days' => 1,
            'return_buffer_days' => 1,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Final Assembly & Packaging',
            'work_center_id' => $wc2->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 15,
            'is_external' => false,
        ]);

        return $routing;
    }

    public function test_subcontract_requirement_uses_existing_pr_model_and_service(): void
    {
        $vendor = $this->createVendor();
        [$raw, $service, $fg] = $this->createProducts();
        $routing = $this->createHybridRouting($vendor, $service);

        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Machining Center',
            'code' => 'WC-MACH-' . rand(100, 999),
            'capacity_per_hour' => 10,
            'labor_rate' => 25.00,
            'overhead_rate' => 15.00,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-HYB-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 50,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'subcontract_cost_per_unit' => 5.00,
            'subcontract_service_product_id' => $service->id,
            'material_supply_type' => 'company_supplied',
            'status' => 'waiting',
        ]);

        $orchestrator = app(SubcontractProcurementOrchestrator::class);
        $pr = $orchestrator->generateSubcontractRequisition($op20, $this->tenantId);

        $this->assertInstanceOf(PurchaseRequisition::class, $pr);
        $this->assertEquals('mo', $pr->source_type);
        $this->assertEquals($order->id, $pr->source_id);
        $this->assertEquals('Draft', $pr->status);
        $this->assertCount(1, $pr->items);
        $this->assertEquals($service->id, $pr->items->first()->product_id);
        $this->assertEquals(50, (float) $pr->items->first()->quantity);
    }

    public function test_existing_pr_to_po_conversion_preserves_subcontract_metadata(): void
    {
        $vendor = $this->createVendor();
        [$raw, $service, $fg] = $this->createProducts();
        $routing = $this->createHybridRouting($vendor, $service);

        $wc1 = WorkCenter::where('tenant_id', $this->tenantId)->first();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-HYB-002',
            'product_id' => $fg->id,
            'quantity_ordered' => 40,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'subcontract_cost_per_unit' => 5.00,
            'subcontract_service_product_id' => $service->id,
            'status' => 'waiting',
        ]);

        $orchestrator = app(SubcontractProcurementOrchestrator::class);
        $pr = $orchestrator->generateSubcontractRequisition($op20, $this->tenantId);
        $prItem = $pr->items->first();

        // Assign preferred vendor to service product for conversion
        $service->update(['preferred_vendor_id' => $vendor->id]);

        $prService = app(PurchaseRequisitionService::class);
        $res = $prService->createPosFromPendingItems([$prItem->id], 'po', $this->tenantId);

        $this->assertEquals(1, $res['count']);
        $po = PurchaseOrder::where('tenant_id', $this->tenantId)->latest('id')->first();

        $this->assertTrue((bool) $po->is_subcontract);
        $this->assertEquals($order->id, $po->production_order_id);
        $this->assertEquals($vendor->id, $po->vendor_id);
        $this->assertEquals('Draft', $po->status);

        $poItem = $po->items->first();
        $this->assertEquals($order->id, $poItem->production_order_id);
    }

    public function test_vendor_material_stock_transfer_lifecycle(): void
    {
        $vendor = $this->createVendor();
        $subWh = $this->createSubcontractorWarehouse($vendor);
        $mainWh = $this->createMainWarehouse();
        [$raw, $service, $fg] = $this->createProducts();

        // Stock Main Warehouse with 200 units of raw material
        StockService::recordInflow(
            tenantId: $this->tenantId,
            productId: $raw->id,
            warehouseId: $mainWh->id,
            quantity: 200,
            unitCost: 15.00,
            referenceType: 'Opening Stock'
        );

        $this->assertEquals(200, StockService::getAvailableStock($raw->id, $mainWh->id));

        // Create Stock Transfer to Subcontractor Warehouse
        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenantId,
            'transfer_number' => 'TRF-SUB-001',
            'from_warehouse_id' => $mainWh->id,
            'to_warehouse_id' => $subWh->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $transfer->items()->create([
            'product_id' => $raw->id,
            'quantity' => 100,
            'received_quantity' => 0,
        ]);

        // Dispatch transfer
        $this->withHeader('X-Tenant', 'test-tenant')->post(route('inventory.transfers.dispatch', $transfer->id));
        $this->assertEquals('In-Transit', $transfer->fresh()->status);
        $this->assertEquals(100, StockService::getAvailableStock($raw->id, $mainWh->id));

        // Receive transfer at Vendor Subcontractor Warehouse
        $this->withHeader('X-Tenant', 'test-tenant')->post(route('inventory.transfers.receive', $transfer->id));
        $this->assertEquals('Completed', $transfer->fresh()->status);
        $this->assertEquals(100, StockService::getAvailableStock($raw->id, $subWh->id));
    }

    public function test_grn_event_dispatch_only_after_db_commit(): void
    {
        $vendor = $this->createVendor();
        [$raw, $service, $fg] = $this->createProducts();

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-TEST-001',
            'vendor_id' => $vendor->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 500,
            'grand_total' => 500,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'quantity' => 100,
            'rate' => 5.00,
            'amount' => 500,
            'total_amount' => 500,
        ]);

        $eventDispatched = false;
        \Illuminate\Support\Facades\Event::listen(GoodsReceiptNoteApproved::class, function () use (&$eventDispatched) {
            $eventDispatched = true;
        });

        // Test rolled back transaction does NOT dispatch event
        try {
            DB::transaction(function () use ($poItem) {
                $grnService = app(GoodsReceiptNoteService::class);
                $grnService->storeGrn([
                    'purchase_order_id' => $poItem->purchase_order_id,
                    'received_date' => now()->toDateString(),
                    'items' => [
                        [
                            'purchase_order_item_id' => $poItem->id,
                            'received_qty' => 50,
                            'rejected_qty' => 0,
                        ],
                    ],
                ], $this->tenantId);

                throw new \Exception("Simulated exception to trigger rollback");
            });
        } catch (\Throwable $e) {
            // Expected rollback
        }

        $this->assertFalse($eventDispatched, "Rolled back GRN transaction must never dispatch GoodsReceiptNoteApproved event.");
    }

    public function test_qc_pending_blocks_successor_until_qc_pass(): void
    {
        $vendor = $this->createVendor();
        $subWh = $this->createSubcontractorWarehouse($vendor);
        $mainWh = $this->createMainWarehouse();
        [$raw, $service, $fg] = $this->createProducts();

        $routing = $this->createHybridRouting($vendor, $service);
        $wc1 = WorkCenter::where('tenant_id', $this->tenantId)->first();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-HYB-003',
            'product_id' => $fg->id,
            'bom_id' => null,
            'quantity_ordered' => 20,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'CNC Milling',
            'work_center_id' => $wc1->id,
            'is_external' => false,
            'status' => 'completed',
            'quantity_produced' => 20,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => true,
            'material_supply_type' => 'company_supplied',
            'status' => 'ready',
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Final Assembly & Packaging',
            'work_center_id' => $wc1->id,
            'is_external' => false,
            'status' => 'waiting',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-HYB-OP20',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 20,
            'rate' => 5.00,
            'amount' => 100,
            'total_amount' => 100,
        ]);

        // Post GRN for OP20
        $grnService = app(GoodsReceiptNoteService::class);
        $grn = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $subWh->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 20,
                    'rejected_qty' => 0,
                ],
            ],
        ], $this->tenantId);

        // OP20 must be in QC Pending state and OP30 must remain waiting
        $this->assertEquals('subcontract_qc_pending', $op20->fresh()->status);
        $this->assertEquals('waiting', $op30->fresh()->status);

        // Approve QC
        $inspection = ProductionQualityInspection::where('production_order_operation_id', $op20->id)->firstOrFail();
        $inspection->update(['status' => 'submitted', 'result' => 'passed']);

        app(\App\Domains\Production\Services\QualityInspectionService::class)->approveInspection(
            $inspection->id,
            $this->user->id,
            'test-signature',
            $this->tenantId
        );

        // OP20 must be completed and OP30 unlocked to ready
        $this->assertEquals('completed', $op20->fresh()->status);
        $this->assertEquals('ready', $op30->fresh()->status);
    }

    public function test_hybrid_intermediate_grn_never_creates_finished_goods(): void
    {
        $vendor = $this->createVendor();
        $subWh = $this->createSubcontractorWarehouse($vendor);
        [$raw, $service, $fg] = $this->createProducts();
        $routing = $this->createHybridRouting($vendor, $service);
        $wc1 = WorkCenter::where('tenant_id', $this->tenantId)->first();

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-HYB-004',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => false,
            'status' => 'ready',
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Final Assembly',
            'work_center_id' => $wc1->id,
            'is_external' => false,
            'status' => 'waiting',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-HYB-OP20-B',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 50,
            'grand_total' => 50,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 10,
            'rate' => 5.00,
            'amount' => 50,
            'total_amount' => 50,
        ]);

        $grnService = app(GoodsReceiptNoteService::class);
        $grn = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $subWh->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 10,
                    'rejected_qty' => 0,
                ],
            ],
        ], $this->tenantId);

        // Order produced quantity must remain 0 (Finished goods are not created by OP20)
        $this->assertEquals(0, (float) $order->fresh()->quantity_produced);
        $this->assertEquals('completed', $op20->fresh()->status);
        $this->assertEquals('ready', $op30->fresh()->status);
    }

    public function test_vendor_material_balance_conservation(): void
    {
        $vendor = $this->createVendor();
        $subWh = $this->createSubcontractorWarehouse($vendor);
        $mainWh = $this->createMainWarehouse();
        [$raw, $service, $fg] = $this->createProducts();
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Machining Center',
            'code' => 'WC-MACH-' . rand(100, 999),
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-ENG-001',
            'product_id' => $fg->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'base_quantity' => 1,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bom->id,
            'material_id' => $raw->id,
            'quantity' => 2.0,
            'uom_id' => 1,
            'material_scrap_percentage' => 0,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-HYB-005',
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 50,
            'production_model' => 'subcontract_company_material',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'External Anodizing',
            'work_center_id' => $wc1->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'material_supply_type' => 'company_supplied',
            'status' => 'ready',
        ]);

        // Dispatch 100 raw materials to vendor
        StockService::recordInflow($this->tenantId, $raw->id, $mainWh->id, 100, 15.00, 'Opening Stock');
        StockService::recordOutflow($this->tenantId, $raw->id, $mainWh->id, 100, 'StockTransfer', $order->id);

        $balanceService = app(SubcontractMaterialBalanceService::class);
        $balBefore = $balanceService->getMaterialBalance($this->tenantId, $order->id, $op20->id);
        $this->assertEquals(100, $balBefore['sent']);
        $this->assertEquals(100, $balBefore['remaining']);

        // Receive partial 30 finished units at GRN -> backflushes 30 * 2 = 60 raw materials
        $balanceService->backflushCompanyMaterial($this->tenantId, $op20, 30, $subWh->id);

        $balAfter = $balanceService->getMaterialBalance($this->tenantId, $order->id, $op20->id);
        $this->assertEquals(100, $balAfter['sent']);
        $this->assertEquals(60, $balAfter['consumed']);
        $this->assertEquals(40, $balAfter['remaining']);
        $this->assertEquals($balAfter['sent'], $balAfter['consumed'] + $balAfter['returned'] + $balAfter['scrapped'] + $balAfter['remaining']);
    }
}
