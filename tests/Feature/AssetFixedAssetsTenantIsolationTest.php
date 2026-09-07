<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-tenant access must be blocked at the query/scope level for all new
 * Fixed Asset tables — mirrors the pattern in
 * tests/Feature/HRMS/HrmsTenantIsolationTest.php.
 */
class AssetFixedAssetsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Asset $assetA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'plan' => 'enterprise']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'plan' => 'enterprise']);

        app(TenantContext::class)->set($this->tenantA);
        $companyA = Company::create(['company_name' => 'Company A']);
        $categoryA = AssetCategory::create(['company_id' => $companyA->id, 'name' => 'Category A']);
        $this->assetA = Asset::create([
            'company_id' => $companyA->id,
            'asset_category_id' => $categoryA->id,
            'asset_code' => 'AST-TENANT-A-1',
            'name' => 'Tenant A Laptop',
            'purchase_date' => now()->toDateString(),
            'purchase_cost' => 50000,
            'capitalization_cost' => 50000,
            'residual_value' => 5000,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->toDateString(),
            'accumulated_depreciation' => 0,
            'book_value' => 50000,
            'condition' => 'new',
            'status' => Asset::STATUS_ACTIVE,
        ]);

        AssetDepreciationSchedule::create([
            'company_id' => $companyA->id,
            'asset_id' => $this->assetA->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'period_start_date' => now()->startOfMonth(),
            'period_end_date' => now()->endOfMonth(),
            'opening_book_value' => 50000,
            'depreciation_amount' => 1250,
            'closing_book_value' => 48750,
            'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'status' => AssetDepreciationSchedule::STATUS_DRAFT,
        ]);

        AssetDisposal::create([
            'company_id' => $companyA->id,
            'asset_id' => $this->assetA->id,
            'disposal_type' => 'scrap',
            'disposal_date' => now()->toDateString(),
            'original_cost' => 50000,
            'accumulated_depreciation_at_disposal' => 0,
            'net_book_value' => 50000,
            'status' => AssetDisposal::STATUS_DRAFT,
        ]);
    }

    /** @test */
    public function tenant_b_cannot_see_tenant_as_asset(): void
    {
        app(TenantContext::class)->set($this->tenantB);

        $this->assertNull(Asset::find($this->assetA->id));
        $this->assertSame(0, Asset::where('asset_code', 'AST-TENANT-A-1')->count());
    }

    /** @test */
    public function tenant_b_cannot_see_tenant_as_depreciation_schedules(): void
    {
        app(TenantContext::class)->set($this->tenantB);

        $this->assertSame(0, AssetDepreciationSchedule::where('asset_id', $this->assetA->id)->count());
    }

    /** @test */
    public function tenant_b_cannot_see_tenant_as_disposals(): void
    {
        app(TenantContext::class)->set($this->tenantB);

        $this->assertSame(0, AssetDisposal::where('asset_id', $this->assetA->id)->count());
    }

    /** @test */
    public function tenant_a_can_still_see_its_own_records(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        $this->assertNotNull(Asset::find($this->assetA->id));
        $this->assertSame(1, AssetDepreciationSchedule::where('asset_id', $this->assetA->id)->count());
        $this->assertSame(1, AssetDisposal::where('asset_id', $this->assetA->id)->count());
    }
}
