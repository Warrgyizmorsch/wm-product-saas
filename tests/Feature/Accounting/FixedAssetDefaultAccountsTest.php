<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The fixed-asset services fall back to fixed account codes when an asset
 * category has no accounts of its own. Those codes must exist in the default
 * chart — depreciation used to look for 5800, which it never seeded.
 */
class FixedAssetDefaultAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const FIXED_ASSET_CODES = ['3200' => 'equity', '4940' => 'income', '5910' => 'expense', '5920' => 'expense'];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
    }

    public function test_default_chart_has_every_account_the_fixed_asset_services_fall_back_to(): void
    {
        foreach (self::FIXED_ASSET_CODES + ['1500' => 'asset', '1510' => 'asset', '5400' => 'expense'] as $code => $type) {
            $account = ChartOfAccount::query()->where('tenant_id', $this->tenant->id)->where('code', $code)->first();

            $this->assertNotNull($account, "Account {$code} is missing from the default chart.");
            $this->assertSame($type, $account->type, "Account {$code} has the wrong type.");
        }
    }

    public function test_depreciation_posts_to_the_default_chart_when_the_category_has_no_accounts(): void
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@acme.test', 'password' => bcrypt('password')]);
        $company = Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme Co']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $category = AssetCategory::create(['tenant_id' => $this->tenant->id, 'company_id' => $company->id, 'name' => 'Laptops']);
        $asset = Asset::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $company->id, 'asset_category_id' => $category->id,
            'asset_code' => 'AST-1', 'name' => 'Laptop', 'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 36000, 'capitalization_cost' => 36000, 'residual_value' => 0, 'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->startOfYear()->toDateString(),
            'capitalization_date' => now()->startOfYear()->toDateString(),
            'accumulated_depreciation' => 0, 'book_value' => 36000, 'condition' => 'good', 'status' => Asset::STATUS_ACTIVE,
        ]);

        $service = app(AssetDepreciationService::class);
        $schedule = $service->generateSchedule($asset, (int) now()->year, (int) now()->month, $user->id);
        $schedule = $service->post($service->approve($service->review($schedule, $user->id), $user->id), $user->id);

        $codes = Journal::withoutGlobalScopes()->with('entries.account')->findOrFail($schedule->journal_id)
            ->entries->mapWithKeys(fn ($entry) => [$entry->account->code => (float) $entry->debit - (float) $entry->credit]);

        $this->assertEquals(['5400' => 1000.0, '1510' => -1000.0], $codes->all());
    }

    public function test_backfill_migration_adds_missing_accounts_to_existing_charts_only(): void
    {
        DB::table('chart_of_accounts')->where('tenant_id', $this->tenant->id)->whereIn('code', array_keys(self::FIXED_ASSET_CODES))->delete();
        $renamed = ChartOfAccount::query()->where('tenant_id', $this->tenant->id)->where('code', '5400')->first();
        $renamed->update(['name' => 'Depreciation (renamed)']);
        $withoutChart = Tenant::create(['name' => 'Empty', 'slug' => 'empty', 'status' => 'active', 'plan' => 'enterprise']);

        (require database_path('migrations/2026_09_15_150000_backfill_fixed_asset_accounts.php'))->up();

        foreach (array_keys(self::FIXED_ASSET_CODES) as $code) {
            $this->assertDatabaseHas('chart_of_accounts', ['tenant_id' => $this->tenant->id, 'code' => $code, 'is_system' => true]);
        }
        $this->assertSame('Depreciation (renamed)', $renamed->fresh()->name);
        $this->assertDatabaseMissing('chart_of_accounts', ['tenant_id' => $withoutChart->id]);
    }
}
