<?php

namespace Tests\Feature;

use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
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
    private ChartOfAccount $capital;
    private ChartOfAccount $fixedAssets;

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

        $this->fixedAssets = $fixedAssetAccount;
        $this->capital = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '3030', 'name' => "Proprietor's Capital",
            'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'is_active' => true,
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

    /** @return array{funding_source: string, funding_account_id: int} */
    private function openingBalance(): array
    {
        return ['funding_source' => AssetCapitalizationService::FUNDING_OPENING_BALANCE, 'funding_account_id' => $this->capital->id];
    }

    private function balance(ChartOfAccount $account): float
    {
        $row = JournalEntry::withoutGlobalScopes()->where('chart_of_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();

        return round((float) $row->d - (float) $row->c, 2);
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
        ] + $this->openingBalance(), $this->accountant->id);

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
        ] + $this->openingBalance(), $this->accountant->id);

        $this->assertSame(24, $capitalized->useful_life_months);
        $this->assertEquals(5000.0, (float) $capitalized->residual_value);
        $this->assertSame(Asset::DEPRECIATION_METHOD_WDV, $capitalized->depreciation_method);
    }

    /** @test */
    public function an_already_active_asset_cannot_be_capitalized_again(): void
    {
        $asset = $this->makeAsset(60000);
        app(AssetCapitalizationService::class)->capitalize($asset, ['useful_life_months' => 36] + $this->openingBalance(), $this->accountant->id);

        $this->expectException(\InvalidArgumentException::class);
        app(AssetCapitalizationService::class)->capitalize($asset->fresh(), ['useful_life_months' => 36] + $this->openingBalance(), $this->accountant->id);
    }

    /** @test */
    public function capitalization_requires_a_useful_life(): void
    {
        $asset = $this->makeAsset(60000);
        $category = $this->category;
        $category->update(['default_useful_life_months' => null]);

        $this->expectException(\InvalidArgumentException::class);
        app(AssetCapitalizationService::class)->capitalize($asset, $this->openingBalance(), $this->accountant->id);
    }

    /** @test */
    public function capitalizing_a_manually_added_asset_posts_it_to_the_ledger_against_opening_balance(): void
    {
        $asset = $this->makeAsset(60000);

        $capitalized = app(AssetCapitalizationService::class)->capitalize($asset, [
            'directly_attributable_cost' => 2000,
            'recoverable_tax' => 10800,
        ] + $this->openingBalance(), $this->accountant->id);

        $journal = app(AssetCapitalizationService::class)->capitalizationJournal($capitalized);
        $this->assertNotNull($journal);
        $this->assertEquals(62000.0, $this->balance($this->fixedAssets));
        // An opening balance carries no input tax: credit equals the capitalized cost.
        $this->assertEquals(-62000.0, $this->balance($this->capital));
    }

    /** @test */
    public function an_asset_paid_from_bank_debits_input_tax_and_credits_the_bank(): void
    {
        $bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true, 'is_active' => true,
        ]);
        $inputTax = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1300', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'system_key' => \App\Domains\Accounting\Support\SystemAccount::INPUT_TAX, 'is_active' => true,
        ]);

        app(AssetCapitalizationService::class)->capitalize($this->makeAsset(60000), [
            'recoverable_tax' => 10800,
            'funding_source' => AssetCapitalizationService::FUNDING_CASH_BANK,
            'funding_account_id' => $bank->id,
        ], $this->accountant->id);

        $this->assertEquals(60000.0, $this->balance($this->fixedAssets));
        $this->assertEquals(10800.0, $this->balance($inputTax));
        $this->assertEquals(-70800.0, $this->balance($bank));
    }

    /** @test */
    public function a_manual_asset_cannot_be_capitalized_without_saying_how_it_was_funded(): void
    {
        $asset = $this->makeAsset(60000);

        try {
            app(AssetCapitalizationService::class)->capitalize($asset, [], $this->accountant->id);
            $this->fail('Expected capitalization without funding to be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(Asset::STATUS_AVAILABLE, $asset->fresh()->status);
    }

    /** @test */
    public function an_asset_bought_through_purchase_posts_nothing_at_capitalization(): void
    {
        $asset = $this->makeAsset(60000);
        // Only the link matters here, not the purchase order itself.
        // (SQLite: defer the FK check to commit; the test transaction never commits.)
        \Illuminate\Support\Facades\DB::statement('PRAGMA defer_foreign_keys = ON');
        $asset->forceFill(['purchase_order_id' => 999])->saveQuietly();

        $capitalized = app(AssetCapitalizationService::class)->capitalize($asset->fresh(), [], $this->accountant->id);

        $this->assertSame(Asset::STATUS_ACTIVE, $capitalized->status);
        $this->assertNull(app(AssetCapitalizationService::class)->capitalizationJournal($capitalized));
        $this->assertEquals(0.0, $this->balance($this->fixedAssets));
    }

    /** @test */
    public function the_missing_entry_can_be_posted_for_an_asset_capitalized_before_this_fix_and_only_once(): void
    {
        // Simulates CHR-001: capitalized by the old code, never on the ledger.
        $asset = $this->makeAsset(80000);
        $asset->forceFill([
            'acquisition_cost' => 80000, 'directly_attributable_cost' => 5000, 'non_recoverable_tax' => 7800,
            'capitalization_cost' => 92800, 'book_value' => 92800, 'accumulated_depreciation' => 0,
            'residual_value' => 42500, 'useful_life_months' => 12, 'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'capitalization_date' => now()->toDateString(), 'depreciation_start_date' => now()->toDateString(),
            'status' => Asset::STATUS_ACTIVE,
        ])->saveQuietly();

        $service = app(AssetCapitalizationService::class);
        $first = $service->postMissingEntry($asset->fresh(), $this->openingBalance(), $this->accountant->id);
        $second = $service->postMissingEntry($asset->fresh(), $this->openingBalance(), $this->accountant->id);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(92800.0, $this->balance($this->fixedAssets));
        $this->assertEquals(-92800.0, $this->balance($this->capital));
    }
}
