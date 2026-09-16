<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\Routing;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\UserPermissionOverride;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $managerA;
    private User $engineerA;
    private User $salesExecA;
    private User $managerB;
    private Product $productA;
    private Uom $uomA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        // Tenant A with enterprise plan (all modules including production)
        $this->tenantA = Tenant::create([
            'name' => 'Acme Manufacturing',
            'slug' => 'acme-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Tenant B with enterprise plan
        $this->tenantB = Tenant::create([
            'name' => 'Beta Industries',
            'slug' => 'beta-ind',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Tenant A Users
        app(TenantContext::class)->set($this->tenantA);

        $this->managerA = $this->makeUser($this->tenantA, 'prod_mgr@acme.test', 'production_manager');
        $this->engineerA = $this->makeUser($this->tenantA, 'prod_eng@acme.test', 'production_engineer');
        $this->salesExecA = $this->makeUser($this->tenantA, 'sales@acme.test', 'sales_executive');

        $this->uomA = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'PCS',
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Hydraulic Pump Assembly',
            'sku' => 'PUMP-001',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uomA->id,
        ]);

        // Tenant B User
        app(TenantContext::class)->set($this->tenantB);
        $this->managerB = $this->makeUser($this->tenantB, 'prod_mgr@beta.test', 'production_manager');

        // Reset to Tenant A
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
            $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->first();
            if ($role) {
                UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'tenant_id' => $tenant->id,
                ]);
            }
        }

        return $user;
    }

    /** @test */
    public function unauthenticated_user_cannot_access_the_approval_endpoint(): void
    {
        $response = $this->getJson('/global-approvals');
        $response->assertStatus(401);
    }

    /** @test */
    public function strict_tenant_isolation_prevents_tenant_a_approvals_from_appearing_for_tenant_b_user(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ACME-001',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        // Tenant A manager sees 1 pending approval
        $this->actingAs($this->managerA);
        $responseA = $this->getJson('/global-approvals');
        $responseA->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.title', 'BOM-ACME-001');

        // Tenant B manager (with same role/permission) queries with Tenant B context
        app(TenantContext::class)->set($this->tenantB);
        $this->withHeaders(['X-Tenant' => $this->tenantB->slug]);
        $this->actingAs($this->managerB);

        $responseB = $this->getJson('/global-approvals');
        $responseB->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('items', []);
    }

    /** @test */
    public function user_without_production_approval_permission_receives_zero_approvals(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ACME-002',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        // Sales executive has NO production approval permissions
        $this->actingAs($this->salesExecA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJson([
                'count' => 0,
                'items' => [],
            ]);

        // Production engineer has production.bom.create but NOT production.bom.approve
        $this->actingAs($this->engineerA);
        $responseEng = $this->getJson('/global-approvals');

        $responseEng->assertOk()
            ->assertJson([
                'count' => 0,
                'items' => [],
            ]);
    }

    /** @test */
    public function module_gating_returns_zero_when_production_module_is_disabled_for_tenant(): void
    {
        // Create tenant with starter plan (Production module not included)
        $tenantStarter = Tenant::create([
            'name' => 'Starter Co',
            'slug' => 'starter-co',
            'status' => 'active',
            'plan' => 'starter', // starter plan does not have production module
        ]);

        app(TenantContext::class)->set($tenantStarter);
        $starterUser = $this->makeUser($tenantStarter, 'starter@user.test', 'production_manager');

        $this->withHeaders(['X-Tenant' => $tenantStarter->slug]);
        $this->actingAs($starterUser);

        $response = $this->getJson('/global-approvals');
        $response->assertOk()
            ->assertJson([
                'count' => 0,
                'items' => [],
            ]);
    }

    /** @test */
    public function current_approver_sees_all_types_of_pending_actionable_production_approvals(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        // 1. Pending BOM
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-2024-001',
            'bom_name' => 'Standard Pump Assembly',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        // 2. Pending Routing
        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'CNC Milling Routing',
            'status' => Routing::STATUS_PENDING_APPROVAL,
        ]);

        // 3. Pending Production Plan
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenantA->id,
            'plan_number' => 'PP-2024-001',
            'name' => 'Monthly Production Plan Q4',
            'product_id' => $this->productA->id,
            'status' => ProductionPlan::STATUS_PENDING_APPROVAL,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        // 4. Pending Scrap Disposal
        $scrap = ProductionScrapDisposal::create([
            'tenant_id' => $this->tenantA->id,
            'category' => 'Defective Casting',
            'reason_code' => 'POROSITY',
            'quantity' => 5,
            'status' => 'pending_approval',
        ]);

        // 5. Pending ECO (under_review)
        $eco = ProductionEco::create([
            'tenant_id' => $this->tenantA->id,
            'eco_number' => 'ECO-2024-001',
            'title' => 'Increase pump housing wall thickness',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id' => $this->productA->id,
            'status' => ProductionEco::STATUS_UNDER_REVIEW,
        ]);

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJsonPath('count', 5);

        $items = $response->json('items');
        $this->assertCount(5, $items);

        $types = array_column($items, 'type');
        $this->assertContains('BOM', $types);
        $this->assertContains('Routing', $types);
        $this->assertContains('Production Plan', $types);
        $this->assertContains('Scrap Disposal', $types);
        $this->assertContains('ECO', $types);
    }

    /** @test */
    public function user_with_permission_override_denial_cannot_see_the_approval(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-OVERRIDE-01',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        // Add explicit permission override denying BOM approval to managerA
        $bomApprovePerm = Permission::query()->where('name', 'production.bom.approve')->firstOrFail();
        UserPermissionOverride::create([
            'user_id' => $this->managerA->id,
            'permission_id' => $bomApprovePerm->id,
            'allowed' => false,
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('items', []);
    }

    /** @test */
    public function already_approved_or_rejected_or_draft_items_do_not_appear_as_actionable_approvals(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        // Draft BOM
        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-DRAFT',
            'product_id' => $this->productA->id,
            'status' => 'draft',
            'effective_date' => now()->toDateString(),
        ]);

        // Approved BOM
        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-APPROVED',
            'product_id' => $this->productA->id,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        // Inactive BOM
        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-INACTIVE',
            'product_id' => $this->productA->id,
            'status' => 'inactive',
            'effective_date' => now()->toDateString(),
        ]);

        // Active Routing (already approved)
        Routing::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Active Routing',
            'status' => 'active',
        ]);

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJson([
                'count' => 0,
                'items' => [],
            ]);
    }

    /** @test */
    public function maximum_header_result_limit_is_respected_while_count_reflects_total_actionable(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        // Create 8 pending BOMs
        for ($i = 1; $i <= 8; $i++) {
            ProductionBom::create([
                'tenant_id' => $this->tenantA->id,
                'bom_number' => "BOM-BULK-00{$i}",
                'product_id' => $this->productA->id,
                'status' => 'pending_approval',
                'effective_date' => now()->toDateString(),
            ]);
        }

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk();
        // Count must show all 8 actionable approvals
        $this->assertEquals(8, $response->json('count'));
        // Items must be bounded to maximum 5
        $this->assertCount(5, $response->json('items'));
    }

    /** @test */
    public function returned_approval_urls_resolve_to_valid_existing_routes(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ROUTE-01',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk();
        $item = $response->json('items.0');

        $expectedUrl = route('production.boms.show', $bom->id);
        $this->assertEquals($expectedUrl, $item['url']);
    }

    /** @test */
    public function response_strictly_conforms_to_the_json_contract(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CONTRACT-01',
            'bom_name' => 'Contract Test BOM',
            'product_id' => $this->productA->id,
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->managerA);
        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJsonStructure([
                'count',
                'items' => [
                    '*' => [
                        'module',
                        'type',
                        'title',
                        'subtitle',
                        'url',
                        'icon',
                        'time',
                    ],
                ],
            ]);
    }
}
