<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractProcurementAutomationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Vendor $vendor;
    protected Warehouse $warehouse;
    protected Product $finishedGood;
    protected Product $rawMaterial;
    protected Product $serviceProduct;
    protected Routing $routing;
    protected RoutingOperation $rOp10;
    protected RoutingOperation $rOp20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Subcontract Automation Tenant',
            'slug' => 'subcon-auto-' . uniqid(),
            'domain' => 'subcon-auto.test',
            'settings' => [], // Unconfigured tenant defaults to manual_pr_po
        ]);

        app()->instance('tenant', $this->tenant);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);
        session(['tenant_id' => $this->tenant->id]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($this->user);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Continuous Brazing Subcontractor Inc',
            'code' => 'VEND-SUB-01',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Production Warehouse',
            'code' => 'WH-MAIN',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Duty Radiator Assembly',
            'sku' => 'FG-RAD-750',
            'item_type' => 'Goods',
            'type' => 'finished_good',
            'is_active' => true,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Aluminum Core Sheets',
            'sku' => 'RM-AL-CORE',
            'item_type' => 'Goods',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $this->serviceProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Brazing Processing Service',
            'sku' => 'SRV-BRAZING',
            'item_type' => 'Service',
            'type' => 'service',
            'is_active' => true,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->finishedGood->id,
            'name' => 'Radiator Subcontract Routing',
            'is_active' => true,
        ]);

        $this->rOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Core Stamping',
            'is_external' => false,
        ]);

        $this->rOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_lead_time_days' => 4,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'material_supply_type' => 'company_supplied',
        ]);
    }

    public function test_unconfigured_tenant_defaults_to_manual_pr_po_workflow()
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-SUB-TEST-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 20.0,
            'material_supply_type' => 'company_supplied',
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        // Under manual_pr_po default, a Subcontract PurchaseRequisition should be created
        $pr = PurchaseRequisition::where('tenant_id', $this->tenant->id)
            ->where('source_id', $order->id)
            ->first();

        $this->assertNotNull($pr);
        $this->assertStringContainsString('Op #20', $pr->notes);

        // No PO should be created automatically
        $poCount = PurchaseOrder::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(0, $poCount);
    }

    public function test_auto_draft_po_mode_skips_pr_and_creates_draft_po_on_release()
    {
        $this->tenant->update([
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_draft_po',
            ],
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-AUTO-DRAFT-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        // PR should be skipped
        $prCount = PurchaseRequisition::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(0, $prCount);

        // Draft PO should be created automatically
        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals('Draft', $po->status);
        $this->assertTrue($po->is_subcontract);
        $this->assertEquals($this->vendor->id, $po->vendor_id);
        $this->assertEquals(250.00, $po->grand_total); // 10 * $25

        $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($poItem);
        $this->assertEquals($op20->id, $poItem->production_order_operation_id);
        $this->assertEquals($this->serviceProduct->id, $poItem->product_id);
    }

    public function test_auto_approved_po_mode_creates_approved_po_within_threshold()
    {
        $this->tenant->update([
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_approved_po',
                'subcontract_auto_approval_limit' => 1000.00,
            ],
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-AUTO-APP-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals('Approved', $po->status);
    }

    public function test_auto_approved_po_falls_back_to_draft_when_total_exceeds_threshold()
    {
        $this->tenant->update([
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_approved_po',
                'subcontract_auto_approval_limit' => 200.00, // Limit $200
            ],
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-AUTO-EXCEED-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00, // Total $250 > $200 limit
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals('Draft', $po->status); // Fallback to Draft
        $this->assertStringContainsString('Auto-Approval Skipped', $po->notes);
    }

    public function test_idempotency_releasing_order_twice_does_not_create_duplicate_pos()
    {
        $this->tenant->update([
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_draft_po',
            ],
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-IDEMPOTENT-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => $this->vendor->id,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
        ]);

        // First release
        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);
        $poCount1 = PurchaseOrder::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $poCount1);

        // Repeat orchestration call
        app(SubcontractProcurementOrchestrator::class)->orchestrateSubcontractProcurement($op20, $this->tenant->id, $this->user->id);
        $poCount2 = PurchaseOrder::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $poCount2); // Idempotent: strictly 1 PO
    }

    public function test_missing_vendor_blocks_automation_safely()
    {
        $this->tenant->update([
            'settings' => [
                'subcontract_procurement_workflow' => 'auto_draft_po',
            ],
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-MISSING-VEND-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Continuous Brazing',
            'is_external' => true,
            'vendor_id' => null, // Missing vendor
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $this->serviceProduct->id,
            'target_produced_qty' => 10.0,
            'material_supply_type' => 'company_supplied',
        ]);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        // PO creation blocked safely
        $poCount = PurchaseOrder::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(0, $poCount);
    }
}
