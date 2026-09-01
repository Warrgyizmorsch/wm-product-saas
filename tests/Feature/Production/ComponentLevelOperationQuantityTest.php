<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentLevelOperationQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $fgTable;
    protected Product $sfgLeg;
    protected Product $sfgSupport;
    protected Product $sfgFrame;
    protected Uom $pcsUom;
    protected WorkCenter $wcCut;
    protected WorkCenter $wcWeld;
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

        $this->fgTable = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dining Table',
            'sku' => 'FG-TABLE-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgLeg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Table Leg',
            'sku' => 'SFG-LEG-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgSupport = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Horizontal Support',
            'sku' => 'SFG-SUPP-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgFrame = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Table Frame',
            'sku' => 'SFG-FRAME-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->wcCut = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cutting Workstation',
            'code' => 'WC-CUT',
            'is_active' => true,
        ]);

        $this->wcWeld = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Welding Workstation',
            'code' => 'WC-WELD',
            'is_active' => true,
        ]);

        $this->wcAssy = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Workstation',
            'code' => 'WC-ASSY',
            'is_active' => true,
        ]);
    }

    protected function createTableBomAndRouting(): array
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TABLE-001',
            'bom_name' => 'Dining Table BOM',
            'product_id' => $this->fgTable->id,
            'base_quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'version' => '1.0',
        ]);

        $itemLeg = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 4.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $itemSupport = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $this->sfgSupport->id,
            'quantity' => 2.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $itemFrame = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'material_id' => $this->sfgFrame->id,
            'quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-TABLE-001',
            'name' => 'Dining Table Routing',
            'product_id' => $this->fgTable->id,
            'status' => 'active',
            'version' => '1.0',
        ]);

        $op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Pipe Cutting — Table Legs',
            'work_center_id' => $this->wcCut->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 2.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op10->id,
            'material_id' => $this->sfgLeg->id,
            'quantity' => 4.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $op20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Pipe Cutting — Horizontal Supports',
            'work_center_id' => $this->wcCut->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 3.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op20->id,
            'material_id' => $this->sfgSupport->id,
            'quantity' => 2.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $op30 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Welding — Table Frame',
            'work_center_id' => $this->wcWeld->id,
            'setup_time_minutes' => 15.0,
            'processing_time_minutes' => 5.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op30->id,
            'material_id' => $this->sfgFrame->id,
            'quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        $op40 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 40,
            'operation_number' => 'OP40',
            'name' => 'Final Assembly — Dining Table',
            'work_center_id' => $this->wcAssy->id,
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 15.0,
        ]);

        return compact('bom', 'routing', 'op10', 'op20', 'op30', 'op40');
    }

    protected function createOrderHelper(ProductionBom $bom, Routing $routing, float $qty = 10.0): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-TEST-' . uniqid(),
            'product_id' => $this->fgTable->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => $qty,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bom,
            $routing,
            $qty,
            $this->tenant->id,
            $this->user->id
        );

        return $order;
    }

    public function test_scenario_a_and_b_single_routing_component_target_quantities(): void
    {
        $setup = $this->createTableBomAndRouting();

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        $ops = $order->operations()->orderBy('sequence', 'asc')->get();

        $this->assertCount(4, $ops);

        // OP10 Pipe Cutting Table Legs: Target = 10 Tables * 4 Legs/Table = 40 Legs
        $this->assertEquals(40.0, $ops[0]->target_produced_qty);
        $this->assertEquals($this->sfgLeg->id, $ops[0]->source_product_id);
        $this->assertTrue($ops[0]->is_intermediate);

        // OP20 Pipe Cutting Supports: Target = 10 Tables * 2 Supports/Table = 20 Supports
        $this->assertEquals(20.0, $ops[1]->target_produced_qty);
        $this->assertEquals($this->sfgSupport->id, $ops[1]->source_product_id);
        $this->assertTrue($ops[1]->is_intermediate);

        // OP30 Welding Frame: Target = 10 Tables * 1 Frame/Table = 10 Frames
        $this->assertEquals(10.0, $ops[2]->target_produced_qty);
        $this->assertEquals($this->sfgFrame->id, $ops[2]->source_product_id);
        $this->assertTrue($ops[2]->is_intermediate);

        // OP40 Final Assembly Table: Target = 10 Tables
        $this->assertEquals(10.0, $ops[3]->target_produced_qty);
        $this->assertEquals($this->fgTable->id, $ops[3]->source_product_id);
        $this->assertFalse($ops[3]->is_intermediate);
    }

    public function test_scenario_d_scheduling_planned_runtime_without_double_multiplication(): void
    {
        $setup = $this->createTableBomAndRouting();

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        $op10 = $order->operations()->where('sequence', 10)->first();
        // OP10 processing time = 2.0 min/Leg * 40 Legs = 80 minutes
        $this->assertEquals(80.0, $op10->processing_time_planned);

        $schedService = app(SchedulingService::class);
        $schedule = $schedService->generateSchedule($order, now());

        $schedOp10 = $schedule->operations()->where('sequence', 10)->first();
        // Duration should be setup (10) + processing (80) = 90 minutes (NOT 800 minutes)
        $this->assertEquals(90.0, $schedOp10->planned_duration_minutes);
    }

    public function test_scenario_e_mes_progress_does_not_increment_parent_fg_quantity_prematurely(): void
    {
        $setup = $this->createTableBomAndRouting();

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);

        $schedService = app(SchedulingService::class);
        $schedule = $schedService->generateSchedule($order, now());

        $schedOp10 = $schedule->operations()->where('sequence', 10)->first();

        $mesService = app(MesExecutionService::class);
        $mesService->startOperation($schedOp10->id, null, $this->user->id);
        $mesService->completeOperation($schedOp10->id, [
            'quantity_produced' => 25.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 10.0,
            'run_minutes_logged' => 50.0,
        ], $this->user->id);

        $op10 = $order->operations()->where('sequence', 10)->first();
        $this->assertEquals(25.0, $op10->quantity_produced);

        // Parent FG order quantity_produced must REMAIN 0.0 (25 Legs cut does not mean 25 Tables finished)
        $order->refresh();
        $this->assertEquals(0.0, $order->quantity_produced);
    }

    public function test_scenario_f_partial_progress_logging(): void
    {
        $setup = $this->createTableBomAndRouting();

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        app(ProductionOrderService::class)->release($order->id, $this->user->id, force: true);
        $schedule = app(SchedulingService::class)->generateSchedule($order, now());
        $schedOp10 = $schedule->operations()->where('sequence', 10)->first();

        $mesService = app(MesExecutionService::class);
        $mesService->startOperation($schedOp10->id, null, $this->user->id);

        // 15 Legs
        $mesService->completeOperation($schedOp10->id, ['quantity_produced' => 15.0], $this->user->id);
        $op10 = $order->operations()->where('sequence', 10)->first();
        $this->assertEquals(15.0, $op10->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->status);

        // 10 Legs
        $mesService->completeOperation($schedOp10->id, ['quantity_produced' => 10.0], $this->user->id);
        $op10->refresh();
        $this->assertEquals(25.0, $op10->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_RUNNING, $op10->status);

        // 15 Legs (Total = 40 / 40)
        $mesService->completeOperation($schedOp10->id, ['quantity_produced' => 15.0], $this->user->id);
        $op10->refresh();
        $this->assertEquals(40.0, $op10->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op10->status);
    }

    public function test_scenario_h_subcontract_pr_uses_component_target_quantity(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Subcontract Vendor Inc',
            'code' => 'VEND-SUB-01',
            'is_active' => true,
        ]);

        $setup = $this->createTableBomAndRouting();
        $setup['op10']->update([
            'is_external' => true,
            'subcontract_cost_per_unit' => 8.0,
            'vendor_id' => $vendor->id,
        ]);

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        $op10 = $order->operations()->where('sequence', 10)->first();
        $this->assertEquals(40.0, $op10->target_produced_qty);

        $orchestrator = app(SubcontractProcurementOrchestrator::class);
        $pr = $orchestrator->generateSubcontractRequisition($op10, $this->tenant->id, $this->user->id);

        $this->assertInstanceOf(PurchaseRequisition::class, $pr);
        $prItem = $pr->items()->first();
        $this->assertNotNull($prItem);
        // PR quantity must be 40.0 Legs (NOT 10 Tables)
        $this->assertEquals(40.0, (float) $prItem->quantity);
    }

    public function test_scenario_j_snapshot_immutability(): void
    {
        $setup = $this->createTableBomAndRouting();

        $order = $this->createOrderHelper($setup['bom'], $setup['routing'], 10.0);

        $op10 = $order->operations()->where('sequence', 10)->first();
        $this->assertEquals(40.0, $op10->target_produced_qty);

        // Edit BOM item quantity to 6 per Table after order creation
        ProductionBomItem::where('bom_id', $setup['bom']->id)
            ->where('material_id', $this->sfgLeg->id)
            ->update(['quantity' => 6.0]);

        // Released order operation target must remain unchanged at 40.0 Legs
        $op10->refresh();
        $this->assertEquals(40.0, $op10->target_produced_qty);
    }
}
