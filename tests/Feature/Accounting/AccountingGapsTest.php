<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
use App\Domains\Accounting\FixedAssets\Services\AssetRevaluationService;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\Gst\Gstr1ReturnService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The accounting gaps found in the 2026-10-23 walkthrough: opening side,
 * journal numbering, asset ledgers and GST, pro-rata and post-revaluation
 * depreciation, reserve realisation, and the trial balance / balance sheet.
 */
class AccountingGapsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 23, 12));

        $this->tenant = Tenant::create(['name' => 'Gaps Co', 'slug' => 'gaps-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@gaps.example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->company = Company::create(['company_name' => 'Gaps Co', 'gst_number' => '08AAFCS1234E1Z0']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $key): ChartOfAccount
    {
        return ChartOfAccount::where('system_key', $key)->firstOrFail();
    }

    /** Debit-positive balance of an account. */
    private function balance(string $key): float
    {
        $row = JournalEntry::where('chart_of_account_id', $this->account($key)->id)
            ->whereHas('journal', fn ($q) => $q->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED]))
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();

        return round((float) $row->d - (float) $row->c, 2);
    }

    private function journal(string $date, array $lines): Journal
    {
        return app(JournalService::class)->post(array_map(fn ($l) => [
            'chart_of_account_id' => $this->account($l[0])->id,
            'debit' => $l[1] ?? 0,
            'credit' => $l[2] ?? 0,
            'party_type' => $l[3] ?? null,
            'party_id' => $l[4] ?? null,
        ], $lines), ['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date, 'source' => Journal::SOURCE_MANUAL]);
    }

    private function asset(string $categoryName, float $cost, string $start): Asset
    {
        $category = AssetCategory::create(['company_id' => $this->company->id, 'name' => $categoryName]);

        $asset = Asset::create([
            'company_id' => $this->company->id, 'asset_category_id' => $category->id,
            'asset_code' => 'FA-' . uniqid(), 'name' => $categoryName . ' item', 'purchase_date' => $start,
            'purchase_cost' => $cost, 'condition' => 'new', 'status' => Asset::STATUS_AVAILABLE,
        ]);

        return app(AssetCapitalizationService::class)->capitalize($asset, [
            'acquisition_cost' => $cost, 'recoverable_tax' => round($cost * 0.18, 2), 'recoverable_tax_gst_type' => 'cgst_sgst',
            'residual_value' => 0, 'useful_life_months' => 120, 'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'capitalization_date' => $start, 'depreciation_start_date' => $start,
            'funding_source' => AssetCapitalizationService::FUNDING_CASH_BANK, 'funding_account_id' => $this->account(SystemAccount::BANK)->id,
        ], $this->user->id);
    }

    /** @test */
    public function a_ledger_without_an_opening_balance_carries_its_normal_side(): void
    {
        $this->assertSame('credit', $this->account('term_loan_bank')->opening_balance_type);
        $this->assertSame('credit', $this->account('share_capital')->opening_balance_type);
        $this->assertSame('debit', $this->account(SystemAccount::CASH)->opening_balance_type);
    }

    /** @test */
    public function a_journal_is_numbered_by_the_month_it_belongs_to(): void
    {
        $april = $this->journal('2026-04-01', [[SystemAccount::CASH, 100], ['share_capital', 0, 100]]);
        $october = $this->journal('2026-10-05', [[SystemAccount::CASH, 50], ['share_capital', 0, 50]]);

        $this->assertStringStartsWith('JNL-202604-', $april->journal_number);
        $this->assertStringStartsWith('JNL-202610-', $october->journal_number);
    }

    /** @test */
    public function capitalizing_uses_the_category_ledger_by_name_and_splits_input_gst(): void
    {
        $asset = $this->asset('Plant & Machinery', 100000, '2026-06-15');

        $this->assertEquals(100000.0, $this->balance('plant_and_machinery'));
        $this->assertEquals(0.0, $this->balance(SystemAccount::FIXED_ASSETS));
        $this->assertEquals(9000.0, $this->balance(SystemAccount::INPUT_CGST));
        $this->assertEquals(9000.0, $this->balance(SystemAccount::INPUT_SGST));
        $this->assertEquals(0.0, $this->balance(SystemAccount::INPUT_TAX));
        $this->assertSame(Asset::STATUS_ACTIVE, $asset->status);
    }

    /** @test */
    public function the_first_month_of_depreciation_is_pro_rata(): void
    {
        $asset = $this->asset('Plant & Machinery', 120000, '2026-06-15'); // 1,000 a month

        $june = app(AssetDepreciationService::class)->generateSchedule($asset, 2026, 6, $this->user->id);

        $this->assertEquals(533.33, (float) $june->depreciation_amount); // 16 of 30 days
    }

    /** @test */
    public function after_a_revaluation_depreciation_runs_over_the_remaining_life(): void
    {
        $asset = $this->asset('Plant & Machinery', 120000, '2026-04-01');
        $asset->update(['accumulated_depreciation' => 6000, 'book_value' => 114000]);

        $service = app(AssetRevaluationService::class);
        $revaluation = $service->revalue($asset->fresh(), ['revaluation_date' => '2026-09-30', 'revalued_amount' => 130000, 'reason' => 'Valuer'], $this->user->id);
        $service->post($service->approve($revaluation, $this->user->id), $this->user->id);

        $this->assertSame(115, (int) $asset->fresh()->useful_life_months); // 120 less Apr-Aug
    }

    /** @test */
    public function selling_a_revalued_asset_credits_its_ledger_splits_igst_and_realises_the_reserve(): void
    {
        $asset = $this->asset('Plant & Machinery', 100000, '2026-04-01');

        $revaluations = app(AssetRevaluationService::class);
        $r = $revaluations->revalue($asset->fresh(), ['revaluation_date' => '2026-05-31', 'revalued_amount' => 120000, 'reason' => 'Valuer'], $this->user->id);
        $revaluations->post($revaluations->approve($r, $this->user->id), $this->user->id);
        $this->assertEquals(-20000.0, $this->balance(SystemAccount::REVALUATION_RESERVE));

        $disposals = app(AssetDisposalService::class);
        $d = $disposals->dispose($asset->fresh(), ['disposal_type' => 'sale', 'disposal_date' => '2026-10-10', 'sale_proceeds' => 110000, 'tax_amount' => 19800, 'gst_type' => 'igst'], $this->user->id);
        $disposals->post($disposals->approve($d, $this->user->id), $this->user->id);

        $this->assertEquals(0.0, $this->balance('plant_and_machinery'));
        $this->assertEquals(-19800.0, $this->balance(SystemAccount::OUTPUT_IGST));
        $this->assertEquals(0.0, $this->balance(SystemAccount::OUTPUT_TAX));
        $this->assertEquals(0.0, $this->balance(SystemAccount::REVALUATION_RESERVE));
        $this->assertEquals(-20000.0, $this->balance(SystemAccount::RESERVES_AND_SURPLUS));
        $this->assertEquals(10000.0, $this->balance(SystemAccount::LOSS_ON_ASSET_DISPOSAL)); // 120,000 - 110,000
    }

    /** @test */
    public function the_trial_balance_shows_net_closing_balances_by_default(): void
    {
        $this->journal('2026-04-01', [[SystemAccount::CASH, 1000], ['share_capital', 0, 1000]]);
        $this->journal('2026-10-05', [[SystemAccount::BANK, 300], [SystemAccount::CASH, 0, 300]]);
        $october = AccountingPeriod::where('name', 'October 2026')->firstOrFail();

        $closing = $this->actingAs($this->user)->withHeader('X-Tenant', 'gaps-co')
            ->get(route('accounting.reports.trial-balance', ['period_id' => $october->id]))->assertOk()->viewData('rows');
        $cash = $closing->first(fn ($row) => $row['account']->code === '1010');
        $this->assertEquals([700.0, 0.0], [$cash['debit'], $cash['credit']]);

        $movement = $this->actingAs($this->user)->withHeader('X-Tenant', 'gaps-co')
            ->get(route('accounting.reports.trial-balance', ['period_id' => $october->id, 'basis' => 'movement']))->assertOk()->viewData('rows');
        $cash = $movement->first(fn ($row) => $row['account']->code === '1010');
        $this->assertEquals([0.0, 300.0], [$cash['debit'], $cash['credit']]);
    }

    /** @test */
    public function the_balance_sheet_shows_customer_credits_as_advances_and_flags_an_opening_difference(): void
    {
        $owes = Customer::create(['name' => 'Owes Us', 'email' => 'owes@example.com']);
        $paid = Customer::create(['name' => 'Paid Ahead', 'email' => 'ahead@example.com']);
        $this->journal('2026-04-01', [[SystemAccount::AR, 8000, 0, 'customer', $owes->id], [SystemAccount::AR, 0, 5000, 'customer', $paid->id], [SystemAccount::OPENING_BALANCE_DIFFERENCE, 0, 3000]]);
        $october = AccountingPeriod::where('name', 'October 2026')->firstOrFail();

        $response = $this->actingAs($this->user)->withHeader('X-Tenant', 'gaps-co')
            ->get(route('accounting.reports.balance-sheet', ['period_id' => $october->id]))->assertOk();

        $find = fn ($rows, string $code) => collect($rows)->first(fn ($row) => $row['account']->code === $code)['balance'] ?? null;
        $sections = $response->viewData('sections');
        $this->assertEquals(8000.0, $find($sections['asset']['current'], '1100'));
        $this->assertEquals(5000.0, $find($sections['liability']['current'], '2200'));
        $this->assertTrue($response->viewData('isBalanced'));
        $this->assertEquals(3000.0, $response->viewData('openingDifference'));
        $response->assertSee("Opening balances don't agree", false);
    }

    /** @test */
    public function a_customer_registered_in_another_state_is_an_inter_state_supply(): void
    {
        $service = app(Gstr1ReturnService::class);

        $outOfState = Customer::create(['name' => 'Pune Buyer', 'email' => 'pune@example.com', 'gstin' => '27ABCDE1234F1Z5']);
        $local = Customer::create(['name' => 'Udaipur Buyer', 'email' => 'udr@example.com', 'gstin' => '08ABCDE1234F1Z9']);

        $this->assertSame(['pos' => '27', 'inter' => true], array_intersect_key($service->supplyFor($outOfState), ['pos' => 0, 'inter' => 0]));
        $this->assertFalse($service->supplyFor($local)['inter']);
    }
}
