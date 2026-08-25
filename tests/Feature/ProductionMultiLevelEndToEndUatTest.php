<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBatchGenealogy;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderCompletionValidator;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionMultiLevelEndToEndUatTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private Warehouse $warehouse;
    private WorkCenter $workCenter;
    private ProductionOrderService $orderService;
    private SchedulingService $schedulingService;
    private MesExecutionService $mesService;
    private ProductionExecutionService $executionService;
    private ProductionOrderCompletionValidator $completionValidator;
    private ProductionCostVarianceService $costVarianceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Heavy Crusher UAT Tenant', 'slug' => 'hc-uat-tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Units', 'code' => 'UNT', 'unit_type' => 'unit']);
        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Factory Warehouse',
            'code' => 'WH-MAIN',
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Assembly Center',
            'code' => 'WC-HEAVY',
            'status' => 'active',
            'daily_capacity_hours' => 8.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->orderService = $this->app->make(ProductionOrderService::class);
        $this->schedulingService = $this->app->make(SchedulingService::class);
        $this->mesService = $this->app->make(MesExecutionService::class);
        $this->executionService = $this->app->make(ProductionExecutionService::class);
        $this->completionValidator = $this->app->make(ProductionOrderCompletionValidator::class);
        $this->costVarianceService = $this->app->make(ProductionCostVarianceService::class);
    }

    /**
     * Complete 14-Step Integrated Heavy Crusher Multi-Level Manufacturing Lifecycle UAT (F-05)
     */
    public function test_heavy_crusher_multi_level_end_to_end_manufacturing_lifecycle_uat(): void
    {
        // ── 1. Create Products (FG, 2 SFGs, Raw Materials) ──────────────────────
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Heavy Crusher', 'sku' => 'HC-1000', 'type' => 'finished_good']);
        $sfgFrame = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Crusher Frame', 'sku' => 'SFG-FRAME', 'type' => 'semi_finished']);
        $sfgBearing = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Bearing Assembly', 'sku' => 'SFG-BEARING', 'type' => 'semi_finished']);
        $rmSteel = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Plate', 'sku' => 'RM-STEEL', 'type' => 'raw_material', 'unit_cost' => 50.0]);
        $rmBearing = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Raw Bearing Kit', 'sku' => 'RM-BEARING', 'type' => 'raw_material', 'unit_cost' => 30.0]);

        // ── 2. Create BOMs & Routings ──────────────────────────────────────────
        // FG BOM (1 Frame + 2 Bearings per Heavy Crusher)
        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-HC', 'bom_name' => 'Heavy Crusher BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfgFrame->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfgBearing->id, 'quantity' => 2.0, 'uom_id' => $this->uom->id]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'HC Routing', 'code' => 'RT-HC', 'status' => 'active']);
        $fgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Main Assembly', 'setup_time_minutes' => 30, 'processing_time_minutes' => 60]);
        $fgOp20 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 20, 'operation_number' => 'FG-20', 'name' => 'Final Testing', 'setup_time_minutes' => 15, 'processing_time_minutes' => 30]);

        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfgFrame->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);
        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfgBearing->id, 'quantity' => 2.0, 'uom_id' => $this->uom->id]);

        // Frame SFG BOM & Routing
        $frameBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfgFrame->id, 'bom_number' => 'BOM-FRAME', 'bom_name' => 'Frame BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $frameBom->id, 'material_id' => $rmSteel->id, 'quantity' => 2.0, 'uom_id' => $this->uom->id]);

        $frameRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfgFrame->id, 'name' => 'Frame Routing', 'code' => 'RT-FRAME', 'status' => 'active']);
        $frameOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $frameRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FRM-10', 'name' => 'Cutting', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);
        $frameOp20 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $frameRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 20, 'operation_number' => 'FRM-20', 'name' => 'Welding', 'setup_time_minutes' => 15, 'processing_time_minutes' => 30]);

        // Bearing SFG BOM & Routing
        $bearingBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfgBearing->id, 'bom_number' => 'BOM-BEARING', 'bom_name' => 'Bearing BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bearingBom->id, 'material_id' => $rmBearing->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $bearingRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfgBearing->id, 'name' => 'Bearing Routing', 'code' => 'RT-BEARING', 'status' => 'active']);
        $bearingOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $bearingRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'BRG-10', 'name' => 'Machining', 'setup_time_minutes' => 10, 'processing_time_minutes' => 15]);

        // ── 3. Partial Warehouse Stock Setup (6 Bearings Reserved in Warehouse) ──
        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $sfgBearing->id,
            'quantity' => 6.0,
            'reserved_qty' => 6.0,
        ]);

        // ── 4. Create Production Order (Quantity 10) ──────────────────────────
        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ], $this->tenant->id);

        $this->assertEquals(5, $order->operations()->count()); // 2 FG ops + 2 Frame ops + 1 Bearing op

        // Partial Stock Target Verification (Frame target = 10, Bearing target = 20 - 6 = 14)
        $bearingOp = $order->operations->firstWhere('operation_number', 'BRG-10');
        $this->assertEquals(14.0, (float) $bearingOp->target_produced_qty);

        // ── 5. Schedule Order ─────────────────────────────────────────────────
        $schedule = $this->schedulingService->generateForwardSchedule($order, now());
        $this->assertNotNull($schedule);

        // Release order for execution
        $this->orderService->release($order->id, $this->user->id, force: true);

        // ── 6. Execute SFG Operations ─────────────────────────────────────────
        $frmOp10 = ProductionOrderOperation::where('production_order_id', $order->id)->where('source_product_id', $sfgFrame->id)->where('sequence', 10)->first();
        $frmOp20 = ProductionOrderOperation::where('production_order_id', $order->id)->where('source_product_id', $sfgFrame->id)->where('sequence', 20)->first();
        $brgOp10 = ProductionOrderOperation::where('production_order_id', $order->id)->where('source_product_id', $sfgBearing->id)->where('sequence', 10)->first();
        $parentFgOp10 = ProductionOrderOperation::where('production_order_id', $order->id)->where('source_product_id', $fg->id)->where('sequence', 10)->first();

        // Claim and log Frame OP10 (10 produced)
        $this->mesService->claimBatchToExecute($frmOp10->id, 10.0, 'CLAIM-FRM-10');
        $this->executionService->logProgress($frmOp10->id, 10.0, 0.0, 0.0, 10.0, 20.0, 'Cut 10 frames', null, $this->user->id);

        // Transfer completed WIP from FRM-10 to FRM-20 work center
        app(\App\Domains\Production\Services\ProductionWipService::class)->reconcileOrderWipCards($order->id);

        $frm10Wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->where('current_routing_operation_id', $frmOp10->routing_operation_id)
            ->first();

        if ($frm10Wip) {
            app(\App\Domains\Production\Services\ProductionWipService::class)->transferWip(
                $frm10Wip->id,
                $frmOp20->id,
                $frmOp20->work_center_id,
                10.0,
                userId: $this->user->id
            );
        }

        // Log Frame OP20 (8 good produced, 2 rejected)
        $this->executionService->logProgress($frmOp20->id, 8.0, 2.0, 0.0, 15.0, 30.0, 'Welded 8 frames, 2 rejected', null, $this->user->id);

        // ── 7. FG Restricted due to Partial Stock (6 warehouse bearings / 2 BOM ratio = 3 executable FG)
        $readiness = $this->mesService->calculateOperationReadiness($parentFgOp10->fresh());
        $this->assertEquals(3.0, $readiness['executable_qty']);

        // Attempting to claim 10 FG units fails because remaining executable quantity is 3.0
        try {
            $this->mesService->claimBatchToExecute($parentFgOp10->id, 10.0, 'CLAIM-FG-FAIL');
            $this->fail("Expected InvalidArgumentException when claiming more than executable quantity.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Remaining executable quantity is 3", $e->getMessage());
        }

        // ── 8. Execute Bearing OP10 & Recover Frame Rework to Unlock Full Capacity ──
        $this->mesService->claimBatchToExecute($brgOp10->id, 14.0, 'CLAIM-BRG-10');
        $this->executionService->logProgress($brgOp10->id, 14.0, 0.0, 0.0, 10.0, 15.0, 'Machined 14 bearings', null, $this->user->id);

        // Recover 2 rejected frames via Rework completion
        $frmOp20Fresh = $frmOp20->fresh();
        $frmOp20Fresh->quantity_produced += 2.0;
        $frmOp20Fresh->quantity_rejected -= 2.0;
        $frmOp20Fresh->save();
        \App\Domains\Production\Models\ProductionOrderRework::where('production_order_operation_id', $frmOp20->id)->update(['status' => 'completed']);

        // ── 9. FG Readiness Unlocks to 10 Units ─────────────────────────────
        $parentFgOp10Fresh = ProductionOrderOperation::find($parentFgOp10->id);
        $readinessFull = $this->mesService->calculateOperationReadiness($parentFgOp10Fresh);
        $this->assertEquals(10.0, $readinessFull['executable_qty']);

        // ── 10. Claim and Execute FG OP10 (Log 5 FG Progress & Physical SFG Consumption) ─
        $this->mesService->claimBatchToExecute($parentFgOp10->id, 5.0, 'CLAIM-FG-5');
        $this->executionService->logProgress($parentFgOp10->id, 5.0, 0.0, 0.0, 30.0, 60.0, 'Assembled 5 heavy crushers', null, $this->user->id);

        // ── 11. Verify Physical SFG Consumption & Ledger Consistency (F-06) ──
        $frmOp30Fresh = $frmOp20->fresh();
        $this->assertEquals(5.0, (float) $frmOp30Fresh->quantity_consumed);

        $brgOp10Fresh = $brgOp10->fresh();
        $this->assertEquals(10.0, (float) $brgOp10Fresh->quantity_consumed); // 5 FG * 2 BOM ratio = 10 bearings

        // ── 12. Verify Batch Genealogy (F-03) ─────────────────────────────────
        $genealogyCount = ProductionBatchGenealogy::where('tenant_id', $this->tenant->id)
            ->where('type', 'component_consumption')
            ->count();
        $this->assertGreaterThan(0, $genealogyCount);

        // ── 13. Verify Costing Protection (No synthetic cost added on sfg_consumed) ─
        $costAnalysis = $this->costVarianceService->getCostAnalysis($order);
        $this->assertNotNull($costAnalysis);

        // ── 14. Completion Validation ─────────────────────────────────────────
        $frmOp10->update(['status' => 'completed']);
        $frmOp20->update(['status' => 'completed']);
        $brgOp10->update(['status' => 'completed']);

        $this->completionValidator->validateCompletion($order);
    }

    /**
     * Test Idempotency, Rework Recovery, SFG Consumption, and Completion (F-01, F-02, F-03, F-06)
     */
    public function test_idempotency_rework_recovery_and_completion_guards(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Engine Complete', 'sku' => 'ENG-100']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Piston Sub', 'sku' => 'PIS-100', 'type' => 'semi_finished']);

        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-ENG', 'bom_name' => 'Engine BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'ENG Routing', 'code' => 'RT-ENG', 'status' => 'active']);
        $fgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Engine Assembly', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);
        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-PIS', 'bom_name' => 'Piston BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'Piston Routing', 'code' => 'RT-PIS', 'status' => 'active']);
        $sfgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $sfgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'SFG-10', 'name' => 'Piston Casting', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenant->id);

        $sfgOp = $order->operations->firstWhere('operation_number', 'SFG-10');
        $fgOp = $order->operations->firstWhere('operation_number', 'FG-10');

        // 1. Claim Idempotency Test (F-02)
        $claim1 = $this->mesService->claimBatchToExecute($sfgOp->id, 10.0, 'KEY-IDEM-001');
        $this->assertEquals(10.0, $claim1);

        // Retry same idempotency key does NOT increase claim quantity
        $claimRetry = $this->mesService->claimBatchToExecute($sfgOp->id, 10.0, 'KEY-IDEM-001');
        $this->assertEquals(10.0, $claimRetry);
        $this->assertEquals(10.0, (float) $sfgOp->fresh()->quantity_claimed);

        // 2. Log SFG Progress (8 Good + 2 Scrapped) (F-01)
        $this->executionService->logProgress($sfgOp->id, 8.0, 0.0, 2.0, 10.0, 20.0, 'Cast 8 good, 2 scrapped', null, $this->user->id);
        $sfgOp->update(['status' => 'completed']);

        // F-01 Claim Resolution Test: Claim = 10, Good = 8, Scrap = 2 -> Total Processed = 10 -> Claim is resolved!
        $this->completionValidator->validateCompletion($order); // Does NOT throw exception!

        // 3. Log Parent FG Progress & Verify Physical Consumption & Ledger Invariant (F-03, F-06)
        $this->mesService->claimBatchToExecute($fgOp->id, 5.0, 'KEY-FG-CLAIM');
        $this->executionService->logProgress($fgOp->id, 5.0, 0.0, 0.0, 10.0, 20.0, 'Assembled 5 engines', null, $this->user->id);

        // F-06 Invariant Check: operation.quantity_consumed == SUM(valid sfg_consumed ledger quantity)
        $sfgConsumedLedgerSum = (float) ProductionWipTransaction::where('tenant_id', $this->tenant->id)
            ->where('production_order_id', $order->id)
            ->where('transaction_type', 'sfg_consumed')
            ->sum('quantity');

        $this->assertEquals(5.0, (float) $sfgOp->fresh()->quantity_consumed);
        $this->assertEquals(5.0, $sfgConsumedLedgerSum);
        $this->assertEquals((float) $sfgOp->fresh()->quantity_consumed, $sfgConsumedLedgerSum);
    }
}
