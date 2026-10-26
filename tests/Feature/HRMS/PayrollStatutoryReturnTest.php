<?php

namespace Tests\Feature\HRMS;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollStatutoryLine;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Domains\HRMS\Services\PayrollStatutoryService;
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
 * PF ECR and ESIC monthly return: each employee's PF / ESI figures are kept
 * when payroll posts, split the way EPFO wants them, and written as the ECR
 * 2.0 text file and the ESIC contribution sheet.
 */
class PayrollStatutoryReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private PayGroup $payGroup;

    /** @var array<int, array> employee id => calculateSalary() result */
    private array $calcs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');

        $this->tenant = Tenant::create(['name' => 'Factory Co', 'slug' => 'factory-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->company = Company::create(['company_name' => 'Factory Co']);
        $this->payGroup = PayGroup::create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'name' => 'Staff']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $this->mock(PayrollCalculationService::class, function ($mock) {
            $mock->shouldReceive('calculateSalary')->andReturnUsing(fn (Employee $employee) => $this->calcs[$employee->id]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An employee whose month comes out as given (the salary maths has its own tests). */
    private function employee(string $name, array $earnings, float $pf, array $pfWages, float $esi, float $esiWages, float $lopDays, ?string $uan, ?string $ip): Employee
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'employee_id' => 'EMP-' . uniqid(),
            'full_name' => $name, 'gender' => 'male', 'date_of_joining' => '2025-01-01',
            'pay_group_id' => $this->payGroup->id, 'status' => true, 'uan_number' => $uan, 'esi_ip_number' => $ip,
        ]);

        $items = [];
        foreach ($earnings as $code => $amount) {
            $items[$code] = ['calculated_value' => $amount, 'type' => 'earning'];
        }
        $items['PF'] = ['calculated_value' => $pf, 'type' => 'deduction', 'name' => 'Provident Fund', 'statutory_wages' => $pfWages[0], 'uncapped_wages' => $pfWages[1]];
        $items['ESI'] = ['calculated_value' => $esi, 'type' => 'deduction', 'name' => 'ESI', 'statutory_wages' => $esiWages];

        $gross = array_sum($earnings);
        $this->calcs[$employee->id] = [
            'items' => $items,
            'summary' => ['net_payout' => $gross - $pf - $esi, 'lop_days' => $lopDays, 'paid_days' => 31 - $lopDays, 'lop_deduction' => 0],
        ];

        return $employee;
    }

    private function postedRun(string $month = '2026-10'): PayrollRun
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'payroll_month' => $month,
            'start_date' => $start->toDateString(), 'end_date' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'locked',
        ]);
        app(PayrollAccountingService::class)->postPayrollRunJournal($run);

        return $run;
    }

    private function seedTwo(): array
    {
        // Above the ESI limit; PF on Basic capped at 15,000.
        $asha = $this->employee('Asha Rao', ['BASIC' => 20000, 'HRA' => 10000], 1800, [15000, 20000], 0, 0, 0, '100000000001', null);
        // Covered by both; two LOP days.
        $ravi = $this->employee('Ravi Kumar', ['BASIC' => 12000, 'HRA' => 6000], 1440, [12000, 12000], 135, 18000, 2, '100000000002', '3100000002');

        return [$asha, $ravi];
    }

    #[Test]
    public function posting_payroll_keeps_each_employees_pf_and_esi_split_for_the_returns(): void
    {
        [$asha, $ravi] = $this->seedTwo();
        $this->postedRun();

        $a = PayrollStatutoryLine::query()->where('employee_id', $asha->id)->firstOrFail();
        $this->assertSame(30000.0, $a->gross_wages);
        $this->assertSame([15000.0, 15000.0, 15000.0], [$a->epf_wages, $a->eps_wages, $a->edli_wages]);
        $this->assertSame([1800.0, 1250.0, 550.0], [$a->epf_employee, $a->eps_employer, $a->epf_employer_diff]); // 12% = 8.33% EPS + 3.67%
        $this->assertSame([0.0, 0.0], [$a->esi_wages, $a->esi_employee]);

        $r = PayrollStatutoryLine::query()->where('employee_id', $ravi->id)->firstOrFail();
        $this->assertSame([1440.0, 1000.0, 440.0], [$r->epf_employee, $r->eps_employer, $r->epf_employer_diff]);
        $this->assertSame([18000.0, 135.0, 585.0], [$r->esi_wages, $r->esi_employee, $r->esi_employer]);
        $this->assertSame([29.0, 2.0], [$r->paid_days, $r->ncp_days]);
        $this->assertSame('3100000002', $r->esi_ip_number);
    }

    #[Test]
    public function the_ecr_and_esic_sheet_are_in_the_portal_formats(): void
    {
        $this->seedTwo();
        $this->postedRun();
        $service = app(PayrollStatutoryService::class);
        $lines = $service->month('2026-10');

        $this->assertSame(
            "100000000001#~#ASHA RAO#~#30000#~#15000#~#15000#~#15000#~#1800#~#1250#~#550#~#0#~#0\n"
            . "100000000002#~#RAVI KUMAR#~#18000#~#12000#~#12000#~#12000#~#1440#~#1000#~#440#~#2#~#0",
            $service->ecr($lines),
        );
        $this->assertSame([['3100000002', 'Ravi Kumar', 29, 18000, 0, '']], $service->esiRows($lines));
        $this->assertSame([], $service->issues($lines));
    }

    #[Test]
    public function a_member_without_uan_is_flagged_and_left_out_of_the_ecr(): void
    {
        $this->employee('No Uan', ['BASIC' => 10000], 1200, [10000, 10000], 0, 0, 0, null, null);
        $this->postedRun();
        $service = app(PayrollStatutoryService::class);
        $lines = $service->month('2026-10');

        $this->assertSame('', $service->ecr($lines));
        $this->assertStringContainsString('No Uan', $service->issues($lines)[0]);
    }

    #[Test]
    public function an_older_run_is_backfilled_only_while_it_still_matches_the_books(): void
    {
        [$asha] = $this->seedTwo();
        $run = $this->postedRun('2026-09');
        $payroll = app(PayrollAccountingService::class);
        PayrollStatutoryLine::query()->delete(); // as if posted before the lines were kept

        $this->assertTrue($payroll->backfillStatutoryLines($run));
        $this->assertSame(2, PayrollStatutoryLine::query()->count());

        PayrollStatutoryLine::query()->delete();
        $this->calcs[$asha->id]['items']['PF']['calculated_value'] = 1500; // salary changed since posting
        $this->assertFalse($payroll->backfillStatutoryLines($run));
        $this->assertSame(0, PayrollStatutoryLine::query()->count());
    }

    #[Test]
    public function the_returns_page_and_downloads_work_for_hr(): void
    {
        $this->seedTwo();
        $this->postedRun();

        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'HR', 'email' => 'hr@factory.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'hr_manager')->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $http = $this->actingAs($user)->withHeader('X-Tenant', 'factory-co');

        $http->get(route('hrms.payroll.statutory-returns.index', ['month' => '2026-10']))->assertOk()->assertSee('Ravi Kumar');
        $ecr = $http->get(route('hrms.payroll.statutory-returns.ecr', ['month' => '2026-10']))->assertOk();
        $this->assertStringContainsString('100000000002#~#RAVI KUMAR', $ecr->getContent());
        $http->get(route('hrms.payroll.statutory-returns.esi', ['month' => '2026-10']))->assertOk();
        $http->get(route('hrms.payroll.statutory-returns.ecr', ['month' => '2026-08']))->assertNotFound();
    }
}
