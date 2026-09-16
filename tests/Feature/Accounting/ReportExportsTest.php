<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\ReportTables;
use App\Exports\AccountingReportExport;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        $this->accountant = $this->makeUser('Accountant', 'accountant@acme.test', 'accountant');

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $account = fn (string $code, string $name, string $type, string $subtype, bool $cash = false) => ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
            'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit', 'is_cash_or_bank' => $cash,
        ]);
        $this->bank = $account('1020', 'Bank Account', 'asset', 'current_asset', true);
        $income = $account('4010', 'Sales Revenue', 'income', 'direct_income');
        $rent = $account('5200', 'Rent Expense', 'expense', 'operating_expense');

        $journals = app(JournalService::class);
        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 5000],
            ['chart_of_account_id' => $income->id, 'credit' => 5000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now(), 'posted_by' => $this->accountant->id]);
        $journals->post([
            ['chart_of_account_id' => $rent->id, 'debit' => 1200],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 1200],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now(), 'posted_by' => $this->accountant->id]);
    }

    private function makeUser(string $name, string $email, ?string $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);

        if ($role !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    private function export(string $report, string $format, array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->accountant)
            ->withHeader('X-Tenant', 'acme')
            ->get(route('accounting.reports.export', ['report' => $report, 'format' => $format] + $query));
    }

    public function test_every_report_downloads_as_pdf(): void
    {
        $queries = ['general-ledger' => ['chart_of_account_id' => $this->bank->id]];

        foreach (array_keys(ReportTables::TITLES) as $report) {
            $response = $this->export($report, 'pdf', $queries[$report] ?? []);

            $this->assertSame(200, $response->status(), "{$report} PDF export failed: ".substr((string) $response->getContent(), 0, 300));
            $this->assertSame('application/pdf', $response->headers->get('content-type'), $report);
        }
    }

    public function test_excel_exports_contain_the_reports_figures(): void
    {
        Excel::fake();
        $today = now()->format('Ymd');

        $this->export('trial-balance', 'xlsx')->assertOk();
        Excel::assertDownloaded("TrialBalance_{$today}.xlsx", fn (AccountingReportExport $export) => collect($export->array())
            ->contains(fn (array $row) => ($row[1] ?? null) === 'Sales Revenue' && ($row[3] ?? null) === 5000.0));

        $this->export('general-ledger', 'xlsx', ['chart_of_account_id' => $this->bank->id])->assertOk();
        Excel::assertDownloaded("GeneralLedger_{$today}.xlsx", fn (AccountingReportExport $export) => collect($export->array())
            ->contains(fn (array $row) => ($row[2] ?? null) === 'Closing balance' && ($row[5] ?? null) === 3800.0));

        $this->export('vouchers-by-staff', 'xlsx')->assertOk();
        Excel::assertDownloaded("VouchersByStaff_{$today}.xlsx", fn (AccountingReportExport $export) => collect($export->array())
            ->contains(fn (array $row) => ($row[0] ?? null) === 'Accountant' && in_array(2, $row, true)));
    }

    public function test_exports_follow_each_reports_own_permission_check(): void
    {
        $staff = $this->makeUser('Staff', 'staff@acme.test', null);

        $this->export('trial-balance', 'pdf', [], $staff)->assertForbidden();
        $this->export('budget-vs-actual', 'xlsx', [], $staff)->assertForbidden();
    }

    public function test_unknown_report_is_not_found(): void
    {
        $this->export('payroll-secrets', 'pdf')->assertNotFound();
    }

    public function test_report_pages_offer_export_buttons_that_keep_the_filters(): void
    {
        $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'acme')
            ->get(route('accounting.reports.general-ledger', ['chart_of_account_id' => $this->bank->id]))
            ->assertOk()
            ->assertSee(route('accounting.reports.export', ['report' => 'general-ledger', 'format' => 'xlsx', 'chart_of_account_id' => $this->bank->id]), false);
    }
}
