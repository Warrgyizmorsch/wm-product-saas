<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderGstTypeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Purchase Test Tenant',
            'slug' => 'purchase-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(TenantContext::class)->set($this->tenant);

        $this->seed(RbacSeeder::class);

        // Active company with Rajasthan GST (prefix 08)
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
            'name' => 'Purchase Admin',
            'email' => 'purchase_admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $purchaseManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'purchase_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $purchaseManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->branch = \App\Domains\HRMS\Models\Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Main Branch',
            'code' => 'BR01',
        ]);
        $this->user->update(['branch_id' => $this->branch->id]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main WH',
            'code' => 'MWH',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Material',
            'sku' => 'MAT-001',
            'cost_price' => 200,
            'gst_rate' => 18,
            'status' => 'active',
        ]);
    }

    public function test_gst_type_resolves_to_cgst_sgst_when_company_and_vendor_first_2_digits_match(): void
    {
        $this->actingAs($this->user);

        // Intra-state vendor (Rajasthan prefix 08)
        $vendorIntra = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rajasthan Local Vendor',
            'code' => 'VEND-RAJ',
            'gstin' => '08BBBBB1111B1Z2',
            'billing_state' => 'Rajasthan',
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $resolved = $service->resolveGstType($vendorIntra, $this->company->id, $this->tenant->id);

        $this->assertEquals('cgst_sgst', $resolved);
    }

    public function test_gst_type_resolves_to_igst_when_company_and_vendor_first_2_digits_differ(): void
    {
        $this->actingAs($this->user);

        // Inter-state vendor (Gujarat prefix 24)
        $vendorInter = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gujarat Outside Vendor',
            'code' => 'VEND-GUJ',
            'gstin' => '24AABCS1429B1Z8',
            'billing_state' => 'Rajasthan', // Notice: GST prefix 24 takes precedence over billing_state string
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $resolved = $service->resolveGstType($vendorInter, $this->company->id, $this->tenant->id);

        $this->assertEquals('igst', $resolved);
    }

    public function test_purchase_order_service_store_auto_resolves_gst_type(): void
    {
        $this->actingAs($this->user);

        $vendorInter = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inter Vendor',
            'code' => 'VEND-INTER',
            'gstin' => '27AABCS1429B1Z5', // Maharashtra (27 != 08)
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $order = $service->storeOrder([
            'vendor_id' => $vendorInter->id,
            'location' => $this->warehouse->name,
            'date' => now()->toDateString(),
            'tax_type' => 'order_wise_tax',
            'tax_amount' => 180,
            // Notice: 'gst_type' omitted on purpose to test auto-resolution
            'discount_type' => 'without_discount',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'rate' => 200,
                    'tax_percent' => 18,
                ]
            ],
        ], $this->tenant->id);

        $this->assertEquals('igst', $order->gst_type);
        $this->assertEquals(180, (float) $order->igst_amount);
        $this->assertEquals(0, (float) $order->cgst_amount);
        $this->assertEquals(0, (float) $order->sgst_amount);
    }

    public function test_purchase_order_without_tax_zeroes_out_all_taxes(): void
    {
        $this->actingAs($this->user);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zero Tax Vendor',
            'code' => 'VEND-Z',
            'gstin' => '08BBBBB1111B1Z2',
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $order = $service->storeOrder([
            'vendor_id' => $vendor->id,
            'location' => $this->warehouse->name,
            'date' => now()->toDateString(),
            'tax_type' => 'without_tax',
            'tax_amount' => 50, // Should be ignored and zeroed out
            'discount_type' => 'without_discount',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'rate' => 200,
                    'tax_percent' => 18,
                ]
            ],
        ], $this->tenant->id);

        $this->assertEquals(0, (float) $order->tax_amount);
        $this->assertEquals(0, (float) $order->cgst_amount);
        $this->assertEquals(0, (float) $order->sgst_amount);
        $this->assertEquals(0, (float) $order->igst_amount);
        $this->assertEquals(400, (float) $order->grand_total);
    }

    public function test_order_wise_tax_does_not_apply_item_level_tax(): void
    {
        $this->actingAs($this->user);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Order Wise Vendor',
            'code' => 'VEND-OW',
            'gstin' => '08BBBBB1111B1Z2',
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $order = $service->storeOrder([
            'vendor_id' => $vendor->id,
            'location' => $this->warehouse->name,
            'date' => now()->toDateString(),
            'tax_type' => 'order_wise_tax',
            'tax_amount' => 0,
            'discount_type' => 'without_discount',
            'items' => [
                [
                    'product_id' => $this->product->id, // Has gst_rate = 18
                    'quantity' => 1,
                    'rate' => 200,
                    'tax_percent' => 18, // Sent from product select, but should be ignored in order_wise_tax
                ]
            ],
        ], $this->tenant->id);

        $this->assertEquals(0, (float) $order->items[0]->tax_percent);
        $this->assertEquals(0, (float) $order->items[0]->tax_amount);
        $this->assertEquals(200, (float) $order->items[0]->total_amount);
        $this->assertEquals(200, (float) $order->grand_total);
    }

    public function test_purchase_order_create_view_contains_gst_attributes(): void
    {
        $this->actingAs($this->user);

        Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'WarrgyizMorsch Vendor',
            'code' => 'VEND-WARR',
            'gstin' => '12DFDDH2121DJZD', // Prefix 12 (different from company prefix 08)
            'status' => 'active',
        ]);

        $responseCreate = $this->withHeaders([
            'X-Tenant' => 'purchase-test',
            'X-Company-Id' => (string) $this->company->id,
            'X-Branch-Id' => (string) $this->branch->id,
        ])->get(route('purchase.orders.create'));

        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('data-company-gst-prefix="08"', false);
        $responseCreate->assertSee('data-gst-prefix="12"', false);
        $responseCreate->assertSee('data-gst-type="igst"', false);
    }

    public function test_purchase_order_edit_view_contains_gst_attributes(): void
    {
        $this->actingAs($this->user);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'WarrgyizMorsch Vendor Edit',
            'code' => 'VEND-WARRE',
            'gstin' => '12DFDDH2121DJZD',
            'status' => 'active',
        ]);

        $service = app(PurchaseOrderService::class);
        $order = $service->storeOrder([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'vendor_id' => $vendor->id,
            'location' => $this->warehouse->name,
            'date' => now()->toDateString(),
            'tax_type' => 'order_wise_tax',
            'discount_type' => 'without_discount',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'rate' => 200,
                ]
            ],
        ], $this->tenant->id);

        $responseEdit = $this->withHeaders([
            'X-Tenant' => 'purchase-test',
            'X-Company-Id' => (string) $this->company->id,
            'X-Branch-Id' => (string) $this->branch->id,
        ])->get(route('purchase.orders.edit', $order->id));

        $responseEdit->assertStatus(200);
        $responseEdit->assertSee('data-company-gst-prefix="08"', false);
        $responseEdit->assertSee('data-gst-prefix="12"', false);
        $responseEdit->assertSee('data-gst-type="igst"', false);
    }

    public function test_purchase_order_show_and_pdf_display_hsn_under_item_and_show_discount(): void
    {
        $this->actingAs($this->user);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Acme Supplies Ltd',
            'code' => 'VEND-ACME',
            'gstin' => '08AAAAA0000A1Z5',
            'status' => 'active',
        ]);

        $this->product->update([
            'hsn_sac' => '73066100',
            'sku' => 'RM-TBL-PIPE',
        ]);

        $service = app(PurchaseOrderService::class);
        $order = $service->storeOrder([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'vendor_id' => $vendor->id,
            'location' => $this->warehouse->name,
            'date' => now()->toDateString(),
            'tax_type' => 'order_wise_tax',
            'order_tax_rate' => 12,
            'discount_type' => 'item_wise',
            'discount_amount' => 60,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'rate' => 300,
                    'discount_percent' => 10,
                    'discount_amount' => 60,
                    'tax_percent' => 0,
                ]
            ],
        ], $this->tenant->id);

        $responseShow = $this->withHeaders([
            'X-Tenant' => 'purchase-test',
            'X-Company-Id' => (string) $this->company->id,
            'X-Branch-Id' => (string) $this->branch->id,
        ])->get(route('purchase.orders.show', $order->id));

        $responseShow->assertStatus(200);
        // HSN badge under item name
        $responseShow->assertSee('HSN: 73066100', false);
        // Separate HSN column removed
        $responseShow->assertDontSee('>HSN/SAC<', false);
        // Discount header and discount value visible
        $responseShow->assertSee('>Discount<', false);
        $responseShow->assertSee('(10%)', false);

        // Verify PDF view contains HSN and Discount
        $pdfHtml = view('modules.purchase.orders.pdf', ['order' => $order->fresh(['items.product', 'vendor', 'warehouse'])])->render();
        $this->assertStringContainsString('HSN: 73066100', $pdfHtml);
        $this->assertStringContainsString('DISCOUNT', $pdfHtml);
        $this->assertStringContainsString('10%', $pdfHtml);

        // Product without HSN should not display empty HSN
        $this->product->update(['hsn_sac' => null]);
        $pdfHtmlNoHsn = view('modules.purchase.orders.pdf', ['order' => $order->fresh(['items.product', 'vendor', 'warehouse'])])->render();
        $this->assertStringNotContainsString('HSN:', $pdfHtmlNoHsn);
    }
}

