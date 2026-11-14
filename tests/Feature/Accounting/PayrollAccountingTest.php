<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\PayrollRun;
use App\Models\Tenant;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Payroll in the books: employer contributions, paying the booked net salary
 * from a chosen bank/cash ledger (never more cash than is on hand), statutory
 * challans, and no invented calendar-year financial year.
 */
class PayrollAccountingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private PayrollAccountingService $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 23, 12));

        $this->tenant = Tenant::create(['name' => 'Payroll Co', 'slug' => 'payroll-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->company = Company::create(['company_name' => 'Payroll Co']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $this->payroll = app(PayrollAccountingService::class);
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

    private function balance(string $key): float
    {
        return $this->payroll->balance($this->account($key));
    }

    private function journal(string $date, array $lines, array $meta = []): Journal
    {
        return app(JournalService::class)->post(array_map(fn ($l) => [
            'chart_of_account_id' => $this->account($l[0])->id, 'debit' => $l[1] ?? 0, 'credit' => $l[2] ?? 0,
        ], $lines), $meta + ['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date, 'source' => Journal::SOURCE_MANUAL]);
    }

    /** A locked September run whose salary accrual (net 10,000) is already booked. */
    private function bookedRun(): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'payroll_month' => '2026-09',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'locked',
        ]);

        $this->journal('2026-09-30', [['salary_and_wages_staff', 10500], ['pf_payable', 0, 500], ['salary_payable', 0, 10000]], [
            'reference_type' => PayrollAccountingService::ACCRUAL_REFERENCE, 'reference_id' => $run->id,
        ]);

        return $run;
    }

    /** @test */
    public function the_employer_pays_its_own_pf_with_charges_and_esi(): void
    {
        $lines = $this->payroll->employerContributionLines(
            1800, 75,
            $this->account('employer_statutory_contribution'), $this->account('pf_payable'), $this->account('esi_payable'), '2026-09',
        );

        $sum = fn (string $key, string $side) => collect($lines)->where('chart_of_account_id', $this->account($key)->id)->sum($side);

        // 12% matching 1,800 + 1% EDLI/admin on PF wages of 15,000 = 1,950; ESI 75 x 3.25/0.75 = 325.
        $this->assertEquals(1950.0, $sum('pf_payable', 'credit'));
        $this->assertEquals(325.0, $sum('esi_payable', 'credit'));
        $this->assertEquals(2275.0, $sum('employer_statutory_contribution', 'debit'));
    }

    /** @test */
    public function the_payout_pays_the_booked_net_salary_from_the_chosen_bank(): void
    {
        $run = $this->bookedRun();
        $hdfc = ChartOfAccount::where('code', '1021')->firstOrFail();

        $payout = $this->payroll->postPayrollPayoutJournal($run, $hdfc->id, '2026-10-05');

        $this->assertNotNull($payout);
        $this->assertEquals(0.0, $this->balance('salary_payable'));
        $this->assertEquals(-10000.0, $this->payroll->balance($hdfc));
        $this->assertEquals(0.0, $this->balance(SystemAccount::CASH));
    }

    /** @test */
    public function cash_in_hand_cannot_pay_more_than_it_holds(): void
    {
        $run = $this->bookedRun();
        $this->journal('2026-09-01', [[SystemAccount::CASH, 3000], ['share_capital', 0, 3000]]);

        try {
            $this->payroll->postPayrollPayoutJournal($run, $this->account(SystemAccount::CASH)->id, '2026-10-05');
            $this->fail('Paid 10,000 out of 3,000 cash.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has only 3,000.00', $e->getMessage());
        }

        $this->assertEquals(3000.0, $this->balance(SystemAccount::CASH));
        $this->assertEquals(-10000.0, $this->balance('salary_payable'));
    }

    /** @test */
    public function a_statutory_challan_clears_the_payable_but_never_overpays_it(): void
    {
        $this->bookedRun(); // PF Payable 500
        $bank = $this->account(SystemAccount::BANK);

        $this->payroll->postStatutoryPayment($this->tenant->id, 'pf_payable', 300, $bank->id, '2026-10-15', 'TRRN123');
        $this->assertEquals(-200.0, $this->balance('pf_payable'));
        $this->assertEquals(200.0, $this->payroll->statutoryDues($this->tenant->id)['pf_payable']['balance']);

        $this->expectException(InvalidArgumentException::class);
        $this->payroll->postStatutoryPayment($this->tenant->id, 'pf_payable', 500, $bank->id, '2026-10-15');
    }

    /** @test */
    public function no_calendar_year_is_invented_for_a_date_outside_the_books(): void
    {
        $run = $this->bookedRun();

        try {
            $this->payroll->postPayrollPayoutJournal($run, null, '2027-06-30'); // after FY 2026-27
            $this->fail('Posted outside every financial year.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No accounting period', $e->getMessage());
        }

        $this->assertSame(1, FiscalYear::count());
        $this->assertSame(0, JournalEntry::whereHas('journal', fn ($q) => $q->where('reference_type', PayrollAccountingService::PAYOUT_REFERENCE))->count());
    }
}
