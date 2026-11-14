<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Listeners\PostPurchaseBillJournal;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Models\TdsSection;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\Tds\TdsCalculatorInterface;
use App\Domains\Accounting\Services\Tds\TdsChallanService;
use App\Domains\Accounting\Services\Tds\TdsReturnService;
use App\Domains\Accounting\Services\Tds\TdsSectionService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Events\BillPosted;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\VendorPaymentService;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Services\CustomerPaymentService;
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
 * Non-salary TDS: section thresholds and rates, the deduction on vendor bills
 * (vendor credited net, section ledger credited), challan deposit, 26Q data,
 * and TDS customers withhold from receipts.
 */
class TdsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private TdsSection $s194c;
    private TdsSection $s194j;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->tenant = Tenant::create(['name' => 'TDS Tenant', 'slug' => 'tds-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);
        $this->seed(RbacSeeder::class);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY 2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
        ]);
        app(TdsSectionService::class)->provisionDefaultsIfMissing($this->tenant->id);

        $this->accountant = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@tds.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->accountant->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($this->accountant);

        $this->s194c = TdsSection::query()->where('code', '194C')->firstOrFail();
        $this->s194j = TdsSection::query()->where('code', '194J')->where('name', 'Professional fees')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendor(array $attributes = []): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => $attributes['name'] ?? 'Contractor ' . uniqid(),
            'status' => 'active',
            'pan' => 'ABCDE1234F',
            'tds_applicable' => true,
            'tds_section_id' => $this->s194c->id,
            'deductee_type' => 'company',
            ...$attributes,
        ]);
    }

    /** A bill with GST on top of $base; posted to the ledger like the bill screen does. */
    private function bill(Vendor $vendor, float $base, string $date, array $attributes = []): VendorBill
    {
        $gst = round($base * 0.18, 2);
        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-' . uniqid(),
            'vendor_id' => $vendor->id,
            'bill_date' => $date,
            'due_date' => $date,
            'status' => 'Unpaid',
            'gst_type' => 'cgst_sgst',
            'subtotal' => $base,
            'tax_amount' => $gst,
            'cgst_amount' => $gst / 2,
            'sgst_amount' => $gst / 2,
            'grand_total' => $base + $gst,
            'paid_amount' => 0,
            'due_amount' => $base + $gst,
            ...$attributes,
        ]);

        app(PostPurchaseBillJournal::class)->handle(new BillPosted($bill));

        return $bill->fresh();
    }

    private function balance(int $accountId): float
    {
        $journals = Journal::query()->where('status', Journal::STATUS_POSTED)->pluck('id');

        return (float) \App\Domains\Accounting\Models\JournalEntry::query()
            ->whereIn('journal_id', $journals)->where('chart_of_account_id', $accountId)
            ->selectRaw('coalesce(sum(credit),0) - coalesce(sum(debit),0) as bal')->value('bal');
    }

    #[Test]
    public function a_bill_below_the_single_and_yearly_limit_has_no_tds_but_is_recorded(): void
    {
        $bill = $this->bill($this->vendor(), 20000, '2026-05-10');

        $this->assertSame(0.0, (float) $bill->tds_amount);
        $this->assertSame(23600.0, (float) $bill->due_amount);
        $this->assertDatabaseHas('tds_deductions', ['source_id' => $bill->id, 'reason' => 'below_threshold', 'tds_amount' => 0]);
    }

    #[Test]
    public function crossing_the_194c_yearly_limit_deducts_on_the_earlier_bills_too(): void
    {
        $vendor = $this->vendor();
        $this->bill($vendor, 25000, '2026-05-10');
        $this->bill($vendor, 25000, '2026-06-10');
        $this->bill($vendor, 25000, '2026-07-10');
        // 75,000 so far; this one takes the year to 1,05,000.
        $crossing = $this->bill($vendor, 30000, '2026-08-10');

        // 2% (company) on 30,000 + the 75,000 untaxed earlier.
        $this->assertSame(2100.0, (float) $crossing->tds_amount);
        $this->assertSame(75000.0, (float) $crossing->tds_catchup_base);

        // From now on, every bill is taxed in full — no second catch-up.
        $next = $this->bill($vendor, 10000, '2026-09-10');
        $this->assertSame(200.0, (float) $next->tds_amount);
        $this->assertSame(0.0, (float) $next->tds_catchup_base);
    }

    #[Test]
    public function a_single_bill_over_30000_is_taxed_without_catch_up(): void
    {
        $bill = $this->bill($this->vendor(['deductee_type' => 'individual']), 40000, '2026-05-10');

        $this->assertSame(400.0, (float) $bill->tds_amount); // 1% for an individual
    }

    #[Test]
    public function a_vendor_without_pan_pays_the_206aa_rate(): void
    {
        $bill = $this->bill($this->vendor(['pan' => null]), 40000, '2026-05-10');

        $this->assertSame(8000.0, (float) $bill->tds_amount);
        $this->assertSame('no_pan', $bill->tds_reason);
    }

    #[Test]
    public function a_valid_lower_deduction_certificate_sets_the_rate_and_an_expired_one_does_not(): void
    {
        $certificate = ['ldc_number' => 'LDC123', 'ldc_rate' => 0.5, 'ldc_valid_from' => '2026-04-01', 'ldc_valid_to' => '2026-06-30'];
        $vendor = $this->vendor($certificate);

        $inside = $this->bill($vendor, 40000, '2026-05-10');
        $this->assertSame(200.0, (float) $inside->tds_amount);
        $this->assertSame('lower_cert', $inside->tds_reason);

        $after = $this->bill($vendor, 40000, '2026-08-10');
        $this->assertSame(800.0, (float) $after->tds_amount);
        $this->assertSame('normal', $after->tds_reason);
    }

    #[Test]
    public function the_vendor_is_credited_net_and_the_section_ledger_gets_the_tds(): void
    {
        $vendor = $this->vendor(['tds_section_id' => $this->s194j->id]);
        $bill = $this->bill($vendor, 100000, '2026-10-05');

        $this->assertSame(10000.0, (float) $bill->tds_amount); // 10% on the value before GST
        $this->assertSame(108000.0, (float) $bill->due_amount); // 1,18,000 less 10,000
        $this->assertSame(108000.0, $bill->netPayable());

        $journal = Journal::query()->where('reference_type', 'vendor_bill')->where('reference_id', $bill->id)->firstOrFail();
        $this->assertEqualsWithDelta((float) $journal->total_debit, (float) $journal->total_credit, 0.001);

        $ap = ChartOfAccount::query()->where('code', '2010')->firstOrFail();
        $this->assertSame(108000.0, $this->balance($ap->id));
        $this->assertSame(10000.0, $this->balance($this->s194j->fresh()->payable_account_id));

        // The bill-wise register opens the bill at the net amount too.
        $reference = BillReference::query()->where('source_type', 'vendor_bill')->where('source_id', $bill->id)->firstOrFail();
        $this->assertEqualsWithDelta(108000.0, abs(app(BillAllocationService::class)->billOutstanding($reference)), 0.001);
    }

    #[Test]
    public function paying_the_net_amount_marks_the_bill_paid(): void
    {
        $vendor = $this->vendor(['tds_section_id' => $this->s194j->id]);
        $bill = $this->bill($vendor, 100000, '2026-10-05');

        app(VendorPaymentService::class)->payBills([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'payment_date' => '2026-10-10',
            'payment_method' => 'Bank Transfer',
            'amount' => 108000,
            'allocations' => [$bill->id => 108000],
        ]);

        $bill->refresh();
        $this->assertSame('Paid', $bill->status);
        $this->assertSame(0.0, (float) $bill->due_amount);
    }

    #[Test]
    public function a_typed_tds_amount_is_kept(): void
    {
        $bill = $this->bill($this->vendor(), 50000, '2026-10-05', [
            'tds_section_id' => $this->s194c->id, 'tds_amount' => 750, 'tds_overridden' => true,
        ]);

        $this->assertSame(750.0, (float) $bill->tds_amount);
        $this->assertSame(1.5, (float) $bill->tds_rate);
    }

    #[Test]
    public function a_challan_clears_the_section_ledgers_and_links_its_deductions(): void
    {
        $contractor = $this->bill($this->vendor(), 40000, '2026-10-05');                                     // 194C: 800
        $professional = $this->bill($this->vendor(['tds_section_id' => $this->s194j->id]), 60000, '2026-10-06'); // 194J: 6,000
        $bank = ChartOfAccount::query()->where('code', '1020')->firstOrFail();
        $ids = TdsDeduction::query()->where('tds_amount', '>', 0)->pluck('id')->all();

        $challan = app(TdsChallanService::class)->deposit([
            'tenant_id' => $this->tenant->id,
            'challan_number' => '00123',
            'bsr_code' => '0510001',
            'deposit_date' => '2026-11-07',
            'interest' => 30,
            'bank_account_id' => $bank->id,
            'deduction_ids' => $ids,
        ]);

        $this->assertSame(6800.0, $challan->tax_amount);
        $this->assertSame(6830.0, $challan->totalAmount());
        $this->assertSame(0.0, $this->balance($this->s194c->fresh()->payable_account_id));
        $this->assertSame(0.0, $this->balance($this->s194j->fresh()->payable_account_id));
        $this->assertSame(0, TdsDeduction::query()->undeposited()->count());

        // A deduction can't be deposited twice.
        $this->expectException(\InvalidArgumentException::class);
        app(TdsChallanService::class)->deposit([
            'tenant_id' => $this->tenant->id, 'challan_number' => '00124', 'deposit_date' => '2026-11-08',
            'bank_account_id' => $bank->id, 'deduction_ids' => [$ids[0]],
        ]);
    }

    #[Test]
    public function the_26q_quarter_matches_the_register(): void
    {
        $this->bill($this->vendor(), 40000, '2026-10-05');                                     // Q3: 800
        $this->bill($this->vendor(['tds_section_id' => $this->s194j->id]), 60000, '2026-11-06'); // Q3: 6,000
        $this->bill($this->vendor(), 40000, '2026-07-05');                                     // Q2: not in Q3
        $this->bill($this->vendor(), 10000, '2026-10-07');                                     // below threshold: not reported

        $q3 = app(TdsReturnService::class)->quarter('2026-27', 3);

        $this->assertCount(2, $q3['deductions']);
        $this->assertSame(6800.0, $q3['totals']['tds']);
        $this->assertSame(100000.0, $q3['totals']['base']);
        $this->assertContains('The deductor TAN is not set (TDS > Settings).', $q3['issues']);

        $table = app(TdsReturnService::class)->exportTable($q3);
        $this->assertCount(2, $table['sections'][1]['rows']);
    }

    #[Test]
    public function a_reversed_bill_drops_out_of_the_register(): void
    {
        $bill = $this->bill($this->vendor(), 40000, '2026-10-05');
        $journal = Journal::query()->where('reference_type', 'vendor_bill')->where('reference_id', $bill->id)->firstOrFail();

        app(JournalService::class)->reverse($journal->id, 'Wrong vendor', $this->accountant->id);

        $this->assertSame(0, TdsDeduction::query()->live()->count());
        $this->assertSame(0.0, app(TdsReturnService::class)->quarter('2026-27', 3)['totals']['tds']);
    }

    #[Test]
    public function a_manual_journal_crediting_a_section_ledger_is_captured(): void
    {
        $vendor = $this->vendor(['tds_section_id' => $this->s194j->id]);
        $expense = ChartOfAccount::query()->where('code', '5550')->firstOrFail();
        $ap = ChartOfAccount::query()->where('code', '2010')->firstOrFail();

        app(JournalService::class)->post([
            ['chart_of_account_id' => $expense->id, 'debit' => 60000, 'credit' => 0],
            ['chart_of_account_id' => $ap->id, 'debit' => 0, 'credit' => 54000, 'party_type' => 'vendor', 'party_id' => $vendor->id],
            ['chart_of_account_id' => $this->s194j->fresh()->payable_account_id, 'debit' => 0, 'credit' => 6000, 'party_type' => 'vendor', 'party_id' => $vendor->id],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => '2026-10-15', 'memo' => 'Audit fee']);

        $row = TdsDeduction::query()->where('source_type', 'journal')->firstOrFail();
        $this->assertSame(6000.0, $row->tds_amount);
        $this->assertSame(60000.0, $row->base_amount);
        $this->assertSame($vendor->id, (int) $row->party_id);
    }

    #[Test]
    public function a_receipt_with_customer_tds_settles_the_invoice_in_full(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Big Buyer', 'email' => 'b@tds.test', 'status' => 'active']);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-1',
            'invoice_date' => '2026-10-01', 'status' => 'Posted', 'subtotal' => 100000,
            'total_amount' => 100000, 'amount_paid' => 0, 'balance_due' => 100000,
        ]);

        $payment = app(CustomerPaymentService::class)->receive([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'payment_date' => '2026-10-15',
            'payment_method' => 'Bank Transfer',
            'amount' => 90000,
            'tds_amount' => 10000,
            'tds_section_code' => '194J',
            'allocations' => [$invoice->id => 100000],
        ]);

        $this->assertSame('Paid', $invoice->fresh()->status);

        $tdsReceivable = ChartOfAccount::query()->where('code', '1640')->firstOrFail();
        $this->assertSame(-10000.0, $this->balance($tdsReceivable->id)); // a debit balance
        $journal = Journal::query()->where('reference_type', 'customer_payment')->where('reference_id', $payment->id)->firstOrFail();
        $this->assertSame(100000.0, (float) $journal->total_credit);
    }

    #[Test]
    public function tds_pages_load_for_the_accountant(): void
    {
        $this->bill($this->vendor(), 40000, '2026-10-05');
        $http = $this->withHeader('X-Tenant', 'tds-tenant');

        foreach (['accounting.tds.setup', 'accounting.tds.register', 'accounting.tds.returns', 'accounting.tds.receivable'] as $route) {
            $http->get(route($route))->assertOk();
        }
        $http->get(route('accounting.tds.returns.export', ['format' => 'xlsx', 'fy' => '2026-27', 'quarter' => 3]))->assertOk();
        $vendorId = TdsDeduction::query()->value('party_id');
        $http->get(route('accounting.tds.form16a', ['vendor' => $vendorId, 'fy' => '2026-27', 'quarter' => 3]))->assertOk();

        // The bill form's live TDS preview (a purchase screen: needs bill-create rights).
        $http->getJson(route('purchase.bills.tds-preview', ['vendor_id' => $vendorId, 'base' => 40000]))->assertForbidden();
        $purchase = Role::query()->whereNull('tenant_id')->where('slug', 'purchase_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $purchase->id, 'tenant_id' => $this->tenant->id]);
        $http->getJson(route('purchase.bills.tds-preview', ['vendor_id' => $vendorId, 'base' => 40000, 'bill_date' => '2026-10-05']))
            ->assertOk()->assertJson(['amount' => 800]);
    }

    #[Test]
    public function one_tenant_never_sees_another_tenants_tds(): void
    {
        $this->bill($this->vendor(), 40000, '2026-10-05');

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tds', 'status' => 'active', 'plan' => 'enterprise']);
        app(TdsSectionService::class)->provisionDefaultsIfMissing($other->id);
        app(TenantContext::class)->set($other);

        $this->assertSame(0, TdsDeduction::query()->count());
        $this->assertSame(TdsSection::query()->count(), count(TdsSectionService::DEFAULTS) + 1); // + salary (192)
        $this->assertTrue(TdsSection::query()->get()->every(fn ($s) => (int) $s->tenant_id === $other->id));

        // A vendor of another tenant can't borrow this tenant's section either.
        $foreignVendor = Vendor::create(['tenant_id' => $other->id, 'name' => 'Other vendor', 'status' => 'active', 'pan' => 'ABCDE1234F', 'tds_applicable' => true, 'tds_section_id' => $this->s194c->id]);
        $this->assertNull(app(TdsCalculatorInterface::class)->forBill($foreignVendor, 50000, '2026-10-05'));
    }
}
