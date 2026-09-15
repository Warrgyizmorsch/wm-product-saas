<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\BudgetService;
use App\Domains\Accounting\Services\Dashboard\PartyBalances;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\HRMS\Models\Company;
use App\Exports\AccountingDashboardExport;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
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
        $this->accountant = $this->makeUser('accountant@example.com', 'accountant');
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

    private function makeUser(string $email, ?string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password'),
        ]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    /** @return array<string, ChartOfAccount> */
    private function makeAccounts(Tenant $tenant, ?int $companyId = null, string $codeSuffix = ''): array
    {
        $make = function (string $code, string $name, string $type, ?string $subtype, bool $cash = false) use ($tenant, $companyId, $codeSuffix) {
            $account = ChartOfAccount::create([
                'tenant_id' => $tenant->id,
                'code' => $code.$codeSuffix,
                'name' => $name,
                'type' => $type,
                'subtype' => $subtype,
                'normal_balance' => in_array($type, [ChartOfAccount::TYPE_ASSET, ChartOfAccount::TYPE_EXPENSE], true) ? ChartOfAccount::BALANCE_DEBIT : ChartOfAccount::BALANCE_CREDIT,
                'is_cash_or_bank' => $cash,
            ]);

            if ($companyId !== null) {
                $account->forceFill(['company_id' => $companyId])->save();
            }

            return $account;
        };

        return [
            'bank' => $make('1020', 'HDFC Bank', ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET, true),
            'income' => $make('4010', 'Sales Revenue', ChartOfAccount::TYPE_INCOME, ChartOfAccount::SUBTYPE_DIRECT_INCOME),
            'cogs' => $make('5020', 'Cost of Sales', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_COGS),
            'rent' => $make('5200', 'Rent Expense', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_OPERATING_EXPENSE),
            'tds' => $make('2140', 'TDS Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            'suspense' => $make('2900', 'Suspense Account', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_SUSPENSE),
        ];
    }

    private function postJournal(Tenant $tenant, ChartOfAccount $debit, ChartOfAccount $credit, float $amount, ?Carbon $date = null, ?int $costCenterId = null, ?int $companyId = null): int
    {
        return app(JournalService::class)->post([
            ['chart_of_account_id' => $debit->id, 'debit' => $amount, 'cost_center_id' => $costCenterId],
            ['chart_of_account_id' => $credit->id, 'credit' => $amount],
        ], array_filter([
            'tenant_id' => $tenant->id,
            'journal_date' => $date ?? now(),
            'company_id' => $companyId,
        ]))->id;
    }

    private function dashboard(array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->accountant)
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

    public function test_checklist_flags_negative_inventory_and_a_ledger_that_does_not_match_documents(): void
    {
        $account = fn (string $code, string $name) => ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name,
            'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);
        $inventory = $account('1200', 'Inventory');
        $receivable = $account('1100', 'Accounts Receivable');
        $a = $this->accounts;

        $this->postJournal($this->tenant, $a['bank'], $a['income'], 2000);
        $this->postJournal($this->tenant, $receivable, $a['income'], 1000);   // no invoice behind it
        $this->postJournal($this->tenant, $a['cogs'], $inventory, 500);       // stock issued, never received
        $this->postJournal($this->tenant, $a['rent'], $a['tds'], 1000);

        $response = $this->dashboard()->assertOk();

        $checklist = $response->viewData('checklist');
        $this->assertFalse($checklist['inventory']['ok']);
        $this->assertFalse($checklist['receivables_ledger']['ok']);
        $this->assertTrue($checklist['payables_ledger']['ok']);

        // Current assets 2,500 (bank 2,000 + AR 1,000 − inventory 500); liabilities 1,000.
        // Negative inventory is not subtracted, so quick never exceeds current.
        $ratios = $response->viewData('ratios');
        $this->assertEquals(2.5, $ratios['current_ratio']);
        $this->assertEquals(2.5, $ratios['quick_ratio']);
        $this->assertNull($ratios['dso']);
    }

    public function test_budget_alerts_list_lines_at_or_over_the_warning_threshold(): void
    {
        $fiscalYear = FiscalYear::query()->where('name', 'FY '.now()->year)->firstOrFail();
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
        $period = $this->dashboard(['preset' => 'last_month'])->assertOk()->viewData('period');

        $this->assertTrue($period->from->equalTo(now()->subMonthNoOverflow()->startOfMonth()));
        $this->assertSame($period->days(), (int) round(abs($period->previousFrom->diffInDays($period->previousTo->copy()->startOfDay()))) + 1);
        $this->assertTrue($period->previousTo->copy()->addDay()->startOfDay()->equalTo($period->from));
    }

    public function test_cost_center_filter_limits_income_and_expense_but_not_cash(): void
    {
        $operations = CostCenter::create(['tenant_id' => $this->tenant->id, 'code' => 'CC-OPS', 'name' => 'Operations', 'is_active' => true]);
        $a = $this->accounts;
        $this->postJournal($this->tenant, $a['bank'], $a['income'], 1000);
        $this->postJournal($this->tenant, $a['rent'], $a['bank'], 500, null, $operations->id);
        $this->postJournal($this->tenant, $a['rent'], $a['bank'], 300);

        $response = $this->dashboard(['cost_center_id' => $operations->id])->assertOk()->assertSee('CC-OPS');

        $this->assertEquals(500, $response->viewData('kpis')['expense']['current']);
        $this->assertEquals(0, $response->viewData('kpis')['income']['current']);
        $this->assertEquals(200, $response->viewData('cash')['total']);
        $this->assertSame($operations->id, $response->viewData('filters')['cost_center']->id);
    }

    public function test_consolidated_view_adds_every_company_and_warns_about_mixed_currencies(): void
    {
        app(TenantContext::class)->set($this->tenant);
        $alpha = Company::create(['company_name' => 'Alpha', 'status' => true, 'currency' => 'INR']);
        $beta = Company::create(['company_name' => 'Beta', 'status' => true, 'currency' => 'USD']);
        app(TenantContext::class)->clear();

        $alphaAccounts = $this->makeAccounts($this->tenant, $alpha->id, '-A');
        $betaAccounts = $this->makeAccounts($this->tenant, $beta->id, '-B');
        $this->postJournal($this->tenant, $alphaAccounts['bank'], $alphaAccounts['income'], 1000, null, null, $alpha->id);
        $this->postJournal($this->tenant, $betaAccounts['bank'], $betaAccounts['income'], 2000, null, null, $beta->id);

        $selected = $this->withSession(['company_id' => $alpha->id])->dashboard()->assertOk();
        $this->assertEquals(1000, $selected->viewData('kpis')['income']['current']);
        $this->assertFalse($selected->viewData('filters')['consolidated']);

        $consolidated = $this->withSession(['company_id' => $alpha->id])->dashboard(['company_scope' => 'all'])->assertOk();
        $this->assertEquals(3000, $consolidated->viewData('kpis')['income']['current']);
        $this->assertTrue($consolidated->viewData('filters')['consolidated']);
        $this->assertStringStartsWith('Warning', $consolidated->viewData('currencyNote'));
    }

    public function test_dashboard_is_cached_until_a_journal_is_posted(): void
    {
        $first = $this->dashboard()->assertOk();
        $this->assertEquals(0, $first->viewData('kpis')['income']['current']);

        $this->travel(5)->minutes();
        $this->assertTrue($first->viewData('generatedAt')->equalTo($this->dashboard()->viewData('generatedAt')));

        $this->postJournal($this->tenant, $this->accounts['bank'], $this->accounts['income'], 1000);

        $this->assertEquals(1000, $this->dashboard()->viewData('kpis')['income']['current']);
    }

    public function test_refresh_clears_the_cache_and_redirects_back_to_the_same_filters(): void
    {
        $this->dashboard(['preset' => 'last_month', 'refresh' => 1])
            ->assertRedirect(route('accounting.dashboard', ['preset' => 'last_month']));
    }

    public function test_exports_the_dashboard_as_pdf_and_excel(): void
    {
        $this->postJournal($this->tenant, $this->accounts['bank'], $this->accounts['income'], 1000);

        $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.dashboard.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        Excel::fake();
        $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.dashboard.export', ['format' => 'xlsx']))
            ->assertOk();

        $filename = sprintf('AccountingDashboard_%s_%s.xlsx', now()->startOfMonth()->format('Ymd'), now()->format('Ymd'));
        Excel::assertDownloaded($filename, fn (AccountingDashboardExport $export) => collect($export->array())
            ->contains(fn (array $row) => $row === ['Revenue', 1000.0, 0.0, '—']));
    }

    public function test_default_tab_and_journal_button_follow_the_users_role(): void
    {
        $owner = $this->makeUser('owner@example.com', 'tenant_owner');
        $auditor = $this->makeUser('auditor@example.com', 'auditor');

        $accountantView = $this->dashboard();
        $this->assertSame('operations', $accountantView->viewData('activeView'));
        $this->assertTrue($accountantView->viewData('canPostJournals'));

        $auditorView = $this->dashboard([], $auditor);
        $this->assertSame('operations', $auditorView->viewData('activeView'));
        $this->assertFalse($auditorView->viewData('canPostJournals'));
        $auditorView->assertDontSee('New Journal');

        $this->assertSame('overview', $this->dashboard([], $owner)->viewData('activeView'));
        $this->assertSame('overview', $this->dashboard(['view' => 'overview'])->viewData('activeView'));
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
        $staff = $this->makeUser('staff@example.com', null);

        $this->dashboard([], $staff)->assertForbidden();

        $this->actingAs($staff)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.dashboard.export', ['format' => 'pdf']))
            ->assertForbidden();
    }
}
