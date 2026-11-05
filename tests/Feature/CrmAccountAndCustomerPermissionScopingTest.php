<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAccountAndCustomerPermissionScopingTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'WarrgyizMorsch',
            'slug' => 'warrgyizmorsch',
        ]);

        $viewPerm = Permission::firstOrCreate(
            ['name' => 'crm.customers.view'],
            ['module' => 'crm', 'entity' => 'customers', 'action' => 'view', 'is_system' => true]
        );

        $createPerm = Permission::firstOrCreate(
            ['name' => 'crm.customers.create'],
            ['module' => 'crm', 'entity' => 'customers', 'action' => 'create', 'is_system' => true]
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
            'permission_id' => $viewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $createPerm->id,
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
            'permission_id' => $viewPerm->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        $this->execUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $execRole->id,
            'role'      => 'sales_executive',
            'name'      => 'Devendra Verma',
            'email'     => 'sales.exec@warrg.com',
        ]);
        $this->execUser->roles()->attach($execRole->id);

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $managerRole->id,
            'role'      => 'sales_manager',
            'name'      => 'Manager User',
            'email'     => 'sales.manager@warrg.com',
        ]);
        $this->managerUser->roles()->attach($managerRole->id);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Demo Admin',
            'email'     => 'admin@example.com',
        ]);

        // Create Accounts: 1 owned by Demo Admin, 1 owned by Devendra Verma
        Customer::$skipAccountAutoCreate = true;

        $this->adminCustomer = Customer::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'James Manufacturing Customer',
            'email'        => 'james@example.com',
            'status'       => 'active',
        ]);

        $this->adminAccount = CrmAccount::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->adminCustomer->id,
            'name'           => 'James Manufacturing Account',
            'account_number' => 'ACC-2026-0001',
            'owner_id'       => $this->adminUser->id,
            'status'         => 'active',
        ]);

        $this->execCustomer = Customer::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Devendra Customer Ltd',
            'email'        => 'devendra.cust@example.com',
            'status'       => 'active',
        ]);

        $this->execAccount = CrmAccount::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->execCustomer->id,
            'name'           => 'Devendra Account Ltd',
            'account_number' => 'ACC-2026-0002',
            'owner_id'       => $this->execUser->id,
            'status'         => 'active',
        ]);

        Customer::$skipAccountAutoCreate = false;
    }

    public function test_sales_executive_with_own_scope_sees_only_own_accounts(): void
    {
        $this->actingAs($this->execUser);

        // 1. Web list
        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/accounts');

        $response->assertStatus(200);
        $accounts = $response->viewData('accounts');
        $accountIds = collect($accounts->items())->pluck('id')->all();

        $this->assertContains($this->execAccount->id, $accountIds, 'Executive should see their owned account');
        $this->assertNotContains($this->adminAccount->id, $accountIds, 'Executive should NOT see Admin owned account');

        // 2. View details of own account -> 200
        $showOwnResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/accounts/{$this->execAccount->id}");
        $showOwnResponse->assertStatus(200);

        // 3. View details of someone else's account -> 403 Forbidden
        $showOtherResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/accounts/{$this->adminAccount->id}");
        $showOtherResponse->assertStatus(403);
    }

    public function test_sales_manager_with_tenant_scope_sees_all_accounts(): void
    {
        $this->actingAs($this->managerUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/accounts');

        $response->assertStatus(200);
        $accounts = $response->viewData('accounts');
        $accountIds = collect($accounts->items())->pluck('id')->all();

        $this->assertContains($this->execAccount->id, $accountIds, 'Manager should see Executive account');
        $this->assertContains($this->adminAccount->id, $accountIds, 'Manager should see Admin account');

        // View details of any account -> 200
        $showResponse = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/accounts/{$this->adminAccount->id}");
        $showResponse->assertStatus(200);
    }

    public function test_sales_executive_with_own_scope_sees_only_own_customers(): void
    {
        $this->actingAs($this->execUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/customers');

        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $customerIds = collect($customers->items())->pluck('id')->all();

        $this->assertContains($this->execCustomer->id, $customerIds, 'Executive should see customer linked to their account');
        $this->assertNotContains($this->adminCustomer->id, $customerIds, 'Executive should NOT see Admin customer');

        // Check scoped counts
        $this->assertEquals(1, $response->viewData('totalCount'), 'Total count should only count owned customers for executive');

        // View details
        $showOwn = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/customers/{$this->execCustomer->id}");
        $showOwn->assertStatus(200);

        $showOther = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/customers/{$this->adminCustomer->id}");
        $showOther->assertStatus(403);
    }

    public function test_sales_manager_with_tenant_scope_sees_all_customers(): void
    {
        $this->actingAs($this->managerUser);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/customers');

        $response->assertStatus(200);
        $customers = $response->viewData('customers');
        $customerIds = collect($customers->items())->pluck('id')->all();

        $this->assertContains($this->execCustomer->id, $customerIds);
        $this->assertContains($this->adminCustomer->id, $customerIds);

        $this->assertEquals(2, $response->viewData('totalCount'), 'Manager should see all tenant customers in count');

        $showOther = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get("/crm/customers/{$this->adminCustomer->id}");
        $showOther->assertStatus(200);
    }
}

