<?php

namespace Tests\Feature;

use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCapitalizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private Company $company;
    private AssetCategory $category;

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
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Co',
        ]);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $fixedAssetAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1500',
            'name' => 'Fixed Assets',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->category = AssetCategory::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Computer Equipment',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'default_depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'default_useful_life_months' => 36,
            'default_residual_value_percent' => 10,
        ]);
    }

    private function makeAsset(float $purchaseCost = 60000): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-TEST-1',
            'name' => 'Dell Latitude',
            'purchase_date' => now()->toDateString(),
            'purchase_cost' => $purchaseCost,
            'condition' => 'new',
            'status' => Asset::STATUS_AVAILABLE,
        ]);
    }

    /** @test */
    public function capitalizing_an_asset_populates_financial_fields_using_category_defaults(): void
    {
        $asset = $this->makeAsset(60000);

        $capitalized = app(AssetCapitalizationService::class)->capitalize($asset, [
            'directly_attributable_cost' => 2000,
            'capitalization_date' => now()->toDateString(),
        ], $this->accountant->id);

        $this->assertSame(Asset::STATUS_ACTIVE, $capitalized->status);
        $this->assertEquals(62000.0, (float) $capitalized->capitalization_cost);
        $this->assertEquals(6200.0, (float) $capitalized->residual_value); // 10% of 62000
        $this->assertSame(36, $capitalized->useful_life_months);
        $this->assertSame(Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, $capitalized->depreciation_method);
        $this->assertEquals(62000.0, (float) $capitalized->book_value);
        $this->assertEquals(0.0, (float) $capitalized->accumulated_depreciation);
    }

    /** @test */
    public function capitalization_can_be_overridden_explicitly_instead_of_using_category_defaults(): void
    {
        $asset = $this->makeAsset(60000);

        $capitalized = app(AssetCapitalizationService::class)->capitalize($asset, [
            'useful_life_months' => 24,
            'residual_value' => 5000,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_WDV,
        ], $this->accountant->id);

        $this->assertSame(24, $capitalized->useful_life_months);
        $this->assertEquals(5000.0, (float) $capitalized->residual_value);
        $this->assertSame(Asset::DEPRECIATION_METHOD_WDV, $capitalized->depreciation_method);
    }

    /** @test */
    public function an_already_active_asset_cannot_be_capitalized_again(): void
    {
        $asset = $this->makeAsset(60000);
        app(AssetCapitalizationService::class)->capitalize($asset, ['useful_life_months' => 36], $this->accountant->id);

        $this->expectException(\InvalidArgumentException::class);
        app(AssetCapitalizationService::class)->capitalize($asset->fresh(), ['useful_life_months' => 36], $this->accountant->id);
    }

    /** @test */
    public function capitalization_requires_a_useful_life(): void
    {
        $asset = $this->makeAsset(60000);
        $category = $this->category;
        $category->update(['default_useful_life_months' => null]);

        $this->expectException(\InvalidArgumentException::class);
        app(AssetCapitalizationService::class)->capitalize($asset, [], $this->accountant->id);
    }
}
