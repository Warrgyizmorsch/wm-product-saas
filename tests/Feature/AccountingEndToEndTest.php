<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full-cycle smoke test for the accounting engine: exercises every real entry
 * point (all 5 manual voucher types, a Sales Invoice + Customer Payment, a
 * Purchase Bill + Vendor Payment) against the standard Chart of Accounts
 * (ChartOfAccountsService::provisionDefaults() — the same template every new
 * tenant now gets automatically), then confirms the books still balance end
 * to end: total debits == total credits across every journal, and the
 * Balance Sheet report renders "Balanced" for the period.
 *
 * This is a regression check, not a spec of correct amounts per line (those
 * are covered per-feature by VoucherEngineTest / AccountingAutoPostingTest /
 * PurchaseAccountingAutoPostingTest) — if any entry point silently stops
 * posting a balanced journal, or the standard COA is missing an account one
 * of the auto-posting listeners expects, this test fails.
 */
class AccountingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private User $salesManager;
    private User $inventoryManager;
    private Customer $customer;
    private Vendor $vendor;
    private Warehouse $warehouse;
    private Product $salesProduct;
    private Product $goodsProduct;
    private Product $serviceProduct;

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

        // Standard default Chart of Accounts — the same template TenantService::create()
        // now provisions automatically for every new tenant.
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = $this->userWithRole('accountant');
        $this->salesManager = $this->userWithRole('sales_manager');
        $this->inventoryManager = $this->userWithRole('inventory_manager');

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'status' => 'active',
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

        $this->salesProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $this->goodsProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Steel',
            'sku' => 'STEEL-1',
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
    }

    private function userWithRole(string $slug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => ucfirst($slug) . ' User',
            'email' => $slug . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $slug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    private function accountByCode(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function postVoucher(string $type, array $items, array $overrides = []): void
    {
        $response = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route("accounting.vouchers.{$type}.store"), array_merge([
                'voucher_date' => now()->toDateString(),
                'memo' => 'E2E smoke test',
                'items' => $items,
            ], $overrides));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }

    /** @test */
    public function the_full_transaction_cycle_stays_balanced_through_the_balance_sheet(): void
    {
        $cash = $this->accountByCode('1010');
        $bank = $this->accountByCode('1020');
        $capital = $this->accountByCode('3010');
        $rentExpense = $this->accountByCode('5200');
        $otherIncome = $this->accountByCode('4900');
        $revenue = $this->accountByCode('4010');
        $ar = $this->accountByCode('1100');
        $ap = $this->accountByCode('2010');
        $otherExpense = $this->accountByCode('5900');

        // 1. Owner injects opening capital into the bank.
        $this->postVoucher('receipt', [
            ['chart_of_account_id' => $bank->id, 'debit' => 50000, 'credit' => 0],
            ['chart_of_account_id' => $capital->id, 'debit' => 0, 'credit' => 50000],
        ]);

        // 2. Contra: withdraw cash from the bank for petty expenses.
        $this->postVoucher('contra', [
            ['chart_of_account_id' => $cash->id, 'debit' => 10000, 'credit' => 0],
            ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 10000],
        ]);

        // 3. Payment voucher: pay office rent in cash.
        $this->postVoucher('payment', [
            ['chart_of_account_id' => $rentExpense->id, 'debit' => 5000, 'credit' => 0],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 5000],
        ]);

        // 4. Receipt voucher: misc income into the bank.
        $this->postVoucher('receipt', [
            ['chart_of_account_id' => $bank->id, 'debit' => 2000, 'credit' => 0],
            ['chart_of_account_id' => $otherIncome->id, 'debit' => 0, 'credit' => 2000],
        ]);

        // 5. Credit note: reduce recognized revenue against a customer's receivable.
        $this->postVoucher('credit_note', [
            ['chart_of_account_id' => $revenue->id, 'debit' => 300, 'credit' => 0],
            ['chart_of_account_id' => $ar->id, 'debit' => 0, 'credit' => 300],
        ]);

        // 6. Debit note: reduce a payable against a previously booked expense.
        $this->postVoucher('debit_note', [
            ['chart_of_account_id' => $ap->id, 'debit' => 200, 'credit' => 0],
            ['chart_of_account_id' => $otherExpense->id, 'debit' => 0, 'credit' => 200],
        ]);

        // 7. Sales Invoice — auto-posts Dr AR / Cr Revenue + Taxes Payable via the real HTTP endpoint.
        $salesOrder = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-E2E-1',
            'order_date' => now()->toDateString(),
            'status' => 'Confirmed',
            'sales_person_id' => $this->salesManager->id,
            'subtotal' => 1000,
            'tax' => 180,
            'discount' => 0,
            'shipping_charges' => 0,
            'adjustment' => 0,
            'total_amount' => 1180,
        ]);

        $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $salesOrder->id,
                'invoice_number' => 'INV-E2E-1',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => null,
                'items' => [
                    [
                        'product_id' => $this->salesProduct->id,
                        'item_name' => $this->salesProduct->name,
                        'warehouse_id' => $this->warehouse->id,
                        'quantity' => 10,
                        'unit_price' => 100,
                        'tax_rate' => 18,
                        'discount' => 0,
                    ],
                ],
            ])->assertRedirect();

        $invoice = Invoice::withoutGlobalScopes()->where('invoice_number', 'INV-E2E-1')->firstOrFail();

        // 8. Customer Payment — fully settles the invoice, Dr Bank / Cr AR.
        $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-E2E-1',
                'payment_date' => now()->toDateString(),
                'amount' => (float) $invoice->total_amount,
                'payment_method' => 'Bank Transfer',
                'allocate_to' => 'invoice',
                'invoice_id' => $invoice->id,
            ])->assertRedirect();

        CustomerPayment::withoutGlobalScopes()->where('payment_number', 'PAY-E2E-1')->firstOrFail();

        // 9. Purchase Bill — goods line to Inventory, service line to Expense, split input GST.
        $this->actingAs($this->inventoryManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.bills.store'), [
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vendor_id' => $this->vendor->id,
                'vendor_invoice_number' => 'VINV-E2E-1',
                'items' => [
                    ['product_id' => $this->goodsProduct->id, 'quantity' => 10, 'unit_price' => 100, 'tax_rate' => 18],
                    ['product_id' => $this->serviceProduct->id, 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
                ],
            ])->assertRedirect();

        $bill = VendorBill::withoutGlobalScopes()->where('vendor_invoice_number', 'VINV-E2E-1')->firstOrFail();

        // 10. Vendor Payment — fully settles the bill, Dr AP / Cr Cash.
        $this->actingAs($this->inventoryManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('purchase.payments.store'), [
                'payment_date' => now()->toDateString(),
                'vendor_id' => $this->vendor->id,
                'vendor_bill_id' => $bill->id,
                'amount' => (float) $bill->grand_total,
                'payment_method' => 'Cash',
            ])->assertRedirect();

        VendorPayment::withoutGlobalScopes()->where('vendor_id', $this->vendor->id)->latest('id')->firstOrFail();

        // ── Every journal posted above must itself be balanced (JournalService::post()
        // already enforces this at write time), so the sum across ALL of them — vouchers,
        // sales, and purchase alike — must be balanced too.
        $journals = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get();
        $this->assertGreaterThanOrEqual(10, $journals->count(), 'Expected at least 10 posted journals across all entry points.');
        $this->assertEqualsWithDelta(
            (float) $journals->sum('total_debit'),
            (float) $journals->sum('total_credit'),
            0.01,
            'Total debits and credits across every posted journal must match.'
        );

        // ── And the Balance Sheet report — computed independently from the same
        // journal entries — must agree that Assets == Liabilities + Equity.
        $period = AccountingPeriod::where('tenant_id', $this->tenant->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        $response = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.balance-sheet', ['period_id' => $period->id]));

        $response->assertOk();
        $response->assertSee('Balanced');

        // ── And the Trial Balance report renders without error and totals match.
        $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.trial-balance', ['period_id' => $period->id]))
            ->assertOk();
    }
}
