<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmQuotationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant the test user full CRM access via the tenant_owner role, seeded
        // the same way production RBAC is (see database/seeders/RbacSeeder.php).
        $this->seed(RbacSeeder::class);
        $tenantOwnerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $tenantOwnerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create a lead in Qualified status
        $this->lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Corp',
            'contact_person' => 'John Doe',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'Qualified',
            'priority' => 'High',
            'segment' => 'Enterprise',
            'call_date' => now(),
        ]);

        // Create product
        $this->product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Widget',
            'sku' => 'WIDG-001',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);
    }

    /** @test */
    public function convert_to_quotation_does_not_create_customer_and_does_not_convert_lead()
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.leads.convertToQuotation', $this->lead->id));

        $response->assertRedirect(route('crm.leads.show', [
            'lead' => $this->lead->id,
            'create_quotation' => 1
        ]));

        // Lead status should NOT change to Converted, and is_customer should be false
        $this->lead->refresh();
        $this->assertEquals('Qualified', $this->lead->status);
        $this->assertFalse($this->lead->is_customer);

        // No customer record should be created yet
        $customer = Customer::where('email', $this->lead->email)->first();
        $this->assertNull($customer);
    }

    /** @test */
    public function quotation_status_accepted_creates_active_customer_and_converts_lead_on_sales_order()
    {
        // 1. Create a Quotation in Draft without a customer_id (nullable)
        $quotationData = [
            'customer_id' => null,
            'sales_person_id' => $this->user->id,
            'quotation_number' => 'QT-9999',
            'quotation_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'item_name' => 'Consulting Service',
                    'quantity' => 10,
                    'unit_price' => 100,
                    'tax_rate' => 18,
                ]
            ],
            'lead_id' => $this->lead->id,
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.store'), $quotationData);

        // Response should be a redirect (3xx)
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Fetch created quotation
        $quotation = Quotation::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($quotation);
        $this->assertEquals('Draft', $quotation->status);
        $this->assertEquals($this->lead->id, $quotation->lead_id);
        $this->assertNull($quotation->customer_id);

        // Verify customer does not exist yet
        $customer = Customer::where('email', $this->lead->email)->first();
        $this->assertNull($customer);

        // Approve the quotation first
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.approve', $quotation->id));
        $response->assertStatus(302);
        
        $quotation->refresh();
        $this->assertEquals('Approved', $quotation->status);

        // 2. Update quotation status to Accepted
        $updateData = array_merge($quotationData, [
            'status' => 'Accepted',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('crm.quotations.update', $quotation->id), $updateData);

        // Verify quotation status is Accepted
        $activeQuotation = Quotation::where('parent_id', $quotation->id)->orWhere('id', $quotation->id)->where('is_current', true)->first();
        $this->assertNotNull($activeQuotation);
        $this->assertEquals('Accepted', $activeQuotation->status);

        // Verify customer is created and active
        $customer = Customer::where('email', $this->lead->email)->first();
        $this->assertNotNull($customer);
        $this->assertEquals('active', $customer->status);

        // Verify quotation is linked to the newly created customer
        $activeQuotation->refresh();
        $this->assertEquals($customer->id, $activeQuotation->customer_id);

        // Verify lead is NOT converted yet (remains Qualified)
        $this->lead->refresh();
        $this->assertEquals('Qualified', $this->lead->status);
        $this->assertFalse($this->lead->is_customer);

        // 3. Create a Sales Order referencing the quotation
        $salesOrderData = [
            'customer_id' => $customer->id,
            'quotation_id' => $activeQuotation->id,
            'sales_person_id' => $this->user->id,
            'sales_order_number' => 'SO-1234',
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 120.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $salesOrderData);

        $response->assertStatus(302);

        // Verify lead is now converted after creating the Sales Order
        $this->lead->refresh();
        $this->assertEquals('Converted', $this->lead->status);
        $this->assertTrue($this->lead->is_customer);
    }

    /** @test */
    public function cannot_manually_convert_lead_to_customer_without_accepted_quotation()
    {
        // Try to update lead status to Converted directly
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('crm.leads.updateStatus', $this->lead->id), [
                'status' => 'Converted'
            ]);

        $response->assertSessionHasErrors(['status']);
        $this->lead->refresh();
        $this->assertEquals('Qualified', $this->lead->status);
        $this->assertFalse($this->lead->is_customer);
    }

    /** @test */
    public function can_manually_convert_lead_to_customer_if_accepted_quotation_exists()
    {
        // 1. Create active customer
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'inactive',
        ]);

        // 2. Create accepted quotation with lead_id so getQuotations() finds it
        Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'lead_id'          => $this->lead->id,
            'quotation_number' => 'QT-9999',
            'quotation_date'   => now(),
            'status'           => 'Accepted',
            'total_amount'     => 1000,
        ]);

        // 3. Try to update lead status to Converted directly
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('crm.leads.updateStatus', $this->lead->id), [
                'status' => 'Converted'
            ]);

        $response->assertSessionHasNoErrors();
        $this->lead->refresh();
        $this->assertEquals('Converted', $this->lead->status);
        $this->assertTrue($this->lead->is_customer);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }

    /** @test */
    public function can_update_quotation_status_via_patch_route()
    {
        // 1. Create customer
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'inactive',
        ]);

        // 2. Create Quotation in Draft
        $quotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'lead_id'          => $this->lead->id,
            'quotation_number' => 'QT-9999',
            'quotation_date'   => now(),
            'status'           => 'Draft',
            'total_amount'     => 1000,
        ]);

        // Approve the quotation first
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.approve', $quotation->id));
        $response->assertStatus(302);

        // 3. Patch status to 'Accepted'
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('crm.quotations.updateStatus', $quotation->id), [
                'status' => 'Accepted'
            ]);

        $response->assertStatus(302);
        
        // Verify quotation status is Accepted
        $quotation->refresh();
        $this->assertEquals('Accepted', $quotation->status);

        // Verify customer is activated
        $customer->refresh();
        $this->assertEquals('active', $customer->status);

        // Verify lead is NOT converted yet (remains Qualified)
        $this->lead->refresh();
        $this->assertEquals('Qualified', $this->lead->status);
        $this->assertFalse($this->lead->is_customer);

        // 4. Create a Sales Order referencing the quotation
        $salesOrderData = [
            'customer_id' => $customer->id,
            'quotation_id' => $quotation->id,
            'sales_person_id' => $this->user->id,
            'sales_order_number' => 'SO-5678',
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 120.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $salesOrderData);

        $response->assertStatus(302);

        // Verify lead is now converted after creating the Sales Order
        $this->lead->refresh();
        $this->assertEquals('Converted', $this->lead->status);
        $this->assertTrue($this->lead->is_customer);
    }

    /** @test */
    public function cannot_transition_draft_quotation_to_sent_without_approval()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'inactive',
        ]);

        $quotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'lead_id'          => $this->lead->id,
            'quotation_number' => 'QT-9999',
            'quotation_date'   => now(),
            'status'           => 'Draft',
            'total_amount'     => 1000,
        ]);

        // Attempting to change status directly to 'Quotation Sent' should fail validation
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('crm.quotations.updateStatus', $quotation->id), [
                'status' => 'Quotation Sent'
            ]);

        $response->assertSessionHasErrors(['status']);
        
        $quotation->refresh();
        $this->assertEquals('Draft', $quotation->status);
    }

    /** @test */
    public function can_approve_and_reject_quotations_via_routes()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'inactive',
        ]);

        $quotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'customer_id'      => $customer->id,
            'lead_id'          => $this->lead->id,
            'quotation_number' => 'QT-9999',
            'quotation_date'   => now(),
            'status'           => 'Draft',
            'total_amount'     => 1000,
        ]);

        // Approve it
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.approve', $quotation->id));
        
        $response->assertStatus(302);
        $quotation->refresh();
        $this->assertEquals('Approved', $quotation->status);

        // Reject it
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.reject', $quotation->id));

        $response->assertStatus(302);
        $quotation->refresh();
        $this->assertEquals('Rejected', $quotation->status);
    }

    /** @test */
    public function quotation_approvals_index_is_accessible()
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('crm.approvals.quotations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.crm.quotations.approvals');
    }
}
