<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractAdversarialE2ETest extends TestCase
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
            'slug' => 'adv-tenant',
            'status' => 'active',
            'name' => 'Adversarial Test Tenant',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Audit Admin',
            'email' => 'audit@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Valve Body',
            'sku' => 'VALVE-BODY-001',
            'unit_of_measure' => 'PCS',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Production Warehouse',
            'code' => 'MAIN-WH',
        ]);
    }

    /**
     * Test 1: Complete Flow:
     * OP10 Cutting (100 WIP) -> OP20 Spray Painting (Outsourced WIP Job Work) -> Partial Dispatch 60
     * (40 remains in-house) -> Vendor Returns 60 -> OP30 Assembly receives EXACTLY 60 WIP.
     */
    public function test_adversarial_partial_dispatch_and_return_wip_flow(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Coatings & Paint Corp',
            'code' => 'VEND-COAT',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-ADV-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
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

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-ADV-001',
            'planned_quantity' => 100,
            'status' => 'planned',
        ]);

        // Initialize WIP & Batch
        $wipService = app(ProductionWipService::class);
        $wip = $wipService->initializeWipForOrder($order->id, $this->user->id, $batch->id);
        $initialBatchId = $wip->production_batch_id;
        $this->assertEquals($batch->id, $initialBatchId);

        $wipService->completeWipOperation($wip->id, $op10->id, 100.00, 0, 0, 10, 30, 'OP10 Complete', $this->user->id, true);

        // 1. Partial Dispatch 60 units (out of 100 available)
        $payload = [
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d'),
            'vehicle_number' => 'MH-12-PARTIAL-01',
            'status' => 'dispatched',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'production_wip_id' => $wip->id,
                    'production_batch_id' => $initialBatchId,
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
        $this->assertEquals(60.00, $challan->dispatched_wip_qty);

        // Verify warehouse stock was NOT deducted
        $stock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNull($stock);

        // 2. Vendor returns 60 WIP units
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $challan->id), [
                'received_qty' => 60.00,
                'accepted_qty' => 60.00,
                'rejected_qty' => 0.00,
                'warehouse_id' => $this->warehouse->id,
                'remarks' => 'Returned 60 painted parts',
            ]);

        $challan->refresh();
        $this->assertEquals('completed', $challan->status);

        // Verify FG Stock is STILL ZERO (Intermediate WIP return does not create FG)
        $fgStock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNull($fgStock);

        // Verify NO stock transactions (IN or OUT) occurred
        $stockTxCount = StockTransaction::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(0, $stockTxCount);

        // Verify OP30 Assembly received EXACTLY 60 WIP units (NOT 100!)
        $op30->refresh();
        $this->assertEquals(60.00, $op30->quantity_transferred_in);
        $this->assertEquals('ready', $op30->status);

        // Verify Batch Genealogy & Batch Uniqueness (No duplicate batch created)
        $batchCount = ProductionBatch::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $batchCount);

        $wipTx = ProductionWipTransaction::where('tenant_id', $this->tenant->id)
            ->where('transaction_type', 'subcontract_received')
            ->first();
        $this->assertNotNull($wipTx);
        $this->assertEquals($initialBatchId, $wipTx->production_batch_id);
    }

    /**
     * Test 2: Sequential Outsourced Operations Workflow:
     * OP10 Cutting -> OP20 Spray Painting (Outsourced WIP) -> OP25 Heat Treatment (Outsourced WIP) -> OP30 Assembly (Internal).
     */
    public function test_adversarial_sequential_outsourced_operations_flow(): void
    {
        $vendorA = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Paint Vendor A',
            'status' => 'active',
        ]);

        $vendorB = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heat Treat Vendor B',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-SEQ-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Cutting',
            'is_external' => false,
            'status' => 'completed',
            'quantity_produced' => 50.00,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op10->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Spray Painting',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'vendor_id' => $vendorA->id,
            'status' => 'ready',
            'target_produced_qty' => 50.00,
        ]);

        $op25 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op20->id,
            'sequence' => 25,
            'operation_number' => '025',
            'name' => 'Heat Treatment',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'vendor_id' => $vendorB->id,
            'status' => 'waiting',
            'target_produced_qty' => 50.00,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op25->id,
            'sequence' => 30,
            'operation_number' => '030',
            'name' => 'Assembly',
            'is_external' => false,
            'status' => 'waiting',
            'target_produced_qty' => 50.00,
        ]);

        $wipService = app(ProductionWipService::class);
        $wip = $wipService->initializeWipForOrder($order->id, $this->user->id);
        $wipService->completeWipOperation($wip->id, $op10->id, 50.00, 0, 0, 10, 30, 'OP10 Done', $this->user->id, true);

        // 1. Dispatch & Receive OP20
        $dc1 = DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-SEQ-20',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'vendor_id' => $vendorA->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d'),
            'dispatched_wip_qty' => 50.00,
            'status' => 'dispatched',
            'created_by' => $this->user->id,
        ]);

        DeliveryChallanItem::create([
            'tenant_id' => $this->tenant->id,
            'delivery_challan_id' => $dc1->id,
            'product_id' => $this->product->id,
            'production_wip_id' => $wip->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50.00,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $dc1->id), [
                'received_qty' => 50.00,
                'accepted_qty' => 50.00,
                'warehouse_id' => $this->warehouse->id,
            ]);

        // OP25 should now be unlocked ('ready') with 50 transferred in
        $op25->refresh();
        $this->assertEquals('ready', $op25->status);
        $this->assertEquals(50.00, $op25->quantity_transferred_in);

        // 2. Dispatch & Receive OP25
        $dc2 = DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'challan_number' => 'DC-SEQ-25',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op25->id,
            'vendor_id' => $vendorB->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d'),
            'dispatched_wip_qty' => 50.00,
            'status' => 'dispatched',
            'created_by' => $this->user->id,
        ]);

        DeliveryChallanItem::create([
            'tenant_id' => $this->tenant->id,
            'delivery_challan_id' => $dc2->id,
            'product_id' => $this->product->id,
            'production_wip_id' => $wip->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50.00,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.receive', $dc2->id), [
                'received_qty' => 50.00,
                'accepted_qty' => 50.00,
                'warehouse_id' => $this->warehouse->id,
            ]);

        // OP30 should now be unlocked ('ready') with 50 transferred in
        $op30->refresh();
        $this->assertEquals('ready', $op30->status);
        $this->assertEquals(50.00, $op30->quantity_transferred_in);

        // Finished Goods warehouse stock MUST still be zero!
        $stock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)->first();
        $this->assertNull($stock);
    }

    /**
     * Test 3: Schedule Generation & Pre-Release Validation for Outsourced Predecessor Operations:
     * Validates that forward scheduling automatically schedules successor operations after
     * the outsourced operation finish time, preventing DEPENDENCY_VIOLATION errors, and
     * verifies that WIP Job Work operations do not appear under Company Material Balance section on Production Order detail view.
     */
    public function test_outsourced_predecessor_scheduling_and_material_balance_ui_isolation(): void
    {
        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly WC',
            'code' => 'WC-ASSY',
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outsourced Plating Vendor',
            'code' => 'VEND-PLATE',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-SCHED-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'status' => ProductionOrder::STATUS_DRAFT,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addDays(10),
        ]);

        $op40 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 40,
            'operation_number' => '040',
            'name' => 'Outsourced Heat Treat / Painting',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'material_supply_type' => 'company_supplied',
            'vendor_id' => $vendor->id,
            'subcontract_lead_time_days' => 2,
            'dispatch_buffer_days' => 0,
            'return_buffer_days' => 0,
            'status' => 'ready',
        ]);

        $op50 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op40->id,
            'sequence' => 50,
            'operation_number' => '050',
            'name' => 'Final Assembly',
            'is_external' => false,
            'work_center_id' => $wc->id,
            'run_time_per_unit' => 5,
            'status' => 'waiting',
        ]);

        \App\Domains\Production\Models\ProductionOrderOperationDependency::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op50->id,
            'predecessor_operation_id' => $op40->id,
            'dependency_type' => 'finish_to_start',
        ]);

        // Generate Forward Schedule
        $schedulingService = app(\App\Domains\Production\Services\SchedulingService::class);
        $schedule = $schedulingService->generateForwardSchedule($order, now()->startOfDay());

        $schedOp40 = $schedule->operations->where('production_order_operation_id', $op40->id)->first();
        $schedOp50 = $schedule->operations->where('production_order_operation_id', $op50->id)->first();

        $this->assertNotNull($schedOp40);
        $this->assertNotNull($schedOp50);

        // Successor planned start MUST be at or after predecessor planned finish
        $this->assertTrue(
            $schedOp50->planned_start->gte($schedOp40->planned_finish),
            "Sequence 50 start ({$schedOp50->planned_start}) must be at or after Sequence 40 finish ({$schedOp40->planned_finish})"
        );

        // Pre-release validation must pass with NO DEPENDENCY_VIOLATION errors
        $validationService = app(\App\Domains\Production\Services\SchedulePreReleaseValidationService::class);
        $validation = $validationService->validate($schedule);

        $hasBlockingErrors = collect($validation['errors'])->contains(fn($err) => ($err['code'] ?? '') === 'DEPENDENCY_VIOLATION');
        $this->assertFalse($hasBlockingErrors, "Schedule pre-release validation should not have DEPENDENCY_VIOLATION errors.");

        // Verify Order Detail UI does NOT render OP40 under Company Material Balance section
        $matBalanceService = app(\App\Domains\Production\Services\SubcontractMaterialBalanceService::class);
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee('Previous Op WIP (Job Work)');
        $response->assertDontSee('Industrial Valve Body (Component/Raw)');
    }

    /**
     * Test 4: Creating a Routing via DTO/Form with subcontract_input_type = previous_operation_wip
     * correctly persists through Routing -> ProductionOrderOperation -> Delivery Challan creation page.
     */
    public function test_routing_creation_persists_subcontract_input_type_to_production_order_and_challan(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Spray Painting Vendor',
            'status' => 'active',
        ]);

        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-CUT-01',
            'name' => 'Cutting Work Center',
            'status' => 'active',
        ]);

        // 1. Create Routing with WIP Job Work operation
        $routingData = [
            'name' => 'WIP Job Work Routing',
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'operation_number' => 'OP-010',
                    'name' => 'Cutting',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $wc->id,
                    'is_external' => false,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 5,
                ],
                [
                    'sequence' => 20,
                    'operation_number' => 'OP-020',
                    'name' => 'Outsourced Spray Painting',
                    'operation_type' => 'outsourcing',
                    'is_external' => true,
                    'vendor_id' => $vendor->id,
                    'subcontract_input_type' => 'previous_operation_wip',
                    'subcontract_lead_time_days' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.routing.store'), $routingData);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        $routing = \App\Domains\Production\Models\Routing::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($routing);

        $routingOp20 = $routing->operations->where('sequence', 20)->first();
        $this->assertNotNull($routingOp20);
        $this->assertEquals('previous_operation_wip', $routingOp20->subcontract_input_type);
        $this->assertTrue($routingOp20->isWipJobWork());

        // Approve routing so it can be used for Production Orders
        $routing->update(['status' => \App\Domains\Production\Models\Routing::STATUS_ACTIVE]);

        // Create BOM & Production Order from Routing
        $bom = \App\Domains\Production\Models\ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TEST-WIP-01',
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-WIP-TEST-01',
            'product_id' => $this->product->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $poService = app(\App\Domains\Production\Services\ProductionOrderService::class);
        $poService->snapshotMultiLevelRoutings($order, $bom, $routing, 10.0, $this->tenant->id, $this->user->id);

        $orderOp20 = $order->operations->where('sequence', 20)->first();
        $this->assertNotNull($orderOp20);
        $this->assertEquals('previous_operation_wip', $orderOp20->subcontract_input_type);
        $this->assertTrue($orderOp20->isWipJobWork());

        // 3. Request Subcontract Delivery Challan Create Page
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.subcontract.delivery-challans.create', [
                'production_order_id' => $order->id,
                'operation_id' => $orderOp20->id,
            ]));

        $response->assertStatus(200);
        // Ensure the delivery challan pre-fills the previous operation WIP description and NOT raw materials
        $response->assertSee('Outward WIP Dispatch');
        $response->assertSee($this->product->name);
    }

    /**
     * Test 5: WIP Job Work dispatches are strictly bounded by predecessor produced WIP.
     * Creating a dispatch when predecessor produced 0 WIP is BLOCKED.
     * Creating a dispatch exceeding produced WIP is BLOCKED.
     */
    public function test_wip_job_work_dispatch_is_strictly_bounded_by_predecessor_produced_wip_qty(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'WIP Test Vendor',
            'status' => 'active',
        ]);

        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-TEST-01',
            'name' => 'Cutting Work Center',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-BOUND-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Cutting',
            'work_center_id' => $wc->id,
            'quantity_produced' => 0.00, // 0 WIP produced!
            'status' => 'ready',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'previous_operation_id' => $op10->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Outsourced Spray Painting',
            'is_external' => true,
            'subcontract_input_type' => 'previous_operation_wip',
            'vendor_id' => $vendor->id,
            'status' => 'waiting',
        ]);

        $wip = \App\Domains\Production\Models\ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'current_routing_operation_id' => null,
            'qty_available' => 0.00,
            'status' => 'available',
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-BOUND-001',
            'planned_quantity' => 50,
            'initial_quantity' => 50,
            'current_quantity' => 50,
            'status' => 'in_progress',
        ]);

        // 1. Attempt to dispatch when OP10 has produced 0 WIP -> MUST BE REJECTED
        $payloadZero = [
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op20->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'challan_date' => date('Y-m-d'),
            'status' => 'dispatched',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'production_wip_id' => $wip->id,
                    'production_batch_id' => $batch->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 10.00,
                    'unit_of_measure' => 'PCS',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payloadZero);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, DeliveryChallan::where('tenant_id', $this->tenant->id)->count());

        // 2. OP10 produces 15 WIP units
        $op10->update(['quantity_produced' => 15.00]);
        $wip->update(['qty_available' => 15.00]);

        // Attempting to dispatch 20 units (when only 15 available) -> MUST BE REJECTED
        $payloadExcess = $payloadZero;
        $payloadExcess['items'][0]['quantity'] = 20.00;

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payloadExcess);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, DeliveryChallan::where('tenant_id', $this->tenant->id)->count());

        // Dispatching 15 units (within available WIP) -> MUST SUCCEED
        $payloadValid = $payloadZero;
        $payloadValid['items'][0]['quantity'] = 15.00;

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), $payloadValid);

        $response->assertSessionHasNoErrors();
        $challan = DeliveryChallan::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($challan);
        $this->assertEquals(15.00, $challan->dispatched_wip_qty);
    }

    /**
     * Test 5: Quality Rejection Rework Flow:
     * When 2 items are rejected during QC, submitting disposition creating a Rework Order
     * (RWK-XXXXX) and NCR (NCR-SF-XXXXX), re-engaging the workstation/machine, and
     * upon rework completion, restoring the 2 items back to available WIP and unlocking successor.
     */
    public function test_rework_order_creation_and_recovery_flow(): void
    {
        $this->withoutExceptionHandling();
        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fabrication Workstation',
            'code' => 'FAB-WC-01',
        ]);

        $machine = \App\Domains\Production\Models\Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name' => 'CNC Milling Center',
            'code' => 'CNC-01',
            'status' => 'Available',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-RWK-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-RWK-001',
            'planned_quantity' => 10,
            'status' => 'in_production',
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'Precision Machining',
            'work_center_id' => $wc->id,
            'machine_id' => $machine->id,
            'is_external' => false,
            'status' => 'running',
            'quantity_produced' => 8.00,
            'quantity_rejected' => 2.00,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Assembly',
            'work_center_id' => $wc->id,
            'is_external' => false,
            'status' => 'pending',
            'quantity_produced' => 0.00,
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'current_routing_operation_id' => null,
            'current_work_center_id' => $wc->id,
            'quantity' => 10,
            'available_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 'active',
        ]);

        // Submit Shopfloor Quality Disposition for 2 rejected units -> Rework Choice
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.mes.disposition', $op10->id), [
                'disposition_type' => 'rework',
                'quantity' => 2.00,
                'work_center_id' => $wc->id,
                'machine_id' => $machine->id,
                'rework_type' => 'reprocess',
                'cost_estimate' => 100.00,
                'reason' => 'Dimensional tolerance out of spec by 0.2mm',
                'instructions' => 'Re-run CNC milling program on defective units',
                'batch_id' => $batch->id,
            ]);

        $response->assertSessionHasNoErrors();

        // Verify NCR and Rework Order creation
        $ncr = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($ncr, 'NCR should be automatically generated upon rework disposition.');
        $this->assertEquals('rework', $ncr->disposition_type);

        $reworkOrder = \App\Domains\Production\Models\ProductionReworkOrder::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($reworkOrder, 'Rework Order (RWK-XXXXX) should be automatically created.');
        $this->assertEquals($ncr->id, $reworkOrder->ncr_id);
        $this->assertEquals($order->id, $reworkOrder->original_production_order_id);

        // Verify Rework Operations are mapped to workstation/machine
        $reworkOps = \App\Domains\Production\Models\ProductionReworkOperation::where('rework_order_id', $reworkOrder->id)->get();
        $this->assertGreaterThanOrEqual(1, $reworkOps->count());
        $this->assertEquals($wc->id, $reworkOps->first()->work_center_id);

        // Simulate Rework execution & completion
        $reworkService = app(\App\Domains\Production\Services\ReworkService::class);
        foreach ($reworkOps as $rwkOp) {
            $reworkService->startOperation($rwkOp->id, $this->tenant->id);
            $reworkService->completeOperation($rwkOp->id, ['setup_time_actual' => 0.2, 'processing_time_actual' => 0.5], $this->tenant->id);
        }

        // Verify Rework Order and NCR are finalized/closed
        $this->assertEquals('completed', $reworkOrder->fresh()->status);
        $this->assertEquals('closed', $ncr->fresh()->status);

        // Verify 2 items were restored back to Op10 quantity_produced and available WIP
        $this->assertEquals(10.00, $op10->fresh()->quantity_produced);
        $this->assertEquals(0.00, $op10->fresh()->quantity_rejected);
        $this->assertEquals(10.00, $wip->fresh()->available_quantity);
    }

    /**
     * Test 7: Rework completion on QC-enabled operation routes units to Pending QC
     * instead of directly auto-accepting them into available WIP.
     */
    public function test_rework_order_completion_triggers_re_qc_when_quality_required_enabled(): void
    {
        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'QC Machine Shop',
            'code' => 'QC-SHOP',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-QC-RWK-002',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-QC-RWK-002',
            'planned_quantity' => 10,
            'status' => 'in_production',
        ]);

        // Operation with quality_required = TRUE
        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => '010',
            'name' => 'High-Precision Grinding',
            'work_center_id' => $wc->id,
            'quality_required' => true,
            'status' => 'running',
            'quantity_produced' => 8.00,
            'quantity_rejected' => 2.00,
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'current_work_center_id' => $wc->id,
            'quantity' => 10,
            'available_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 'active',
        ]);

        // 1. Submit Rework Disposition for 2 rejected units
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.mes.disposition', $op10->id), [
                'disposition_type' => 'rework',
                'quantity' => 2.00,
                'work_center_id' => $wc->id,
                'rework_type' => 'reprocess',
                'cost_estimate' => 100.00,
                'reason' => 'Defective finish',
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        $reworkOrder = \App\Domains\Production\Models\ProductionReworkOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $reworkOps = \App\Domains\Production\Models\ProductionReworkOperation::where('rework_order_id', $reworkOrder->id)->get();

        // 2. Complete Rework operations
        $reworkService = app(\App\Domains\Production\Services\ReworkService::class);
        foreach ($reworkOps as $rwkOp) {
            $reworkService->startOperation($rwkOp->id, $this->tenant->id);
            $reworkService->completeOperation($rwkOp->id, ['setup_time_actual' => 0.1, 'processing_time_actual' => 0.4], $this->tenant->id);
        }

        // 3. Verify units were NOT directly auto-accepted:
        // - quantity_produced stays 8.00 (not 10.00)
        // - available_quantity stays 8.00 (successor operation remains locked)
        // - Pending QC quantity is now 2.00!
        $this->assertEquals(8.00, $op10->fresh()->quantity_produced);
        $this->assertEquals(8.00, $wip->fresh()->available_quantity);
        $mesExecutionService = app(\App\Domains\Production\Services\MesExecutionService::class);
        $this->assertEquals(2.00, $mesExecutionService->getPendingQcQuantity($op10->id));

        // 4. Perform Re-QC Inspection for the 2 reworked units
        $qcService = app(\App\Domains\Production\Services\QualityInspectionService::class);
        $qcService->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $op10->id,
            'accepted_qty' => 2.00,
            'rejected_qty' => 0.00,
            'batch_id' => $batch->id,
            'remarks' => 'Re-QC inspection passed after shopfloor rework',
        ], $this->user->id);

        // 5. Verify units are NOW accepted into produced & available WIP after passing Re-QC:
        $this->assertEquals(10.00, $op10->fresh()->quantity_produced);
        $this->assertEquals(10.00, $wip->fresh()->available_quantity);
        $this->assertEquals(0.00, $mesExecutionService->getPendingQcQuantity($op10->id));
    }

    /**
     * Test 8: Subcontract Vendor Rework Disposition creates a Rework Delivery Challan (Return Gate Pass)
     * for returning rejected units back to the subcontractor vendor.
     */
    public function test_subcontract_vendor_rework_challan_creation_and_dispatch_flow(): void
    {
        $this->withoutExceptionHandling();
        $wc = \App\Domains\Production\Models\WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subcontract Receiving Bay',
            'code' => 'SUB-BAY',
            'status' => 'active',
        ]);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electroplating Vendor Corp',
            'code' => 'VEND-ELECTRO',
            'status' => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-SUB-RWK-003',
            'product_id' => $this->product->id,
            'quantity_ordered' => 4,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BATCH-SUB-RWK-003',
            'planned_quantity' => 4,
            'status' => 'in_production',
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => '020',
            'name' => 'Electroplating Service',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'quality_required' => true,
            'status' => 'running',
            'quantity_produced' => 3.00,
            'quantity_rejected' => 1.00,
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'available_quantity' => 3,
            'rejected_quantity' => 1,
            'status' => 'active',
        ]);

        // Submit Subcontract Rework Disposition (Vendor Rework Pathway)
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.mes.disposition', $op20->id), [
                'disposition_type' => 'rework',
                'rework_location' => 'vendor_rework',
                'quantity' => 1.00,
                'reason' => 'Surface coating uneven on 1 unit',
                'instructions' => 'Re-dip and polish defective unit',
                'batch_id' => $batch->id,
            ]);

        $response->assertSessionHasNoErrors();

        // Verify NCR creation with subcontract category and vendor_rework disposition
        $ncr = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($ncr);
        $this->assertEquals('subcontract', $ncr->category);
        $this->assertEquals('vendor_rework', $ncr->disposition_type);

        // Verify Subcontract Rework Delivery Challan (Return Gate Pass) creation
        $challan = DeliveryChallan::where('tenant_id', $this->tenant->id)->where('type', 'vendor_rework')->latest('id')->first();
        $this->assertNotNull($challan, 'Subcontract Rework Delivery Challan should be created.');
        $this->assertEquals($vendor->id, $challan->vendor_id);
        $this->assertEquals(1.00, $challan->dispatched_wip_qty);

        $challanItem = DeliveryChallanItem::where('delivery_challan_id', $challan->id)->first();
        $this->assertNotNull($challanItem);
        $this->assertEquals(1.00, $challanItem->quantity);

        // Verify WIP Transaction for Subcontract Rework Dispatch
        $wipTx = ProductionWipTransaction::where('tenant_id', $this->tenant->id)
            ->where('transaction_type', 'subcontract_rework_dispatched')
            ->latest('id')
            ->first();
        $this->assertNotNull($wipTx);
        $this->assertEquals(1.00, $wipTx->quantity);
    }
}
