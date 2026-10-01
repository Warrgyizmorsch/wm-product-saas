<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalApprovalSettings;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\Accounting\Support\VoucherType;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Maker-checker for manual journals and vouchers.
 */
class JournalApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;   // maker
    private User $owner;        // checker
    private ChartOfAccount $bank;
    private ChartOfAccount $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = $this->makeUser('Asha Accountant', 'asha@acme.test', 'accountant');
        $this->owner = $this->makeUser('Omar Owner', 'omar@acme.test', 'tenant_owner');

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true,
        ]);
        $this->expense = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);
    }

    private function makeUser(string $name, string $email, string $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'acme');
    }

    private function enableApprovals(float $threshold = 0, bool $approversPostDirectly = true): void
    {
        app(JournalApprovalSettings::class)->update($this->tenant->id, [
            'enabled' => true, 'threshold' => $threshold, 'approvers_post_directly' => $approversPostDirectly,
        ]);
    }

    private function storeJournal(User $maker, float $amount = 500)
    {
        return $this->as($maker)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'memo' => 'Office repairs',
            'items' => [
                ['chart_of_account_id' => $this->expense->id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    private function ledgerDebitFor(ChartOfAccount $account): float
    {
        return (float) (app(JournalService::class)->balancesAsOf($this->tenant->id, now()->endOfYear())
            ->firstWhere('chart_of_account_id', $account->id)?->debit ?? 0);
    }

    public function test_with_approvals_off_manual_journals_post_directly(): void
    {
        $this->storeJournal($this->accountant)->assertRedirect();

        $this->assertSame(Journal::STATUS_POSTED, Journal::query()->sole()->status);
        $this->assertSame(500.0, $this->ledgerDebitFor($this->expense));
    }

    public function test_a_makers_journal_waits_for_approval_and_stays_out_of_the_ledger(): void
    {
        $this->enableApprovals();

        $this->storeJournal($this->accountant)->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'sent for approval'));

        $journal = Journal::query()->sole();
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->status);
        $this->assertNull($journal->posted_at);
        $this->assertSame($this->accountant->id, (int) $journal->posted_by);
        $this->assertSame(0.0, $this->ledgerDebitFor($this->expense));
    }

    public function test_a_second_person_approves_and_the_journal_posts(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->accountant);
        $journal = Journal::query()->sole();

        $this->as($this->owner)->post(route('accounting.approvals.approve', $journal))->assertRedirect()->assertSessionHas('success');

        $journal->refresh();
        $this->assertSame(Journal::STATUS_POSTED, $journal->status);
        $this->assertSame($this->owner->id, (int) $journal->approved_by);
        $this->assertNotNull($journal->approved_at);
        $this->assertNotNull($journal->posted_at);
        $this->assertSame(500.0, $this->ledgerDebitFor($this->expense));
        $this->assertDatabaseHas('accounting_audit_logs', ['subject_id' => $journal->id, 'event_type' => 'journal.approved']);
    }

    public function test_the_maker_can_never_approve_their_own_entry(): void
    {
        // Even an owner — who holds the approve permission — must not check their own work
        // once "approvers post directly" is off and their entry is held.
        $this->enableApprovals(approversPostDirectly: false);
        $this->storeJournal($this->owner);
        $journal = Journal::query()->sole();
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->status);

        $this->as($this->owner)->post(route('accounting.approvals.approve', $journal))->assertSessionHas('error');
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        app(JournalService::class)->approve($journal->id, $this->owner->id);
    }

    public function test_a_maker_without_the_approve_permission_cannot_open_or_use_the_queue(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->accountant);
        $journal = Journal::query()->sole();
        $other = $this->makeUser('Ravi Accountant', 'ravi@acme.test', 'accountant');

        $this->as($other)->get(route('accounting.approvals.index'))->assertForbidden();
        $this->as($other)->post(route('accounting.approvals.approve', $journal))->assertForbidden();
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->fresh()->status);
    }

    public function test_rejection_needs_a_reason_and_a_rejected_journal_never_posts(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->accountant);
        $journal = Journal::query()->sole();

        $this->as($this->owner)->post(route('accounting.approvals.reject', $journal), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->as($this->owner)->post(route('accounting.approvals.reject', $journal), ['reason' => 'Wrong expense head'])->assertSessionHas('success');

        $journal->refresh();
        $this->assertSame(Journal::STATUS_REJECTED, $journal->status);
        $this->assertSame('Wrong expense head', $journal->rejection_reason);
        $this->assertSame($this->owner->id, (int) $journal->rejected_by);

        $this->as($this->owner)->post(route('accounting.approvals.approve', $journal))->assertSessionHas('error');
        $this->assertSame(0.0, $this->ledgerDebitFor($this->expense));
    }

    public function test_entries_below_the_threshold_post_directly(): void
    {
        $this->enableApprovals(threshold: 10000);

        $this->storeJournal($this->accountant, 9999.99);
        $this->storeJournal($this->accountant, 10000);

        $this->assertSame(
            [Journal::STATUS_POSTED, Journal::STATUS_PENDING_APPROVAL],
            Journal::query()->orderBy('id')->pluck('status')->all()
        );
    }

    public function test_an_approvers_own_entry_posts_directly_unless_strict_mode_is_on(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->owner);
        $this->assertSame(Journal::STATUS_POSTED, Journal::query()->latest('id')->first()->status);

        $this->enableApprovals(approversPostDirectly: false);
        $this->storeJournal($this->owner);
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, Journal::query()->latest('id')->first()->status);
    }

    public function test_vouchers_from_the_voucher_screen_wait_but_automatic_postings_do_not(): void
    {
        $this->enableApprovals();

        $this->as($this->accountant)->post(route('accounting.vouchers.payment.store'), [
            'voucher_date' => now()->toDateString(),
            'party_name' => 'Electrician',
            'items' => [
                ['chart_of_account_id' => $this->expense->id, 'debit' => 800, 'credit' => 0],
                ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 800],
            ],
        ])->assertRedirect();

        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, Journal::query()->where('voucher_type', 'payment')->sole()->status);

        // e.g. bank reconciliation posting a voucher for a statement line
        $auto = app(VoucherService::class)->post(VoucherType::RECEIPT, [
            ['chart_of_account_id' => $this->bank->id, 'debit' => 300],
            ['chart_of_account_id' => $this->expense->id, 'credit' => 300],
        ], ['tenant_id' => $this->tenant->id, 'posted_by' => $this->accountant->id]);

        $this->assertSame(Journal::STATUS_POSTED, $auto->status);
    }

    public function test_approval_fails_if_the_period_closed_while_the_journal_waited(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->accountant);
        $journal = Journal::query()->sole();

        $periods = app(FiscalPeriodService::class);
        $periods->closePeriod($periods->periodForDate(now())->id);

        $this->as($this->owner)->post(route('accounting.approvals.approve', $journal))->assertSessionHas('error');
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->fresh()->status);
    }

    public function test_only_configurers_can_change_settings_and_the_queue_lists_pending_entries(): void
    {
        $this->as($this->accountant)->put(route('accounting.approvals.settings'), ['enabled' => 1, 'threshold' => 0])->assertForbidden();
        $this->assertFalse(app(JournalApprovalSettings::class)->for($this->tenant->id)['enabled']);

        $this->as($this->owner)->put(route('accounting.approvals.settings'), ['enabled' => 1, 'threshold' => 2500])->assertRedirect();
        $settings = app(JournalApprovalSettings::class)->for($this->tenant->id);
        $this->assertTrue($settings['enabled']);
        $this->assertSame(2500.0, $settings['threshold']);
        $this->assertFalse($settings['approvers_post_directly']); // unchecked box = off

        $this->storeJournal($this->accountant, 3000);
        $number = Journal::query()->sole()->journal_number;

        $this->as($this->owner)->get(route('accounting.approvals.index'))->assertOk()->assertSee($number)->assertSee('Asha Accountant');
        $this->as($this->owner)->get(route('accounting.journals.show', Journal::query()->sole()))->assertOk()->assertSee('Awaiting approval');
    }

    public function test_settings_and_queue_are_isolated_per_tenant(): void
    {
        $this->enableApprovals();
        $this->storeJournal($this->accountant);

        $other = Tenant::create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active', 'plan' => 'enterprise']);
        $this->assertFalse(app(JournalApprovalSettings::class)->for($other->id)['enabled']);

        app(\App\Core\Tenant\TenantContext::class)->set($other);
        $this->assertSame(0, Journal::query()->pendingApproval()->count());
    }
}
