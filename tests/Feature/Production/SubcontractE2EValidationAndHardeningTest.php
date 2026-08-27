<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractE2EValidationAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Vendor $vendor1;
    protected Vendor $vendor2;
    protected Warehouse $warehouse;
    protected Product $finishedGood;
    protected Product $rawMaterial;
    protected Product $serviceProduct1;
    protected Product $serviceProduct2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'E2E Hardening Tenant',
            'slug' => 'e2e-hard-' . uniqid(),
            'domain' => 'e2e-hard.test',
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_approved_po',
                'subcontract_auto_approval_limit' => 5000.00,
            ],
        ]);

        app()->instance('tenant', $this->tenant);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);
        session(['tenant_id' => $this->tenant->id]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($this->user);

        $this->vendor1 = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heat Treatment Subcontractor Inc',
            'code' => 'VEND-HEAT-01',
            'status' => 'active',
        ]);

        $this->vendor2 = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Surface Coating Subcontractor Inc',
            'code' => 'VEND-COAT-02',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Manufacturing Warehouse',
            'code' => 'WH-CENTRAL',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision Aerospace Turbine Assembly',
            'sku' => 'FG-TURBINE-100',
            'item_type' => 'Goods',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Titanium Alloy Ingot',
            'sku' => 'RM-TITANIUM-01',
            'item_type' => 'Goods',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        $this->serviceProduct1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vacuum Heat Treatment Service',
            'sku' => 'SRV-HEAT-TREAT',
            'item_type' => 'Service',
            'type' => 'service',
            'status' => 'active',
        ]);

        $this->serviceProduct2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Anodizing & Coating Service',
            'sku' => 'SRV-ANODIZE',
            'item_type' => 'Service',
            'type' => 'service',
            'status' => 'active',
        ]);
    }

    public function test_complete_multi_op_routing_e2e_lifecycle()
    {
        // 5-Op Routing: OP10 (Int) -> OP20 (Ext - Heat) -> OP30 (Int) -> OP40 (Ext - Coat) -> OP50 (Int)
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-E2E-TURBINE-001',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Precision Cutting & Stamping',
            'is_external' => false,
            'target_produced_qty' => 20.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Vacuum Heat Treatment',
            'is_external' => true,
            'vendor_id' => $this->vendor1->id,
            'subcontract_cost_per_unit' => 30.00,
            'subcontract_lead_time_days' => 3,
            'subcontract_service_product_id' => $this->serviceProduct1->id,
            'material_supply_type' => 'company_supplied',
            'target_produced_qty' => 20.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'CNC Precision Machining',
            'is_external' => false,
            'target_produced_qty' => 20.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op40 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 40,
            'operation_number' => 'OP-40',
            'name' => 'Surface Anodizing Coating',
            'is_external' => true,
            'vendor_id' => $this->vendor2->id,
            'subcontract_cost_per_unit' => 15.00,
            'subcontract_lead_time_days' => 2,
            'subcontract_service_product_id' => $this->serviceProduct2->id,
            'material_supply_type' => 'vendor_supplied',
            'target_produced_qty' => 20.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op50 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 50,
            'operation_number' => 'OP-50',
            'name' => 'Final Assembly & Packaging',
            'is_external' => false,
            'target_produced_qty' => 20.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        // Release Production Order
        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        // 1. Verify two distinct Approved POs were generated automatically for OP20 and OP40
        $poCount = PurchaseOrder::where('tenant_id', $this->tenant->id)->where('production_order_id', $order->id)->count();
        $this->assertEquals(2, $poCount);

        $poOp20 = PurchaseOrderItem::where('production_order_operation_id', $op20->id)->first()->order;
        $poOp40 = PurchaseOrderItem::where('production_order_operation_id', $op40->id)->first()->order;

        $this->assertNotNull($poOp20);
        $this->assertNotNull($poOp40);
        $this->assertNotEquals($poOp20->id, $poOp40->id);
        $this->assertEquals('Approved', $poOp20->status);
        $this->assertEquals('Approved', $poOp40->status);
        $this->assertEquals($this->vendor1->id, $poOp20->vendor_id);
        $this->assertEquals($this->vendor2->id, $poOp40->vendor_id);

        // 2. Complete OP10 internally (Stamping 20 units)
        $op10->quantity_produced = 20.0;
        $op10->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op10->save();

        // Advance WIP from OP10 to OP20
        app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($op10->id);

        $wip = ProductionWip::where('tenant_id', $this->tenant->id)->where('production_order_id', $order->id)->first();
        $this->assertNotNull($wip);
        $this->assertEquals(20.0, $wip->available_quantity);

        // 3. Receive GRN for OP20 (Heat Treatment Subcontracting)
        $grnService = app(GoodsReceiptNoteService::class);
        $poi20 = PurchaseOrderItem::where('production_order_operation_id', $op20->id)->first();

        $grn1 = $grnService->storeGrn([
            'purchase_order_id' => $poOp20->id,
            'vendor_id' => $this->vendor1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poi20->id,
                    'received_qty' => 20.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        // Subcontract GRN advances OP20 & transfers WIP to OP30
        app(SubcontractReceiptOrchestrator::class)->processSubcontractReceipt($grn1);

        $op20->refresh();
        $this->assertEquals(20.0, $op20->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op20->status);

        // 4. Complete OP30 internally (CNC Machining)
        $op30->quantity_produced = 20.0;
        $op30->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op30->save();

        app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($op30->id);

        // 5. Receive GRN for OP40 (Vendor-Supplied Anodizing Coating)
        $poi40 = PurchaseOrderItem::where('production_order_operation_id', $op40->id)->first();

        $grn2 = $grnService->storeGrn([
            'purchase_order_id' => $poOp40->id,
            'vendor_id' => $this->vendor2->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poi40->id,
                    'received_qty' => 20.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        app(SubcontractReceiptOrchestrator::class)->processSubcontractReceipt($grn2);

        $op40->refresh();
        $this->assertEquals(20.0, $op40->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op40->status);

        // 6. Complete OP50 internally (Final Assembly)
        $op50->quantity_produced = 20.0;
        $op50->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op50->save();

        // Complete overall order
        app(ProductionOrderService::class)->complete($order->id, $this->user->id);

        $order->refresh();
        $this->assertEquals(ProductionOrder::STATUS_COMPLETED, $order->status);
    }

    public function test_over_receipt_protection_blocks_exceeding_ordered_quantity()
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-OVER-RECEIPT-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor1->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct1->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)->where('production_order_id', $order->id)->first();
        $poi = PurchaseOrderItem::where('purchase_order_id', $po->id)->first();

        $grnService = app(GoodsReceiptNoteService::class);

        // Receive 8 units first
        $grn1 = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendor1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poi->id,
                    'received_qty' => 8.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $poi->refresh();
        $this->assertEquals(8.0, $poi->received_qty);

        // Attempt receiving 5 more (total 13 > 10 ordered) -> Expect InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total received would exceed ordered quantity');

        $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendor1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poi->id,
                    'received_qty' => 5.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);
    }

    public function test_qc_partial_acceptance_and_rejection_advances_only_passed_quantity()
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-QC-PARTIAL-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Precision Brazing with Quality Control',
            'is_external' => true,
            'vendor_id' => $this->vendor1->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct1->id,
            'target_produced_qty' => 20.0,
            'quality_required' => true, // QC Required
            'material_supply_type' => 'company_supplied',
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)->where('production_order_id', $order->id)->first();
        $poi = PurchaseOrderItem::where('purchase_order_id', $po->id)->first();

        $grnService = app(GoodsReceiptNoteService::class);
        $grn = $grnService->storeGrn([
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendor1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poi->id,
                    'received_qty' => 20.0,
                    'rejected_qty' => 0.0,
                ]
            ]
        ], $this->tenant->id);

        $receiptOrchestrator = app(SubcontractReceiptOrchestrator::class);
        $receiptOrchestrator->processSubcontractReceipt($grn);

        $op20->refresh();
        $this->assertEquals('subcontract_qc_pending', $op20->status);

        $qcInspection = ProductionQualityInspection::where('tenant_id', $this->tenant->id)
            ->where('production_order_operation_id', $op20->id)
            ->first();

        $this->assertNotNull($qcInspection);

        // Approve QC with 16 Passed, 4 Rejected
        $qcInspection->result = 'passed';
        $qcInspection->passed_qty = 16.0;
        $qcInspection->failed_qty = 4.0;
        $qcInspection->inspected_quantity = 20.0;
        $qcInspection->save();

        $receiptOrchestrator->processQcApproval($qcInspection);

        $op20->refresh();
        $this->assertEquals(16.0, $op20->quantity_produced); // Only 16 passed quantity advances
    }

    public function test_multi_tenant_isolation_prevents_cross_tenant_access()
    {
        // Create Tenant B
        $tenantB = Tenant::create([
            'name' => 'Cross Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
            'domain' => 'tenant-b.test',
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_approved_po',
                'subcontract_auto_approval_limit' => 5000.00,
            ],
        ]);

        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
        ]);

        $vendorB = Vendor::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Subcontractor Tenant B',
            'code' => 'VEND-B',
            'status' => 'active',
        ]);

        $finishedGoodB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Turbine Tenant B',
            'sku' => 'FG-TURB-B',
            'item_type' => 'Goods',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $orderB = ProductionOrder::create([
            'tenant_id' => $tenantB->id,
            'order_number' => 'MO-TENANT-B-01',
            'product_id' => $finishedGoodB->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $serviceProductB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Subcontract Service Tenant B',
            'sku' => 'SRV-TENANT-B',
            'item_type' => 'Service',
            'type' => 'service',
            'status' => 'active',
        ]);

        $opB = ProductionOrderOperation::create([
            'tenant_id' => $tenantB->id,
            'production_order_id' => $orderB->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Tenant B Operation',
            'is_external' => true,
            'vendor_id' => $vendorB->id,
            'subcontract_cost_per_unit' => 50.00,
            'subcontract_service_product_id' => $serviceProductB->id,
            'target_produced_qty' => 10.0,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        app(\App\Core\Tenant\TenantContext::class)->setTenant($tenantB);

        // Attempting to orchestrate procurement for Tenant B operation under Tenant B context
        $orchestrator = app(SubcontractProcurementOrchestrator::class);
        
        $poB = $orchestrator->orchestrateSubcontractProcurement($opB, $tenantB->id, $userB->id);

        $this->assertNotNull($poB);
        $this->assertEquals($tenantB->id, $poB->tenant_id);

        // Tenant A must not see Tenant B POs
        $tenantAPoCount = PurchaseOrder::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $orderB->id)
            ->count();

        $this->assertEquals(0, $tenantAPoCount);
    }
}
