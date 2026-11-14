<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionReadinessService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrossAssemblyQueueThresholdTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private ProductionOrderService $orderService;
    private SchedulingService $schedulingService;
    private ProductionReadinessService $readinessService;
    private ProductionExecutionService $executionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Cross-Assembly Queue Tenant',
            'slug' => 'cross-queue-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Engineer',
            'email' => 'engineer@cross-queue.test',
            'password' => bcrypt('password'),
            'role' => 'production_engineer',
        ]);
        $this->actingAs($this->user);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'unit_type' => 'unit',
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Machining Center 1',
            'code' => 'WC-M1',
            'status' => 'active',
            'daily_capacity_hours' => 24.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Center 2',
            'code' => 'WC-A2',
            'status' => 'active',
            'daily_capacity_hours' => 24.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->orderService = app(ProductionOrderService::class);
        $this->schedulingService = app(SchedulingService::class);
        $this->readinessService = app(ProductionReadinessService::class);
        $this->executionService = app(ProductionExecutionService::class);
    }

    public function test_saving_routing_with_queue_threshold_on_final_operation_succeeds(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision SFG',
            'sku' => 'SFG-PRECISION-01',
            'type' => 'semi_finished_good',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Precision SFG 2-Step Routing',
            'product_id' => $product->id,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => '1',
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Initial Turning',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter1->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                ],
                [
                    'sequence' => 20,
                    'name' => 'Final Grinding and Queue Stream',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter2->id,
                    'setup_time_minutes' => 15,
                    'processing_time_minutes' => 10,
                    'expected_yield_percentage' => 100.00,
                    'queue_threshold_enabled' => true,
                    'transfer_batch_quantity' => 8,
                    'transfer_lag_minutes' => 12,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();

        $routing = Routing::where('product_id', $product->id)->first();
        $this->assertNotNull($routing);
        $this->assertCount(2, $routing->operations);

        $finalOp = $routing->operations->firstWhere('sequence', 20);
        $this->assertNotNull($finalOp);
        $this->assertTrue((bool) $finalOp->queue_threshold_enabled);
        $this->assertEquals(8.0, (float) $finalOp->transfer_batch_quantity);
        $this->assertEquals(12.0, (float) $finalOp->transfer_lag_minutes);

        // Verify updating the routing with queue threshold on final operation also succeeds
        $updatePayload = [
            'name' => 'Precision SFG 2-Step Routing Updated',
            'product_id' => $product->id,
            'version' => '1.0.1',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'id' => $routing->operations->firstWhere('sequence', 10)->id,
                    'sequence' => 10,
                    'name' => 'Initial Turning Modified',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter1->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                ],
                [
                    'id' => $finalOp->id,
                    'sequence' => 20,
                    'name' => 'Final Grinding and Queue Stream',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter2->id,
                    'setup_time_minutes' => 15,
                    'processing_time_minutes' => 10,
                    'expected_yield_percentage' => 100.00,
                    'queue_threshold_enabled' => true,
                    'transfer_batch_quantity' => 15,
                    'transfer_lag_minutes' => 20,
                ],
            ],
        ];

        $updateResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->put(route('production.routing.update', $routing->id), $updatePayload);

        $updateResponse->assertRedirect();

        $routing->refresh();
        $updatedFinalOp = $routing->operations()->where('sequence', 20)->first();
        $this->assertNotNull($updatedFinalOp);
        $this->assertTrue((bool) $updatedFinalOp->queue_threshold_enabled);
        $this->assertEquals(15.0, (float) $updatedFinalOp->transfer_batch_quantity);
        $this->assertEquals(20.0, (float) $updatedFinalOp->transfer_lag_minutes);
    }

    public function test_multilevel_scheduling_starts_parent_after_sfg_transfer_batch_rather_than_full_batch(): void
    {
        // 1. Finished Good & SFG & RM Products
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Ingot', 'sku' => 'RM-STEEL-01']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Piston Rod SFG', 'sku' => 'SFG-PISTON-01']);
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Engine Cylinder FG', 'sku' => 'FG-CYLINDER-01']);

        // 2. FG Routing: 1 operation that consumes the SFG
        $fgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'name' => 'FG Cylinder Assembly Routing',
            'routing_number' => 'RT-FG-CYL',
            'status' => 'active',
        ]);
        $fgOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'work_center_id' => $this->workCenter2->id,
            'sequence' => 10,
            'operation_number' => 'FG-10',
            'name' => 'Final Assembly',
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 15,
        ]);
        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $fgOp->id,
            'material_id' => $sfg->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        // FG BOM
        $fgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-FG-CYL',
            'bom_name' => 'Cylinder BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $sfg->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        // 3. SFG Routing: 1 operation with Queue Threshold enabled on its final operation
        $sfgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $sfg->id,
            'name' => 'Piston Rod SFG Routing',
            'routing_number' => 'RT-SFG-PIST',
            'status' => 'active',
        ]);
        $sfgOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $sfgRouting->id,
            'work_center_id' => $this->workCenter1->id,
            'sequence' => 10,
            'operation_number' => 'SFG-10',
            'name' => 'Piston Machining',
            'setup_time_minutes' => 30,
            'processing_time_minutes' => 20, // 20 units * 20 min = 400 min run time. Total batch = 430 min.
            'queue_threshold_enabled' => true,
            'transfer_batch_quantity' => 5, // Transfer batch of 5: 30 setup + (5 * 20) + 10 lag = 140 min.
            'transfer_lag_minutes' => 10,
        ]);

        // SFG BOM
        $sfgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $sfg->id,
            'bom_number' => 'BOM-SFG-PIST',
            'bom_name' => 'Piston BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $sfgBom->id,
            'material_id' => $rm->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        // Create direct multi-level order for quantity of 20 starting at 08:00 (work shift start)
        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 20,
            'start_date' => now()->startOfDay()->setTime(8, 0, 0)->toDateTimeString(),
            'end_date' => now()->addDays(5)->toDateTimeString(),
        ], $this->tenant->id);

        $startDate = Carbon::parse(now()->startOfDay()->setTime(8, 0, 0)->toDateTimeString());
        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);

        $this->assertNotNull($schedule);
        $schedOps = $schedule->operations;

        $sfgSchedOp = $schedOps->firstWhere('orderOperation.operation_number', 'SFG-10');
        $fgSchedOp = $schedOps->firstWhere('orderOperation.operation_number', 'FG-10');

        $this->assertNotNull($sfgSchedOp);
        $this->assertNotNull($fgSchedOp);

        $sfgStart = Carbon::parse($sfgSchedOp->planned_start);
        $sfgFinish = Carbon::parse($sfgSchedOp->planned_finish);
        $fgStart = Carbon::parse($fgSchedOp->planned_start);

        // SFG total run time for 20 units is 430 minutes (30 setup + 400 run)
        $this->assertEquals(430, $sfgStart->diffInMinutes($sfgFinish));

        // Because queue_threshold_enabled is TRUE with transfer batch = 5 and lag = 10:
        // First batch transfer ready at: 30 setup + (5 * 20) run + 10 lag = 140 minutes from start.
        $this->assertEquals(140, $sfgStart->diffInMinutes($fgStart));

        // Parent operation starts SIGNIFICANTLY earlier than the full SFG batch finish!
        $this->assertTrue(
            $fgStart->lt($sfgFinish),
            "Parent operation planned start ({$fgStart}) must be earlier than SFG full batch finish ({$sfgFinish})."
        );

        // Contrast case: If SFG final op had queue_threshold_enabled = false, Finish-to-Start applies
        $sfgOp->update(['queue_threshold_enabled' => false, 'overlap_enabled' => false]);
        $sfgOrderOp = $order->operations()->where('operation_number', 'SFG-10')->first();
        $sfgOrderOp->update(['queue_threshold_enabled' => false, 'overlap_enabled' => false]);

        $ftsSchedule = $this->schedulingService->generateForwardSchedule($order, $startDate);
        $ftsSfgSchedOp = $ftsSchedule->operations->firstWhere('orderOperation.operation_number', 'SFG-10');
        $ftsFgSchedOp = $ftsSchedule->operations->firstWhere('orderOperation.operation_number', 'FG-10');

        $this->assertTrue(
            Carbon::parse($ftsFgSchedOp->planned_start)->gte(Carbon::parse($ftsSfgSchedOp->planned_finish)),
            "When queue threshold is disabled, parent operation must start only after SFG full batch finish."
        );
    }

    public function test_consuming_parent_operation_becomes_ready_when_sfg_reaches_transfer_batch(): void
    {
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel', 'sku' => 'RM-ST-02']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Sub Module', 'sku' => 'SFG-MOD-02']);
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Main Module', 'sku' => 'FG-MOD-02']);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'FG Module Routing', 'code' => 'RT-FGM', 'status' => 'active']);
        $fgOp = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter2->id, 'sequence' => 10, 'operation_number' => 'FG-M10', 'name' => 'FG Assembly', 'setup_time_minutes' => 10, 'processing_time_minutes' => 10]);
        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-FGM', 'bom_name' => 'FGM BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'SFG Module Routing', 'code' => 'RT-SFGM', 'status' => 'active']);
        $sfgOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $sfgRouting->id,
            'work_center_id' => $this->workCenter1->id,
            'sequence' => 10,
            'operation_number' => 'SFG-M10',
            'name' => 'SFG Fabrication',
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 10,
            'queue_threshold_enabled' => true,
            'transfer_batch_quantity' => 10,
        ]);

        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-SFGM', 'bom_name' => 'SFGM BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $sfgBom->id, 'material_id' => $rm->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 50,
            'start_date' => now()->startOfDay()->toDateTimeString(),
            'end_date' => now()->addDays(5)->toDateTimeString(),
        ], $this->tenant->id);

        $orderOps = $order->operations()->get();
        $sfgOrderOp = $orderOps->firstWhere('operation_number', 'SFG-M10');
        $fgOrderOp = $orderOps->firstWhere('operation_number', 'FG-M10');

        $this->assertNotNull($sfgOrderOp);
        $this->assertNotNull($fgOrderOp);

        // Before SFG claims or produces transfer batch, FG operation is blocked by dependency dimension
        $evalBefore = $this->readinessService->evaluateOperationReadiness($fgOrderOp);
        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $evalBefore['dependency']['status']);
        $this->assertEquals(0.0, $evalBefore['dependency']['ready_qty']);

        // Now SFG operation logs progress equal to transfer batch quantity (10 units)
        $this->executionService->logProgress(
            $sfgOrderOp->id,
            10.0,
            0.0,
            0.0,
            10.0,
            100.0,
            'First transfer batch completed',
            null,
            $this->user->id
        );

        $fgOrderOp->refresh();
        $sfgOrderOp->refresh();

        // Evaluate readiness again after reaching the transfer batch threshold
        $evalAfter = $this->readinessService->evaluateOperationReadiness($fgOrderOp);
        $this->assertNotEquals(ProductionReadinessService::STATUS_BLOCKED, $evalAfter['dependency']['status']);
        $this->assertGreaterThanOrEqual(10.0, $evalAfter['dependency']['ready_qty']);

        // Order operation status is advanced to READY
        $this->assertEquals(ProductionOrderOperation::STATUS_READY, $fgOrderOp->status);
    }

    public function test_standalone_production_order_with_queue_threshold_on_final_op_requires_full_quantity_to_autocomplete(): void
    {
        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Standalone FG', 'sku' => 'FG-STANDALONE']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'RM Standalone', 'sku' => 'RM-STANDALONE']);

        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'name' => 'Standalone Routing', 'code' => 'RT-ST', 'status' => 'active']);
        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'work_center_id' => $this->workCenter1->id,
            'sequence' => 10,
            'operation_number' => 'OP-ST-10',
            'name' => 'Single Final Op With Threshold',
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 10,
            'queue_threshold_enabled' => true,
            'transfer_batch_quantity' => 10,
        ]);

        $bom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'bom_number' => 'BOM-ST', 'bom_name' => 'ST BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bom->id, 'material_id' => $rm->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $order = $this->orderService->createDirect([
            'product_id' => $product->id,
            'quantity_ordered' => 50,
            'start_date' => now()->startOfDay()->toDateTimeString(),
            'end_date' => now()->addDays(2)->toDateTimeString(),
        ], $this->tenant->id);

        $op = $order->operations()->first();
        $this->assertNotNull($op);

        // 1. Operator logs progress of 10 units (only the transfer batch)
        $this->executionService->logProgress(
            $op->id,
            10.0,
            0.0,
            0.0,
            10.0,
            100.0,
            'Transfer batch 1 logged',
            null,
            $this->user->id
        );

        $order->refresh();
        $op->refresh();

        // Evaluate auto-complete: must return FALSE because 10 < 50
        $completedPrematurely = $this->orderService->evaluateAndAutoCompleteOrder($order);
        $this->assertFalse($completedPrematurely, 'Order must not auto-complete after only a transfer batch is finished.');

        $order->refresh();
        $this->assertNotEquals(ProductionOrder::STATUS_COMPLETED, $order->status);

        // 2. Operator logs remaining 40 units (total produced = 50)
        $this->executionService->logProgress(
            $op->id,
            40.0,
            0.0,
            0.0,
            0.0,
            400.0,
            'Remaining quantity logged',
            null,
            $this->user->id
        );

        $order->refresh();
        $op->refresh();
        $this->assertEquals(50.0, (float) $op->quantity_produced);

        // Evaluate auto-complete: now returns TRUE because 100% of order quantity is produced
        $autoCompleted = $this->orderService->evaluateAndAutoCompleteOrder($order);
        $this->assertTrue($autoCompleted, 'Order must auto-complete when 100% of ordered quantity is produced.');

        $order->refresh();
        $this->assertEquals(ProductionOrder::STATUS_COMPLETED, $order->status);
    }
}
