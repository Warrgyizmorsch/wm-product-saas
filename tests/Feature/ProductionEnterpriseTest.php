<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Services\ProductionBomVersionService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\BomWhereUsedService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Enterprise Manufacturing Tenant',
            'slug' => 'ent-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manufacturing Admin',
            'email' => 'mfg-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->seed(RbacSeeder::class);

        $productionManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $productionManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Units',
            'code' => 'PCS',
        ]);
    }

    /**
     * Test Work Center Hierarchies and Cycle Prevention.
     */
    public function test_work_center_hierarchy_and_cycle_prevention(): void
    {
        // 1. Create a department
        $dept = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Department',
            'code' => 'DEPT-ASSY',
            'type' => 'department',
            'status' => 'active',
        ]);

        // 2. Create a section under the department
        $section = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronic Section',
            'code' => 'SEC-ELEC',
            'type' => 'section',
            'parent_id' => $dept->id,
            'status' => 'active',
        ]);

        // 3. Create a work center under the section
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Line 1',
            'code' => 'WC-ASSY1',
            'type' => 'work_center',
            'parent_id' => $section->id,
            'status' => 'active',
        ]);

        $this->assertEquals($dept->id, $section->parent_id);
        $this->assertEquals($section->id, $wc->parent_id);
        $this->assertCount(1, $dept->children);
        $this->assertEquals($section->id, $dept->children->first()->id);

        // 4. Try to update department's parent to be Assembly Line 1 (Cycle: A -> B -> C -> A)
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->put(route('production.work-centers.update', $dept->id), [
                'name' => 'Assembly Department Renamed',
                'code' => 'DEPT-ASSY',
                'type' => 'department',
                'parent_id' => $wc->id,
                'status' => 'active',
            ]);

        $response->assertSessionHas('error', 'Circular work center hierarchy cycle detected.');
        $this->assertNull(WorkCenter::find($dept->id)->parent_id); // parent remains null

        // 5. Verify work centers edit controller filters out the current work center
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->get(route('production.work-centers.edit', $section->id));

        $response->assertStatus(200);
        $parentOptions = $response->viewData('parentOptions');
        // Parent options should not contain Electronic Section (itself)
        $this->assertFalse($parentOptions->contains('id', $section->id));
    }

    /**
     * Test BOM Revision Semantics.
     */
    public function test_bom_revision_semantics(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly A',
            'sku' => 'SUB-A',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-A',
            'bom_name' => 'Subassembly BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.2.3',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        // Add a component item
        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw steel',
            'sku' => 'RM-STEEL',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 10,
            'material_id' => $rm->id,
            'quantity' => 5.0,
            'uom_id' => $this->uom->id,
        ]);

        // 1. Post major bump revision
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->post(route('production.boms.create-revision', $bom->id), [
                'bump_type' => 'major',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'version' => '2.0.0',
            'status' => 'draft',
        ]);

        $majorBom = ProductionBom::where('version', '2.0.0')->first();
        $this->assertCount(1, $majorBom->items);
        $this->assertEquals(5.0, $majorBom->items->first()->quantity);

        // 2. Post minor bump revision
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->post(route('production.boms.create-revision', $bom->id), [
                'bump_type' => 'minor',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'version' => '1.3.0',
            'status' => 'draft',
        ]);

        // 3. Post patch bump revision
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->post(route('production.boms.create-revision', $bom->id), [
                'bump_type' => 'patch',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'version' => '1.2.4',
            'status' => 'draft',
        ]);
    }

    /**
     * Test auto-bumping version if the direct increment version already exists.
     */
    public function test_bom_revision_auto_bumps_if_conflict_exists(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly A',
            'sku' => 'SUB-A',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-A',
            'bom_name' => 'Subassembly BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.2.3',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        // Seed conflicting BOMs for 1.2.4 and 1.2.5
        ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-A',
            'bom_name' => 'Conflicting 1.2.4',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.2.4',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-A',
            'bom_name' => 'Conflicting 1.2.5',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.2.5',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Post patch revision - should automatically select 1.2.6
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->post(route('production.boms.create-revision', $bom->id), [
                'bump_type' => 'patch',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'version' => '1.2.6',
            'status' => 'draft',
        ]);
    }

    /**
     * Test MRP Tabular BOM Explosion.
     */
    public function test_mrp_tabular_bom_explosion(): void
    {
        // 1. Setup multi-level products
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Car',
            'sku' => 'FG-CAR',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $sub = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Car Door Assembly',
            'sku' => 'SF-DOOR',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Plate',
            'sku' => 'RM-STEEL',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        // 2. Setup BOM for finished car (FG-CAR -> SF-DOOR * 4)
        $fgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CAR',
            'bom_name' => 'Finished Car BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'sequence' => 10,
            'material_id' => $sub->id,
            'quantity' => 4.0,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 5.00, // 5% scrap
        ]);

        // 3. Setup BOM for sub-assembly (SF-DOOR -> RM-STEEL * 10)
        $subBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-DOOR',
            'bom_name' => 'Car Door BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $sub->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $subBom->id,
            'sequence' => 10,
            'material_id' => $rm->id,
            'quantity' => 10.0,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 10.00, // 10% scrap
        ]);

        // 4. Request BOM details page to confirm explosion is loaded
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->get(route('production.boms.show', $fgBom->id));

        $response->assertStatus(200);
        $explosion = $response->viewData('explosion');
        $this->assertNotNull($explosion);

        // Verify the tree node properties
        $tree = $explosion['tree'];
        $this->assertEquals('Finished Car', $tree['product_name']);
        $this->assertCount(1, $tree['children']);

        $doorChild = $tree['children'][0];
        $this->assertEquals('Car Door Assembly', $doorChild['product_name']);
        // Net quantity: 4.0 (qty per parent)
        $this->assertEquals(4.0, $doorChild['net_quantity']);
        // Gross quantity: 4.0 * (1 + 0.05) = 4.20
        $this->assertEquals(4.20, $doorChild['gross_quantity']);

        $steelPlateLeaf = $doorChild['children'][0];
        $this->assertEquals('Steel Plate', $steelPlateLeaf['product_name']);
        // Net quantity: 4.20 (gross qty of parent) * 10 = 42.0
        $this->assertEquals(42.0, $steelPlateLeaf['net_quantity']);
        // Gross quantity: 42.0 * (1 + 0.10) = 46.20
        $this->assertEquals(46.20, $steelPlateLeaf['gross_quantity']);
    }

    /**
     * Test Dynamic Material Consumption in Routing.
     */
    public function test_dynamic_material_consumption_in_routing(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Line 1',
            'code' => 'WC-ASSY1',
            'type' => 'work_center',
            'status' => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chassis Assembly Routing',
            'status' => 'active',
        ]);

        $op = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Assemble Chassis Frame',
            'operation_type' => 'assembly',
            'work_center_id' => $wc->id,
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 60.0,
            'expected_yield_percentage' => 98.0,
            'quality_required' => true,
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Frame Bracket',
            'sku' => 'RM-BRACKET',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        // Map material to routing operation
        $opMat = RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op->id,
            'material_id' => $rm->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
            'consumption_type' => 'backflush',
        ]);

        $this->assertEquals($op->id, $opMat->routing_operation_id);
        $this->assertEquals($rm->id, $opMat->material_id);
        $this->assertEquals(2.0, $opMat->quantity);
        $this->assertEquals('backflush', $opMat->consumption_type);

        $this->assertCount(1, $op->materials);
        $this->assertEquals($rm->id, $op->materials->first()->material_id);
    }

    /**
     * Test Overhead Costs and Scrap Calculation.
     */
    public function test_overhead_costs_and_scrap_calculation(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Welding Bay A',
            'code' => 'WC-WELD-A',
            'type' => 'work_center',
            'cost_per_hour' => 50.00, // labor hourly cost
            'overhead_rate' => 25.00,  // machine hourly overhead cost
            'status' => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chassis Assembly Routing',
            'status' => 'active',
        ]);

        $op = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Welding Frame',
            'operation_type' => 'assembly',
            'work_center_id' => $wc->id,
            'setup_time_minutes' => 30.0,      // 0.5 hour
            'processing_time_minutes' => 120.0, // 2.0 hours
            'expected_yield_percentage' => 95.0, // yield factor
            'labor_cost_rate' => 50.00 / 60.0,  // labor per minute cost
            'machine_cost_rate' => 25.00 / 60.0, // machine per minute overhead cost
            'quality_required' => false,
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Car Chassis',
            'sku' => 'FG-CHASSIS',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Plate',
            'sku' => 'RM-STEEL',
            'type' => 'raw_material',
            'status' => 'active',
            'unit_cost' => 12.00,
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CHASSIS',
            'bom_name' => 'Chassis BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 2.0, // base quantity
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'routing_id' => $routing->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 10,
            'material_id' => $rm->id,
            'quantity' => 15.0,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 8.00, // 8% scrap
        ]);

        // Calculate Cost via Service
        $costService = new ProductionCostService();
        $costs = $costService->calculateCost($bom);

        // 1. Material Cost:
        // quantity (15.0) * (1 + 8/100) * unit_cost (12.00) = 15.0 * 1.08 * 12.00 = 194.40
        $this->assertEquals(194.40, round($costs['material_cost'], 4));

        // 2. Routing Cost:
        // total time = (setup 30 + run 120) = 150 minutes = 2.5 hours
        // rate per minute = labor (50.00/60) + machine (25.00/60) = 75.00/60
        // standard cost = 150 minutes * (75.00/60) = 187.50
        // yield factor multiplier = 100 / expected_yield (95) = 1.0526315789...
        // scaled by base quantity (2.0)
        // formula: standard_cost * base_quantity * (100 / expected_yield)
        // = 187.50 * 2.0 * (100 / 95) = 375 * 1.0526315789... = 394.736842...
        $expectedRoutingCost = 150 * ((50.00 / 60.0) + (25.00 / 60.0)) * 2.0 * (100 / 95.0);
        $this->assertEquals(round($expectedRoutingCost, 4), round($costs['routing_cost'], 4));

        // Overhead Cost: 150 mins * (25.00/60) * 2.0 * (100/95.0) = 131.5789
        $expectedOverheadCost = 150 * (25.00 / 60.0) * 2.0 * (100 / 95.0);
        $this->assertEquals(round($expectedOverheadCost, 4), round($costs['overhead_cost'], 4));

        // Scrap Adjustment (value of scrap loss): 15.0 * (8/100) * 12.00 = 14.40
        $this->assertEquals(14.40, round($costs['scrap_adjustment'], 4));

        // 3. Total Cost: Material (194.40) + Routing (394.7368) + Overhead (131.5789) = 720.7158
        $this->assertEquals(round($costs['material_cost'] + $costs['routing_cost'] + $costs['overhead_cost'], 4), round($costs['total_cost'], 4));
    }

    /**
     * Test Where Used Directory.
     */
    public function test_where_used_directory(): void
    {
        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Common Raw Screw',
            'sku' => 'RM-SCREW',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        $fg1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabinet Frame',
            'sku' => 'FG-CABINET',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $fg2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chair Base',
            'sku' => 'FG-CHAIR',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $bom1 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CABINET',
            'bom_name' => 'Cabinet BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg1->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom1->id,
            'sequence' => 10,
            'material_id' => $rm->id,
            'quantity' => 16.0,
            'uom_id' => $this->uom->id,
        ]);

        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CHAIR',
            'bom_name' => 'Chair BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg2->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom2->id,
            'sequence' => 10,
            'material_id' => $rm->id,
            'quantity' => 8.0,
            'uom_id' => $this->uom->id,
        ]);

        // Query Where Used directory via Service
        $whereUsedService = new BomWhereUsedService();
        $parents = $whereUsedService->findParents($rm);

        $this->assertCount(2, $parents);
        $this->assertTrue($parents->contains('id', $fg1->id));
        $this->assertTrue($parents->contains('id', $fg2->id));
    }

    /**
     * Test that auto-generated BOM numbers do not collide with existing ones.
     */
    public function test_auto_generated_bom_number_is_always_unique(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget A',
            'sku' => 'WDG-A',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Component material',
            'sku' => 'RM-COMP',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        // Seed a BOM with the next auto number BOM-000001
        ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-000001',
            'bom_name' => 'Conflicting BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Submit store request with AUTO - should generate BOM-000002
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'ent-mfg')
            ->post(route('production.boms.store'), [
                'bom_number' => 'AUTO',
                'bom_name' => 'Auto Unique BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $product->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $this->uom->id,
                'version' => '1.1.0',
                'effective_date' => now()->toDateString(),
                'items' => [
                    [
                        'material_id' => $rm->id,
                        'quantity' => 1.0,
                        'uom_id' => $this->uom->id,
                    ]
                ]
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-000002',
            'bom_name' => 'Auto Unique BOM',
        ]);
    }

    /**
     * Test Work Center hierarchy path generation helper.
     */
    public function test_work_center_hierarchy_path_generation(): void
    {
        $dept = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fabrication',
            'code' => 'DEPT-FAB',
            'type' => 'department',
            'status' => 'active',
        ]);

        $section = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cutting',
            'code' => 'SEC-CUT',
            'type' => 'section',
            'parent_id' => $dept->id,
            'status' => 'active',
        ]);

        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Laser Cutting',
            'code' => 'WC-LASER',
            'type' => 'work_center',
            'parent_id' => $section->id,
            'status' => 'active',
        ]);

        $this->assertEquals('Fabrication > Cutting > Laser Cutting', $wc->getHierarchyPath());
    }

    /**
     * Test Routing validation contexts (permissive for engineering, strict active-only for manufacturing).
     */
    public function test_routing_validation_context_constraints(): void
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-DRAFT-1',
            'name' => 'Draft Routing',
            'status' => 'draft',
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineered FG',
            'sku' => 'FG-ENG',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Metal sheet',
            'sku' => 'RM-METAL',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        // 1. Trying to link draft routing to manufacturing BOM should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Only active routings can be assigned to manufacturing BOMs.");

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-ENG-1',
            'bom_name' => 'Manufacturing BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'routing_id' => $routing->id,
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $rm->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                ]
            ]
        ]);

        $bomService->create($dto, $this->user->id);
    }

    /**
     * Test draft routing is permitted during engineering BOM creation.
     */
    public function test_draft_routing_is_permitted_for_engineering_context(): void
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-DRAFT-2',
            'name' => 'Draft Routing 2',
            'status' => 'draft',
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineered FG 2',
            'sku' => 'FG-ENG-2',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Metal sheet 2',
            'sku' => 'RM-METAL-2',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-ENG-2',
            'bom_name' => 'Engineering BOM',
            'bom_type' => 'engineering',
            'usage_context' => 'engineering',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'routing_id' => $routing->id,
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $rm->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                ]
            ]
        ]);

        $bom = $bomService->create($dto, $this->user->id);
        $this->assertNotNull($bom);
        $this->assertEquals($routing->id, $bom->routing_id);
    }

    /**
     * Test circular child BOM linking prevention.
     */
    public function test_circular_child_bom_linking_detection(): void
    {
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Parent Good',
            'sku' => 'FG-PARENT',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $sub = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Child Subassembly',
            'sku' => 'SF-CHILD',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        // Create Parent BOM (Draft)
        $parentBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-PARENT',
            'bom_name' => 'Parent BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Create Child BOM (Draft) linking back to parent
        $childBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CHILD',
            'bom_name' => 'Child BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $sub->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Link parent to child item (BOM-CHILD has an item pointing to FG-PARENT with child_bom_id = BOM-PARENT)
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $childBom->id,
            'sequence' => 10,
            'material_id' => $fg->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
            'child_bom_id' => $parentBom->id,
        ]);

        // Now if we try to update/create Parent BOM linking child item to BOM-CHILD, it should detect circular cycle!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Circular child BOM dependency detected.");

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-PARENT',
            'bom_name' => 'Parent BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $sub->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                    'child_bom_id' => $childBom->id,
                ]
            ]
        ]);

        $bomService->update($parentBom->id, $dto);
    }

    /**
     * Test linked child BOM product must match component material product.
     */
    public function test_bom_item_child_bom_version_must_match_material_product(): void
    {
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Item',
            'sku' => 'FG-ITEM',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $sub1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly 1',
            'sku' => 'SF-SUB1',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        $sub2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly 2',
            'sku' => 'SF-SUB2',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        // Create BOM for Subassembly 2
        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB2',
            'bom_name' => 'Subassembly 2 BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $sub2->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        // Try to link Subassembly 2's BOM to Subassembly 1's component row
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Linked child BOM product does not match component material product.");

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-FG-ERR',
            'bom_name' => 'Invalid Link BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $sub1->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                    'child_bom_id' => $bom2->id, // Mismatch: bom2 belongs to sub2 but material_id is sub1
                ]
            ]
        ]);

        $bomService->create($dto, $this->user->id);
    }

    /**
     * Test child BOM effective date must predate parent BOM effective date.
     */
    public function test_child_bom_effective_date_must_predate_parent(): void
    {
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Item',
            'sku' => 'FG-ITEM-2',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $sub = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly',
            'sku' => 'SF-SUB-2',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        // Create child BOM with future effective date
        $childBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-FUTURE-CHILD',
            'bom_name' => 'Future Child BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $sub->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->addDays(5)->toDateString(), // 5 days in the future
            'status' => 'approved',
        ]);

        // Try to create parent BOM effective today linking the future child BOM
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Child BOM effective date");

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-PARENT-ERR',
            'bom_name' => 'Parent BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(), // Effective today
            'items' => [
                [
                    'material_id' => $sub->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                    'child_bom_id' => $childBom->id,
                ]
            ]
        ]);

        $bomService->create($dto, $this->user->id);
    }

    /**
     * Test child BOM expiry must outlast parent BOM expiry.
     */
    public function test_child_bom_expiry_must_outlast_parent(): void
    {
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Item',
            'sku' => 'FG-ITEM-3',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $sub = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subassembly',
            'sku' => 'SF-SUB-3',
            'type' => 'semi_finished',
            'status' => 'active',
        ]);

        // Create child BOM that expires in 2 days
        $childBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXPIRING-CHILD',
            'bom_name' => 'Expiring Child BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $sub->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        // Try to create parent BOM that expires in 5 days (outlasting child expiry)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Child BOM expiry date");

        $bomService = new \App\Domains\Production\Services\ProductionBomService(
            new \App\Domains\Production\Repositories\ProductionBomRepository(),
            new \App\Domains\Production\Services\ProductionBomNumberService()
        );

        $dto = \App\Domains\Production\DTO\ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-PARENT-EXP-ERR',
            'bom_name' => 'Parent BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(5)->toDateString(), // Expires in 5 days
            'items' => [
                [
                    'material_id' => $sub->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                    'child_bom_id' => $childBom->id,
                ]
            ]
        ]);

        $bomService->create($dto, $this->user->id);
    }
}

