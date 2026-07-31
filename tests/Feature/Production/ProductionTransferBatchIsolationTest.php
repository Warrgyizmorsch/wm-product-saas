<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionTransferBatchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private WorkCenter $workCenter;
    private BatchProductionService $batchService;
    private ProductionWipService $wipService;
    private ProductionExecutionService $executionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        app()->instance('tenant', $this->tenant);
        session(['tenant_id' => $this->tenant->id]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test ISO Product',
            'sku' => 'ISO-001',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-ISO-01',
            'name' => 'ISO WorkCenter',
        ]);

        $this->batchService = app(BatchProductionService::class);
        $this->wipService = app(ProductionWipService::class);
        $this->executionService = app(ProductionExecutionService::class);
    }

    private function createOrderWithRouting(float $transferBatchQty = 5.0, bool $overlapEnabled = true): array
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'RT-ISO-' . rand(1000, 9999),
            'name' => 'ISO Routing',
            'product_id' => $this->product->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $rOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'transfer_batch_quantity' => $transferBatchQty,
            'overlap_enabled' => $overlapEnabled,
        ]);

        $rOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter->id,
            'transfer_batch_quantity' => 0.0,
            'overlap_enabled' => false,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-ISO-' . rand(1000, 9999),
            'product_id' => $this->product->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 50.0,
            'production_mode' => 'batch',
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp10->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'quantity_produced' => 0.0,
            'quantity_transferred_out' => 0.0,
            'transfer_batch_quantity' => $transferBatchQty,
            'overlap_enabled' => $overlapEnabled,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'quantity_produced' => 0.0,
            'quantity_transferred_in' => 0.0,
            'quantity_transferred_out' => 0.0,
        ]);

        return [$order, $op10, $op20];
    }

    /**
     * 1. Test transfer_batch_quantity = 5 creates 3 transfer transactions of 5 units each for 15 qty logged, without creating new ProductionBatches.
     */
    public function test_transfer_batch_quantity_creates_movement_chunks_on_same_production_batch(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);

        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 20.0, ProductionBatch::STATUS_IN_PROGRESS);

        $this->executionService->logProgress($op10->id, 15.0, 0, 0, 0, 10, 'Log 15 units', null, $this->user->id, false, null, $batch1->id);

        // Check total batches in DB for order — MUST REMAIN 1
        $totalBatches = ProductionBatch::where('production_order_id', $order->id)->count();
        $this->assertEquals(1, $totalBatches);
        $this->assertEquals($batch1->id, ProductionBatch::first()->id);

        // Check WIP Transactions for transfer — MUST BE 3 transactions of 5 units each
        $transferTxs = ProductionWipTransaction::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->where('production_batch_id', $batch1->id)
            ->where('transaction_type', 'transferred')
            ->get();

        $this->assertCount(3, $transferTxs);
        foreach ($transferTxs as $tx) {
            $this->assertEquals(5.0, (float) $tx->quantity);
            $this->assertEquals($op10->routing_operation_id, $tx->from_operation_id);
            $this->assertEquals($op20->routing_operation_id, $tx->to_operation_id);
        }

        // OP20 batch queue MUST show BAT-001 with Transferred In = 15
        $queueOp20 = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $queueOp20['active']);
        $this->assertEquals(15.0, $queueOp20['active'][0]['input_received']);
        $this->assertEquals(15.0, $queueOp20['active'][0]['remaining_to_process']);
    }

    /**
     * 2. Test BAT-001 and BAT-002 stay 100% isolated without cross-batch aggregation.
     */
    public function test_bat001_and_bat002_wip_and_queue_isolation(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(2.0, true);

        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $batch2 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Produce 5 on BAT-001 (auto transfers 4, 1 remainder)
        $this->executionService->logProgress($op10->id, 5.0, 0, 0, 0, 10, 'Log 5 on BAT1', null, $this->user->id, false, null, $batch1->id);

        // Produce 5 on BAT-002 (auto transfers 4, 1 remainder)
        $this->executionService->logProgress($op10->id, 5.0, 0, 0, 0, 10, 'Log 5 on BAT2', null, $this->user->id, false, null, $batch2->id);

        // Fetch OP20 queue
        $queueOp20 = $this->batchService->getOperationBatchQueue($op20);

        // Both batches MUST be present at OP20 independently
        $this->assertCount(2, $queueOp20['active']);

        $itemBat1 = collect($queueOp20['active'])->firstWhere('batch.id', $batch1->id);
        $itemBat2 = collect($queueOp20['active'])->firstWhere('batch.id', $batch2->id);

        $this->assertNotNull($itemBat1);
        $this->assertNotNull($itemBat2);

        // Transferred In MUST be 4 for BAT-001 and 4 for BAT-002 (NEVER aggregated into BAT-001)
        $this->assertEquals(4.0, $itemBat1['input_received']);
        $this->assertEquals(4.0, $itemBat2['input_received']);

        // Check WIP records in DB
        $wipBat1_Op20 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op20->routing_operation_id)->first();
        $wipBat2_Op20 = ProductionWip::where('production_batch_id', $batch2->id)->where('current_routing_operation_id', $op20->routing_operation_id)->first();

        $this->assertNotNull($wipBat1_Op20);
        $this->assertNotNull($wipBat2_Op20);
        $this->assertNotEquals($wipBat1_Op20->id, $wipBat2_Op20->id);
    }

    /**
     * 3. Test incomplete source operation keeps remainder below threshold until completed.
     */
    public function test_incomplete_source_operation_keeps_remainder_below_threshold(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 4 units (less than transfer_batch_quantity = 5)
        $this->executionService->logProgress($op10->id, 4.0, 0, 0, 0, 10, 'Log 4 units', null, $this->user->id, false, null, $batch1->id);

        // 0 units transferred because source op is not completed and 4 < 5
        $queueOp20 = $this->batchService->getOperationBatchQueue($op20);
        $this->assertEmpty($queueOp20['active']);
        $this->assertCount(1, $queueOp20['waiting_input']);
    }

    /**
     * 4. Test final remainder auto-transfer when source operation completes.
     */
    public function test_final_remainder_auto_transferred_when_source_operation_completes(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 9.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 9 units and mark op10 completed (should auto-transfer 5 + 4 = 9)
        $this->executionService->logProgress($op10->id, 9.0, 0, 0, 0, 10, 'Complete OP10', null, $this->user->id, true, null, $batch1->id);

        $queueOp20 = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $queueOp20['active']);
        $this->assertEquals(9.0, $queueOp20['active'][0]['input_received']);
    }

    /**
     * 5. Test manual transfer of remaining quantity succeeds.
     */
    public function test_manual_remainder_transfer_succeeds(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 7 units on OP10 (auto transfers 5, leaving 2 ready to transfer)
        $this->executionService->logProgress($op10->id, 7.0, 0, 0, 0, 10, 'Log 7 units', null, $this->user->id, false, null, $batch1->id);

        $wipOp10 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op10->routing_operation_id)->first();
        $this->assertNotNull($wipOp10);

        // Manually transfer remaining 2 units
        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 2.0, 'Manual transfer remainder', $this->user->id);

        $queueOp20 = $this->batchService->getOperationBatchQueue($op20);
        $this->assertCount(1, $queueOp20['active']);
        $this->assertEquals(7.0, $queueOp20['active'][0]['input_received']);
    }

    /**
     * 6. Test manual transfer quantity greater than ready balance is rejected.
     */
    public function test_manual_transfer_exceeding_ready_balance_is_rejected(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 5 units on OP10 (auto transfers 5, leaving 0 ready)
        $this->executionService->logProgress($op10->id, 5.0, 0, 0, 0, 10, 'Log 5 units', null, $this->user->id, false, null, $batch1->id);

        $wipOp10 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op10->routing_operation_id)->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds available ready-to-transfer quantity');

        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 1.0, 'Excess transfer', $this->user->id);
    }

    /**
     * 7. Test manual transfer of zero or negative quantity is rejected.
     */
    public function test_manual_transfer_zero_quantity_is_rejected(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $wipOp10 = $this->wipService->getOrCreateWipForBatchOperation($order->id, $batch1->id, $op10->routing_operation_id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transfer quantity must be greater than zero.');

        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 0.0, 'Zero transfer', $this->user->id);
    }

    /**
     * 8. Test manual transfer double-click is idempotent.
     */
    public function test_manual_transfer_double_click_is_idempotent(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(0.0, false);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        $this->executionService->logProgress($op10->id, 5.0, 0, 0, 0, 10, 'Log 5 units', null, $this->user->id, false, null, $batch1->id);
        $wipOp10 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op10->routing_operation_id)->first();

        $key = 'TX-MANUAL-IDEMPOTENT-123';
        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Manual transfer 1', $this->user->id, $key);
        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Manual transfer 2', $this->user->id, $key);

        $txs = ProductionWipTransaction::where('remarks', 'like', "%IDEMPOTENCY:{$key}%")->get();
        $this->assertCount(1, $txs);
        $this->assertEquals(5.0, (float) $op20->fresh()->quantity_transferred_in);
    }

    /**
     * 9. Test quality hold and pending rework exclude output from transferable balance.
     */
    public function test_quality_hold_and_rework_exclude_output_from_transfer(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(0.0, false);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 5 good units and 5 rejected (creates pending rework / Quality inspection hold)
        $this->executionService->logProgress($op10->id, 5.0, 5.0, 0, 0, 10, 'Log 5 good 5 reject', null, $this->user->id, false, null, $batch1->id);

        $wipOp10 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op10->routing_operation_id)->first();

        // Create pending rework record
        ProductionOrderRework::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op10->id,
            'production_batch_id' => $batch1->id,
            'quantity' => 5.0,
            'rework_quantity' => 5.0,
            'status' => 'pending',
            'recorded_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has an active quality hold or pending rework');

        $this->wipService->transferWip($wipOp10->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'Attempt transfer', $this->user->id);
    }

    /**
     * 10. Test overlapping operations allow the same batch to have separate OP10 and OP20 WIP records.
     */
    public function test_overlapping_operations_allow_same_batch_to_have_separate_op10_and_op20_wip_records(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 20.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 10 on OP10 (auto transfers 10 to OP20)
        $this->executionService->logProgress($op10->id, 10.0, 0, 0, 0, 10, 'Log 10 on OP10', null, $this->user->id, false, null, $batch1->id);

        $wipOp10 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op10->routing_operation_id)->first();
        $wipOp20 = ProductionWip::where('production_batch_id', $batch1->id)->where('current_routing_operation_id', $op20->routing_operation_id)->first();

        $this->assertNotNull($wipOp10);
        $this->assertNotNull($wipOp20);
        $this->assertNotEquals($wipOp10->id, $wipOp20->id);
    }

    /**
     * 11. Test final operation does not create downstream destination WIP.
     */
    public function test_final_operation_does_not_create_downstream_destination_wip(): void
    {
        [$order, $op10, $op20] = $this->createOrderWithRouting(5.0, true);
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Transfer 10 to OP20
        $this->executionService->logProgress($op10->id, 10.0, 0, 0, 0, 10, 'OP10 10 units', null, $this->user->id, true, null, $batch1->id);

        // Log 10 on OP20 (final operation)
        $this->executionService->logProgress($op20->id, 10.0, 0, 0, 0, 10, 'OP20 10 units', null, $this->user->id, true, null, $batch1->id);

        // Evaluate transfers for OP20 -> should return 0.0
        $transferred = $this->wipService->evaluateAndExecuteWipTransfers($op20->id, $this->user->id);
        $this->assertEquals(0.0, $transferred);
    }
}
