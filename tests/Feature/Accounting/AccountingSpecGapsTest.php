<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\JournalRepository;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\YearEndClosingService;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Year-end close/reopen, post-once idempotency, re-posting after a reversal,
 * the separate reopen permission, fiscal-year overlap, and the project
 * dimension on journal lines.
 */
class AccountingSpecGapsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private FiscalYear $lastYear;
    private FiscalYear $thisYear;
    private ChartOfAccount $bank;
    private ChartOfAccount $sales;
    private ChartOfAccount $rent;
    private ChartOfAccount $reserves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $periods = app(FiscalPeriodService::class);
        $this->lastYear = $periods->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . (now()->year - 1),
            'start_date' => now()->subYear()->startOfYear()->toDateString(),
            'end_date' => now()->subYear()->endOfYear()->toDateString(),
        ]);
        $this->thisYear = $periods->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->owner = $this->makeUser('Omar Owner', 'omar@acme.test', Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail());

        $this->bank = $this->account('1020', 'Bank', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_DEBIT, ['is_cash_or_bank' => true]);
        $this->sales = $this->account('4010', 'Sales', ChartOfAccount::TYPE_INCOME, ChartOfAccount::BALANCE_CREDIT, ['subtype' => ChartOfAccount::SUBTYPE_DIRECT_INCOME]);
        $this->rent = $this->account('5200', 'Rent', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT, ['subtype' => ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE]);
        $this->reserves = $this->account('3020', 'Reserves & Surplus', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::BALANCE_CREDIT);
    }

    private function account(string $code, string $name, string $type, string $normal, array $extra = []): ChartOfAccount
    {
        return ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $normal,
        ] + $extra);
    }

    private function makeUser(string $name, string $email, Role $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'acme');
    }

    private function journals(): JournalService
    {
        return app(JournalService::class);
    }

    /** Sale of 1,000 and rent of 600 in last year → 400 profit. */
    private function bookLastYearTrading(): void
    {
        $date = now()->subYear()->setDate(now()->year - 1, 6, 15)->toDateString();

        $this->journals()->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 1000],
            ['chart_of_account_id' => $this->sales->id, 'credit' => 1000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => $date]);

        $this->journals()->post([
            ['chart_of_account_id' => $this->rent->id, 'debit' => 600],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 600],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => $date]);
    }

    /** debit minus credit for one account, cumulative to $asOf. */
    private function balance(ChartOfAccount $account, Carbon $asOf, bool $excludeYearEndClose = false): float
    {
        $row = $this->journals()->balancesAsOf($this->tenant->id, $asOf, $excludeYearEndClose)->firstWhere('chart_of_account_id', $account->id);

        return round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0), 2);
    }

    private function lastYearEnd(): Carbon
    {
        return Carbon::parse($this->lastYear->end_date)->endOfDay();
    }

    // ---- Year-end closing -------------------------------------------------

    public function test_closing_a_year_moves_its_profit_into_reserves_and_zeroes_income_and_expense(): void
    {
        $this->bookLastYearTrading();

        $this->as($this->owner)->post(route('accounting.fiscal-years.close', $this->lastYear))
            ->assertRedirect()->assertSessionHasNoErrors();

        $closing = Journal::query()->where('source', Journal::SOURCE_YEAR_END_CLOSE)->sole();
        $this->assertSame($this->lastYear->end_date->toDateString(), $closing->journal_date->toDateString());
        $this->assertSame(YearEndClosingService::REFERENCE_TYPE, $closing->reference_type);

        $this->assertSame(0.0, $this->balance($this->sales, $this->lastYearEnd()));
        $this->assertSame(0.0, $this->balance($this->rent, $this->lastYearEnd()));
        $this->assertSame(-400.0, $this->balance($this->reserves, $this->lastYearEnd()), 'profit of 400 credited to Reserves');
        $this->assertSame(400.0, $this->balance($this->bank, $this->lastYearEnd()), 'balance sheet accounts untouched');

        $this->lastYear->refresh();
        $this->assertSame(FiscalYear::STATUS_CLOSED, $this->lastYear->status);
        $this->assertSame(0, $this->lastYear->periods()->where('status', AccountingPeriod::STATUS_OPEN)->count());
    }

    public function test_reports_still_show_the_closed_years_profit(): void
    {
        $this->bookLastYearTrading();
        app(YearEndClosingService::class)->close($this->lastYear, $this->owner->id);

        // P&L / Trial Balance for June read the trading, not the closing entry.
        $june = AccountingPeriod::query()->where('fiscal_year_id', $this->lastYear->id)->where('name', 'like', 'June%')->sole();
        $rows = $this->journals()->trialBalance($june)->keyBy('chart_of_account_id');
        $this->assertSame(1000.0, (float) $rows[$this->sales->id]->credit);

        $december = AccountingPeriod::query()->where('fiscal_year_id', $this->lastYear->id)->where('name', 'like', 'December%')->sole();
        $this->assertTrue($this->journals()->trialBalance($december)->isEmpty(), 'closing entry is kept out of the pre-closing trial balance');

        // Cash Flow view of the ledger keeps income/expense as they were.
        $this->assertSame(-1000.0, $this->balance($this->sales, $this->lastYearEnd(), excludeYearEndClose: true));
    }

    public function test_a_year_cannot_be_closed_while_an_earlier_year_is_open(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Close fiscal year '{$this->lastYear->name}' first");

        app(YearEndClosingService::class)->close($this->thisYear, $this->owner->id);
    }

    public function test_reopening_a_year_reverses_the_closing_entry_on_the_year_end_and_it_can_be_closed_again(): void
    {
        $this->bookLastYearTrading();
        $yearEnd = app(YearEndClosingService::class);
        $yearEnd->close($this->lastYear, $this->owner->id);

        $this->as($this->owner)->post(route('accounting.fiscal-years.reopen', $this->lastYear))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(FiscalYear::STATUS_OPEN, $this->lastYear->fresh()->status);
        $this->assertSame(-1000.0, $this->balance($this->sales, $this->lastYearEnd()));
        $this->assertSame(0.0, $this->balance($this->reserves, $this->lastYearEnd()));

        $reversal = Journal::query()->where('source', Journal::SOURCE_YEAR_END_CLOSE)->where('status', Journal::STATUS_POSTED)->sole();
        $this->assertSame($this->lastYear->end_date->toDateString(), $reversal->journal_date->toDateString());

        // Periods stay closed until someone reopens the one they need.
        $this->assertSame(0, $this->lastYear->periods()->where('status', AccountingPeriod::STATUS_OPEN)->count());

        $yearEnd->close($this->lastYear->fresh(), $this->owner->id);
        $this->assertSame(-400.0, $this->balance($this->reserves, $this->lastYearEnd()));
        $this->assertSame(3, Journal::query()->where('source', Journal::SOURCE_YEAR_END_CLOSE)->count());
    }

    public function test_the_closing_journal_cannot_be_reversed_by_hand(): void
    {
        $this->bookLastYearTrading();
        app(YearEndClosingService::class)->close($this->lastYear, $this->owner->id);
        $closing = Journal::query()->where('source', Journal::SOURCE_YEAR_END_CLOSE)->sole();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reopen the fiscal year');
        $this->journals()->reverse($closing->id);
    }

    public function test_a_period_in_a_closed_year_cannot_be_reopened(): void
    {
        app(YearEndClosingService::class)->close($this->lastYear, $this->owner->id);
        $period = $this->lastYear->periods()->firstOrFail();

        $this->as($this->owner)->post(route('accounting.periods.reopen', $period))
            ->assertSessionHasErrors('period');

        $this->assertSame(AccountingPeriod::STATUS_CLOSED, $period->fresh()->status);
    }

    public function test_a_locked_period_cannot_be_reopened(): void
    {
        $periods = app(FiscalPeriodService::class);
        $period = $this->thisYear->periods()->firstOrFail();
        $periods->closePeriod($period->id);
        $periods->lockPeriod($period->id);

        $this->expectException(InvalidArgumentException::class);
        $periods->reopenPeriod($period->id);
    }

    // ---- Reopen permission ------------------------------------------------

    public function test_closing_periods_does_not_grant_reopening_them(): void
    {
        $role = Role::create(['tenant_id' => $this->tenant->id, 'name' => 'Period Closer', 'slug' => 'period_closer', 'level' => 50]);
        foreach (['accounting.periods.view', 'accounting.periods.manage', 'accounting.fiscal_years.view'] as $name) {
            RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => Permission::query()->where('name', $name)->value('id'),
                'scope' => RolePermission::SCOPE_TENANT,
            ]);
        }
        $closer = $this->makeUser('Cara Closer', 'cara@acme.test', $role);
        $period = $this->thisYear->periods()->firstOrFail();

        $this->as($closer)->post(route('accounting.periods.close', $period))->assertRedirect();
        $this->assertSame(AccountingPeriod::STATUS_CLOSED, $period->fresh()->status);

        $this->as($closer)->post(route('accounting.periods.reopen', $period))->assertForbidden();
        $this->as($this->owner)->post(route('accounting.periods.reopen', $period))->assertRedirect();
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->fresh()->status);
    }

    // ---- Post once / re-post after reversal -------------------------------

    private function postInvoice(int $invoiceId, float $amount = 250): Journal
    {
        return $this->journals()->postOnce([
            ['chart_of_account_id' => $this->bank->id, 'debit' => $amount],
            ['chart_of_account_id' => $this->sales->id, 'credit' => $amount],
        ], ['tenant_id' => $this->tenant->id, 'reference_type' => 'Invoice', 'reference_id' => $invoiceId]);
    }

    public function test_posting_the_same_document_twice_creates_one_journal(): void
    {
        $first = $this->postInvoice(7);
        $second = $this->postInvoice(7);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Journal::query()->where('reference_type', 'Invoice')->count());
        $this->assertSame('Invoice:7', $first->idempotency_key);
    }

    public function test_a_document_can_be_posted_again_after_its_journal_is_reversed(): void
    {
        $original = $this->postInvoice(7);
        $reversal = $this->journals()->reverse($original->id, 'Invoice cancelled');

        $this->assertNull($original->fresh()->idempotency_key);
        $this->assertNull($reversal->idempotency_key);
        $this->assertNull($this->journals()->activePosting($this->tenant->id, 'Invoice', 7), 'a reversal is not a standing posting');

        $reposted = $this->postInvoice(7, 300);

        $this->assertNotSame($original->id, $reposted->id);
        $this->assertSame('Invoice:7', $reposted->idempotency_key);
        $this->assertSame($reposted->id, $this->journals()->activePosting($this->tenant->id, 'Invoice', 7)?->id);
    }

    public function test_the_database_rejects_a_second_journal_with_the_same_key(): void
    {
        $this->postInvoice(7);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->journals()->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 250],
            ['chart_of_account_id' => $this->sales->id, 'credit' => 250],
        ], ['tenant_id' => $this->tenant->id, 'idempotency_key' => 'Invoice:7']);
    }

    public function test_a_worker_that_loses_the_race_gets_the_winners_journal(): void
    {
        $winner = $this->postInvoice(7);

        // The loser's "already posted?" check ran before the winner committed,
        // so it saw nothing; its insert then hits the unique key.
        $this->app->bind(JournalRepositoryInterface::class, fn () => new class extends JournalRepository {
            private bool $checked = false;

            public function activeForReference(int $tenantId, string $referenceType, int $referenceId): ?Journal
            {
                if (! $this->checked) {
                    $this->checked = true;

                    return null;
                }

                return parent::activeForReference($tenantId, $referenceType, $referenceId);
            }
        });
        $this->app->forgetInstance(JournalService::class);

        $loser = $this->postInvoice(7);

        $this->assertSame($winner->id, $loser->id);
        $this->assertSame(1, Journal::query()->where('reference_type', 'Invoice')->count());
    }

    public function test_changing_an_opening_balance_twice_leaves_one_standing_opening_journal(): void
    {
        $service = app(ChartOfAccountsService::class);
        $this->bank->update(['opening_balance' => 100, 'opening_balance_type' => ChartOfAccount::BALANCE_DEBIT]);
        $service->syncOpeningBalance($this->bank->fresh());
        $this->bank->update(['opening_balance' => 150]);
        $service->syncOpeningBalance($this->bank->fresh());
        $this->bank->update(['opening_balance' => 175]);
        $service->syncOpeningBalance($this->bank->fresh());

        $standing = $this->journals()->activePosting($this->tenant->id, 'chart_of_account_opening_balance', $this->bank->id);
        $this->assertSame(175.0, (float) $standing->total_debit);
        $this->assertSame(1, Journal::query()
            ->where('reference_type', 'chart_of_account_opening_balance')
            ->where('status', Journal::STATUS_POSTED)
            ->whereNotNull('idempotency_key')
            ->count());
    }

    // ---- Fiscal year overlap ----------------------------------------------

    public function test_overlapping_fiscal_years_are_rejected(): void
    {
        $this->as($this->owner)->post(route('accounting.fiscal-years.store'), [
            'name' => 'Overlap',
            'start_date' => now()->setDate(now()->year, 7, 1)->toDateString(),
            'end_date' => now()->setDate(now()->year + 1, 6, 30)->toDateString(),
        ])->assertSessionHasErrors('start_date');

        $this->assertSame(2, FiscalYear::query()->count());
    }

    // ---- Project dimension ------------------------------------------------

    private function project(int $tenantId, string $code): int
    {
        return DB::table('projects')->insertGetId([
            'tenant_id' => $tenantId, 'project_code' => $code, 'name' => "Project {$code}",
            'start_date' => now()->toDateString(), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_journal_lines_carry_a_project_and_the_trial_balance_filters_by_it(): void
    {
        $projectId = $this->project($this->tenant->id, 'P-1');

        $this->as($this->owner)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $this->rent->id, 'project_id' => $projectId, 'debit' => 80, 'credit' => 0],
                ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 80],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame($projectId, JournalEntry::query()->where('chart_of_account_id', $this->rent->id)->sole()->project_id);

        $period = app(FiscalPeriodService::class)->periodForDate(now());
        $rows = $this->journals()->trialBalance($period, projectId: $projectId);
        $this->assertSame([$this->rent->id], $rows->pluck('chart_of_account_id')->all());

        $this->as($this->owner)->get(route('accounting.reports.trial-balance', ['project_id' => $projectId]))->assertOk()->assertSee('Project P-1');
    }

    public function test_reversal_keeps_the_project_and_another_tenants_project_is_rejected(): void
    {
        $projectId = $this->project($this->tenant->id, 'P-1');
        $journal = $this->journals()->post([
            ['chart_of_account_id' => $this->rent->id, 'project_id' => $projectId, 'debit' => 80],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 80],
        ], ['tenant_id' => $this->tenant->id]);

        $reversal = $this->journals()->reverse($journal->id);
        $this->assertSame($projectId, $reversal->entries->firstWhere('chart_of_account_id', $this->rent->id)->project_id);

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        $foreignProject = $this->project($other->id, 'X-1');

        $this->as($this->owner)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $this->rent->id, 'project_id' => $foreignProject, 'debit' => 80, 'credit' => 0],
                ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 80],
            ],
        ])->assertSessionHasErrors('items.0.project_id');
    }
}
