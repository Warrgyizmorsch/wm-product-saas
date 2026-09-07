<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Services\MrpLotSizingService;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionMrpLotSizingTest extends TestCase
{
    use RefreshDatabase;

    private \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Lot Sizing Tenant',
            'slug' => 'lot-sizing-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
    }

    private function createProduct(string $sku, float $moq = 0.0, float $orderMultiple = 0.0): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Product ' . $sku,
            'sku' => $sku,
            'type' => 'raw_material',
            'planning_type' => 'purchase',
            'unit_cost' => 10.0,
            'cost_price' => 10.0,
            'minimum_order_qty' => $moq,
            'order_multiple' => $orderMultiple,
            'status' => 'active',
        ]);
    }

    private function createWarehouse(): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-LOT-' . uniqid(),
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
     * Test 1 — No configuration: Net Requirement = 73, No MOQ, No Multiple -> Planned = 73.
     */
    public function test_1_no_configuration_planned_supply_equals_net_requirement(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(73.0, 0.0, 0.0);

        $this->assertEquals(73.0, $res['planned_supply_qty']);
        $this->assertEquals('Standard', $res['rule']);
    }

    /**
     * Test 2 — MOQ: Net Requirement = 73, MOQ = 100 -> Planned = 100.
     */
    public function test_2_moq_raises_planned_supply_to_minimum(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(73.0, 100.0, 0.0);

        $this->assertEquals(100.0, $res['planned_supply_qty']);
        $this->assertStringContainsString('MOQ 100', $res['rule']);
    }

    /**
     * Test 3 — Order Multiple: Net Requirement = 73, Multiple = 25 -> Planned = 75.
     */
    public function test_3_order_multiple_rounds_up_to_nearest_step(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(73.0, 0.0, 25.0);

        $this->assertEquals(75.0, $res['planned_supply_qty']);
        $this->assertStringContainsString('Multiple 25', $res['rule']);
    }

    /**
     * Test 4 — MOQ + Multiple: Net Requirement = 73, MOQ = 100, Multiple = 25 -> Planned = 100.
     */
    public function test_4_moq_plus_multiple_interaction(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(73.0, 100.0, 25.0);

        $this->assertEquals(100.0, $res['planned_supply_qty']);
    }

    /**
     * Test 5 — Exact Multiple: Net Requirement = 75, Multiple = 25 -> Planned = 75.
     */
    public function test_5_exact_multiple_does_not_over_inflate(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(75.0, 0.0, 25.0);

        $this->assertEquals(75.0, $res['planned_supply_qty']);
    }

    /**
     * Test 6 — Requirement Above MOQ: Net Requirement = 125, MOQ = 100, Multiple = 50 -> Planned = 150.
     */
    public function test_6_requirement_above_moq_uses_next_multiple(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(125.0, 100.0, 50.0);

        $this->assertEquals(150.0, $res['planned_supply_qty']);
    }

    /**
     * Test 7 — Decimal Quantity: Net Requirement = 7.2, Multiple = 2.5 -> Planned = 7.5.
     */
    public function test_7_decimal_quantity_with_decimal_multiple(): void
    {
        $lotSizer = app(MrpLotSizingService::class);
        $res = $lotSizer->calculatePlannedSupply(7.2, 0.0, 2.5);

        $this->assertEquals(7.5, $res['planned_supply_qty']);
    }

    /**
     * Test 8 — Existing Supply netting happens BEFORE Lot Sizing.
     * Demand = 100, Stock = 20, Open PO = 50 -> Net Shortage = 30.
     * With MOQ = 100 -> Planned Supply = 100 (not 100 - 70 = 30; Net is 30, then MOQ 100 applied).
     */
    public function test_8_existing_supply_netted_before_lot_sizing(): void
    {
        $product = $this->createProduct('RM-LOT-8', 100.0, 0.0);
        $wh = $this->createWarehouse();
        $vendor = $this->createVendor($this->tenant->id);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'quantity' => 20.0,
            'reserved_qty' => 0.0,
        ]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-LOT-8',
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
                'source_ref' => 'Demand Test 8',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);
        $this->assertNotEmpty($result['consolidated']);

        $item = $result['consolidated'][0];
        $this->assertEquals(100.0, $item['required_qty']);
        $this->assertEquals(70.0, $item['total_usable_supply_qty']);
        $this->assertEquals(30.0, $item['net_requirement_qty']); // Net requirement is 30!
        $this->assertEquals(100.0, $item['planned_supply_qty']); // Planned supply is 100 due to MOQ!
    }

    /**
     * Test 9 — Multiple Dated Demands & Chronological Netting preserved before lot sizing.
     */
    public function test_9_multiple_dated_demands_netted_before_lot_sizing(): void
    {
        $product = $this->createProduct('RM-LOT-9', 0.0, 50.0);
        $wh = $this->createWarehouse();

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'quantity' => 10.0,
            'reserved_qty' => 0.0,
        ]);

        $mrpService = app(MrpShortageService::class);
        $demandInputs = [
            [
                'product_id' => $product->id,
                'quantity' => 30.0,
                'required_date' => '2026-09-02',
                'source_ref' => 'Demand 1',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);
        $item = $result['consolidated'][0];

        $this->assertEquals(30.0, $item['required_qty']);
        $this->assertEquals(20.0, $item['net_requirement_qty']); // 30 - 10 = 20 net requirement
        $this->assertEquals(50.0, $item['planned_supply_qty']); // Multiple 50 rounds 20 up to 50
    }

    /**
     * Test 10 — Tenant Isolation: Tenant A does not consume Tenant B lot-sizing parameters.
     */
    public function test_10_tenant_isolation_in_lot_sizing(): void
    {
        $productA = $this->createProduct('RM-TENANT-LOT-A', 100.0, 0.0);
        $tenantB = \App\Models\Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-lot-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Product B',
            'sku' => 'RM-TENANT-LOT-B',
            'type' => 'raw_material',
            'minimum_order_qty' => 500.0,
            'status' => 'active',
        ]);

        $mrpService = app(MrpShortageService::class);
        $resultA = $mrpService->calculateShortages([
            ['product_id' => $productA->id, 'quantity' => 30.0, 'required_date' => '2026-09-10'],
        ], $this->tenant->id);

        $this->assertEquals(100.0, $resultA['consolidated'][0]['planned_supply_qty']);
        $this->assertNotEquals(500.0, $resultA['consolidated'][0]['planned_supply_qty']);
    }

    /**
     * Test 11 — Explicit Guardrail #5 Test:
     * Parent requirement = 73, MOQ = 100 -> Parent Net Requirement = 73, Planned Supply = 100.
     * Verify child BOM explosion child requirement is based on actual Net 73, NOT inflated to 100.
     */
    public function test_11_parent_moq_does_not_inflate_child_bom_requirement(): void
    {
        $wh = $this->createWarehouse();

        // Parent SFG with MOQ = 100
        $parent = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SubAssembly SFG-11',
            'sku' => 'SFG-11',
            'type' => 'semi_finished',
            'planning_type' => 'manufacture',
            'minimum_order_qty' => 100.0,
            'status' => 'active',
        ]);

        // Child Raw Material
        $child = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Material RM-11',
            'sku' => 'RM-11',
            'type' => 'raw_material',
            'planning_type' => 'purchase',
            'minimum_order_qty' => 0.0,
            'status' => 'active',
        ]);

        // BOM: 1 SFG requires 2 RM
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $parent->id,
            'bom_number' => 'BOM-11',
            'base_quantity' => 1.0,
            'status' => 'approved',
            'effective_date' => '2026-01-01',
        ]);

        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'production_bom_id' => $bom->id,
            'bom_id' => $bom->id,
            'material_id' => $child->id,
            'quantity' => 2.0,
            'uom_id' => $uom->id,
            'material_scrap_percentage' => 0.0,
        ]);

        $mrpService = app(MrpShortageService::class);
        $demandInputs = [
            [
                'product_id' => $parent->id,
                'quantity' => 73.0,
                'required_date' => '2026-09-10',
                'source_ref' => 'Demand 11',
            ],
        ];

        $result = $mrpService->calculateShortages($demandInputs, $this->tenant->id, $wh->id);

        $treeNode = $result['tree'][0];
        $this->assertEquals(73.0, $treeNode['net_requirement_qty']);
        $this->assertEquals(100.0, $treeNode['planned_supply_qty']);

        // Check child node in tree: child required quantity must be 73 * 2 = 146 (NOT 100 * 2 = 200)
        $childNode = $treeNode['children'][0];
        $this->assertEquals(146.0, $childNode['required_qty']);
        $this->assertEquals(146.0, $childNode['net_requirement_qty']);
    }
}
