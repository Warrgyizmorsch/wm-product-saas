<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $salesExecA;
    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Industries',
            'slug' => 'beta-ind',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(TenantContext::class)->set($this->tenantA);

        $this->ownerA = $this->makeUser($this->tenantA, 'owner@acme.test', 'tenant_owner');
        $this->salesExecA = $this->makeUser($this->tenantA, 'sales@acme.test', 'sales_executive');

        app(TenantContext::class)->set($this->tenantB);
        $this->ownerB = $this->makeUser($this->tenantB, 'owner@beta.test', 'tenant_owner');

        // Reset context back to Tenant A for tests
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);
    }

    private function makeUser(Tenant $tenant, string $email, ?string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $user;
    }

    /** @test */
    public function unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/global-search?q=test');
        $response->assertStatus(401);
    }

    /** @test */
    public function query_validation_returns_empty_results_for_short_or_empty_queries(): void
    {
        $this->actingAs($this->ownerA);

        $responseEmpty = $this->getJson('/global-search?q=');
        $responseEmpty->assertOk()
            ->assertJson([
                'query' => '',
                'module' => 'all',
                'total' => 0,
                'results' => [],
            ]);

        $responseOneChar = $this->getJson('/global-search?q=a');
        $responseOneChar->assertOk()
            ->assertJson([
                'query' => 'a',
                'module' => 'all',
                'total' => 0,
                'results' => [],
            ]);
    }

    /** @test */
    public function strict_tenant_isolation_prevents_tenant_a_from_seeing_tenant_b_records(): void
    {
        // Create matching records in both tenants
        app(TenantContext::class)->set($this->tenantA);
        Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Alpha Client',
            'company_name' => 'Acme Alpha Client Ltd',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($this->tenantB);
        Customer::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Acme Beta Client',
            'company_name' => 'Acme Beta Client Ltd',
            'status' => 'active',
        ]);

        // Search as Tenant A Owner
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/global-search?q=Acme&module=crm');
        $response->assertOk();

        $titles = collect($response->json('results'))->pluck('title')->toArray();

        $this->assertContains('Acme Alpha Client Ltd', $titles);
        $this->assertNotContains('Acme Beta Client Ltd', $titles);

        // Search as Tenant B Owner
        app(TenantContext::class)->set($this->tenantB);
        $this->withHeaders(['X-Tenant' => $this->tenantB->slug]);
        $this->actingAs($this->ownerB);

        $responseB = $this->getJson('/global-search?q=Acme&module=crm');
        $responseB->assertOk();

        $titlesB = collect($responseB->json('results'))->pluck('title')->toArray();
        $this->assertContains('Acme Beta Client Ltd', $titlesB);
        $this->assertNotContains('Acme Alpha Client Ltd', $titlesB);
    }

    /** @test */
    public function rbac_excludes_records_from_unauthorized_domains(): void
    {
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'sku' => 'PROD-BASE-01',
            'name' => 'Base Manufacturing Item',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        // Create a production order
        ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'order_number' => 'PRD-ORD-9999',
            'batch_number' => 'BATCH-9999',
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'confirmed',
        ]);

        // Sales executive does NOT have production order permissions
        $this->actingAs($this->salesExecA);

        $response = $this->getJson('/global-search?q=PRD-ORD&module=production');
        $response->assertOk()
            ->assertJson([
                'total' => 0,
                'results' => [],
            ]);

        // Tenant owner DOES have production order permissions
        $this->actingAs($this->ownerA);

        $responseOwner = $this->getJson('/global-search?q=PRD-ORD&module=production');
        $responseOwner->assertOk();
        $this->assertGreaterThanOrEqual(1, $responseOwner->json('total'));
        $this->assertEquals('PRD-ORD-9999', $responseOwner->json('results.0.title'));
    }

    /** @test */
    public function navigation_search_returns_permitted_menu_items(): void
    {
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);
        $this->actingAs($this->ownerA);

        $response = $this->getJson('/global-search?q=Orders&module=navigation');
        $response->assertOk();

        $categories = collect($response->json('results'))->pluck('category')->unique()->toArray();
        $this->assertContains('Navigation', $categories);

        $urls = collect($response->json('results'))->pluck('url')->toArray();
        foreach ($urls as $url) {
            $this->assertNotEmpty($url);
        }
    }

    /** @test */
    public function module_filtering_restricts_results_to_the_requested_domain(): void
    {
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);

        // Create a product and a customer with overlapping text "GlobalAlpha"
        Product::create([
            'tenant_id' => $this->tenantA->id,
            'sku' => 'GA-SKU-001',
            'name' => 'GlobalAlpha Gadget',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'GlobalAlpha Logistics',
            'company_name' => 'GlobalAlpha Logistics Ltd',
            'status' => 'active',
        ]);

        $this->actingAs($this->ownerA);

        // Search Inventory only
        $invResponse = $this->getJson('/global-search?q=GlobalAlpha&module=inventory');
        $invResponse->assertOk();
        $invCategories = collect($invResponse->json('results'))->pluck('category')->unique()->toArray();

        $this->assertContains('Products', $invCategories);
        $this->assertNotContains('Customers', $invCategories);

        // Search CRM only
        $crmResponse = $this->getJson('/global-search?q=GlobalAlpha&module=crm');
        $crmResponse->assertOk();
        $crmCategories = collect($crmResponse->json('results'))->pluck('category')->unique()->toArray();

        $this->assertContains('Customers', $crmCategories);
        $this->assertNotContains('Products', $crmCategories);
    }

    /** @test */
    public function result_counts_are_strictly_bounded(): void
    {
        app(TenantContext::class)->set($this->tenantA);
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug]);

        // Seed 10 products
        for ($i = 1; $i <= 10; $i++) {
            Product::create([
                'tenant_id' => $this->tenantA->id,
                'sku' => sprintf('BULK-SKU-%03d', $i),
                'name' => 'Bulk Product ' . $i,
                'type' => 'finished_good',
                'status' => 'active',
            ]);
        }

        $this->actingAs($this->ownerA);

        // In module=all, maximum per entity is 3
        $allResponse = $this->getJson('/global-search?q=BULK-SKU&module=all');
        $allResponse->assertOk();
        $this->assertLessThanOrEqual(3, $allResponse->json('total'));

        // In specific module mode, maximum per entity is 8
        $moduleResponse = $this->getJson('/global-search?q=BULK-SKU&module=inventory');
        $moduleResponse->assertOk();
        $this->assertLessThanOrEqual(8, $moduleResponse->json('total'));
    }
}
