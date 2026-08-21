<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Purchase\Events\BillPosted;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use App\Domains\Purchase\Events\VendorPaymentRecorded;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseReturnItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Purchase\Models\VendorPayment;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseAccountingAutoPostingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Vendor $vendor;
    private Product $goodsProduct;
    private Product $serviceProduct;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $inventoryManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'inventory_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $inventoryManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplies',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $this->goodsProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $this->serviceProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Consulting',
            'sku' => 'SVC-1',
            'type' => 'raw_material',
            'item_type' => 'Service',
            'status' => 'active',
            'unit_cost' => 0.00,
        ]);

        $this->seedAccountingBooks($this->tenant->id);
    }

    /**
     * Extends the AccountingAutoPostingTest pattern with the Purchase-side
     * codes (2010 AP, 1410 Advance to Suppliers, 1600 Input GST, 5900 Expense)
     * that the Sales test's own COA subset doesn't seed.
     */
    private function seedAccountingBooks(int $tenantId): void
    {
        $headers = [
            ['code' => '1000', 'name' => 'Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
            ['code' => '2000', 'name' => 'Liabilities', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '5000', 'name' => 'Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
        ];

        $headerIds = [];
        foreach ($headers as $header) {
            $account = ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'code' => $header['code'],
                'name' => $header['name'],
                'type' => $header['type'],
                'normal_balance' => $header['normal_balance'],
                'is_system' => true,
                'is_active' => true,
            ]);
            $headerIds[$header['code']] = $account->id;
        }

        $children = [
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1410', 'name' => 'Advance to Suppliers', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1600', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Duties & Taxes (Output)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
        ];

        foreach ($children as $child) {
            ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'code' => $child['code'],
                'name' => $child['name'],
                'type' => $child['type'],
                'normal_balance' => $child['normal_balance'],
                'parent_id' => $headerIds[$child['parent']],
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** @test */
    public function event_listeners_are_actually_wired_up(): void
    {
        $this->assertTrue(Event::hasListeners(BillPosted::class));
        $this->assertTrue(Event::hasListeners(VendorPaymentRecorded::class));
        $this->assertTrue(Event::hasListeners(PurchaseReturnApproved::class));
    }

    /** @test */
    public function bill_creation_posts_a_balanced_journal_with_correct_goods_service_split(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.bills.store'), [
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vendor_id' => $this->vendor->id,
                'vendor_invoice_number' => 'VINV-0001',
                'items' => [
                    ['product_id' => $this->goodsProduct->id, 'quantity' => 10, 'unit_price' => 100, 'tax_rate' => 18],
                    ['product_id' => $this->serviceProduct->id, 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
                ],
            ]);

        $response->assertRedirect();

        $bill = VendorBill::withoutGlobalScopes()->where('vendor_invoice_number', 'VINV-0001')->firstOrFail();

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_bill')
            ->where('reference_id', $bill->id)
            ->get();
        $this->assertCount(1, $journals);

        $journal = $journals->first();
        $this->assertEquals(Journal::SOURCE_PURCHASE, $journal->source);
        $this->assertEqualsWithDelta((float) $bill->grand_total, (float) $journal->total_debit, 0.01);
        $this->assertEqualsWithDelta((float) $bill->grand_total, (float) $journal->total_credit, 0.01);

        $inventory = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1200')->firstOrFail();
        $expense = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '5900')->firstOrFail();
        $inputGst = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1600')->firstOrFail();
        $ap = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '2010')->firstOrFail();

        $entries = $journal->entries;
        $this->assertEqualsWithDelta(1000.0, (float) $entries->firstWhere('chart_of_account_id', $inventory->id)->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $entries->firstWhere('chart_of_account_id', $expense->id)->debit, 0.01);
        $this->assertEqualsWithDelta(180.0, (float) $entries->firstWhere('chart_of_account_id', $inputGst->id)->debit, 0.01);
        $this->assertEqualsWithDelta(1680.0, (float) $entries->firstWhere('chart_of_account_id', $ap->id)->credit, 0.01);
    }

    /** @test */
    public function dispatching_bill_posted_twice_does_not_double_post(): void
    {
        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-0002',
            'vendor_id' => $this->vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'Unpaid',
            'subtotal' => 1000,
            'tax_amount' => 180,
            'grand_total' => 1180,
            'paid_amount' => 0,
            'due_amount' => 1180,
        ]);

        VendorBillItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_bill_id' => $bill->id,
            'product_id' => $this->goodsProduct->id,
            'quantity' => 10,
            'unit_rate' => 100,
            'tax_percentage' => 18,
            'total_amount' => 1180,
        ]);

        event(new BillPosted($bill));
        event(new BillPosted($bill));

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_bill')
            ->where('reference_id', $bill->id)
            ->get();
        $this->assertCount(1, $journals);
    }

    /** @test */
    public function vendor_payment_against_a_bill_debits_accounts_payable_not_duties_and_taxes(): void
    {
        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-0003',
            'vendor_id' => $this->vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'Unpaid',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.payments.store'), [
                'payment_date' => now()->toDateString(),
                'vendor_id' => $this->vendor->id,
                'vendor_bill_id' => $bill->id,
                'amount' => 1000,
                'payment_method' => 'Cash',
            ]);

        $response->assertRedirect();

        $payment = VendorPayment::withoutGlobalScopes()->where('vendor_id', $this->vendor->id)->latest('id')->firstOrFail();
        $this->assertSame('Bill Payment', $payment->payment_type);

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(1, $journals);

        $ap = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '2010')->firstOrFail();
        $cash = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1010')->firstOrFail();

        $entries = $journals->first()->entries;
        $this->assertEqualsWithDelta(1000.0, (float) $entries->firstWhere('chart_of_account_id', $ap->id)->debit, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $entries->firstWhere('chart_of_account_id', $cash->id)->credit, 0.01);
    }

    /** @test */
    public function pure_advance_vendor_payment_debits_advance_to_suppliers_not_loans_advances(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.payments.store'), [
                'payment_date' => now()->toDateString(),
                'vendor_id' => $this->vendor->id,
                'amount' => 300,
                'payment_method' => 'Bank Transfer',
            ]);

        $response->assertRedirect();

        $payment = VendorPayment::withoutGlobalScopes()->where('vendor_id', $this->vendor->id)->latest('id')->firstOrFail();
        $this->assertSame('Advance', $payment->payment_type);

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(1, $journals);

        $advance = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1410')->firstOrFail();
        $bank = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1020')->firstOrFail();

        $entries = $journals->first()->entries;
        $this->assertEqualsWithDelta(300.0, (float) $entries->firstWhere('chart_of_account_id', $advance->id)->debit, 0.01);
        $this->assertEqualsWithDelta(300.0, (float) $entries->firstWhere('chart_of_account_id', $bank->id)->credit, 0.01);
    }

    /** @test */
    public function dispatching_vendor_payment_recorded_twice_does_not_double_post(): void
    {
        $payment = VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'payment_number' => 'VPAY-0001',
            'vendor_id' => $this->vendor->id,
            'payment_type' => 'Advance',
            'payment_method' => 'Cash',
            'payment_date' => now()->toDateString(),
            'amount' => 250,
            'status' => 'Posted',
        ]);

        event(new VendorPaymentRecorded($payment));
        event(new VendorPaymentRecorded($payment));

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(1, $journals);
    }

    /** @test */
    public function purchase_return_approval_posts_a_balanced_reversing_journal(): void
    {
        StockService::recordInflow(
            $this->tenant->id,
            $this->goodsProduct->id,
            $this->warehouse->id,
            50,
            100.0,
            'Opening Stock'
        );

        $return = PurchaseReturn::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'return_number' => 'PRET-0001',
            'return_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 500,
            'total_refund_amount' => 500,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->goodsProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total_amount' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.returns.approve', $return->id));

        $response->assertRedirect();

        $return->refresh();
        $this->assertSame('Completed', $return->status);

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $return->id)
            ->get();
        $this->assertCount(1, $journals);

        $ap = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '2010')->firstOrFail();
        $inventory = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1200')->firstOrFail();

        $entries = $journals->first()->entries;
        $this->assertEqualsWithDelta(500.0, (float) $entries->firstWhere('chart_of_account_id', $ap->id)->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $entries->firstWhere('chart_of_account_id', $inventory->id)->credit, 0.01);
    }

    /** @test */
    public function missing_chart_of_accounts_does_not_block_bill_creation(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'No Books Tenant',
            'slug' => 'no-books-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books User',
            'email' => 'nobooks-purchase@example.com',
            'password' => bcrypt('password'),
        ]);

        $inventoryManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'inventory_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $otherUser->id,
            'role_id' => $inventoryManagerRole->id,
            'tenant_id' => $otherTenant->id,
        ]);

        $vendor = Vendor::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books Vendor',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books Widget',
            'sku' => 'WIDGET-NB',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $response = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', 'no-books-tenant')
            ->post(route('purchase.bills.store'), [
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vendor_id' => $vendor->id,
                'vendor_invoice_number' => 'VINV-NB-1',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendor_bills', ['vendor_invoice_number' => 'VINV-NB-1', 'tenant_id' => $otherTenant->id]);

        $journals = Journal::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->get();
        $this->assertCount(0, $journals);
    }
}
