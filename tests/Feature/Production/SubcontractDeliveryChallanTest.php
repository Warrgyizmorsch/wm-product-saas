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
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
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

    public function test_mes_dashboard_renders_manage_challan_for_outsourced_operations(): void
    {
        $vendor = \App\Domains\Inventory\Models\Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision Coating Pvt Ltd',
            'code' => 'VEND-COAT-01',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-MES-SUB-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Subcontracted Powder Coating',
            'is_external' => true,
            'vendor_id' => $vendor->id,
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'schedule_number' => 'SCH-MES-SUB-001',
            'production_order_id' => $order->id,
            'status' => ProductionSchedule::STATUS_RELEASED,
            'scheduling_type' => 'forward',
            'generated_by' => 'forward',
            'scheduled_at' => now(),
            'created_by' => 1,
        ]);

        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id' => null,
            'machine_id' => null,
            'sequence' => 20,
            'planned_start' => now(),
            'planned_finish' => now()->addDays(2),
            'planned_duration_minutes' => 2880,
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.mes.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Manage Challan');
        $response->assertSee('Precision Coating Pvt Ltd');
        $response->assertSee('MANAGE CHALLAN');
    }

    public function test_can_receive_subcontract_delivery_challan_and_update_stock_and_operation(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Matrix Logistics',
            'code' => 'VEND-MATRIX',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-REC-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 30,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'TIG Nozzle Welding',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'status' => 'vendor_dispatched',
        ]);

        $challan = DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-REC-001',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => now()->toDateString(),
            'dispatched_wip_qty' => 30.00,
            'status' => 'dispatched',
            'created_by' => $this->user->id,
        ]);

        \App\Domains\Production\Models\DeliveryChallanItem::create([
            'tenant_id' => $this->tenant->id,
            'delivery_challan_id' => $challan->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30.00,
            'unit_of_measure' => 'PCS',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $challan->id), [
                'received_qty' => 30.00,
                'accepted_qty' => 30.00,
                'rejected_qty' => 0.00,
                'warehouse_id' => $this->warehouse->id,
                'remarks' => 'Received 30 pcs clean after welding',
            ]);

        $response->assertRedirect(route('production.subcontract.delivery-challans.show', $challan->id));

        $challan->refresh();
        $this->assertEquals('completed', $challan->status);

        $orderOp->refresh();
        $this->assertEquals('completed', $orderOp->status);
        $this->assertEquals(30.00, $orderOp->quantity_produced);

        $stock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(30.00, $stock->quantity);
    }

    public function test_can_dispatch_and_receive_wip_job_work_without_creating_fg_stock(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ABC Paints Ltd',
            'code' => 'VEND-ABC',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-WIP-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => $this->user->id,
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Sheet Cutting',
            'is_external' => false,
            'status' => 'completed',
            'quantity_produced' => 100.00,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op10->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Spray Painting Service',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'vendor_id' => $vendor->id,
            'status' => 'ready',
            'target_produced_qty' => 100.00,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op20->id,
            'sequence' => 30,
            'operation_number' => '030',
            'name' => 'Final Assembly',
            'is_external' => false,
            'status' => 'waiting',
            'target_produced_qty' => 100.00,
        ]);

        // Initialize WIP at OP10
        $wipService = app(\App\Domains\Production\Services\ProductionWipService::class);
        $wip = $wipService->initializeWipForOrder($order->id, $this->user->id);
        $wipService->completeWipOperation($wip->id, $op10->id, 100.00, 0, 0, 10, 30, 'OP10 Completed', $this->user->id, true);

        // 1. Create Delivery Challan for OP20 (pre-fills 100 WIP from OP10)
        $payload = [
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d'),
            'vehicle_number' => 'MH-12-WIP-99',
            'status' => 'dispatched',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'production_wip_id' => $wip->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 60.00,
                    'unit_of_measure' => 'PCS',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payload);

        $challan = DeliveryChallan::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($challan);
        $this->assertEquals('dispatched', $challan->status);
        $this->assertEquals(60.00, $challan->dispatched_wip_qty);

        // Verify warehouse stock was NOT deducted (because it's WIP on shopfloor)
        $stock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNull($stock); // Zero warehouse stock transactions

        // 2. Receive 60 WIP back from vendor
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $challan->id), [
                'received_qty' => 60.00,
                'accepted_qty' => 60.00,
                'rejected_qty' => 0.00,
                'warehouse_id' => $this->warehouse->id,
                'remarks' => '60 pcs returned painted cleanly',
            ]);

        $response->assertRedirect(route('production.subcontract.delivery-challans.show', $challan->id));

        $challan->refresh();
        $this->assertEquals('completed', $challan->status);

        $op20->refresh();
        $this->assertEquals(60.00, $op20->quantity_produced);

        // CRITICAL ASSERTION 1: Finished Goods warehouse stock MUST remain 0 (NO FG stock created!)
        $fgStock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNull($fgStock);

        // CRITICAL ASSERTION 2: 60 WIP units transferred to OP-30 Assembly!
        $op30->refresh();
        $this->assertEquals(60.00, $op30->quantity_transferred_in);
        $this->assertEquals('ready', $op30->status);
    }

    public function test_wip_job_work_blocks_over_receiving_returned_quantity(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ABC Paints Ltd',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-WIP-OVER-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Spray Painting Service',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'vendor_id' => $vendor->id,
            'status' => 'vendor_dispatched',
        ]);

        $challan = DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-OVER-001',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => now()->toDateString(),
            'dispatched_wip_qty' => 60.00,
            'status' => 'dispatched',
            'created_by' => $this->user->id,
        ]);

        // Attempt to receive 70 (exceeding 60 dispatched)
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $challan->id), [
                'received_qty' => 70.00,
                'accepted_qty' => 70.00,
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertSessionHas('error');
    }
}



