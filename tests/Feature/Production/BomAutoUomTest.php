<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomAutoUomTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uomKg;
    private Uom $uomPcs;
    private Product $finishedGood;
    private Product $rawMaterial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Manufacturing Test Tenant',
            'slug' => 'test-mfg-' . uniqid(),
            'domain' => 'test-mfg-' . uniqid() . '.localhost',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Manager',
            'email' => 'pm@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager',
        ]);

        $this->uomKg = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kilogram',
            'code' => 'KG',
            'unit_type' => 'weight',
            'ratio' => 1.0,
            'is_active' => true,
        ]);

        $this->uomPcs = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'unit_type' => 'unit',
            'ratio' => 1.0,
            'is_active' => true,
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Table',
            'sku' => 'FG-TBL-001',
            'type' => 'finished_good',
            'item_type' => 'Goods',
            'variation_type' => 'Single',
            'status' => 'active',
            'uom_id' => $this->uomPcs->id,
            'selling_price' => 200,
            'cost_price' => 100,
            'unit_cost' => 100,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Bar',
            'sku' => 'RM-BAR-001',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'variation_type' => 'Single',
            'status' => 'active',
            'uom_id' => $this->uomKg->id,
            'selling_price' => 50,
            'cost_price' => 20,
            'unit_cost' => 20,
        ]);
    }

    public function test_bom_create_and_update_pages_render_locked_uom_fields(): void
    {
        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.boms.create', ['product_id' => $this->finishedGood->id]));
        $response->assertOk();
        $response->assertSee('id="base_uom_id_display"', false);
        $response->assertSee('disabled', false);
        $response->assertSee('bom-row-uom-select', false);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TBL-V1',
            'bom_name' => 'Table BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'draft',
            'effective_date' => now()->toDateString(),
        ]);

        $bom->items()->create([
            'tenant_id' => $this->tenant->id,
            'material_id' => $this->rawMaterial->id,
            'quantity' => 4.0,
            'uom_id' => $this->uomKg->id,
            'priority' => 1,
        ]);

        $editResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.boms.edit', $bom->id));
        $editResponse->assertOk();
        $editResponse->assertSee('id="base_uom_id_display"', false);
        $editResponse->assertSee('bom-row-uom-select', false);
    }

    public function test_bom_store_auto_populates_uoms_from_product_master_when_omitted(): void
    {
        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);

        $payload = [
            'bom_number' => 'BOM-AUTO-UOM-001',
            'bom_name' => 'Auto UOM Test',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            // base_uom_id omitted on purpose to test server auto-resolution
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial->id,
                    'quantity' => 2.5,
                    // uom_id omitted on purpose to test server auto-resolution
                    'priority' => 1,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.boms.store'), $payload);
        $response->assertRedirect();

        $bom = ProductionBom::where('bom_number', 'BOM-AUTO-UOM-001')->first();
        $this->assertNotNull($bom);
        $this->assertEquals($this->uomPcs->id, $bom->base_uom_id);

        $bomItem = $bom->items()->first();
        $this->assertNotNull($bomItem);
        $this->assertEquals($this->uomKg->id, $bomItem->uom_id);
    }

    public function test_bom_update_auto_populates_uoms_from_product_master(): void
    {
        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-UPDATE-001',
            'bom_name' => 'Initial Draft',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'draft',
            'effective_date' => now()->toDateString(),
        ]);

        $updatePayload = [
            'bom_number' => 'BOM-UPDATE-001',
            'bom_name' => 'Updated BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 2.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial->id,
                    'quantity' => 5.0,
                    'priority' => 1,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->put(route('production.boms.update', $bom->id), $updatePayload);
        $response->assertRedirect();

        $bom->refresh();
        $this->assertEquals($this->uomPcs->id, $bom->base_uom_id);

        $bomItem = $bom->items()->first();
        $this->assertNotNull($bomItem);
        $this->assertEquals($this->uomKg->id, $bomItem->uom_id);
    }
}
