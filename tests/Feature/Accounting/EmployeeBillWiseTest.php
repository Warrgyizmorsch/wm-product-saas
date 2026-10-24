<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\Accounting\Services\TravelExpenseAccountingService;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Models\Tenant;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bill-wise for employees: each salary is a SAL bill the payout settles, a
 * cash advance is an ADV bill, and a claim or a payroll recovery that clears
 * the advance settles that bill rather than sitting On Account.
 */
class EmployeeBillWiseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private PayGroup $payGroup;

    /** @var array<int, array{net: float, recovery: float}> employee id => this month's figures */
    private array $salaries = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 23, 12));
        BillAllocationService::resetSchemaCache();

        $this->tenant = Tenant::create(['name' => 'Staff Co', 'slug' => 'staff-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->company = Company::create(['company_name' => 'Staff Co']);
        $this->payGroup = PayGroup::create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'name' => 'Staff']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        // The salary maths has its own tests; here only its result matters.
        $this->mock(PayrollCalculationService::class, function ($mock) {
            $mock->shouldReceive('calculateSalary')->andReturnUsing(function (Employee $employee) {
                $pay = $this->salaries[$employee->id] ?? ['net' => 0.0, 'recovery' => 0.0];
                $items = ['BASIC' => ['calculated_value' => $pay['net'] + $pay['recovery'], 'type' => 'earning']];
                if ($pay['recovery'] > 0) {
                    $items['ADVANCE_RECOVERY'] = ['calculated_value' => $pay['recovery'], 'type' => 'deduction', 'name' => 'Advance Recovery'];
                }

                return ['items' => $items, 'summary' => ['net_payout' => $pay['net']]];
            });
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $name, float $net, float $recovery = 0.0): Employee
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'employee_id' => 'EMP-' . uniqid(),
            'full_name' => $name, 'gender' => 'male', 'date_of_joining' => '2025-01-01',
            'pay_group_id' => $this->payGroup->id, 'status' => true,
        ]);
        $this->salaries[$employee->id] = ['net' => $net, 'recovery' => $recovery];

        return $employee;
    }

    private function payrollRun(string $month): PayrollRun
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return PayrollRun::create([
            'company_id' => $this->company->id, 'payroll_month' => $month,
            'start_date' => $start->toDateString(), 'end_date' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'locked',
        ]);
    }

    private function advance(Employee $employee, float $amount, ?ExpenseReport $report = null): CashAdvance
    {
        $advance = CashAdvance::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'expense_report_id' => $report?->id,
            'amount' => $amount, 'approved_amount' => $amount, 'purpose' => 'Site visit', 'status' => 'disbursed',
        ]);
        $this->assertNotNull(app(TravelExpenseAccountingService::class)->postCashAdvanceDisbursementJournal($advance));

        return $advance;
    }

    private function salaryBill(Employee $employee, string $month): BillReference
    {
        return BillReference::where('party_type', JournalEntry::PARTY_EMPLOYEE)->where('party_id', $employee->id)
            ->where('ref_number', "SAL-{$month}")->firstOrFail();
    }

    private function open(BillReference $bill): float
    {
        return app(BillAllocationService::class)->billOutstanding($bill);
    }

    private function advanceBill(CashAdvance $advance): BillReference
    {
        return BillReference::where('ref_number', 'ADV-' . $advance->id)->firstOrFail();
    }

    private function onAccount(Employee $employee, string $systemKey): float
    {
        return round((float) BillAllocation::where('party_type', JournalEntry::PARTY_EMPLOYEE)->where('party_id', $employee->id)
            ->where('control_account_id', ChartOfAccount::where('system_key', $systemKey)->value('id'))
            ->where('ref_type', BillAllocation::ON_ACCOUNT)->sum('amount'), 2);
    }

    /** Every party's register = its ledger balance, on every bill-wise ledger. */
    private function assertRegisterMatchesLedger(): void
    {
        foreach (array_keys(app(BillAllocationService::class)->controlAccounts($this->tenant->id)) as $controlId) {
            $ledger = app(JournalRepositoryInterface::class)->partyBalancesUpTo($this->tenant->id, $controlId, Carbon::create(2027, 3, 31)->endOfDay())
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();
            $register = BillAllocation::where('control_account_id', $controlId)->groupBy('party_type', 'party_id')
                ->selectRaw('party_type, party_id, SUM(amount) as balance')->get()
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();

            $this->assertEquals($ledger->all(), $register->all(), "Register out of step with the ledger on account #{$controlId}");
        }
    }

    /** @test */
    public function each_salary_is_a_bill_the_payout_settles_and_a_held_salary_stays_open(): void
    {
        $asha = $this->employee('Asha', 30000);
        $vikram = $this->employee('Vikram', 20000);
        PayrollHold::create(['employee_id' => $vikram->id, 'payroll_month' => '2026-09', 'status' => 'on_hold']);
        $run = $this->payrollRun('2026-09');

        $payroll = app(PayrollAccountingService::class);
        $accrual = $payroll->postPayrollRunJournal($run);

        $salaryPayable = ChartOfAccount::where('system_key', 'salary_payable')->value('id');
        $lines = $accrual->entries()->where('chart_of_account_id', $salaryPayable)->get();
        $this->assertCount(2, $lines, 'One Salary Payable line per employee');
        $this->assertEquals([$asha->id, $vikram->id], $lines->pluck('party_id')->map(fn ($id) => (int) $id)->sort()->values()->all());
        $this->assertEquals(-30000.0, $this->open($this->salaryBill($asha, '2026-09')));
        $this->assertEquals(-20000.0, $this->open($this->salaryBill($vikram, '2026-09')));

        $payroll->postPayrollPayoutJournal($run, null, '2026-10-05');

        $this->assertEquals(0.0, $this->open($this->salaryBill($asha, '2026-09')));
        $this->assertEquals(-20000.0, $this->open($this->salaryBill($vikram, '2026-09')), 'A held salary is still owed');
        $this->assertEquals(0.0, $this->onAccount($asha, 'salary_payable'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function an_advance_recovered_through_payroll_settles_the_oldest_advance_first(): void
    {
        $asha = $this->employee('Asha', 28000, 7000);
        $first = $this->advance($asha, 5000);
        $second = $this->advance($asha, 4000);

        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-10'));

        $this->assertEquals(0.0, $this->open($this->advanceBill($first)));
        $this->assertEquals(2000.0, $this->open($this->advanceBill($second)));
        $this->assertEquals(0.0, $this->onAccount($asha, 'advance_to_employees'), 'Recovery is matched to the advances, not left On Account');
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_claim_settles_its_own_advance_and_leaves_an_unrelated_one_open(): void
    {
        $asha = $this->employee('Asha', 0);
        $older = $this->advance($asha, 1000);
        $report = ExpenseReport::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $asha->id, 'title' => 'Pune visit',
            'total_amount' => 6000, 'approved_amount' => 6000, 'advance_adjusted' => 5000,
            'approved_net_reimbursement' => 1000, 'status' => 'paid',
        ]);
        $tripAdvance = $this->advance($asha, 5000, $report);

        $this->assertNotNull(app(TravelExpenseAccountingService::class)->postExpenseReportPayoutJournal($report));

        $this->assertEquals(0.0, $this->open($this->advanceBill($tripAdvance)));
        $this->assertEquals(1000.0, $this->open($this->advanceBill($older)), 'The older, unrelated advance is untouched');
        $this->assertEquals(0.0, $this->onAccount($asha, 'advance_to_employees'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_claim_with_no_linked_advance_clears_the_oldest_one(): void
    {
        $asha = $this->employee('Asha', 0);
        $older = $this->advance($asha, 3000);
        $newer = $this->advance($asha, 3000);
        $report = ExpenseReport::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $asha->id, 'title' => 'Client lunch',
            'total_amount' => 2500, 'approved_amount' => 2500, 'advance_adjusted' => 2500,
            'approved_net_reimbursement' => 0, 'status' => 'paid',
        ]);

        app(TravelExpenseAccountingService::class)->postExpenseReportPayoutJournal($report);

        $this->assertEquals(500.0, $this->open($this->advanceBill($older)));
        $this->assertEquals(3000.0, $this->open($this->advanceBill($newer)));
        $this->assertRegisterMatchesLedger();
    }
}
