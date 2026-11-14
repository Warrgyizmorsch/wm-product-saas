<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
use App\Domains\Accounting\FixedAssets\Services\AssetRevaluationService;
use App\Domains\Accounting\FixedAssets\Services\AssetWriteOffService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The fixed-asset register (capitalization_cost, accumulated_depreciation)
 * must stay equal to the Fixed Assets and Accumulated Depreciation ledgers
 * through every combination of depreciation, revaluation, disposal and
 * write-off — otherwise a revalued asset leaves stray balances behind when it
 * is disposed of.
 */
class FixedAssetLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;
    private AssetCategory $category;

    /** @var array<string, ChartOfAccount> */
    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-month so "this month" and "last month" are both inside the fiscal year.
        Carbon::setTestNow(Carbon::create((int) now()->year, 6, 15));

        $this->tenant = Tenant::create(['name' => 'Ledger Co', 'slug' => 'ledger-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@example.com', 'password' => bcrypt('password')]);
        $this->company = Company::create(['company_name' => 'Ledger Co']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        foreach ([
            'fa' => ['1500', 'Fixed Assets', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_DEBIT],
            'ad' => ['1510', 'Accumulated Depreciation', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_CREDIT],
            'reserve' => ['3200', 'Revaluation Reserve', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::BALANCE_CREDIT],
            'dep' => ['5400', 'Depreciation Expense', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT],
            'loss' => ['5910', 'Loss on Sale of Fixed Assets', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT],
            'impairment' => ['5920', 'Impairment Loss', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT],
        ] as $key => [$code, $name, $type, $balance]) {
            $this->accounts[$key] = ChartOfAccount::create([
                'tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name,
                'type' => $type, 'normal_balance' => $balance, 'is_system' => true, 'is_active' => true,
            ]);
        }

        $this->category = AssetCategory::create(['company_id' => $this->company->id, 'name' => 'Plant']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * An asset bought 12 months ago for 120,000 over 120 months, with its
     * 12,000 of accumulated depreciation already on the ledger (opening entry
     * mirrored here so ledger and register start equal).
     */
    private function asset(): Asset
    {
        $asset = Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-' . uniqid(),
            'name' => 'Press',
            'purchase_date' => now()->subMonths(12)->startOfMonth()->toDateString(),
            'purchase_cost' => 120000,
            'acquisition_cost' => 120000,
            'capitalization_cost' => 120000,
            'residual_value' => 0,
            'useful_life_months' => 120,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->subMonths(12)->startOfMonth()->toDateString(),
            'capitalization_date' => now()->subMonths(12)->startOfMonth()->toDateString(),
            'accumulated_depreciation' => 12000,
            'book_value' => 108000,
            'condition' => 'good',
            'status' => Asset::STATUS_ACTIVE,
        ]);

        // Opening ledger balances matching the register.
        app(\App\Domains\Accounting\Services\JournalService::class)->post([
            ['chart_of_account_id' => $this->accounts['fa']->id, 'debit' => 120000, 'description' => 'Opening'],
            ['chart_of_account_id' => $this->accounts['ad']->id, 'credit' => 12000, 'description' => 'Opening'],
            ['chart_of_account_id' => $this->accounts['reserve']->id, 'credit' => 108000, 'description' => 'Opening'],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()->startOfYear()->toDateString(), 'memo' => 'Opening']);

        return $asset;
    }

    /** Debit-positive ledger balance of one account. */
    private function balance(string $key): float
    {
        $row = JournalEntry::withoutGlobalScopes()
            ->where('chart_of_account_id', $this->accounts[$key]->id)
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
            ->first();

        return round((float) $row->d - (float) $row->c, 2);
    }

    private function revalue(Asset $asset, float $amount, ?string $date = null)
    {
        $service = app(AssetRevaluationService::class);
        $revaluation = $service->revalue($asset, [
            'revaluation_date' => $date ?? now()->toDateString(),
            'revalued_amount' => $amount,
            'reason' => 'Appraisal',
        ], $this->user->id);

        return $service->post($service->approve($revaluation, $this->user->id), $this->user->id);
    }

    private function assertRegisterMatchesLedger(Asset $asset): void
    {
        $asset->refresh();
        $this->assertEquals((float) $asset->capitalization_cost, $this->balance('fa'), 'Fixed asset ledger out of step with register');
        $this->assertEquals(-(float) $asset->accumulated_depreciation, $this->balance('ad'), 'Accumulated depreciation ledger out of step with register');
        $this->assertEquals(round((float) $asset->capitalization_cost - (float) $asset->accumulated_depreciation, 2), (float) $asset->book_value);
    }

    /** @test */
    public function a_surplus_revaluation_writes_back_accumulated_depreciation_and_keeps_register_and_ledger_equal(): void
    {
        $asset = $this->asset();
        $reserveBefore = $this->balance('reserve');

        $revaluation = $this->revalue($asset, 150000);

        $this->assertEquals(108000.0, (float) $revaluation->previous_book_value);
        $this->assertEquals(42000.0, (float) $revaluation->revaluation_surplus_deficit);
        $this->assertEquals(0.0, (float) $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(-42000.0, round($this->balance('reserve') - $reserveBefore, 2));
        $this->assertRegisterMatchesLedger($asset);
    }

    /** @test */
    public function a_deficit_first_uses_up_this_assets_revaluation_reserve_before_charging_impairment(): void
    {
        $asset = $this->asset();
        $this->revalue($asset, 150000); // +42,000 to reserve

        $this->revalue($asset, 100000); // -50,000: 42,000 from reserve, 8,000 impairment

        $this->assertEquals(8000.0, $this->balance('impairment'));
        $this->assertRegisterMatchesLedger($asset);

        $this->revalue($asset, 105000); // +5,000 reverses earlier impairment first
        $this->assertEquals(3000.0, $this->balance('impairment'));
        $this->assertRegisterMatchesLedger($asset);
    }

    /** @test */
    public function disposing_of_a_revalued_asset_clears_both_ledgers(): void
    {
        $asset = $this->asset();
        $this->revalue($asset, 150000);

        $service = app(AssetDisposalService::class);
        $disposal = $service->dispose($asset->fresh(), ['disposal_type' => 'scrap', 'disposal_date' => now()->toDateString()], $this->user->id);
        $service->post($service->approve($disposal, $this->user->id), $this->user->id);

        $this->assertEquals(0.0, $this->balance('fa'));
        $this->assertEquals(0.0, $this->balance('ad'));
        $this->assertEquals(150000.0, $this->balance('loss'));
    }

    /** @test */
    public function depreciation_after_a_revaluation_runs_on_the_revalued_amount_over_the_remaining_life(): void
    {
        $asset = $this->asset();
        $this->revalue($asset, 108000 + 0.0, now()->startOfMonth()->toDateString()); // no surplus, only re-basing

        $asset->refresh();
        $this->assertEquals(108, (int) $asset->useful_life_months); // 120 - 12 elapsed

        $depreciation = app(AssetDepreciationService::class);
        $schedule = $depreciation->generateSchedule($asset, (int) now()->year, (int) now()->month, $this->user->id);
        $this->assertEquals(108000.0, (float) $schedule->opening_book_value);
        $this->assertEquals(1000.0, (float) $schedule->depreciation_amount); // 108,000 / 108

        $depreciation->post($depreciation->approve($depreciation->review($schedule, $this->user->id), $this->user->id), $this->user->id);
        $this->assertRegisterMatchesLedger($asset);
    }

    /** @test */
    public function a_revaluation_dated_before_already_posted_depreciation_is_rejected(): void
    {
        $asset = $this->asset();
        $depreciation = app(AssetDepreciationService::class);
        $schedule = $depreciation->generateSchedule($asset, (int) now()->year, (int) now()->month, $this->user->id);
        $depreciation->post($depreciation->approve($depreciation->review($schedule, $this->user->id), $this->user->id), $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->revalue($asset, 150000, now()->subMonth()->toDateString());
    }

    /** @test */
    public function a_later_period_cannot_be_generated_while_an_earlier_one_is_unposted(): void
    {
        $asset = $this->asset();
        $depreciation = app(AssetDepreciationService::class);
        $previous = now()->subMonth();
        $depreciation->generateSchedule($asset, (int) $previous->year, (int) $previous->month, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $depreciation->generateSchedule($asset, (int) now()->year, (int) now()->month, $this->user->id);
    }

    /** @test */
    public function a_write_off_uses_accumulated_depreciation_as_at_posting_not_as_at_request(): void
    {
        $asset = $this->asset();
        $writeOffs = app(AssetWriteOffService::class);
        $writeOff = $writeOffs->writeOff($asset, ['write_off_date' => now()->toDateString(), 'reason' => 'Damaged'], $this->user->id);

        // Depreciation posted after the request was raised.
        $depreciation = app(AssetDepreciationService::class);
        $schedule = $depreciation->generateSchedule($asset->fresh(), (int) now()->year, (int) now()->month, $this->user->id);
        $depreciation->post($depreciation->approve($depreciation->review($schedule, $this->user->id), $this->user->id), $this->user->id);

        $writeOff = $writeOffs->post($writeOffs->approve($writeOff, $this->user->id), $this->user->id);

        $this->assertEquals(107000.0, (float) $writeOff->net_book_value);
        $this->assertEquals(0.0, $this->balance('fa'));
        $this->assertEquals(0.0, $this->balance('ad'));
    }

    /** @test */
    public function a_disposal_uses_accumulated_depreciation_as_at_posting_not_as_at_request(): void
    {
        $asset = $this->asset();
        $disposals = app(AssetDisposalService::class);
        $disposal = $disposals->dispose($asset, ['disposal_type' => 'scrap', 'disposal_date' => now()->toDateString()], $this->user->id);

        $depreciation = app(AssetDepreciationService::class);
        $schedule = $depreciation->generateSchedule($asset->fresh(), (int) now()->year, (int) now()->month, $this->user->id);
        $depreciation->post($depreciation->approve($depreciation->review($schedule, $this->user->id), $this->user->id), $this->user->id);

        $disposal = $disposals->post($disposals->approve($disposal, $this->user->id), $this->user->id);

        $this->assertEquals(13000.0, (float) $disposal->accumulated_depreciation_at_disposal);
        $this->assertEquals(0.0, $this->balance('fa'));
        $this->assertEquals(0.0, $this->balance('ad'));
    }
}
