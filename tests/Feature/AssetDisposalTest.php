<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
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

class AssetDisposalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private AssetCategory $category;
    private Company $company;
    private AssetDisposalService $service;

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
        $accumulatedDepreciationAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1510', 'name' => 'Accumulated Depreciation',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $gainAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '4900', 'name' => 'Gain on Asset Disposal',
            'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $lossAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5910', 'name' => 'Loss on Asset Disposal',
            'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank Account',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $this->category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Vehicles',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedDepreciationAccount->id,
            'gain_on_disposal_account_id' => $gainAccount->id,
            'loss_on_disposal_account_id' => $lossAccount->id,
        ]);

        $this->service = app(AssetDisposalService::class);
    }

    private function makeActiveAsset(float $capitalizationCost, float $accumulatedDepreciation): Asset
    {
        $bookValue = $capitalizationCost - $accumulatedDepreciation;

        return Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-DISP-' . uniqid(),
            'name' => 'Delivery Van',
            'purchase_date' => now()->subYears(2)->toDateString(),
            'purchase_cost' => $capitalizationCost,
            'capitalization_cost' => $capitalizationCost,
            'residual_value' => 5000,
            'useful_life_months' => 60,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->subYears(2)->toDateString(),
            'capitalization_date' => now()->subYears(2)->toDateString(),
            'accumulated_depreciation' => $accumulatedDepreciation,
            'book_value' => $bookValue,
            'condition' => 'good',
            'status' => Asset::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function disposing_at_a_gain_posts_a_balanced_journal_with_a_gain_line(): void
    {
        $asset = $this->makeActiveAsset(100000, 60000); // NBV = 40000

        $disposal = $this->service->dispose($asset, [
            'disposal_type' => 'sale',
            'disposal_date' => now()->toDateString(),
            'sale_proceeds' => 50000,
        ], $this->user->id);

        $this->assertEquals(40000.0, (float) $disposal->net_book_value);
        $this->assertEquals(10000.0, (float) $disposal->gain_loss_amount); // 50000 - 40000

        $disposal = $this->service->approve($disposal, $this->user->id);
        $disposal = $this->service->post($disposal, $this->user->id);

        $this->assertSame(AssetDisposal::STATUS_POSTED, $disposal->status);
        $journal = Journal::withoutGlobalScopes()->findOrFail($disposal->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);

        $asset->refresh();
        $this->assertSame(Asset::STATUS_SOLD, $asset->status);
        $this->assertEquals(0.0, (float) $asset->book_value);
    }

    /** @test */
    public function disposing_at_a_loss_posts_a_balanced_journal_with_a_loss_line(): void
    {
        $asset = $this->makeActiveAsset(100000, 60000); // NBV = 40000

        $disposal = $this->service->dispose($asset, [
            'disposal_type' => 'sale',
            'disposal_date' => now()->toDateString(),
            'sale_proceeds' => 25000,
        ], $this->user->id);

        $this->assertEquals(-15000.0, (float) $disposal->gain_loss_amount); // 25000 - 40000

        $disposal = $this->service->approve($disposal, $this->user->id);
        $disposal = $this->service->post($disposal, $this->user->id);

        $journal = Journal::withoutGlobalScopes()->findOrFail($disposal->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
    }

    /** @test */
    public function disposing_at_break_even_posts_no_gain_or_loss_line_but_still_balances(): void
    {
        $asset = $this->makeActiveAsset(100000, 60000); // NBV = 40000

        $disposal = $this->service->dispose($asset, [
            'disposal_type' => 'sale',
            'disposal_date' => now()->toDateString(),
            'sale_proceeds' => 40000,
        ], $this->user->id);

        $this->assertEquals(0.0, (float) $disposal->gain_loss_amount);

        $disposal = $this->service->approve($disposal, $this->user->id);
        $disposal = $this->service->post($disposal, $this->user->id);

        $journal = Journal::withoutGlobalScopes()->findOrFail($disposal->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
    }

    /** @test */
    public function an_already_disposed_asset_cannot_be_disposed_again(): void
    {
        $asset = $this->makeActiveAsset(100000, 100000); // fully depreciated
        $asset->update(['status' => Asset::STATUS_SOLD]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->dispose($asset, [
            'disposal_type' => 'scrap',
            'disposal_date' => now()->toDateString(),
        ], $this->user->id);
    }

    /** @test */
    public function posting_requires_approval_first(): void
    {
        $asset = $this->makeActiveAsset(100000, 60000);
        $disposal = $this->service->dispose($asset, [
            'disposal_type' => 'sale', 'disposal_date' => now()->toDateString(), 'sale_proceeds' => 40000,
        ], $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->post($disposal, $this->user->id);
    }
}
