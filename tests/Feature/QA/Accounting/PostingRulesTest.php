<?php

namespace Tests\Feature\QA\Accounting;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Double-entry, period and segregation-of-duty rules, driven through the real
 * Journal and Voucher screens.
 */
class PostingRulesTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    private User $accountant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants(withBooks: true);

        $this->accountant = $this->userWithRole($this->tenantA, 'accountant');
        $this->owner = $this->userWithRole($this->tenantA, 'tenant_owner');
    }

    private function cash(): ChartOfAccount
    {
        return $this->accountFor($this->tenantA, '1010');
    }

    private function capital(): ChartOfAccount
    {
        return $this->accountFor($this->tenantA, '3010');
    }

    private function postJournal(User $user, array $items, array $extra = [])
    {
        return $this->as($user, $this->tenantA)->post(route('accounting.journals.store'), array_merge([
            'journal_date' => now()->toDateString(),
            'memo' => 'QA posting rules',
            'items' => $items,
        ], $extra));
    }

    private function journalCount(): int
    {
        return Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->count();
    }

    private function currentPeriod(): AccountingPeriod
    {
        return AccountingPeriod::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->firstOrFail();
    }

    public static function invalidJournals(): array
    {
        // Line shapes use placeholders resolved in the test: C = cash, K = capital.
        return [
            'single line' => [[['C', 100, 0]]],
            'unbalanced by one paisa' => [[['C', 100.00, 0], ['K', 0, 99.99]]],
            'unbalanced large amounts' => [[['C', 10000000, 0], ['K', 0, 9999999]]],
            'negative debit' => [[['C', -100, 0], ['K', 0, -100]]],
            'debit and credit on one line' => [[['C', 100, 100], ['K', 0, 0]]],
            'all-zero lines' => [[['C', 0, 0], ['K', 0, 0]]],
            'non-numeric amount' => [[['C', 'abc', 0], ['K', 0, 'abc']]],
        ];
    }

    #[DataProvider('invalidJournals')]
    public function test_an_invalid_journal_is_rejected_and_writes_nothing(array $shape): void
    {
        $items = array_map(fn ($l) => [
            'chart_of_account_id' => ($l[0] === 'C' ? $this->cash() : $this->capital())->id,
            'debit' => $l[1],
            'credit' => $l[2],
        ], $shape);

        $before = $this->journalCount();
        $this->postJournal($this->accountant, $items)->assertSessionHasErrors();

        $this->assertSame($before, $this->journalCount());
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_a_balanced_journal_posts_and_the_ledger_stays_in_balance(): void
    {
        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 1234.56, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 1234.56],
        ])->assertSessionHasNoErrors();

        $journal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->latest('id')->firstOrFail();
        $this->assertSame(Journal::STATUS_POSTED, $journal->status);
        $this->assertSame($this->currentPeriod()->id, (int) $journal->accounting_period_id);
        $this->assertSame($this->accountant->id, (int) $journal->posted_by);

        $this->assertLedgerBalances();
    }

    public function test_a_date_outside_every_fiscal_year_is_refused(): void
    {
        $before = $this->journalCount();

        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100],
        ], ['journal_date' => now()->addYears(3)->toDateString()])->assertSessionHasErrors();

        $this->assertSame($before, $this->journalCount());
    }

    public static function blockedPeriodActions(): array
    {
        return [
            'closed period' => ['close'],
            'locked period' => ['lock'],
        ];
    }

    #[DataProvider('blockedPeriodActions')]
    public function test_nothing_can_be_posted_into_a_closed_or_locked_period(string $action): void
    {
        $period = $this->currentPeriod();
        $this->as($this->owner, $this->tenantA)->post(route("accounting.periods.{$action}", $period->id));
        $this->assertNotSame(AccountingPeriod::STATUS_OPEN, $period->fresh()->status, "Owner could not {$action} the period");

        $before = $this->journalCount();
        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100],
        ])->assertSessionHasErrors();

        $this->as($this->accountant, $this->tenantA)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $this->assertSame($before, $this->journalCount(), "A journal landed in a {$action}d period");
    }

    public function test_a_closed_period_can_be_reopened_and_posting_resumes(): void
    {
        $period = $this->currentPeriod();
        $this->as($this->owner, $this->tenantA)->post(route('accounting.periods.close', $period->id));
        $this->as($this->owner, $this->tenantA)->post(route('accounting.periods.reopen', $period->id));

        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->fresh()->status);

        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100],
        ])->assertSessionHasNoErrors();
    }

    public function test_an_inactive_account_cannot_be_posted_to(): void
    {
        $dormant = $this->accountFor($this->tenantA, '5200');
        $dormant->forceFill(['is_active' => false])->save();

        $before = $this->journalCount();
        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $dormant->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->assertSame($before, $this->journalCount(), 'A journal posted to a deactivated account');
    }

    public function test_reversal_mirrors_the_original_and_cannot_be_repeated(): void
    {
        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 500],
        ]);
        $original = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->latest('id')->firstOrFail();

        $this->as($this->owner, $this->tenantA)->post(route('accounting.journals.reverse', $original), ['reason' => 'Entered twice']);
        $this->as($this->owner, $this->tenantA)->post(route('accounting.journals.reverse', $original), ['reason' => 'Again']);

        $original->refresh();
        $this->assertSame(Journal::STATUS_REVERSED, $original->status);
        $this->assertSame(2, $this->journalCount(), 'Reversing twice created a second reversal');

        $reversal = Journal::withoutGlobalScopes()->find($original->reversed_journal_id);
        $this->assertEquals(500, (float) $reversal->entries()->withoutGlobalScopes()->where('chart_of_account_id', $this->capital()->id)->value('debit'));
        $this->assertEquals(500, (float) $reversal->entries()->withoutGlobalScopes()->where('chart_of_account_id', $this->cash()->id)->value('credit'));

        $this->assertLedgerBalances();
        $this->assertEquals(0.0, $this->netBalance($this->cash()), 'Cash should net to zero after the reversal');
    }

    public function test_an_account_with_postings_or_a_system_account_cannot_be_deleted(): void
    {
        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 10, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 10],
        ]);

        $this->as($this->owner, $this->tenantA)->delete(route('accounting.chart-of-accounts.destroy', $this->cash()));
        $this->assertNotSoftDeleted('chart_of_accounts', ['id' => $this->cash()->id]);

        $this->as($this->owner, $this->tenantA)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '5999', 'name' => 'QA Temp', 'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $temp = $this->accountFor($this->tenantA, '5999');
        $this->as($this->owner, $this->tenantA)->delete(route('accounting.chart-of-accounts.destroy', $temp));
        $this->assertSoftDeleted('chart_of_accounts', ['id' => $temp->id]);
    }

    public function test_account_codes_are_unique_within_a_tenant(): void
    {
        $this->as($this->accountant, $this->tenantA)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1010', 'name' => 'Duplicate Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => 1,
        ]);

        $this->assertSame(1, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->where('code', '1010')->count());
    }

    public static function readOnlyRoles(): array
    {
        return ['auditor' => ['auditor'], 'read_only' => ['read_only'], 'sales_executive' => ['sales_executive']];
    }

    #[DataProvider('readOnlyRoles')]
    public function test_reviewer_and_non_finance_roles_cannot_post_or_change_the_books(string $roleSlug): void
    {
        $user = $this->userWithRole($this->tenantA, $roleSlug);
        $lines = [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100],
        ];

        $this->assertDenied($this->postJournal($user, $lines), 'journal');
        $this->assertDenied($this->as($user, $this->tenantA)->post(route('accounting.vouchers.payment.store'), [
            'voucher_date' => now()->toDateString(), 'items' => $lines,
        ]), 'voucher');
        $this->assertDenied($this->as($user, $this->tenantA)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '5998', 'name' => 'x', 'type' => 'expense', 'normal_balance' => 'debit',
        ]), 'coa');
        $this->assertDenied($this->as($user, $this->tenantA)->post(route('accounting.periods.close', $this->currentPeriod()->id)), 'period close');

        $this->assertSame(0, $this->journalCount());
    }

    public function test_the_accountant_cannot_close_periods_delete_accounts_or_reverse(): void
    {
        $this->assertDenied($this->as($this->accountant, $this->tenantA)->post(route('accounting.periods.close', $this->currentPeriod()->id)), 'close');
        $this->assertDenied($this->as($this->accountant, $this->tenantA)->delete(route('accounting.chart-of-accounts.destroy', $this->accountFor($this->tenantA, '5200'))), 'delete');

        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 10, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 10],
        ]);
        $journal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->latest('id')->firstOrFail();
        $this->assertDenied($this->as($this->accountant, $this->tenantA)->post(route('accounting.journals.reverse', $journal)), 'reverse');
    }

    public function test_an_oversized_voucher_or_memo_is_rejected(): void
    {
        $items = [];
        foreach (range(1, 21) as $i) {
            $items[] = ['chart_of_account_id' => $this->cash()->id, 'debit' => 1, 'credit' => 0];
        }
        $items[] = ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 21];

        $this->as($this->accountant, $this->tenantA)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => now()->toDateString(), 'items' => $items,
        ])->assertSessionHasErrors('items');

        $this->postJournal($this->accountant, [
            ['chart_of_account_id' => $this->cash()->id, 'debit' => 1, 'credit' => 0],
            ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 1],
        ], ['memo' => str_repeat('x', 501)])->assertSessionHasErrors('memo');

        $this->assertSame(0, $this->journalCount());
    }

    public function test_a_long_chain_of_mixed_vouchers_keeps_debits_equal_to_credits(): void
    {
        $bank = $this->accountFor($this->tenantA, '1020');
        $rent = $this->accountFor($this->tenantA, '5200');
        $income = $this->accountFor($this->tenantA, '4900');

        $post = fn (string $type, array $items) => $this->as($this->accountant, $this->tenantA)
            ->post(route("accounting.vouchers.{$type}.store"), ['voucher_date' => now()->toDateString(), 'items' => $items])
            ->assertSessionHasNoErrors();

        $post('receipt', [['chart_of_account_id' => $bank->id, 'debit' => 100000.10, 'credit' => 0], ['chart_of_account_id' => $this->capital()->id, 'debit' => 0, 'credit' => 100000.10]]);
        $post('contra', [['chart_of_account_id' => $this->cash()->id, 'debit' => 0.33, 'credit' => 0], ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 0.33]]);
        foreach (range(1, 15) as $i) {
            $post('payment', [['chart_of_account_id' => $rent->id, 'debit' => 0.1 * $i, 'credit' => 0], ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 0.1 * $i]]);
        }
        $post('receipt', [['chart_of_account_id' => $bank->id, 'debit' => 0.07, 'credit' => 0], ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 0.07]]);

        $this->assertLedgerBalances();
        $this->as($this->accountant, $this->tenantA)->get(route('accounting.reports.trial-balance'))->assertOk();
        $this->as($this->accountant, $this->tenantA)->get(route('accounting.reports.balance-sheet'))->assertOk()->assertSee('Balanced');
    }

    private function assertLedgerBalances(): void
    {
        $totals = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->selectRaw('ROUND(SUM(debit), 2) as d, ROUND(SUM(credit), 2) as c')
            ->first();

        $this->assertEquals((float) $totals->d, (float) $totals->c, 'Ledger out of balance: debits ' . $totals->d . ' vs credits ' . $totals->c);
    }

    private function netBalance(ChartOfAccount $account): float
    {
        $row = JournalEntry::withoutGlobalScopes()->where('chart_of_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->first();

        return round((float) $row->net, 2);
    }
}
