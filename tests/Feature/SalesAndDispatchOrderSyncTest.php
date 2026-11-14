<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Services\DispatchOrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAndDispatchOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Sync Test Tenant',
            'slug' => 'sync-test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sync Manager',
            'email' => 'sync@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->first()
            ?? Role::query()->whereNull('tenant_id')->where('slug', 'sales_manager')->first();
        if ($role) {
            UserRole::create([
                'user_id' => $this->user->id,
                'role_id' => $role->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Buyer',
            'email' => 'buyer@enterprise.com',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Server Unit',
            'sku' => 'SRV-001',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 500.00,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Hub',
            'code' => 'CENTRAL-WH',
            'status' => 'active',
        ]);

        // Inflow 100 units into warehouse
        StockService::recordInflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            100.0,
            500.0,
            'Initial Stock'
        );
    }

    /** @test */
    public function sales_order_and_dispatch_order_line_items_sync_and_auto_compute_lifecycle()
    {
        // 1. Create Sales Order for 10 units
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-SYNC-01',
            'order_date' => now(),
            'status' => 'Confirmed',
            'subtotal' => 10000,
            'total_amount' => 10000,
        ]);

        $soItem = $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'item_name' => 'Enterprise Server Unit',
            'quantity' => 10,
            'unit_price' => 1000.00,
            'amount' => 10000.00,
            'status' => 'Pending',
        ]);

        $this->assertEquals(0, (float) $soItem->allocated_qty);
        $this->assertEquals(0, (float) $soItem->dispatched_qty);
        $this->assertEquals(10, (float) $soItem->remaining_qty);

        // 2. Create Dispatch Order for 4 units (Partial Allocation)
        $dispatchService = app(DispatchOrderService::class);
        $doData = [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'dispatch_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 4,
                    'sales_order_item_id' => $soItem->id,
                ]
            ]
        ];

        $dispatch = $dispatchService->createDispatchOrder($doData, $this->user->id);

        $this->assertNotNull($dispatch);
        $this->assertEquals('Pending', $dispatch->status);

        // Check line item linkage
        $doItem = $dispatch->items->first();
        $this->assertEquals($soItem->id, $doItem->sales_order_item_id);

        // Check SO item allocated quantity increased
        $soItem->refresh();
        $this->assertEquals(4, (float) $soItem->allocated_qty);
        $this->assertEquals(0, (float) $soItem->dispatched_qty);
        $this->assertEquals(6, (float) $soItem->open_allocatable_qty);

        // 3. Ship the Dispatch Order
        $dispatchService->shipDispatchOrder($dispatch);
        $dispatch->refresh();
        $this->assertEquals('Shipped', $dispatch->status);

        // Check SO item quantities and status updated
        $soItem->refresh();
        $this->assertEquals(0, (float) $soItem->allocated_qty);
        $this->assertEquals(4, (float) $soItem->dispatched_qty);
        $this->assertEquals(6, (float) $soItem->remaining_qty);
        $this->assertEquals('Partially Dispatched', $soItem->status);

        // Check Sales Order status automatically updated to Partially Shipped
        $order->refresh();
        $this->assertEquals('Partially Shipped', $order->status);

        // 4. Test Short-Close: Customer cancels remaining 6 units
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'sync-test-tenant')
            ->post(route('sales.orders.short-close', $order->id), [
                'reason' => 'Customer requested cancellation of remaining 6 units',
            ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();
        $soItem->refresh();

        $this->assertEquals('Short-Closed', $order->status);
        $this->assertEquals(6, (float) $soItem->cancelled_qty);
        $this->assertEquals(4, (float) $soItem->dispatched_qty);
        $this->assertEquals(0, (float) $soItem->remaining_qty);
        $this->assertEquals('Short-Closed', $soItem->status);
    }

    /** @test */
    public function dispatch_cancellation_releases_allocations_and_recalculates_so_status()
    {
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-CANCEL-01',
            'order_date' => now(),
            'status' => 'Confirmed',
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);

        $soItem = $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'item_name' => 'Enterprise Server Unit',
            'quantity' => 5,
            'unit_price' => 1000.00,
            'amount' => 5000.00,
            'status' => 'Pending',
        ]);

        $dispatchService = app(DispatchOrderService::class);
        $doData = [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'dispatch_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 5,
                ]
            ]
        ];

        $dispatch = $dispatchService->createDispatchOrder($doData, $this->user->id);
        $soItem->refresh();
        $this->assertEquals(5, (float) $soItem->allocated_qty);

        // Cancel Dispatch Order
        $dispatchService->cancelDispatchOrder($dispatch, 'Vehicle broke down, rescheduling');
        $dispatch->refresh();
        $soItem->refresh();
        $order->refresh();

        $this->assertEquals('Cancelled', $dispatch->status);
        $this->assertEquals(0, (float) $soItem->allocated_qty);
        $this->assertEquals(5, (float) $soItem->remaining_qty);
        $this->assertEquals('Confirmed', $order->status);
    }

    /** @test */
    public function sales_order_transitions_to_completed_when_fully_dispatched_and_fully_invoiced()
    {
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-COMP-01',
            'order_date' => now(),
            'status' => 'Confirmed',
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);

        $soItem = $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'item_name' => 'Enterprise Server Unit',
            'quantity' => 5,
            'unit_price' => 1000.00,
            'amount' => 5000.00,
            'status' => 'Pending',
        ]);

        $dispatchService = app(DispatchOrderService::class);
        $doData = [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'dispatch_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 5,
                    'sales_order_item_id' => $soItem->id,
                ]
            ]
        ];

        // 1. Dispatch 100% of physical goods
        $dispatch = $dispatchService->createDispatchOrder($doData, $this->user->id);
        $dispatchService->shipDispatchOrder($dispatch);

        $order->refresh();
        $this->assertEquals('Shipped', $order->status); // Shipped, but not yet invoiced!

        // 2. Invoice the order via InvoiceController or direct DB invoice + refresh
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'sync-test-tenant')
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $order->id,
                'customer_id' => $this->customer->id,
                'invoice_number' => 'INV-TEST-001',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'total_amount' => 5000,
                'items' => [
                    [
                        'sales_order_item_id' => $soItem->id,
                        'product_id' => $this->product->id,
                        'warehouse_id' => $this->warehouse->id,
                        'item_name' => 'Enterprise Server Unit',
                        'quantity' => 5,
                        'unit_price' => 1000.00,
                        'discount' => 0,
                        'tax_rate' => 0,
                    ]
                ]
            ]);

        $response->assertSessionHasNoErrors();

        // 3. SO must now automatically be Completed
        $order->refresh();
        $soItem->refresh();

        $this->assertEquals('Completed', $order->status);
        $this->assertEquals('Fulfilled', $soItem->status);
        $this->assertTrue($order->isFullyInvoiced());
    }
}
