<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\MaintenanceWorkflowService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderCompletionValidator;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SubcontractProcurementOrchestrator;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplexIndustrialManufacturingScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Uom $pcsUom;
    protected Uom $mtrUom;

    protected Product $fgCrusher;
    protected Product $sfgFrame;
    protected Product $sfgPitman;
    protected Product $sfgFlywheel;

    protected Product $compSidePlate;
    protected Product $compBasePlate;
    protected Product $compSupportBeam;
    protected Product $compShaft;
    protected Product $compBearing;
    protected Product $compFlywheelDisk;

    protected Product $rawSteelPlate;
    protected Product $rawSteelBar;

    protected WorkCenter $wcCutting;
    protected WorkCenter $wcMachining;
    protected WorkCenter $wcWelding;
    protected WorkCenter $wcAssembly;

    protected Machine $machineCncCut;
    protected Machine $machineLathe;
    protected Machine $machineWelder;
    protected Vendor $subcontractHeatTreater;

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

        $this->mtrUom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Meters',
            'code' => 'MTR',
            'symbol' => 'm',
            'category' => 'length',
        ]);

        // 1. Products
        $this->fgCrusher = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jaw Crusher Assembly',
            'sku' => 'FG-CRUSHER-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
            'cost_price' => 15000.0,
        ]);

        $this->sfgFrame = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Crusher Frame Assembly',
            'sku' => 'SFG-FRAME-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgPitman = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pitman Assembly',
            'sku' => 'SFG-PITMAN-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->sfgFlywheel = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flywheel Assembly',
            'sku' => 'SFG-FLYWHEEL-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->compSidePlate = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Side Plate 20mm',
            'sku' => 'COMP-PLATE-SIDE',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->compBasePlate = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Base Plate 25mm',
            'sku' => 'COMP-PLATE-BASE',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->compSupportBeam = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Structural Beam',
            'sku' => 'COMP-BEAM-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->compShaft = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Eccentric Shaft',
            'sku' => 'COMP-SHAFT-001',
            'type' => 'manufactured',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->compBearing = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Spherical Roller Bearing',
            'sku' => 'RAW-BEARING-001',
            'type' => 'purchased',
            'uom_id' => $this->pcsUom->id,
        ]);

        $this->rawSteelPlate = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Steel Plate Stock',
            'sku' => 'RAW-STEEL-PLATE',
            'type' => 'purchased',
            'uom_id' => $this->pcsUom->id,
        ]);

        // Work Centers & Machines
        $this->wcCutting = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Cutting Station',
            'code' => 'WC-CUT-HEAVY',
            'is_active' => true,
        ]);

        $this->wcMachining = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CNC Machining Station',
            'code' => 'WC-CNC-01',
            'is_active' => true,
        ]);

        $this->wcWelding = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Welding Cell',
            'code' => 'WC-WELD-01',
            'is_active' => true,
        ]);

        $this->wcAssembly = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Assembly Bay',
            'code' => 'WC-ASSY-MAIN',
            'is_active' => true,
        ]);

        $this->machineCncCut = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wcCutting->id,
            'name' => 'CNC Plasma Cutter 01',
            'code' => 'MCH-CUT-01',
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $this->machineLathe = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wcMachining->id,
            'name' => 'CNC Heavy Lathe 01',
            'code' => 'MCH-LATHE-01',
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $this->machineWelder = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wcWelding->id,
            'name' => 'Robotic Welder Cell',
            'code' => 'MCH-WELD-01',
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $this->subcontractHeatTreater = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ABC Thermal Heat Treatment Solutions',
            'code' => 'VEND-HEAT-001',
            'is_active' => true,
        ]);
    }

    public function test_full_complex_industrial_manufacturing_lifecycle(): void
    {
        // 1. Build Multi-Level BOM for Jaw Crusher
        // Base Qty = 1 Crusher
        $bomFg = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CRUSHER-FG',
            'bom_name' => 'Jaw Crusher Master BOM',
            'product_id' => $this->fgCrusher->id,
            'base_quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'version' => '1.0',
        ]);

        // FG BOM Items: 1 Frame, 1 Pitman, 2 Side Plates, 4 Support Beams
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bomFg->id,
            'material_id' => $this->compSidePlate->id,
            'quantity' => 2.0, // 2 Side Plates / Crusher
            'uom_id' => $this->pcsUom->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bomFg->id,
            'material_id' => $this->compSupportBeam->id,
            'quantity' => 4.0, // 4 Support Beams / Crusher
            'uom_id' => $this->pcsUom->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bomFg->id,
            'material_id' => $this->sfgFrame->id,
            'quantity' => 1.0, // 1 Frame / Crusher
            'uom_id' => $this->pcsUom->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bomFg->id,
            'material_id' => $this->compShaft->id,
            'quantity' => 1.0, // 1 Shaft / Crusher
            'uom_id' => $this->pcsUom->id,
        ]);

        // 2. Build Master Routing with Operations
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-CRUSHER-MASTER',
            'name' => 'Jaw Crusher Master Routing',
            'product_id' => $this->fgCrusher->id,
            'status' => 'active',
            'version' => '1.0',
        ]);

        // OP10: Side Plate Plasma Cutting (Side Plate, Target 10)
        $op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Side Plate Plasma Cutting',
            'work_center_id' => $this->wcCutting->id,
            'machine_id' => $this->machineCncCut->id,
            'setup_time_minutes' => 15.0,
            'processing_time_minutes' => 8.0, // 8 min / Side Plate
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op10->id,
            'material_id' => $this->compSidePlate->id,
            'quantity' => 2.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        // OP20: Support Beam Cutting (Support Beam, Target 20)
        $op20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Support Beam Cutting',
            'work_center_id' => $this->wcCutting->id,
            'machine_id' => $this->machineCncCut->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 5.0, // 5 min / Support Beam
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op20->id,
            'material_id' => $this->compSupportBeam->id,
            'quantity' => 4.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        // OP30: Frame Heavy Welding (Frame SFG, Target 5)
        $op30 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Frame Heavy Welding Assembly',
            'work_center_id' => $this->wcWelding->id,
            'machine_id' => $this->machineWelder->id,
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 30.0, // 30 min / Frame
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op30->id,
            'material_id' => $this->sfgFrame->id,
            'quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        // OP40: Shaft Turning & Heat Treatment (Shaft, Target 5, Subcontract)
        $op40 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 40,
            'operation_number' => 'OP40',
            'name' => 'Shaft Heat Treatment (Outsourced)',
            'work_center_id' => $this->wcMachining->id,
            'is_external' => true,
            'vendor_id' => $this->subcontractHeatTreater->id,
            'subcontract_cost_per_unit' => 250.0,
            'setup_time_minutes' => 0.0,
            'processing_time_minutes' => 20.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op40->id,
            'material_id' => $this->compShaft->id,
            'quantity' => 1.0,
            'uom_id' => $this->pcsUom->id,
        ]);

        // OP50: Final Jaw Crusher Assembly (FG, Target 5)
        $op50 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 50,
            'operation_number' => 'OP50',
            'name' => 'Final Jaw Crusher Assembly',
            'work_center_id' => $this->wcAssembly->id,
            'setup_time_minutes' => 60.0,
            'processing_time_minutes' => 120.0, // 120 min / Crusher
        ]);

        // 3. Create Production Order for 5 Jaw Crushers
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-CRUSHER-5',
            'product_id' => $this->fgCrusher->id,
            'bom_id' => $bomFg->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        // Snapshot Routings
        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bomFg,
            $routing,
            5.0,
            $this->tenant->id,
            $this->user->id
        );

        $order->refresh();

        // 4. Verify Operation Targets & Component Quantities
        $opSidePlate = $order->operations()->where('sequence', 10)->first();
        $opBeam = $order->operations()->where('sequence', 20)->first();
        $opFrame = $order->operations()->where('sequence', 30)->first();
        $opShaft = $order->operations()->where('sequence', 40)->first();
        $opFinal = $order->operations()->where('sequence', 50)->first();

        // 5 Crushers * 2 Side Plates = 10 Side Plates
        $this->assertEquals(10.0, $opSidePlate->target_produced_qty);
        // 5 Crushers * 4 Beams = 20 Support Beams
        $this->assertEquals(20.0, $opBeam->target_produced_qty);
        // 5 Crushers * 1 Frame = 5 Frames
        $this->assertEquals(5.0, $opFrame->target_produced_qty);
        // 5 Crushers * 1 Shaft = 5 Shafts
        $this->assertEquals(5.0, $opShaft->target_produced_qty);
        // 5 Crushers = 5 Crushers Final
        $this->assertEquals(5.0, $opFinal->target_produced_qty);

        // 5. Verify Scheduling Duration (No double multiplication)
        // OP10 duration = setup (15) + (10 Side Plates * 8 min) = 95 minutes
        $timesOp10 = app(SchedulingService::class)->calculateOperationTimes($opSidePlate, $opSidePlate->target_produced_qty);
        $this->assertEquals(95.0, $timesOp10['total_minutes']);

        // OP30 duration = setup (30) + (5 Frames * 30 min) = 180 minutes
        $timesOp30 = app(SchedulingService::class)->calculateOperationTimes($opFrame, $opFrame->target_produced_qty);
        $this->assertEquals(180.0, $timesOp30['total_minutes']);

        // 6. Test Batch Production Splitting (Batch A = 2 Crushers, Batch B = 3 Crushers)
        $batchService = app(BatchProductionService::class);
        $batchA = $batchService->createBatch($this->tenant->id, $order->id, $this->fgCrusher->id, 2.0, $this->user->id);
        $batchB = $batchService->createBatch($this->tenant->id, $order->id, $this->fgCrusher->id, 3.0, $this->user->id);

        $targetSidePlateA = ($batchA->planned_quantity / $order->quantity_ordered) * $opSidePlate->target_produced_qty;
        $targetSidePlateB = ($batchB->planned_quantity / $order->quantity_ordered) * $opSidePlate->target_produced_qty;

        $this->assertEquals(4.0, $targetSidePlateA);
        $this->assertEquals(6.0, $targetSidePlateB);
        $this->assertEquals(10.0, $targetSidePlateA + $targetSidePlateB);

        // 7. Partial MES Progress & Intermediate Isolation (Log 4 -> 3 -> 3 Side Plates)
        $mesService = app(MesExecutionService::class);
        $order->status = ProductionOrder::STATUS_IN_PROGRESS;
        $order->save();
        $opSidePlate->status = ProductionOrderOperation::STATUS_RUNNING;
        $opSidePlate->save();

        // Log 4 Side Plates
        $opSidePlate->quantity_produced += 4.0;
        $opSidePlate->save();
        $opSidePlate->refresh();
        $order->refresh();
        $this->assertEquals(4.0, $opSidePlate->quantity_produced);
        $this->assertEquals(0.0, $order->quantity_produced); // Parent FG produced stays 0!

        // Log 3 Side Plates
        $opSidePlate->quantity_produced += 3.0;
        $opSidePlate->save();
        $opSidePlate->refresh();
        $this->assertEquals(7.0, $opSidePlate->quantity_produced);

        // Log 3 Side Plates (Reaches 10/10 Side Plates target)
        $opSidePlate->quantity_produced += 3.0;
        $opSidePlate->status = ProductionOrderOperation::STATUS_COMPLETED;
        $opSidePlate->save();
        $opSidePlate->refresh();
        $this->assertEquals(10.0, $opSidePlate->quantity_produced);
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $opSidePlate->status);
        $this->assertEquals(0.0, $order->quantity_produced); // Parent FG produced STILL 0!

        // 8. Machine Breakdown Handling
        $this->machineWelder->status = Machine::STATUS_UNDER_MAINTENANCE;
        $this->machineWelder->save();
        $this->machineWelder->refresh();
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $this->machineWelder->status);

        // Resolve maintenance & restore machine
        $this->machineWelder->status = Machine::STATUS_ACTIVE;
        $this->machineWelder->save();
        $this->machineWelder->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machineWelder->status);

        // 9. Subcontract Operation Execution (5 Shafts Heat Treatment)
        $subOrchestrator = app(SubcontractProcurementOrchestrator::class);
        $subReceipt = app(SubcontractReceiptOrchestrator::class);

        $pr = $subOrchestrator->generateSubcontractRequisition($opShaft, $this->tenant->id, $this->user->id);
        $this->assertEquals(5.0, $pr->items->first()->quantity); // Uses 5 Shafts target

        // Simulate partial receipt of 3 Shafts
        $opShaft->quantity_produced = 3.0;
        $opShaft->save();
        $opShaft->refresh();
        $this->assertEquals(3.0, $opShaft->quantity_produced);

        // Complete remaining 2 Shafts receipt
        $opShaft->quantity_produced = 5.0;
        $opShaft->status = ProductionOrderOperation::STATUS_COMPLETED;
        $opShaft->save();

        // Complete Frame Welding (5 Frames) & Beams (20 Beams)
        $opBeam->quantity_produced = 20.0;
        $opBeam->status = ProductionOrderOperation::STATUS_COMPLETED;
        $opBeam->save();

        $opFrame->quantity_produced = 5.0;
        $opFrame->status = ProductionOrderOperation::STATUS_COMPLETED;
        $opFrame->save();

        // 10. Final FG Assembly & Receipt Validation
        $opFinal->quantity_produced = 5.0;
        $opFinal->status = ProductionOrderOperation::STATUS_COMPLETED;
        $opFinal->save();

        $order->quantity_produced = 5.0;
        $order->status = ProductionOrder::STATUS_COMPLETED;
        $order->save();

        $order->refresh();
        $this->assertEquals(5.0, $order->quantity_produced);

        // 11. Production Order Costing Rollup
        $costService = app(ProductionCostService::class);
        $subCost = (float) ($opShaft->subcontract_cost_per_unit * $opShaft->target_produced_qty);
        // 5 Shafts * 250 = 1250
        $this->assertEquals(1250.0, $subCost);
    }
}
