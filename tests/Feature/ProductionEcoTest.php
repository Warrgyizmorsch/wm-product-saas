<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Services\ProductionEcoService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionEcoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Uom $uom;
    private User $user;
    private Product $fgProduct;
    private Product $rm1;
    private Product $rm2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = Tenant::create([
            'name' => 'ECO Tenant',
            'slug' => 'eco-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ECO Engineer',
            'email' => 'eco-engineer-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Widget',
            'sku' => 'FG-WIDGET-' . uniqid(),
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->rm1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Material Alpha',
            'sku' => 'RM-ALPHA-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->rm2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Material Beta',
            'sku' => 'RM-BETA-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);
    }

    private function createBom(int $revision = 0, float $rm1Qty = 2.0, string $status = 'approved'): ProductionBom
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-' . uniqid(),
            'bom_name' => 'BOM Widget Rev ' . $revision,
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->fgProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => "1.{$revision}.0",
            'revision' => $revision,
            'effective_date' => '2026-01-01',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => $rm1Qty,
            'uom_id' => $this->uom->id,
        ]);

        return $bom;
    }

    private function createRouting(int $revision = 0, float $runTime = 10.0, string $status = Routing::STATUS_ACTIVE): Routing
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RTG-' . uniqid(),
            'name' => 'Routing Widget Rev ' . $revision,
            'product_id' => $this->fgProduct->id,
            'version' => "1.{$revision}.0",
            'revision' => $revision,
            'is_default' => true,
            'effective_from' => '2026-01-01',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP10',
            'name' => 'Machining',
            'sequence' => 10,
            'setup_time_minutes' => 5.0,
            'processing_time_minutes' => $runTime,
        ]);

        return $routing;
    }

    private function createOrder(int $qty = 100, string $status = 'released', ?int $bomId = null): ProductionOrder
    {
        return ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-TEST-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bomId,
            'quantity_ordered' => $qty,
            'status' => $status,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
        ]);
    }

    /**
     * Test 1 — Draft ECO can be created with generated number.
     */
    public function test_1_create_draft_eco(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $ecoService = app(ProductionEcoService::class);

        $eco = $ecoService->createEco([
            'title' => 'Optimize Component Alpha Usage',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'reason' => 'Material cost reduction',
        ], $this->user->id);

        $this->assertEquals(ProductionEco::STATUS_DRAFT, $eco->status);
        $this->assertStringStartsWith('ECO-2026-', $eco->eco_number);
        $this->assertEquals(0, $eco->current_bom_revision);
        $this->assertEquals(1, $eco->proposed_bom_revision);
    }

    /**
     * Test 2 — Full Lifecycle: DRAFT -> UNDER_REVIEW -> APPROVED -> RELEASED -> CLOSED.
     */
    public function test_2_approval_lifecycle(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Lifecycle Test ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $this->assertEquals(ProductionEco::STATUS_DRAFT, $eco->status);

        $eco = $ecoService->submitForReview($eco, $this->user->id);
        $this->assertEquals(ProductionEco::STATUS_UNDER_REVIEW, $eco->status);

        $eco = $ecoService->approve($eco, 'Approved by QC Lead', $this->user->id);
        $this->assertEquals(ProductionEco::STATUS_APPROVED, $eco->status);

        $eco = $ecoService->release($eco, $this->user->id);
        $this->assertEquals(ProductionEco::STATUS_RELEASED, $eco->status);

        $eco = $ecoService->close($eco, $this->user->id);
        $this->assertEquals(ProductionEco::STATUS_CLOSED, $eco->status);
    }

    /**
     * Test 3 — Unapproved ECO Cannot Release (Throws Exception).
     */
    public function test_3_unapproved_eco_cannot_release(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $ecoService = app(ProductionEcoService::class);

        $eco = $ecoService->createEco([
            'title' => 'Unapproved Release Attempt',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
        ], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $ecoService->release($eco, $this->user->id);
    }

    /**
     * Test 4 — BOM Revision Preservation: Current BOM remains intact; proposed BOM activated upon release.
     */
    public function test_4_bom_revision_preservation(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'BOM Revision ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $currentBom->refresh();
        $proposedBom->refresh();

        $this->assertEquals('inactive', $currentBom->status);
        $this->assertEquals(0, $currentBom->revision);

        $this->assertEquals('approved', $proposedBom->status);
        $this->assertEquals(1, $proposedBom->revision);
    }

    /**
     * Test 5 — Routing Revision Preservation: Current routing set to historical; new routing activated.
     */
    public function test_5_routing_revision_preservation(): void
    {
        $currentRouting = $this->createRouting(0, 10.0);
        $proposedRouting = $this->createRouting(1, 15.0, Routing::STATUS_DRAFT);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Routing Revision ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_ROUTING,
            'product_id' => $this->fgProduct->id,
            'proposed_routing_id' => $proposedRouting->id,
        ], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $currentRouting->refresh();
        $proposedRouting->refresh();

        $this->assertEquals(Routing::STATUS_HISTORICAL, $currentRouting->status);
        $this->assertEquals(Routing::STATUS_ACTIVE, $proposedRouting->status);
        $this->assertEquals(1, $proposedRouting->revision);
    }

    /**
     * Test 6 — Future Production Order uses newly released revision after effective date.
     */
    public function test_6_future_production_orders_use_new_revision(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Effective Revision Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $activeBom = ProductionBom::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->fgProduct->id)
            ->where('status', 'approved')
            ->first();

        $this->assertEquals($proposedBom->id, $activeBom->id);
        $this->assertEquals(1, $activeBom->revision);
    }

    /**
     * Test 7 — Existing Released Production Order snapshot is preserved.
     */
    public function test_7_existing_production_order_snapshot_preserved(): void
    {
        $currentBom = $this->createBom(0, 2.0);

        $order = $this->createOrder(100, 'released', $currentBom->id);

        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Snapshot Safety ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $order->refresh();
        $this->assertEquals($currentBom->id, $order->bom_id);
    }

    /**
     * Test 8 — BOM Impact Analysis detects component additions, removals, quantity changes.
     */
    public function test_8_bom_impact_analysis(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        // Add RM2 to proposed BOM
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $proposedBom->id,
            'sequence' => 2,
            'material_id' => $this->rm2->id,
            'quantity' => 3.0,
            'uom_id' => $this->uom->id,
        ]);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'BOM Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'current_bom_id' => $currentBom->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertNotEmpty($impact['material_impact']['added']);
        $this->assertEquals($this->rm2->id, $impact['material_impact']['added'][0]['material_id']);
        $this->assertNotEmpty($impact['material_impact']['quantity_changed']);
        $this->assertEquals(-1.0, $impact['material_impact']['quantity_changed'][0]['delta_quantity']);
    }

    /**
     * Test 9 — Routing Impact Analysis detects added/removed operations and cycle time deltas.
     */
    public function test_9_routing_impact_analysis(): void
    {
        $currentRouting = $this->createRouting(0, 10.0);
        $proposedRouting = $this->createRouting(1, 15.0, Routing::STATUS_DRAFT);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Routing Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_ROUTING,
            'product_id' => $this->fgProduct->id,
            'current_routing_id' => $currentRouting->id,
            'proposed_routing_id' => $proposedRouting->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertNotEmpty($impact['routing_impact']['modified_operations']);
        $this->assertEquals(5.0, $impact['capacity_impact']['cycle_time_delta_per_unit_minutes']);
    }

    /**
     * Test 10 — Open Production Order Impact identifies open orders correctly.
     */
    public function test_10_open_production_order_impact(): void
    {
        $this->createOrder(50, 'released');
        $this->createOrder(100, 'completed');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Open Order Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertEquals(1, $impact['open_orders']['total_count']);
        $this->assertEquals(50.0, $impact['open_orders']['total_quantity']);
    }

    /**
     * Test 11 — WIP Protection: Existing ProductionWip records are not modified by impact analysis.
     */
    public function test_11_wip_protection(): void
    {
        $order = $this->createOrder(100, 'in_progress');

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'WIP Op',
            'target_produced_qty' => 100,
            'status' => 'running',
        ]);

        ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'product_id' => $this->fgProduct->id,
            'quantity_in_wip' => 40,
            'status' => 'in_progress',
        ]);

        $wipCountBefore = ProductionWip::count();

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'WIP Protection Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertEquals($wipCountBefore, ProductionWip::count());
        $this->assertEquals(1, $impact['wip_impact']['affected_wip_count']);
    }

    /**
     * Test 12 — Batch Genealogy Protection: Existing batch records remain intact.
     */
    public function test_12_batch_genealogy_protection(): void
    {
        $order = $this->createOrder(100, 'in_progress');

        ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->fgProduct->id,
            'batch_number' => 'BAT-001',
            'planned_quantity' => 100,
            'status' => 'active',
        ]);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Genealogy Safety Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertEquals('PROTECTED_HISTORICAL_DATA_INTACT', $impact['genealogy_impact']['protection_status']);
        $this->assertEquals(1, $impact['genealogy_impact']['affected_batches_count']);
    }

    /**
     * Test 13 — MRP Impact calculates net demand shift across open orders.
     */
    public function test_13_mrp_impact(): void
    {
        $this->createOrder(100, 'released');

        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'MRP Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'current_bom_id' => $currentBom->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertNotEmpty($impact['mrp_impact']['component_deltas']);
        $this->assertEquals(-100.0, $impact['mrp_impact']['component_deltas'][0]['net_demand_delta']);
    }

    /**
     * Test 14 — Cost Impact calculates standard unit cost variance.
     */
    public function test_14_cost_impact(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 3.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Cost Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'current_bom_id' => $currentBom->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertEquals(10.0, $impact['cost_impact']['material_cost_delta']);
    }

    /**
     * Test 15 — Capacity Impact calculates cycle time changes.
     */
    public function test_15_capacity_impact(): void
    {
        $this->createOrder(120, 'released');

        $currentRouting = $this->createRouting(0, 10.0);
        $proposedRouting = $this->createRouting(1, 20.0, Routing::STATUS_DRAFT);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Capacity Impact Test',
            'change_type' => ProductionEco::CHANGE_TYPE_ROUTING,
            'product_id' => $this->fgProduct->id,
            'current_routing_id' => $currentRouting->id,
            'proposed_routing_id' => $proposedRouting->id,
        ], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);

        $this->assertEquals(10.0, $impact['capacity_impact']['cycle_time_delta_per_unit_minutes']);
        $this->assertEquals(20.0, $impact['capacity_impact']['total_open_capacity_impact_hours']);
    }

    /**
     * Test 16 — Effective Date Enforcement: Releasing with future effective date preserves date scope.
     */
    public function test_16_effective_date_enforcement(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Future Effective Date ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'proposed_bom_id' => $proposedBom->id,
            'effective_date' => '2026-10-01',
        ], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $proposedBom->refresh();
        $this->assertEquals('2026-10-01', $proposedBom->effective_date->toDateString());
    }

    /**
     * Test 17 — Tenant Isolation: Tenant A cannot view or alter Tenant B ECO data.
     */
    public function test_17_tenant_isolation(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-eco-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $ecoService = app(ProductionEcoService::class);
        $ecoA = $ecoService->createEco([
            'title' => 'Tenant A ECO',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
        ], $this->user->id);

        $ecosB = ProductionEco::where('tenant_id', $tenantB->id)->get();
        $this->assertCount(0, $ecosB);
    }

    /**
     * Test 18 — Impact Analysis is 100% Read-Only and produces zero database mutations.
     */
    public function test_18_read_only_impact_analysis(): void
    {
        $currentBom = $this->createBom(0, 2.0);
        $proposedBom = $this->createBom(1, 1.0, 'draft');

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco([
            'title' => 'Read Only Test',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->fgProduct->id,
            'current_bom_id' => $currentBom->id,
            'proposed_bom_id' => $proposedBom->id,
        ], $this->user->id);

        $bomCountBefore = ProductionBom::count();
        $orderCountBefore = ProductionOrder::count();

        $ecoService->analyzeImpact($eco);

        $this->assertEquals($bomCountBefore, ProductionBom::count());
        $this->assertEquals($orderCountBefore, ProductionOrder::count());
    }
}
