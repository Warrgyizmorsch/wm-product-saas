<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\BudgetService;
use App\Domains\Accounting\Services\Dashboard\PartyBalances;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;

    /** @var array<string, ChartOfAccount> */
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = $this->makeTenant('test-tenant');
        $this->accounts = $this->makeAccounts($this->tenant);

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->accountant->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);

        // Last year too, so "previous period" dates early in January still have an open period.
        foreach ([now()->subYear(), now()] as $year) {
            app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
                'tenant_id' => $tenant->id,
                'name' => 'FY '.$year->year,
                'start_date' => $year->copy()->startOfYear()->toDateString(),
                'end_date' => $year->copy()->endOfYear()->toDateString(),
            ]);
        }

        return $tenant;
    }

    /** @return array<string, ChartOfAccount> */
    private function makeAccounts(Tenant $tenant): array
    {
        $make = fn (string $code, string $name, string $type, ?string $subtype, bool $cash = false) => ChartOfAccount::create([
            'tenant_id' => $tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => in_array($type, [ChartOfAccount::TYPE_ASSET, ChartOfAccount::TYPE_EXPENSE], true) ? ChartOfAccount::BALANCE_DEBIT : ChartOfAccount::BALANCE_CREDIT,
            'is_cash_or_bank' => $cash,
        ]);

        return [
            'bank' => $make('1020', 'HDFC Bank', ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET, true),
            'income' => $make('4010', 'Sales Revenue', ChartOfAccount::TYPE_INCOME, ChartOfAccount::SUBTYPE_DIRECT_INCOME),
            'cogs' => $make('5020', 'Cost of Sales', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_COGS),
            'rent' => $make('5200', 'Rent Expense', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_OPERATING_EXPENSE),
            'tds' => $make('2140', 'TDS Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            'suspense' => $make('2900', 'Suspense Account', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_SUSPENSE),
        ];
    }

    private function postJournal(Tenant $tenant, ChartOfAccount $debit, ChartOfAccount $credit, float $amount, ?Carbon $date = null): int
    {
        return app(JournalService::class)->post([
            ['chart_of_account_id' => $debit->id, 'debit' => $amount],
            ['chart_of_account_id' => $credit->id, 'credit' => $amount],
        ], ['tenant_id' => $tenant->id, 'journal_date' => $date ?? now()])->id;
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.dashboard', $query));
    }

    public function test_period_figures_ratios_and_comparison_with_the_previous_period(): void
    {
        $a = $this->accounts;
        $this->postJournal($this->tenant, $a['bank'], $a['income'], 5000);
        $this->postJournal($this->tenant, $a['cogs'], $a['bank'], 2000);
        $this->postJournal($this->tenant, $a['rent'], $a['bank'], 500);
        $this->postJournal($this->tenant, $a['rent'], $a['tds'], 100);
        $reversed = $this->postJournal($this->tenant, $a['rent'], $a['bank'], 300);
        app(JournalService::class)->reverse($reversed);
        // Falls in the previous 10-day window.
        $this->postJournal($this->tenant, $a['bank'], $a['income'], 1000, now()->subDays(15));

        $response = $this->dashboard([
            'preset' => 'custom',
            'from' => now()->subDays(9)->toDateString(),
            'to' => now()->toDateString(),
        ])->assertOk()->assertSee('Accounting Dashboard');

        $kpis = $response->viewData('kpis');
        $this->assertEquals(['current' => 5000, 'previous' => 1000, 'change' => 400.0], $kpis['income']);
        $this->assertEquals(2600, $kpis['expense']['current']);
        $this->assertNull($kpis['expense']['change']);
        $this->assertEquals(2400, $kpis['net_profit']['current']);

        $ratios = $response->viewData('ratios');
        $this->assertEquals(60.0, $ratios['gross_margin']);
        $this->assertEquals(48.0, $ratios['net_margin']);
        // Current assets = bank 3,500; current liabilities = TDS 100.
        $this->assertEquals(35.0, $ratios['current_ratio']);
        $this->assertEquals(3400, $ratios['working_capital']);

        $this->assertEquals(3500, $response->viewData('cash')['total']);
        $this->assertEquals(100, $response->viewData('tdsPayable'));
        $this->assertEquals(['label' => 'Cost of Sales', 'amount' => 2000], $response->viewData('expenseBreakdown')[0]);
        $this->assertCount(6, $response->viewData('trend')['labels']);
        $this->assertEquals(3500, $response->viewData('forecast')['closing']);
        $this->assertTrue($response->viewData('checklist')['trial_balance']['ok']);
    }

    public function test_checklist_flags_an_uncleared_suspense_balance(): void
    {
        $this->postJournal($this->tenant, $this->accounts['suspense'], $this->accounts['bank'], 50);

        $checklist = $this->dashboard()->assertOk()->viewData('checklist');

        $this->assertFalse($checklist['suspense']['ok']);
        $this->assertTrue($checklist['current_period']['ok']);
        $this->assertTrue($checklist['trial_balance']['ok']);
        // HDFC Bank has never been reconciled.
        $this->assertFalse($checklist['bank_reconciliation']['ok']);
    }

    public function test_budget_alerts_list_lines_at_or_over_the_warning_threshold(): void
    {
        $fiscalYear = \App\Domains\Accounting\Models\FiscalYear::query()->where('name', 'FY '.now()->year)->firstOrFail();
        $budget = app(BudgetService::class)->create([
            'tenant_id' => $this->tenant->id, 'fiscal_year_id' => $fiscalYear->id, 'name' => 'Operating Budget',
        ], [
            ['chart_of_account_id' => $this->accounts['rent']->id, 'amount' => 1000],
        ]);
        app(BudgetService::class)->approve($budget, $this->accountant->id);
        $this->postJournal($this->tenant, $this->accounts['rent'], $this->accounts['bank'], 900);

        $alerts = $this->dashboard()->assertOk()->viewData('budgetAlerts');

        $this->assertCount(1, $alerts);
        $this->assertSame('Rent Expense', $alerts[0]['account']);
        $this->assertEquals(90.0, $alerts[0]['percent']);
        $this->assertSame(BudgetService::STATUS_WARNING, $alerts[0]['status']);
    }

    public function test_aging_buckets_top_parties_and_amount_due_within_the_horizon(): void
    {
        $summary = app(PartyBalances::class)->summarize([
            ['party' => 'Acme', 'due_date' => '2026-04-10', 'amount' => 100],   // not due, inside 30 days
            ['party' => 'Acme', 'due_date' => '2026-03-15', 'amount' => 200],   // 16 days overdue
            ['party' => 'Birla', 'due_date' => '2026-01-15', 'amount' => 300],  // 75 days overdue
            ['party' => 'Cipla', 'due_date' => '2025-12-01', 'amount' => 400],  // 120 days overdue
            ['party' => 'Dabur', 'due_date' => '2026-06-30', 'amount' => 500],  // not due, beyond 30 days
        ], Carbon::parse('2026-03-31'));

        $this->assertEquals(1500, $summary['total']);
        $this->assertEquals(900, $summary['overdue']);
        $this->assertEquals(['not_due' => 600, '0_30' => 200, '31_60' => 0, '61_90' => 300, '90_plus' => 400], $summary['buckets']);
        $this->assertEquals(['name' => 'Dabur', 'amount' => 500], $summary['top'][0]);
        $this->assertEquals(1000, $summary['due_within_horizon']);
    }

    public function test_last_month_preset_compares_with_the_month_before(): void
    {
        $response = $this->dashboard(['preset' => 'last_month'])->assertOk();

        $period = $response->viewData('period');
        $this->assertTrue($period->from->equalTo(now()->subMonthNoOverflow()->startOfMonth()));
        $this->assertSame($period->days(), (int) round(abs($period->previousFrom->diffInDays($period->previousTo->copy()->startOfDay()))) + 1);
        $this->assertTrue($period->previousTo->copy()->addDay()->startOfDay()->equalTo($period->from));
    }

    public function test_another_tenants_journals_do_not_count(): void
    {
        $other = $this->makeTenant('other-tenant');
        app(TenantContext::class)->set($other);
        $otherAccounts = $this->makeAccounts($other);
        $this->postJournal($other, $otherAccounts['bank'], $otherAccounts['income'], 7000);
        app(TenantContext::class)->clear();

        $response = $this->dashboard()->assertOk();

        $this->assertEquals(0, $response->viewData('cash')['total']);
        $this->assertEquals(0, $response->viewData('kpis')['income']['current']);
        $this->assertTrue($response->viewData('recentJournals')->isEmpty());
    }

    public function test_a_user_without_report_access_is_forbidden(): void
    {
        $staff = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => bcrypt('password'),
        ]);

        $this->actingAs($staff)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.dashboard'))
            ->assertForbidden();
    }
}
