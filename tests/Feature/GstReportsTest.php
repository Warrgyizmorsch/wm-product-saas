<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesReturn;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These reports are self-contained: they read Sales/Purchase documents
 * directly (matching AR/AP Aging's convention) rather than the Journal
 * ledger, and derive Sales/Purchase Return tax splits from
 * total_refund_amount - total_amount since neither Return model stores a
 * CGST/SGST/IGST breakdown.
 */
class GstReportsTest extends TestCase
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
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    private function todayInMonth(): string
    {
        return now()->startOfMonth()->addDays(5)->toDateString();
    }

    /** @test */
    public function gst_summary_nets_output_and_input_against_returns(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'B2B Buyer', 'gstin' => '27ABCDE1234F1Z5']);
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Supplies', 'status' => 'active']);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0001',
            'invoice_date' => $this->todayInMonth(),
            'status' => 'Sent',
            'subtotal' => 10000,
            'cgst_amount' => 900,
            'sgst_amount' => 900,
            'igst_amount' => 0,
            'tax_amount' => 1800,
            'total_amount' => 11800,
            'balance_due' => 11800,
        ]);

        SalesReturn::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'return_number' => 'RET-0001',
            'return_date' => $this->todayInMonth(),
            'status' => 'Completed',
            'total_amount' => 1000,
            'total_refund_amount' => 1180,
        ]);

        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-0001',
            'vendor_id' => $vendor->id,
            'bill_date' => $this->todayInMonth(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'Unpaid',
            'subtotal' => 5000,
            'cgst_amount' => 450,
            'sgst_amount' => 450,
            'igst_amount' => 0,
            'tax_amount' => 900,
            'grand_total' => 5900,
            'due_amount' => 5900,
        ]);

        PurchaseReturn::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'vendor_bill_id' => $bill->id,
            'return_number' => 'PRET-0001',
            'return_date' => $this->todayInMonth(),
            'status' => 'Completed',
            'total_amount' => 500,
            'total_refund_amount' => 590,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.gst-summary', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();

        // Output: CGST 900-90=810, SGST 900-90=810 (return tax = 1180-1000=180, split 90/90)
        $response->assertSee('810.00');
        // Input: CGST 450-45=405, SGST 450-45=405 (return tax = 590-500=90, split 45/45)
        $response->assertSee('405.00');
        // Payable per head = 810 - 405 = 405, total = 810.00
        $response->assertSeeInOrder(['GST Payable', 'Total Payable', '810.00']);
    }

    /** @test */
    public function gstr1_splits_invoices_and_credit_notes_by_b2b_versus_b2c(): void
    {
        $b2bCustomer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'B2B Buyer', 'gstin' => '27ABCDE1234F1Z5']);
        $b2cCustomer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Walk-in Buyer', 'gstin' => null]);

        $b2bInvoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $b2bCustomer->id,
            'invoice_number' => 'INV-B2B-1',
            'invoice_date' => $this->todayInMonth(),
            'status' => 'Sent',
            'subtotal' => 10000,
            'cgst_amount' => 900,
            'sgst_amount' => 900,
            'igst_amount' => 0,
            'tax_amount' => 1800,
            'total_amount' => 11800,
            'balance_due' => 11800,
        ]);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $b2cCustomer->id,
            'invoice_number' => 'INV-B2C-1',
            'invoice_date' => $this->todayInMonth(),
            'status' => 'Sent',
            'subtotal' => 2000,
            'cgst_amount' => 180,
            'sgst_amount' => 180,
            'igst_amount' => 0,
            'tax_amount' => 360,
            'total_amount' => 2360,
            'balance_due' => 2360,
        ]);

        SalesReturn::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $b2bCustomer->id,
            'invoice_id' => $b2bInvoice->id,
            'return_number' => 'RET-B2B-1',
            'return_date' => $this->todayInMonth(),
            'status' => 'Completed',
            'total_amount' => 1000,
            'total_refund_amount' => 1180,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.gstr1', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertSee('INV-B2B-1');
        $response->assertSee('B2B Buyer');
        $response->assertSee('27ABCDE1234F1Z5');
        $response->assertDontSee('INV-B2C-1'); // B2C section is summarized, not itemized
        $response->assertSee('RET-B2B-1');

        // B2B totals: 1 invoice, taxable 10,000
        // B2C totals: 1 invoice, taxable 2,000
        $response->assertSee('10,000.00');
        $response->assertSee('2,000.00');
    }

    /** @test */
    public function gstr3b_computes_net_payable_from_outward_and_itc(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Buyer', 'gstin' => '27ABCDE1234F1Z5']);
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Vendor', 'status' => 'active']);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0001',
            'invoice_date' => $this->todayInMonth(),
            'status' => 'Sent',
            'subtotal' => 10000,
            'cgst_amount' => 900,
            'sgst_amount' => 900,
            'igst_amount' => 0,
            'tax_amount' => 1800,
            'total_amount' => 11800,
            'balance_due' => 11800,
        ]);

        VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-0001',
            'vendor_id' => $vendor->id,
            'bill_date' => $this->todayInMonth(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'Unpaid',
            'subtotal' => 4000,
            'cgst_amount' => 360,
            'sgst_amount' => 360,
            'igst_amount' => 0,
            'tax_amount' => 720,
            'grand_total' => 4720,
            'due_amount' => 4720,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.gstr3b', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();
        // Net payable per head = 900 - 360 = 540, total = 1,080.00
        $response->assertSee('540.00');
        $response->assertSee('1,080.00');
    }
}
