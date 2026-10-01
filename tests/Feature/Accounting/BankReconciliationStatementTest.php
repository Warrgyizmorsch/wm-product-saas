<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\BankStatementMatch;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationMatcher;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\BankReconciliationStatementService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bank reconciliation, Tally BRS style: import → match (1:1, 1:many, with
 * difference, adjustment) → Bank Reconciliation Statement → complete/reopen.
 */
class BankReconciliationStatementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private User $owner;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = $this->makeTenant('brs-tenant');
        $this->seed(RbacSeeder::class);
        $this->provision($this->tenant);

        $this->accountant = $this->makeUser($this->tenant, 'accountant', 'accountant@brs.test');
        $this->owner = $this->makeUser($this->tenant, 'tenant_owner', 'owner@brs.test');

        $this->bank = $this->account('1020');
        $this->actingAs($this->accountant);
    }

    // ---------------------------------------------------------------- import

    #[Test]
    public function it_imports_withdrawal_and_deposit_columns_with_indian_dates(): void
    {
        $rec = $this->startRec(['statement_from_date' => $this->d(1), 'statement_date' => $this->d(31)]);

        $csv = "Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.\n"
            . Carbon::parse($this->d(2))->format('d/m/Y') . ",NEFT from customer,UTR555001,,\"45,000.00\"\n"
            . Carbon::parse($this->d(9))->format('d/m/Y') . ",Cheque paid,000123,\"12,500.00\",\n";

        $result = $this->service()->importStatement($rec, $this->csv($csv));

        $this->assertSame(2, $result['imported']);
        $lines = $rec->statementLines()->orderBy('transaction_date')->get();
        $this->assertSame($this->d(2), $lines[0]->transaction_date->toDateString());
        $this->assertSame(45000.0, $lines[0]->amount);
        $this->assertSame('UTR555001', $lines[0]->reference);
        $this->assertSame(-12500.0, $lines[1]->amount);
        $this->assertSame('000123', $lines[1]->reference);
    }

    #[Test]
    public function reimporting_the_same_statement_is_refused_but_identical_rows_in_one_file_are_kept(): void
    {
        $rec = $this->startRec();

        $csv = "date,description,amount\n{$this->d(5)},ATM WDL,-1500\n{$this->d(5)},ATM WDL,-1500\n{$this->d(6)},Interest,20\n";

        $first = $this->service()->importStatement($rec, $this->csv($csv));
        $this->assertSame(3, $first['imported'], 'two identical ATM withdrawals on one day are both real');

        try {
            $this->service()->importStatement($rec, $this->csv($csv));
            $this->fail('The same statement was imported twice.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already imported', $e->getMessage());
        }

        // The refused attempt leaves no empty "pending" upload behind.
        $this->assertSame(1, $rec->statementUploads()->count());
        $this->assertSame(3, $rec->statementLines()->count());
    }

    #[Test]
    public function lines_outside_the_statement_period_are_skipped(): void
    {
        $rec = $this->startRec(['statement_from_date' => $this->d(1), 'statement_date' => $this->d(15)]);

        $csv = "date,description,amount\n{$this->d(3)},In period,100\n{$this->d(20)},After statement date,200\n2019-06-17,Years ago,300\n";
        $result = $this->service()->importStatement($rec, $this->csv($csv));

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['out_of_period']);
    }

    #[Test]
    public function unmatched_lines_and_whole_uploads_can_be_deleted_but_matched_ones_cannot(): void
    {
        $rec = $this->startRec();
        $entry = $this->receipt(1000, $this->d(3));
        $result = $this->service()->importStatement($rec, $this->csv("date,description,amount\n{$this->d(3)},Deposit,1000\n{$this->d(4)},Junk,5\n"));

        [$deposit, $junk] = $rec->statementLines()->orderBy('transaction_date')->get()->all();
        $this->matcher()->match($rec, $deposit->id, [$entry->id]);

        try {
            $this->service()->deleteLines($rec, [$deposit->id]);
            $this->fail('A matched line was deleted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unmatch them first', $e->getMessage());
        }

        $this->assertSame(1, $this->service()->deleteLines($rec, [$junk->id]));

        $this->matcher()->unmatch($rec, $deposit->id);
        $this->assertSame(1, $this->service()->deleteUpload($rec, $result['upload']->id));
        $this->assertSame(0, $rec->statementLines()->count());
    }

    // -------------------------------------------------------------- matching

    #[Test]
    public function a_manual_match_must_agree_in_amount(): void
    {
        $rec = $this->startRec();
        $entry = $this->receipt(500, $this->d(3));
        $line = $this->line($rec, $this->d(3), 45000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Choose an account for the difference');
        $this->matcher()->match($rec, $line->id, [$entry->id]);
    }

    #[Test]
    public function one_deposit_can_clear_several_receipts(): void
    {
        $rec = $this->startRec();
        $a = $this->receipt(30000, $this->d(3));
        $b = $this->receipt(15000, $this->d(4));
        $line = $this->line($rec, $this->d(5), 45000, 'Deposit slip 17');

        $this->matcher()->match($rec, $line->id, [$a->id, $b->id], null, null, $this->accountant->id);

        $this->assertTrue($line->refresh()->is_matched);
        $this->assertSame(2, BankStatementMatch::where('bank_statement_line_id', $line->id)->count());
        foreach ([$a, $b] as $entry) {
            $entry->refresh();
            $this->assertTrue($entry->is_reconciled);
            $this->assertSame($this->d(5), $entry->bank_date->toDateString(), 'bank date = date the bank cleared it');
        }
    }

    #[Test]
    public function matching_with_a_difference_posts_it_to_the_chosen_account(): void
    {
        $rec = $this->startRec();
        $payment = $this->payment(10000, $this->d(3));
        // The bank took 10,025: the cheque plus a 25 charge.
        $line = $this->line($rec, $this->d(4), -10025);
        $charges = $this->account('5570');

        $this->matcher()->match($rec, $line->id, [$payment->id], $charges->id, 'Cheque return charges');

        $matches = BankStatementMatch::where('bank_statement_line_id', $line->id)->get();
        $this->assertCount(2, $matches);
        $this->assertEqualsWithDelta(-10025.0, $matches->sum('amount'), 0.001);

        $chargeEntry = JournalEntry::where('chart_of_account_id', $charges->id)->sole();
        $this->assertEquals(25.0, $chargeEntry->debit);
    }

    #[Test]
    public function an_adjustment_cannot_post_to_a_group_account(): void
    {
        $rec = $this->startRec();
        $line = $this->line($rec, $this->d(4), -450, 'Bank charges');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('group account');
        $this->matcher()->postAdjustment($rec, $line->id, $this->account('5000')->id);
    }

    #[Test]
    public function unmatching_reopens_the_line_and_its_entries(): void
    {
        $rec = $this->startRec();
        $entry = $this->receipt(700, $this->d(3));
        $line = $this->line($rec, $this->d(3), 700);
        $this->matcher()->match($rec, $line->id, [$entry->id]);

        $this->matcher()->unmatch($rec, $line->id);

        $this->assertFalse($line->refresh()->is_matched);
        $entry->refresh();
        $this->assertFalse($entry->is_reconciled);
        $this->assertNull($entry->bank_date);
        $this->assertSame(0, BankStatementMatch::count());
    }

    #[Test]
    public function auto_match_uses_the_cheque_number_to_pick_between_equal_amounts(): void
    {
        $rec = $this->startRec();
        $wrong = $this->payment(5000, $this->d(3), 'Cheque 000111 to Vendor A');
        $right = $this->payment(5000, $this->d(3), 'Cheque 000222 to Vendor B');
        $line = $this->line($rec, $this->d(8), -5000, 'CHQ PAID', '000222');

        $this->assertSame(1, $this->matcher()->autoMatch($rec));

        $this->assertSame($right->id, $line->refresh()->matched_journal_entry_id);
        $this->assertFalse($wrong->refresh()->is_reconciled);
    }

    #[Test]
    public function auto_match_leaves_ambiguous_lines_for_a_person(): void
    {
        $rec = $this->startRec();
        $this->receipt(2000, $this->d(3));
        $this->receipt(2000, $this->d(3));
        $line = $this->line($rec, $this->d(4), 2000, 'Cash deposit');

        $this->assertSame(0, $this->matcher()->autoMatch($rec));
        $this->assertFalse($line->refresh()->is_matched);
        $this->assertCount(2, $this->matcher()->suggestions(collect([$line]), $this->matcher()->candidates($rec))[$line->id]);
    }

    #[Test]
    public function entries_dated_after_the_statement_date_cannot_be_matched(): void
    {
        $rec = $this->startRec(['statement_date' => $this->d(15)]);
        $late = $this->receipt(300, $this->d(20));
        $line = $this->line($rec, $this->d(10), 300);

        $this->expectException(InvalidArgumentException::class);
        $this->matcher()->match($rec, $line->id, [$late->id]);
    }

    // ------------------------------------------------------------------- BRS

    #[Test]
    public function the_brs_lists_uncleared_cheques_and_agrees_with_the_bank(): void
    {
        // Books: +50,000 receipt, −8,000 cheque issued (not yet presented), both before the statement date.
        $receipt = $this->receipt(50000, $this->d(2));
        $this->payment(8000, $this->d(10), 'Cheque 000901');

        $rec = $this->startRec(['statement_date' => $this->d(31), 'opening_balance' => 0, 'closing_balance' => 49550]);
        $deposit = $this->line($rec, $this->d(3), 50000);
        $charges = $this->line($rec, $this->d(30), -450, 'SMS charges');

        $this->matcher()->match($rec, $deposit->id, [$receipt->id]);
        $this->matcher()->postAdjustment($rec, $charges->id, $this->account('5570')->id);

        $brs = app(BankReconciliationStatementService::class)->build($rec->refresh());

        $this->assertSame(41550.0, $brs['book_balance']);          // 50,000 − 8,000 − 450
        $this->assertSame(8000.0, $brs['cheques_not_presented_total']);
        $this->assertSame(0.0, $brs['deposits_not_credited_total']);
        $this->assertSame(49550.0, $brs['computed_bank_balance']); // 41,550 + 8,000
        $this->assertSame(0.0, $brs['difference']);

        $completed = $this->service()->complete($rec, $this->owner->id);
        $this->assertTrue($completed->isCompleted());
        $this->assertSame(41550.0, $completed->book_balance);
        $this->assertEquals(8000.0, $completed->brs_snapshot['cheques_not_presented_total']);
    }

    #[Test]
    public function the_cheque_cleared_next_month_drops_off_the_next_brs_but_stays_on_the_old_one(): void
    {
        $this->receipt(20000, $this->d(2));
        $cheque = $this->payment(8000, $this->d(10));

        $first = $this->startRec(['statement_date' => $this->d(31), 'opening_balance' => 0, 'closing_balance' => 20000]);
        $this->matcher()->autoMatch($first);
        $line = $this->line($first, $this->d(2), 20000);
        $this->matcher()->autoMatch($first);
        $this->assertTrue($line->refresh()->is_matched);
        $this->service()->complete($first, $this->owner->id);

        $nextMonth = Carbon::parse($this->d(31))->addDays(5)->toDateString();
        $second = $this->startRec(['statement_date' => Carbon::parse($this->d(31))->addMonth()->endOfMonth()->toDateString(), 'closing_balance' => 12000]);
        $this->assertSame(20000.0, $second->opening_balance, 'opening carried forward from the last closing balance');
        $clearing = $this->line($second, $nextMonth, -8000);
        $this->matcher()->match($second, $clearing->id, [$cheque->id]);

        $statement = app(BankReconciliationStatementService::class);
        $this->assertSame(0.0, $statement->build($second->refresh())['cheques_not_presented_total']);
        $this->assertSame(8000.0, $statement->build($first->refresh())['cheques_not_presented_total'], 'as on the first statement date the cheque was still outstanding');
        $this->assertSame(0.0, $statement->build($second)['difference']);
    }

    #[Test]
    public function completion_is_refused_when_books_and_bank_disagree(): void
    {
        $receipt = $this->receipt(1000, $this->d(3));

        // The statement is internally consistent (5,000 + 1,000 = 6,000) but the
        // books never had the 5,000 opening balance.
        $rec = $this->startRec(['opening_balance' => 5000, 'closing_balance' => 6000]);
        $line = $this->line($rec, $this->d(3), 1000);
        $this->matcher()->match($rec, $line->id, [$receipt->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not agree');
        $this->service()->complete($rec, $this->owner->id);
    }

    // -------------------------------------------------------------- lifecycle

    #[Test]
    public function only_one_open_reconciliation_per_account_and_dates_move_forward(): void
    {
        $rec = $this->startRec(['statement_date' => $this->d(15), 'closing_balance' => 0]);

        try {
            $this->startRec(['statement_date' => $this->d(31)]);
            $this->fail('Second open reconciliation was allowed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already has a reconciliation in progress', $e->getMessage());
        }

        $this->service()->complete($rec, $this->owner->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already reconciled up to');
        $this->startRec(['statement_date' => $this->d(10)]);
    }

    #[Test]
    public function only_the_latest_completed_reconciliation_can_be_reopened(): void
    {
        $first = $this->startRec(['statement_date' => $this->d(10)]);
        $this->service()->complete($first, $this->owner->id);
        $second = $this->startRec(['statement_date' => $this->d(20)]);
        $this->service()->complete($second, $this->owner->id);

        try {
            $this->service()->reopen($first->refresh(), $this->owner->id);
            $this->fail('An older reconciliation was reopened.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Only the latest', $e->getMessage());
        }

        $reopened = $this->service()->reopen($second->refresh(), $this->owner->id);
        $this->assertSame(BankReconciliation::STATUS_IN_PROGRESS, $reopened->status);
        $this->assertSame($this->owner->id, $reopened->reopened_by);
    }

    #[Test]
    public function a_reconciled_journal_cannot_be_reversed_until_unmatched(): void
    {
        $rec = $this->startRec();
        $entry = $this->receipt(900, $this->d(3));
        $line = $this->line($rec, $this->d(3), 900);
        $this->matcher()->match($rec, $line->id, [$entry->id]);

        try {
            app(JournalService::class)->reverse($entry->journal_id, 'oops');
            $this->fail('Reconciled journal was reversed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unmatch it there', $e->getMessage());
        }

        $this->matcher()->unmatch($rec, $line->id);
        $this->assertSame(Journal::STATUS_POSTED, app(JournalService::class)->reverse($entry->journal_id, 'oops')->status);
    }

    #[Test]
    public function reversed_journals_are_not_offered_or_outstanding(): void
    {
        $entry = $this->receipt(1234, $this->d(3));
        app(JournalService::class)->reverse($entry->journal_id, 'duplicate');

        $rec = $this->startRec(['statement_date' => Carbon::today()->toDateString() > $this->d(31) ? Carbon::today()->toDateString() : $this->d(31)]);

        $this->assertCount(0, $this->matcher()->candidates($rec));
        $this->assertSame(0.0, app(BankReconciliationStatementService::class)->build($rec)['deposits_not_credited_total']);
    }

    // ------------------------------------------------------------- HTTP / RBAC

    #[Test]
    public function the_workspace_renders_and_exports_the_brs(): void
    {
        $rec = $this->startRec();
        $this->receipt(1000, $this->d(3));
        $this->line($rec, $this->d(3), 1000, 'NEFT');

        $this->http($this->accountant)->get(route('accounting.bank-reconciliation.show', $rec))
            ->assertOk()
            ->assertSee('Balance as per Books')
            ->assertSee('Bank Reconciliation Statement as on');

        $this->http($this->accountant)->get(route('accounting.bank-reconciliation.export', [$rec, 'pdf']))->assertOk();
        $xlsx = $this->http($this->accountant)->get(route('accounting.bank-reconciliation.export', [$rec, 'xlsx']))->assertOk();

        // Zero amounts must be written as 0, not left blank (Laravel Excel
        // treats 0 as empty unless strict null comparison is on).
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx->baseResponse->getFile()->getPathname())->getActiveSheet();
        $values = collect($sheet->toArray(null, false, false))->keyBy(0);
        $this->assertSame(0.0, (float) $values['Add: Cheques issued / payments not yet presented'][1]);
        $this->assertNotNull($values['Add: Cheques issued / payments not yet presented'][1]);
        $this->assertSame(1000.0, (float) $values['Less: Cheques deposited / receipts not yet credited'][1], 'Less rows print as positive amounts');
    }

    #[Test]
    public function match_several_entries_over_http(): void
    {
        $rec = $this->startRec();
        $a = $this->receipt(600, $this->d(3));
        $b = $this->receipt(400, $this->d(3));
        $line = $this->line($rec, $this->d(4), 1000);

        $this->http($this->accountant)
            ->post(route('accounting.bank-reconciliation.match', $rec), ['statement_line_id' => $line->id, 'journal_entry_ids' => [$a->id, $b->id]])
            ->assertSessionHas('success');

        $this->assertTrue($line->refresh()->is_matched);

        $this->http($this->accountant)
            ->post(route('accounting.bank-reconciliation.unmatch', [$rec, $line->id]))
            ->assertSessionHas('success');

        $this->assertFalse($line->refresh()->is_matched);
    }

    #[Test]
    public function a_view_only_user_cannot_change_a_reconciliation(): void
    {
        $auditor = $this->makeUser($this->tenant, 'auditor', 'auditor@brs.test');
        $rec = $this->startRec();
        $line = $this->line($rec, $this->d(3), 100);

        $this->http($auditor)->get(route('accounting.bank-reconciliation.show', $rec))->assertOk();
        $this->http($auditor)->post(route('accounting.bank-reconciliation.delete-lines', $rec), ['line_ids' => [$line->id]])->assertForbidden();
        $this->http($auditor)->post(route('accounting.bank-reconciliation.complete', $rec))->assertForbidden();
        $this->assertSame(1, $rec->statementLines()->count());
    }

    #[Test]
    public function another_tenant_cannot_open_or_touch_the_reconciliation(): void
    {
        $rec = $this->startRec();

        $other = $this->makeTenant('other-brs-tenant');
        $this->provision($other);
        $intruder = $this->makeUser($other, 'tenant_owner', 'owner@other.test');

        $this->actingAs($intruder)->withHeader('X-Tenant', $other->slug)
            ->get(route('accounting.bank-reconciliation.show', $rec))
            ->assertNotFound();

        $this->actingAs($intruder)->withHeader('X-Tenant', $other->slug)
            ->post(route('accounting.bank-reconciliation.auto-match', $rec))
            ->assertNotFound();
    }

    // --------------------------------------------------------------- helpers

    /** A date in March of the current year (inside the test fiscal year). */
    private function d(int $day): string
    {
        return Carbon::create(now()->year, 3, 1)->addDays($day - 1)->toDateString();
    }

    private function startRec(array $overrides = []): BankReconciliation
    {
        return $this->service()->start($overrides + [
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->bank->id,
            'statement_date' => $this->d(31),
            // No opening balance: start() carries the last closing balance forward (0 for the first).
            'closing_balance' => 0,
        ]);
    }

    private function line(BankReconciliation $rec, string $date, float $amount, string $description = 'Line', ?string $reference = null): BankStatementLine
    {
        return BankStatementLine::create([
            'tenant_id' => $this->tenant->id,
            'bank_reconciliation_id' => $rec->id,
            'transaction_date' => $date,
            'description' => $description,
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }

    /** Dr Bank / Cr Capital — returns the bank entry. */
    private function receipt(float $amount, string $date, string $memo = 'Receipt'): JournalEntry
    {
        return $this->bankJournal($amount, $date, $memo, $this->account('3010'));
    }

    /** Dr Rent / Cr Bank — returns the bank entry. */
    private function payment(float $amount, string $date, string $memo = 'Payment'): JournalEntry
    {
        return $this->bankJournal(-$amount, $date, $memo, $this->account('5200'));
    }

    private function bankJournal(float $signed, string $date, string $memo, ChartOfAccount $other): JournalEntry
    {
        $amount = abs($signed);
        $lines = $signed > 0
            ? [['chart_of_account_id' => $this->bank->id, 'debit' => $amount, 'credit' => 0, 'description' => $memo], ['chart_of_account_id' => $other->id, 'debit' => 0, 'credit' => $amount]]
            : [['chart_of_account_id' => $other->id, 'debit' => $amount, 'credit' => 0], ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => $amount, 'description' => $memo]];

        $journal = app(JournalService::class)->post($lines, ['tenant_id' => $this->tenant->id, 'journal_date' => $date, 'memo' => $memo]);

        return $journal->entries->firstWhere('chart_of_account_id', $this->bank->id);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statement.csv', $content);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function service(): BankReconciliationService
    {
        return app(BankReconciliationService::class);
    }

    private function matcher(): BankReconciliationMatcher
    {
        return app(BankReconciliationMatcher::class);
    }

    private function http(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', $this->tenant->slug);
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);
    }

    private function provision(Tenant $tenant): void
    {
        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    private function makeUser(Tenant $tenant, string $roleSlug, string $email): User
    {
        $user = User::create(['tenant_id' => $tenant->id, 'name' => ucfirst($roleSlug), 'email' => $email, 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }
}
