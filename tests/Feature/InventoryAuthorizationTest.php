<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $inventoryManager;
    private User $salesExecutive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->inventoryManager = $this->createUserWithRole('inv@example.com', 'inventory_manager');
        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');
    }

    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    /** @test */
    public function guest_is_redirected_to_login_instead_of_reaching_inventory(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.products.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function a_role_without_inventory_permission_cannot_view_products(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.products.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function inventory_manager_can_view_and_create_products(): void
    {
        $index = $this->actingAs($this->inventoryManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.products.index'));

        $index->assertOk();

        $store = $this->actingAs($this->inventoryManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('inventory.products.store'), [
                'name' => 'Widget',
                'item_type' => 'Goods',
                'type' => 'finished_good',
                'variation_type' => 'Single',
                'sku' => 'WIDGET-1',
                'selling_price' => 10,
                'cost_price' => 5,
                'uom_id' => null,
                'hsn_sac' => null,
                'preferred_vendor_id' => null,
                'sales_account' => null,
                'purchase_account' => null,
                'inventory_account' => null,
                'description' => null,
                'brand' => null,
                'manufacturer' => null,
                'mpn' => null,
                'barcode' => null,
                'upc' => null,
                'ean' => null,
                'isbn' => null,
                'length' => null,
                'width' => null,
                'height' => null,
                'weight' => null,
                'dimension_unit' => null,
                'weight_unit' => null,
            ]);

        $store->assertRedirect(route('inventory.products.index'));
        $this->assertDatabaseHas('products', ['sku' => 'WIDGET-1', 'tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function a_role_without_inventory_permission_cannot_delete_a_product(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-2',
            'type' => 'finished_good',
            'item_type' => 'Goods',
            'variation_type' => 'Single',
            'status' => 'active',
            'selling_price' => 10,
            'cost_price' => 5,
            'unit_cost' => 5,
        ]);

        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('inventory.products.destroy', $product));

        $response->assertForbidden();
        $this->assertNotNull($product->fresh());
    }

    /** @test */
    public function inventory_manager_can_access_material_requirements(): void
    {
        $response = $this->actingAs($this->inventoryManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.requirements.index'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.inventory.requirements.index');
    }
}
