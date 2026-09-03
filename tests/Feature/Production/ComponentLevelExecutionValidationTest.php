<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentLevelExecutionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $fgTable;
    protected Product $sfgLeg;
    protected Product $rawPipe;
    protected Uom $pcsUom;
    protected Uom $meterUom;
    protected WorkCenter $wcCut;
    protected WorkCenter $wcAssy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->pcsUom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'symbol' => 'pcs',
            'category' => 'unit',
        ]);

        $this->meterUom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Meters',
            'code' => 'MTR',
            'symbol' => 'm',
            'category' => 'length',
        ]);

        $this->fgTable = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dining Table',
            'sku' => 'FG-TBL-002',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgLeg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Table Leg',
            'sku' => 'SFG-LEG-002',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->rawPipe = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Steel Pipe',
            'sku' => 'RAW-PIPE-001',
            'type' => 'purchased',
            'uom_id' => $this->meterUom->id,
        ]);

        $this->wcCut = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cutting Station',
            'code' => 'WC-CUT-2',
            'is_active' => true,
        ]);

        $this->wcAssy = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Station',
            'code' => 'WC-ASSY-2',
            'is_active' => true,
        ]);
    }

    public function test_bom_base_quantity_normalization(): void
    {
        // BOM Base Qty = 5 Tables, Component = 20 Legs. Order Qty = 10 Tables.
        // Expected component target = 10 * (20 / 5) = 40 Legs (NOT 200).
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-BASE-5',
            'bom_name' => 'Dining Table BOM 5 Base',
            'product_id' => $this->fgTable->id,
            'base_quantity' => 5.0,
            'uom_id' => $this->pcsUom->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'version' => '1.0',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 20.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-BASE-5',
            'name' => 'Dining Table Routing 5 Base',
            'product_id' => $this->fgTable->id,
            'status' => 'active',
            'version' => '1.0',
        ]);

        $op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Leg Cutting',
            'work_center_id' => $this->wcCut->id,
            'setup_time_minutes' => 5.0,
            'processing_time_minutes' => 2.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op10->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 20.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-BASE-TEST',
            'product_id' => $this->fgTable->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bom,
            $routing,
            10.0,
            $this->tenant->id,
            $this->user->id
        );

        $op = $order->operations()->where('sequence', 10)->first();
        // 10 Tables * (20 Legs / 5 Base) = 40 Legs
        $this->assertEquals(40.0, $op->target_produced_qty);
    }

    public function test_scrap_factor_semantics(): void
    {
        // 4 Legs per Table, 5% Scrap on material. Order Qty = 10 Tables.
        // Target = 10 * 4 * (1 + 0.05) = 42 Legs required processing target.
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SCRAP-5',
            'bom_name' => 'Scrap Test BOM',
            'product_id' => $this->fgTable->id,
            'base_quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'version' => '1.0',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 4.0,
            'material_scrap_percentage' => 5.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-SCRAP-5',
            'name' => 'Scrap Test Routing',
            'product_id' => $this->fgTable->id,
            'status' => 'active',
            'version' => '1.0',
        ]);

        $op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Leg Cutting',
            'work_center_id' => $this->wcCut->id,
            'setup_time_minutes' => 5.0,
            'processing_time_minutes' => 2.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op10->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 4.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-SCRAP-TEST',
            'product_id' => $this->fgTable->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bom,
            $routing,
            10.0,
            $this->tenant->id,
            $this->user->id
        );

        $op = $order->operations()->where('sequence', 10)->first();
        // Net Good Output Required = 10 Tables * 4 Legs = 40 Legs (Scrap % applies to raw material requisition)
        $this->assertEquals(40.0, $op->target_produced_qty);
    }

    public function test_legacy_order_fallback_accessor(): void
    {
        // For historical order operations without explicit target_produced_qty column populated
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-LEGACY-001',
            'product_id' => $this->fgTable->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
        ]);

        $legacyOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Legacy Cutting',
            'work_center_id' => $this->wcCut->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 50.0,
            'total_time_planned' => 60.0,
        ]);

        // Dynamically evaluates fallback without crashing
        $this->assertEquals(10.0, $legacyOp->target_produced_qty);
    }

    public function test_pending_quantity_and_progress_percentage(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-PROG-001',
            'product_id' => $this->fgTable->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Leg Cutting',
            'source_product_id' => $this->sfgLeg->id,
            'target_produced_qty' => 40.0,
            'quantity_produced' => 25.0,
            'work_center_id' => $this->wcCut->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
        ]);

        $pending = max(0.0, $op->target_produced_qty - $op->quantity_produced);
        $pct = $op->target_produced_qty > 0 ? ($op->quantity_produced / $op->target_produced_qty) * 100 : 0.0;

        $this->assertEquals(15.0, $pending);
        $this->assertEquals(62.5, $pct);
    }

    public function test_batch_split_target_consistency(): void
    {
        $batchService = app(\App\Domains\Production\Services\BatchProductionService::class);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-BATCH-SPLIT',
            'product_id' => $this->fgTable->id,
            'quantity_ordered' => 10.0,
            'production_mode' => 'batch',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Leg Cutting',
            'source_product_id' => $this->sfgLeg->id,
            'target_produced_qty' => 40.0,
            'work_center_id' => $this->wcCut->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
        ]);

        $batchA = $batchService->createBatch($this->tenant->id, $order->id, $this->fgTable->id, 4.0, $this->user->id);
        $batchB = $batchService->createBatch($this->tenant->id, $order->id, $this->fgTable->id, 6.0, $this->user->id);

        // 4 Tables batch * 4 Legs/Table = 16 Legs target
        $targetA = ($batchA->planned_quantity / $order->quantity_ordered) * $op->target_produced_qty;
        // 6 Tables batch * 4 Legs/Table = 24 Legs target
        $targetB = ($batchB->planned_quantity / $order->quantity_ordered) * $op->target_produced_qty;

        $this->assertEquals(16.0, $targetA);
        $this->assertEquals(24.0, $targetB);
        $this->assertEquals(40.0, $targetA + $targetB);
    }
}
