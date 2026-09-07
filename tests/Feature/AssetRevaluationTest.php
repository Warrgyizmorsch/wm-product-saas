<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Services\AssetRevaluationService;
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

class AssetRevaluationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private AssetCategory $category;
    private Company $company;
    private Asset $asset;
    private AssetRevaluationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password'),
        ]);

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
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '3200', 'name' => 'Revaluation Reserve',
            'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5920', 'name' => 'Impairment Loss',
            'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $this->category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Land & Buildings',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
        ]);

        $this->asset = Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-REV-1',
            'name' => 'Warehouse',
            'purchase_date' => now()->subYears(3)->toDateString(),
            'purchase_cost' => 500000,
            'acquisition_cost' => 500000,
            'capitalization_cost' => 500000,
            'residual_value' => 50000,
            'useful_life_months' => 240,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->subYears(3)->toDateString(),
            'capitalization_date' => now()->subYears(3)->toDateString(),
            'accumulated_depreciation' => 30000,
            'book_value' => 470000,
            'condition' => 'good',
            'status' => Asset::STATUS_ACTIVE,
        ]);

        $this->service = app(AssetRevaluationService::class);
    }

    /** @test */
    public function a_surplus_revaluation_posts_a_balanced_journal_and_leaves_acquisition_cost_untouched(): void
    {
        $revaluation = $this->service->revalue($this->asset, [
            'revaluation_date' => now()->toDateString(),
            'revalued_amount' => 600000,
            'reason' => 'Market appraisal shows significant appreciation.',
        ], $this->user->id);

        $this->assertEquals(130000.0, (float) $revaluation->revaluation_surplus_deficit); // 600000 - 470000

        $revaluation = $this->service->approve($revaluation, $this->user->id);
        $revaluation = $this->service->post($revaluation, $this->user->id);

        $journal = Journal::withoutGlobalScopes()->findOrFail($revaluation->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
        $this->assertEquals(130000.0, (float) $journal->total_debit);

        $this->asset->refresh();
        $this->assertEquals(600000.0, (float) $this->asset->capitalization_cost);
        $this->assertEquals(600000.0, (float) $this->asset->book_value);
        $this->assertEquals(500000.0, (float) $this->asset->acquisition_cost); // never touched
    }

    /** @test */
    public function a_deficit_revaluation_posts_an_impairment_loss(): void
    {
        $revaluation = $this->service->revalue($this->asset, [
            'revaluation_date' => now()->toDateString(),
            'revalued_amount' => 400000,
            'reason' => 'Impairment due to structural damage.',
        ], $this->user->id);

        $this->assertEquals(-70000.0, (float) $revaluation->revaluation_surplus_deficit); // 400000 - 470000

        $revaluation = $this->service->approve($revaluation, $this->user->id);
        $revaluation = $this->service->post($revaluation, $this->user->id);

        $journal = Journal::withoutGlobalScopes()->findOrFail($revaluation->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);

        $this->asset->refresh();
        $this->assertEquals(400000.0, (float) $this->asset->capitalization_cost);
        $this->assertEquals(500000.0, (float) $this->asset->acquisition_cost); // never touched
    }

    /** @test */
    public function posting_a_revaluation_regenerates_future_draft_schedules_but_leaves_posted_ones_alone(): void
    {
        $postedSchedule = AssetDepreciationSchedule::create([
            'company_id' => $this->company->id,
            'asset_id' => $this->asset->id,
            'period_year' => now()->subMonth()->year,
            'period_month' => now()->subMonth()->month,
            'period_start_date' => now()->subMonth()->startOfMonth(),
            'period_end_date' => now()->subMonth()->endOfMonth(),
            'opening_book_value' => 470000,
            'depreciation_amount' => 1750,
            'closing_book_value' => 468250,
            'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'status' => AssetDepreciationSchedule::STATUS_POSTED,
        ]);

        $draftSchedule = AssetDepreciationSchedule::create([
            'company_id' => $this->company->id,
            'asset_id' => $this->asset->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'period_start_date' => now()->startOfMonth(),
            'period_end_date' => now()->endOfMonth(),
            'opening_book_value' => 468250,
            'depreciation_amount' => 1750,
            'closing_book_value' => 466500,
            'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'status' => AssetDepreciationSchedule::STATUS_DRAFT,
        ]);

        $revaluation = $this->service->revalue($this->asset, [
            'revaluation_date' => now()->startOfMonth()->toDateString(),
            'revalued_amount' => 600000,
            'reason' => 'Appraisal.',
        ], $this->user->id);
        $revaluation = $this->service->approve($revaluation, $this->user->id);
        $this->service->post($revaluation, $this->user->id);

        $this->assertDatabaseHas('asset_depreciation_schedules', ['id' => $postedSchedule->id]);
        $this->assertDatabaseMissing('asset_depreciation_schedules', ['id' => $draftSchedule->id]);
    }
}
