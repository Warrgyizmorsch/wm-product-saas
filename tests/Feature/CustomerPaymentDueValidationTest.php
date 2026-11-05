<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Company;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentDueValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(TenantContext::class)->set($this->tenant);

        $this->seed(RbacSeeder::class);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Test Co',
            'currency' => 'INR',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
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
            'company_id' => $this->company->id,
            'name' => 'Customer James',
            'status' => 'active',
        ]);
    }

    public function test_web_payment_cannot_exceed_invoice_balance_due(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-0009',
            'invoice_date' => now()->toDateString(),
            'status' => 'Posted',
            'total_amount' => 23.60,
            'amount_paid' => 0.00,
            'balance_due' => 23.60,
        ]);

        // Attempt to pay 30.00 against invoice with balance 23.60
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from(route('sales.payments.create', ['invoice_id' => $invoice->id, 'customer_id' => $this->customer->id]))
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-0099',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank Transfer',
                'amount' => 30.00,
                'allocate_to' => 'invoice',
                'invoice_id' => $invoice->id,
            ]);

        $response->assertRedirect(route('sales.payments.create', ['invoice_id' => $invoice->id, 'customer_id' => $this->customer->id]));
        $response->assertSessionHasErrors(['amount']);
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('customer_payments', [
            'payment_number' => 'PAY-0099',
        ]);
    }

    public function test_web_payment_succeeds_when_amount_within_invoice_balance_due(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-0010',
            'invoice_date' => now()->toDateString(),
            'status' => 'Posted',
            'total_amount' => 23.60,
            'amount_paid' => 0.00,
            'balance_due' => 23.60,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => $this->customer->id,
                'payment_number' => 'PAY-0100',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank Transfer',
                'amount' => 23.60,
                'allocate_to' => 'invoice',
                'invoice_id' => $invoice->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_payments', [
            'payment_number' => 'PAY-0100',
            'amount' => 23.60,
        ]);

        $invoice->refresh();
        $this->assertEquals(0.00, (float) $invoice->balance_due);
        $this->assertEquals('Paid', $invoice->status);
    }

    public function test_api_payment_cannot_exceed_invoice_balance_due(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-0011',
            'invoice_date' => now()->toDateString(),
            'status' => 'Posted',
            'total_amount' => 50.00,
            'amount_paid' => 0.00,
            'balance_due' => 50.00,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson('/api/sales/payments', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank Transfer',
                'amount' => 75.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
        $this->assertStringContainsString('cannot exceed', $response->json('message'));
    }

    public function test_api_payment_succeeds_when_amount_within_invoice_balance_due(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-0012',
            'invoice_date' => now()->toDateString(),
            'status' => 'Posted',
            'total_amount' => 50.00,
            'amount_paid' => 0.00,
            'balance_due' => 50.00,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson('/api/sales/payments', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Bank Transfer',
                'amount' => 50.00,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customer_payments', [
            'customer_id' => $this->customer->id,
            'amount' => 50.00,
        ]);

        $invoice->refresh();
        $this->assertEquals(0.00, (float) $invoice->balance_due);
        $this->assertEquals('Paid', $invoice->status);
    }
}
