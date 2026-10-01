<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankReconciliationRule;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\BankStatementMatch;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\BankReconciliationMatcher;
use App\Domains\Accounting\Services\BankReconciliationRuleService;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Support\VoucherType;
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
 * Narration rules, learning, auto-post, bulk posting as vouchers, splits.
 */
class BankReconciliationAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Auto Tenant', 'slug' => 'auto-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = $this->user('accountant', 'acc@auto.test');
        $this->bank = $this->account('1020');
        $this->actingAs($this->accountant);
    }

    #[Test]
    public function rules_match_on_narration_direction_and_amount(): void
    {
        $rules = app(BankReconciliationRuleService::class);
        $rec = $this->rec();
        $charges = $this->line($rec, 3, -590, 'SMS & SERVICE CHARGES INCL GST');
        $refund = $this->line($rec, 4, 590, 'SMS CHARGES REVERSAL');

        $rule = $this->rule(['pattern' => 'sms', 'direction' => 'out', 'max_amount' => 1000]);

        $this->assertTrue($rules->matches($rule, $charges));
        $this->assertFalse($rules->matches($rule, $refund), 'direction');
        $this->assertFalse($rules->matches($rule->fill(['max_amount' => 100]), $charges), 'amount range');
        $this->assertTrue($rules->matches($rule->fill(['max_amount' => null, 'match_type' => 'regex', 'pattern' => '^sms\s+&']), $charges));
        $this->assertTrue($rules->matches($rule->fill(['match_type' => 'starts_with', 'pattern' => 'sms service']), $charges), 'punctuation ignored');
    }

    #[Test]
    public function posting_a_line_teaches_a_rule_that_suggests_next_months_line(): void
    {
        $rec = $this->rec();
        $rent = $this->line($rec, 10, -18000, 'NEFT DR-HDFC00012-LANDMARK PROPERTIES RENT-UTR555');

        app(BankReconciliationService::class)->createAndMatch($rec, $rent->id, $this->account('5200')->id, $this->accountant->id, null, 'Landmark Properties');

        $learned = BankReconciliationRule::sole();
        $this->assertSame('landmark properties rent', $learned->pattern);
        $this->assertSame('learned', $learned->source);
        $this->assertSame('out', $learned->direction);
        $this->assertSame('Landmark Properties', $learned->party_name);

        // Next month: different UTR, same payee.
        $next = $this->line($rec, 28, -18000, 'NEFT DR-HDFC00099-LANDMARK PROPERTIES RENT-UTR999');
        $service = app(BankReconciliationRuleService::class);
        $suggestion = $service->suggestions(collect([$next]), $service->rulesFor($rec))[$next->id] ?? null;

        $this->assertNotNull($suggestion);
        $this->assertSame($this->account('5200')->id, $suggestion->target_account_id);
    }

    #[Test]
    public function auto_post_rules_post_payment_vouchers_during_auto_match(): void
    {
        $rec = $this->rec();
        $line = $this->line($rec, 12, -590, 'SMS & SERVICE CHARGES', 'SVC0912');
        $this->rule(['pattern' => 'service charges', 'direction' => 'out', 'auto_post' => true, 'party_name' => 'HDFC Bank', 'target_account_id' => $this->account('5570')->id]);

        $this->assertSame(1, app(BankReconciliationMatcher::class)->autoMatch($rec));

        $this->assertTrue($line->refresh()->is_matched);
        $match = BankStatementMatch::sole();
        $this->assertSame(BankStatementMatch::METHOD_RULE, $match->method);

        $journal = $match->journalEntry->journal->load('voucherDetail');
        $this->assertSame(VoucherType::PAYMENT, $journal->voucher_type);
        $this->assertStringStartsWith('PAY-', $journal->journal_number);
        $this->assertSame('HDFC Bank', $journal->voucherDetail->party_name);
        $this->assertSame('SVC0912', $journal->voucherDetail->reference_no);
        $this->assertSame(1, BankReconciliationRule::sole()->hits);
    }

    #[Test]
    public function selected_lines_are_posted_in_bulk_as_receipt_and_payment_vouchers(): void
    {
        $rec = $this->rec();
        $interest = $this->line($rec, 14, 125, 'SAVINGS INTEREST CREDIT');
        $upi = $this->line($rec, 15, -350, 'UPI/ZOMATO/STAFF LUNCH');
        $unknown = $this->line($rec, 16, -99, 'MISC');

        $this->http()->post(route('accounting.bank-reconciliation.bulk-post', $rec), ['items' => [
            $interest->id => ['account_id' => $this->account('4910')->id, 'party_name' => 'HDFC Bank', 'narration' => 'Interest Q2'],
            $upi->id => ['account_id' => $this->account('5500')->id, 'party_name' => 'Zomato'],
            $unknown->id => ['account_id' => ''],
        ]])->assertSessionHas('success', 'Posted 2 voucher(s) and matched them.')->assertSessionHas('import_errors');

        $this->assertTrue($interest->refresh()->is_matched);
        $this->assertTrue($upi->refresh()->is_matched);
        $this->assertFalse($unknown->refresh()->is_matched);

        $receipt = $interest->matches()->sole()->journalEntry->journal->load('voucherDetail');
        $this->assertSame(VoucherType::RECEIPT, $receipt->voucher_type);
        $this->assertSame('Interest Q2', $receipt->memo);

        $payment = $upi->matches()->sole()->journalEntry->journal->load('voucherDetail');
        $this->assertSame(VoucherType::PAYMENT, $payment->voucher_type);
        $this->assertSame('upi', $payment->voucherDetail->payment_method);

        $this->assertSame(2, BankReconciliationRule::where('source', 'learned')->count(), 'both choices are learned');
    }

    #[Test]
    public function one_bank_line_can_be_split_across_several_ledgers(): void
    {
        $rec = $this->rec();
        $line = $this->line($rec, 20, -1180, 'AMC CHARGES INCL GST');

        $this->http()->post(route('accounting.bank-reconciliation.create-and-match', $rec), [
            'statement_line_id' => $line->id,
            'party_name' => 'Tech Services',
            'splits' => [
                ['account_id' => $this->account('5530')->id, 'amount' => 1000],
                ['account_id' => $this->account('1630')->id, 'amount' => 180],
            ],
        ])->assertSessionHas('success', 'Voucher posted across 2 ledgers and matched.');

        $journal = $line->refresh()->matches()->sole()->journalEntry->journal->load('entries');
        $this->assertCount(3, $journal->entries);
        $this->assertEquals(1180.0, $journal->entries->firstWhere('chart_of_account_id', $this->bank->id)->credit);
        $this->assertEquals(180.0, $journal->entries->firstWhere('chart_of_account_id', $this->account('1630')->id)->debit);
        $this->assertSame(0, BankReconciliationRule::count(), 'split postings are not learned as a single-ledger rule');
    }

    #[Test]
    public function splits_that_do_not_add_up_are_refused(): void
    {
        $rec = $this->rec();
        $line = $this->line($rec, 20, -1180, 'AMC');

        $this->http()->post(route('accounting.bank-reconciliation.create-and-match', $rec), [
            'statement_line_id' => $line->id,
            'splits' => [['account_id' => $this->account('5530')->id, 'amount' => 1000], ['account_id' => $this->account('1630')->id, 'amount' => 100]],
        ])->assertSessionHas('error');

        $this->assertFalse($line->refresh()->is_matched);
        $this->assertSame(0, Journal::count());
    }

    #[Test]
    public function a_cash_withdrawal_to_cash_in_hand_is_a_contra_voucher(): void
    {
        $rec = $this->rec();
        $line = $this->line($rec, 5, -5000, 'ATM WDL SELF');

        app(BankReconciliationService::class)->createAndMatch($rec, $line->id, $this->account('1010')->id, $this->accountant->id);

        $journal = $line->refresh()->matches()->sole()->journalEntry->journal->load('voucherDetail');
        $this->assertSame(VoucherType::CONTRA, $journal->voucher_type);
        $this->assertSame('cash', $journal->voucherDetail->payment_method);
    }

    #[Test]
    public function the_rules_screen_lists_creates_and_protects_rules(): void
    {
        $this->http()->get(route('accounting.bank-rules.index'))->assertOk()->assertSee('Bank Rules');

        $this->http()->post(route('accounting.bank-rules.store'), [
            'name' => 'Bank charges', 'match_type' => 'contains', 'pattern' => 'charges', 'direction' => 'out',
            'target_account_id' => $this->account('5570')->id, 'auto_post' => 1, 'is_active' => 1,
        ])->assertSessionHas('success');

        $rule = BankReconciliationRule::sole();
        $this->assertTrue($rule->auto_post);
        $this->http()->get(route('accounting.bank-rules.index'))->assertSee('Bank charges');

        $this->http()->post(route('accounting.bank-rules.store'), [
            'name' => 'Bad', 'match_type' => 'regex', 'pattern' => '([', 'direction' => 'any', 'target_account_id' => $this->account('5570')->id,
        ])->assertSessionHasErrors('pattern');

        $auditor = $this->user('auditor', 'aud@auto.test');
        $this->actingAs($auditor)->withHeader('X-Tenant', $this->tenant->slug)->get(route('accounting.bank-rules.index'))->assertOk();
        $this->actingAs($auditor)->withHeader('X-Tenant', $this->tenant->slug)->delete(route('accounting.bank-rules.destroy', $rule))->assertForbidden();
        $this->assertSame(1, BankReconciliationRule::count());
    }

    #[Test]
    public function the_workspace_shows_rule_suggestions_and_the_bulk_dialog(): void
    {
        $rec = $this->rec();
        $this->line($rec, 12, -590, 'SMS & SERVICE CHARGES');
        $this->rule(['pattern' => 'service charges', 'target_account_id' => $this->account('5570')->id]);

        $this->http()->get(route('accounting.bank-reconciliation.show', $rec))
            ->assertOk()
            ->assertSee('5570 Bank Charges')
            ->assertSee('Post selected…');
    }

    // --------------------------------------------------------------- helpers

    private function rec(): BankReconciliation
    {
        return app(BankReconciliationService::class)->start([
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->bank->id,
            'statement_date' => $this->d(31),
            'closing_balance' => 0,
        ]);
    }

    private function line(BankReconciliation $rec, int $day, float $amount, string $description, ?string $reference = null): BankStatementLine
    {
        return BankStatementLine::create([
            'tenant_id' => $this->tenant->id,
            'bank_reconciliation_id' => $rec->id,
            'transaction_date' => $this->d($day),
            'description' => $description,
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }

    private function rule(array $attributes): BankReconciliationRule
    {
        return BankReconciliationRule::create($attributes + [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test rule',
            'match_type' => 'contains',
            'direction' => 'any',
            'target_account_id' => $this->account('5570')->id,
            'priority' => 100,
            'is_active' => true,
        ]);
    }

    private function d(int $day): string
    {
        return Carbon::create(now()->year, 3, 1)->addDays($day - 1)->toDateString();
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function user(string $role, string $email): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => ucfirst($role), 'email' => $email, 'password' => bcrypt('password')]);
        $roleModel = Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $roleModel->id, 'tenant_id' => $this->tenant->id]);
        $user->forceFill(['role_id' => $roleModel->id])->save();

        return $user;
    }

    private function http()
    {
        return $this->actingAs($this->accountant)->withHeader('X-Tenant', $this->tenant->slug);
    }
}
