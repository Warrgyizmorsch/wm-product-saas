<?php

namespace Tests\Feature\Api\Production;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private ProductionOrder $orderA;
    private ProductionBom $bomA;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();

        // Setup Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Operator',
            'email' => 'alpha@test.com',
            'password' => 'password',
        ]);
        UserRole::create([
            'user_id' => $this->userA->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        // Setup Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Operator',
            'email' => 'beta@test.com',
            'password' => 'password',
        ]);
        UserRole::create([
            'user_id' => $this->userB->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        // Setup Tenant A inventory and records
        $uomA = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);
        $productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Widget',
            'sku' => 'WGT-A',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $uomA->id,
        ]);
        $this->bomA = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-A-001',
            'bom_name' => 'Alpha BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $uomA->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $this->userA->id,
        ]);
        $this->orderA = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-A-1001',
            'product_id' => $productA->id,
            'bom_id' => $this->bomA->id,
            'quantity_ordered' => 10,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'status' => 'draft',
            'created_by' => $this->userA->id,
        ]);
    }

    private function headers(User $user, ?string $tenantSlug = null): array
    {
        $slug = $tenantSlug ?? ($user->id === $this->userB->id ? $this->tenantB->slug : $this->tenantA->slug);

        return [
            'Accept' => 'application/json',
            'X-Tenant' => $slug,
            'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $user->createToken('test-token')->plainTextToken,
        ];
    }

    public function test_tenant_b_cannot_access_tenant_a_production_order(): void
    {
        // Tenant B requests Tenant A's order ID
        $response = $this->withHeaders($this->headers($this->userB))
            ->getJson('/api/v1/production/orders/' . $this->orderA->id);

        $response->assertStatus(404);
    }

    public function test_tenant_b_cannot_access_tenant_a_bom(): void
    {
        // Tenant B requests Tenant A's BOM ID
        $response = $this->withHeaders($this->headers($this->userB))
            ->getJson('/api/v1/production/boms/' . $this->bomA->id);

        $response->assertStatus(404);
    }

    public function test_tenant_header_mismatch_triggers_cross_tenant_denial(): void
    {
        // User A attempts to pass Tenant B's slug in X-Tenant header
        $response = $this->withHeaders($this->headers($this->userA, 'tenant-beta'))
            ->getJson('/api/v1/production/orders');

        // Blocked at authentication/isolation boundary (401 from TenantAwareUserProvider or 403 from middleware)
        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }

    public function test_resources_do_not_leak_tenant_id_or_internal_keys(): void
    {
        $response = $this->withHeaders($this->headers($this->userA))
            ->getJson('/api/v1/production/orders/' . $this->orderA->id);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('tenant_id', $data);
        $this->assertArrayNotHasKey('deleted_at', $data);
        $this->assertArrayNotHasKey('created_by', $data);
    }
}
