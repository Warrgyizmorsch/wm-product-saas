<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionSupplyAwareMrpTest extends TestCase
{
    use RefreshDatabase;

    private \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Supply Aware MRP Tenant',
            'slug' => 'mrp-supply-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
    }

    private function createRawProduct(string $sku = 'RM-001', float $unitCost = 10.0): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Material ' . $sku,
            'sku' => $sku,
            'type' => 'raw_material',
            'planning_type' => 'purchase',
            'unit_cost' => $unitCost,
            'cost_price' => $unitCost,
            'status' => 'active',
        ]);
    }

    private function createWarehouse(): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN-' . uniqid(),
        ]);
    }

    private function setStock(Product $product, Warehouse $wh, float $onHand, float $reserved = 0.0): ProductWarehouseStock
    {
        return ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'quantity' => $onHand,
            'reserved_qty' => $reserved,
        ]);
    }

    private function createVendor(int $tenantId): \App\Domains\Inventory\Models\Vendor
    {
        return \App\Domains\Inventory\Models\Vendor::create([
            'tenant_id' => $tenantId,
            'name' => 'Supplier ' . uniqid(),
            'code' => 'VEND-' . uniqid(),
            'status' => 'active',
        ]);
    }

    /**
     * Scenario A: Demand = 100, Stock = 20, Open PO = 50 arriving BEFORE demand date.
     * Expected Net Shortage = 30 (not 80).
     */
    public function test_scenario_a_open_po_arriving_before_demand_date_reduces_shortage(): void
    {
        $product = $this->createRawProduct('RM-SCENARIO-A');
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);
        $this->setStock($product, $wh, 20.0, 0.0);

        // Open PO = 50 arriving on 2026-09-05 (before demand date 2026-09-10)
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-SCENARIO-A',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-05',
            'status' => 'Approved',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,

            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'received_qty' => 0.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 100.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Sales Demand A',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(100.0, $item['required_qty']);
        $this->assertEquals(20.0, $item['available_qty']);
        $this->assertEquals(50.0, $item['open_po_supply_qty']);
        $this->assertEquals(70.0, $item['total_usable_supply_qty']);
        $this->assertEquals(30.0, $item['gross_shortage_qty']); // Usable supply of 70 reduces shortage to 30!
    }

    /**
     * Scenario B: Demand = 100, Stock = 20, Open PO = 50 arriving AFTER demand date.
     * Expected Net Shortage = 80 (since late PO cannot satisfy earlier demand).
     */
    public function test_scenario_b_open_po_arriving_after_demand_date_is_excluded(): void
    {
        $product = $this->createRawProduct('RM-SCENARIO-B');
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);
        $this->setStock($product, $wh, 20.0, 0.0);

        // Open PO = 50 arriving on 2026-09-15 (AFTER demand date 2026-09-10)
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-SCENARIO-B',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-15',
            'status' => 'Approved',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,

            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'received_qty' => 0.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 100.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Sales Demand B',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(100.0, $item['required_qty']);
        $this->assertEquals(20.0, $item['available_qty']);
        $this->assertEquals(0.0, $item['open_po_supply_qty']); // Late PO ignored!
        $this->assertEquals(20.0, $item['total_usable_supply_qty']);
        $this->assertEquals(80.0, $item['gross_shortage_qty']);
    }

    /**
     * Scenario C: Demand = 100, Stock = 20, Open Production Order = 50 completing BEFORE demand date.
     * Expected Net Shortage = 30.
     */
    public function test_scenario_c_open_production_order_completing_before_demand_date_reduces_shortage(): void
    {
        $product = $this->createRawProduct('SFG-SCENARIO-C');
        $product->update(['type' => 'semi_finished', 'planning_type' => 'manufacture']);
        $wh = $this->createWarehouse();
        $this->setStock($product, $wh, 20.0, 0.0);

        // Open Production Order = 50 completing on 2026-09-08 (before demand date 2026-09-10)
        ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'WO-SCENARIO-C',
            'product_id' => $product->id,
            'quantity_ordered' => 50.0,
            'quantity_produced' => 0.0,
            'start_date' => '2026-09-02',
            'end_date' => '2026-09-08',
            'status' => 'in_progress',
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 100.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Demand C',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(100.0, $item['required_qty']);
        $this->assertEquals(20.0, $item['available_qty']);
        $this->assertEquals(50.0, $item['open_wo_supply_qty']);
        $this->assertEquals(70.0, $item['total_usable_supply_qty']);
        $this->assertEquals(30.0, $item['gross_shortage_qty']);
    }

    /**
     * Scenario D: Demand = 100, Stock = 20, Cancelled PO = 50.
     * Expected Net Shortage = 80 (Cancelled PO provides 0 supply).
     */
    public function test_scenario_d_cancelled_po_is_excluded_from_supply(): void
    {
        $product = $this->createRawProduct('RM-SCENARIO-D');
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);
        $this->setStock($product, $wh, 20.0, 0.0);

        // Cancelled PO = 50
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-SCENARIO-D',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-05',
            'status' => 'Cancelled',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,

            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'received_qty' => 0.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 100.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Demand D',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(0.0, $item['open_po_supply_qty']);
        $this->assertEquals(80.0, $item['gross_shortage_qty']);
    }

    /**
     * Scenario E: Demand = 100, Stock = 20, PO Ordered = 100, PO Received = 40 (Remaining = 60).
     * Expected Net Shortage = 20.
     */
    public function test_scenario_e_partial_po_receipt_uses_remaining_unreceived_balance(): void
    {
        $product = $this->createRawProduct('RM-SCENARIO-E');
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);
        $this->setStock($product, $wh, 20.0, 0.0);

        // PO Ordered = 100, Received = 40 (open balance = 60)
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-SCENARIO-E',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-05',
            'status' => 'Partially Received',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,

            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 100.0,
            'received_qty' => 40.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 100.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Demand E',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(60.0, $item['open_po_supply_qty']); // Only remaining 60 balance counted!
        $this->assertEquals(80.0, $item['total_usable_supply_qty']); // 20 stock + 60 PO = 80
        $this->assertEquals(20.0, $item['gross_shortage_qty']); // 100 - 80 = 20 net shortage!
    }

    /**
     * Scenario F: Multiple demands for same product across different dates.
     * Stock = 20
     * Demand 1 (01 Sep) = 50 -> Shortage 30 (consumes 20 stock)
     * PO Supply (05 Sep) = 40
     * Demand 2 (10 Sep) = 30 -> Shortage 0 (consumes 30 from PO supply)
     */
    public function test_scenario_f_multiple_dated_demands_consume_supply_chronologically(): void
    {
        $product = $this->createRawProduct('RM-SCENARIO-F');
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);
        $this->setStock($product, $wh, 20.0, 0.0);

        // PO Supply = 40 arriving on 2026-09-05
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-SCENARIO-F',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-05',
            'status' => 'Approved',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $this->tenant->id,

            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 40.0,
            'received_qty' => 0.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 50.0,
                'required_date' => '2026-09-01',
                'source_ref' => 'Demand 1 (01 Sep)',
            ],
            [
                'product_id' => $product->id,
                'quantity' => 30.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Demand 2 (10 Sep)',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(80.0, $item['required_qty']);
        $this->assertEquals(30.0, $item['gross_shortage_qty']); // Demand 1 (50 - 20 = 30) + Demand 2 (30 - 30 PO = 0) = 30 total shortage!
    }

    /**
     * Edge Case: Tenant Isolation Test.
     * Ensure Tenant A cannot consume Tenant B's open PO supply.
     */
    public function test_tenant_isolation_prevents_cross_tenant_supply_netting(): void
    {
        $product = $this->createRawProduct('RM-TENANT-A');
        $wh = $this->createWarehouse();
        $tenantB = \App\Models\Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $vendorTenantB = $this->createVendor($tenantB->id);
        $this->setStock($product, $wh, 0.0, 0.0);

        // Tenant B PO for same product ID pattern
        $poTenantB = PurchaseOrder::create([
            'tenant_id' => $tenantB->id,
            'vendor_id' => $vendorTenantB->id,
            'purchase_order_number' => 'PO-TENANT-B',
            'date' => '2026-09-01',
            'delivery_date' => '2026-09-05',
            'status' => 'Approved',
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $tenantB->id,

            'purchase_order_id' => $poTenantB->id,
            'product_id' => $product->id,
            'quantity' => 100.0,
            'received_qty' => 0.0,
            'rate' => 10.0,
            'amount' => 500.0,
        ]);

        $mrpService = app(MrpShortageService::class);

        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 50.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Tenant A Demand',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $this->assertNotEmpty($result['consolidated']);
        $item = $result['consolidated'][0];

        $this->assertEquals(0.0, $item['open_po_supply_qty']); // Tenant B's PO is strictly isolated!
        $this->assertEquals(50.0, $item['gross_shortage_qty']);
    }
}
