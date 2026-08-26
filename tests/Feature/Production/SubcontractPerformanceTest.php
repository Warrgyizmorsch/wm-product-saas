<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Services\SubcontractPerformanceService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $user;
    protected Product $product;
    protected Vendor $vendorA;
    protected Vendor $vendorB;
    protected Warehouse $warehouse;
    protected SubcontractPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'slug' => 'tenant-a',
            'status' => 'active',
            'name' => 'Tenant A',
        ]);

        $this->otherTenant = Tenant::create([
            'slug' => 'tenant-b',
            'status' => 'active',
            'name' => 'Tenant B',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@tenanta.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engine Radiator Core',
            'sku' => 'RAD-CORE-001',
            'unit_of_measure' => 'PCS',
            'unit_cost' => 120.00,
        ]);

        $this->vendorA = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Subcontracting Services',
            'vendor_code' => 'VND-001',
            'status' => 'active',
        ]);

        $this->vendorB = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Precision Machining',
            'vendor_code' => 'VND-002',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Store',
            'code' => 'WH-MAIN',
        ]);

        $this->service = app(SubcontractPerformanceService::class);
    }

    public function test_on_time_and_delayed_delivery_sla_metrics(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000100',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'created_by' => $this->user->id,
        ]);

        // Operation 1: On-time delivery
        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Heat Treatment',
            'is_external' => true,
            'vendor_id' => $this->vendorA->id,
            'subcontract_lead_time_days' => 3,
            'subcontract_cost_per_unit' => 15.00,
            'status' => 'completed',
            'actual_start_time' => now()->subDays(5),
            'actual_end_time' => now()->subDays(2),
        ]);

        DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-2026-000001',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op1->id,
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d', strtotime('-5 days')),
            'expected_return_date' => date('Y-m-d', strtotime('-2 days')),
            'status' => 'completed',
        ]);

        // Operation 2: Delayed delivery (Expected return 3 days ago, completed today)
        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Surface Anodizing',
            'is_external' => true,
            'vendor_id' => $this->vendorA->id,
            'subcontract_lead_time_days' => 2,
            'subcontract_cost_per_unit' => 20.00,
            'status' => 'completed',
            'actual_start_time' => now()->subDays(7),
            'actual_end_time' => now(),
        ]);

        DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-2026-000002',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op2->id,
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d', strtotime('-7 days')),
            'expected_return_date' => date('Y-m-d', strtotime('-3 days')),
            'status' => 'completed',
        ]);

        $delivery = $this->service->getDeliveryMetrics($this->tenant->id);

        $this->assertEquals(2, $delivery['total_completed_ops']);
        $this->assertEquals(1, $delivery['on_time_ops_count']);
        $this->assertEquals(50.0, $delivery['on_time_delivery_pct']);
        $this->assertGreaterThan(0, $delivery['avg_late_delay_days']);
    }

    public function test_quality_acceptance_rejection_and_rework_rates(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000200',
            'product_id' => $this->product->id,
            'quantity_ordered' => 200,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'created_by' => $this->user->id,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'CNC Milling',
            'is_external' => true,
            'vendor_id' => $this->vendorB->id,
            'status' => 'completed',
            'quantity_produced' => 180,
            'quantity_rejected' => 20,
            'quantity_scrapped' => 5,
        ]);

        ProductionOrderRework::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'quantity' => 15,
            'status' => 'pending',
            'recorded_at' => now(),
        ]);

        $quality = $this->service->getQualityMetrics($this->tenant->id);

        $this->assertEquals(200.0, $quality['received_qty']);
        $this->assertEquals(180.0, $quality['accepted_qty']);
        $this->assertEquals(20.0, $quality['rejected_qty']);
        $this->assertEquals(15.0, $quality['rework_qty']);
        $this->assertEquals(5.0, $quality['scrap_qty']);
        $this->assertEquals(90.0, $quality['acceptance_rate']);
        $this->assertEquals(10.0, $quality['rejection_rate']);
        $this->assertEquals(7.5, $quality['rework_rate']);
        $this->assertEquals(2.5, $quality['scrap_rate']);
    }

    public function test_cost_and_po_rate_variance_calculation(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000300',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => $this->user->id,
        ]);

        // Planned rate: ₹100/unit (Total planned = ₹1,000)
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Laser Cutting',
            'is_external' => true,
            'vendor_id' => $this->vendorA->id,
            'subcontract_cost_per_unit' => 100.00,
            'target_produced_qty' => 10,
            'status' => 'waiting',
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-2026-000999',
            'vendor_id' => $this->vendorA->id,
            'date' => date('Y-m-d'),
            'status' => 'Approved',
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'subtotal' => 1200.00,
            'grand_total' => 1200.00,
        ]);

        // Actual PO rate: ₹120/unit (Total committed = ₹1,200, rate variance = +20%)
        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'production_order_operation_id' => $op->id,
            'quantity' => 10,
            'rate' => 120.00,
            'amount' => 1200.00,
            'total_amount' => 1200.00,
        ]);

        $cost = $this->service->getCostMetrics($this->tenant->id);

        $this->assertEquals(1000.00, $cost['planned_cost']);
        $this->assertEquals(1200.00, $cost['committed_po_cost']);
        $this->assertEquals(200.00, $cost['cost_variance_amount']);
        $this->assertEquals(20.0, $cost['cost_variance_pct']);
        $this->assertEquals(20.0, $cost['po_rate_variance_pct']);
    }

    public function test_delayed_operations_report_detects_blocking_successor(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000400',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'created_by' => $this->user->id,
        ]);

        // Overdue subcontract operation
        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Subcontract Plating',
            'is_external' => true,
            'vendor_id' => $this->vendorA->id,
            'status' => 'vendor_dispatched',
            'actual_start_time' => now()->subDays(10),
        ]);

        DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-2026-000400',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op1->id,
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d', strtotime('-10 days')),
            'expected_return_date' => date('Y-m-d', strtotime('-4 days')),
            'status' => 'dispatched',
        ]);

        // Successor operation waiting
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Final Assembly',
            'is_external' => false,
            'status' => 'waiting',
        ]);

        $delayedReport = $this->service->getDelayedOperationsReport($this->tenant->id);

        $this->assertCount(1, $delayedReport);
        $this->assertEquals('MO-2026-000400', $delayedReport[0]['order_number']);
        $this->assertGreaterThanOrEqual(4, $delayedReport[0]['days_overdue']);
        $this->assertStringContainsString('Op #20', $delayedReport[0]['blocking_successor']);
    }

    public function test_tenant_isolation_prevents_data_leakage(): void
    {
        // Other tenant's vendor & operation
        $otherVendor = Vendor::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Secret Vendor B',
            'status' => 'active',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id' => $this->otherTenant->id,
            'order_number' => 'MO-SECRET-99',
            'product_id' => $this->product->id,
            'quantity_ordered' => 500,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => 1,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->otherTenant->id,
            'production_order_id' => $otherOrder->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Secret Processing',
            'is_external' => true,
            'vendor_id' => $otherVendor->id,
            'status' => 'completed',
            'quantity_produced' => 500,
        ]);

        $metricsTenantA = $this->service->getOverallMetrics($this->tenant->id);
        $metricsTenantB = $this->service->getOverallMetrics($this->otherTenant->id);

        $this->assertEquals(0, $metricsTenantA['total_completed_ops']);
        $this->assertEquals(1, $metricsTenantB['total_completed_ops']);
    }

    public function test_subcontract_analytics_web_route_renders_successfully(): void
    {
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.subcontract.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Subcontract Vendor Analytics');
        $response->assertSee('On-Time Delivery Rate');
    }
}
