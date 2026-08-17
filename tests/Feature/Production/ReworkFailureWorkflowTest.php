<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\ReworkService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReworkFailureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private ProductionOrder $order;
    private WorkCenter $workCenter;
    private RoutingOperation $rOp1;
    private RoutingOperation $rOp2;
    private ProductionOrderOperation $op1;
    private ProductionOrderOperation $op2;
    private ProductionBatch $batch;
    private ProductionNcr $ncr;
    private ProductionReworkOrder $reworkOrder;
    private ReworkService $reworkService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Rework Test Tenant',
            'slug' => 'rework-test-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'QA Inspector',
            'email' => 'qa@reworktest-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($this->user);

        $uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Units', 'code' => 'PCS']);
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision Assembly Component',
            'sku' => 'PRD-RWK-' . uniqid(),
            'uom_id' => $uom->id,
            'unit_cost' => 25.00,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-RWK-' . uniqid(),
            'product_id' => $this->product->id,
            'quantity_ordered' => 10.00,
            'quantity_produced' => 0.00,
            'quantity_rejected' => 0.00,
            'quantity_scrapped' => 0.00,
            'status' => 'released',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'due_date' => now()->addDays(5),
            'created_by' => $this->user->id,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-RWK',
            'name' => 'Quality & Assembly Center',
            'status' => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-RWK-' . uniqid(),
            'name' => 'Rework Test Routing',
            'product_id' => $this->product->id,
            'status' => 'approved',
        ]);

        $this->rOp1 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Machining',
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->rOp2 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Finishing',
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'routing_operation_id' => $this->rOp1->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Machining',
            'status' => 'running',
            'quantity_produced' => 0.00,
            'quantity_rejected' => 0.00,
            'quantity_scrapped' => 0.00,
        ]);

        $this->op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'routing_operation_id' => $this->rOp2->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Finishing',
            'status' => 'waiting',
            'quantity_produced' => 0.00,
            'quantity_rejected' => 0.00,
            'quantity_scrapped' => 0.00,
        ]);

        app(ProductionWipService::class)->initializeWip($this->order->id);

        $batchService = app(BatchProductionService::class);
        $this->batch = $batchService->createBatch($this->tenant->id, $this->order->id, $this->product->id, 10.00, $this->op1->id);

        $executionService = app(ProductionExecutionService::class);

        // OP-10: Log 8 Good, 2 Rejected -> Creates ProductionOrderRework record (2 pending)
        $executionService->logProgress(
            $this->op1->id,
            8.00,
            2.00,
            0.00,
            0,
            0,
            'OP-10',
            null,
            $this->user->id,
            true,
            null,
            $this->batch->id
        );

        $this->ncr = ProductionNcr::create([
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-TEST-' . uniqid(),
            'category' => 'process',
            'status' => 'open',
            'disposition_type' => 'rework',
            'production_order_id' => $this->order->id,
            'production_order_operation_id' => $this->op1->id,
            'batch_id' => $this->batch->id,
            'quantity' => 2.00,
            'description' => 'Dimension out of tolerance',
        ]);

        $this->reworkService = app(ReworkService::class);
        $this->reworkOrder = $this->reworkService->createReworkOrder($this->tenant->id, $this->ncr->id, [
            'original_production_order_id' => $this->order->id,
            'work_center_id' => $this->workCenter->id,
            'cost_estimate' => 100.00,
        ]);
    }

    /** Test 1: Failing rework changes status to failed */
    public function test_failing_rework_changes_status_to_failed(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Part cracked during rework attempt']);

        $this->reworkOrder->refresh();
        $this->assertEquals('failed', $this->reworkOrder->status);
    }

    /** Test 2: Rejected quantity decreases correctly */
    public function test_rejected_quantity_decreases_correctly(): void
    {
        $this->op1->refresh();
        $this->assertEquals(2.00, $this->op1->quantity_rejected);

        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Structural failure']);

        $this->op1->refresh();
        $this->order->refresh();
        $this->assertEquals(0.00, $this->op1->quantity_rejected);
        $this->assertEquals(0.00, $this->order->quantity_rejected);
    }

    /** Test 3: Scrap quantity increases correctly */
    public function test_scrap_quantity_increases_correctly(): void
    {
        $this->op1->refresh();
        $this->assertEquals(0.00, $this->op1->quantity_scrapped);

        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Unrecoverable defect']);

        $this->op1->refresh();
        $this->order->refresh();
        $this->assertEquals(2.00, $this->op1->quantity_scrapped);
        $this->assertEquals(2.00, $this->order->quantity_scrapped);
    }

    /** Test 4: Produced quantity does NOT increase on failed rework */
    public function test_produced_quantity_does_not_increase(): void
    {
        $this->op1->refresh();
        $initialProduced = $this->op1->quantity_produced;

        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Defect unrepairable']);

        $this->op1->refresh();
        $this->assertEquals($initialProduced, $this->op1->quantity_produced);
    }

    /** Test 5: Available WIP does NOT increase on failed rework */
    public function test_available_wip_does_not_increase(): void
    {
        $wip = ProductionWip::where('production_order_id', $this->order->id)->where('production_batch_id', $this->batch->id)->first();
        $initialAvailable = $wip->available_quantity;

        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Scrapped in rework']);

        $wip->refresh();
        $this->assertEquals($initialAvailable, $wip->available_quantity);
        $this->assertEquals(0.00, $wip->rejected_quantity);
        $this->assertEquals(2.00, $wip->scrap_quantity);
    }

    /** Test 6: ProductionOrderScrap record is created correctly */
    public function test_production_order_scrap_is_created(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Thermal stress fracture']);

        $scrap = ProductionOrderScrap::where('production_order_id', $this->order->id)->first();
        $this->assertNotNull($scrap);
        $this->assertEquals(2.00, $scrap->quantity);
        $this->assertEquals($this->op1->id, $scrap->production_order_operation_id);
    }

    /** Test 7: ProductionWipTransaction is created with rework_failed_scrapped type */
    public function test_wip_transaction_logged_with_rework_failed_scrapped_type(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Tool clearance failure']);

        $tx = ProductionWipTransaction::where('production_order_id', $this->order->id)
            ->where('transaction_type', 'rework_failed_scrapped')
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(2.00, $tx->quantity);
        $this->assertEquals(0.00, $tx->good_quantity);
        $this->assertEquals(2.00, $tx->scrap_quantity);
        $this->assertEquals(-2.00, $tx->rework_quantity);
    }

    /** Test 8: Linked NCR resolves with scrap disposition */
    public function test_linked_ncr_resolves_with_scrap_disposition(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'QC Fail']);

        $this->ncr->refresh();
        $this->assertEquals('closed', $this->ncr->status);
        $this->assertEquals('scrap', $this->ncr->disposition_type);
    }

    /** Test 9 & 10: Production batch ID remains unchanged and scrap belongs to batch */
    public function test_batch_id_remains_unchanged_and_scrap_belongs_to_batch(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Scrap verification']);

        $scrap = ProductionOrderScrap::where('production_order_id', $this->order->id)->first();
        $this->assertEquals($this->batch->id, $scrap->production_batch_id);

        $tx = ProductionWipTransaction::where('production_order_id', $this->order->id)
            ->where('transaction_type', 'rework_failed_scrapped')
            ->first();
        $this->assertEquals($this->batch->id, $tx->production_batch_id);
    }

    /** Test 11: Double fail request does not duplicate accounting (idempotency) */
    public function test_double_fail_request_is_idempotent(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'First attempt']);
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Second attempt']);

        $scrapsCount = ProductionOrderScrap::where('production_order_id', $this->order->id)->count();
        $this->assertEquals(1, $scrapsCount);

        $this->op1->refresh();
        $this->assertEquals(2.00, $this->op1->quantity_scrapped);
    }

    /** Test 12: Rework cannot fail after successful completion */
    public function test_cannot_fail_rework_after_successful_completion(): void
    {
        $opFirst = $this->reworkOrder->operations->first();
        $opLast = $this->reworkOrder->operations->last();

        $this->reworkService->startOperation($opFirst->id, $this->tenant->id);
        $this->reworkService->completeOperation($opFirst->id, ['setup_time_actual' => 10], $this->tenant->id);

        $this->reworkService->startOperation($opLast->id, $this->tenant->id);
        $this->reworkService->completeOperation($opLast->id, ['setup_time_actual' => 15], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Invalid attempt']);
    }

    /** Test 13: Rework cannot fail after cancellation */
    public function test_cannot_fail_rework_after_cancellation(): void
    {
        $this->reworkOrder->update(['status' => 'cancelled']);

        $this->expectException(\InvalidArgumentException::class);
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Invalid attempt']);
    }

    /** Test 16: Tenant A cannot fail Tenant B's rework */
    public function test_tenant_isolation_prevents_failing_other_tenant_rework(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-' . uniqid(), 'status' => 'active', 'plan' => 'enterprise']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Cross tenant fail'], $otherTenant->id);
    }

    /** Test 18 & 19: Pending rework hold is removed and scrap cannot transfer */
    public function test_pending_rework_hold_removed_and_scrap_cannot_transfer(): void
    {
        $this->assertTrue(ProductionOrderRework::where('production_batch_id', $this->batch->id)->where('status', 'pending')->exists());

        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Fail rework']);

        $this->assertFalse(ProductionOrderRework::where('production_batch_id', $this->batch->id)->where('status', 'pending')->exists());

        // Good transferred in at OP-20 should be exactly 8 (not 10)
        $wip2 = ProductionWip::where('production_order_id', $this->order->id)
            ->where('production_batch_id', $this->batch->id)
            ->where('current_routing_operation_id', $this->rOp2->id)
            ->first();
        $this->assertEquals(8.00, $wip2->available_quantity);

        $batchService = app(BatchProductionService::class);
        $queue = $batchService->getOperationBatchQueue($this->op1);
        $item = collect(array_merge($queue['active'], $queue['completed'], $queue['waiting_transfer']))->firstWhere('batch.id', $this->batch->id);
        $this->assertNotNull($item);
        $this->assertEquals(0.00, $item['pending_rejected_at_operation']);
        $this->assertNotEquals('REWORK', $item['display_status']);
    }

    /** Test 20: Batch completion works correctly when final unresolved rework is converted to scrap */
    public function test_batch_reconciles_cleanly_when_rework_is_scrapped(): void
    {
        $this->reworkService->failRework($this->reworkOrder->id, ['reason' => 'Batch scrap resolution']);

        $pendingRework = ProductionOrderRework::where('production_batch_id', $this->batch->id)->where('status', 'pending')->count();
        $scrappedQty = ProductionOrderScrap::where('production_order_id', $this->order->id)->where('production_batch_id', $this->batch->id)->sum('quantity');

        $this->assertEquals(0, $pendingRework);
        $this->assertEquals(2.00, $scrappedQty);
    }
}
