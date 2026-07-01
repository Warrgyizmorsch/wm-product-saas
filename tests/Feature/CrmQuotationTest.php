<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
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
    }

    /** @test */
    public function convert_to_quotation_initiates_inactive_customer_and_does_not_convert_lead()
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

        // A customer record should be created with status 'inactive'
        $customer = Customer::where('email', $this->lead->email)->first();
        $this->assertNotNull($customer);
        $this->assertEquals('inactive', $customer->status);
    }

    /** @test */
    public function quotation_status_accepted_converts_lead_and_activates_customer()
    {
        // 1. Create inactive customer first (simulating the convertToQuotation step)
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Corp',
            'email' => 'john@acme.com',
            'phone' => '1234567890',
            'status' => 'inactive',
        ]);

        // 2. Create a Quotation in Draft
        $quotationData = [
            'customer_id' => $customer->id,
            'sales_person_id' => $this->user->id,
            'quotation_number' => 'QT-9999',
            'quotation_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'items' => [
                [
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

        // Response should be a redirect (3xx), not 422 or 500
        $response->assertStatus(302); // must redirect

        $response->assertSessionHasNoErrors();

        // Fetch created quotation — bypass BelongsToTenant global scope in tests
        $quotation = Quotation::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($quotation, 'Quotation was not created — check validation errors');
        $this->assertEquals('Draft', $quotation->status);
        $this->assertEquals($this->lead->id, $quotation->lead_id);

        // Verify customer and lead are still inactive / not converted
        $customer->refresh();
        $this->assertEquals('inactive', $customer->status);
        $this->lead->refresh();
        $this->assertEquals('Qualified', $this->lead->status);

        // Approve the quotation first
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.quotations.approve', $quotation->id));
        $response->assertStatus(302);
        
        $quotation->refresh();
        $this->assertEquals('Approved', $quotation->status);

        // 3. Update quotation status to Accepted
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

        // Verify customer is activated
        $customer->refresh();
        $this->assertEquals('active', $customer->status);

        // Verify lead is converted
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

        // Verify lead is converted
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
}
