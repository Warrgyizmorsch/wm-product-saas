<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Purchase\Services\PurchaseAdvancePaymentService;
use App\Domains\Purchase\Services\VendorBillService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorBillAdvanceAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Vendor $vendor;
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

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Supplier X',
            'status' => 'active',
        ]);

        $fy = FiscalYear::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'FY 2026',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'open',
        ]);

        AccountingPeriod::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => now()->month,
            'name' => 'October 2026',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);

        // Seed basic COA accounts needed for journals
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => '1010',
            'name' => 'Bank Account',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => '1400',
            'name' => 'Advance to Vendor',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => '2010',
            'name' => 'Accounts Payable',
            'type' => 'liability',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function advance_cannot_be_applied_twice_and_tracks_allocations_accurately(): void
    {
        $this->actingAs($this->user);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'purchase_order_number' => 'PO-TEST-001',
            'date' => now(),
            'status' => 'Approved',
            'subtotal' => 100.0,
            'grand_total' => 100.0,
        ]);

        // Record PO advance payment of 100
        $advanceService = app(PurchaseAdvancePaymentService::class);
        $advance = $advanceService->recordAdvancePayment([
            'payment_date' => now()->toDateString(),
            'vendor_id' => $this->vendor->id,
            'purchase_order_id' => $po->id,
            'amount' => 100.0,
            'payment_method' => 'Bank Transfer',
        ], $this->tenant->id);

        $this->assertEquals(100.0, (float) $advance->amount);

        // Check available advance for vendor / PO
        $billService = app(VendorBillService::class);
        $avail = $billService->getAvailableVendorAdvance($this->vendor->id, $this->tenant->id, $po->id);
        $this->assertEquals(100.0, $avail);

        // Create a bill for 150 (greater than advance)
        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'purchase_order_id' => $po->id,
            'bill_number' => 'BILL-TEST-001',
            'bill_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 150.0,
            'grand_total' => 150.0,
            'paid_amount' => 0.0,
            'due_amount' => 150.0,
            'status' => 'Posted',
        ]);

        // First application: should succeed and apply 100
        $result1 = $billService->applyAdvanceCredit($bill, $this->tenant->id);
        $this->assertTrue($result1);

        $bill->refresh();
        $this->assertEquals(100.0, (float) $bill->paid_amount);
        $this->assertEquals(50.0, (float) $bill->due_amount);
        $this->assertEquals('Partially Paid', $bill->status);
        $this->assertCount(1, $bill->allocations);
        $this->assertEquals(100.0, (float) $bill->allocations->first()->allocated_amount);

        // Now available advance must be 0
        $availAfter = $billService->getAvailableVendorAdvance($this->vendor->id, $this->tenant->id, $po->id);
        $this->assertEquals(0.0, $availAfter);

        // Second application: MUST FAIL (cannot apply advance twice)
        $result2 = $billService->applyAdvanceCredit($bill, $this->tenant->id);
        $this->assertFalse($result2);

        // Bill state must not change
        $bill->refresh();
        $this->assertEquals(100.0, (float) $bill->paid_amount);
        $this->assertEquals(50.0, (float) $bill->due_amount);
        $this->assertEquals('Partially Paid', $bill->status);
        $this->assertCount(1, $bill->allocations);

        // Test registering vendor payment for remaining due:
        // Both net bank outflow and allocated amount must be prefilled with due_amount (50.00)
        $role = \App\Models\Access\Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->first();
        if ($role) {
            \App\Models\Access\UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
            $this->user->forceFill(['role_id' => $role->id])->save();
        }
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('purchase.payments.create', ['bill_id' => $bill->id]));

        $response->assertStatus(200);
        // Ensure both fields contain 50.00 and NOT 0.00
        $response->assertSee('value="50.00"', false);
        $response->assertDontSee('value="0.00"', false);
    }
}

