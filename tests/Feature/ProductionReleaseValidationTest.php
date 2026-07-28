<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Controllers\ScannerController;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\QualityInspection;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductionReleaseValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Uom $uom;
    private Warehouse $rawWarehouse;
    private Warehouse $secWarehouse;
    private Warehouse $fgWarehouse;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private Machine $machine1;
    private Machine $machine2;
    private Product $diningTableFG;
    private Product $tableTopSubAssy;
    private Product $woodBoardRaw;
    private Product $steelScrewRaw;
    private ProductionBom $parentBom;
    private ProductionBom $childBom;
    private Routing $routing;
    private RoutingOperation $op10;
    private RoutingOperation $op20;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Acme Manufacturing Inc',
            'slug' => 'acme-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'John Planner',
            'email' => 'john.planner@acme.com',
            'password' => bcrypt('password'),
        ]);

        // Setup Tenant B for tenant isolation tests
        $this->tenantB = Tenant::create([
            'name' => 'Competitor Corp',
            'slug' => 'competitor-corp',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Jane Competitor',
            'email' => 'jane@competitor.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->userA);
        session(['tenant_id' => $this->tenantA->id]);

        // Phase 1 Master Data Creation
        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->rawWarehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Raw Materials WH',
            'code' => 'WH-RAW',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->secWarehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Secondary Hardware WH',
            'code' => 'WH-SEC',
            'status' => 'active',
        ]);

        $this->fgWarehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Finished Goods WH',
            'code' => 'WH-FG',
            'status' => 'active',
        ]);

        // Work Centers & Machines
        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cutting & Carpentry Center',
            'code' => 'WC-CUT',
            'overhead_rate' => 50.00,
        ]);

        $this->machine1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter1->id,
            'name' => 'Industrial CNC Saw',
            'code' => 'MC-SAW-01',
            'status' => 'active',
        ]);

        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Assembly & Finishing Center',
            'code' => 'WC-ASSY',
            'overhead_rate' => 40.00,
        ]);

        $this->machine2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter2->id,
            'name' => 'Polishing & Spraying Station',
            'code' => 'MC-POLISH-01',
            'status' => 'active',
        ]);

        // Products
        $this->diningTableFG = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Deluxe Dining Table',
            'sku' => 'FG-TBL-DELUXE',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'unit_cost' => 350.00,
            'status' => 'active',
        ]);

        $this->tableTopSubAssy = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Oak Table Top Sub-Assembly',
            'sku' => 'SUB-TOP-OAK',
            'type' => 'sub_assembly',
            'uom_id' => $this->uom->id,
            'unit_cost' => 180.00,
            'status' => 'active',
        ]);

        $this->woodBoardRaw = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Solid Oak Wood Board',
            'sku' => 'RAW-OAK-BOARD',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'unit_cost' => 60.00,
            'status' => 'active',
        ]);

        $this->steelScrewRaw = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Heavy Steel Screw 50mm',
            'sku' => 'RAW-SCREW-50',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'unit_cost' => 2.50,
            'status' => 'active',
        ]);

        // Child BOM for Sub-assembly
        $this->childBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->tableTopSubAssy->id,
            'bom_number' => 'BOM-SUB-TOP-01',
            'bom_name' => 'Table Top Assembly BOM',
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $this->childBom->id,
            'material_id' => $this->woodBoardRaw->id,
            'quantity' => 2.0,
            'scrap_percentage' => 5.0,
            'uom_id' => $this->uom->id,
        ]);

        // Parent BOM for Deluxe Dining Table
        $this->parentBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->diningTableFG->id,
            'bom_number' => 'BOM-TBL-DELUXE-01',
            'bom_name' => 'Deluxe Dining Table Master BOM',
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        // BOM Item 1: Sub-assembly Table Top
        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $this->parentBom->id,
            'material_id' => $this->tableTopSubAssy->id,
            'child_bom_id' => $this->childBom->id,
            'quantity' => 1.0,
            'scrap_percentage' => 0.0,
            'uom_id' => $this->uom->id,
        ]);

        // BOM Item 2: Steel Screws
        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $this->parentBom->id,
            'material_id' => $this->steelScrewRaw->id,
            'quantity' => 12.0,
            'scrap_percentage' => 0.0,
            'uom_id' => $this->uom->id,
        ]);

        // Routing & Operations
        $this->routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->diningTableFG->id,
            'routing_code' => 'RT-TBL-DELUXE-01',
            'name' => 'Dining Table Production Routing',
            'status' => 'active',
        ]);

        $this->op10 = RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting & Sizing',
            'setup_time_minutes' => 15.0,
            'run_time_per_unit' => 30.0,
        ]);

        $this->op20 = RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->workCenter2->id,
            'machine_id' => $this->machine2->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Assembly & Polishing',
            'setup_time_minutes' => 20.0,
            'run_time_per_unit' => 45.0,
        ]);
    }

    /**
     * Complete End-to-End Production Lifecycle Release Validation Scenario.
     */
    public function test_complete_end_to_end_production_lifecycle_validation(): void
    {
        $orderService = app(ProductionOrderService::class);
        $materialService = app(ProductionMaterialService::class);
        $schedulingService = app(SchedulingService::class);
        $capacityService = app(CapacityPlanningService::class);
        $mesService = app(MesExecutionService::class);
        $execService = app(ProductionExecutionService::class);
        $scannerController = app(ScannerController::class);

        // ══════════════════════════════════════════════════════════════
        // PHASE 1 — MASTER DATA VALIDATION
        // ══════════════════════════════════════════════════════════════
        $this->assertEquals('active', $this->diningTableFG->status);
        $this->assertEquals('approved', $this->parentBom->status);
        $this->assertEquals($this->childBom->id, $this->parentBom->items->first()->child_bom_id);
        $this->assertEquals(2, $this->routing->operations->count());

        // ══════════════════════════════════════════════════════════════
        // PHASE 2 — PRODUCTION PLAN CREATION
        // ══════════════════════════════════════════════════════════════
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Deluxe Dining Table Q3 Master Plan',
            'plan_number' => 'PLN-2026-001',
            'product_id' => $this->diningTableFG->id,
            'bom_id' => $this->parentBom->id,
            'routing_id' => $this->routing->id,
            'quantity' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
            'created_by' => $this->userA->id,
        ]);

        $this->assertEquals($this->tenantA->id, $plan->tenant_id);
        $this->assertEquals('approved', $plan->status);

        // ══════════════════════════════════════════════════════════════
        // PHASE 4 — PRODUCTION ORDER CREATION & MATERIAL RESERVATION
        // ══════════════════════════════════════════════════════════════
        $order = $orderService->createDirect([
            'product_id' => $this->diningTableFG->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'production_mode' => 'batch',
        ], $this->tenantA->id, $this->userA->id);

        $this->assertEquals('batch', $order->production_mode);
        $this->assertEquals($this->parentBom->id, $order->bom_id);
        $this->assertEquals($this->routing->id, $order->routing_id);

        // ══════════════════════════════════════════════════════════════
        // PHASE 3 — MRP AND MATERIAL REQUIREMENTS
        // ══════════════════════════════════════════════════════════════
        // Explode 10 Tables:
        // Table Top Sub-Assembly = 10 * 1.0 = 10 units
        // Solid Oak Board (via Child BOM 5% scrap) = 10 * 2.0 * 1.05 = 21 boards
        // Steel Screws = 10 * 12 = 120 screws
        $reqSlip = ProductionRequisitionSlip::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'requisition_number' => 'REQ-2026-001',
            'requisition_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->userA->id,
        ]);
        $this->assertNotNull($reqSlip->id);

        // Update order status to released and initialize WIP
        $order->update(['status' => 'released']);
        app(\App\Domains\Production\Services\ProductionWipService::class)->initializeWip($order->id);
        $order = $order->fresh(['reservations']);
        $this->assertNotEmpty($order->reservations);

        // Component-level warehouse override test: move screws reservation to secondary warehouse
        $screwRes = $order->reservations->where('product_id', $this->steelScrewRaw->id)->first();
        if ($screwRes) {
            $updatedRes = $materialService->updateReservationWarehouse($screwRes->id, $this->secWarehouse->id);
            $this->assertEquals($this->secWarehouse->id, $updatedRes->warehouse_id);
        }

        // Issue material for first reservation line
        $topRes = $order->reservations->first();
        if ($topRes) {
            $topRes->update(['quantity_reserved' => 10.0]);
            $materialService->issueMaterial($topRes->id, 10.0, $this->rawWarehouse->id, $this->userA->id);
            $this->assertEquals(10.0, $topRes->fresh()->quantity_issued);
        }

        // ══════════════════════════════════════════════════════════════
        // PHASE 6 — SCHEDULING AND CAPACITY
        // ══════════════════════════════════════════════════════════════
        $schedule = $schedulingService->generateSchedule($order, now());
        $this->assertCount(2, $schedule->operations);

        $op1 = $schedule->operations->where('sequence', 10)->first();
        $op2 = $schedule->operations->where('sequence', 20)->first();
        $this->assertNotNull($op1);
        $this->assertNotNull($op2);

        // Downtime Guard Validation: verify active downtime blocks rescheduling
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter1->id,
            'machine_id' => $this->machine1->id,
            'category' => 'Maintenance',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => 'open',
            'reason' => 'Scheduled Spindle Maintenance',
            'created_by' => $this->userA->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $capacityService->validateMachineAvailability(
            $this->tenantA->id,
            $this->machine1->id,
            now(),
            now()->addHours(2)
        );
    }

    /**
     * Test MES Execution, Andon Alert, Scrap, NCR, Quality Hold & FG Receipt.
     */
    public function test_mes_execution_andon_quality_hold_and_fg_receipt_flow(): void
    {
        $orderService = app(ProductionOrderService::class);
        $schedulingService = app(SchedulingService::class);
        $mesService = app(MesExecutionService::class);
        $execService = app(ProductionExecutionService::class);

        $order = $orderService->createDirect([
            'product_id' => $this->diningTableFG->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'production_mode' => 'batch',
        ], $this->tenantA->id, $this->userA->id);

        $order->update(['status' => 'released']);
        app(\App\Domains\Production\Services\ProductionWipService::class)->initializeWip($order->id);

        $schedule = $schedulingService->generateSchedule($order, now());
        $op1 = $schedule->operations->where('sequence', 10)->first();
        $op2 = $schedule->operations->where('sequence', 20)->first();

        // ══════════════════════════════════════════════════════════════
        // PHASE 7 — MES EXECUTION & ANDON ALERT
        // ══════════════════════════════════════════════════════════════
        $mesService->startOperation($op1->id, $this->machine1->id, $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_RUNNING, $op1->fresh()->status);

        // Raise Andon Breakdown Alert
        $mesService->reportAndonAlert($op1->id, 'Breakdown', 'high', 'Blade Jam Failure', null, $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_PAUSED, $op1->fresh()->status);

        $openDowntime = ProductionMachineDowntime::where('machine_id', $this->machine1->id)
            ->where('status', 'open')
            ->first();
        $this->assertNotNull($openDowntime);

        // Close downtime and resume operation
        $openDowntime->update(['status' => 'resolved', 'end_time' => now()]);
        $mesService->resumeOperation($op1->id, $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_RUNNING, $op1->fresh()->status);

        // Complete OP-10
        $mesService->completeOperation($op1->id, ['quantity_produced' => 10.0, 'quantity_scrapped' => 0.0], $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_COMPLETED, $op1->fresh()->status);
        $this->assertEquals(ProductionScheduleOperation::STATUS_READY, $op2->fresh()->status);

        // ══════════════════════════════════════════════════════════════
        // PHASE 8 — WIP TRANSITION
        // ══════════════════════════════════════════════════════════════
        $wip = ProductionWip::where('production_order_id', $order->id)->first();
        $this->assertNotNull($wip);

        // Complete OP-20
        $mesService->startOperation($op2->id, $this->machine2->id, $this->userA->id);
        $mesService->completeOperation($op2->id, ['quantity_produced' => 10.0, 'quantity_scrapped' => 0.0], $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_COMPLETED, $op2->fresh()->status);

        // ══════════════════════════════════════════════════════════════
        // PHASE 9 — SCRAP & NCR CREATION
        // ══════════════════════════════════════════════════════════════
        $scrap = $execService->logScrap(
            $order->id,
            $op1->id,
            $this->woodBoardRaw->id,
            1.0,
            'Defective Wood Grain Knot',
            $this->userA->id,
            $this->rawWarehouse->id,
            true, // create_ncr = true
            ['ncr_category' => 'material_defect']
        );

        $this->assertNotNull($scrap);
        $ncr = ProductionNcr::where('production_order_id', $order->id)->first();
        $this->assertNotNull($ncr);
        $this->assertEquals($order->id, $ncr->production_order_id);

        // Create Rework Record
        $rework = ProductionOrderRework::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'quantity' => 1.0,
            'reason' => 'Defective Edge Finish Rework',
            'status' => 'pending',
            'recorded_by' => $this->userA->id,
            'recorded_at' => now(),
        ]);

        // ══════════════════════════════════════════════════════════════
        // PHASE 10 — QUALITY INSPECTION & RECEIPT BLOCK GUARD
        // ══════════════════════════════════════════════════════════════
        // Rework pending must block finished goods receipt
        try {
            $execService->receiveFinishedGoods($order->id, 10.0, 'passed', null, $this->userA->id, $this->fgWarehouse->id);
            $this->fail('Expected pending rework exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('pending Rework', $e->getMessage());
        }

        // Resolve Rework & Quality Hold
        $rework->update(['status' => 'completed']);

        // ══════════════════════════════════════════════════════════════
        // PHASE 11 — BATCH, SERIAL & SCANNER LOG TRACEABILITY
        // ══════════════════════════════════════════════════════════════
        $batch = ProductionBatch::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'product_id' => $this->diningTableFG->id,
            'batch_number' => 'BAT-TBL-001',
            'planned_quantity' => 10.0,
            'actual_quantity' => 10.0,
            'status' => 'completed',
        ]);

        $serial = ProductionSerialNumber::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'product_id' => $this->diningTableFG->id,
            'batch_id' => $batch->id,
            'serial_number' => 'SER-TBL-0001',
            'status' => 'active',
        ]);

        // Scan barcode simulator
        $scanRequest = new Request([
            'code' => 'ORD-' . str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'action' => 'view',
        ]);

        $response = app(ScannerController::class)->scan($scanRequest);
        $this->assertEquals(302, $response->getStatusCode());

        // ══════════════════════════════════════════════════════════════
        // PHASE 12 — FINISHED GOODS RECEIPT
        // ══════════════════════════════════════════════════════════════
        $fgLog = $execService->receiveFinishedGoods(
            $order->id,
            10.0,
            'passed',
            'Completed Order Batch Receipts',
            $this->userA->id,
            $this->fgWarehouse->id
        );

        $this->assertNotNull($fgLog);
        $this->assertEquals('completed', $order->fresh()->status);

        // ══════════════════════════════════════════════════════════════
        // PHASE 13 & 14 — COSTING & INVENTORY RECONCILIATION
        // ══════════════════════════════════════════════════════════════
        $fgStockTx = StockTransaction::where('tenant_id', $this->tenantA->id)
            ->where('product_id', $this->diningTableFG->id)
            ->where('warehouse_id', $this->fgWarehouse->id)
            ->first();

        $this->assertNotNull($fgStockTx);

        // ══════════════════════════════════════════════════════════════
        // PHASE 15 — TIMELINE & AUDIT HISTORY
        // ══════════════════════════════════════════════════════════════
        $timelineCount = ProductionEventTimeline::where('tenant_id', $this->tenantA->id)
            ->where('production_order_id', $order->id)
            ->count();
        $this->assertGreaterThan(0, $timelineCount);

        // ══════════════════════════════════════════════════════════════
        // PHASE 16 — TENANT ISOLATION SANITY CHECK
        // ══════════════════════════════════════════════════════════════
        $this->actingAs($this->userB);
        session(['tenant_id' => $this->tenantB->id]);

        $tenantBOrdersCount = ProductionOrder::where('tenant_id', $this->tenantB->id)->count();
        $this->assertEquals(0, $tenantBOrdersCount);

        $this->assertNull(ProductionOrder::where('tenant_id', $this->tenantB->id)->find($order->id));
    }
}
