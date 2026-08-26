<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractDeliveryChallanTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'slug' => 'test-tenant',
            'status' => 'active',
            'name' => 'Test Tenant',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Radiator Header',
            'sku' => 'RAW-RAD-001',
            'unit_of_measure' => 'PCS',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Raw Store',
            'code' => 'RAW-STORE-01',
        ]);
    }

    public function test_can_render_delivery_challan_create_page_prefilled(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Logistics',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => $this->user->id,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Tank Header Crimping',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'status' => 'waiting',
        ]);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.subcontract.delivery-challans.create', [
                'production_order_id' => $order->id,
                'operation_id' => $op->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('New Subcontract Delivery Challan');
        $response->assertSee($order->order_number);
        $response->assertSee('Matrix Logistics');
    }

    public function test_item_warehouse_is_required_for_delivery_challan(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Logistics',
            'status' => 'active',
        ]);

        $payload = [
            'vendor_id' => $vendor->id,
            'challan_date' => date('Y-m-d'),
            'status' => 'draft',
            'items' => [
                ['product_id' => $this->product->id, 'warehouse_id' => null, 'quantity' => 10]
            ]
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payload);

        $response->assertSessionHasErrors('items.0.warehouse_id');
    }

    public function test_blocks_dispatch_when_warehouse_stock_is_insufficient(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Logistics',
            'status' => 'active',
        ]);

        // Stock available is only 3.00, but dispatching 10.00
        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3.00,
            'available_qty' => 3.00,
        ]);

        $payload = [
            'vendor_id' => $vendor->id,
            'challan_date' => date('Y-m-d'),
            'status' => 'dispatched',
            'items' => [
                ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 10.00]
            ]
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payload);

        $response->assertSessionHasErrors('items');
    }

    public function test_can_store_dispatch_and_deduct_inventory_stock(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Logistics',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-2026-000001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => $this->user->id,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Tank Header Crimping',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'status' => 'waiting',
        ]);

        // Provide 50.00 units in warehouse stock
        $stock = ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50.00,
            'available_qty' => 50.00,
        ]);

        $payload = [
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'vendor_id' => $vendor->id,
            'challan_date' => date('Y-m-d'),
            'vehicle_number' => 'MH-12-AB-1234',
            'transporter_name' => 'Logistics Express',
            'lr_number' => 'LR-987654',
            'driver_name' => 'John Driver',
            'status' => 'dispatched',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 10.00,
                    'unit_of_measure' => 'PCS',
                ]
            ]
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payload);

        $challan = DeliveryChallan::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($challan);
        $this->assertEquals('dispatched', $challan->status);
        $this->assertEquals('MH-12-AB-1234', $challan->vehicle_number);

        // Verify stock decreased by 10.00 (from 50.00 to 40.00)
        $stock->refresh();
        $this->assertEquals(40.00, $stock->quantity);
        $this->assertEquals(40.00, $stock->available_qty);

        // Verify OUT StockTransaction was created
        $transaction = StockTransaction::where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'DeliveryChallan')
            ->where('type', 'OUT')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(10.00, $transaction->quantity);

        // Check operation status updated to vendor_dispatched
        $op->refresh();
        $this->assertEquals('vendor_dispatched', $op->status);

        $response->assertRedirect(route('production.subcontract.delivery-challans.show', $challan->id));
    }
}
