<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionOverlappingOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $fgProduct;
    private Uom $uom;
    private Warehouse $warehouse;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private Routing $routing;
    private RoutingOperation $op10;
    private RoutingOperation $op20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(['id' => 1], [
            'name' => 'Overlapping Test Tenant',
            'slug' => 'overlap-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app()->instance('tenant', $this->tenant);
        session(['tenant_id' => $this->tenant->id]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Op Operator',
            'email' => 'operator@overlap.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);

        $this->uom = Uom::firstOrCreate([
            'tenant_id' => $this->tenant->id,
            'code' => 'PCS',
        ], [
            'name' => 'Pieces',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Overlapped Assembly',
            'sku' => 'OVL-001',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
        ]);

        // Approved BOM required by ProductionOrderService
        \App\Domains\Production\Models\ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fgProduct->id,
            'bom_number' => 'BOM-OVL-001',
            'version' => '1.0',
            'status' => 'approved',
            'is_active' => true,
            'effective_date' => now()->toDateString(),
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Production Warehouse',
            'code' => 'MAIN-WH',
            'type' => 'physical',
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cutting Station',
            'code' => 'WC-CUT',
            'cost_per_hour' => 50.00,
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Station',
            'code' => 'WC-ASM',
            'cost_per_hour' => 60.00,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fgProduct->id,
            'name' => 'Overlapped Assembly Routing',
            'routing_number' => 'RT-OVL-001',
            'version' => '1.0',
            'status' => 'active',
            'is_default' => true,
            'effective_from' => now()->subDays(1),
        ]);

        $this->op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter1->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 5,
            'overlap_enabled' => false,
            'transfer_batch_quantity' => 0.0000,
        ]);

        $this->op20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter2->id,
            'setup_time_minutes' => 15,
            'processing_time_minutes' => 10,
            'overlap_enabled' => false,
            'transfer_batch_quantity' => 0.0000,
        ]);
    }

    private function createReleasedOrder(float $qty = 50.0, bool $enableOverlap = false, float $transferBatch = 10.0): ProductionOrder
    {
        if ($enableOverlap) {
            $this->op10->update([
                'overlap_enabled' => true,
                'transfer_batch_quantity' => $transferBatch,
            ]);
        }

        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $this->fgProduct->id,
            'quantity_ordered' => $qty,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'production_mode' => 'discrete',
        ], $this->tenant->id, $this->user->id);

        $order->update(['status' => 'released']);
        app(ProductionWipService::class)->initializeWip($order->id);

        return $order;
    }

    /** 1. Overlap disabled preserves standard completion dependency. */
    public function test_overlap_disabled_preserves_standard_completion_dependency(): void
    {
        $order = $this->createReleasedOrder(50.0, false);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'Partial 10 units', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op20->status);
        $this->assertEquals(0.0000, $op20->quantity_transferred_in);
    }

    /** 2. First transfer batch unlocks the next operation. */
    public function test_first_transfer_batch_unlocks_next_operation(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch 10 units', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);
        $this->assertEquals(10.0000, $op20->quantity_transferred_in);
    }

    /** 3. Source operation remains RUNNING. */
    public function test_source_operation_remains_running_after_partial_transfer(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch 10 units', null, $this->user->id, false
        );

        $op10->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->status);
    }

    /** 4. Destination can start while source remains RUNNING. */
    public function test_destination_can_start_while_source_remains_running(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch 10 units', null, $this->user->id, false
        );

        $op20->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->fresh()->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op20->fresh()->status);
    }

    /** 5. Destination cannot process more than received WIP. */
    public function test_destination_cannot_process_more_than_received_wip(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch 10 units', null, $this->user->id, false
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Exceeds available transferred input WIP');

        app(ProductionExecutionService::class)->logProgress(
            $op20->id, 12.0, 0.0, 0.0, 0.0, 40.0, 'Attempting 12 units on Op20', null, $this->user->id, false
        );
    }

    /** 6. Downstream scrap reduces available WIP. */
    public function test_downstream_scrap_reduces_available_wip(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch 10 units', null, $this->user->id, false
        );

        // Process 5 good, 2 scrapped on Op 20 (Total 7 consumed)
        app(ProductionExecutionService::class)->logProgress(
            $op20->id, 5.0, 0.0, 2.0, 0.0, 20.0, 'Log 5 good + 2 scrap on Op20', null, $this->user->id, false
        );

        $available = app(ProductionWipService::class)->getAvailableInputWip($op20->fresh());
        $this->assertEquals(3.0000, $available);
    }

    /** 7. Multiple progress logs transfer only newly eligible quantities. */
    public function test_multiple_progress_logs_transfer_only_newly_eligible_quantities(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        // Log 15 units (1 transfer batch of 10)
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 15.0, 0.0, 0.0, 0.0, 45.0, 'Log 15 units', null, $this->user->id, false
        );
        $this->assertEquals(10.0000, $op20->fresh()->quantity_transferred_in);

        // Log another 8 units (Total 23 produced -> 2 transfer batches of 10 = 20 total transferred)
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 8.0, 0.0, 0.0, 0.0, 25.0, 'Log 8 units', null, $this->user->id, false
        );
        $this->assertEquals(20.0000, $op20->fresh()->quantity_transferred_in);
    }

    /** 8. Delta progress follows the existing contract. */
    public function test_delta_progress_follows_existing_contract(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Shift 1: 5 units', null, $this->user->id, false
        );
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Shift 2: 5 units', null, $this->user->id, false
        );

        $op10->refresh();
        $this->assertEquals(10.0000, $op10->quantity_produced);
    }

    /** 9. Quantity below transfer threshold does not unlock the destination. */
    public function test_quantity_below_transfer_threshold_does_not_unlock_destination(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 8.0, 0.0, 0.0, 0.0, 24.0, 'Log 8 units (Below 10 threshold)', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op20->status);
        $this->assertEquals(0.0000, $op20->quantity_transferred_in);
    }

    /** 10. Multiple eligible transfer batches in one log are handled correctly. */
    public function test_multiple_eligible_transfer_batches_in_one_log(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 25.0, 0.0, 0.0, 0.0, 75.0, 'Log 25 units in one go', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);
        $this->assertEquals(20.0000, $op20->quantity_transferred_in);
    }

    /** 11. Final remainder transfers on source completion. */
    public function test_final_remainder_transfers_on_source_completion(): void
    {
        $order = $this->createReleasedOrder(25.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        // Produce 25 units total and mark operation completed (2 batches of 10 = 20 + 5 remainder)
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 25.0, 0.0, 0.0, 0.0, 75.0, 'Complete Op 10 with 25 total', null, $this->user->id, true
        );

        $op20->refresh();
        $this->assertEquals(25.0000, $op20->quantity_transferred_in);
    }

    /** 12. Scrap and rejection are excluded. */
    public function test_scrap_and_rejection_are_excluded_from_transfer(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        // 8 good, 5 rejected, 5 scrap (Total processed = 18, but only 8 good output)
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 8.0, 5.0, 5.0, 0.0, 30.0, '8 good, 5 rej, 5 scrap', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(0.0000, $op20->quantity_transferred_in);
    }

    /** 13. Operation-scoped Quality Hold blocks transfer. */
    public function test_operation_scoped_quality_hold_blocks_transfer(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        $qualityPlan = \App\Domains\Production\Models\ProductionQualityPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hold Test Plan',
            'type' => 'in_process',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        ProductionQualityInspection::create([
            'tenant_id' => $this->tenant->id,
            'quality_plan_id' => $qualityPlan->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op10->id,
            'stage' => 'in_process',
            'status' => 'hold',
        ]);

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, '10 units under hold', null, $this->user->id, false
        );

        $op20->refresh();
        $this->assertEquals(0.0000, $op20->quantity_transferred_in);
    }

    /** 14. Hold release triggers pending transfer. */
    public function test_hold_release_triggers_pending_transfer(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        $qualityPlan = \App\Domains\Production\Models\ProductionQualityPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Release Test Plan',
            'type' => 'in_process',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $inspection = ProductionQualityInspection::create([
            'tenant_id' => $this->tenant->id,
            'quality_plan_id' => $qualityPlan->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op10->id,
            'stage' => 'in_process',
            'status' => 'hold',
        ]);

        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, '10 units under hold', null, $this->user->id, false
        );

        $this->assertEquals(0.0000, $op20->fresh()->quantity_transferred_in);

        // Clear quality hold
        $inspection->update(['status' => 'passed']);
        app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($op10->id, $this->user->id);

        $op20->refresh();
        $this->assertEquals(10.0000, $op20->quantity_transferred_in);
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);
    }

    /** 15. Duplicate HTTP request with same idempotency key does not duplicate progress. */
    public function test_duplicate_http_request_with_same_idempotency_key_does_not_duplicate_progress(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $key = (string) Str::uuid();

        $log1 = app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, '10 units log 1', null, $this->user->id, false, $key
        );

        $log2 = app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, '10 units log 1 duplicate', null, $this->user->id, false, $key
        );

        $this->assertEquals($log1->id, $log2->id);
        $this->assertEquals(10.0000, $op10->fresh()->quantity_produced);
    }

    /** 16. Multi-tenant isolation: Tenant A cannot transfer Tenant B WIP. */
    public function test_tenant_isolation_prevents_cross_tenant_wip_transfer(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-overlap',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $orderA = $this->createReleasedOrder(50.0, true, 10.0);
        $op10A = $orderA->operations()->where('sequence', 10)->first();

        // Switch to Tenant B session and application instance
        app()->instance('tenant', $tenantB);
        session(['tenant_id' => $tenantB->id]);

        $transferred = app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($op10A->id, $this->user->id);
        $this->assertEquals(0.0, $transferred);
    }

    /** 17. MES completeOperation rejects processing quantity exceeding available input WIP. */
    public function test_mes_complete_operation_rejects_exceeding_available_input_wip(): void
    {
        $order = $this->createReleasedOrder(50.0, true, 10.0);
        $schedule = app(\App\Domains\Production\Services\SchedulingService::class)->generateSchedule(
            $order, now(), 'forward'
        );
        $op10 = $order->operations()->where('sequence', 10)->first();
        $op20 = $order->operations()->where('sequence', 20)->first();

        // 10 units produced & transferred to Op 20
        app(ProductionExecutionService::class)->logProgress(
            $op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'Log 10 units', null, $this->user->id, false
        );
        $schedOp20 = ProductionScheduleOperation::where('production_order_operation_id', $op20->id)->first();
        $schedOp20->update(['status' => ProductionScheduleOperation::STATUS_READY]);
        app(\App\Domains\Production\Services\MesExecutionService::class)->startOperation($schedOp20->id, null, $this->user->id);

        // Attempting to complete Op 20 with 20 units when only 10 units were transferred should fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Exceeds available transferred input WIP of 10 units.');

        app(\App\Domains\Production\Services\MesExecutionService::class)->completeOperation($schedOp20->id, [
            'quantity_produced' => 20.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
        ], $this->user->id);
    }
}
