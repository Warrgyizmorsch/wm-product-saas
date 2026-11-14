<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Models\TdsSection;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\Accounting\Services\Tds\TdsChallanService;
use App\Domains\Accounting\Services\Tds\TdsReturnService;
use App\Domains\Accounting\Services\Tds\TdsSectionService;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Domains\Purchase\Services\VendorBillTdsService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Salary TDS (section 192): payroll records each employee's salary and TDS in
 * the register, which feeds 24Q (quarterly) and Form 16 (yearly), separate
 * from the vendors' 26Q.
 */
class SalaryTdsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private PayGroup $payGroup;

    /** @var array<int, array{gross: float, tds: float}> */
    private array $salaries = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Salary Co', 'slug' => 'salary-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->company = Company::create(['company_name' => 'Salary Co']);
        $this->payGroup = PayGroup::create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'name' => 'Staff']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
        app(TdsSectionService::class)->provisionDefaultsIfMissing($this->tenant->id);

        // The salary maths has its own tests; here only gross and TDS matter.
        $this->mock(PayrollCalculationService::class, function ($mock) {
            $mock->shouldReceive('calculateSalary')->andReturnUsing(function (Employee $employee) {
                $pay = $this->salaries[$employee->id] ?? ['gross' => 0.0, 'tds' => 0.0];
                $items = ['BASIC' => ['calculated_value' => $pay['gross'], 'type' => 'earning']];
                if ($pay['tds'] > 0) {
                    $items['TDS'] = ['calculated_value' => $pay['tds'], 'type' => 'deduction', 'name' => 'Income Tax (TDS)'];
                }

                return ['items' => $items, 'summary' => ['net_payout' => $pay['gross'] - $pay['tds']]];
            });
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $name, float $gross, float $tds, ?string $pan = 'ABCPE1234F'): Employee
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'employee_id' => 'EMP-' . uniqid(),
            'full_name' => $name, 'gender' => 'female', 'date_of_joining' => '2025-01-01',
            'pay_group_id' => $this->payGroup->id, 'status' => true, 'pan_card_number' => $pan,
        ]);
        $this->salaries[$employee->id] = ['gross' => $gross, 'tds' => $tds];

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

    private function salaryRows()
    {
        return TdsDeduction::query()->where('source_type', TdsDeduction::SOURCE_PAYROLL_RUN)->orderBy('party_name')->get();
    }

    #[Test]
    public function a_posted_payroll_puts_each_employees_salary_and_tds_under_section_192(): void
    {
        $asha = $this->employee('Asha', 120000, 9500);
        $this->employee('Vikram', 40000, 0, 'BCDPV5678G');

        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-10'));

        $rows = $this->salaryRows();
        $this->assertCount(2, $rows, 'Every paid employee is in the register, TDS or not');
        $this->assertSame((int) $asha->id, (int) $rows[0]->party_id);
        $this->assertSame('employee', $rows[0]->party_type);
        $this->assertSame(120000.0, $rows[0]->base_amount);
        $this->assertSame(9500.0, $rows[0]->tds_amount);
        $this->assertSame('ABCPE1234F', $rows[0]->pan);
        $this->assertSame('2026-10-31', $rows[0]->deduction_date->toDateString());
        $this->assertSame(3, $rows[0]->quarter);

        // Section 192 sits on the TDS Payable 2140 payroll has always credited.
        $section = TdsSection::query()->where('code', TdsSection::SALARY_CODE)->firstOrFail();
        $this->assertSame($section->id, (int) $rows[0]->tds_section_id);
        $this->assertSame('2140', ChartOfAccount::find($section->payable_account_id)->code);
    }

    #[Test]
    public function salary_tds_is_in_24q_and_never_in_26q(): void
    {
        $this->employee('Asha', 120000, 9500);
        $this->employee('Vikram', 40000, 0, 'BCDPV5678G');
        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-10'));

        $returns = app(TdsReturnService::class);
        $q24 = $returns->quarter('2026-27', 3, TdsReturnService::FORM_24Q);
        $q26 = $returns->quarter('2026-27', 3, TdsReturnService::FORM_26Q);

        $this->assertCount(1, $q24['deductions'], '24Q lists the months tax was deducted');
        $this->assertSame(9500.0, $q24['totals']['tds']);
        $this->assertCount(0, $q26['deductions']);
        $this->assertCount(2, $returns->annualSalary('2026-27'), 'Form 16 / Annexure II covers every employee paid');

        $table = $returns->exportTable($q24);
        $this->assertStringStartsWith('Form 24Q', $table['title']);
    }

    #[Test]
    public function a_challan_already_paid_from_payroll_statutory_dues_is_not_posted_twice(): void
    {
        $this->employee('Asha', 120000, 9500);
        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-10'));
        $bank = ChartOfAccount::query()->where('code', '1020')->firstOrFail();
        $journalsBefore = Journal::query()->count();
        $id = $this->salaryRows()->first()->id;

        $challan = app(TdsChallanService::class)->deposit([
            'tenant_id' => $this->tenant->id, 'challan_number' => '77001', 'deposit_date' => '2026-11-07',
            'bank_account_id' => $bank->id, 'deduction_ids' => [$id], 'already_paid' => true,
        ]);

        $this->assertNull($challan->journal_id);
        $this->assertSame($journalsBefore, Journal::query()->count());
        $this->assertSame($challan->id, (int) TdsDeduction::find($id)->tds_challan_id);
        $this->assertSame(9500.0, app(TdsReturnService::class)->quarter('2026-27', 3, TdsReturnService::FORM_24Q)['totals']['deposited']);
    }

    #[Test]
    public function an_older_run_is_backfilled_only_when_its_tds_still_matches_the_books(): void
    {
        $asha = $this->employee('Asha', 120000, 9500);
        $run = $this->payrollRun('2026-09');
        $payroll = app(PayrollAccountingService::class);
        $payroll->postPayrollRunJournal($run);
        TdsDeduction::query()->delete(); // as if posted before the register existed

        $this->assertTrue($payroll->backfillSalaryTds($run));
        $this->assertSame(9500.0, $this->salaryRows()->first()->tds_amount);

        // Salary inputs changed since: the recalculation no longer matches the journal.
        TdsDeduction::query()->delete();
        $this->salaries[$asha->id]['tds'] = 11000;
        $this->assertFalse($payroll->backfillSalaryTds($run));
        $this->assertCount(0, $this->salaryRows());
    }

    #[Test]
    public function the_salary_section_is_not_offered_on_vendor_bills(): void
    {
        $codes = app(VendorBillTdsService::class)->sectionOptions()->pluck('code');

        $this->assertNotContains(TdsSection::SALARY_CODE, $codes->all());
        $this->assertContains('194C', $codes->all());
    }

    #[Test]
    public function the_24q_page_and_form_16_download_work(): void
    {
        $asha = $this->employee('Asha', 120000, 9500);
        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-10'));

        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Acc', 'email' => 'acc@salary.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $http = $this->actingAs($user)->withHeader('X-Tenant', 'salary-co');

        $http->get(route('accounting.tds.returns', ['form' => '24Q', 'fy' => '2026-27', 'quarter' => 3]))
            ->assertOk()->assertSee('Asha')->assertSee('Form 16');
        $http->get(route('accounting.tds.returns.export', ['format' => 'xlsx', 'form' => '24Q', 'fy' => '2026-27', 'quarter' => 3]))->assertOk();
        $http->get(route('accounting.tds.form16', ['employee' => $asha->id, 'fy' => '2026-27']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // An employee with nil TDS can still get a Form 16 (salary summary).
        $vikram = $this->employee('Vikram', 40000, 0, 'BCDPV5678G');
        app(PayrollAccountingService::class)->postPayrollRunJournal($this->payrollRun('2026-11'));
        $http->get(route('accounting.tds.returns', ['form' => '24Q', 'fy' => '2026-27', 'quarter' => 3]))
            ->assertOk()->assertSee(route('accounting.tds.form16', ['employee' => $vikram->id, 'fy' => '2026-27']), false);
        $http->get(route('accounting.tds.form16', ['employee' => $vikram->id, 'fy' => '2026-27']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
