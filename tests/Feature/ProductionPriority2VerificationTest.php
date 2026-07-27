<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionPriority2VerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Product $productA;
    private Uom $uom;
    private Warehouse $warehouse;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionBom $bom;
    private Routing $routing;
    private ProductionOrderService $orderService;
    private CapacityPlanningService $capacityService;
    private SchedulingService $schedulingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'P2 Tenant A',
            'slug' => 'p2-tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'User A',
            'email' => 'usera-p2@example.com',
            'password' => bcrypt('password'),
        ]);

        // Setup Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'P2 Tenant B',
            'slug' => 'p2-tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'User B',
            'email' => 'userb-p2@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->userA);
        session(['tenant_id' => $this->tenantA->id]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Plant A Warehouse',
            'code' => 'WH-A',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Machining Center A',
            'code' => 'WC-MACH-A',
            'overhead_rate' => 45.00,
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'CNC Milling Machine',
            'code' => 'MC-CNC-01',
            'status' => 'active',
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Precision Valve Body',
            'sku' => 'FG-VALVE-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'unit_cost' => 85.00,
            'status' => 'active',
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'bom_number' => 'BOM-VALVE-01',
            'bom_name' => 'Valve Master BOM',
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'routing_code' => 'RT-VALVE-01',
            'name' => 'Valve Machining Routing',
            'status' => 'active',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'CNC Milling',
            'setup_time_minutes' => 10.0,
            'run_time_per_unit' => 20.0,
        ]);

        $this->orderService = app(ProductionOrderService::class);
        $this->capacityService = app(CapacityPlanningService::class);
        $this->schedulingService = app(SchedulingService::class);
    }

    /**
     * P6.1 Test: Valid reschedule outside downtime succeeds.
     */
    public function test_p6_1_valid_reschedule_outside_downtime_succeeds(): void
    {
        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 2.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->orderService->release($order->id, $this->userA->id);
        $schedule = $this->schedulingService->generateForwardSchedule($order, now());
        $schedOp = $schedule->operations->first();

        // Downtime is tomorrow from 14:00 to 16:00
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantA->id,
            'machine_id' => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'reason' => 'Planned Calibration',
            'category' => 'Maintenance',
            'start_time' => now()->tomorrow()->setHour(14)->setMinute(0),
            'end_time' => now()->tomorrow()->setHour(16)->setMinute(0),
            'status' => ProductionMachineDowntime::STATUS_OPEN,
            'created_by' => $this->userA->id,
        ]);

        // Reschedule to tomorrow 09:00 - 10:00 (outside downtime)
        $newStart = now()->tomorrow()->setHour(9)->setMinute(0);
        $this->capacityService->rescheduleOperation($schedOp->id, $newStart, $this->machine->id, 'Normal move', $this->userA->id);

        $this->assertEquals($newStart->toDateTimeString(), $schedOp->fresh()->planned_start->toDateTimeString());
    }

    /**
     * P6.1 Test: Reschedule overlapping active machine downtime is blocked.
     */
    public function test_p6_1_reschedule_overlapping_downtime_is_blocked(): void
    {
        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 2.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->orderService->release($order->id, $this->userA->id);
        $schedule = $this->schedulingService->generateForwardSchedule($order, now());
        $schedOp = $schedule->operations->first();

        $origStart = $schedOp->planned_start->copy();

        // Downtime tomorrow 10:00 to 12:00
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantA->id,
            'machine_id' => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'reason' => 'Emergency Spindle Repair',
            'category' => 'Breakdown',
            'start_time' => now()->tomorrow()->setHour(10)->setMinute(0),
            'end_time' => now()->tomorrow()->setHour(12)->setMinute(0),
            'status' => ProductionMachineDowntime::STATUS_OPEN,
            'created_by' => $this->userA->id,
        ]);

        // Attempt reschedule to start at 11:00 (overlaps downtime)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('active downtime or maintenance');

        try {
            $newStart = now()->tomorrow()->setHour(11)->setMinute(0);
            $this->capacityService->rescheduleOperation($schedOp->id, $newStart, $this->machine->id, 'Overlap test', $this->userA->id);
        } finally {
            // Verify original start time remains unchanged in database
            $this->assertEquals($origStart->toDateTimeString(), $schedOp->fresh()->planned_start->toDateTimeString());
        }
    }

    /**
     * P6.1 Test: Closed / cancelled downtime does NOT block rescheduling.
     */
    public function test_p6_1_closed_downtime_does_not_block_reschedule(): void
    {
        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 2.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->orderService->release($order->id, $this->userA->id);
        $schedule = $this->schedulingService->generateForwardSchedule($order, now());
        $schedOp = $schedule->operations->first();

        // Downtime is closed
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantA->id,
            'machine_id' => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'reason' => 'Finished Repair',
            'category' => 'Breakdown',
            'start_time' => now()->tomorrow()->setHour(10)->setMinute(0),
            'end_time' => now()->tomorrow()->setHour(12)->setMinute(0),
            'status' => ProductionMachineDowntime::STATUS_CLOSED,
            'created_by' => $this->userA->id,
        ]);

        $newStart = now()->tomorrow()->setHour(10)->setMinute(30);
        $this->capacityService->rescheduleOperation($schedOp->id, $newStart, $this->machine->id, 'Move over closed downtime', $this->userA->id);

        $this->assertEquals($newStart->toDateTimeString(), $schedOp->fresh()->planned_start->toDateTimeString());
    }

    /**
     * P6.1 Test: Tenant A downtime does NOT block Tenant B schedules.
     */
    public function test_p6_1_tenant_isolation_on_downtime_check(): void
    {
        // Tenant A downtime
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantA->id,
            'machine_id' => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'reason' => 'Tenant A Breakdown',
            'category' => 'Breakdown',
            'start_time' => now()->tomorrow()->setHour(10)->setMinute(0),
            'end_time' => now()->tomorrow()->setHour(12)->setMinute(0),
            'status' => ProductionMachineDowntime::STATUS_OPEN,
            'created_by' => $this->userA->id,
        ]);

        // Tenant B machine with same ID in Tenant B scope
        $machineB = Machine::create([
            'tenant_id' => $this->tenantB->id,
            'work_center_id' => WorkCenter::create([
                'tenant_id' => $this->tenantB->id,
                'name' => 'Tenant B WC',
                'code' => 'WC-B',
            ])->id,
            'name' => 'Tenant B Machine',
            'code' => 'MC-B',
            'status' => 'active',
        ]);

        // Tenant B rescheduling during Tenant A's downtime slot should succeed for Tenant B's machine
        $this->actingAs($this->userB);
        session(['tenant_id' => $this->tenantB->id]);

        $start = now()->tomorrow()->setHour(10)->setMinute(30);
        $finish = $start->copy()->addHour();

        // Should not throw exception because Tenant A's downtime is isolated to Tenant A
        $this->capacityService->validateMachineAvailability($this->tenantB->id, $machineB->id, $start, $finish);
        $this->assertTrue(true);

        $this->actingAs($this->userA);
        session(['tenant_id' => $this->tenantA->id]);
    }

    /**
     * P1.1 Test: Approving a new BOM revision supersedes the previous approved revision.
     */
    public function test_p1_1_bom_revision_approval_supersedes_older_version(): void
    {
        $bomService = app(\App\Domains\Production\Services\ProductionBomService::class);

        // Historical order created using original approved BOM v1.0.0 ($this->bom)
        $historicalOrder = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->assertEquals($this->bom->id, $historicalOrder->bom_id);

        // Create new BOM revision v2.0.0
        $newBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'bom_number' => 'BOM-VALVE-02',
            'bom_name' => 'Valve Master BOM v2',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1.0,
            'version' => '2.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'pending_approval',
        ]);

        // Approve new BOM version
        $bomService->approve($newBom->id, $this->userA->id);

        // 1. Verify new BOM is approved
        $this->assertEquals('approved', $newBom->fresh()->status);

        // 2. Verify older BOM v1.0.0 status moved to inactive
        $this->assertEquals('inactive', $this->bom->fresh()->status);
        $this->assertNotNull($this->bom->fresh()->expiry_date);

        // 3. Historical order retains original BOM ID reference
        $this->assertEquals($this->bom->id, $historicalOrder->fresh()->bom_id);

        // 4. New order automatically resolves the newly approved BOM v2.0.0
        $newOrder = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->assertEquals($newBom->id, $newOrder->bom_id);
    }

    /**
     * P4.1 Test: Authorized operator can report Andon Alert & Breakdown.
     */
    public function test_p4_1_operator_andon_alert_creates_downtime_and_pauses_operation(): void
    {
        ProductionMachineDowntime::withoutGlobalScopes()->forceDelete();
        $mesService = app(\App\Domains\Production\Services\MesExecutionService::class);

        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 2.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $this->orderService->release($order->id, $this->userA->id);
        $schedule = $this->schedulingService->generateForwardSchedule($order, now());
        $schedOp = $schedule->operations->first();

        // Start operation
        $mesService->startOperation($schedOp->id, $this->machine->id, $this->userA->id);
        $this->assertEquals(ProductionScheduleOperation::STATUS_RUNNING, $schedOp->fresh()->status);

        // Report Andon Breakdown Alert
        $res = $mesService->reportAndonAlert(
            $schedOp->id,
            'Breakdown',
            'critical',
            'Main Motor Overheating',
            'Sparks observed near motor housing',
            $this->userA->id
        );

        // 1. Verify response contains downtime ID
        $this->assertNotNull($res['downtime_id']);

        // 2. Verify machine downtime record created
        $downtime = ProductionMachineDowntime::find($res['downtime_id']);
        $this->assertEquals($this->machine->id, $downtime->machine_id);
        $this->assertEquals('Breakdown', $downtime->category);
        $this->assertEquals('Main Motor Overheating', $downtime->reason);
        $this->assertEquals('open', $downtime->status);

        // 3. Verify running operation was automatically paused
        $this->assertEquals(ProductionScheduleOperation::STATUS_PAUSED, $schedOp->fresh()->status);
    }

    /**
     * P7.1 Test: Logging scrap without NCR creates scrap entry only.
     */
    public function test_p7_1_log_scrap_without_ncr_creates_scrap_record_only(): void
    {
        $executionService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $op = $order->operations->first();

        $ncrCountBefore = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $this->tenantA->id)->count();

        $scrap = $executionService->logScrap(
            $order->id,
            $op->id,
            $this->productA->id,
            1.5,
            'Dimensional tolerance error',
            $this->userA->id,
            null,
            false // createNcr = false
        );

        $this->assertNotNull($scrap->id);
        $this->assertEquals(1.5, (float) $scrap->quantity);
        $this->assertEquals('Dimensional tolerance error', $scrap->reason);

        // Verify NO NCR was created
        $ncrCountAfter = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $this->tenantA->id)->count();
        $this->assertEquals($ncrCountBefore, $ncrCountAfter);
    }

    /**
     * P7.1 Test: Logging scrap with Create NCR enabled creates linked NCR.
     */
    public function test_p7_1_log_scrap_with_ncr_enabled_creates_linked_ncr(): void
    {
        $executionService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        $order = $this->orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $op = $order->operations->first();

        $scrap = $executionService->logScrap(
            $order->id,
            $op->id,
            $this->productA->id,
            2.0,
            'Severe casting porosity',
            $this->userA->id,
            null,
            true, // createNcr = true
            ['category' => 'Material Defect']
        );

        $this->assertNotNull($scrap->id);

        // Verify linked NCR was created with correct context
        $ncr = \App\Domains\Production\Models\ProductionNcr::where('tenant_id', $this->tenantA->id)
            ->where('production_order_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($ncr);
        $this->assertEquals('Material Defect', $ncr->category);
        $this->assertEquals('scrap', $ncr->disposition_type);
        $this->assertEquals($op->id, $ncr->production_order_operation_id);
        $this->assertEquals($this->userA->id, $ncr->operator_id);
        $this->assertStringContainsString('Severe casting porosity', $ncr->description);
    }
}
