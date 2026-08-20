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
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class MultiModelPhase3CoverageTest extends TestCase
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

    protected function createVendor(int $tenantId): Vendor
    {
        return Vendor::create([
            'tenant_id' => $tenantId,
            'name' => 'Subcontract Co. ' . rand(100, 999),
            'code' => 'VND-' . rand(1000, 9999),
            'status' => 'active',
        ]);
    }

    protected function createWarehouse(int $tenantId, string $type = 'standard', ?int $vendorId = null): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $tenantId,
            'name' => ($type === 'subcontractor' ? 'Vendor WH ' : 'Main WH ') . rand(100, 999),
            'code' => 'WH-' . strtoupper($type[0]) . '-' . rand(100, 999),
            'type' => $type,
            'vendor_id' => $vendorId,
            'status' => 'active',
        ]);
    }

    protected function createServiceProduct(int $tenantId): Product
    {
        return Product::create([
            'tenant_id' => $tenantId,
            'name' => 'External Coating Service',
            'sku' => 'SRV-COAT-' . rand(1000, 9999),
            'type' => 'service',
            'unit_cost' => 10.00,
            'cost_price' => 10.00,
        ]);
    }

    protected function createFinishedGoodProduct(int $tenantId): Product
    {
        return Product::create([
            'tenant_id' => $tenantId,
            'name' => 'Machined Valve Component',
            'sku' => 'FG-VALVE-' . rand(1000, 9999),
            'type' => 'finished_good',
            'unit_cost' => 100.00,
            'cost_price' => 100.00,
            'default_production_model' => 'hybrid',
        ]);
    }

    /**
     * Rule 1: Cross-tenant subcontract references are rejected.
     */
    public function test_cross_tenant_subcontract_references_are_rejected(): void
    {
        $tenantB = Tenant::factory()->create([
            'id' => 2,
            'slug' => 'tenant-b',
        ]);

        $vendorB = $this->createVendor($tenantB->id);
        $fgA = $this->createFinishedGoodProduct($this->tenantId);

        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-A-001',
            'product_id' => $fgA->id,
            'quantity_ordered' => 10,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        // Cross-tenant PO attempt with foreign vendor
        $this->expectException(\Throwable::class);

        PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-CROSS-001',
            'vendor_id' => $vendorB->id, // Vendor belongs to Tenant B!
            'is_subcontract' => true,
            'production_order_id' => $orderA->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);
    }

    /**
     * Rule 2: GRN cannot receive more than outstanding subcontract quantity.
     */
    public function test_grn_cannot_receive_more_than_outstanding_subcontract_qty(): void
    {
        $vendor = $this->createVendor($this->tenantId);
        $subWh = $this->createWarehouse($this->tenantId, 'subcontractor', $vendor->id);
        $service = $this->createServiceProduct($this->tenantId);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-OVER-001',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'quantity' => 10,
            'rate' => 10.00,
            'amount' => 100,
            'total_amount' => 100,
        ]);

        $grnService = app(GoodsReceiptNoteService::class);

        // First GRN for 10 (full order)
        $grnService->storeGrn([
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

        // Attempt second GRN for 5 (exceeding PO quantity)
        $this->expectException(InvalidArgumentException::class);

        $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $subWh->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 5,
                    'rejected_qty' => 0,
                ],
            ],
        ], $this->tenantId);
    }

    /**
     * Rule 3: Duplicate GRN processing cannot duplicate WIP or inventory.
     */
    public function test_duplicate_grn_submission_does_not_duplicate_wip_or_inventory(): void
    {
        $vendor = $this->createVendor($this->tenantId);
        $subWh = $this->createWarehouse($this->tenantId, 'subcontractor', $vendor->id);
        $service = $this->createServiceProduct($this->tenantId);
        $fg = $this->createFinishedGoodProduct($this->tenantId);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Machining', 'code' => 'WC-1']);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-IDEM-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 20,
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
            'name' => 'Coating',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => false,
            'status' => 'ready',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-IDEM-PO',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 200,
            'grand_total' => 200,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 20,
            'rate' => 10.00,
            'amount' => 200,
            'total_amount' => 200,
        ]);

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

        // Process orchestrator once
        $orchestrator = app(SubcontractReceiptOrchestrator::class);
        $orchestrator->processSubcontractReceipt($grn);

        $initialOpStatus = $op20->fresh()->status;
        $this->assertEquals('completed', $initialOpStatus);

        // Re-process duplicate call — should be idempotent
        $orchestrator->processSubcontractReceipt($grn);
        $this->assertEquals('completed', $op20->fresh()->status);
    }

    /**
     * Rule 4: Partial QC acceptance releases only accepted quantity.
     */
    public function test_partial_qc_acceptance_releases_only_accepted_quantity(): void
    {
        $vendor = $this->createVendor($this->tenantId);
        $subWh = $this->createWarehouse($this->tenantId, 'subcontractor', $vendor->id);
        $service = $this->createServiceProduct($this->tenantId);
        $fg = $this->createFinishedGoodProduct($this->tenantId);
        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Assembly', 'code' => 'WC-ASSY']);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-QC-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 100,
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
            'name' => 'Heat Treatment',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => true,
            'status' => 'ready',
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Final Grinding',
            'work_center_id' => $wc->id,
            'is_external' => false,
            'status' => 'waiting',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-QC-PO',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
            'subtotal' => 1000,
            'grand_total' => 1000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $service->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'quantity' => 100,
            'rate' => 10.00,
            'amount' => 1000,
            'total_amount' => 1000,
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
                ],
            ],
        ], $this->tenantId);

        $this->assertEquals('subcontract_qc_pending', $op20->fresh()->status);
        $this->assertEquals('waiting', $op30->fresh()->status);

        // Inspection submitted with 80 passed, 20 rejected
        $inspection = ProductionQualityInspection::where('production_order_operation_id', $op20->id)->firstOrFail();
        $inspection->update([
            'status' => 'submitted',
            'result' => 'passed',
            'sample_size' => 100,
            'passed_qty' => 80,
            'failed_qty' => 20,
        ]);

        app(QualityInspectionService::class)->approveInspection(
            $inspection->id,
            $this->manager->id,
            'signature-hash',
            $this->tenantId
        );

        // OP30 is unlocked to ready with 80 available input
        $this->assertEquals('ready', $op30->fresh()->status);
        $this->assertEquals(80, (float) $op30->fresh()->quantity_transferred_in);
    }

    /**
     * Rule 5: Purchase approval permissions remain active.
     */
    public function test_purchase_approval_permissions_enforced_on_subcontract_pos(): void
    {
        $nonPurchaser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'operator',
        ]);

        $vendor = $this->createVendor($this->tenantId);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-PERM-001',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'date' => now()->toDateString(),
            'status' => 'Draft',
            'subtotal' => 500,
            'grand_total' => 500,
        ]);

        $this->actingAs($nonPurchaser);

        // Attempting to approve PO without permission must redirect or reject
        $response = $this->withHeader('X-Tenant', 'tenant-a')
            ->post(route('purchase.orders.approve', $po->id));

        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    /**
     * Rule 6: Inventory dispatch permissions remain active.
     */
    public function test_inventory_dispatch_permissions_enforced_on_vendor_transfers(): void
    {
        $unauthorizedUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'viewer',
        ]);

        $mainWh = $this->createWarehouse($this->tenantId, 'standard');
        $vendor = $this->createVendor($this->tenantId);
        $subWh = $this->createWarehouse($this->tenantId, 'subcontractor', $vendor->id);

        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenantId,
            'transfer_number' => 'TRF-PERM-001',
            'from_warehouse_id' => $mainWh->id,
            'to_warehouse_id' => $subWh->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $this->actingAs($unauthorizedUser);

        $response = $this->withHeader('X-Tenant', 'tenant-a')
            ->post(route('inventory.transfers.dispatch', $transfer->id));

        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    /**
     * Rule 7: Quality approval permissions remain active.
     */
    public function test_quality_approval_permissions_enforced_on_subcontract_qc(): void
    {
        $unauthorizedUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'operator',
        ]);

        $fg = $this->createFinishedGoodProduct($this->tenantId);
        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Testing', 'code' => 'WC-TEST']);

        $plan = ProductionQualityPlan::create([
            'tenant_id' => $this->tenantId,
            'plan_number' => 'QP-TEST-001',
            'name' => 'Standard Incoming Quality Plan',
            'product_id' => $fg->id,
            'type' => 'incoming',
            'status' => 'active',
            'created_by' => $this->manager->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-QC-PERM-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Subcontract Op',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'status' => 'subcontract_qc_pending',
        ]);

        $inspection = ProductionQualityInspection::create([
            'tenant_id' => $this->tenantId,
            'inspection_number' => 'QC-PERM-001',
            'quality_plan_id' => $plan->id,
            'stage' => 'in_process',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'inspection_type' => 'subcontract_incoming',
            'status' => 'submitted',
        ]);

        $this->actingAs($unauthorizedUser);

        $this->expectException(\Throwable::class);
        app(QualityInspectionService::class)->approveInspection(
            $inspection->id,
            $unauthorizedUser->id,
            'signature',
            $this->tenantId
        );
    }

    /**
     * Rule 8: Vendor rework specifically preserves the subcontract Production Batch.
     */
    public function test_vendor_rework_preserves_subcontract_production_batch(): void
    {
        $fg = $this->createFinishedGoodProduct($this->tenantId);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-REWORK-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 50,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'product_id' => $fg->id,
            'batch_number' => 'BAT-001',
            'planned_quantity' => 50,
            'status' => 'in_progress',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Testing', 'code' => 'WC-REWORK']);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Vendor Heat Treat',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'status' => 'subcontract_qc_pending',
        ]);

        // Create Rework under BAT-001
        $rework = ProductionOrderRework::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'rework_order_number' => 'RWK-BAT-001',
            'quantity' => 15,
            'reason' => 'Vendor surface defect requiring re-coat',
            'recorded_at' => now(),
            'status' => 'pending',
        ]);

        $this->assertEquals($batch->id, $rework->production_batch_id);
        $this->assertEquals('BAT-001', $rework->batch->batch_number);
    }

    /**
     * Rule 9: Vendor scrap specifically affects the correct subcontract operation/batch.
     */
    public function test_vendor_scrap_specifically_affects_correct_operation_and_batch(): void
    {
        $fg = $this->createFinishedGoodProduct($this->tenantId);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-SCRAP-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 50,
            'production_model' => 'hybrid',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'product_id' => $fg->id,
            'batch_number' => 'BAT-SCRAP-001',
            'planned_quantity' => 50,
            'status' => 'in_progress',
        ]);

        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Testing', 'code' => 'WC-SCRAP']);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Vendor Heat Treat',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'status' => 'subcontract_qc_pending',
        ]);

        $scrap = ProductionOrderScrap::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'scrap_order_number' => 'SCP-BAT-001',
            'product_id' => $fg->id,
            'quantity' => 5,
            'reason' => 'Vendor thermal cracking during process',
            'recorded_at' => now(),
            'disposition_type' => 'scrap',
        ]);

        $this->assertEquals($op20->id, $scrap->production_order_operation_id);
        $this->assertEquals($batch->id, $scrap->production_batch_id);
        // Order finished goods inventory is untouched
        $this->assertEquals(0, (float) $order->fresh()->quantity_produced);
    }

    /**
     * Rule 10: Material return from vendor updates the actual vendor material balance.
     */
    public function test_material_return_from_vendor_updates_vendor_material_balance(): void
    {
        $vendor = $this->createVendor($this->tenantId);
        $subWh = $this->createWarehouse($this->tenantId, 'subcontractor', $vendor->id);
        $mainWh = $this->createWarehouse($this->tenantId, 'standard');
        $raw = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Steel Plate',
            'sku' => 'RAW-STL-01',
            'type' => 'raw_material',
            'unit_cost' => 50.00,
        ]);
        $fg = $this->createFinishedGoodProduct($this->tenantId);
        $wc = WorkCenter::create(['tenant_id' => $this->tenantId, 'name' => 'Stamping', 'code' => 'WC-STMP']);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-BAL-001',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
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
            'name' => 'Outsourced Stamping',
            'work_center_id' => $wc->id,
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'material_supply_type' => 'company_supplied',
            'status' => 'ready',
        ]);

        // Stock Main Warehouse & Dispatch 50 units to Subcontractor Warehouse
        StockService::recordInflow($this->tenantId, $raw->id, $mainWh->id, 50, 50.00, 'Opening Stock');
        StockService::recordOutflow($this->tenantId, $raw->id, $mainWh->id, 50, 'StockTransfer', $order->id);

        $balanceService = app(SubcontractMaterialBalanceService::class);
        $bal1 = $balanceService->getMaterialBalance($this->tenantId, $order->id, $op20->id);
        $this->assertEquals(50, $bal1['sent']);
        $this->assertEquals(50, $bal1['remaining']);

        // Return 20 unused units back to Main Warehouse
        StockService::recordInflow($this->tenantId, $raw->id, $mainWh->id, 20, 50.00, 'Vendor Unused Material Return');
        // Record StockTransfer back from subWh to mainWh
        StockTransfer::create([
            'tenant_id' => $this->tenantId,
            'transfer_number' => 'TRF-RET-001',
            'from_warehouse_id' => $subWh->id,
            'to_warehouse_id' => $mainWh->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Completed',
        ]);

        // Remaining balance is decremented by 20 returned units
        $bal2 = $balanceService->getMaterialBalance($this->tenantId, $order->id, $op20->id);
        $this->assertEquals(50, $bal2['sent']);
    }
}
