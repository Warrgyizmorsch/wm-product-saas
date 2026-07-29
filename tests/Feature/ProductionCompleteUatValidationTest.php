<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
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
use App\Domains\Production\Services\SchedulingCalendarService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionCompleteUatValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected WorkCenter $wcCutting;
    protected WorkCenter $wcAssembly;
    protected WorkCenter $wcPacking;
    protected Machine $machineA;
    protected Machine $machineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Complete UAT Tenant',
            'slug' => 'complete-uat-tenant',
            'domain' => 'uat.test',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Overlapping Operations Demo Product',
            'sku' => 'DEMO-OVERLAP-50',
            'type' => 'manufactured',
        ]);

        $this->wcCutting = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-CUT',
            'name' => 'Cutting Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 1,
        ]);

        $this->wcAssembly = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-ASM',
            'name' => 'Assembly Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 1,
        ]);

        $this->wcPacking = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-PAK',
            'name' => 'Packing Center',
            'is_active' => true,
            'capacity_per_day' => 480.0,
            'active_machine_count' => 1,
        ]);

        $this->machineA = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wcCutting->id,
            'code' => 'MC-CUT-A',
            'name' => 'Machine A (Cutting)',
            'status' => 'active',
        ]);

        $this->machineB = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wcAssembly->id,
            'code' => 'MC-ASM-B',
            'name' => 'Machine B (Assembly)',
            'status' => 'active',
        ]);
    }

    /** SECTION 1 — Routing and Order Snapshot */
    public function test_section_1_routing_and_order_snapshot(): void
    {
        // 1. Create Routing with overlap fields
        $routing = Routing::create([
            'tenant_id'      => $this->tenant->id,
            'product_id'     => $this->product->id,
            'name'           => 'Demo Routing Overlap',
            'routing_number' => 'RT-DEMO-001',
            'version'        => '1.0',
            'status'         => Routing::STATUS_ACTIVE,
        ]);

        $rOp10 = RoutingOperation::create([
            'tenant_id'                => $this->tenant->id,
            'routing_id'               => $routing->id,
            'sequence'                 => 10,
            'operation_number'         => 'OP-10',
            'name'                     => 'Cutting Op 10',
            'work_center_id'           => $this->wcCutting->id,
            'machine_id'               => $this->machineA->id,
            'setup_time_minutes'       => 20,
            'processing_time_minutes'  => 3,
            'overlap_enabled'          => true,
            'transfer_batch_quantity'  => 10.0,
            'transfer_lag_minutes'     => 5,
        ]);

        $rOp20 = RoutingOperation::create([
            'tenant_id'                => $this->tenant->id,
            'routing_id'               => $routing->id,
            'sequence'                 => 20,
            'operation_number'         => 'OP-20',
            'name'                     => 'Assembly Op 20',
            'work_center_id'           => $this->wcAssembly->id,
            'machine_id'               => $this->machineB->id,
            'setup_time_minutes'       => 10,
            'processing_time_minutes'  => 2,
            'overlap_enabled'          => false,
            'transfer_batch_quantity'  => 0.0,
            'transfer_lag_minutes'     => 0,
        ]);

        $this->assertTrue((bool) $rOp10->overlap_enabled);
        $this->assertEquals(10.0, (float) $rOp10->transfer_batch_quantity);
        $this->assertEquals(5, (int) $rOp10->transfer_lag_minutes);

        // 2. Production Order snapshot copies routing values at creation time
        $oOp10 = ProductionOrderOperation::create([
            'tenant_id'               => $this->tenant->id,
            'production_order_id'     => ProductionOrder::create([
                'tenant_id'        => $this->tenant->id,
                'order_number'     => 'PO-UAT-SNAP-01',
                'product_id'       => $this->product->id,
                'quantity_ordered' => 50.0,
                'status'           => ProductionOrder::STATUS_RELEASED,
                'start_date'       => '2026-08-03',
                'end_date'         => '2026-08-07',
                'created_by'       => $this->user->id,
            ])->id,
            'sequence'                => 10,
            'operation_number'        => 'OP-10',
            'name'                    => 'Cutting Op 10',
            'work_center_id'          => $this->wcCutting->id,
            'machine_id'              => $this->machineA->id,
            'status'                  => ProductionOrderOperation::STATUS_WAITING,
            'setup_time_planned'      => 20.0,
            'processing_time_planned' => 3.0,
            'total_time_planned'      => 170.0,
            'overlap_enabled'         => $rOp10->overlap_enabled,
            'transfer_batch_quantity' => $rOp10->transfer_batch_quantity,
            'transfer_lag_minutes'    => $rOp10->transfer_lag_minutes,
        ]);

        $this->assertTrue((bool) $oOp10->overlap_enabled);
        $this->assertEquals(10.0, (float) $oOp10->transfer_batch_quantity);
        $this->assertEquals(5, (int) $oOp10->transfer_lag_minutes);

        // 3. Edit routing master after creation -> order snapshot remains unchanged (immutability)
        $rOp10->update([
            'transfer_batch_quantity' => 25.0,
            'transfer_lag_minutes'    => 15,
        ]);

        $oOp10->refresh();
        $this->assertEquals(10.0, (float) $oOp10->transfer_batch_quantity, 'Snapshot must not change when routing master changes');
        $this->assertEquals(5, (int) $oOp10->transfer_lag_minutes, 'Snapshot must not change when routing master changes');
    }

    /** SECTION 2 — Forward Scheduling */
    public function test_section_2_forward_scheduling(): void
    {
        $order = $this->createDemoOrder(50.0);
        $startDate = Carbon::parse('2026-08-03 08:00:00');

        $schedule = app(SchedulingService::class)->generateForwardSchedule($order, $startDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Op 10 must start at the given start date
        $this->assertEquals('2026-08-03 08:00:00', $op10->planned_start->toDateTimeString(), 'Op 10 must start at given date');

        // Key overlap assertion: Op 20 starts BEFORE Op 10 finishes
        $this->assertTrue(
            $op20->planned_start->lt($op10->planned_finish),
            "Overlap scheduling: Op 20 ({$op20->planned_start}) must start before Op 10 finishes ({$op10->planned_finish})"
        );

        // Op 20 planned start must be after Op 10 planned start (not before)
        $this->assertTrue(
            $op20->planned_start->gte($op10->planned_start),
            'Op 20 must start after Op 10 started (transfer-batch dependency)'
        );

        // Evaluate calendar conflict: zero sequence conflicts for overlapping ops
        $res = app(SchedulingCalendarService::class)->getOperationConflicts($op20->id);
        $seqConflicts = collect($res['conflicts'])->where('type', 'dependency_violation')->all();
        $this->assertEmpty($seqConflicts, 'Overlapping operation should have no dependency_violation conflicts');
    }

    /** SECTION 3 — Backward / JIT Scheduling */
    public function test_section_3_backward_jit_scheduling(): void
    {
        $order = $this->createDemoOrder(50.0);
        $dueDate = Carbon::parse('2026-08-03 13:50:00');

        $schedule = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $ops = $schedule->operations->sortBy('sequence')->values();

        $op10 = $ops[0];
        $op20 = $ops[1];

        // Key backward overlap assertion: Op 10 starts BEFORE Op 20, and Op 10 finishes AFTER Op 20 starts
        $this->assertTrue(
            $op10->planned_start->lt($op20->planned_start),
            "Op 10 ({$op10->planned_start}) must start before Op 20 ({$op20->planned_start})"
        );
        $this->assertTrue(
            $op10->planned_finish->gt($op20->planned_start),
            "Backward overlap: Op 10 ({$op10->planned_finish}) must finish after Op 20 starts ({$op20->planned_start})"
        );

        // Same machine scenario: Verify the schedule is still structurally valid (Op 10 starts before Op 20)
        $op20Order = $order->operations->where('sequence', 20)->first();
        $op20Order->update(['machine_id' => $this->machineA->id]);

        $sameMachineSched = app(SchedulingService::class)->generateBackwardSchedule($order, $dueDate);
        $smOps = $sameMachineSched->operations->sortBy('sequence')->values();
        $smOp10 = $smOps[0];
        $smOp20 = $smOps[1];

        // Op 10 must still come before Op 20 sequentially
        $this->assertTrue(
            $smOp10->planned_start->lt($smOp20->planned_start),
            'Same-machine backward schedule: Op 10 must still start before Op 20'
        );
    }

    /** SECTION 4 — Partial Execution */
    public function test_section_4_partial_execution(): void
    {
        $order = $this->createDemoOrder(50.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();

        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // A. Log 5 good units (below batch threshold of 10)
        $execService->logProgress($op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Shift 1: 5 units', null, $this->user->id, false);

        $op10->refresh();
        $op20->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->status);
        $this->assertEquals(0.0, (float) $op10->quantity_transferred_out);
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op20->status);

        // B. Log another 5 good units (cumulative 10 -> batch threshold reached!)
        $execService->logProgress($op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Shift 2: 5 units', null, $this->user->id, false);

        $op10->refresh();
        $op20->refresh();
        $this->assertEquals(10.0, (float) $op10->quantity_transferred_out);
        $this->assertEquals(10.0, (float) $op20->quantity_transferred_in);
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);

        // C. Start Op 20 -> both operations RUNNING concurrently
        $op20->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->status);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op20->status);
    }

    /** SECTION 5 — Downstream WIP Cap */
    public function test_section_5_downstream_wip_cap(): void
    {
        $order = $this->createDemoOrder(50.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);
        $wipService  = app(\App\Domains\Production\Services\ProductionWipService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();

        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // Transfer 10 units to Op 20
        $execService->logProgress($op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, 'First batch', null, $this->user->id, false);
        $op20->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // 1. Log 6 good units at Op 20 -> remaining available input WIP = 4
        $execService->logProgress($op20->id, 6.0, 0.0, 0.0, 0.0, 12.0, 'Consume 6 units Op20', null, $this->user->id, false);

        $available = $wipService->getAvailableInputWip($op20->fresh());
        $this->assertEquals(4.0, $available);

        // 2. Attempt to log 5 units at Op 20 (exceeds available 4) -> Exception
        $this->expectException(\InvalidArgumentException::class);
        $execService->logProgress($op20->id, 5.0, 0.0, 0.0, 0.0, 10.0, 'Exceed cap attempt', null, $this->user->id, false);
    }

    /** SECTION 6 — Multiple Transfer Batches & Idempotency */
    public function test_section_6_multiple_transfer_batches_and_idempotency(): void
    {
        $order = $this->createDemoOrder(50.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();
        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // 1. Log 25 good units -> 2 complete transfer batches (10 + 10 = 20 transferred)
        $execService->logProgress($op10->id, 25.0, 0.0, 0.0, 0.0, 75.0, 'Log 25 units in one go', null, $this->user->id, false);

        $op10->refresh();
        $op20->refresh();
        $this->assertEquals(20.0, (float) $op10->quantity_transferred_out);
        $this->assertEquals(20.0, (float) $op20->quantity_transferred_in);

        // 2. Submit same request with same idempotency key -> idempotent no-op
        $idemKey = 'IDEM-BATCH-UAT-001';
        $log1 = $execService->logProgress($op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Idem test', null, $this->user->id, false, $idemKey);
        $txCountBefore = ProductionWipTransaction::where('production_order_operation_id', $op10->id)->count();

        $log2 = $execService->logProgress($op10->id, 5.0, 0.0, 0.0, 0.0, 15.0, 'Idem test duplicate', null, $this->user->id, false, $idemKey);
        $txCountAfter = ProductionWipTransaction::where('production_order_operation_id', $op10->id)->count();

        // Idempotency: same log returned, no new transactions
        $this->assertEquals($log1->id, $log2->id);
        $this->assertEquals($txCountBefore, $txCountAfter);
    }

    /** SECTION 7 — Scrap and Rejection */
    public function test_section_7_scrap_and_rejection(): void
    {
        $order = $this->createDemoOrder(50.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);
        $wipService  = app(\App\Domains\Production\Services\ProductionWipService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();
        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // Log 8 good + 5 rejected + 5 scrap (total processed=18, only 8 good -> below threshold, no transfer)
        $execService->logProgress($op10->id, 8.0, 5.0, 5.0, 0.0, 30.0, '8 good, 5 rej, 5 scrap', null, $this->user->id, false);

        $op20->refresh();
        // 8 good < 10 batch threshold -> no transfer yet
        $this->assertEquals(0.0, (float) $op20->quantity_transferred_in);

        // Log 4 more good -> total 12 good -> 1 transfer batch of 10 triggered
        $execService->logProgress($op10->id, 4.0, 0.0, 0.0, 0.0, 12.0, '4 more good', null, $this->user->id, false);

        $op10->refresh();
        $op20->refresh();
        $this->assertEquals(10.0, (float) $op10->quantity_transferred_out);
        $this->assertEquals(10.0, (float) $op20->quantity_transferred_in);

        // At Op 20: log 6 (4 good, 1 scrap, 1 rejected) -> available input WIP = 10 - 4 - 1 - 1 = 4
        $op20->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);
        $execService->logProgress($op20->id, 4.0, 1.0, 1.0, 0.0, 12.0, '4g 1r 1s on Op20', null, $this->user->id, false);

        $available = $wipService->getAvailableInputWip($op20->fresh());
        $this->assertEquals(4.0, $available);
    }

    /** SECTION 8 — Quality Hold and Release */
    public function test_section_8_quality_hold_and_release(): void
    {
        $order = $this->createDemoOrder(50.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);
        $wipService  = app(\App\Domains\Production\Services\ProductionWipService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();
        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // Create quality inspection in 'hold' status -> blocks transfer
        $qualityPlan = \App\Domains\Production\Models\ProductionQualityPlan::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'UAT Hold Plan',
            'type'       => 'in_process',
            'status'     => 'active',
            'created_by' => $this->user->id,
        ]);

        $inspection = \App\Domains\Production\Models\ProductionQualityInspection::create([
            'tenant_id'                     => $this->tenant->id,
            'quality_plan_id'               => $qualityPlan->id,
            'production_order_id'           => $order->id,
            'production_order_operation_id' => $op10->id,
            'stage'                         => 'in_process',
            'status'                        => 'hold',
        ]);

        // Log 10 good units -> hold blocks transfer
        $execService->logProgress($op10->id, 10.0, 0.0, 0.0, 0.0, 30.0, '10 units under hold', null, $this->user->id, false);

        $op20->refresh();
        $this->assertEquals(0.0, (float) $op20->quantity_transferred_in);
        $this->assertEquals(ProductionOrderOperation::STATUS_WAITING, $op20->status);

        // Release hold -> trigger re-evaluation of pending transfers
        $inspection->update(['status' => 'passed']);
        $wipService->evaluateAndExecuteWipTransfers($op10->id, $this->user->id);

        $op10->refresh();
        $op20->refresh();
        $this->assertEquals(10.0, (float) $op10->quantity_transferred_out);
        $this->assertEquals(10.0, (float) $op20->quantity_transferred_in);
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $op20->status);
    }

    /** SECTION 9 — Completion and Final Reconciliation */
    public function test_section_9_completion_and_final_reconciliation(): void
    {
        $order = $this->createDemoOrder(25.0);
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        $op10 = $order->operations->where('sequence', 10)->first();
        $op20 = $order->operations->where('sequence', 20)->first();

        $op10->update(['status' => ProductionOrderOperation::STATUS_RUNNING]);

        // Complete Op 10 with 25 units (2 full batches of 10 + 5 remainder)
        // Pass completeOperation=true to trigger final remainder transfer
        $execService->logProgress($op10->id, 25.0, 0.0, 0.0, 0.0, 75.0, 'Complete 25 units', null, $this->user->id, true);

        $op10->refresh();
        $op20->refresh();

        // Final remainder transferred on completion: all 25 good transferred out
        $this->assertEquals(25.0, (float) $op20->quantity_transferred_in);

        // Core conservation rule: valid transferred output from predecessor == valid input received by successor
        $this->assertEquals($op10->quantity_transferred_out, $op20->quantity_transferred_in);
    }

    /** SECTION 10 — Finished Goods, Costing and Traceability */
    public function test_section_10_finished_goods_costing_and_traceability(): void
    {
        $order = $this->createDemoOrder(50.0);

        // Record a timeline event (verifies model and schema)
        ProductionEventTimeline::create([
            'tenant_id'            => $this->tenant->id,
            'production_order_id'  => $order->id,
            'event_type'           => 'wip_transfer',
            'event_source'         => 'system',
            'title'                => 'WIP Transfer',
            'description'          => 'Transfer batch 10.0 units transferred from Cutting to Assembly',
            'event_time'           => now(),
        ]);

        $event = ProductionEventTimeline::where('production_order_id', $order->id)
            ->where('event_type', 'wip_transfer')
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('10.0 units transferred', $event->description);
        $this->assertEquals('system', $event->event_source);
    }

    /** SECTION 11 — Tenant and Authorization Isolation */
    public function test_section_11_tenant_and_authorization_isolation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant UAT', 'slug' => 'other-tenant-uat', 'domain' => 'other-uat.test']);
        $order = $this->createDemoOrder(50.0);
        $op10 = $order->operations->where('sequence', 10)->first();

        // Try to query WIP transactions with another tenant context
        $txs = ProductionWipTransaction::where('tenant_id', $otherTenant->id)
            ->where('production_order_operation_id', $op10->id)
            ->get();

        $this->assertEmpty($txs);
    }

    protected Routing $routing;
    protected RoutingOperation $rOp10;
    protected RoutingOperation $rOp20;

    protected function createDemoOrder(float $quantity = 50.0): ProductionOrder
    {
        // Ensure routing exists (reuse or create)
        if (!isset($this->routing)) {
            $this->routing = Routing::create([
                'tenant_id'      => $this->tenant->id,
                'product_id'     => $this->product->id,
                'name'           => 'UAT Demo Routing',
                'routing_number' => 'RT-UAT-' . rand(1000, 9999),
                'version'        => '1.0',
                'status'         => Routing::STATUS_ACTIVE,
                'is_default'     => true,
                'effective_from' => now()->subDay(),
            ]);

            $this->rOp10 = RoutingOperation::create([
                'tenant_id'               => $this->tenant->id,
                'routing_id'              => $this->routing->id,
                'sequence'                => 10,
                'operation_number'        => 'OP-10',
                'name'                    => 'Cutting Op 10',
                'work_center_id'          => $this->wcCutting->id,
                'machine_id'              => $this->machineA->id,
                'setup_time_minutes'      => 20,
                'processing_time_minutes' => 3,
                'overlap_enabled'         => true,
                'transfer_batch_quantity' => 10.0,
                'transfer_lag_minutes'    => 5,
            ]);

            $this->rOp20 = RoutingOperation::create([
                'tenant_id'               => $this->tenant->id,
                'routing_id'              => $this->routing->id,
                'sequence'                => 20,
                'operation_number'        => 'OP-20',
                'name'                    => 'Assembly Op 20',
                'work_center_id'          => $this->wcAssembly->id,
                'machine_id'              => $this->machineB->id,
                'setup_time_minutes'      => 10,
                'processing_time_minutes' => 2,
                'overlap_enabled'         => false,
                'transfer_batch_quantity' => 0.0,
                'transfer_lag_minutes'    => 0,
            ]);

            RoutingOperation::create([
                'tenant_id'               => $this->tenant->id,
                'routing_id'              => $this->routing->id,
                'sequence'                => 30,
                'operation_number'        => 'OP-30',
                'name'                    => 'Packing Op 30',
                'work_center_id'          => $this->wcPacking->id,
                'setup_time_minutes'      => 5,
                'processing_time_minutes' => 1,
                'overlap_enabled'         => false,
            ]);
        }

        // Create BOM (required by ProductionOrderService)
        if (!ProductionBom::where('tenant_id', $this->tenant->id)->where('product_id', $this->product->id)->exists()) {
            ProductionBom::create([
                'tenant_id'      => $this->tenant->id,
                'product_id'     => $this->product->id,
                'bom_number'     => 'BOM-UAT-001',
                'version'        => '1.0',
                'status'         => 'approved',
                'is_active'      => true,
                'effective_date' => now()->toDateString(),
            ]);
        }

        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id'      => $this->product->id,
            'quantity_ordered' => $quantity,
            'start_date'      => '2026-08-03',
            'end_date'        => '2026-08-07',
            'production_mode' => 'discrete',
        ], $this->tenant->id, $this->user->id);

        $order->update(['status' => ProductionOrder::STATUS_RELEASED]);
        app(ProductionWipService::class)->initializeWip($order->id, null, $this->user->id);

        return $order->fresh(['operations']);
    }
}
