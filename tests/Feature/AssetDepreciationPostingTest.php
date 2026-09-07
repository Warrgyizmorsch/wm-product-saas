<?php

namespace Tests\Feature;

use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDepreciationPostingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Asset $asset;
    private AssetDepreciationService $service;

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

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $company = Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme Co']);

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
        $depreciationExpenseAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5800', 'name' => 'Depreciation Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $accumulatedDepreciationAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1510', 'name' => 'Accumulated Depreciation',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $category = AssetCategory::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'name' => 'Computer Equipment',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'depreciation_expense_account_id' => $depreciationExpenseAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedDepreciationAccount->id,
        ]);

        $this->asset = Asset::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'AST-DEP-1',
            'name' => 'Server',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 120000,
            'capitalization_cost' => 120000,
            'residual_value' => 12000,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->startOfYear()->toDateString(),
            'capitalization_date' => now()->startOfYear()->toDateString(),
            'accumulated_depreciation' => 0,
            'book_value' => 120000,
            'condition' => 'good',
            'status' => Asset::STATUS_ACTIVE,
        ]);

        $this->service = app(AssetDepreciationService::class);
    }

    /** @test */
    public function generate_review_approve_post_produces_a_balanced_journal_and_updates_the_asset(): void
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $schedule = $this->service->generateSchedule($this->asset, $year, $month, $this->user->id);
        $this->assertSame(AssetDepreciationSchedule::STATUS_DRAFT, $schedule->status);
        $this->assertEquals(3000.0, (float) $schedule->depreciation_amount); // (120000-12000)/36

        $schedule = $this->service->review($schedule, $this->user->id);
        $this->assertSame(AssetDepreciationSchedule::STATUS_REVIEWED, $schedule->status);

        $schedule = $this->service->approve($schedule, $this->user->id);
        $this->assertSame(AssetDepreciationSchedule::STATUS_APPROVED, $schedule->status);

        $schedule = $this->service->post($schedule, $this->user->id);
        $this->assertSame(AssetDepreciationSchedule::STATUS_POSTED, $schedule->status);
        $this->assertNotNull($schedule->journal_id);

        $journal = Journal::withoutGlobalScopes()->with('entries')->findOrFail($schedule->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
        $this->assertEquals(3000.0, (float) $journal->total_debit);

        $this->asset->refresh();
        $this->assertEquals(3000.0, (float) $this->asset->accumulated_depreciation);
        $this->assertEquals(117000.0, (float) $this->asset->book_value);
    }

    /** @test */
    public function posting_requires_the_schedule_to_be_approved_first(): void
    {
        $schedule = $this->service->generateSchedule($this->asset, (int) now()->year, (int) now()->month, $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->post($schedule, $this->user->id);
    }

    /** @test */
    public function generating_the_same_asset_and_period_twice_is_rejected(): void
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->service->generateSchedule($this->asset, $year, $month, $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateSchedule($this->asset, $year, $month, $this->user->id);
    }

    /** @test */
    public function the_database_unique_constraint_is_the_authoritative_duplicate_guard(): void
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->service->generateSchedule($this->asset, $year, $month, $this->user->id);

        $this->assertSame(1, AssetDepreciationSchedule::where('asset_id', $this->asset->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        AssetDepreciationSchedule::create([
            'company_id' => $this->asset->company_id,
            'asset_id' => $this->asset->id,
            'period_year' => $year,
            'period_month' => $month,
            'period_start_date' => now()->startOfMonth(),
            'period_end_date' => now()->endOfMonth(),
            'opening_book_value' => 120000,
            'depreciation_amount' => 3000,
            'closing_book_value' => 117000,
            'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'status' => AssetDepreciationSchedule::STATUS_DRAFT,
        ]);
    }
}
