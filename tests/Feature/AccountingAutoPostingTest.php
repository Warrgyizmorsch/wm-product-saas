<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Events\StockOutflowRecorded;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Events\BomApproved;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AccountingAutoPostingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Product $product;
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

        $salesManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $salesManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $this->seedAccountingBooks($this->tenant->id);
    }

    /**
     * Mirrors AccountingChartOfAccountsSeeder's demo-tenant seeding, but scoped
     * to whatever tenant a given test needs — the shipped seeder only targets
     * the 'demo' tenant, so tests cannot rely on it.
     */
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
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '2020', 'name' => 'Taxes Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Customer Advances', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
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

    private function createSalesOrder(int $tenantId, int $customerId, int $salesPersonId): SalesOrder
    {
        return SalesOrder::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'sales_order_number' => 'SO-' . uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'Confirmed',
            'sales_person_id' => $salesPersonId,
            'subtotal' => 1000,
            'tax' => 180,
            'discount' => 0,
            'shipping_charges' => 0,
            'adjustment' => 0,
            'total_amount' => 1180,
        ]);
    }

    /** @test */
    public function event_listeners_are_actually_wired_up(): void
    {
        $this->assertTrue(Event::hasListeners(InvoicePosted::class));
        $this->assertTrue(Event::hasListeners(CustomerPaymentReceived::class));
        $this->assertTrue(Event::hasListeners(StockOutflowRecorded::class));
        // Regression proof: Production's previously-dead event/listener pair now fires too.
        $this->assertTrue(Event::hasListeners(BomApproved::class));
    }

    /** @test */
    public function invoice_creation_posts_a_balanced_journal_via_the_real_http_endpoint(): void
    {
        $salesOrder = $this->createSalesOrder($this->tenant->id, $this->customer->id, $this->user->id);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $salesOrder->id,
                'invoice_number' => 'INV-0001',
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'notes' => null,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'warehouse_id' => $this->warehouse->id,
                        'quantity' => 10,
                        'unit_price' => 100,
                        'tax_rate' => 18,
                        'discount' => 0,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $invoice = Invoice::withoutGlobalScopes()->where('invoice_number', 'INV-0001')->firstOrFail();

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoice->id)
            ->get();
        $this->assertCount(1, $journals);

        $journal = $journals->first();
        $this->assertEquals(Journal::SOURCE_SALES, $journal->source);
        $this->assertEqualsWithDelta((float) $invoice->grand_total, (float) $journal->total_debit, 0.01);
        $this->assertEqualsWithDelta((float) $invoice->grand_total, (float) $journal->total_credit, 0.01);
    }

    /** @test */
    public function dispatching_invoice_posted_twice_does_not_double_post(): void
    {
        $salesOrder = $this->createSalesOrder($this->tenant->id, $this->customer->id, $this->user->id);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $salesOrder->id,
            'invoice_number' => 'INV-0002',
            'invoice_date' => now()->toDateString(),
            'status' => 'Draft',
            'subtotal' => 1000,
            'tax_total' => 180,
            'discount' => 0,
            'grand_total' => 1180,
        ]);

        event(new InvoicePosted($invoice));
        event(new InvoicePosted($invoice));

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'invoice')
            ->where('reference_id', $invoice->id)
            ->get();
        $this->assertCount(1, $journals);
    }

    /** @test */
    public function customer_payment_allocated_to_an_invoice_posts_bank_against_receivable(): void
    {
        $salesOrder = $this->createSalesOrder($this->tenant->id, $this->customer->id, $this->user->id);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $salesOrder->id,
            'invoice_number' => 'INV-0003',
            'invoice_date' => now()->toDateString(),
            'status' => 'Sent',
            'subtotal' => 1000,
            'tax_total' => 0,
            'discount' => 0,
            'grand_total' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-INV-1',
                'payment_date' => now()->toDateString(),
                'amount' => 1000,
                'payment_method' => 'Cash',
                'allocate_to' => 'invoice',
                'invoice_id' => $invoice->id,
            ]);

        $response->assertRedirect();

        $payment = CustomerPayment::withoutGlobalScopes()->where('payment_number', 'PAY-INV-1')->firstOrFail();
        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'customer_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(1, $journals);

        $bank = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1020')->firstOrFail();
        $ar = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1100')->firstOrFail();

        $entries = $journals->first()->entries;
        $this->assertEqualsWithDelta(1000, (float) $entries->firstWhere('chart_of_account_id', $bank->id)->debit, 0.01);
        $this->assertEqualsWithDelta(1000, (float) $entries->firstWhere('chart_of_account_id', $ar->id)->credit, 0.01);
    }

    /** @test */
    public function customer_payment_allocated_as_sales_order_advance_posts_against_customer_advances(): void
    {
        $salesOrder = $this->createSalesOrder($this->tenant->id, $this->customer->id, $this->user->id);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-ADV-1',
                'payment_date' => now()->toDateString(),
                'amount' => 500,
                'payment_method' => 'Cash',
                'allocate_to' => 'sales_order',
                'sales_order_id' => $salesOrder->id,
            ]);

        $response->assertRedirect();

        $payment = CustomerPayment::withoutGlobalScopes()->where('payment_number', 'PAY-ADV-1')->firstOrFail();
        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'customer_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(1, $journals);

        $advances = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '2200')->firstOrFail();
        $entries = $journals->first()->entries;
        $this->assertEqualsWithDelta(500, (float) $entries->firstWhere('chart_of_account_id', $advances->id)->credit, 0.01);
    }

    /** @test */
    public function unallocated_customer_payment_posts_nothing_and_does_not_throw(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-UNALLOC-1',
                'payment_date' => now()->toDateString(),
                'amount' => 200,
                'payment_method' => 'Cash',
                'allocate_to' => 'unallocated',
            ]);

        $response->assertRedirect();

        $payment = CustomerPayment::withoutGlobalScopes()->where('payment_number', 'PAY-UNALLOC-1')->firstOrFail();
        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'customer_payment')
            ->where('reference_id', $payment->id)
            ->get();
        $this->assertCount(0, $journals);
    }

    /** @test */
    public function delivery_order_stock_outflow_posts_cogs_journal(): void
    {
        StockService::recordInflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            50,
            100.0,
            'Opening Stock'
        );

        $transaction = StockService::recordOutflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            5,
            'DeliveryOrder',
            999
        );

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', 'delivery_order')
            ->where('reference_id', 999)
            ->get();
        $this->assertCount(1, $journals);

        $journal = $journals->first();
        $this->assertEquals(Journal::SOURCE_INVENTORY, $journal->source);
        $this->assertEqualsWithDelta((float) $transaction->total_value, (float) $journal->total_debit, 0.01);
    }

    /** @test */
    public function production_material_issue_outflow_does_not_post_a_journal(): void
    {
        StockService::recordInflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            50,
            100.0,
            'Opening Stock'
        );

        StockService::recordOutflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            5,
            'Production Material Issue',
            888
        );

        $journals = Journal::withoutGlobalScopes()
            ->where('source', Journal::SOURCE_INVENTORY)
            ->get();

        $this->assertCount(0, $journals);
    }

    /** @test */
    public function missing_fiscal_period_does_not_block_invoice_creation(): void
    {
        // A tenant with no chart of accounts / fiscal year seeded at all.
        $otherTenant = Tenant::create([
            'name' => 'No Books Tenant',
            'slug' => 'no-books-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books User',
            'email' => 'nobooks@example.com',
            'password' => bcrypt('password'),
        ]);

        $salesManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $otherUser->id,
            'role_id' => $salesManagerRole->id,
            'tenant_id' => $otherTenant->id,
        ]);

        $customer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books Customer',
            'email' => 'nobookscustomer@example.com',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books Warehouse',
            'code' => 'WH-NB',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'No Books Widget',
            'sku' => 'WIDGET-NB',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $salesOrder = $this->createSalesOrder($otherTenant->id, $customer->id, $otherUser->id);

        $response = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', 'no-books-tenant')
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $salesOrder->id,
                'invoice_number' => 'INV-NB-1',
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'notes' => null,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'quantity' => 1,
                        'unit_price' => 100,
                        'tax_rate' => 0,
                        'discount' => 0,
                    ],
                ],
            ]);

        // The invoice itself must still be created successfully — a missing
        // fiscal period is an accounting-side gap, not a reason to fail the sale.
        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-NB-1', 'tenant_id' => $otherTenant->id]);

        $journals = Journal::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->get();
        $this->assertCount(0, $journals);
    }
}
