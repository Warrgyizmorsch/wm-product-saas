<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Depreciation posting must respect accounting period locks — inherited "for
 * free" via JournalService::post() -> FiscalPeriodService::assertOpenPeriodForDate(),
 * so this test mainly proves that guard isn't bypassed by the Fixed Asset
 * posting path.
 */
class AssetAccountingPeriodValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posting_depreciation_into_a_closed_period_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise']);

        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($tenant);

        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password')]);
        $company = Company::create(['company_name' => 'Acme Co']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        // Close the current period.
        AccountingPeriod::where('tenant_id', $tenant->id)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->update(['status' => 'closed']);

        $fixedAssetAccount = ChartOfAccount::create([
            'tenant_id' => $tenant->id, 'code' => '1500', 'name' => 'Fixed Assets',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $depreciationExpenseAccount = ChartOfAccount::create([
            'tenant_id' => $tenant->id, 'code' => '5800', 'name' => 'Depreciation Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $accumulatedDepreciationAccount = ChartOfAccount::create([
            'tenant_id' => $tenant->id, 'code' => '1510', 'name' => 'Accumulated Depreciation',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $category = AssetCategory::create([
            'company_id' => $company->id,
            'name' => 'Computer Equipment',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'depreciation_expense_account_id' => $depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedDepreciationAccount->id,
        ]);

        $asset = Asset::create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'AST-PERIOD-1',
            'name' => 'Server',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 36000,
            'capitalization_cost' => 36000,
            'residual_value' => 0,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->startOfYear()->toDateString(),
            'accumulated_depreciation' => 0,
            'book_value' => 36000,
            'condition' => 'good',
            'status' => Asset::STATUS_ACTIVE,
        ]);

        $service = app(AssetDepreciationService::class);
        $schedule = $service->generateSchedule($asset, (int) now()->year, (int) now()->month, $user->id);
        $schedule = $service->review($schedule, $user->id);
        $schedule = $service->approve($schedule, $user->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->post($schedule, $user->id);
    }
}
