<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Services\BillWise\AdvanceAdjustmentService;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\BillAgeing;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerOpeningBalanceService;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bill-wise register: invoices/bills open bills, receipts/payments/returns
 * settle them, unmatched money stays On Account, reversals undo, and every
 * party's bills add up to its ledger balance.
 */
class BillWiseSettlementTest extends TestCase
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

        $this->tenant = Tenant::create(['name' => 'Bills Co', 'slug' => 'bills-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@bills.example.com', 'password' => bcrypt('password')]);
        $this->company = Company::create(['company_name' => 'Bills Co']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $this->customer = Customer::create(['name' => 'Ravi Traders', 'email' => 'ravi@example.com']);
        $this->vendor = Vendor::create(['name' => 'Steel Mills', 'code' => 'V-STEEL', 'status' => 'active']);
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

    private function journals(): JournalService
    {
        return app(JournalService::class);
    }

    /** An invoice journal: Dr AR (customer) / Cr Sales, opening bill $number. */
    private function invoice(string $number, float $amount, string $date, int $sourceId, ?string $due = null): Journal
    {
        return $this->journals()->post([
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'debit' => $amount, 'party_type' => 'customer', 'party_id' => $this->customer->id],
            ['chart_of_account_id' => $this->account(SystemAccount::SALES_REVENUE)->id, 'credit' => $amount],
        ], [
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date,
            'reference_type' => 'Invoice', 'reference_id' => $sourceId,
            'bill_allocations' => [BillAllocationService::newRef('receivable', $number, $date, $due ?? $date, 'Invoice', $sourceId)],
        ]);
    }

    /** A receipt: Dr Bank / Cr AR (customer), optionally against invoice #$invoiceSourceId. */
    private function receipt(float $amount, string $date, ?int $invoiceSourceId = null, ?float $against = null): Journal
    {
        return $this->journals()->post([
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => $amount],
            ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'credit' => $amount, 'party_type' => 'customer', 'party_id' => $this->customer->id],
        ], [
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date,
            'bill_allocations' => $invoiceSourceId ? [BillAllocationService::againstRef('receivable', 'Invoice', $invoiceSourceId, $against ?? $amount)] : [],
        ]);
    }

    private function outstanding(int $invoiceSourceId): float
    {
        $bill = app(BillAllocationService::class)->billFor('Invoice', $invoiceSourceId);

        return $bill ? app(BillAllocationService::class)->billOutstanding($bill) : 0.0;
    }

    /** Every party's register = its ledger balance on AR and AP. */
    private function assertRegisterMatchesLedger(): void
    {
        foreach ([SystemAccount::AR, SystemAccount::AP] as $key) {
            $control = $this->account($key);
            $ledger = app(JournalRepositoryInterface::class)->partyBalancesUpTo($this->tenant->id, $control->id, now()->endOfDay())
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();
            $register = BillAllocation::where('control_account_id', $control->id)->groupBy('party_type', 'party_id')
                ->selectRaw('party_type, party_id, SUM(amount) as balance')->get()
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)])->filter(fn ($v) => abs($v) >= 0.01)->sortKeys();

            $this->assertEquals($ledger->all(), $register->all(), "Register out of step with the ledger on {$key}");
        }
    }

    /** @test */
    public function a_part_receipt_against_an_invoice_leaves_the_rest_outstanding(): void
    {
        $this->invoice('INV-1', 10000, '2026-09-01', 101, '2026-09-30');
        $this->receipt(4000, '2026-10-05', 101);

        $this->assertEquals(6000.0, $this->outstanding(101));

        $report = app(BillAgeing::class)->build('receivable', Carbon::parse('2026-10-23'));
        $this->assertEquals(6000.0, $report['grandTotal']);
        $this->assertEquals(6000.0, $report['buckets']['0_30']); // due 30 Sep, 23 days overdue
        $this->assertSame('INV-1', $report['parties'][0]['documents'][0]['invoice']->invoice_number);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function money_not_matched_to_a_bill_stays_on_account(): void
    {
        $this->invoice('INV-2', 5000, '2026-09-10', 102);
        $this->receipt(8000, '2026-10-01', 102, 8000); // pays the bill in full, 3,000 extra
        $this->receipt(1000, '2026-10-02');            // not against any bill

        $this->assertEquals(0.0, $this->outstanding(102));

        $report = app(BillAgeing::class)->build('receivable', Carbon::parse('2026-10-23'));
        $this->assertEquals(-4000.0, $report['parties'][0]['on_account']);
        $this->assertEquals(-4000.0, $report['grandTotal']);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function reversing_an_invoice_closes_its_bill_and_reversing_a_receipt_reopens_it(): void
    {
        $this->invoice('INV-3', 7000, '2026-10-01', 103);
        $receipt = $this->receipt(7000, '2026-10-03', 103);
        $this->assertEquals(0.0, $this->outstanding(103));

        $this->journals()->reverse($receipt->id, 'Cheque bounced');
        $this->assertEquals(7000.0, $this->outstanding(103));

        $invoiceJournal = Journal::where('reference_type', 'Invoice')->where('reference_id', 103)->first();
        $this->journals()->reverse($invoiceJournal->id, 'Invoice cancelled');
        $this->assertEquals(0.0, $this->outstanding(103));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function an_opening_due_is_one_bill_dated_the_books_start_and_an_opening_credit_is_an_advance(): void
    {
        $this->customer->update(['opening_balance' => 25000, 'opening_balance_type' => 'debit']);
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($this->customer->fresh());

        $bill = BillReference::where('party_id', $this->customer->id)->firstOrFail();
        $this->assertSame('Opening Balance', $bill->ref_number);
        $this->assertSame('2026-04-01', $bill->bill_date->toDateString());
        $this->assertEquals(25000.0, app(BillAllocationService::class)->billOutstanding($bill));

        $other = Customer::create(['name' => 'Paid Ahead Ltd', 'email' => 'ahead@example.com', 'opening_balance' => 3000, 'opening_balance_type' => 'credit']);
        app(CustomerOpeningBalanceService::class)->syncCustomerOpeningBalance($other);
        $this->assertSame(BillAllocation::ADVANCE, BillAllocation::where('party_id', $other->id)->value('ref_type'));
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_vendor_bill_is_settled_by_the_payment_made_against_it(): void
    {
        $ap = $this->account(SystemAccount::AP);
        $this->journals()->post([
            ['chart_of_account_id' => $this->account('purchase_raw_material')->id, 'debit' => 12000],
            ['chart_of_account_id' => $ap->id, 'credit' => 12000, 'party_type' => 'vendor', 'party_id' => $this->vendor->id],
        ], ['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => '2026-08-01',
            'bill_allocations' => [BillAllocationService::newRef('payable', 'BILL-9', '2026-08-01', '2026-08-31', 'vendor_bill', 9)]]);

        $this->journals()->post([
            ['chart_of_account_id' => $ap->id, 'debit' => 5000, 'party_type' => 'vendor', 'party_id' => $this->vendor->id],
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'credit' => 5000],
        ], ['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => '2026-09-10',
            'bill_allocations' => [BillAllocationService::againstRef('payable', 'vendor_bill', 9, 5000)]]);

        $report = app(BillAgeing::class)->build('payable', Carbon::parse('2026-10-23'));
        $this->assertEquals(7000.0, $report['grandTotal']);
        $this->assertEquals(7000.0, $report['buckets']['31_60']); // due 31 Aug
        $this->assertSame('BILL-9', $report['parties'][0]['documents'][0]['bill']->bill_number);
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function a_sales_order_advance_applied_to_its_invoice_leaves_advance_from_customers(): void
    {
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'sales_order_number' => 'SO-1',
            'order_date' => '2026-10-01', 'status' => 'Confirmed', 'sales_person_id' => $this->user->id,
            'subtotal' => 10000, 'tax' => 0, 'discount' => 0, 'shipping_charges' => 0, 'adjustment' => 0, 'total_amount' => 10000,
        ]);
        $payment = CustomerPayment::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'payment_number' => 'PAY-ADV',
            'payment_date' => '2026-10-02', 'payment_method' => 'Bank Transfer', 'amount' => 4000, 'status' => 'Posted',
        ]);
        $allocation = PaymentAllocation::create(['customer_payment_id' => $payment->id, 'sales_order_id' => $order->id, 'allocated_amount' => 4000]);
        event(new \App\Domains\Sales\Events\CustomerPaymentReceived($payment)); // Cr 2200 Advance from Customers
        $this->assertEquals(-4000.0, $this->balance(SystemAccount::ADVANCE_FROM_CUSTOMERS));

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id, 'sales_order_id' => $order->id, 'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SO1', 'invoice_date' => '2026-10-10', 'due_date' => '2026-10-25', 'status' => 'Posted',
            'subtotal' => 10000, 'total_amount' => 10000, 'balance_due' => 10000,
        ]);
        $this->invoice('INV-SO1', 10000, '2026-10-10', $invoice->id, '2026-10-25');
        $allocation->update(['invoice_id' => $invoice->id]);

        app(AdvanceAdjustmentService::class)->adjustInvoice($invoice);
        app(AdvanceAdjustmentService::class)->adjustInvoice($invoice); // idempotent

        $this->assertEquals(0.0, $this->balance(SystemAccount::ADVANCE_FROM_CUSTOMERS));
        $this->assertEquals(6000.0, $this->outstanding($invoice->id));
        $this->assertSame(1, Journal::where('reference_type', AdvanceAdjustmentService::REFERENCE_TYPE)->count());
        $this->assertRegisterMatchesLedger();
    }

    /** @test */
    public function the_backfill_rebuilds_the_same_register_and_can_run_twice(): void
    {
        $this->invoice('INV-B1', 9000, '2026-07-01', 201);
        $this->receipt(2000, '2026-08-01', 201);
        $this->receipt(500, '2026-08-02');
        $before = BillAllocation::orderBy('id')->get(['ref_type', 'amount'])->map(fn ($a) => [$a->ref_type, (float) $a->amount])->all();

        // An older install: journals posted before the register existed.
        BillAllocation::query()->delete();
        BillReference::query()->delete();
        $invoiceRow = Invoice::create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'invoice_number' => 'INV-B1', 'invoice_date' => '2026-07-01', 'status' => 'Posted', 'subtotal' => 9000, 'total_amount' => 9000, 'balance_due' => 7000]);
        Journal::where('reference_id', 201)->update(['reference_type' => 'Invoice', 'reference_id' => $invoiceRow->id]);

        $this->artisan('accounting:build-bill-register', ['--tenant' => $this->tenant->id])->assertSuccessful();
        $this->artisan('accounting:build-bill-register', ['--tenant' => $this->tenant->id])->assertSuccessful();

        // Receipts without a stored allocation document rebuild as On Account.
        $this->assertSame(9000.0, (float) BillAllocation::where('ref_type', BillAllocation::NEW_REF)->sum('amount'));
        $this->assertEquals(-2500.0, (float) BillAllocation::where('ref_type', BillAllocation::ON_ACCOUNT)->sum('amount'));
        $this->assertCount(3, $before);
        $this->assertRegisterMatchesLedger();
    }

    private function balance(string $key): float
    {
        $account = $this->account($key);

        return round((float) \App\Domains\Accounting\Models\JournalEntry::where('chart_of_account_id', $account->id)->sum('debit')
            - (float) \App\Domains\Accounting\Models\JournalEntry::where('chart_of_account_id', $account->id)->sum('credit'), 2);
    }
}
