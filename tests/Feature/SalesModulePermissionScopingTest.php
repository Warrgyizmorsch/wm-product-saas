<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesModulePermissionScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $execUser;
    private User $managerUser;
    private User $adminUser;

    private CrmAccount $adminAccount;
    private CrmAccount $execAccount;
    private Customer $adminCustomer;
    private Customer $execCustomer;

    private Quotation $adminQuotation;
    private Quotation $execQuotation;

    private SalesOrder $adminOrder;
    private SalesOrder $execOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'WarrgyizMorsch Sales',
            'slug' => 'warrgyizmorsch-sales',
        ]);

        $quotationsViewPerm = Permission::firstOrCreate(
            ['name' => 'crm.quotations.view'],
            ['module' => 'crm', 'entity' => 'quotations', 'action' => 'view', 'is_system' => true]
        );

        $ordersViewPerm = Permission::firstOrCreate(
            ['name' => 'sales.orders.view'],
            ['module' => 'sales', 'entity' => 'orders', 'action' => 'view', 'is_system' => true]
        );

        $invoicesViewPerm = Permission::firstOrCreate(
            ['name' => 'sales.invoices.view'],
            ['module' => 'sales', 'entity' => 'invoices', 'action' => 'view', 'is_system' => true]
        );

        $paymentsViewPerm = Permission::firstOrCreate(
            ['name' => 'sales.payments.view'],
            ['module' => 'sales', 'entity' => 'payments', 'action' => 'view', 'is_system' => true]
        );

        $returnsViewPerm = Permission::firstOrCreate(
            ['name' => 'sales.returns.view'],
            ['module' => 'sales', 'entity' => 'returns', 'action' => 'view', 'is_system' => true]
        );

        $customersViewPerm = Permission::firstOrCreate(
            ['name' => 'crm.customers.view'],
            ['module' => 'crm', 'entity' => 'customers', 'action' => 'view', 'is_system' => true]
        );

        $ordersCreatePerm = Permission::firstOrCreate(
            ['name' => 'sales.orders.create'],
            ['module' => 'sales', 'entity' => 'orders', 'action' => 'create', 'is_system' => true]
        );

        $invoicesCreatePerm = Permission::firstOrCreate(
            ['name' => 'sales.invoices.create'],
            ['module' => 'sales', 'entity' => 'invoices', 'action' => 'create', 'is_system' => true]
        );

        // Sales Executive Role (SCOPE: OWN)
        $execRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'sales_executive',
            'name' => 'Sales Executive',
            'level' => 50,
            'is_system' => true,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $quotationsViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $ordersViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $invoicesViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $paymentsViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $returnsViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $customersViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $ordersCreatePerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $invoicesCreatePerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        // Sales Manager Role (SCOPE: TENANT)
        $managerRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'sales_manager',
            'name' => 'Sales Manager',
            'level' => 60,
            'is_system' => true,
        ]);
        RolePermission::create([
            'role_id' => $managerRole->id,
            'permission_id' => $quotationsViewPerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
        RolePermission::create([
            'role_id' => $managerRole->id,
            'permission_id' => $ordersViewPerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
        RolePermission::create([
            'role_id' => $managerRole->id,
            'permission_id' => $customersViewPerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
        RolePermission::create([
            'role_id' => $managerRole->id,
            'permission_id' => $ordersCreatePerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
        RolePermission::create([
            'role_id' => $managerRole->id,
            'permission_id' => $invoicesCreatePerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        $this->execUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $execRole->id,
            'role'      => 'sales_executive',
            'name'      => 'Devendra Verma',
            'email'     => 'devendra.sales@warrg.com',
        ]);
        $this->execUser->roles()->attach($execRole->id);

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $managerRole->id,
            'role'      => 'sales_manager',
            'name'      => 'Manager User',
            'email'     => 'manager.sales@warrg.com',
        ]);
        $this->managerUser->roles()->attach($managerRole->id);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Demo Admin',
            'email'     => 'admin.sales@example.com',
        ]);

        Customer::$skipAccountAutoCreate = true;

        $this->adminCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin Customer',
            'email'     => 'admin.cust@example.com',
            'status'    => 'active',
        ]);
        $this->adminAccount = CrmAccount::create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->adminCustomer->id,
            'name'        => 'Admin Account',
            'owner_id'    => $this->adminUser->id,
            'status'      => 'active',
        ]);

        $this->execCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Exec Customer',
            'email'     => 'exec.cust@example.com',
            'status'    => 'active',
        ]);
        $this->execAccount = CrmAccount::create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->execCustomer->id,
            'name'        => 'Exec Account',
            'owner_id'    => $this->execUser->id,
            'status'      => 'active',
        ]);

        Customer::$skipAccountAutoCreate = false;

        // Create Quotations
        $this->adminQuotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'crm_account_id'   => $this->adminAccount->id,
            'sales_person_id'  => $this->adminUser->id,
            'quotation_number' => '1001',
            'quotation_date'   => now()->toDateString(),
            'status'           => 'Accepted',
            'is_current'       => true,
            'total_amount'     => 5000,
        ]);

        $this->execQuotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'crm_account_id'   => $this->execAccount->id,
            'sales_person_id'  => $this->execUser->id,
            'quotation_number' => '1002',
            'quotation_date'   => now()->toDateString(),
            'status'           => 'Accepted',
            'is_current'       => true,
            'total_amount'     => 8000,
        ]);

        // Create Sales Orders
        $this->adminOrder = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->adminCustomer->id,
            'quotation_id'       => $this->adminQuotation->id,
            'sales_person_id'    => $this->adminUser->id,
            'sales_order_number' => '2001',
            'order_date'         => now()->toDateString(),
            'status'             => 'Confirmed',
            'total_amount'       => 5000,
        ]);

        $this->execOrder = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->execCustomer->id,
            'quotation_id'       => $this->execQuotation->id,
            'sales_person_id'    => $this->execUser->id,
            'sales_order_number' => '2002',
            'order_date'         => now()->toDateString(),
            'status'             => 'Confirmed',
            'total_amount'       => 8000,
        ]);
    }

    public function test_sales_executive_with_own_scope_sees_only_own_quotations(): void
    {
        $this->actingAs($this->execUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/quotations');

        $response->assertStatus(200);
        $quotations = $response->viewData('quotations');
        $quotationIds = collect($quotations->items())->pluck('id')->all();

        $this->assertContains($this->execQuotation->id, $quotationIds, 'Executive sees their own quotation');
        $this->assertNotContains($this->adminQuotation->id, $quotationIds, 'Executive should NOT see Admin quotation');

        // View detail own -> 200
        $showOwn = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/quotations/{$this->execQuotation->id}/detail-partial");
        $showOwn->assertStatus(200);

        // View detail other -> 403
        $showOther = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/quotations/{$this->adminQuotation->id}/detail-partial");
        $showOther->assertStatus(403);
    }

    public function test_sales_manager_with_tenant_scope_sees_all_quotations(): void
    {
        $this->actingAs($this->managerUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/quotations');

        $response->assertStatus(200);
        $quotations = $response->viewData('quotations');
        $quotationIds = collect($quotations->items())->pluck('id')->all();

        $this->assertContains($this->execQuotation->id, $quotationIds);
        $this->assertContains($this->adminQuotation->id, $quotationIds);
    }

    public function test_sales_executive_with_own_scope_sees_only_own_sales_orders(): void
    {
        $this->actingAs($this->execUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/orders');

        $response->assertStatus(200);
        $orders = $response->viewData('orders');
        $orderIds = collect($orders->items())->pluck('id')->all();

        $this->assertContains($this->execOrder->id, $orderIds, 'Executive sees their own sales order');
        $this->assertNotContains($this->adminOrder->id, $orderIds, 'Executive should NOT see Admin sales order');

        // View detail own -> 200
        $showOwn = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/sales/orders/{$this->execOrder->id}");
        $showOwn->assertStatus(200);

        // View detail other -> 403
        $showOther = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/sales/orders/{$this->adminOrder->id}");
        $showOther->assertStatus(403);
    }

    public function test_sales_manager_with_tenant_scope_sees_all_sales_orders(): void
    {
        $this->actingAs($this->managerUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/orders');

        $response->assertStatus(200);
        $orders = $response->viewData('orders');
        $orderIds = collect($orders->items())->pluck('id')->all();

        $this->assertContains($this->execOrder->id, $orderIds);
        $this->assertContains($this->adminOrder->id, $orderIds);

        $showOther = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/sales/orders/{$this->adminOrder->id}");
        $showOther->assertStatus(200);
    }

    public function test_account_manager_sees_orders_and_quotations_for_accounts_they_manage(): void
    {
        // An order made for the executive's customer (by admin/someone else)
        $orderOnMyCust = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->execCustomer->id,
            'sales_person_id'    => $this->adminUser->id,
            'sales_order_number' => '2003',
            'order_date'         => now()->toDateString(),
            'status'             => 'Confirmed',
            'total_amount'       => 12000,
        ]);

        // A quotation made for the executive's account (by admin/someone else)
        $quotationOnMyAccount = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'crm_account_id'   => $this->execAccount->id,
            'sales_person_id'  => $this->adminUser->id,
            'quotation_number' => '1003',
            'quotation_date'   => now()->toDateString(),
            'status'           => 'Accepted',
            'is_current'       => true,
            'total_amount'     => 15000,
        ]);

        $this->actingAs($this->execUser);

        // Sales Orders Check:
        $responseOrders = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/orders');
        $responseOrders->assertStatus(200);
        $orderIds = collect($responseOrders->viewData('orders')->items())->pluck('id')->all();
        $this->assertContains($orderOnMyCust->id, $orderIds, 'Account Manager sees orders for their managed account');
        $this->assertNotContains($this->adminOrder->id, $orderIds, 'Account Manager does NOT see orders of other accounts');

        // Quotations Check:
        $responseQuotations = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/quotations');
        $responseQuotations->assertStatus(200);
        $quotationIds = collect($responseQuotations->viewData('quotations')->items())->pluck('id')->all();
        $this->assertContains($quotationOnMyAccount->id, $quotationIds, 'Account Manager sees quotations for their managed account');
        $this->assertNotContains($this->adminQuotation->id, $quotationIds, 'Account Manager does NOT see quotations of other accounts');
    }

    public function test_account_manager_sees_invoices_payments_and_returns_for_accounts_they_manage(): void
    {
        // 1. Invoices
        $invoiceOnMyCust = Invoice::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->execCustomer->id,
            'invoice_number' => 'INV-TEST-001',
            'invoice_date'   => now()->toDateString(),
            'status'         => 'Posted',
            'subtotal'       => 1000,
            'total_amount'   => 1000,
            'balance_due'    => 1000,
        ]);

        $invoiceOnAdminCust = Invoice::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->adminCustomer->id,
            'invoice_number' => 'INV-TEST-002',
            'invoice_date'   => now()->toDateString(),
            'status'         => 'Posted',
            'subtotal'       => 2000,
            'total_amount'   => 2000,
            'balance_due'    => 2000,
        ]);

        // 2. Payments
        $paymentOnMyCust = CustomerPayment::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->execCustomer->id,
            'payment_number' => 'PAY-TEST-001',
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'amount'         => 1000,
            'status'         => 'Posted',
        ]);

        $paymentOnAdminCust = CustomerPayment::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->adminCustomer->id,
            'payment_number' => 'PAY-TEST-002',
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'amount'         => 2000,
            'status'         => 'Posted',
        ]);

        // 3. Returns
        $returnOnMyCust = SalesReturn::create([
            'tenant_id'           => $this->tenant->id,
            'customer_id'         => $this->execCustomer->id,
            'return_number'       => 'RET-TEST-001',
            'return_date'         => now()->toDateString(),
            'status'              => 'Pending',
            'total_amount'        => 500,
            'total_refund_amount' => 500,
        ]);

        $returnOnAdminCust = SalesReturn::create([
            'tenant_id'           => $this->tenant->id,
            'customer_id'         => $this->adminCustomer->id,
            'return_number'       => 'RET-TEST-002',
            'return_date'         => now()->toDateString(),
            'status'              => 'Pending',
            'total_amount'        => 600,
            'total_refund_amount' => 600,
        ]);

        $this->actingAs($this->execUser);

        // Invoices Check:
        $responseInvoices = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/invoices');
        $responseInvoices->assertStatus(200);
        $invoiceIds = collect($responseInvoices->viewData('invoices')->items())->pluck('id')->all();
        $this->assertContains($invoiceOnMyCust->id, $invoiceIds);
        $this->assertNotContains($invoiceOnAdminCust->id, $invoiceIds);

        // Customer Payments Check:
        $responsePayments = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/payments');
        $responsePayments->assertStatus(200);
        $paymentIds = collect($responsePayments->viewData('payments')->items())->pluck('id')->all();
        $this->assertContains($paymentOnMyCust->id, $paymentIds);
        $this->assertNotContains($paymentOnAdminCust->id, $paymentIds);

        // Sales Returns Check:
        $responseReturns = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/returns');
        $responseReturns->assertStatus(200);
        $returnIds = collect($responseReturns->viewData('returns')->items())->pluck('id')->all();
        $this->assertContains($returnOnMyCust->id, $returnIds);
        $this->assertNotContains($returnOnAdminCust->id, $returnIds);
    }

    public function test_customer_dropdown_is_automatically_scoped_by_rbac_in_sales_order_and_invoice_create(): void
    {
        // 1. Sales Executive (SCOPE_OWN on crm.customers.view)
        $this->actingAs($this->execUser);

        $soCreateResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/orders/create');
        $soCreateResponse->assertStatus(200);

        $customersInSo = $soCreateResponse->viewData('customers');
        $customerIdsInSo = $customersInSo->pluck('id')->all();

        $this->assertContains($this->execCustomer->id, $customerIdsInSo, 'Sales Executive sees their managed customer in SO dropdown');
        $this->assertNotContains($this->adminCustomer->id, $customerIdsInSo, 'Sales Executive does NOT see other customer in SO dropdown');

        // Invoice create
        $invCreateResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/invoices/create');
        $invCreateResponse->assertStatus(200);

        $customersInInv = $invCreateResponse->viewData('customers');
        $customerIdsInInv = $customersInInv->pluck('id')->all();

        $this->assertContains($this->execCustomer->id, $customerIdsInInv, 'Sales Executive sees their managed customer in Invoice dropdown');
        $this->assertNotContains($this->adminCustomer->id, $customerIdsInInv, 'Sales Executive does NOT see other customer in Invoice dropdown');

        // 2. Sales Manager (SCOPE_TENANT on crm.customers.view)
        $this->actingAs($this->managerUser);

        $mgrResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/sales/orders/create');
        $mgrResponse->assertStatus(200);

        $mgrCustomers = $mgrResponse->viewData('customers')->pluck('id')->all();
        $this->assertContains($this->execCustomer->id, $mgrCustomers, 'Sales Manager sees executive customer');
        $this->assertContains($this->adminCustomer->id, $mgrCustomers, 'Sales Manager sees admin customer');
    }
}

