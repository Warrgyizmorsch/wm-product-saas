<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationPartySettler;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\BankReconciliationStatementService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A bank deposit settles customer invoices; a bank debit pays vendor bills.
 * Sales/Purchase record the payment, Accounting posts it to the reconciled
 * bank ledger (not the default 1020), and the line is matched.
 */
class BankReconciliationPartySettlementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $hdfc;
    private Customer $customer;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Party Tenant', 'slug' => 'party-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@party.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->accountant->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($this->accountant);

        // HDFC Current A/c (1021) — a bank ledger other than the default 1020.
        $this->hdfc = $this->account('1021');

        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Greenfield Distributors', 'email' => 'g@example.com', 'status' => 'active']);
        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'ABC Suppliers', 'status' => 'active']);
    }

    #[Test]
    public function a_deposit_settles_two_customer_invoices_and_leaves_the_rest_on_account(): void
    {
        $inv1 = $this->invoice('INV-1', 30000, 5);
        $inv2 = $this->invoice('INV-2', 15000, 8);
        $rec = $this->rec();
        $line = $this->line($rec, 12, 50000, 'NEFT CR-GREENFIELD DISTRIBUTORS', 'UTR1');

        $this->http()->post(route('accounting.bank-reconciliation.settle', $rec), [
            'statement_line_id' => $line->id,
            'party_type' => 'customer',
            'party_id' => $this->customer->id,
            'allocations' => [$inv1->id => 30000, $inv2->id => 15000],
        ])->assertSessionHas('success');

        // Sales side: payment, allocations, invoices paid.
        $payment = CustomerPayment::sole();
        $this->assertEquals(50000, $payment->amount);
        $this->assertSame($this->hdfc->id, $payment->bank_account_id);
        $this->assertSame('UTR1', $payment->reference_no);
        $this->assertSame(2, $payment->allocations()->count());
        $this->assertSame('Paid', $inv1->refresh()->status);
        $this->assertEquals(0, $inv2->refresh()->balance_due);

        // Accounting side: posted to HDFC (not 1020) against the customer, and matched.
        $bankEntry = JournalEntry::where('chart_of_account_id', $this->hdfc->id)->sole();
        $this->assertEquals(50000, $bankEntry->debit);
        $this->assertTrue($bankEntry->is_reconciled);
        $this->assertTrue($line->refresh()->is_matched);
        $receivable = JournalEntry::where('party_type', 'customer')->where('party_id', $this->customer->id)->sole();
        $this->assertEquals(50000, $receivable->credit);
        $this->assertSame(0, JournalEntry::where('chart_of_account_id', $this->account('1020')->id)->count());

        $this->assertSame(0.0, app(BankReconciliationStatementService::class)->build($rec->refresh())['bank_credits_not_in_books_total']);
    }

    #[Test]
    public function a_debit_pays_a_vendor_bill(): void
    {
        $bill = $this->bill('BILL-7', 12000, 3);
        $rec = $this->rec();
        $line = $this->line($rec, 4, -12000, 'CHQ PAID-ABC SUPPLIERS', '000451');

        app(BankReconciliationPartySettler::class)->settle($rec, $line->id, 'vendor', $this->vendor->id, [$bill->id => 12000], $this->accountant->id);

        $payment = VendorPayment::sole();
        $this->assertSame($this->hdfc->id, $payment->bank_account_id);
        $this->assertSame('Cheque', $payment->payment_method);
        $this->assertSame('Bill Payment', $payment->payment_type);
        $this->assertSame('Paid', $bill->refresh()->status);

        $bankEntry = JournalEntry::where('chart_of_account_id', $this->hdfc->id)->sole();
        $this->assertEquals(12000, $bankEntry->credit);
        $this->assertTrue($line->refresh()->is_matched);
        $this->assertEquals(12000, JournalEntry::where('chart_of_account_id', $this->account('2010')->id)->sole()->debit);
    }

    #[Test]
    public function applying_more_than_an_invoice_owes_or_than_was_received_is_refused_and_nothing_is_saved(): void
    {
        $inv = $this->invoice('INV-9', 1000, 5);
        $rec = $this->rec();
        $line = $this->line($rec, 6, 500, 'NEFT CR GREENFIELD');

        foreach ([[$inv->id => 600], [$inv->id => 1500]] as $allocations) {
            try {
                app(BankReconciliationPartySettler::class)->settle($rec, $line->id, 'customer', $this->customer->id, $allocations);
                $this->fail('Over-allocation was accepted.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, CustomerPayment::count());
        $this->assertEquals(1000, $inv->refresh()->balance_due);
        $this->assertFalse($line->refresh()->is_matched);
    }

    #[Test]
    public function the_direction_must_fit_the_party(): void
    {
        $rec = $this->rec();
        $line = $this->line($rec, 6, -500, 'SOMETHING');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only money received');
        app(BankReconciliationPartySettler::class)->settle($rec, $line->id, 'customer', $this->customer->id, []);
    }

    #[Test]
    public function open_documents_are_listed_for_the_dialog(): void
    {
        $this->invoice('INV-1', 30000, 5);
        $this->bill('BILL-7', 12000, 3);
        $rec = $this->rec();

        $this->http()->getJson(route('accounting.bank-reconciliation.party-documents', [$rec, 'type' => 'customer', 'party_id' => $this->customer->id]))
            ->assertOk()->assertJsonPath('documents.0.number', 'INV-1')->assertJsonPath('documents.0.balance', 30000);

        $this->http()->getJson(route('accounting.bank-reconciliation.party-documents', [$rec, 'type' => 'vendor', 'party_id' => $this->vendor->id]))
            ->assertOk()->assertJsonPath('documents.0.number', 'BILL-7');

        $this->http()->get(route('accounting.bank-reconciliation.show', $rec))->assertOk()->assertSee('Greenfield Distributors', false);
    }

    // --------------------------------------------------------------- helpers

    private function rec(): BankReconciliation
    {
        return app(BankReconciliationService::class)->start([
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->hdfc->id,
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

    private function invoice(string $number, float $total, int $day): Invoice
    {
        return Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => $number,
            'invoice_date' => $this->d($day),
            'status' => 'Posted',
            'subtotal' => $total,
            'total_amount' => $total,
            'amount_paid' => 0,
            'balance_due' => $total,
        ]);
    }

    private function bill(string $number, float $total, int $day): VendorBill
    {
        return VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => $number,
            'vendor_id' => $this->vendor->id,
            'bill_date' => $this->d($day),
            'status' => 'Posted',
            'subtotal' => $total,
            'grand_total' => $total,
            'paid_amount' => 0,
            'due_amount' => $total,
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

    private function http()
    {
        return $this->actingAs($this->accountant)->withHeader('X-Tenant', $this->tenant->slug);
    }
}
