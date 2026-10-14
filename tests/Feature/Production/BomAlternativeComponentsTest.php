<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\MrpEngineService;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionCostService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomAlternativeComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant Test',
            'slug' => 'tenant-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User Test',
            'email' => 'testuser@example.com',
            'password' => 'password',
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
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
    }

    private function createProduct(string $name, string $sku, string $type = 'raw_material', float $cost = 10.0): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'sku' => $sku,
            'type' => $type,
            'unit_cost' => $cost,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function header_validity_dates_are_retained_and_work_properly()
    {
        $fg = $this->createProduct('FG Desk', 'FG-DESK', 'finished_good');
        $effectiveDate = Carbon::now()->subDays(5)->toDateString();
        $expiryDate = Carbon::now()->addDays(30)->toDateString();

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-HDR-001',
            'bom_name' => 'Desk BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => $effectiveDate,
            'expiry_date' => $expiryDate,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($effectiveDate, $bom->effective_date->toDateString());
        $this->assertEquals($expiryDate, $bom->expiry_date->toDateString());
    }

    /** @test */
    public function can_store_bom_with_primary_and_alternatives_using_row_uids()
    {
        $fg = $this->createProduct('Wooden Chair', 'FG-CHAIR', 'finished_good');
        $oakLeg = $this->createProduct('Oak Leg', 'RM-OAK-LEG', 'raw_material', 15.0);
        $pineLeg = $this->createProduct('Pine Leg', 'RM-PINE-LEG', 'raw_material', 10.0);
        $steelLeg = $this->createProduct('Steel Leg', 'RM-STEEL-LEG', 'raw_material', 12.0);

        $payload = [
            'product_id' => $fg->id,
            'bom_number' => 'BOM-CHAIR-001',
            'bom_name' => 'Chair Standard BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'row_uid' => 'row_primary_leg',
                    'material_id' => $oakLeg->id,
                    'quantity' => 4,
                    'uom_id' => $this->uom->id,
                    'material_scrap_percentage' => 0,
                    'sequence' => 1,
                    'is_alternative' => 0,
                    'replaces_row_uid' => null,
                    'priority' => 1,
                ],
                [
                    'row_uid' => 'row_alt_pine',
                    'material_id' => $pineLeg->id,
                    'quantity' => 4,
                    'uom_id' => $this->uom->id,
                    'material_scrap_percentage' => 0,
                    'sequence' => 2,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_primary_leg',
                    'priority' => 1,
                ],
                [
                    'row_uid' => 'row_alt_steel',
                    'material_id' => $steelLeg->id,
                    'quantity' => 4,
                    'uom_id' => $this->uom->id,
                    'material_scrap_percentage' => 0,
                    'sequence' => 3,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_primary_leg',
                    'priority' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'tenant-test')
            ->post(route('production.boms.store'), $payload);

        $response->assertSessionHasNoErrors();

        $bom = ProductionBom::where('bom_number', 'BOM-CHAIR-001')->firstOrFail();
        $this->assertCount(3, $bom->items);

        $primaryItem = $bom->items()->where('material_id', $oakLeg->id)->first();
        $altPine = $bom->items()->where('material_id', $pineLeg->id)->first();
        $altSteel = $bom->items()->where('material_id', $steelLeg->id)->first();

        $this->assertFalse((bool)$primaryItem->is_alternative);
        $this->assertNull($primaryItem->replaces_item_id);

        $this->assertTrue((bool)$altPine->is_alternative);
        $this->assertEquals($primaryItem->id, $altPine->replaces_item_id);
        $this->assertEquals(1, $altPine->priority);

        $this->assertTrue((bool)$altSteel->is_alternative);
        $this->assertEquals($primaryItem->id, $altSteel->replaces_item_id);
        $this->assertEquals(2, $altSteel->priority);

        // Test model relations
        $this->assertCount(2, $primaryItem->alternatives);
        $this->assertEquals($altPine->id, $primaryItem->alternatives->first()->id);
        $this->assertEquals($primaryItem->id, $altPine->primaryItem->id);
    }

    /** @test */
    public function validation_fails_if_alternative_targets_itself_or_non_existent_row()
    {
        $fg = $this->createProduct('Table', 'FG-TBL', 'finished_good');
        $top = $this->createProduct('Table Top', 'RM-TOP');

        $payloadSelf = [
            'product_id' => $fg->id,
            'bom_number' => 'BOM-TBL-ERR1',
            'bom_name' => 'Table BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'row_uid' => 'row_1',
                    'material_id' => $top->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_1',
                    'priority' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'tenant-test')
            ->post(route('production.boms.store'), $payloadSelf);

        $response->assertSessionHasErrors(['items.0.replaces_row_uid']);

        $payloadMissing = [
            'product_id' => $fg->id,
            'bom_number' => 'BOM-TBL-ERR2',
            'bom_name' => 'Table BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'row_uid' => 'row_1',
                    'material_id' => $top->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_does_not_exist',
                    'priority' => 1,
                ],
            ],
        ];

        $response2 = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'tenant-test')
            ->post(route('production.boms.store'), $payloadMissing);

        $response2->assertSessionHasErrors(['items.0.replaces_row_uid']);
    }

    /** @test */
    public function validation_fails_if_alternative_targets_another_alternative()
    {
        $fg = $this->createProduct('Cabinet', 'FG-CAB', 'finished_good');
        $p1 = $this->createProduct('Part 1', 'P1');
        $p2 = $this->createProduct('Part 2', 'P2');
        $p3 = $this->createProduct('Part 3', 'P3');

        $payload = [
            'product_id' => $fg->id,
            'bom_number' => 'BOM-CAB-ERR',
            'bom_name' => 'Cabinet BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'row_uid' => 'row_primary',
                    'material_id' => $p1->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'is_alternative' => 0,
                ],
                [
                    'row_uid' => 'row_alt1',
                    'material_id' => $p2->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_primary',
                    'priority' => 1,
                ],
                [
                    'row_uid' => 'row_alt2',
                    'material_id' => $p3->id,
                    'quantity' => 1,
                    'uom_id' => $this->uom->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'row_alt1', // Invalid: targets another alternative
                    'priority' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'tenant-test')
            ->post(route('production.boms.store'), $payload);

        $response->assertSessionHasErrors(['items.2.replaces_row_uid']);
    }

    /** @test */
    public function costing_service_excludes_alternative_components_from_total_cost()
    {
        $fg = $this->createProduct('Sofa', 'FG-SOFA', 'finished_good');
        $frame = $this->createProduct('Wood Frame', 'RM-FRAME', 'raw_material', 100.0);
        $altFrame = $this->createProduct('Metal Frame', 'RM-MFRAME', 'raw_material', 200.0);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-SOFA-001',
            'bom_name' => 'Sofa BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $primaryItem = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $frame->id,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => false,
            'priority' => 1,
            'sequence' => 1,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $altFrame->id,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => true,
            'replaces_item_id' => $primaryItem->id,
            'priority' => 1,
            'sequence' => 2,
        ]);

        $costService = app(ProductionCostService::class);
        $materialCost = $costService->calculateMaterialCost($bom);

        // Material cost must be exactly 100.0, NOT 300.0
        $this->assertEquals(100.0, $materialCost);
    }

    /** @test */
    public function bom_explosion_excludes_alternatives_from_consolidated_requirements()
    {
        $fg = $this->createProduct('Door', 'FG-DOOR', 'finished_good');
        $panel = $this->createProduct('Wood Panel', 'RM-PANEL', 'raw_material');
        $altPanel = $this->createProduct('Composite Panel', 'RM-CPANEL', 'raw_material');

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-DOOR-001',
            'bom_name' => 'Door BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $primary = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $panel->id,
            'quantity' => 2,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => false,
            'priority' => 1,
            'sequence' => 1,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $altPanel->id,
            'quantity' => 2,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => true,
            'replaces_item_id' => $primary->id,
            'priority' => 1,
            'sequence' => 2,
        ]);

        $explosionService = app(BomExplosionService::class);
        $explosion = $explosionService->explode($fg->id, 5, $this->tenant->id); // 5 Doors

        $flat = $explosion['flat'];
        $this->assertCount(1, $flat);
        $this->assertEquals($panel->id, $flat[0]['product_id']);
        $this->assertEquals(10.0, $flat[0]['gross_quantity']); // 5 * 2 = 10
    }

    /** @test */
    public function mrp_engine_excludes_alternatives_from_plan_requirements()
    {
        $fg = $this->createProduct('Box', 'FG-BOX', 'finished_good');
        $cardboard = $this->createProduct('Cardboard', 'RM-CB', 'raw_material');
        $altPlastic = $this->createProduct('Plastic Sheet', 'RM-PS', 'raw_material');

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-BOX-001',
            'bom_name' => 'Box BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $primary = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $cardboard->id,
            'quantity' => 3,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => false,
            'priority' => 1,
            'sequence' => 1,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $altPlastic->id,
            'quantity' => 3,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => true,
            'replaces_item_id' => $primary->id,
            'priority' => 1,
            'sequence' => 2,
        ]);

        $plan = \App\Domains\Production\Models\ProductionPlan::create([
            'tenant_id' => $this->tenant->id,
            'plan_number' => 'PLN-TEST-001',
            'name' => 'Test Box Plan',
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'quantity' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'draft',
        ]);

        $mrpService = app(MrpEngineService::class);
        $mrpService->runMrp($plan);

        $requirements = $plan->requirements()->get();

        // Only primary requirement should be generated
        $this->assertCount(1, $requirements);
        $this->assertEquals($cardboard->id, $requirements[0]->product_id);
        $this->assertEquals(30.0, (float)$requirements[0]->required_quantity);
    }

    /** @test */
    public function version_duplication_preserves_replaces_item_id_mapping()
    {
        $fg = $this->createProduct('Window', 'FG-WIN', 'finished_good');
        $glass = $this->createProduct('Clear Glass', 'RM-GLS', 'raw_material');
        $altTinted = $this->createProduct('Tinted Glass', 'RM-TGLS', 'raw_material');

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-WIN-001',
            'bom_name' => 'Window BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $primary = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $glass->id,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => false,
            'priority' => 1,
            'sequence' => 1,
        ]);

        $alt = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $altTinted->id,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 0,
            'is_alternative' => true,
            'replaces_item_id' => $primary->id,
            'priority' => 1,
            'sequence' => 2,
        ]);

        $bomService = app(ProductionBomService::class);
        $newBom = $bomService->duplicateVersion($bom->id, '2.0.0', $this->user->id);

        $this->assertCount(2, $newBom->items);
        $newPrimary = $newBom->items()->where('material_id', $glass->id)->first();
        $newAlt = $newBom->items()->where('material_id', $altTinted->id)->first();

        $this->assertNotEquals($primary->id, $newPrimary->id);
        $this->assertNotEquals($alt->id, $newAlt->id);

        $this->assertFalse((bool)$newPrimary->is_alternative);
        $this->assertNull($newPrimary->replaces_item_id);

        $this->assertTrue((bool)$newAlt->is_alternative);
        $this->assertEquals($newPrimary->id, $newAlt->replaces_item_id);
    }
}
