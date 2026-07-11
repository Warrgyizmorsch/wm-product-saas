<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Models\SalesOrder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        // Create user with full sales access (this suite exercises the whole
        // order lifecycle — index/create/confirm/cancel/ship — not scoped
        // ownership, so sales_manager is the right fit).
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $salesManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $salesManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create customer
        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe Corp',
            'email' => 'john@doe.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        // Create product
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Widget',
            'sku' => 'WIDG-001',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);
    }

    /** @test */
    public function sales_order_index_is_accessible()
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.index'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.sales.orders.index');
    }

    /** @test */
    public function sales_order_create_is_accessible()
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.create'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.sales.orders.create');
    }

    /** @test */
    public function sales_order_can_be_stored()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'sales_person_id' => $this->user->id,
            'sales_order_number' => 'SO-0001',
            'order_date' => now()->format('Y-m-d'),
            'shipment_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_terms' => 'Net 30',
            'billing_address' => '123 Main St, Anytown',
            'shipping_address' => '456 Delivery Rd, City',
            'discount' => 10.00,
            'shipping_charges' => 50.00,
            'adjustment' => 5.00,
            'terms_conditions' => 'Standard terms apply',
            'notes' => 'Please package carefully',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'item_name' => 'Standard Widget',
                    'description' => 'A great widget',
                    'quantity' => 5,
                    'unit_price' => 120.00,
                    'tax_rate' => 18.00,
                    'discount' => 5.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $orderData);

        // Assert redirect to show page
        $order = SalesOrder::where('sales_order_number', '0001')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('sales.orders.show', $order->id));

        // Check values
        $this->assertEquals(600.00, $order->subtotal); // 5 * 120
        $this->assertEquals(107.10, $order->tax); // (600 - 5) * 18% = 595 * 0.18 = 107.1
        $this->assertEquals(752.10, $order->total_amount); // 600 + 107.10 - 10 + 50 + 5 = 752.1
        $this->assertEquals('Draft', $order->status);

        // Check items table
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(5, $item->quantity);
        $this->assertEquals(120.00, $item->unit_price);
        $this->assertEquals(5.00, $item->discount);
        $this->assertEquals(595.00, $item->amount); // (5 * 120) - 5
    }

    /** @test */
    public function sales_order_can_be_created_from_quotation_context()
    {
        // 1. Create a Quotation
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quotation_number' => 'QT-1111',
            'quotation_date' => now(),
            'status' => 'Accepted',
            'subtotal' => 200.00,
            'tax' => 36.00,
            'total_amount' => 236.00,
            'terms_conditions' => 'Quotation terms text',
            'notes' => 'Quotation notes text',
        ]);

        $quotation->items()->create([
            'product_id' => $this->product->id,
            'item_name' => 'Standard Widget',
            'quantity' => 2,
            'unit_price' => 100.00,
            'tax_rate' => 18.00,
            'amount' => 200.00,
        ]);

        // 2. Load Sales Order Create page with quotation_id parameter
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.create', ['quotation_id' => $quotation->id]));

        $response->assertStatus(200);
        $response->assertViewHas('prefillQuotation');
    }

    /** @test */
    public function sales_order_status_transitions_operate_correctly()
    {
        // Create warehouse
        $warehouse = \App\Domains\Inventory\Models\Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main WH',
            'code' => 'MAIN-WH',
            'status' => 'active'
        ]);

        // Record stock inflow to ensure we have available stock to confirm order
        \App\Domains\Inventory\Services\StockService::recordInflow(
            $this->tenant->id,
            $this->product->id,
            $warehouse->id,
            10.0,
            100.0,
            'Opening Stock'
        );

        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-0002',
            'order_date' => now(),
            'status' => 'Draft',
            'subtotal' => 100,
            'total_amount' => 118,
        ]);

        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'item_name' => 'Standard Widget',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00
        ]);

        // 1. Confirm
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.confirm', $order->id));
        
        $order->refresh();
        $this->assertEquals('Confirmed', $order->status);

        // 2. Ship via Delivery Order
        $do = \App\Domains\Sales\Models\DeliveryOrder::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $order->id,
            'delivery_number' => 'DO-001',
            'delivery_date' => now(),
            'status' => 'Draft',
        ]);
        $doItem = $do->items()->create([
            'sales_order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.deliveries.ship', $do->id));

        $order->refresh();
        $this->assertEquals('Shipped', $order->status);

        // 3. Try to cancel Shipped order (should fail validation)
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.cancel', $order->id));

        $order->refresh();
        $this->assertEquals('Shipped', $order->status); // unchanged
    }

    /** @test */
    public function sales_order_isolation_by_tenant()
    {
        // Create second tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        $otherCustomer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Customer',
            'email' => 'other-customer@example.com',
            'status' => 'active',
        ]);

        // Create sales order in other tenant
        $otherOrder = SalesOrder::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'sales_order_number' => 'SO-9999',
            'order_date' => now(),
            'status' => 'Draft',
        ]);

        // Try to access other tenant's order as first user
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.show', $otherOrder->id));

        $response->assertStatus(404); // Scoped out
    }

    /** @test */
    public function test_supplier_method_routing_rules()
    {
        // Assign production_manager role to bypass 403 on production order creation
        $prodRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $prodRole->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->update(['role' => 'admin']);

        $warehouse = \App\Domains\Inventory\Models\Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main WH',
            'code' => 'MAIN-WH',
            'status' => 'active'
        ]);

        // Create Buy Product
        $buyProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Buy Widget',
            'sku' => 'BUY-001',
            'type' => 'finished_good',
            'status' => 'active',
            'supplier_method' => 'buy',
            'unit_cost' => 100.00,
        ]);

        // Create Manufacture Product
        $mfgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mfg Widget',
            'sku' => 'MFG-001',
            'type' => 'finished_good',
            'status' => 'active',
            'supplier_method' => 'manufacture',
            'unit_cost' => 150.00,
        ]);

        // Create Sales Order
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-SUPPLIER-TEST',
            'order_date' => now(),
            'status' => 'Draft',
            'subtotal' => 250,
            'total_amount' => 295,
        ]);

        $itemBuy = $order->items()->create([
            'product_id' => $buyProduct->id,
            'warehouse_id' => $warehouse->id,
            'item_name' => 'Buy Widget',
            'quantity' => 2,
            'unit_price' => 100.00,
            'amount' => 200.00
        ]);

        $itemMfg = $order->items()->create([
            'product_id' => $mfgProduct->id,
            'warehouse_id' => $warehouse->id,
            'item_name' => 'Mfg Widget',
            'quantity' => 1,
            'unit_price' => 150.00,
            'amount' => 150.00
        ]);

        // Confirm Order
        $order->update(['status' => 'Confirmed']);

        // 1. Create Delivery Order — verify only Buy item is present
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.deliveries.create', ['sales_order_id' => $order->id]));

        $response->assertStatus(200);
        $response->assertSee('Buy Widget');
        $response->assertDontSee('Mfg Widget');

        // 2. Create Production Order — verify only Manufacture item is present
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.create', ['sales_order_id' => $order->id]));

        $response->assertStatus(200);
        $response->assertSee('Mfg Widget');
        $response->assertDontSee('Buy Widget');
    }
}
