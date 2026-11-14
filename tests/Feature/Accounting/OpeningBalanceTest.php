<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerOpeningBalanceService;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Services\VendorOpeningBalanceService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Opening balances of ledgers and parties: dated at the books' start, either
 * side, offset to Opening Balance Difference, re-posted when changed, and
 * never silently dropped.
 */
class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Books start 1 Apr 2026; balances are typed in October.
        Carbon::setTestNow(Carbon::create(2026, 10, 22, 12));

        $this->tenant = Tenant::create(['name' => 'Opening Co', 'slug' => 'opening-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@opening.example.com', 'password' => bcrypt('password')]);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function openBooks(): void
    {
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY 2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
        ]);
    }

    private function account(string $key): ChartOfAccount
    {
        return ChartOfAccount::where('system_key', $key)->firstOrFail();
    }

    /** Debit-positive balance of an account, optionally for one party. */
    private function balance(ChartOfAccount $account, ?string $partyType = null, ?int $partyId = null): float
    {
        $row = JournalEntry::query()
            ->where('chart_of_account_id', $account->id)
            ->when($partyType, fn ($q) => $q->where('party_type', $partyType)->where('party_id', $partyId))
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
            ->first();

        return round((float) $row->d - (float) $row->c, 2);
    }

    private function customer(float $opening, string $side = 'debit'): Customer
    {
        return Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Acme Retail ' . uniqid(), 'email' => uniqid() . '@acme.example.com',
            'opening_balance' => $opening, 'opening_balance_type' => $side, 'status' => 'active',
        ]);
    }

    private function vendor(float $opening, string $side = 'credit'): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Steel Supplier ' . uniqid(), 'code' => 'V' . random_int(1000, 9999),
            'opening_balance' => $opening, 'opening_balance_type' => $side, 'status' => 'active',
        ]);
    }

    /** @test */
    public function a_customer_opening_is_dated_at_the_books_start_and_offset_to_opening_balance_difference(): void
    {
        $this->openBooks();
        $customer = $this->customer(50000);

        $journal = app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($customer);

        $this->assertSame('2026-04-01', Carbon::parse($journal->journal_date)->toDateString());
        $this->assertEquals(50000.0, $this->balance($this->account(SystemAccount::AR), JournalEntry::PARTY_CUSTOMER, $customer->id));
        $this->assertEquals(-50000.0, $this->balance($this->account(SystemAccount::OPENING_BALANCE_DIFFERENCE)));
        $this->assertEquals(0.0, $this->balance($this->account(SystemAccount::SUSPENSE)));
    }

    /** @test */
    public function advances_can_be_entered_on_either_side(): void
    {
        $this->openBooks();
        $customer = $this->customer(8000, 'credit'); // advance received
        $vendor = $this->vendor(3000, 'debit');      // advance paid

        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($customer);
        app(VendorOpeningBalanceService::class)->syncVendorOpeningBalance($vendor);

        $this->assertEquals(-8000.0, $this->balance($this->account(SystemAccount::AR), JournalEntry::PARTY_CUSTOMER, $customer->id));
        $this->assertEquals(3000.0, $this->balance($this->account(SystemAccount::AP), JournalEntry::PARTY_VENDOR, $vendor->id));
        $this->assertEquals(-8000.0, $customer->signedOpeningBalance());
        $this->assertEquals(-3000.0, $vendor->signedOpeningBalance());
    }

    /** @test */
    public function changing_an_opening_reverses_it_on_the_opening_date_and_posts_the_new_amount(): void
    {
        $this->openBooks();
        $customer = $this->customer(50000);
        $service = app(CustomerOpeningBalanceService::class);
        $first = $service->syncCustomerOpeningBalance($customer);

        $customer->update(['opening_balance' => 45000]);
        $second = $service->syncCustomerOpeningBalance($customer->fresh());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(Journal::STATUS_REVERSED, $first->fresh()->status);
        $reversal = Journal::find($first->fresh()->reversed_journal_id);
        $this->assertSame('2026-04-01', Carbon::parse($reversal->journal_date)->toDateString());
        $this->assertEquals(45000.0, $this->balance($this->account(SystemAccount::AR), JournalEntry::PARTY_CUSTOMER, $customer->id));

        // Saving again unchanged posts nothing new.
        $this->assertSame($second->id, $service->syncCustomerOpeningBalance($customer->fresh())->id);

        // Clearing it removes it from the books.
        $customer->update(['opening_balance' => 0]);
        $this->assertNull($service->syncCustomerOpeningBalance($customer->fresh()));
        $this->assertEquals(0.0, $this->balance($this->account(SystemAccount::AR), JournalEntry::PARTY_CUSTOMER, $customer->id));
    }

    /** @test */
    public function a_ledger_opening_uses_its_dr_cr_and_the_same_offset(): void
    {
        $this->openBooks();
        $bank = $this->account(SystemAccount::BANK);
        $bank->update(['opening_balance' => 125000, 'opening_balance_type' => 'debit']);

        $journal = app(ChartOfAccountsService::class)->syncOpeningBalance($bank->fresh());

        $this->assertSame('2026-04-01', Carbon::parse($journal->journal_date)->toDateString());
        $this->assertEquals(125000.0, $this->balance($bank));
        $this->assertEquals(-125000.0, $this->balance($this->account(SystemAccount::OPENING_BALANCE_DIFFERENCE)));
        $this->assertEquals(0.0, $this->balance($this->account(SystemAccount::RESERVES_AND_SURPLUS)));
    }

    /** @test */
    public function openings_net_to_zero_on_opening_balance_difference_once_all_are_in(): void
    {
        $this->openBooks();
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($this->customer(40000));
        app(VendorOpeningBalanceService::class)->syncVendorOpeningBalance($this->vendor(25000));
        $bank = $this->account(SystemAccount::BANK);
        $bank->update(['opening_balance' => 35000, 'opening_balance_type' => 'debit']);
        app(ChartOfAccountsService::class)->syncOpeningBalance($bank->fresh());
        $capital = ChartOfAccount::where('code', '3030')->firstOrFail();
        $capital->update(['opening_balance' => 50000, 'opening_balance_type' => 'credit']);
        app(ChartOfAccountsService::class)->syncOpeningBalance($capital->fresh());

        // Assets 40,000 + 35,000 = Liabilities 25,000 + Capital 50,000.
        $this->assertEquals(0.0, $this->balance($this->account(SystemAccount::OPENING_BALANCE_DIFFERENCE)));
    }

    /** @test */
    public function a_failed_opening_is_reported_not_silently_dropped(): void
    {
        // No financial year yet.
        $customer = $this->customer(50000);

        $message = app(CustomerOpeningBalanceService::class)->trySync($customer);

        $this->assertStringContainsString('opening balance was not posted', $message);
        $this->assertStringContainsString('financial year', $message);
        $this->assertSame(0, Journal::count());
    }

    /** @test */
    public function the_opening_balance_difference_account_is_created_for_older_tenants(): void
    {
        $this->openBooks();
        ChartOfAccount::where('system_key', SystemAccount::OPENING_BALANCE_DIFFERENCE)->forceDelete();

        app(VendorOpeningBalanceService::class)->syncVendorOpeningBalance($this->vendor(12000));

        $created = $this->account(SystemAccount::OPENING_BALANCE_DIFFERENCE);
        $this->assertSame('3050', $created->code);
        $this->assertSame(ChartOfAccount::TYPE_EQUITY, $created->type);
        $this->assertEquals(12000.0, $this->balance($created));
    }

    /** @test */
    public function the_repair_command_reposts_old_openings_and_posts_unposted_ones(): void
    {
        $this->openBooks();

        // Posted by the old code: dated the day it was typed, offset to Suspense.
        $old = $this->customer(30000);
        app(JournalService::class)->postOnce([
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => 30000, 'party_type' => 'customer', 'party_id' => $old->id],
            ['chart_of_account_id' => $this->account(SystemAccount::SUSPENSE)->id, 'credit' => 30000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now(), 'reference_type' => 'customer_opening_balance', 'reference_id' => $old->id]);

        // Saved through the API: on the customer, never posted.
        $unposted = $this->customer(20000);

        $this->artisan('accounting:post-opening-balances', ['--tenant' => $this->tenant->id])->assertSuccessful();

        $ar = $this->account(SystemAccount::AR);
        $this->assertEquals(30000.0, $this->balance($ar, 'customer', $old->id));
        $this->assertEquals(20000.0, $this->balance($ar, 'customer', $unposted->id));
        $this->assertEquals(0.0, $this->balance($this->account(SystemAccount::SUSPENSE)));
        $this->assertEquals(-50000.0, $this->balance($this->account(SystemAccount::OPENING_BALANCE_DIFFERENCE)));

        foreach ([$old, $unposted] as $customer) {
            $posting = app(JournalService::class)->activePosting($this->tenant->id, 'customer_opening_balance', $customer->id);
            $this->assertSame('2026-04-01', Carbon::parse($posting->journal_date)->toDateString());
        }

        // Re-running changes nothing.
        $count = Journal::count();
        $this->artisan('accounting:post-opening-balances', ['--tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertSame($count, Journal::count());
    }

    /** @test */
    public function the_customer_api_now_posts_the_opening_balance(): void
    {
        $this->openBooks();
        // The API falls back to company #1 for a user without a company.
        \App\Domains\HRMS\Models\Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Opening Co']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Tenant', 'opening-co')
            ->postJson('/api/sales/customers', [
                'name' => 'API Customer', 'email' => 'api@customer.example.com',
                'opening_balance' => 15000, 'opening_balance_type' => 'debit',
            ]);

        $response->assertCreated()->assertJsonPath('warning', null);
        $customer = Customer::where('email', 'api@customer.example.com')->firstOrFail();
        $this->assertEquals(15000.0, $this->balance($this->account(SystemAccount::AR), 'customer', $customer->id));
    }

    /** @test */
    public function the_party_ledger_shows_the_posted_opening_as_opening_and_matches_the_books(): void
    {
        $this->openBooks();
        $customer = $this->customer(50000);
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($customer);

        $ledger = app(JournalService::class)->partyLedger('customer', $customer->id, Carbon::parse('2026-04-01'), Carbon::parse('2026-10-31')->endOfDay());
        $this->assertEquals(50000.0, $ledger['opening']);
        $this->assertCount(0, $ledger['entries']);
        $this->assertEquals(50000.0, $ledger['closing']);

        // A customer whose opening never reached the books shows nothing on the
        // statement, the same as the Trial Balance (run the repair command).
        $unposted = $this->customer(9000);
        $statement = app(JournalService::class)->partyLedger('customer', $unposted->id, Carbon::parse('2026-04-01'), Carbon::parse('2026-10-31')->endOfDay());
        $this->assertEquals(0.0, $statement['closing']);
    }

    /** @test */
    public function the_general_ledger_shows_the_opening_entry_as_the_first_periods_opening(): void
    {
        $this->openBooks();
        $bank = $this->account(SystemAccount::BANK);
        $bank->update(['opening_balance' => 125000, 'opening_balance_type' => 'debit']);
        app(ChartOfAccountsService::class)->syncOpeningBalance($bank->fresh());

        $april = AccountingPeriod::whereDate('start_date', '2026-04-01')->firstOrFail();
        $ledger = app(JournalService::class)->generalLedger($bank->id, $april);

        $this->assertEquals(125000.0, $ledger['opening']['debit']);
        $this->assertCount(0, $ledger['entries']);
    }
}
