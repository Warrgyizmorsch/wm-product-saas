<?php

namespace Tests\Feature\Purchase;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\ThreeWayMatchService;
use App\Domains\Purchase\Services\ThreeWayMatchSettings;
use App\Domains\Purchase\Services\VendorBillService;
use App\Domains\Purchase\Services\VendorPaymentService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * 3-way match: PO (rate) ↔ GRN (accepted qty) ↔ vendor bill.
 */
class ThreeWayMatchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $buyer;    // enters bills
    private User $owner;    // releases holds
    private Vendor $vendor;
    private Product $widget;
    private PurchaseOrderItem $poItem;
    private GoodsReceiptNoteItem $grnItem;
    private PurchaseOrder $po;
    private GoodsReceiptNote $grn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);

        $this->buyer = $this->makeUser('Priya Purchase', 'priya@acme.test', 'purchase_manager');
        $this->owner = $this->makeUser('Omar Owner', 'omar@acme.test', 'tenant_owner');

        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Co', 'status' => 'active']);
        $warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'WH-1', 'status' => 'active']);
        $this->widget = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Steel Rod', 'sku' => 'ROD-1', 'type' => 'raw_material',
            'item_type' => 'Goods', 'status' => 'active', 'unit_cost' => 100,
        ]);

        // PO: 100 rods @ 100. GRN: 100 received, 90 accepted.
        $this->po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id, 'purchase_order_number' => 'PO-1', 'vendor_id' => $this->vendor->id,
            'date' => now()->toDateString(), 'status' => 'Approved',
        ]);
        $this->poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $this->po->id, 'product_id' => $this->widget->id,
            'quantity' => 100, 'rate' => 100, 'amount' => 10000, 'total_amount' => 10000,
        ]);
        $this->grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id, 'grn_number' => 'GRN-1', 'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->vendor->id, 'warehouse_id' => $warehouse->id, 'received_date' => now()->toDateString(), 'status' => 'Approved',
        ]);
        $this->grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenant->id, 'goods_receipt_note_id' => $this->grn->id, 'purchase_order_item_id' => $this->poItem->id,
            'product_id' => $this->widget->id, 'ordered_qty' => 100, 'received_qty' => 100, 'accepted_qty' => 90, 'rejected_qty' => 10,
            'unit_rate' => 100, 'total_amount' => 9000,
        ]);

        $this->seedBooks();
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

    private function seedBooks(): void
    {
        $parents = [];
        foreach ([['1000', 'Assets', 'asset', 'debit'], ['2000', 'Liabilities', 'liability', 'credit'], ['5000', 'Expenses', 'expense', 'debit']] as [$code, $name, $type, $nb]) {
            $parents[$code] = ChartOfAccount::create(['tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $nb, 'is_system' => true])->id;
        }
        foreach ([
            ['1020', 'Bank Account', 'asset', 'debit', '1000'], ['1200', 'Inventory', 'asset', 'debit', '1000'],
            ['1600', 'Duties & Taxes (Input Credit)', 'asset', 'debit', '1000'], ['1610', 'Input CGST', 'asset', 'debit', '1000'],
            ['1620', 'Input SGST', 'asset', 'debit', '1000'], ['1630', 'Input IGST', 'asset', 'debit', '1000'],
            ['2010', 'Accounts Payable', 'liability', 'credit', '2000'], ['5900', 'Other Expense', 'expense', 'debit', '5000'],
        ] as [$code, $name, $type, $nb, $parent]) {
            ChartOfAccount::create(['tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $nb, 'parent_id' => $parents[$parent], 'is_system' => true]);
        }
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    private function setMode(string $mode, float $qtyTol = 0, float $priceTol = 2): void
    {
        app(ThreeWayMatchSettings::class)->update($this->tenant->id, [
            'mode' => $mode, 'qty_tolerance_percent' => $qtyTol, 'price_tolerance_percent' => $priceTol,
        ]);
    }

    /** Enter a bill against the GRN line, as the buyer, through the real service. */
    private function bill(float $qty, float $rate): VendorBill
    {
        $this->actingAs($this->buyer);

        return app(VendorBillService::class)->storeBill([
            'purchase_order_id' => $this->po->id,
            'goods_receipt_note_id' => $this->grn->id,
            'vendor_id' => $this->vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [[
                'product_id' => $this->widget->id,
                'purchase_order_item_id' => $this->poItem->id,
                'goods_receipt_note_item_id' => $this->grnItem->id,
                'quantity' => $qty,
                'unit_price' => $rate,
                'tax_rate' => 0,
            ]],
        ], $this->tenant->id)->fresh();
    }

    private function postedJournalFor(VendorBill $bill): bool
    {
        return Journal::query()->where('reference_type', 'vendor_bill')->where('reference_id', $bill->id)->exists();
    }

    public function test_a_bill_that_matches_po_and_grn_is_marked_matched_and_posts(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);

        $bill = $this->bill(90, 100);

        $this->assertSame(VendorBill::MATCH_MATCHED, $bill->match_status);
        $this->assertSame('Unpaid', $bill->status);
        $this->assertTrue($this->postedJournalFor($bill));
        $this->assertSame('3-way', $bill->match_details['lines'][0]['basis']);
    }

    public function test_billing_more_than_was_accepted_is_an_exception(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);

        // 100 were received but only 90 accepted.
        $bill = $this->bill(100, 100);

        $this->assertSame(VendorBill::MATCH_EXCEPTION, $bill->match_status);
        $this->assertFalse($bill->match_details['lines'][0]['qty_ok']);
        $this->assertStringContainsString('only 90 were accepted', $bill->match_details['lines'][0]['messages'][0]);
    }

    public function test_quantity_already_billed_on_earlier_bills_counts(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);

        $this->assertSame(VendorBill::MATCH_MATCHED, $this->bill(60, 100)->match_status);
        $second = $this->bill(40, 100); // 60 + 40 = 100 > 90 accepted

        $this->assertSame(VendorBill::MATCH_EXCEPTION, $second->match_status);
        $this->assertSame(60.0, (float) $second->match_details['lines'][0]['billed_before_qty']);
    }

    public function test_rate_above_po_beyond_tolerance_is_an_exception_but_within_or_below_is_fine(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_WARN, priceTol: 2);

        $this->assertSame(VendorBill::MATCH_MATCHED, $this->bill(10, 102)->match_status);   // +2% = at tolerance
        $this->assertSame(VendorBill::MATCH_MATCHED, $this->bill(10, 95)->match_status);    // cheaper is fine
        $over = $this->bill(10, 103);                                                         // +3%

        $this->assertSame(VendorBill::MATCH_EXCEPTION, $over->match_status);
        $this->assertFalse($over->match_details['lines'][0]['price_ok']);
        $this->assertSame(3.0, (float) $over->match_details['lines'][0]['price_variance_percent']);
    }

    public function test_quantity_tolerance_allows_a_small_overbill(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD, qtyTol: 5);

        // 90 accepted + 5% = 94.5 allowed
        $this->assertSame(VendorBill::MATCH_MATCHED, $this->bill(94, 100)->match_status);
    }

    public function test_warn_mode_flags_the_mismatch_but_still_posts(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_WARN);

        $bill = $this->bill(100, 120);

        $this->assertSame(VendorBill::MATCH_EXCEPTION, $bill->match_status);
        $this->assertSame('Unpaid', $bill->status);
        $this->assertTrue($this->postedJournalFor($bill));
    }

    public function test_hold_mode_keeps_a_mismatched_bill_out_of_the_ledger_and_unpayable(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);

        $bill = $this->bill(100, 120);

        $this->assertSame(VendorBill::STATUS_ON_HOLD, $bill->status);
        $this->assertFalse($this->postedJournalFor($bill));

        // Re-firing the posting event (as other code paths do) must not post it either.
        event(new \App\Domains\Purchase\Events\BillPosted($bill));
        $this->assertFalse($this->postedJournalFor($bill));

        // Not in the payable list, and paying it directly is refused.
        $this->assertFalse(app(VendorPaymentService::class)->openBills($this->vendor->id)->contains('id', $bill->id));

        try {
            app(VendorPaymentService::class)->payBills([
                'tenant_id' => $this->tenant->id, 'vendor_id' => $this->vendor->id, 'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank Transfer', 'amount' => 100, 'allocations' => [$bill->id => 100],
            ]);
            $this->fail('Paying a held bill should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('on hold', $e->getMessage());
        }
    }

    public function test_releasing_a_held_bill_posts_it_and_records_who_and_why(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);
        $bill = $this->bill(100, 120);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->post(route('purchase.bills.release-hold', $bill->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->post(route('purchase.bills.release-hold', $bill->id), ['reason' => 'Vendor raised price; agreed by email'])
            ->assertSessionHas('success');

        $bill->refresh();
        $this->assertSame('Unpaid', $bill->status);
        $this->assertSame($this->owner->id, (int) $bill->hold_released_by);
        $this->assertSame('Vendor raised price; agreed by email', $bill->hold_release_reason);
        $this->assertTrue($this->postedJournalFor($bill));
        $this->assertTrue(app(VendorPaymentService::class)->openBills($this->vendor->id)->contains('id', $bill->id));
    }

    public function test_the_person_who_entered_a_bill_cannot_release_it_and_buyers_lack_permission(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);
        $bill = $this->bill(100, 120);

        $this->actingAs($this->buyer)->withHeader('X-Tenant', 'acme')
            ->post(route('purchase.bills.release-hold', $bill->id), ['reason' => 'ok'])
            ->assertForbidden();

        $this->expectException(InvalidArgumentException::class);
        app(ThreeWayMatchService::class)->release($bill, $this->buyer->id, 'ok');
    }

    public function test_mode_off_skips_checking(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_OFF);

        $bill = $this->bill(100, 120);

        $this->assertNull($bill->match_status);
        $this->assertSame('Unpaid', $bill->status);
    }

    public function test_bill_matching_page_lists_held_bills_and_only_configurers_change_settings(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);
        $bill = $this->bill(100, 120);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->get(route('purchase.bill-matching.index'))
            ->assertOk()->assertSee($bill->bill_number)->assertSee('On hold');

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->get(route('purchase.bills.show', $bill->id))
            ->assertOk()->assertSee('3-way match')->assertSee('Release hold');

        $this->actingAs($this->buyer)->withHeader('X-Tenant', 'acme')
            ->put(route('purchase.bill-matching.settings'), ['mode' => 'off', 'qty_tolerance_percent' => 0, 'price_tolerance_percent' => 0])
            ->assertForbidden();
        $this->assertSame('hold', app(ThreeWayMatchSettings::class)->for($this->tenant->id)['mode']);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->put(route('purchase.bill-matching.settings'), ['mode' => 'warn', 'qty_tolerance_percent' => 1.5, 'price_tolerance_percent' => 3])
            ->assertRedirect();
        $this->assertSame(['mode' => 'warn', 'qty_tolerance_percent' => 1.5, 'price_tolerance_percent' => 3.0], app(ThreeWayMatchSettings::class)->for($this->tenant->id));
    }

    public function test_api_bills_are_matched_by_grn_header_and_held_ones_cannot_be_approved(): void
    {
        $this->setMode(ThreeWayMatchSettings::MODE_HOLD);
        // The bills API stamps the caller's company on the bill.
        $company = \App\Domains\HRMS\Models\Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme Co']);
        $this->buyer->forceFill(['company_id' => $company->id])->save();
        foreach ([$this->po, $this->poItem, $this->grn, $this->grnItem] as $record) {
            $record->forceFill(['company_id' => $company->id])->save();
        }
        \Laravel\Sanctum\Sanctum::actingAs($this->buyer);

        $response = $this->withHeader('X-Tenant', 'acme')->postJson(route('api.purchase.bills.store'), [
            'vendor_id' => $this->vendor->id,
            'purchase_order_id' => $this->po->id,
            'goods_receipt_note_id' => $this->grn->id,
            'bill_date' => now()->toDateString(),
            'items' => [['product_id' => $this->widget->id, 'quantity' => 95, 'unit_price' => 100]],
        ])->assertCreated();

        $bill = VendorBill::query()->findOrFail($response->json('data.id'));
        $this->assertSame(VendorBill::STATUS_ON_HOLD, $bill->status);
        $this->assertSame('3-way', $bill->match_details['lines'][0]['basis']);

        $this->withHeader('X-Tenant', 'acme')->patchJson(route('api.purchase.bills.status', $bill->id), ['status' => 'Approved'])
            ->assertStatus(422);
        $this->assertSame(VendorBill::STATUS_ON_HOLD, $bill->fresh()->status);
        $this->assertFalse($this->postedJournalFor($bill));
    }

    public function test_default_mode_is_warn(): void
    {
        $this->assertSame('warn', app(ThreeWayMatchSettings::class)->for($this->tenant->id)['mode']);
    }
}
