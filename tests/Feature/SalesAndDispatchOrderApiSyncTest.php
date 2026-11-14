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
use App\Domains\Sales\Services\DispatchOrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAndDispatchOrderApiSyncTest extends TestCase
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
            'name' => 'API Sync Tenant',
            'slug' => 'api-sync-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Sync User',
            'email' => 'api-sync@example.com',
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
            'name' => 'Enterprise Buyer API',
            'email' => 'buyer-api@enterprise.com',
            'phone' => '9876543211',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Storage Server',
            'sku' => 'STR-001',
            'type' => 'finished_good',
            'status' => 'active',
            'unit_cost' => 300.00,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'West WH',
            'code' => 'WEST-WH',
            'status' => 'active',
        ]);

        StockService::recordInflow(
            $this->tenant->id,
            $this->product->id,
            $this->warehouse->id,
            50.0,
            300.0,
            'Initial Stock'
        );
    }

    /** @test */
    public function sales_order_api_confirm_short_close_and_dispatch_cancel_work_correctly()
    {
        // 1. Create Draft SO
        $order = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => 'SO-API-01',
            'order_date' => now(),
            'status' => 'Draft',
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);

        $soItem = $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'item_name' => 'API Storage Server',
            'quantity' => 10,
            'unit_price' => 500.00,
            'amount' => 5000.00,
            'status' => 'Pending',
        ]);

        // 2. Test API Confirm: POST /api/sales/orders/{id}/confirm
        $confirmResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'api-sync-tenant')
            ->postJson("/api/sales/orders/{$order->id}/confirm");

        $confirmResponse->assertStatus(200);
        $this->assertTrue($confirmResponse->json('success'));

        $order->refresh();
        $this->assertEquals('Confirmed', $order->status);
        $this->assertCount(1, $order->materialRequirements);

        // 3. Create Dispatch Order for 4 units
        $dispatchService = app(DispatchOrderService::class);
        $dispatch = $dispatchService->createDispatchOrder([
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
        ], $this->user->id);

        $soItem->refresh();
        $this->assertEquals(4, (float) $soItem->allocated_qty);

        // 4. Test API Dispatch Cancel: POST /api/sales/dispatches/{id}/cancel
        $cancelDoResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'api-sync-tenant')
            ->postJson("/api/sales/dispatches/{$dispatch->id}/cancel", [
                'reason' => 'Truck breakdown, rescheduling',
            ]);

        $cancelDoResponse->assertStatus(200);
        $this->assertTrue($cancelDoResponse->json('success'));

        $dispatch->refresh();
        $soItem->refresh();
        $order->refresh();

        $this->assertEquals('Cancelled', $dispatch->status);
        $this->assertEquals(0, (float) $soItem->allocated_qty);
        $this->assertEquals('Confirmed', $order->status);

        // 5. Create new DO for 3 units and ship it
        $dispatch2 = $dispatchService->createDispatchOrder([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $order->id,
            'dispatch_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 3,
                    'sales_order_item_id' => $soItem->id,
                ]
            ]
        ], $this->user->id);

        $dispatchService->shipDispatchOrder($dispatch2);
        $order->refresh();
        $soItem->refresh();

        $this->assertEquals('Partially Shipped', $order->status);
        $this->assertEquals(3, (float) $soItem->dispatched_qty);
        $this->assertEquals(7, (float) $soItem->remaining_qty);

        // 6. Test API Short-Close: POST /api/sales/orders/{id}/short-close
        $shortCloseResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'api-sync-tenant')
            ->postJson("/api/sales/orders/{$order->id}/short-close", [
                'reason' => 'Customer cancelled remaining 7 units due to project scope change',
            ]);

        $shortCloseResponse->assertStatus(200);
        $this->assertTrue($shortCloseResponse->json('success'));

        $order->refresh();
        $soItem->refresh();

        $this->assertEquals('Short-Closed', $order->status);
        $this->assertEquals(7, (float) $soItem->cancelled_qty);
        $this->assertEquals(3, (float) $soItem->dispatched_qty);
        $this->assertEquals(0, (float) $soItem->remaining_qty);
        $this->assertEquals('Short-Closed', $soItem->status);
    }
}
