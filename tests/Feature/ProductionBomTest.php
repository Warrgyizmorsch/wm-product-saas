<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\ProductionBomService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBomTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Uom $uomA;
    private Uom $uomB;
    private Product $finishedGoodA;
    private Product $subAssemblyA;
    private Product $rawMaterialA;
    private Product $finishedGoodB;
    private Product $rawMaterialB;
    private Routing $routingA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => 'password',
        ]);

        $this->seed(RbacSeeder::class);

        $productionManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->userA->id,
            'role_id' => $productionManagerRole->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        // 2. Create Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => 'password',
        ]);

        // Setup UOMs and Products for Tenant A
        $this->uomA = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
        
        $this->routingA = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Standard Assembly Line Routing',
            'status' => 'active',
        ]);

        $this->finishedGoodA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Car Door Assembly',
            'sku' => 'FG-CAR-DOOR',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 0.0,
        ]);

        $this->subAssemblyA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inner Panel sub-assembly',
            'sku' => 'SF-PANEL',
            'type' => 'finished_good', // Manufactured sub-assembly
            'status' => 'active',
            'unit_cost' => 50.00,
        ]);

        $this->rawMaterialA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Raw Steel Sheet',
            'sku' => 'RM-STEEL',
            'type' => 'raw_material',
            'status' => 'active',
            'unit_cost' => 10.00,
        ]);

        // Setup UOMs and Products for Tenant B
        $this->uomB = Uom::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
        $this->finishedGoodB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Finished Product B',
            'sku' => 'FG-B',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 0.0,
        ]);
        $this->rawMaterialB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Raw Material B',
            'sku' => 'RM-B',
            'type' => 'raw_material',
            'status' => 'active',
            'unit_cost' => 5.0,
        ]);
    }

    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        $bomB = ProductionBom::create([
            'tenant_id' => $this->tenantB->id,
            'bom_number' => 'BOM-B-001',
            'bom_name' => 'BOM B Standard',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGoodB->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomB->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->userB->id,
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.boms.show', $bomB->id));

        $response->assertStatus(404);
    }

    public function test_can_create_bom_with_base_quantity_and_type(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), [
                'bom_number' => 'BOM-A-001',
                'bom_name' => 'Car Door Standard BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $this->finishedGoodA->id,
                'base_quantity' => 100, // base quantity basis
                'base_uom_id' => $this->uomA->id,
                'version' => '1.0.0',
                'routing_id' => $this->routingA->id,
                'effective_date' => now()->toDateString(),
                'notes' => 'Standard Door assembly',
                'items' => [
                    [
                        'material_id' => $this->rawMaterialA->id,
                        'quantity' => 200,
                        'uom_id' => $this->uomA->id,
                        'material_scrap_percentage' => 5.00,
                        'is_alternative' => 0,
                        'priority' => 1,
                    ],
                ]
            ]);

        $response->assertSessionHasNoErrors();
        
        $bom = ProductionBom::where('bom_number', 'BOM-A-001')->first();
        $this->assertNotNull($bom);
        $this->assertEquals($this->tenantA->id, $bom->tenant_id);
        $this->assertEquals('Car Door Standard BOM', $bom->bom_name);
        $this->assertEquals('manufacturing', $bom->bom_type);
        $this->assertEquals(100.0, $bom->base_quantity);
        $this->assertEquals($this->routingA->id, $bom->routing_id);
        $this->assertEquals('draft', $bom->status);
        $this->assertCount(1, $bom->items);
        $this->assertEquals(5.00, $bom->items->first()->material_scrap_percentage);
    }

    public function test_auto_bom_number_generation(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), [
                'bom_number' => 'AUTO', // auto-generation keyword
                'bom_name' => 'Auto BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $this->finishedGoodA->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $this->uomA->id,
                'version' => '1.0.0',
                'effective_date' => now()->toDateString(),
                'items' => [
                    [
                        'material_id' => $this->rawMaterialA->id,
                        'quantity' => 2.0,
                        'uom_id' => $this->uomA->id,
                    ],
                ]
            ]);

        $response->assertSessionHasNoErrors();
        $bom = ProductionBom::where('bom_name', 'Auto BOM')->first();
        $this->assertNotNull($bom);
        $this->assertEquals('BOM-000001', $bom->bom_number);
    }

    public function test_custom_bom_number_validation(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), [
                'bom_number' => 'CAR-DOOR-SPEC-BOM', // Custom string
                'bom_name' => 'Custom BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $this->finishedGoodA->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $this->uomA->id,
                'version' => '1.0.0',
                'effective_date' => now()->toDateString(),
                'items' => [
                    [
                        'material_id' => $this->rawMaterialA->id,
                        'quantity' => 2.0,
                        'uom_id' => $this->uomA->id,
                    ],
                ]
            ]);

        $response->assertSessionHasNoErrors();
        $bom = ProductionBom::where('bom_name', 'Custom BOM')->first();
        $this->assertNotNull($bom);
        $this->assertEquals('CAR-DOOR-SPEC-BOM', $bom->bom_number);
    }

    public function test_duplicate_bom_number_is_prevented(): void
    {
        // Seed first BOM
        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-DUPLICATE-TEST',
            'bom_name' => 'Duplicate 1',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Attempt second BOM with same number
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), [
                'bom_number' => 'BOM-DUPLICATE-TEST',
                'bom_name' => 'Duplicate 2',
                'bom_type' => 'manufacturing',
                'product_id' => $this->finishedGoodA->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $this->uomA->id,
                'version' => '2.0.0',
                'effective_date' => now()->toDateString(),
                'items' => [
                    [
                        'material_id' => $this->rawMaterialA->id,
                        'quantity' => 2.0,
                        'uom_id' => $this->uomA->id,
                    ],
                ]
            ]);

        $response->assertSessionHas('error');
    }

    public function test_base_quantity_cannot_be_zero(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), [
                'bom_number' => 'BOM-ZERO-QTY',
                'bom_name' => 'Zero Qty BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $this->finishedGoodA->id,
                'base_quantity' => 0.0, // Invalid zero quantity
                'base_uom_id' => $this->uomA->id,
                'version' => '1.0.0',
                'effective_date' => now()->toDateString(),
                'items' => [
                    [
                        'material_id' => $this->rawMaterialA->id,
                        'quantity' => 2.0,
                        'uom_id' => $this->uomA->id,
                    ],
                ]
            ]);

        $response->assertSessionHasErrors(['base_quantity']);
    }

    public function test_approval_workflow_transitions_and_deactivates_older_versions(): void
    {
        // 1. Create first approved BOM version 1.0.0
        $bom1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CD-1',
            'bom_name' => 'V1',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->userA->id,
        ]);

        // 2. Create second draft BOM version 1.1.0
        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CD-1',
            'bom_name' => 'V1.1',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 1.0,
            'version' => '1.1.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);

        // Submit for approval
        $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.submit', $bom2->id))
            ->assertStatus(302);

        $this->assertEquals('pending_approval', $bom2->fresh()->status);

        // Approve version 1.1.0
        $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.approve', $bom2->id))
            ->assertStatus(302);

        // Assert newer version is approved and older version is inactive
        $this->assertEquals('approved', $bom2->fresh()->status);
        $this->assertEquals('inactive', $bom1->fresh()->status);

        // Assert approval logs are written
        $this->assertDatabaseHas('production_bom_approvals', [
            'bom_id' => $bom2->id,
            'action' => 'Submitted',
        ]);
        $this->assertDatabaseHas('production_bom_approvals', [
            'bom_id' => $bom2->id,
            'action' => 'Approved',
        ]);
    }

    public function test_version_duplication_creates_new_revision(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-DUP-TEST',
            'bom_name' => 'BOM standard',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 10.0,
            'version' => '1.0.0',
            'revision' => 0,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->userA->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $bom->id,
            'material_id' => $this->rawMaterialA->id,
            'quantity' => 20.0000,
            'uom_id' => $this->uomA->id,
            'material_scrap_percentage' => 10.00,
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.duplicate', $bom->id), [
                'new_version' => '1.1.0',
            ]);

        $response->assertStatus(302);
        
        $newBom = ProductionBom::where('version', '1.1.0')->first();
        $this->assertNotNull($newBom);
        $this->assertEquals('draft', $newBom->status);
        $this->assertEquals(1, $newBom->revision); // revision increments
        $this->assertCount(1, $newBom->items);
        $this->assertEquals(20.0, $newBom->items->first()->quantity);
        $this->assertEquals(10.00, $newBom->items->first()->material_scrap_percentage);

        // Check history log
        $this->assertDatabaseHas('production_bom_approvals', [
            'bom_id' => $newBom->id,
            'action' => 'Revision Created',
        ]);
    }

    public function test_scrap_logic_computes_correct_gross_material(): void
    {
        $service = app(ProductionBomService::class);
        
        // net qty = 100, scrap = 5%
        // required = 100 + (100 * 0.05) = 105
        $gross = $service->calculateRequiredMaterial(100.0, 5.0);
        $this->assertEquals(105.0, $gross);

        // net qty = 20, scrap = 10%
        // required = 20 + 2 = 22
        $gross2 = $service->calculateRequiredMaterial(20.0, 10.0);
        $this->assertEquals(22.0, $gross2);
    }

    public function test_bom_multi_level_explosion_with_scrap(): void
    {
        // 1. Build Sub-assembly BOM (to build 1 panel, you need 2 steel sheets with 10% scrap)
        $subBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-SUB-PANEL',
            'bom_name' => 'Inner Panel sub-assembly',
            'bom_type' => 'manufacturing',
            'product_id' => $this->subAssemblyA->id,
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $subBom->id,
            'material_id' => $this->rawMaterialA->id,
            'quantity' => 2.0, // 2kg steel per panel
            'uom_id' => $this->uomA->id,
            'material_scrap_percentage' => 10.00, // 10% scrap
        ]);

        // 2. Build Finished Good BOM (to build 100 door assemblies, you need 100 subassembly panels with 0% scrap)
        $parentBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-PARENT-DOOR',
            'bom_name' => 'Parent Door Standard',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 100.0, // base qty
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $parentBom->id,
            'material_id' => $this->subAssemblyA->id,
            'quantity' => 100.0, // 100 sub-assembly panels for 100 doors
            'uom_id' => $this->uomA->id,
            'material_scrap_percentage' => 0.00,
        ]);

        // 3. Explode finished door assembly for a batch size of 500 doors
        $explosionService = app(BomExplosionService::class);
        $explosion = $explosionService->explode($this->finishedGoodA->id, 500.0, $this->tenantA->id);

        // Multiplier at parent level is 500 / 100 = 5
        // Net sub-assemblies required = 100 * 5 = 500 panels
        // Gross sub-assemblies required = 500 panels (scrap 0%)
        // Recursing to sub-assembly BOM with parent qty 500:
        // Multiplier at sub-assembly level is 500 / 1.0 = 500
        // Net steel sheets = 2.0 * 500 = 1000 kg steel
        // Gross steel sheets = 1000 * 1.1 = 1100 kg steel
        
        $this->assertEquals(500, $explosion['tree']['quantity']);
        $this->assertCount(1, $explosion['tree']['children']);
        
        $subNode = $explosion['tree']['children'][0];
        $this->assertEquals($this->subAssemblyA->id, $subNode['product_id']);
        $this->assertEquals(500.0, $subNode['quantity']);
        
        $steelNode = $subNode['children'][0];
        $this->assertEquals($this->rawMaterialA->id, $steelNode['product_id']);
        $this->assertEquals(1000.0, $steelNode['net_quantity']);
        $this->assertEquals(1100.0, $steelNode['gross_quantity']); // verified 1100 with scrap

        // Consolidated requirements should only contain the leaf raw material
        $this->assertCount(1, $explosion['flat']);
        $this->assertEquals($this->rawMaterialA->id, $explosion['flat'][0]['product_id']);
        $this->assertEquals(1100.0, $explosion['flat'][0]['gross_quantity']);
    }

    public function test_circular_dependency_is_prevented(): void
    {
        // FG Door references Subassembly, and Subassembly references FG Door (loop)
        $bom1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-FG',
            'bom_name' => 'FG Door',
            'product_id' => $this->finishedGoodA->id,
            'base_quantity' => 1.0,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $bom1->id,
            'material_id' => $this->subAssemblyA->id,
            'quantity' => 1.0,
            'uom_id' => $this->uomA->id,
        ]);

        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-SUB',
            'bom_name' => 'Sub Panel',
            'product_id' => $this->subAssemblyA->id,
            'base_quantity' => 1.0,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $bom2->id,
            'material_id' => $this->finishedGoodA->id, // References parent (infinite loop)
            'quantity' => 1.0,
            'uom_id' => $this->uomA->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Circular dependency loop detected");

        $explosionService = app(BomExplosionService::class);
        $explosionService->explode($this->finishedGoodA->id, 10.0, $this->tenantA->id);
    }

    public function test_quick_create_product_success(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->postJson(route('products.quick-create'), [
                'name' => 'Alloy Assembly Joint',
                'sku' => 'SF-ALLOY-JOINT',
                'type' => 'semi_finished',
                'unit_cost' => 12.99
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'type'])
            ->assertJson([
                'name' => 'Alloy Assembly Joint',
                'type' => 'semi_finished'
            ]);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenantA->id,
            'sku' => 'SF-ALLOY-JOINT',
            'type' => 'semi_finished'
        ]);
    }

    public function test_quick_create_uom_success(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->postJson(route('uoms.quick-create'), [
                'name' => 'Liters',
                'code' => 'L'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name'])
            ->assertJson([
                'name' => 'Liters'
            ]);

        $this->assertDatabaseHas('uoms', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'L'
        ]);
    }

    public function test_quick_create_product_validation(): void
    {
        // SKU is required
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->postJson(route('products.quick-create'), [
                'name' => 'Incomplete Product',
                'type' => 'raw_material'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_quick_create_tenant_isolation(): void
    {
        // Creating duplicate SKU for Tenant A is allowed if it belongs to Tenant B
        Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Shared SKU Product',
            'sku' => 'SKU-SHARED',
            'type' => 'raw_material',
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->postJson(route('products.quick-create'), [
                'name' => 'Tenant A Product',
                'sku' => 'SKU-SHARED',
                'type' => 'raw_material'
            ]);

        $response->assertStatus(200);

        // However, Tenant A cannot duplicate their own SKU
        $response2 = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->postJson(route('products.quick-create'), [
                'name' => 'Tenant A Duplicate SKU',
                'sku' => 'SKU-SHARED',
                'type' => 'raw_material'
            ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_create_child_bom_prefills_product(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.boms.create', ['product_id' => $this->subAssemblyA->id]));

        $response->assertStatus(200)
            ->assertSee('value="' . $this->subAssemblyA->id . '"', false);
    }
}
