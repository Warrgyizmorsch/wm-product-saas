<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\GstReturnDocument;
use App\Domains\Accounting\Models\GstReturnFiling;
use App\Domains\Accounting\Services\Gst\Gstr1ReturnService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\GstConfiguration;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Upload GST Returns → GSTR-1 (Tally-style): which vouchers are pending,
 * the portal JSON, and upload status through edits and cancellations.
 */
class Gstr1UploadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private Product $pump;
    private Product $service;
    private Customer $registered;
    private Customer $walkIn;
    private Customer $karnataka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'GST Tenant', 'slug' => 'gst-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);
        $this->seed(RbacSeeder::class);

        $this->accountant = $this->user('acc@gst.test', 'accountant');
        $this->actingAs($this->accountant);

        GstConfiguration::create([
            'tenant_id' => $this->tenant->id,
            'seller_gstin' => '27AAACW1234A1Z5',
            'legal_name' => 'Wargyizmorsch Pvt Ltd',
            'state_code' => '27',
            'is_active' => true,
            'is_default' => true,
        ]);

        $pcs = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Piece', 'code' => 'PCS']);
        $this->pump = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Hydraulic Pump', 'sku' => 'PUMP', 'type' => 'finished_good', 'status' => 'active', 'uom_id' => $pcs->id, 'hsn_sac' => '8413']);
        $this->service = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Installation', 'sku' => 'INST', 'type' => 'service', 'status' => 'active', 'uom_id' => $pcs->id, 'hsn_sac' => '998719']);

        $this->registered = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Greenfield Distributors', 'gstin' => '27ABCDE1234F1Z5', 'status' => 'active']);
        $this->walkIn = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Walk-in Pune', 'billing_address' => 'FC Road, Pune, Maharashtra 411004', 'status' => 'active']);
        $this->karnataka = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Bengaluru Retail', 'shipping_address' => 'MG Road, Bengaluru, Karnataka 560001', 'status' => 'active']);
    }

    #[Test]
    public function the_period_is_split_into_gstr1_tables_and_exported_as_portal_json(): void
    {
        $this->sampleMonth();

        $page = $this->http()->get(route('accounting.gst-returns.gstr1', $this->period()));
        $page->assertOk()
            ->assertSee('Upload GSTR-1')
            ->assertSee('Pending to Upload (Voucher Count - 4; Summary Count - 6)', false)
            ->assertSee('B2B Invoices - 4A, 4B, 6B, 6C')
            ->assertSee('B2C (Large) Invoices - 5A, 5B')
            ->assertSee('Export (Offline)')
            ->assertDontSee('INV-DRAFT');

        $response = $this->http()->post(route('accounting.gst-returns.gstr1.export', $this->period()), ['scope' => 'all', 'mark_uploaded' => 1]);
        $response->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('GSTR1_27AAACW1234A1Z5_082026_', $response->headers->get('Content-Disposition'));

        $json = json_decode($response->getContent(), true);
        $this->assertSame('27AAACW1234A1Z5', $json['gstin']);
        $this->assertSame('082026', $json['fp']);

        // B2B: rate-wise items, intra-state → CGST/SGST.
        $this->assertSame('27ABCDE1234F1Z5', $json['b2b'][0]['ctin']);
        $inv = $json['b2b'][0]['inv'][0];
        $this->assertSame(['INV-001', '05-08-2026', 13900.0, '27', 'N', 'R'], [$inv['inum'], $inv['idt'], (float) $inv['val'], $inv['pos'], $inv['rchrg'], $inv['inv_typ']]);
        $this->assertEquals(['txval' => 2000, 'rt' => 5, 'camt' => 50, 'samt' => 50, 'csamt' => 0], $inv['itms'][0]['itm_det']);
        $this->assertEquals(['txval' => 10000, 'rt' => 18, 'camt' => 900, 'samt' => 900, 'csamt' => 0], $inv['itms'][1]['itm_det']);

        // B2C Large: inter-state over ₹1 lakh, place of supply from the address.
        $this->assertSame('29', $json['b2cl'][0]['pos']);
        $this->assertEquals(['txval' => 150000, 'rt' => 18, 'iamt' => 27000, 'csamt' => 0], $json['b2cl'][0]['inv'][0]['itms'][0]['itm_det']);

        // B2C Small: summarised by place of supply and rate.
        $this->assertEquals([['sply_ty' => 'INTRA', 'pos' => '27', 'typ' => 'OE', 'txval' => 5000, 'rt' => 18, 'camt' => 450, 'samt' => 450, 'csamt' => 0]], $json['b2cs']);

        // Credit note to a registered customer.
        $note = $json['cdnr'][0]['nt'][0];
        $this->assertSame(['C', 'CN-001', 5900.0], [$note['ntty'], $note['nt_num'], (float) $note['val']]);
        $this->assertEquals(['txval' => 5000, 'rt' => 18, 'camt' => 450, 'samt' => 450, 'csamt' => 0], $note['itms'][0]['itm_det']);

        // HSN summary split B2B / B2C, net of the credit note; services report UQC NA.
        $hsnB2b = collect($json['hsn']['hsn_b2b'])->keyBy('hsn_sc');
        $this->assertEquals([1, 5000, 450], [$hsnB2b['8413']['qty'], $hsnB2b['8413']['txval'], $hsnB2b['8413']['camt']]);
        $this->assertSame(['NA', 0], [$hsnB2b['998719']['uqc'], $hsnB2b['998719']['qty']]);
        $hsnB2c = collect($json['hsn']['hsn_b2c'])->keyBy('hsn_sc');
        $this->assertEquals([31, 155000, 27000, 'PCS'], [$hsnB2c['8413']['qty'], $hsnB2c['8413']['txval'], $hsnB2c['8413']['iamt'], $hsnB2c['8413']['uqc']]);

        // Documents issued: the cancelled invoice counts, the draft does not.
        $docs = collect($json['doc_issue']['doc_det'])->keyBy('doc_num');
        $this->assertEquals(['from' => 'INV-001', 'to' => 'INV-004', 'totnum' => 4, 'cancel' => 1, 'net_issue' => 3], array_diff_key($docs[1]['docs'][0], ['num' => 1]));
        $this->assertSame(1, $docs[5]['docs'][0]['totnum']);

        // Everything is now uploaded.
        $this->assertSame(4, GstReturnDocument::where('status', 'uploaded')->count());
        $this->assertSame(0, $this->build()['pending_count']);
        $this->assertSame(1, GstReturnFiling::count());
    }

    #[Test]
    public function edits_after_upload_show_as_modified_and_cancellations_are_sent_as_deletions(): void
    {
        [$b2b, , $b2cl] = $this->sampleMonth();
        app(Gstr1ReturnService::class)->export($this->build(), null, $this->accountant->id);

        // Change the B2B invoice, cancel the B2C large one.
        $b2b->items()->first()->update(['unit_price' => 5500, 'quantity' => 2]);
        $b2b->update(['subtotal' => 11000 + 2000, 'cgst_amount' => 1040, 'sgst_amount' => 1040, 'tax_amount' => 2080, 'total_amount' => 15080]);
        $b2cl->update(['status' => 'Cancelled']);

        $built = $this->build();
        $this->assertSame('modified', $built['documents']["invoice:{$b2b->id}"]['state']);
        $this->assertSame('delete', $built['documents']["invoice:{$b2cl->id}"]['state']);
        $this->assertSame(2, $built['pending_count']);

        $json = json_decode(app(Gstr1ReturnService::class)->export($built, null, $this->accountant->id)->payload, true);
        $this->assertSame(15080.0, (float) $json['b2b'][0]['inv'][0]['val']);
        $this->assertArrayNotHasKey('flag', $json['b2b'][0]['inv'][0]);
        $this->assertSame('D', $json['b2cl'][0]['inv'][0]['flag']);
        $this->assertSame('INV-003', $json['b2cl'][0]['inv'][0]['inum']);

        $after = $this->build();
        $this->assertSame(0, $after['pending_count']);
        $this->assertSame('removed', $after['documents']["invoice:{$b2cl->id}"]['state']);
        $this->assertSame('deleted', GstReturnDocument::where('document_id', $b2cl->id)->value('status'));
    }

    #[Test]
    public function delete_requests_and_resets_follow_tally(): void
    {
        [$b2b] = $this->sampleMonth();
        app(Gstr1ReturnService::class)->export($this->build(), null, $this->accountant->id);
        $key = "invoice:{$b2b->id}";

        $this->http()->post(route('accounting.gst-returns.gstr1.status', $this->period()), ['action' => 'delete_request', 'keys' => [$key]])
            ->assertSessionHas('success');
        $this->assertSame('delete_requested', $this->build()['documents'][$key]['state']);

        $this->http()->post(route('accounting.gst-returns.gstr1.status', $this->period()), ['action' => 'reset_delete', 'keys' => [$key]]);
        $this->assertSame('uploaded', $this->build()['documents'][$key]['state']);

        // Send the deletion.
        app(Gstr1ReturnService::class)->requestDelete([$key]);
        $json = json_decode(app(Gstr1ReturnService::class)->export($this->build(), [$key], $this->accountant->id)->payload, true);
        $this->assertSame('D', $json['b2b'][0]['inv'][0]['flag']);
        $this->assertSame('removed', $this->build()['documents'][$key]['state']);

        // Reset upload status: pending again.
        $this->http()->post(route('accounting.gst-returns.gstr1.status', $this->period()), ['action' => 'reset', 'keys' => [$key]]);
        $this->assertSame('pending', $this->build()['documents'][$key]['state']);

        // Mark as uploaded without a file.
        $this->http()->post(route('accounting.gst-returns.gstr1.status', $this->period()), ['action' => 'mark_uploaded', 'keys' => [$key]])
            ->assertSessionHas('success');
        $this->assertSame('uploaded', $this->build()['documents'][$key]['state']);
    }

    #[Test]
    public function vouchers_with_errors_are_held_back_with_the_reason(): void
    {
        $noHsn = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Loose Part', 'sku' => 'LP', 'type' => 'finished_good', 'status' => 'active']);
        $badGstin = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Typo Traders', 'gstin' => '27ABCDE1234', 'status' => 'active']);

        $a = $this->invoice('INV-010', $this->registered, [[$noHsn, 1, 1000, 18]], 5);
        $b = $this->invoice('INV-011', $badGstin, [[$this->pump, 1, 1000, 18]], 5);
        $c = $this->invoice('INV-012', $this->registered, [[$this->pump, 1, 1000, 18]], 5, taxOverride: 100);
        $d = $this->invoice('INV-013', $this->karnataka, [[$this->pump, 1, 1000, 18]], 5); // intra tax to another state

        $built = $this->build();
        $issues = fn ($invoice) => implode(' ', $built['documents']["invoice:{$invoice->id}"]['issues']);

        $this->assertStringContainsString('HSN/SAC missing for Loose Part', $issues($a));
        $this->assertStringContainsString('not a valid GSTIN', $issues($b));
        $this->assertStringContainsString('does not agree with rate', $issues($c));
        $this->assertStringContainsString('CGST/SGST charged, but the place of supply is Karnataka', $issues($d));
        $this->assertCount(4, $built['groups']['exception']);
        $this->assertSame(0, $built['pending_count']);

        $this->http()->get(route('accounting.gst-returns.gstr1', $this->period()))
            ->assertSee('Not Ready to Upload')
            ->assertSee('HSN/SAC missing for Loose Part');
    }

    #[Test]
    public function export_needs_the_business_gstin_and_a_whole_month(): void
    {
        $this->sampleMonth();

        $this->http()->post(route('accounting.gst-returns.gstr1.export', ['from' => '2026-08-01', 'to' => '2026-08-15']), ['scope' => 'all'])
            ->assertSessionHas('error', 'Choose a full month (or a full quarter) as the return period to export.');

        GstConfiguration::query()->update(['seller_gstin' => null]);
        $this->http()->post(route('accounting.gst-returns.gstr1.export', $this->period()), ['scope' => 'all'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'GSTIN is missing'));

        $this->assertSame(0, GstReturnFiling::count());
        $this->assertSame('062026', app(Gstr1ReturnService::class)->returnPeriod(Carbon::parse('2026-04-01'), Carbon::parse('2026-06-30')));
    }

    #[Test]
    public function an_auditor_can_look_but_not_file(): void
    {
        $this->sampleMonth();
        $auditor = $this->user('audit@gst.test', 'auditor');

        $this->actingAs($auditor)->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('accounting.gst-returns.gstr1', $this->period()))->assertOk()->assertDontSee('Export (Offline)');
        $this->actingAs($auditor)->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('accounting.gst-returns.gstr1.export', $this->period()), ['scope' => 'all'])->assertForbidden();
    }

    // --------------------------------------------------------------- helpers

    /** @return array{0: Invoice, 1: Invoice, 2: Invoice} */
    private function sampleMonth(): array
    {
        $b2b = $this->invoice('INV-001', $this->registered, [[$this->pump, 2, 5000, 18], [$this->service, 1, 2000, 5]], 5);
        $b2cs = $this->invoice('INV-002', $this->walkIn, [[$this->pump, 1, 5000, 18]], 8);
        $b2cl = $this->invoice('INV-003', $this->karnataka, [[$this->pump, 30, 5000, 18]], 10, igst: true);
        $this->invoice('INV-004', $this->walkIn, [[$this->pump, 1, 100, 18]], 11, status: 'Cancelled');
        $this->invoice('INV-DRAFT', $this->walkIn, [[$this->pump, 1, 100, 18]], 12, status: 'Draft');

        $return = SalesReturn::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->registered->id, 'invoice_id' => $b2b->id,
            'return_number' => 'CN-001', 'return_date' => '2026-08-20', 'status' => 'Completed',
            'total_amount' => 5000, 'total_refund_amount' => 5900,
        ]);
        $warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'WH-MAIN', 'status' => 'active', 'is_default' => true]);
        SalesReturnItem::create([
            'warehouse_id' => $warehouse->id,
            'sales_return_id' => $return->id, 'invoice_item_id' => $b2b->items()->where('product_id', $this->pump->id)->value('id'),
            'product_id' => $this->pump->id, 'quantity' => 1, 'unit_price' => 5000, 'total_amount' => 5000,
        ]);

        return [$b2b, $b2cs, $b2cl];
    }

    /** @param list<array{0: Product, 1: float, 2: float, 3: float}> $lines */
    private function invoice(string $number, Customer $customer, array $lines, int $day, bool $igst = false, string $status = 'Posted', ?float $taxOverride = null): Invoice
    {
        $subtotal = 0;
        $tax = 0;
        foreach ($lines as [, $qty, $price, $rate]) {
            $subtotal += $qty * $price;
            $tax += $qty * $price * $rate / 100;
        }
        $tax = $taxOverride ?? $tax;

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => $number,
            'invoice_date' => sprintf('2026-08-%02d', $day), 'status' => $status,
            'gst_type' => $igst ? 'igst' : 'cgst_sgst', 'tax_type' => 'item_wise_tax',
            'subtotal' => $subtotal, 'discount_amount' => 0, 'tax_amount' => $tax,
            'cgst_amount' => $igst ? 0 : $tax / 2, 'sgst_amount' => $igst ? 0 : $tax / 2, 'igst_amount' => $igst ? $tax : 0,
            'total_amount' => $subtotal + $tax, 'balance_due' => $subtotal + $tax,
        ]);

        foreach ($lines as [$product, $qty, $price, $rate]) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'product_id' => $product->id, 'item_name' => $product->name,
                'quantity' => $qty, 'unit_price' => $price, 'discount' => 0, 'tax_rate' => $rate,
                'subtotal' => $qty * $price, 'tax_amount' => $qty * $price * $rate / 100, 'total_amount' => $qty * $price * (1 + $rate / 100),
            ]);
        }

        return $invoice->fresh();
    }

    private function build(): array
    {
        return app(Gstr1ReturnService::class)->build(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
    }

    private function period(): array
    {
        return ['from' => '2026-08-01', 'to' => '2026-08-31'];
    }

    private function user(string $email, string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => ucfirst($roleSlug), 'email' => $email, 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    private function http()
    {
        return $this->actingAs($this->accountant)->withHeader('X-Tenant', $this->tenant->slug);
    }
}
