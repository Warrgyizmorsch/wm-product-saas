<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Vendor $vendor;
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

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vendor Test LLC',
            'code' => 'VEND001',
            'email' => 'vendor@test.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Widget',
            'sku' => 'WIDG001',
            'type' => 'raw_material',
            'selling_price' => 100,
            'cost_price' => 50,
        ]);
    }

    /** @test */
    public function guests_are_redirected_to_login(): void
    {
        $this->withHeader('X-Tenant', 'purchase-test')
            ->get(route('purchase.orders.index'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_view_orders_index(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'purchase-test')
            ->get(route('purchase.orders.index'))
            ->assertOk()
            ->assertViewIs('modules.purchase.orders.index');
    }

    /** @test */
    public function authenticated_user_can_view_order_create(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'purchase-test')
            ->get(route('purchase.orders.create'))
            ->assertOk()
            ->assertViewIs('modules.purchase.orders.create');
    }

    /** @test */
    public function authenticated_user_can_store_direct_purchase_order(): void
    {
        $data = [
            'vendor_id' => $this->vendor->id,
            'date' => '2026-07-18',
            'delivery_date' => '2026-07-25',
            'location' => 'Head Office',
            'reference' => 'Test Ref',
            'discount_type' => 'order_wise',
            'tax_type' => 'order_wise_tax',
            'gst_type' => 'cgst_sgst',
            'subtotal' => 100.00,
            'discount_amount' => 10.00,
            'cgst_amount' => 8.10,
            'sgst_amount' => 8.10,
            'igst_amount' => 0.00,
            'tax_amount' => 16.20,
            'grand_total' => 106.20,
            'notes' => 'Test Notes',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 2,
                    'rate' => 50.00,
                    'amount' => 100.00,
                    'discount_percent' => 0.00,
                    'discount_amount' => 0.00,
                    'tax_percent' => 18.00,
                    'cgst_percent' => 9.00,
                    'sgst_percent' => 9.00,
                    'igst_percent' => 0.00,
                    'cgst_amount' => 4.50,
                    'sgst_amount' => 4.50,
                    'igst_amount' => 0.00,
                    'tax_amount' => 9.00,
                    'total_amount' => 109.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'purchase-test')
            ->post(route('purchase.orders.store'), $data);

        $this->assertDatabaseHas('purchase_orders', [
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'gst_type' => 'cgst_sgst',
            'grand_total' => 106.20,
            'status' => 'Draft',
        ]);

        $po = PurchaseOrder::where('tenant_id', $this->tenant->id)->first();
        $response->assertRedirect(route('purchase.orders.show', $po->id));
    }

    /** @test */
    public function authenticated_user_can_approve_draft_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-2026-000001',
            'vendor_id' => $this->vendor->id,
            'date' => '2026-07-18',
            'grand_total' => 100.00,
            'status' => 'Draft',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'purchase-test')
            ->post(route('purchase.orders.approve', $po->id));

        $response->assertRedirect(route('purchase.orders.show', $po->id));
        $this->assertEquals('Approved', $po->fresh()->status);
    }

    /** @test */
    public function authenticated_user_can_download_purchase_order_pdf(): void
    {
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_number' => 'PO-2026-000001',
            'vendor_id' => $this->vendor->id,
            'date' => '2026-07-18',
            'grand_total' => 100.00,
            'status' => 'Draft',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'purchase-test')
            ->get(route('purchase.orders.download', $po->id));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=PurchaseOrder_PO-2026-000001.pdf', $response->headers->get('Content-Disposition'));
    }
}
