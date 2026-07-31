<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MesOperationBatchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;
    protected WorkCenter $workCenter;
    protected BatchProductionService $batchService;
    protected ProductionExecutionService $executionService;
    protected ProductionWipService $wipService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Visibility Test Product',
            'sku' => 'VIS-PRD-001',
            'type' => 'manufactured',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Production Warehouse',
            'code' => 'WH-MAIN',
            'is_default' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Work Center',
            'code' => 'WC-MAIN',
            'status' => 'active',
            'daily_capacity_hours' => 16.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->batchService = app(BatchProductionService::class);
        $this->executionService = app(ProductionExecutionService::class);
        $this->wipService = app(ProductionWipService::class);

        app()->instance('tenant.id', $this->tenant->id);
    }

    protected function createOrderWith3Operations(float $qty = 10.0): array
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => '3-Op Routing',
            'status' => 'approved',
        ]);

        $rOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'OP10 Machining',
        ]);

        $rOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'OP20 Assembly',
        ]);

        $rOp30 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'OP30 Inspection',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-VIS-' . rand(1000, 9999),
            'product_id' => $this->product->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => $qty,
            'production_mode' => 'batch',
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp10->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'OP10 Machining',
            'status' => 'in_progress',
            'planned_quantity' => $qty,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp20->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'OP20 Assembly',
            'status' => 'waiting',
            'planned_quantity' => $qty,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp30->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'OP30 Inspection',
            'status' => 'waiting',
            'planned_quantity' => $qty,
        ]);

        return [$order, $op10, $op20, $op30];
    }

    /**
     * 1. Test Operation-Specific Visibility & Queue Categorization after Full OP10 Transfer.
     */
    public function test_operation_specific_visibility_after_full_op10_transfer(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $wip = $this->wipService->initializeWip($order->id, $batch->id);

        // Log 10.0 at OP10
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 10.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Transfer 10.0 to OP20
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'Transfer all to OP20', $this->user->id);

        // Check OP10 Queue: Batch should NOT be active on OP10, but in completed history
        $op10Queue = $this->batchService->getOperationBatchQueue($op10);
        $this->assertEmpty($op10Queue['active']);
        $this->assertCount(1, $op10Queue['completed']);
        $this->assertEquals($batch->id, $op10Queue['completed'][0]['batch']->id);

        // Check OP20 Queue: Batch SHOULD be active on OP20 as READY with 10 input available
        $op20Queue = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $op20Queue['active']);
        $this->assertEquals($batch->id, $op20Queue['active'][0]['batch']->id);
        $this->assertEquals('READY', $op20Queue['active'][0]['display_status']);
        $this->assertEquals(10.0, $op20Queue['active'][0]['input_available']);
        $this->assertEquals(10.0, $op20Queue['active'][0]['remaining_to_process']);
    }

    /**
     * 2. Test Partial Transfer (5 to OP20, 5 remaining at OP10).
     */
    public function test_partial_transfer_balances_and_same_batch_number(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $wip = $this->wipService->initializeWip($order->id, $batch->id);

        // Log 5.0 at OP10
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Transfer 5.0 to OP20
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Partial transfer', $this->user->id);

        // Check OP10 Queue: Active with 5 processed, 5 remaining
        $op10Queue = $this->batchService->getOperationBatchQueue($op10);
        $this->assertCount(1, $op10Queue['active']);
        $this->assertEquals(5.0, $op10Queue['active'][0]['processed_at_operation']);
        $this->assertEquals(5.0, $op10Queue['active'][0]['remaining_to_process']);
        $this->assertEquals('PARTIALLY_PROCESSED', $op10Queue['active'][0]['display_status']);

        // Check OP20 Queue: Active with 5 transferred in, 0 processed, 5 remaining
        $op20Queue = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $op20Queue['active']);
        $this->assertEquals($batch->id, $op20Queue['active'][0]['batch']->id);
        $this->assertEquals(5.0, $op20Queue['active'][0]['input_available']);
        $this->assertEquals(0.0, $op20Queue['active'][0]['processed_at_operation']);
        $this->assertEquals(5.0, $op20Queue['active'][0]['remaining_to_process']);
        $this->assertEquals('READY', $op20Queue['active'][0]['display_status']);
    }

    /**
     * 3. Test Partial Successor Processing (OP20 receives 5, processes 3).
     */
    public function test_partial_successor_processing(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $wip = $this->wipService->initializeWip($order->id, $batch->id);

        // Log 5.0 at OP10 & transfer to OP20
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Transfer 5', $this->user->id);

        // Log 3.0 at OP20
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 3.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        $op20Queue = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $op20Queue['active']);
        $this->assertEquals(3.0, $op20Queue['active'][0]['processed_at_operation']);
        $this->assertEquals(2.0, $op20Queue['active'][0]['remaining_to_process']);
        $this->assertEquals('PARTIALLY_PROCESSED', $op20Queue['active'][0]['display_status']);
    }

    /**
     * 4. Test Additional Transfer Updates Available Quantity at Successor.
     */
    public function test_additional_transfer_updates_successor_balances(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $wip = $this->wipService->initializeWip($order->id, $batch->id);

        // Log 5.0 at OP10 & transfer 5.0
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Transfer 1', $this->user->id);

        // OP20 processes 3.0
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op20->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 3.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Log remaining 5.0 at OP10 & transfer second batch of 5.0 to OP20
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $batch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Transfer 2', $this->user->id);

        // Check OP20 Queue: Total transferred in = 10.0, Processed = 3.0, Remaining = 7.0
        $op20Queue = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $op20Queue['active']);
        $this->assertEquals(10.0, $op20Queue['active'][0]['input_received']);
        $this->assertEquals(3.0, $op20Queue['active'][0]['processed_at_operation']);
        $this->assertEquals(7.0, $op20Queue['active'][0]['remaining_to_process']);
    }

    /**
     * 5. Test No Predecessor Transfer Categorized into Waiting Input.
     */
    public function test_no_predecessor_transfer_categorized_into_waiting_input(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // OP20 Queue before any transfer from OP10
        $op20Queue = $this->batchService->getOperationBatchQueue($op20);

        $this->assertEmpty($op20Queue['active']);
        $this->assertCount(1, $op20Queue['waiting_input']);
        $this->assertEquals($batch->id, $op20Queue['waiting_input'][0]['batch']->id);
        $this->assertFalse($op20Queue['waiting_input'][0]['can_log_progress']);
    }

    /**
     * 6. Test Quality Blocked / Rework Categorization.
     */
    public function test_quality_blocked_and_rework_categorization(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        $batchBlocked = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_BLOCKED);

        $op10Queue = $this->batchService->getOperationBatchQueue($op10);

        $this->assertCount(1, $op10Queue['blocked']);
        $this->assertEquals('BLOCKED', $op10Queue['blocked'][0]['display_status']);
        $this->assertFalse($op10Queue['blocked'][0]['can_log_progress']);
    }

    /**
     * 7. Test Tenant Isolation for Operation Queue.
     */
    public function test_tenant_isolation_for_operation_queue(): void
    {
        [$order1, $op10_1] = $this->createOrderWith3Operations(10.0);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order1->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Create foreign tenant and order
        $foreignTenant = Tenant::factory()->create();
        $foreignProduct = Product::create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Foreign Product',
            'sku' => 'FOR-001',
        ]);
        $foreignOrder = ProductionOrder::create([
            'tenant_id' => $foreignTenant->id,
            'order_number' => 'ORD-FOR-999',
            'product_id' => $foreignProduct->id,
            'quantity_ordered' => 10.0,
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        $foreignBatch = $this->batchService->createBatch($foreignTenant->id, $foreignOrder->id, $foreignProduct->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Fetch queue for tenant 1 op10
        $op10Queue = $this->batchService->getOperationBatchQueue($op10_1);

        // Must ONLY contain batch1, NOT foreignBatch
        $this->assertCount(1, $op10Queue['active']);
        $this->assertEquals($batch1->id, $op10Queue['active'][0]['batch']->id);
    }
}
