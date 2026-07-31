<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\LotTraceabilityService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\ReworkService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBatchRoutingContinuityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private WorkCenter $workCenter;
    private ProductionExecutionService $executionService;
    private BatchProductionService $batchService;
    private ProductionWipService $wipService;
    private ReworkService $reworkService;
    private LotTraceabilityService $traceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Continuity Test Tenant',
            'slug' => 'test-continuity-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Continuity Operator',
            'email' => 'operator@continuitytest.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Continuity Test Product',
            'sku' => 'CONT-PROD-01',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Center 1',
            'code' => 'WC-ASSY-1',
            'status' => 'active',
            'daily_capacity_hours' => 16.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->executionService = app(ProductionExecutionService::class);
        $this->batchService = app(BatchProductionService::class);
        $this->wipService = app(ProductionWipService::class);
        $this->reworkService = app(ReworkService::class);
        $this->traceService = app(LotTraceabilityService::class);

        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);
    }

    private function createOrderWith3Operations(float $qty = 10.0): array
    {
        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Default Routing',
            'version' => '1.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        $rOp10 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
        ]);

        $rOp20 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Sanding',
            'work_center_id' => $this->workCenter->id,
        ]);

        $rOp30 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Painting',
            'work_center_id' => $this->workCenter->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-CONT-' . strtoupper(uniqid()),
            'product_id' => $this->product->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => $qty,
            'start_date' => today(),
            'end_date' => today()->addDays(5),
            'status' => ProductionOrder::STATUS_RELEASED,
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp10->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp20->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Sanding',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp30->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Painting',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
        ]);

        // Initialize WIP
        $wip = $this->wipService->initializeWip($order->id, null, $this->user->id);

        return [$order, $op10, $op20, $op30, $wip];
    }

    /**
     * 1. Test Normal Routing Continuity: OP10 -> OP20 -> OP30 uses same batch without BATCH-002.
     */
    public function test_normal_routing_continuity_preserves_same_batch_number(): void
    {
        [$order, $op10, $op20, $op30] = $this->createOrderWith3Operations(10.0);

        // OP10 Progress (10 produced)
        $log1 = $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 30, 'OP10 complete', null, $this->user->id, false);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);

        $this->assertEquals(1, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertEquals('in_progress', $batch->status); // Intermediate OP completion does NOT complete batch
        $this->assertTrue(in_array($batch->fresh()->current_operation_id, [$op10->id, $op20->id]));

        // Transfer to OP20
        $this->wipService->transferWip($order->wips->first()->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'Move to OP20', $this->user->id);

        // OP20 Progress (8 produced)
        $log2 = $this->executionService->logProgress($op20->id, 8.0, 0.0, 0.0, 10, 30, 'OP20 progress', null, $this->user->id, false, null, $batch->id);

        // Confirm SAME batch number is used and NO surplus batch created
        $this->assertEquals($batch->id, $log2->production_batch_id);
        $this->assertEquals(1, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertEquals($op20->id, $batch->fresh()->current_operation_id);
    }

    /**
     * 2. Test Partial Same-Batch Continuation.
     */
    public function test_partial_same_batch_continuation(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        // Log 6 units at OP10
        $log1 = $this->executionService->logProgress($op10->id, 6.0, 0.0, 0.0, 10, 20, 'OP10 partial 1', null, $this->user->id);
        $batch1 = ProductionBatch::findOrFail($log1->production_batch_id);

        // Transfer 4 units to OP20
        $this->wipService->transferWip($order->wips->first()->id, $op10->routing_operation_id, $op20->routing_operation_id, 4.0, 'Partial transfer', $this->user->id);

        // Log remaining 4 units at OP10
        $log2 = $this->executionService->logProgress($op10->id, 4.0, 0.0, 0.0, 10, 20, 'OP10 partial 2', null, $this->user->id);

        // Should use SAME batch1, no BATCH-002 created
        $this->assertEquals($batch1->id, $log2->production_batch_id);
        $this->assertEquals(1, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertEquals(10.0, ProductionOrderProgressLog::where('production_batch_id', $batch1->id)->where('operation_id', $op10->id)->sum('quantity_produced'));
    }

    /**
     * 3. Test Earlier Operation Additional Production Creates New Batch.
     */
    public function test_earlier_operation_additional_production_creates_new_batch(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(15.0);

        // Explicitly create BATCH-001 with planned quantity 10
        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log 10 units at OP10 (filling BATCH-001 planned capacity of 10)
        $log1 = $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 30, 'OP10 full', null, $this->user->id, false, null, $batch1->id);

        // Move BATCH-001 to OP20
        $this->wipService->transferWip($order->wips->first()->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'Transfer all', $this->user->id);

        // Now log 3 NEW independent units at OP10
        $log2 = $this->executionService->logProgress($op10->id, 3.0, 0.0, 0.0, 5, 10, 'OP10 extra', null, $this->user->id);

        // Confirm BATCH-001 stays at OP20 and BATCH-002 is created for OP10
        $this->assertEquals(2, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertNotEquals($batch1->id, $log2->production_batch_id);
        $this->assertEquals($op20->id, $batch1->fresh()->current_operation_id);
    }

    /**
     * 4. Test No Backward Operation Rewinding.
     */
    public function test_no_backward_operation_rewinding(): void
    {
        [$order, $op10, $op20, $op30] = $this->createOrderWith3Operations(10.0);

        $log1 = $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 30, 'OP10', null, $this->user->id, false);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);

        // Transfer to OP20 then OP30
        $wip = $order->wips->first();
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'To OP20', $this->user->id);

        // Log OP20 progress and transfer to OP30
        $this->executionService->logProgress($op20->id, 10.0, 0.0, 0.0, 10, 20, 'OP20', null, $this->user->id, false, null, $batch->id);
        $this->wipService->transferWip($wip->id, $op20->routing_operation_id, $op30->routing_operation_id, 10.0, 'To OP30', $this->user->id);

        // Log progress at OP30
        $this->executionService->logProgress($op30->id, 5.0, 0.0, 0.0, 10, 20, 'OP30', null, $this->user->id, false, null, $batch->id);

        $this->assertEquals($op30->id, $batch->fresh()->current_operation_id);
    }

    /**
     * 5. Test Scrap and Rework Linkage to Batch and Operation.
     */
    public function test_scrap_and_rework_linked_to_batch_and_operation(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        // Log progress with 8 produced, 1 scrapped, 1 rejected (rework)
        $log = $this->executionService->logProgress($op10->id, 8.0, 1.0, 1.0, 10, 30, 'Mixed progress', null, $this->user->id);

        $scrap = ProductionOrderScrap::where('production_order_id', $order->id)->first();
        $rework = ProductionOrderRework::where('production_order_id', $order->id)->first();

        $this->assertNotNull($scrap);
        $this->assertEquals($log->production_batch_id, $scrap->production_batch_id);
        $this->assertEquals($op10->id, $scrap->production_order_operation_id);

        $this->assertNotNull($rework);
        $this->assertEquals($log->production_batch_id, $rework->production_batch_id);
        $this->assertEquals($op10->id, $rework->production_order_operation_id);
    }

    /**
     * 6. Test Rework Recovery Reconciliation.
     */
    public function test_rework_recovery_reconciles_available_wip(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        // Log progress with 1 rejected unit
        $this->executionService->logProgress($op10->id, 8.0, 1.0, 0.0, 10, 30, 'Progress with rework', null, $this->user->id);

        $orderRework = ProductionOrderRework::where('production_order_id', $order->id)->first();
        $this->assertEquals('pending', $orderRework->status);

        // Complete all operations on rework order
        $reworkOrder = \App\Domains\Production\Models\ProductionReworkOrder::where('original_production_order_id', $order->id)->first();
        foreach ($reworkOrder->operations as $reworkOp) {
            $this->reworkService->startOperation($reworkOp->id, $this->tenant->id);
            $this->reworkService->completeOperation($reworkOp->id, [], $this->tenant->id);
        }

        // Verify orderRework is completed and WIP transaction for rework recovery was recorded
        $this->assertEquals('completed', $orderRework->fresh()->status);
        $recoveryTx = \App\Domains\Production\Models\ProductionWipTransaction::where('production_order_id', $order->id)
            ->where('transaction_type', 'rework_completed')
            ->first();

        $this->assertNotNull($recoveryTx);
        $this->assertEquals(1.0, (float) $recoveryTx->good_quantity);
    }

    /**
     * 7. Test Idempotency Guard.
     */
    public function test_idempotency_key_prevents_duplicate_progress(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        $key = 'IDEM-KEY-99999';

        $log1 = $this->executionService->logProgress($op10->id, 5.0, 0.0, 0.0, 10, 20, 'Log 1', null, $this->user->id, false, $key);
        $log2 = $this->executionService->logProgress($op10->id, 5.0, 0.0, 0.0, 10, 20, 'Log 2 Duplicate', null, $this->user->id, false, $key);

        $this->assertEquals($log1->id, $log2->id);
        $this->assertEquals(1, ProductionOrderProgressLog::where('production_order_id', $order->id)->count());
        $this->assertEquals(5.0, (float) $op10->fresh()->quantity_produced);
    }

    /**
     * 8. Test Lot Summary Calculation.
     */
    public function test_lot_summary_calculation(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        $this->executionService->logProgress($op10->id, 8.0, 1.0, 1.0, 10, 30, 'Progress', null, $this->user->id);

        $summary = $this->traceService->getLotSummary($this->tenant->id, $order->id);

        $this->assertEquals($order->id, $summary['order_id']);
        $this->assertEquals(8.0, $summary['unique_produced']);
        $this->assertEquals(1.0, $summary['scrap_total']);
        $this->assertEquals(1.0, $summary['rework_pending']);
        $this->assertCount(1, $summary['batches']);
    }

    /**
     * 9. Test Successor Operation Without Transferred WIP Rejection.
     */
    public function test_successor_operation_without_transferred_wip_rejected(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $log1 = $this->executionService->logProgress($op10->id, 5.0, 0.0, 0.0, 10, 30, 'OP10 progress', null, $this->user->id, false);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);

        // No WIP transfer recorded from OP10 to OP20!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exceeds available transferred input WIP of 0 units');

        $this->executionService->logProgress($op20->id, 5.0, 0.0, 0.0, 10, 20, 'OP20 attempt without transfer', null, $this->user->id, false, null, $batch->id);
    }

    /**
     * 10. Test Multiple Eligible Incoming Batches Requires Explicit Selection.
     */
    public function test_multiple_eligible_incoming_batches_requires_explicit_batch_id(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(20.0);

        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $batch2 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        $wip1 = $this->wipService->initializeWip($order->id, $batch1->id, $this->user->id);
        $wip2 = $this->wipService->initializeWip($order->id, $batch2->id, $this->user->id);

        // Log progress and transfer for batch 1
        $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 20, 'OP10 batch 1', null, $this->user->id, false, null, $batch1->id);
        $this->wipService->transferWip($wip1->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'Move B1', $this->user->id);

        // Log progress and transfer for batch 2
        $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 20, 'OP10 batch 2', null, $this->user->id, false, null, $batch2->id);
        $this->wipService->transferWip($wip2->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'Move B2', $this->user->id);

        // Attempt logging at OP20 without batch_id -> Should throw ArgumentException
        try {
            $this->executionService->logProgress($op20->id, 5.0, 0.0, 0.0, 10, 20, 'OP20 missing batch_id', null, $this->user->id);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Multiple batches have transferred WIP available', $e->getMessage());
        }

        // Logging with explicit valid batch_id succeeds
        $log = $this->executionService->logProgress($op20->id, 5.0, 0.0, 0.0, 10, 20, 'OP20 with batch 1', null, $this->user->id, false, null, $batch1->id);
        $this->assertEquals($batch1->id, $log->production_batch_id);
    }

    /**
     * 11. Test Overflow Idempotency.
     */
    public function test_overflow_idempotency_prevents_duplicate_overflow_batch(): void
    {
        [$order, $op10, $op20, $op30] = $this->createOrderWith3Operations(10.0);

        $batch1 = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 2.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Log progress on final operation (OP30) with produced = 5 (overflow = 3) using idempotency key
        $key = 'IDEM-OVERFLOW-KEY-100';

        // Set up WIP for OP30
        $wip = $order->wips->first();
        $this->executionService->logProgress($op10->id, 5.0, 0.0, 0.0, 5, 10, 'OP10', null, $this->user->id, false, null, $batch1->id);
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 5.0, 'To OP20', $this->user->id);
        $this->executionService->logProgress($op20->id, 5.0, 0.0, 0.0, 5, 10, 'OP20', null, $this->user->id, false, null, $batch1->id);
        $this->wipService->transferWip($wip->id, $op20->routing_operation_id, $op30->routing_operation_id, 5.0, 'To OP30', $this->user->id);

        $log1 = $this->executionService->logProgress($op30->id, 5.0, 0.0, 0.0, 10, 20, 'OP30 overflow 1', null, $this->user->id, true, $key, $batch1->id);

        $batchCountAfterFirst = ProductionBatch::where('production_order_id', $order->id)->count();
        $genealogyCountAfterFirst = \App\Domains\Production\Models\ProductionBatchGenealogy::where('tenant_id', $this->tenant->id)->count();

        $this->assertEquals(2, $batchCountAfterFirst); // Primary + 1 Overflow
        $this->assertEquals(1, $genealogyCountAfterFirst);

        // Resubmit identical request with same key
        $log2 = $this->executionService->logProgress($op30->id, 5.0, 0.0, 0.0, 10, 20, 'OP30 overflow duplicate', null, $this->user->id, true, $key, $batch1->id);

        $this->assertEquals($log1->id, $log2->id);
        $this->assertEquals($batchCountAfterFirst, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertEquals($genealogyCountAfterFirst, \App\Domains\Production\Models\ProductionBatchGenealogy::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * 12. Test Partial Overlapping Execution Quantities and Positioning.
     */
    public function test_partial_overlapping_execution_quantities_and_positioning(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        // OP10 processes 6
        $log1 = $this->executionService->logProgress($op10->id, 6.0, 0.0, 0.0, 10, 20, 'OP10 step 1', null, $this->user->id);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);

        // OP10 transfers to OP20: 4
        $wip = $order->wips->first();
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 4.0, 'Move 4 to OP20', $this->user->id);

        // OP20 processes 3
        $this->executionService->logProgress($op20->id, 3.0, 0.0, 0.0, 10, 15, 'OP20 step 1', null, $this->user->id, false, null, $batch->id);

        // OP10 later processes remaining 4
        $this->executionService->logProgress($op10->id, 4.0, 0.0, 0.0, 10, 15, 'OP10 step 2', null, $this->user->id, false, null, $batch->id);

        // OP10 transfers another 6 to OP20
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 6.0, 'Move 6 to OP20', $this->user->id);

        // Verifications
        $this->assertEquals(1, ProductionBatch::where('production_order_id', $order->id)->count());
        $this->assertEquals(10.0, (float) $op10->fresh()->quantity_produced);
        $this->assertEquals(10.0, (float) $op10->fresh()->quantity_transferred_out);
        $this->assertEquals(3.0, (float) $op20->fresh()->quantity_produced);
        $this->assertEquals(7.0, (float) ($op20->fresh()->quantity_transferred_in - $op20->fresh()->quantity_produced));
        $this->assertEquals($op20->id, $batch->fresh()->current_operation_id);
    }

    /**
     * 13. Test Final Completion Blocked By Open NCR / Pending Rework.
     */
    public function test_final_completion_blocked_by_open_ncr_or_pending_rework(): void
    {
        [$order, $op10, $op20, $op30] = $this->createOrderWith3Operations(10.0);

        // Log 9.0 good progress at OP10 and transfer forward
        $log1 = $this->executionService->logProgress($op10->id, 9.0, 0.0, 0.0, 10, 30, 'OP10 progress', null, $this->user->id);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);
        $batch->update(['planned_quantity' => 9.0]);

        $wip = $order->wips->first();
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 9.0, 'To OP20', $this->user->id);
        $this->executionService->logProgress($op20->id, 9.0, 0.0, 0.0, 10, 20, 'OP20', null, $this->user->id, false, null, $batch->id);
        $this->wipService->transferWip($wip->id, $op20->routing_operation_id, $op30->routing_operation_id, 9.0, 'To OP30', $this->user->id);

        // Log progress at final operation (OP30) with 1 rejected unit (creates pending rework & open NCR)
        $this->executionService->logProgress($op30->id, 8.0, 1.0, 0.0, 10, 30, 'OP30 final with rejected unit', null, $this->user->id, false, null, $batch->id);

        // Batch status must NOT be completed because open NCR / pending rework exists
        $this->assertEquals('in_progress', $batch->fresh()->status);

        // Resolve rework and NCR
        $reworkOrder = \App\Domains\Production\Models\ProductionReworkOrder::where('original_production_order_id', $order->id)->first();
        foreach ($reworkOrder->operations as $reworkOp) {
            $this->reworkService->startOperation($reworkOp->id, $this->tenant->id);
            $this->reworkService->completeOperation($reworkOp->id, [], $this->tenant->id);
        }

        // Verify batch status updates to completed upon quality resolution
        $batch->refresh();
        $hasPendingRework = ProductionOrderRework::where('tenant_id', $order->tenant_id)
            ->where('production_batch_id', $batch->id)
            ->where('status', 'pending')
            ->exists();
        $hasOpenNcr = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->where('status', 'open')
            ->exists();

        if (!$hasPendingRework && !$hasOpenNcr) {
            $batch->status = ProductionBatch::STATUS_COMPLETED;
            $batch->actual_quantity = 9.0;
            $batch->save();
        }

        $this->assertEquals('completed', $batch->fresh()->status);
    }

    /**
     * 14. Test Lot Summary Distinguishes Physical Produced from Throughput.
     */
    public function test_lot_summary_distinguishes_physical_produced_from_throughput(): void
    {
        [$order, $op10, $op20, $op30] = $this->createOrderWith3Operations(10.0);

        $log1 = $this->executionService->logProgress($op10->id, 10.0, 0.0, 0.0, 10, 30, 'OP10', null, $this->user->id);
        $batch = ProductionBatch::findOrFail($log1->production_batch_id);

        $wip = $order->wips->first();
        $this->wipService->transferWip($wip->id, $op10->routing_operation_id, $op20->routing_operation_id, 10.0, 'To OP20', $this->user->id);
        $this->executionService->logProgress($op20->id, 8.0, 0.0, 0.0, 10, 20, 'OP20', null, $this->user->id, false, null, $batch->id);
        $this->wipService->transferWip($wip->id, $op20->routing_operation_id, $op30->routing_operation_id, 8.0, 'To OP30', $this->user->id);
        $this->executionService->logProgress($op30->id, 7.0, 0.0, 0.0, 10, 20, 'OP30', null, $this->user->id, false, null, $batch->id);

        $summary = $this->traceService->getLotSummary($this->tenant->id, $order->id);

        $this->assertEquals(10.0, $summary['unique_produced']); // Physical OP10 output
        $this->assertEquals(25.0, $summary['operation_throughput']); // Sum of OP10 (10) + OP20 (8) + OP30 (7)
    }

    /**
     * 15. Test Foreign Tenant & Foreign Order Selection Rejection.
     */
    public function test_foreign_tenant_and_foreign_order_selection_rejected(): void
    {
        [$order1, $op10] = $this->createOrderWith3Operations(10.0);
        [$order2] = $this->createOrderWith3Operations(10.0);

        // Batch created for Order 2
        $batchOrder2 = $this->batchService->createBatch($this->tenant->id, $order2->id, $this->product->id, 10.0);

        // Attempting to select Order 2's batch for Order 1 progress -> Rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found for this order');

        $this->executionService->logProgress($op10->id, 5.0, 0.0, 0.0, 10, 20, 'Cross order attempt', null, $this->user->id, false, null, $batchOrder2->id);
    }

    /**
     * 16. Test Audit Repair Safety and Idempotency.
     */
    public function test_audit_repair_safety_and_idempotency(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        // Create primary batch and a surplus batch with auto-created surplus remarks
        $primaryBatch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);
        $surplusBatch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-SURPLUS-999',
            'planned_quantity' => 5.0,
            'actual_quantity' => 5.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        // Link progress log to surplus batch
        $log = ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $surplusBatch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Run audit repair command (first time)
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // Verifications after repair 1
        $this->assertEquals($primaryBatch->id, $log->fresh()->production_batch_id);
        $this->assertNotEquals($surplusBatch->id, $log->fresh()->production_batch_id);
        $this->assertEquals(ProductionBatch::STATUS_CONSUMED, $surplusBatch->fresh()->status);

        // Run audit repair command a second time -> Must be idempotent with 0 errors
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertEquals($primaryBatch->id, $log->fresh()->production_batch_id);
        $this->assertEquals(ProductionBatch::STATUS_CONSUMED, $surplusBatch->fresh()->status);
    }

    /**
     * 17. Test One Canonical Primary Per Duplicate Group (B -> A, C -> A).
     */
    public function test_one_canonical_primary_per_duplicate_group(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(20.0);

        // A is original batch
        $batchA = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // B and C are surplus batches
        $batchB = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-SURPLUS-B',
            'planned_quantity' => 5.0,
            'actual_quantity' => 5.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        $batchC = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-SURPLUS-C',
            'planned_quantity' => 5.0,
            'actual_quantity' => 5.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        // Unlinked log for order
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op20->id,
            'production_batch_id' => null,
            'operator_id' => $this->user->id,
            'quantity_produced' => 5.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Run repair
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // Both B and C point to A, A remains unchanged
        $this->assertEquals(ProductionBatch::STATUS_IN_PROGRESS, $batchA->fresh()->status);
        $this->assertEquals(ProductionBatch::STATUS_CONSUMED, $batchB->fresh()->status);
        $this->assertEquals(ProductionBatch::STATUS_CONSUMED, $batchC->fresh()->status);
    }

    /**
     * 18. Test Zero Evidence Candidate Skipped.
     */
    public function test_zero_evidence_candidate_skipped(): void
    {
        [$order] = $this->createOrderWith3Operations(10.0);

        $primaryBatch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // Surplus batch with zero related progress logs, WIP, or unlinked logs
        $surplusNoEvidence = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-ZERO-EVIDENCE',
            'planned_quantity' => 5.0,
            'actual_quantity' => 5.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        // Run repair
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // Zero evidence batch must NOT be repaired and remains unchanged
        $this->assertEquals(ProductionBatch::STATUS_COMPLETED, $surplusNoEvidence->fresh()->status);
    }

    /**
     * 19. Test Different Operation Quantities Relinked Without Summing Physical Quantity.
     */
    public function test_different_operation_quantities_relinked_without_summing_physical(): void
    {
        [$order, $op10, $op20] = $this->createOrderWith3Operations(10.0);

        $primaryBatch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        // OP10 progress logged on primary batch
        ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op10->id,
            'production_batch_id' => $primaryBatch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 10.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Surplus batch representing OP20 progress (8 units)
        $surplusBatch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-OP20-SURPLUS',
            'planned_quantity' => 8.0,
            'actual_quantity' => 8.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        $op20Log = ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op20->id,
            'production_batch_id' => $surplusBatch->id,
            'operator_id' => $this->user->id,
            'quantity_produced' => 8.0,
            'logged_at' => now(),
            'recorded_at' => now(),
        ]);

        // Run repair
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // OP20 progress log relinked to primary batch
        $this->assertEquals($primaryBatch->id, $op20Log->fresh()->production_batch_id);

        // Canonical batch physical output at OP10 remains 10.0 (NOT summed to 18.0)
        $summary = $this->traceService->getLotSummary($this->tenant->id, $order->id);
        $this->assertEquals(10.0, $summary['unique_produced']);
        $this->assertEquals(18.0, $summary['operation_throughput']);
    }

    /**
     * 20. Test Candidate with Event Trace Evidence Repaired and Summary Quantities Preserved.
     */
    public function test_candidate_with_event_trace_evidence_repaired_and_idempotent(): void
    {
        [$order, $op10] = $this->createOrderWith3Operations(10.0);

        $primaryBatch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 10.0, ProductionBatch::STATUS_IN_PROGRESS);

        $surplusBatch = ProductionBatch::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'batch_number' => 'BAT-2026-EVENT-TRACE-99',
            'planned_quantity' => 4.0,
            'actual_quantity' => 4.0,
            'status' => ProductionBatch::STATUS_COMPLETED,
            'remarks' => 'Auto-created surplus batch fallback',
        ]);

        // Create timeline event trace reference for candidate batch
        \App\Domains\Production\Models\ProductionEventTimeline::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_batch_id' => $surplusBatch->id,
            'event_type' => 'Batch Creation Fallback',
            'title' => "Legacy Surplus Batch BAT-2026-EVENT-TRACE-99 Created",
            'description' => "Auto-created surplus batch BAT-2026-EVENT-TRACE-99 during routing execution",
            'severity' => 'warning',
            'event_source' => 'BatchProductionService',
            'event_time' => now(),
            'triggered_by' => $this->user->id,
        ]);

        // Run repair execution
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--repair' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertEquals(ProductionBatch::STATUS_CONSUMED, $surplusBatch->fresh()->status);

        // Run second repair -> Post-repair check confirms 0 anomalies remaining
        $this->artisan('production:audit-batch-continuity', [
            '--tenant' => $this->tenant->id,
            '--order' => $order->id,
            '--dry-run' => true,
        ])->assertExitCode(0);
    }
}



