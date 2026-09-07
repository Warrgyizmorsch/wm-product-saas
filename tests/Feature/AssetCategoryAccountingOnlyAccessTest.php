<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\Platform\Models\Plan;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCategoryAccountingOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // A plan that includes Accounting/Purchase but NOT HRMS — the exact
        // scenario that exposed the gap: every Fixed Asset workflow screen
        // works, but Asset Category management (HRMS-gated) does not.
        $plan = Plan::create([
            'name' => 'Accounting Only',
            'slug' => 'accounting-only-test',
            'price' => 0,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'features' => ['accounting', 'purchase'],
            'is_active' => true,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Accounting Only Tenant',
            'slug' => 'accounting-only-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
            'plan_id' => $plan->id,
        ]);

        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant',
            'email' => 'accountant@accounting-only.example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Co',
        ]);

        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1500',
            'name' => 'Fixed Assets',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    public function test_hrms_asset_screen_is_blocked_for_an_accounting_only_plan(): void
    {
        $response = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'accounting-only-tenant')
            ->get('/hrms/assets');

        $response->assertForbidden();
    }

    public function test_accounting_gated_category_screen_is_reachable_and_usable(): void
    {
        $index = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'accounting-only-tenant')
            ->get(route('accounting.fixed-assets.categories.index'));
        $index->assertOk();

        $store = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'accounting-only-tenant')
            ->post(route('accounting.fixed-assets.categories.store'), [
                'company_id' => $this->company->id,
                'name' => 'Plant Machinery',
                'default_useful_life_months' => 60,
            ]);
        $store->assertRedirect(route('accounting.fixed-assets.categories.index'));
        $this->assertDatabaseHas('asset_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Plant Machinery',
        ]);
    }

    public function test_full_lifecycle_without_touching_any_hrms_route(): void
    {
        $category = AssetCategory::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Plant Machinery',
        ]);

        $register = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'accounting-only-tenant')
            ->post(route('accounting.fixed-assets.register.store'), [
                'asset_category_id' => $category->id,
                'name' => 'CNC Lathe Machine',
                'asset_code' => 'AST-CNC-001',
                'serial_number' => 'SN-CNC-001',
                'condition' => 'new',
                'purchase_cost' => 500000,
            ]);
        $register->assertRedirect();

        $asset = \App\Domains\HRMS\Models\Asset::where('asset_code', 'AST-CNC-001')->firstOrFail();
        $this->assertEquals($this->tenant->id, $asset->tenant_id);
        $this->assertEquals('available', $asset->status);

        $capitalize = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'accounting-only-tenant')
            ->post(route('accounting.fixed-assets.capitalize', $asset), [
                'acquisition_cost' => 500000,
                'useful_life_months' => 60,
                'depreciation_method' => 'straight_line',
            ]);
        $capitalize->assertRedirect(route('accounting.fixed-assets.show', $asset));

        $asset->refresh();
        $this->assertEquals('active', $asset->status);
        $this->assertEquals(500000, (float) $asset->book_value);
    }
}
