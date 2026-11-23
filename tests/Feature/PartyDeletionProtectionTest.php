<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyDeletionException;
use App\Services\PartyDeletionGuard;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS 2.3 Requirement:
 * "Parties linked to transactions are never hard-deleted."
 */
class PartyDeletionProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Deletion Guard Tenant',
            'slug' => 'del-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(\App\Support\Tenancy::class)->setTenant($this->tenant);
        $this->seed(RbacSeeder::class);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner User',
            'email' => 'owner@del.test',
            'password' => bcrypt('password'),
        ]);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->first();
        if ($ownerRole) {
            UserRole::create([
                'user_id' => $this->owner->id,
                'role_id' => $ownerRole->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }
    }

    private function createCustomer(string $name, float $openingBalance = 0.0): Customer
    {
        return Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@del.test',
            'status' => 'active',
            'opening_balance' => $openingBalance,
        ]);
    }

    private function createVendor(string $name, float $openingBalance = 0.0): Vendor
    {
        return Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'code' => 'VEND-' . uniqid(),
            'email' => strtolower(str_replace(' ', '', $name)) . '@del.test',
            'status' => 'active',
            'opening_balance' => $openingBalance,
        ]);
    }

    public function test_customer_with_invoices_cannot_be_hard_deleted(): void
    {
        $customer = $this->createCustomer('Invoice Customer');

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DEL-01',
            'invoice_date' => now(),
            'total_amount' => 1500.00,
            'status' => 'unpaid',
        ]);

        $guard = app(PartyDeletionGuard::class);
        $this->assertTrue($guard->hasTransactions($customer));
        $this->assertArrayHasKey('invoices', $guard->transactionSummary($customer));

        $this->expectException(PartyDeletionException::class);
        $this->expectExceptionMessage('Parties linked to transactions can never be hard-deleted');

        $customer->delete();
    }

    public function test_customer_with_sales_orders_cannot_be_hard_deleted(): void
    {
        $customer = $this->createCustomer('Order Customer');

        SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-DEL-01',
            'order_date' => now(),
            'total_amount' => 25000.00,
            'status' => 'Confirmed',
        ]);

        $this->expectException(PartyDeletionException::class);
        $customer->delete();
    }

    public function test_customer_with_payments_cannot_be_hard_deleted(): void
    {
        $customer = $this->createCustomer('Payment Customer');

        CustomerPayment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'payment_number' => 'PAY-DEL-01',
            'payment_date' => now(),
            'amount' => 5000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
        ]);

        $this->expectException(PartyDeletionException::class);
        $customer->delete();
    }

    public function test_customer_with_quotations_cannot_be_hard_deleted(): void
    {
        $customer = $this->createCustomer('Quote Customer');
        $account = CrmAccount::where('customer_id', $customer->id)->first();
        if (! $account) {
            $account = CrmAccount::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Quote Account',
                'customer_id' => $customer->id,
            ]);
        }

        Quotation::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $account->id,
            'quotation_number' => 'QT-DEL-01',
            'quotation_date' => now(),
            'total_amount' => 12000.00,
            'status' => 'Sent',
        ]);

        $this->expectException(PartyDeletionException::class);
        $customer->delete();
    }

    public function test_customer_with_opening_balance_cannot_be_hard_deleted(): void
    {
        $customer = $this->createCustomer('Balance Customer', 7500.00);

        $guard = app(PartyDeletionGuard::class);
        $this->assertTrue($guard->hasTransactions($customer));

        $this->expectException(PartyDeletionException::class);
        $customer->delete();
    }

    public function test_clean_customer_with_zero_transactions_can_be_deleted(): void
    {
        $customer = $this->createCustomer('Clean Customer');
        $id = $customer->id;

        $guard = app(PartyDeletionGuard::class);
        $this->assertFalse($guard->hasTransactions($customer));

        $customer->delete();

        $this->assertDatabaseMissing('customers', ['id' => $id]);
    }

    public function test_vendor_with_purchase_orders_cannot_be_hard_deleted(): void
    {
        $vendor = $this->createVendor('PO Vendor');

        PurchaseOrder::unguarded(fn () => PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-DEL-01',
            'date' => now(),
            'grand_total' => 3000.00,
            'status' => 'Approved',
        ]));

        $guard = app(PartyDeletionGuard::class);
        $this->assertTrue($guard->hasTransactions($vendor));
        $this->assertArrayHasKey('purchase_orders', $guard->transactionSummary($vendor));

        $this->expectException(PartyDeletionException::class);
        $vendor->delete();
    }

    public function test_vendor_with_bills_cannot_be_hard_deleted(): void
    {
        $vendor = $this->createVendor('Bill Vendor');

        VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'bill_number' => 'BILL-DEL-01',
            'bill_date' => now(),
            'grand_total' => 4500.00,
            'status' => 'posted',
        ]);

        $this->expectException(PartyDeletionException::class);
        $vendor->delete();
    }

    public function test_vendor_with_payments_cannot_be_hard_deleted(): void
    {
        $vendor = $this->createVendor('Payment Vendor');

        VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'VPAY-DEL-01',
            'payment_date' => now(),
            'amount' => 2000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
        ]);

        $this->expectException(PartyDeletionException::class);
        $vendor->delete();
    }

    public function test_vendor_with_rfqs_cannot_be_hard_deleted(): void
    {
        $vendor = $this->createVendor('RFQ Vendor');

        $rfq = PurchaseRfq::create([
            'tenant_id' => $this->tenant->id,
            'rfq_number' => 'RFQ-DEL-01',
            'title' => 'Supply RFP',
            'rfq_date' => now(),
            'status' => 'Sent',
        ]);

        PurchaseRfqVendor::create([
            'tenant_id' => $this->tenant->id,
            'purchase_rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => 'sent',
        ]);

        $this->expectException(PartyDeletionException::class);
        $vendor->delete();
    }

    public function test_clean_vendor_with_zero_transactions_can_be_deleted(): void
    {
        $vendor = $this->createVendor('Clean Vendor');
        $id = $vendor->id;

        $guard = app(PartyDeletionGuard::class);
        $this->assertFalse($guard->hasTransactions($vendor));

        $vendor->delete();

        $this->assertDatabaseMissing('vendors', ['id' => $id]);
    }

    public function test_customer_api_destroy_deactivates_transacted_party_instead_of_deleting(): void
    {
        $customer = $this->createCustomer('API Transacted Customer');

        SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-API-01',
            'order_date' => now(),
            'total_amount' => 10000.00,
            'status' => 'Confirmed',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'del-tenant')
            ->deleteJson("/api/sales/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Customer has transaction records; status changed to inactive.',
        ]);

        // Customer MUST still exist in database, marked inactive
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'inactive',
        ]);
    }

    public function test_vendor_api_destroy_deactivates_transacted_party_instead_of_deleting(): void
    {
        $vendor = $this->createVendor('API Transacted Vendor');

        VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'bill_number' => 'BILL-API-01',
            'bill_date' => now(),
            'grand_total' => 8000.00,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'del-tenant')
            ->deleteJson("/api/purchase/vendors/{$vendor->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Vendor has order records; status set to inactive.',
        ]);

        // Vendor MUST still exist in database, marked inactive
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'status' => 'inactive',
        ]);
    }

    public function test_customer_policy_blocks_delete_when_transactions_exist(): void
    {
        $customer = $this->createCustomer('Policy Customer');

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-POL-01',
            'invoice_date' => now(),
            'total_amount' => 500.00,
            'status' => 'unpaid',
        ]);

        $this->assertFalse($this->owner->can('delete', $customer));
    }
}
