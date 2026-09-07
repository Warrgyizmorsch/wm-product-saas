<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Policies\AssetPolicy;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms the fixed_assets.* permission namespace actually gates the new
 * capitalize/generate/post actions, and that it's independent from the
 * pre-existing hrms.assets.* namespace (an HR-only role should not be able
 * to capitalize or post depreciation just because it can allocate an asset).
 */
class AssetFixedAssetsRbacTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private AssetCategory $category;
    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->company = Company::create(['company_name' => 'Acme Co']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $fixedAssetAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1500', 'name' => 'Fixed Assets',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $this->category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Computer Equipment',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'default_useful_life_months' => 36,
        ]);

        $this->asset = Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-RBAC-1',
            'name' => 'Laptop',
            'purchase_date' => now()->toDateString(),
            'purchase_cost' => 60000,
            'condition' => 'new',
            'status' => Asset::STATUS_AVAILABLE,
        ]);
    }

    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        return $user;
    }

    /** @test */
    public function an_hr_manager_cannot_capitalize_an_asset_despite_holding_hrms_assets_permissions(): void
    {
        $hrManager = $this->createUserWithRole('hr@example.com', 'hr_manager');

        $this->assertFalse(app(AssetPolicy::class)->capitalize($hrManager, $this->asset));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        \Illuminate\Support\Facades\Gate::forUser($hrManager)->authorize('capitalize', $this->asset);
    }

    /** @test */
    public function an_accountant_can_capitalize_an_asset(): void
    {
        $accountant = $this->createUserWithRole('accountant@example.com', 'accountant');

        $this->assertTrue(app(AssetPolicy::class)->capitalize($accountant, $this->asset));

        $capitalized = app(AssetCapitalizationService::class)->capitalize($this->asset, [], $accountant->id);
        $this->assertSame(Asset::STATUS_ACTIVE, $capitalized->status);
    }

    /** @test */
    public function a_sales_executive_cannot_generate_or_post_depreciation(): void
    {
        $salesExec = $this->createUserWithRole('sales@example.com', 'sales_executive');

        $access = app(AccessService::class);
        $this->assertFalse($access->allows($salesExec, 'fixed_assets.depreciation.generate', ['tenant_id' => $this->tenant->id]));
        $this->assertFalse($access->allows($salesExec, 'fixed_assets.depreciation.post', ['tenant_id' => $this->tenant->id]));
    }

    /** @test */
    public function an_accountant_can_generate_and_post_depreciation(): void
    {
        $accountant = $this->createUserWithRole('accountant2@example.com', 'accountant');

        $access = app(AccessService::class);
        $this->assertTrue($access->allows($accountant, 'fixed_assets.depreciation.generate', ['tenant_id' => $this->tenant->id]));
        $this->assertTrue($access->allows($accountant, 'fixed_assets.depreciation.post', ['tenant_id' => $this->tenant->id]));
    }

    /** @test */
    public function tenant_owner_has_full_fixed_assets_access_via_the_blanket_grant(): void
    {
        $owner = $this->createUserWithRole('owner@example.com', 'tenant_owner');

        $access = app(AccessService::class);
        foreach ([
            'fixed_assets.assets.capitalize',
            'fixed_assets.depreciation.generate',
            'fixed_assets.depreciation.post',
            'fixed_assets.disposal.approve',
            'fixed_assets.writeoff.approve',
            'fixed_assets.revaluation.approve',
        ] as $permission) {
            $this->assertTrue($access->allows($owner, $permission, ['tenant_id' => $this->tenant->id]), "Expected tenant_owner to have {$permission}");
        }
    }
}
