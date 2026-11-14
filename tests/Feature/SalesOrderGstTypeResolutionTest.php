<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Services\SalesInvoiceCreationService;
use App\Domains\Sales\Services\SalesOrderService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderGstTypeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;
    private Product $product;

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

        // Company with Rajasthan GST number (prefix 08)
        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Test Company Rajasthan',
            'gst_number' => '08AAAAA0000A1Z5',
            'state' => 'Rajasthan',
            'currency' => 'INR',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
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

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'sku' => 'TP-001',
            'selling_price' => 100,
            'cost_price' => 50,
            'status' => 'active',
        ]);
    }

    public function test_gst_type_resolves_to_cgst_sgst_when_company_and_customer_first_2_digits_match(): void
    {
        $this->actingAs($this->user);

        // Same state customer (Rajasthan, prefix 08)
        $customerIntra = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Same State Customer',
            'gstin' => '08BBBBB1111B1Z2',
            'billing_state' => 'Rajasthan',
        ]);

        $service = app(SalesOrderService::class);
        $resolved = $service->resolveGstType($customerIntra, $this->company->id, $this->tenant->id);

        $this->assertEquals('cgst_sgst', $resolved);
    }

    public function test_gst_type_resolves_to_igst_when_company_and_customer_first_2_digits_differ(): void
    {
        $this->actingAs($this->user);

        // Inter-state customer (Sikkim, prefix 11)
        $customerInter = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inter State Customer',
            'gstin' => '11AABCS1429B1Z8',
            'billing_state' => 'Rajasthan', // Notice: Even if address text says Rajasthan, first 2 digits (11) dictate Inter-state
        ]);

        $service = app(SalesOrderService::class);
        $resolved = $service->resolveGstType($customerInter, $this->company->id, $this->tenant->id);

        $this->assertEquals('igst', $resolved);
    }

    public function test_sales_order_service_create_auto_resolves_gst_type(): void
    {
        $this->actingAs($this->user);

        $customerInter = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inter State Customer',
            'gstin' => '24AABCS1429B1Z8', // Gujarat (24 != 08)
        ]);

        $service = app(SalesOrderService::class);

        $order = $service->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $customerInter->id,
            'sales_order_number' => 'SO-2026-0001',
            'order_date' => now()->format('Y-m-d'),
            'tax_type' => 'item_wise_tax',
            // gst_type omitted -> must be auto-resolved to igst
        ], [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'tax_rate' => 18,
            ],
        ]);

        $this->assertEquals('igst', $order->gst_type);
        $this->assertEquals(36.00, (float) $order->tax);
        $this->assertEquals(236.00, (float) $order->total_amount);
    }

    public function test_sales_order_with_without_tax_has_zero_tax(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Any Customer',
            'gstin' => '08BBBBB1111B1Z2',
        ]);

        $service = app(SalesOrderService::class);

        $order = $service->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-2026-0002',
            'order_date' => now()->format('Y-m-d'),
            'tax_type' => 'without_tax',
        ], [
            [
                'product_id' => $this->product->id,
                'quantity' => 5,
                'unit_price' => 100,
                'tax_rate' => 18,
            ],
        ]);

        $this->assertEquals(0, (float) $order->tax);
        $this->assertEquals(500.00, (float) $order->total_amount);
    }

    public function test_sales_invoice_creation_service_resolve_gst_type_matches_first_2_digits(): void
    {
        $this->actingAs($this->user);

        $sameStateCust = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Same State Customer',
            'gstin' => '08AABCS1429B1Z8',
        ]);

        $diffStateCust = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diff State Customer',
            'gstin' => '27AABCS1429B1Z8', // Maharashtra
        ]);

        $service = app(SalesInvoiceCreationService::class);

        $this->assertEquals('cgst_sgst', $service->resolveGstType($sameStateCust, $this->company->id, $this->tenant->id));
        $this->assertEquals('igst', $service->resolveGstType($diffStateCust, $this->company->id, $this->tenant->id));
    }
}

