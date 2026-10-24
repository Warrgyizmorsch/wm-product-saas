<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\BillSettlement;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\BillWise\BillSettlementService;
use App\Domains\Accounting\Services\BillWise\OpeningBreakupService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalApprovalSettings;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerOpeningBalanceService;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bill-wise, phase 2: any bill-wise ledger is tracked, vouchers pick the
 * bills they settle, On Account money is adjusted against bills later, and
 * the register always equals each party's ledger balance.
 */
class BillSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private User $user;
    private Customer $customer;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 23, 12));
        BillAllocationService::resetSchemaCache();

        $this->tenant = Tenant::create(['name' => 'Settle Co', 'slug' => 'settle-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@settle.example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->company = Company::create(['company_name' => 'Settle Co']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $this->customer = Customer::create(['name' => 'Ravi Traders', 'email' => 'ravi@example.com', 'company_id' => $this->company->id]);
        $this->vendor = Vendor::create(['name' => 'Steel Mills', 'code' => 'V-STEEL', 'status' => 'active', 'company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $key): ChartOfAccount
    {
        return ChartOfAccount::where('system_key', $key)->firstOrFail();
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'settle-co');
    }

    private function journal(array $lines, string $date, array $hints = []): Journal
    {
        return app(JournalService::class)->post($lines, [
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date,
            'bill_allocations' => $hints,
        ]);
    }

    private function invoice(string $number, float $amount, string $date, int $sourceId): Journal
    {
        return $this->journal([
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => $amount, 'party_type' => 'customer', 'party_id' => $this->customer->id],
            ['chart_of_account_id' => $this->account(SystemAccount::SALES_REVENUE)->id, 'credit' => $amount],
        ], $date, [BillAllocationService::newRef('receivable', $number, $date, $date, 'Invoice', $sourceId)]);
    }

    private function onAccountReceipt(float $amount, string $date, string $key = SystemAccount::AR): Journal
    {
        return $this->journal([
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => $amount],
            ['chart_of_account_id' => $this->account($key)->id, 'credit' => $amount, 'party_type' => 'customer', 'party_id' => $this->customer->id],
        ], $date);
    }

    private function bill(string $ref): BillReference
    {
        return BillReference::where('ref_number', $ref)->firstOrFail();
    }

    private function open(string $ref): float
    {
        return app(BillAllocationService::class)->billOutstanding($this->bill($ref));
    }

    /** Every party's register = its ledger balance, on every bill-wise ledger. */
    private function assertRegisterMatchesLedger(): void
    {
        foreach (array_keys(app(BillAllocationService::class)->controlAccounts($this->tenant->id)) as $controlId) {
            $ledger = app(JournalRepositoryInterface::class)->partyBalancesUpTo($this->tenant->id, $controlId, now()->endOfDay())
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();
            $register = BillAllocation::where('control_account_id', $controlId)->groupBy('party_type', 'party_id')
                ->selectRaw('party_type, party_id, SUM(amount) as balance')->get()
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();

            $this->assertEquals($ledger->all(), $register->all(), "Register out of step with the ledger on account #{$controlId}");
        }
    }

    /** @test */
    public function a_ledger_switched_to_bill_wise_is_tracked_and_one_switched_off_is_not(): void
    {
        $creditors = $this->account('creditors_for_expenses');
        $this->assertTrue((bool) $creditors->bill_wise, 'Creditors for Expenses is bill-wise by default');

        $expense = $this->account(SystemAccount::OTHER_EXPENSE);
        $this->journal([
            ['chart_of_account_id' => $expense->id, 'debit' => 1200],
            ['chart_of_account_id' => $creditors->id, 'credit' => 1200, 'party_type' => 'vendor', 'party_id' => $this->vendor->id],
        ], '2026-10-01', [['ref_type' => 'new_ref', 'ref_number' => 'ELEC-OCT', 'ledger' => 'payable']]);

        $this->assertEquals(-1200.0, $this->open('ELEC-OCT'));

        $creditors->update(['bill_wise' => false]);
        app(BillAllocationService::class)->forgetControls();
        $this->journal([
            ['chart_of_account_id' => $expense->id, 'debit' => 300],
            ['chart_of_account_id' => $creditors->id, 'credit' => 300, 'party_type' => 'vendor', 'party_id' => $this->vendor->id],
        ], '2026-10-02', [['ref_type' => 'new_ref', 'ref_number' => 'WATER-OCT']]);

        $this->assertNull(BillReference::where('ref_number', 'WATER-OCT')->first());
    }

    /** @test */
    public function a_receipt_voucher_with_a_picked_bill_settles_it_and_the_rest_is_on_account(): void
    {
        $this->invoice('INV-7', 10000, '2026-09-10', 7);
        $bill = $this->bill('INV-7');

        $this->as($this->user)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => '2026-10-20',
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 12000, 'credit' => 0],
                [
                    'chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => 0, 'credit' => 12000,
                    'party' => "customer:{$this->customer->id}",
                    'bill_allocations' => json_encode([['ref_type' => 'against_ref', 'bill_reference_id' => $bill->id, 'amount' => 10000]]),
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(0.0, $this->open('INV-7'));
        $onAccount = app(BillAllocationService::class)->onAccount('receivable', null, $this->account(SystemAccount::AR)->id)["customer:{$this->customer->id}"] ?? 0;
        $this->assertEquals(-2000.0, $onAccount);

        $line = Journal::where('voucher_type', 'receipt')->firstOrFail()->entries()->where('credit', '>', 0)->first();
        $this->assertSame('customer', $line->party_type);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_voucher_cannot_pick_another_partys_bill_or_more_than_the_line(): void
    {
        $this->invoice('INV-8', 5000, '2026-09-10', 8);
        $other = Customer::create(['name' => 'Other Buyer', 'email' => 'other@example.com', 'company_id' => $this->company->id]);

        $base = fn (array $allocations, int $partyId) => [
            'voucher_date' => '2026-10-20',
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 1000, 'credit' => 0],
                ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => 0, 'credit' => 1000,
                    'party' => "customer:{$partyId}", 'bill_allocations' => json_encode($allocations)],
            ],
        ];

        $this->as($this->user)->post(route('accounting.vouchers.receipt.store'),
            $base([['ref_type' => 'against_ref', 'bill_reference_id' => $this->bill('INV-8')->id, 'amount' => 1000]], $other->id))
            ->assertSessionHasErrors('items');

        $this->as($this->user)->post(route('accounting.vouchers.receipt.store'),
            $base([['ref_type' => 'against_ref', 'bill_reference_id' => $this->bill('INV-8')->id, 'amount' => 1500]], $this->customer->id))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Journal::where('voucher_type', 'receipt')->count());
    }

    /** @test */
    public function a_voucher_line_on_a_party_ledger_needs_its_party(): void
    {
        $voucher = fn (?string $party) => [
            'voucher_date' => '2026-10-20',
            'party_name' => 'acme',
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::AP)->id, 'debit' => 100000, 'credit' => 0] + ($party ? ['party' => $party] : []),
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 0, 'credit' => 100000],
            ],
        ];

        // Typing a name in the free-text Party Name box is not enough.
        $this->as($this->user)->post(route('accounting.vouchers.payment.store'), $voucher(null))
            ->assertSessionHasErrors(['items' => 'Line 1: Accounts Payable is kept party by party: click "Pick party & bills" and choose the party.']);
        $this->assertSame(0, Journal::where('voucher_type', 'payment')->count());

        $this->as($this->user)->post(route('accounting.vouchers.payment.store'), $voucher("vendor:{$this->vendor->id}"))
            ->assertSessionHasNoErrors()->assertRedirect();
        $onAccount = app(BillAllocationService::class)->onAccount('payable', null, $this->account(SystemAccount::AP)->id)["vendor:{$this->vendor->id}"] ?? 0;
        $this->assertEquals(100000.0, $onAccount, 'With the party and no bill picked, the payment is On Account');
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function the_vouchers_party_tags_its_payables_line_and_names_the_voucher(): void
    {
        $this->as($this->user)->post(route('accounting.vouchers.payment.store'), [
            'voucher_date' => '2026-10-20',
            'party' => "vendor:{$this->vendor->id}",
            'party_name' => 'whatever the box showed',
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 0, 'credit' => 5000],
                ['chart_of_account_id' => $this->account(SystemAccount::AP)->id, 'debit' => 5000, 'credit' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $journal = Journal::where('voucher_type', 'payment')->firstOrFail();
        $line = $journal->entries()->where('chart_of_account_id', $this->account(SystemAccount::AP)->id)->firstOrFail();
        $this->assertSame('vendor', $line->party_type);
        $this->assertSame($this->vendor->id, (int) $line->party_id);
        $this->assertSame('Steel Mills', $journal->voucherDetail->party_name, 'The name comes from the party picked');
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_lines_own_party_wins_over_the_vouchers_party(): void
    {
        $this->as($this->user)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => '2026-10-20',
            'party' => "vendor:{$this->vendor->id}",
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 3000, 'credit' => 0],
                ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => 0, 'credit' => 3000, 'party' => "customer:{$this->customer->id}"],
            ],
        ])->assertSessionHasNoErrors();

        $line = Journal::where('voucher_type', 'receipt')->firstOrFail()->entries()->where('credit', '>', 0)->firstOrFail();
        $this->assertSame('customer', $line->party_type);
    }

    /** @test */
    public function an_unknown_voucher_party_is_refused_and_a_name_only_payee_still_posts(): void
    {
        $expense = ['chart_of_account_id' => $this->account(SystemAccount::OTHER_EXPENSE)->id, 'debit' => 800, 'credit' => 0];
        $bank = ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 0, 'credit' => 800];

        $this->as($this->user)->post(route('accounting.vouchers.payment.store'), [
            'voucher_date' => '2026-10-20', 'party' => 'vendor:999999', 'items' => [$bank, $expense],
        ])->assertSessionHasErrors('party');

        // A one-off payee: a name on the voucher, no party ledger touched.
        $this->as($this->user)->post(route('accounting.vouchers.payment.store'), [
            'voucher_date' => '2026-10-20', 'party' => '', 'party_name' => 'Ramesh Plumber', 'items' => [$bank, $expense],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $journal = Journal::where('voucher_type', 'payment')->firstOrFail();
        $this->assertSame('Ramesh Plumber', $journal->voucherDetail->party_name);
        $this->assertSame(0, $journal->entries()->whereNotNull('party_id')->count());
    }

    /** @test */
    public function the_voucher_form_offers_the_party_picker_with_each_partys_ledger(): void
    {
        $this->as($this->user)->get(route('accounting.vouchers.payment.index'))
            ->assertOk()
            ->assertSee('id="voucherParty-payment"', false)
            ->assertViewHas('partyLedgers', fn ($ledgers) => $ledgers['vendor'] === $this->account(SystemAccount::AP)->id
                && $ledgers['customer'] === $this->account(SystemAccount::AR)->id
                && $ledgers['employee'] === $this->account('salary_payable')->id);
    }

    /** @test */
    public function a_voucher_waiting_for_approval_applies_its_picked_bills_when_approved(): void
    {
        $this->invoice('INV-9', 4000, '2026-09-10', 9);
        app(JournalApprovalSettings::class)->update($this->tenant->id, ['enabled' => true, 'threshold' => 0, 'approvers_post_directly' => false]);

        $this->as($this->user)->post(route('accounting.journals.store'), [
            'journal_date' => '2026-10-20',
            'items' => [
                ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 4000, 'credit' => 0],
                ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => 0, 'credit' => 4000,
                    'party' => "customer:{$this->customer->id}",
                    'bill_allocations' => json_encode([['ref_type' => 'against_ref', 'bill_reference_id' => $this->bill('INV-9')->id, 'amount' => 4000]])],
            ],
        ])->assertSessionHasNoErrors();

        $pending = Journal::where('status', Journal::STATUS_PENDING_APPROVAL)->firstOrFail();
        $this->assertEquals(4000.0, $this->open('INV-9'), 'Nothing is settled before approval');

        $checker = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Checker', 'email' => 'chk@settle.example.com', 'password' => bcrypt('password')]);
        app(JournalService::class)->approve($pending->id, $checker->id);

        $this->assertEquals(0.0, $this->open('INV-9'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function on_account_money_is_adjusted_against_a_bill_without_a_journal_and_can_be_undone(): void
    {
        $this->invoice('INV-10', 75000, '2026-08-01', 10);
        $this->onAccountReceipt(75000, '2026-09-15');
        $journalsBefore = Journal::count();

        $service = app(BillSettlementService::class);
        $suggestion = $service->suggest('customer', $this->customer->id);
        $this->assertEquals([$this->bill('INV-10')->id => 75000.0], $suggestion['bills']);

        $settlement = $service->settle('customer', $this->customer->id, $suggestion['bills'], $suggestion['pieces'], 'Matched with party', '2026-10-23', $this->user->id);

        $this->assertNull($settlement->journal_id);
        $this->assertSame($journalsBefore, Journal::count(), 'Same-ledger adjustment posts nothing');
        $this->assertEquals(0.0, $this->open('INV-10'));
        $this->assertCount(0, $service->unmatched('customer', $this->customer->id));
        $this->assertRegisterMatchesLedger();

        $service->undo($settlement, $this->user->id);
        $this->assertEquals(75000.0, $this->open('INV-10'));
        $this->assertEquals(-75000.0, $service->unmatched('customer', $this->customer->id)->sum('amount'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function oldest_bills_are_matched_first_and_a_partial_adjustment_leaves_the_rest_open(): void
    {
        $this->invoice('INV-OLD', 3000, '2026-07-01', 11);
        $this->invoice('INV-NEW', 5000, '2026-09-01', 12);
        $this->onAccountReceipt(4000, '2026-10-01');

        $service = app(BillSettlementService::class);
        $suggestion = $service->suggest('customer', $this->customer->id);
        $this->assertEquals([$this->bill('INV-OLD')->id => 3000.0, $this->bill('INV-NEW')->id => 1000.0], $suggestion['bills']);

        $service->settle('customer', $this->customer->id, $suggestion['bills'], $suggestion['pieces'], null, '2026-10-23', $this->user->id);

        $this->assertEquals(0.0, $this->open('INV-OLD'));
        $this->assertEquals(4000.0, $this->open('INV-NEW'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function an_advance_on_another_ledger_is_moved_onto_the_bill_by_a_transfer_journal(): void
    {
        $this->invoice('INV-20', 6000, '2026-10-01', 20);
        $this->onAccountReceipt(6000, '2026-09-20', SystemAccount::ADVANCE_FROM_CUSTOMERS);

        $service = app(BillSettlementService::class);
        $piece = $service->unmatched('customer', $this->customer->id)->first();
        $this->assertSame($this->account(SystemAccount::ADVANCE_FROM_CUSTOMERS)->id, $piece->ledger_id);

        $settlement = $service->settle('customer', $this->customer->id, [$this->bill('INV-20')->id => 6000], [$piece->key => 6000], null, '2026-10-23', $this->user->id);

        $this->assertNotNull($settlement->journal_id);
        $this->assertEquals(0.0, $this->open('INV-20'));
        $this->assertEquals(0.0, round((float) $this->account(SystemAccount::ADVANCE_FROM_CUSTOMERS)->journalEntries()->sum('credit')
            - (float) $this->account(SystemAccount::ADVANCE_FROM_CUSTOMERS)->journalEntries()->sum('debit'), 2));
        $this->assertCount(0, $service->unmatched('customer', $this->customer->id));
        $this->assertRegisterMatchesLedger();

        $service->undo($settlement, $this->user->id);
        $this->assertEquals(6000.0, $this->open('INV-20'));
        $this->assertSame(Journal::STATUS_REVERSED, Journal::find($settlement->journal_id)->status);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function the_settlement_screen_saves_a_match_and_rejects_unequal_totals(): void
    {
        $this->invoice('INV-30', 2000, '2026-09-01', 30);
        $this->onAccountReceipt(2000, '2026-10-01');
        $piece = app(BillSettlementService::class)->unmatched('customer', $this->customer->id)->first();
        $party = "customer:{$this->customer->id}";

        $this->as($this->user)->get(route('accounting.bill-wise.settlement', ['party' => $party]))
            ->assertOk()->assertSee('INV-30');

        $this->as($this->user)->post(route('accounting.bill-wise.settlement.store'), [
            'party' => $party, 'settled_on' => '2026-10-23',
            'bills' => [$this->bill('INV-30')->id => 2000], 'pieces' => [$piece->key => 1500],
        ])->assertSessionHasErrors('settlement');

        $this->as($this->user)->post(route('accounting.bill-wise.settlement.store'), [
            'party' => $party, 'settled_on' => '2026-10-23',
            'bills' => [$this->bill('INV-30')->id => 2000], 'pieces' => [$piece->key => 2000],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(0.0, $this->open('INV-30'));
        $this->assertSame(1, BillSettlement::count());

        $this->as($this->user)->delete(route('accounting.bill-wise.settlement.destroy', BillSettlement::first()))->assertRedirect();
        $this->assertEquals(2000.0, $this->open('INV-30'));
    }

    /** @test */
    public function open_bills_lookup_lists_the_partys_bills_on_the_ledger(): void
    {
        $this->invoice('INV-40', 900, '2026-09-01', 40);

        $this->as($this->user)->getJson(route('accounting.bill-wise.open-bills', [
            'party_type' => 'customer', 'party_id' => $this->customer->id, 'account_id' => $this->account(SystemAccount::AR)->id,
        ]))->assertOk()->assertJsonPath('bills.0.ref_number', 'INV-40')->assertJsonPath('bills.0.outstanding', 900);

        $this->as($this->user)->getJson(route('accounting.bill-wise.parties', ['q' => 'Ravi']))
            ->assertOk()->assertJsonPath('results.0.id', "customer:{$this->customer->id}");
    }

    /** @test */
    public function an_opening_is_broken_into_old_bills_and_what_was_received_moves_to_the_oldest(): void
    {
        $this->customer->update(['opening_balance' => 10000, 'opening_balance_type' => 'debit']);
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($this->customer->fresh());
        // A receipt taken against the opening before it was broken up.
        $this->journal([
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 3000],
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'credit' => 3000, 'party_type' => 'customer', 'party_id' => $this->customer->id],
        ], '2026-05-10', [BillAllocationService::againstRef('receivable', 'customer_opening_balance', $this->customer->id, 3000)]);
        $this->assertEquals(7000.0, $this->open('Opening Balance'));

        app(OpeningBreakupService::class)->split('customer', $this->customer->id, [
            ['ref' => 'OLD-2', 'bill_date' => '2026-02-20', 'due_date' => '2026-03-20', 'amount' => 6000],
            ['ref' => 'OLD-1', 'bill_date' => '2026-01-05', 'due_date' => '2026-01-31', 'amount' => 4000],
        ], $this->user->id);

        $this->assertNull(BillReference::where('ref_number', 'Opening Balance')->first());
        $this->assertEquals(1000.0, $this->open('OLD-1'), 'The receipt settles the oldest due bill first');
        $this->assertEquals(6000.0, $this->open('OLD-2'));
        $this->assertSame('2026-01-05', $this->bill('OLD-1')->bill_date->toDateString());
        $this->assertRegisterMatchesLedger();

        // The backfill rebuilds the same old bills from the saved breakup.
        BillAllocation::query()->delete();
        BillReference::query()->delete();
        $this->artisan('accounting:build-bill-register', ['--tenant' => $this->tenant->id])->assertSuccessful();
        // (A manual receipt has no source document, so it rebuilds as On Account.)
        $this->assertEquals(4000.0, $this->open('OLD-1'));
        $this->assertEquals(6000.0, $this->open('OLD-2'));
        $this->assertEquals(-3000.0, (float) BillAllocation::whereNull('bill_reference_id')->where('party_id', $this->customer->id)->sum('amount'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function an_opening_breakup_must_add_up_and_can_go_back_to_one_bill(): void
    {
        $this->customer->update(['opening_balance' => 5000, 'opening_balance_type' => 'debit']);
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($this->customer->fresh());
        $party = "customer:{$this->customer->id}";

        $this->as($this->user)->post(route('accounting.bill-wise.opening-breakup'), [
            'party' => $party, 'rows' => [['ref' => 'A-1', 'bill_date' => '2026-03-01', 'amount' => 4000]],
        ])->assertSessionHasErrors('opening');

        $this->as($this->user)->post(route('accounting.bill-wise.opening-breakup'), [
            'party' => $party, 'rows' => [
                ['ref' => 'A-1', 'bill_date' => '2026-03-01', 'amount' => 4000],
                ['ref' => 'A-2', 'bill_date' => '2026-03-15', 'amount' => 1000],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(4000.0, $this->open('A-1'));
        $this->assertCount(2, $this->customer->fresh()->opening_bills);

        $this->as($this->user)->get(route('accounting.bill-wise.settlement', ['party' => $party]))->assertOk()->assertSee('2 old bills');

        $this->as($this->user)->post(route('accounting.bill-wise.opening-breakup'), ['party' => $party, 'rows' => []])->assertSessionHasNoErrors();
        $this->assertEquals(5000.0, $this->open('Opening Balance'));
        $this->assertNull($this->customer->fresh()->opening_bills);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function ageing_shows_advances_apart_from_on_account_money(): void
    {
        $this->invoice('INV-AG', 5000, '2026-10-01', 31);
        $this->journal([
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 2000],
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'credit' => 2000, 'party_type' => 'customer', 'party_id' => $this->customer->id],
        ], '2026-10-05', [['ref_type' => 'advance', 'ledger' => 'receivable']]);
        $this->onAccountReceipt(1000, '2026-10-06');

        $response = $this->as($this->user)->get(route('accounting.reports.ar-aging'))->assertOk()
            ->assertSee('On Account')->assertSee('Advance');
        $row = $response->viewData('customers')[0];
        $this->assertEquals(-1000.0, $row['on_account']);
        $this->assertEquals(-2000.0, $row['advance']);
        $this->assertEquals(-1000.0, $response->viewData('onAccount'));
        $this->assertEquals(-2000.0, $response->viewData('advance'));
        $this->assertEquals(2000.0, $response->viewData('grandTotal'));
    }
}
