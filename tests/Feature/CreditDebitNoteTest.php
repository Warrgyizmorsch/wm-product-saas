<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\VoucherDetail;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseReturnItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales Return / Purchase Return approval must post through the same
 * voucher_type/numbering machinery the manual Voucher Engine uses (so the
 * auto-generated Credit/Debit Note shows up in Accounting > Credit Notes /
 * Debit Notes), and must reduce the balance of the specific linked
 * Invoice/Vendor Bill rather than only the aggregate AR/AP control account.
 */
class CreditDebitNoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

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

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->seedAccountingBooks($this->tenant->id);
    }

    private function seedAccountingBooks(int $tenantId): void
    {
        $headers = [
            ['code' => '1000', 'name' => 'Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
            ['code' => '2000', 'name' => 'Liabilities', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '4000', 'name' => 'Income', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
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
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1610', 'name' => 'Input CGST', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1620', 'name' => 'Input SGST', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2110', 'name' => 'Output CGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2120', 'name' => 'Output SGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4030', 'name' => 'Sales Returns & Allowances', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '4000'],
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
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
    public function sales_return_linked_to_an_invoice_posts_a_credit_note_and_reduces_that_invoices_balance(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Buyer',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0001',
            'invoice_date' => now()->toDateString(),
            'status' => 'Paid',
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'balance_due' => 0,
        ]);

        $return = SalesReturn::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'return_number' => 'RET-0001',
            'return_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 500,
            'total_refund_amount' => 500,
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $return->id,
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total_amount' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.returns.approve', $return->id));

        $response->assertRedirect();
        $this->assertSame('Completed', $return->fresh()->status);

        // Invoice was fully paid (balance_due 0); a credit note against it
        // reduces the balance further into a "credit owed back" scenario —
        // clamped at 0 here since balance_due was already 0.
        $invoice->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $invoice->balance_due, 0.01);

        $journal = Journal::withoutGlobalScopes()
            ->where('reference_type', 'sales_return')
            ->where('reference_id', $return->id)
            ->firstOrFail();

        $this->assertSame('credit_note', $journal->voucher_type);
        $this->assertStringStartsWith('CN-', $journal->journal_number);

        $detail = VoucherDetail::withoutGlobalScopes()->where('journal_id', $journal->id)->firstOrFail();
        $this->assertSame('customer', $detail->party_type);
        $this->assertSame('Acme Buyer', $detail->party_name);
        $this->assertSame('RET-0001', $detail->reference_no);

        // The auto-posted Credit Note must be reachable from the Accounting
        // Credit Notes screen, not just exist as a bare journal.
        $listResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.vouchers.credit_note.index'));

        $listResponse->assertOk();
        $listResponse->assertSee($journal->journal_number);
    }

    /** @test */
    public function sales_return_with_a_partially_paid_invoice_reduces_its_balance_and_updates_status(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Buyer',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0002',
            'invoice_date' => now()->toDateString(),
            'status' => 'Unpaid',
            'total_amount' => 2000,
            'amount_paid' => 0,
            'balance_due' => 2000,
        ]);

        $return = SalesReturn::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'return_number' => 'RET-0002',
            'return_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 500,
            'total_refund_amount' => 500,
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $return->id,
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total_amount' => 500,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.returns.approve', $return->id))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEqualsWithDelta(1500.0, (float) $invoice->balance_due, 0.01);
        $this->assertSame('Partially Paid', $invoice->status);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->amount_paid, 0.01, 'A credit note must not be recorded as cash received.');
    }

    /** @test */
    public function sales_return_without_a_linked_invoice_still_posts_the_journal_without_touching_any_invoice(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Buyer',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $return = SalesReturn::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'return_number' => 'RET-0003',
            'return_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 300,
            'total_refund_amount' => 300,
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $return->id,
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 3,
            'unit_price' => 100,
            'total_amount' => 300,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.returns.approve', $return->id))
            ->assertRedirect();

        $journal = Journal::withoutGlobalScopes()
            ->where('reference_type', 'sales_return')
            ->where('reference_id', $return->id)
            ->firstOrFail();

        $this->assertSame('credit_note', $journal->voucher_type);
    }

    /** @test */
    public function purchase_return_linked_to_a_bill_posts_a_debit_note_and_reduces_that_bills_balance(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplies',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        StockService::recordInflow($this->tenant->id, $product->id, $warehouse->id, 50, 100.0, 'Opening Stock');

        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-0001',
            'vendor_id' => $vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'Unpaid',
            'subtotal' => 5000,
            'tax_amount' => 0,
            'grand_total' => 5000,
            'paid_amount' => 0,
            'due_amount' => 5000,
        ]);

        $return = PurchaseReturn::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'vendor_bill_id' => $bill->id,
            'return_number' => 'PRET-0001',
            'return_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 500,
            'total_refund_amount' => 500,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total_amount' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.returns.approve', $return->id));

        $response->assertRedirect();
        $this->assertSame('Completed', $return->fresh()->status);

        $bill->refresh();
        $this->assertEqualsWithDelta(4500.0, (float) $bill->due_amount, 0.01);
        $this->assertSame('Partially Paid', $bill->status);

        $journal = Journal::withoutGlobalScopes()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $return->id)
            ->firstOrFail();

        $this->assertSame('debit_note', $journal->voucher_type);
        $this->assertStringStartsWith('DN-', $journal->journal_number);

        $detail = VoucherDetail::withoutGlobalScopes()->where('journal_id', $journal->id)->firstOrFail();
        $this->assertSame('vendor', $detail->party_type);
        $this->assertSame('Acme Supplies', $detail->party_name);

        $listResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.vouchers.debit_note.index'));

        $listResponse->assertOk();
        $listResponse->assertSee($journal->journal_number);
    }
}
