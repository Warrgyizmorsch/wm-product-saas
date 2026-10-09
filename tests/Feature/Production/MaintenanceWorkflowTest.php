<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionPmSchedule;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceSpareService;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\PmScheduleService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $spareProduct;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();

        $this->tenant = Tenant::create([
            'name'   => 'Test Maintenance Tenant',
            'slug'   => 'test-maint-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Maint Admin',
            'email'     => 'maintadmin_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->user);
        app(TenantContext::class)->setTenant($this->tenant);
        app(Tenancy::class)->setTenant($this->tenant);
        app()->instance('tenant', $this->tenant);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Test Machining Center',
            'code'                  => 'WC-TEST-01',
            'cost_per_hour'          => 50.00,
            'capacity_per_hour'      => 10.00,
            'efficiency_percentage'  => 100.00,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'          => $this->tenant->id,
            'work_center_id'     => $this->workCenter->id,
            'name'               => 'Test CNC Milling Machine',
            'code'               => 'MCH-CNC-01',
            'status'             => Machine::STATUS_ACTIVE,
            'current_state'      => 'Idle',
            'maintenance_status' => 'none',
        ]);

        $this->spareProduct = Product::create([
            'tenant_id'                   => $this->tenant->id,
            'name'                        => 'Heavy Duty Bearing 6205',
            'sku'                         => 'SPR-BRG-6205',
            'cost_price'                  => 25.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Main Maintenance Warehouse',
            'code'      => 'WH-MAINT-01',
        ]);

        StockService::recordInflow(
            $this->tenant->id,
            $this->spareProduct->id,
            $this->warehouse->id,
            100.00,
            25.00,
            'OpeningStock',
            1
        );
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_pm_schedule_and_computes_due_dates_correctly()
    {
        $pmService = app(PmScheduleService::class);

        $schedule = $pmService->createSchedule($this->tenant->id, [
            'machine_id'               => $this->machine->id,
            'name'                     => 'Monthly Lubrication & Alignment',
            'maintenance_type'         => 'preventive',
            'frequency_type'           => 'days',
            'frequency_value'          => 30,
            'estimated_duration_hours' => 2.0,
            'priority'                 => 'medium',
            'last_completed_date'      => Carbon::today()->subDays(10)->toDateString(),
        ]);

        $this->assertInstanceOf(ProductionPmSchedule::class, $schedule);
        $this->assertEquals(Carbon::today()->addDays(20)->toDateString(), $schedule->next_due_date->toDateString());
        $this->assertFalse($schedule->isDue());
    }

    /** @test */
    public function it_idempotently_generates_pm_work_orders_for_due_schedules()
    {
        $pmService = app(PmScheduleService::class);

        $schedule = $pmService->createSchedule($this->tenant->id, [
            'machine_id'               => $this->machine->id,
            'name'                     => 'Weekly Filter Cleaning',
            'maintenance_type'         => 'preventive',
            'frequency_type'           => 'days',
            'frequency_value'          => 7,
            'next_due_date'            => Carbon::today()->toDateString(),
            'estimated_duration_hours' => 1.0,
            'priority'                 => 'high',
        ]);

        // First generation run
        $generated1 = $pmService->generateDueWorkOrders($this->tenant->id);
        $this->assertCount(1, $generated1);
        $this->assertEquals($schedule->id, $generated1[0]->pm_schedule_id);

        // Second generation run (Idempotency check: should skip because open WO exists)
        $generated2 = $pmService->generateDueWorkOrders($this->tenant->id);
        $this->assertCount(0, $generated2);
    }

    /** @test */
    public function it_starts_work_order_sets_machine_to_under_maintenance_and_blocks_mes()
    {
        $woService  = app(MaintenanceWorkOrderService::class);
        $mesService = app(MesExecutionService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => 'preventive',
            'priority'            => 'high',
            'problem_description' => 'Regular Preventive Maintenance',
            'assignments' => [[
                'assignment_type' => 'internal',
                'technician_id' => $this->user->id,
                'technician_name' => $this->user->name,
            ]],
        ]);

        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Work Order Created',
        ]);
        $this->assertDatabaseMissing('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'event_type'   => 'Machine DT Started',
        ]);

        // Start Work Order
        $startedWo = $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        $this->assertEquals('in_progress', $startedWo->status);
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Machine DT Started',
        ]);
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Maintenance Started',
        ]);
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $this->machine->status);
        $this->assertEquals('Maintenance', $this->machine->current_state);

        // Verify MES execution is BLOCKED when machine is under_maintenance
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Machine [{$this->machine->name}] is not available for production (status: under_maintenance).");

        // Invoke private validation method to test MES interlock directly
        $reflection = new \ReflectionClass($mesService);
        $method     = $reflection->getMethod('validateMachineForExecution');
        $method->setAccessible(true);
        $method->invoke($mesService, $this->machine->id, $this->tenant->id);
    }

    /** @test */
    public function it_reports_emergency_breakdown_and_creates_a_draft_work_order_without_starting_it()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Spindle Bearing Failure & Overheating',
            $this->user->id,
            'critical'
        );

        $this->assertInstanceOf(ProductionMaintenanceWorkOrder::class, $wo);
        $this->assertEquals('breakdown', $wo->type);
        $this->assertEquals('draft', $wo->status);
        $this->assertNotNull($wo->downtime_id);
        $this->assertNotNull(ProductionMachineDowntime::where('tenant_id', $this->tenant->id)
            ->where('id', $wo->downtime_id)
            ->where('status', ProductionMachineDowntime::STATUS_OPEN)
            ->first());
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Work Order Created',
        ]);
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'downtime_id'  => $wo->downtime_id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Machine DT Started',
        ]);
        $this->assertDatabaseMissing('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'event_type'   => 'Maintenance Started',
        ]);

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_INACTIVE, $this->machine->status);
        $this->assertEquals('Breakdown', $this->machine->current_state);
        $this->assertEquals('breakdown', $this->machine->maintenance_status);
    }

    /** @test */
    public function it_issues_spare_parts_via_stock_service_and_updates_costs()
    {
        $woService    = app(MaintenanceWorkOrderService::class);
        $spareService = app(MaintenanceSpareService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => 'preventive',
            'problem_description' => 'Replace Main Drive Bearing',
            'assignments' => [[
                'assignment_type' => 'internal',
                'technician_id' => $this->user->id,
                'technician_name' => $this->user->name,
            ]],
        ]);

        $spare = $spareService->addSpareRequest($wo->id, $this->tenant->id, $this->spareProduct->id, $this->warehouse->id, 2.0);

        $issuedSpare = $spareService->issueSparePart($spare->id, $this->tenant->id, 2.0, $this->user->id);

        $this->assertEquals(2.0, $issuedSpare->issued_qty);
        $this->assertEquals(50.00, $issuedSpare->total_cost);

        $wo->refresh();
        $this->assertEquals(50.00, $wo->spare_parts_cost);

        // Verify stock deducted in inventory
        $stock = ProductWarehouseStock::where('product_id', $this->spareProduct->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(98.00, $stock->quantity);
    }

    /** @test */
    public function it_tracks_breakdown_downtime_without_using_the_obsolete_downtime_log_table()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Spindle Bearing Failure & Overheating',
            $this->user->id,
            'critical'
        );

        $this->assertNotNull($wo->downtime_id);
        $this->assertDatabaseHas('production_machine_downtimes', [
            'id'        => $wo->downtime_id,
            'tenant_id' => $this->tenant->id,
            'machine_id' => $this->machine->id,
            'status'    => 'open',
        ]);
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Machine DT Started',
        ]);
        $this->assertFalse(Schema::hasTable('production_machine_downtime_logs'));
    }

    /** @test */
    public function it_routes_the_explicit_downtime_start_endpoint_to_the_downtime_controller()
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenant->slug,
        ])->post('/production/mes/downtime/start', [
            'machine_id' => $this->machine->id,
            'category'   => 'Breakdown',
            'reason'     => 'Hydraulic pressure drop during cycle.',
            'remarks'    => 'Operator flagged a sudden drop in pressure.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_machine_downtimes', [
            'tenant_id'  => $this->tenant->id,
            'machine_id' => $this->machine->id,
            'category'   => 'Breakdown',
            'status'     => 'open',
        ]);
    }

    /** @test */
    public function it_completes_work_order_closes_downtime_restores_machine_and_updates_pm_schedule()
    {
        $pmService = app(PmScheduleService::class);
        $woService = app(MaintenanceWorkOrderService::class);

        $schedule = $pmService->createSchedule($this->tenant->id, [
            'machine_id'               => $this->machine->id,
            'name'                     => 'Bi-weekly Check',
            'maintenance_type'         => 'preventive',
            'frequency_type'           => 'days',
            'frequency_value'          => 14,
            'next_due_date'            => Carbon::today()->toDateString(),
            'estimated_duration_hours' => 1.5,
            'priority'                 => 'medium',
        ]);

        $woList = $pmService->generateDueWorkOrders($this->tenant->id);
        $wo = $woList[0];
        $woService->createAssignmentsForWorkOrder($wo, [[
            'assignment_type' => 'internal',
            'technician_id' => $this->user->id,
            'technician_name' => $this->user->name,
        ]], $this->user->id);

        $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        // Complete Work Order using the standard completion flow with mechanic cost.
        $completedWo = $woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Inspected machine, adjusted belt tension, completed lubrication.',
            0.0,
            null,
            0.0,
            125.00
        );

        $this->assertEquals('completed', $completedWo->status);
        $this->assertEquals(125.00, $completedWo->mechanic_cost);
        $this->assertEquals(125.00, $completedWo->total_cost);
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $completedWo->id,
            'event_type'   => 'Work Order Completed',
        ]);

        // Verify Machine status restored to active and state to Idle
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('Idle', $this->machine->current_state);
        $this->assertEquals(Carbon::today()->toDateString(), $this->machine->last_maintenance_date->toDateString());

        // Verify PM Schedule next_due_date updated to +14 days from today
        $schedule->refresh();
        $this->assertEquals(Carbon::today()->addDays(14)->toDateString(), $schedule->next_due_date->toDateString());
    }

    /** @test */
    public function it_enforces_tenant_isolation_on_maintenance_records()
    {
        $otherTenant = Tenant::create([
            'name'   => 'Other Tenant',
            'slug'   => 'other-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $pmService = app(PmScheduleService::class);
        $schedule  = $pmService->createSchedule($this->tenant->id, [
            'machine_id'               => $this->machine->id,
            'name'                     => 'Tenant A Schedule',
            'maintenance_type'         => 'preventive',
            'frequency_type'           => 'days',
            'frequency_value'          => 30,
            'estimated_duration_hours' => 1.0,
            'priority'                 => 'medium',
        ]);

        $repo = app(\App\Domains\Production\Repositories\MaintenanceRepositoryInterface::class);

        $found = $repo->findPmSchedule($schedule->id, $otherTenant->id);
        $this->assertNull($found);
    }

    /** @test */
    public function it_resolves_downtime_and_completes_work_order_restoring_machine_active_status()
    {
        $woService       = app(MaintenanceWorkOrderService::class);
        $downtimeService = app(\App\Domains\Production\Services\DowntimeService::class);

        // 1. Report emergency breakdown and keep it in draft until explicitly started
        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Hydraulic Pressure Seal Rupture',
            $this->user->id,
            'critical'
        );

        $this->assertNotNull($wo->downtime_id);
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_DRAFT, $wo->status);

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_INACTIVE, $this->machine->status);
        $this->assertEquals('Breakdown', $this->machine->current_state);

        // 2. Explicitly start the draft work order through the normal maintenance workflow
        $woService->createAssignmentsForWorkOrder($wo, [[
            'assignment_type' => 'internal',
            'technician_id' => $this->user->id,
            'technician_name' => $this->user->name,
        ]], $this->user->id);

        $startedWo = $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS, $startedWo->status);
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $this->machine->refresh()->status);
        $this->assertEquals('Breakdown', $this->machine->current_state);

        // 3. Resolve downtime from MES machine monitor screen (endDowntime)
        $downtimeService->endDowntime(
            $this->tenant->id,
            $wo->downtime_id,
            $this->user->id,
            'Replaced hydraulic seal ring and refilled fluid.'
        );

        // 4. Verify Work Order is now completed
        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);
        $this->assertEquals('Replaced hydraulic seal ring and refilled fluid.', $wo->work_performed);

        // 5. Verify Machine is restored to Active, Idle, and none
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('Idle', $this->machine->current_state);
        $this->assertEquals('none', $this->machine->maintenance_status);
    }

    /** @test */
    public function it_completes_work_order_with_erp_completion_modal_flow_and_restores_machine()
    {
        $woService    = app(MaintenanceWorkOrderService::class);
        $spareService = app(MaintenanceSpareService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => 'preventive',
            'problem_description' => 'Semi-annual calibration and parts check',
            'assignments' => [[
                'assignment_type' => 'internal',
                'technician_id'   => $this->user->id,
                'technician_name' => $this->user->name,
                'hourly_rate'     => 50.00,
            ]],
        ]);

        $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        // Issue spare parts: 2 x $25.00 = $50.00
        $spare = $spareService->addSpareRequest($wo->id, $this->tenant->id, $this->spareProduct->id, $this->warehouse->id, 2.0);
        $spareService->issueSparePart($spare->id, $this->tenant->id, 2.0, $this->user->id);

        $assignment = $wo->assignments()->first();

        // Submit completion using the ERP completion form data
        $captureTime = now()->toISOString();
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenant->slug,
        ])->post("/production/maintenance/work-orders/{$wo->id}/complete", [
            'work_performed'           => 'Calibrated sensors, replaced drive bearings, aligned spindle.',
            'completion_action'        => 'restore',
            'was_machine_scraped'      => '0',
            'completed_at'             => $captureTime,
            'additional_cost'          => 25.00,
            'external_parts_purchased' => '1',
            'assignments' => [
                [
                    'id'           => $assignment->id,
                    'hourly_rate'  => 60.00, // edited hourly rate
                    'worked_hours' => 2.50,  // edited worked hours (60 * 2.5 = 150)
                ],
            ],
        ]);

        $response->assertRedirect();
        $wo->refresh();

        // Mechanic cost: 60 * 2.5 = 150.00
        $this->assertEquals(150.00, $wo->mechanic_cost);
        // Preserved spare parts cost: 50.00
        $this->assertEquals(50.00, $wo->spare_parts_cost);
        // Additional expense / overhead: 25.00
        $this->assertEquals(25.00, $wo->additional_cost);
        $this->assertEquals(25.00, $wo->additional_expense);
        // Total cost: 150 + 50 + 25 = 225.00
        $this->assertEquals(225.00, $wo->total_cost);
        // Flags
        $this->assertTrue($wo->external_parts_purchased);
        $this->assertFalse($wo->was_machine_scraped);
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);

        // Machine restored
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('Idle', $this->machine->current_state);
    }

    /** @test */
    public function it_completes_work_order_with_complete_and_scrap_and_decommissions_machine()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => 'breakdown',
            'problem_description' => 'Motor catastrophic burn-out',
            'assignments' => [[
                'assignment_type' => 'internal',
                'technician_id'   => $this->user->id,
                'technician_name' => $this->user->name,
                'hourly_rate'     => 40.00,
            ]],
        ]);

        $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        $response = $this->withHeaders([
            'X-Tenant' => $this->tenant->slug,
        ])->post("/production/maintenance/work-orders/{$wo->id}/complete", [
            'work_performed'      => 'Inspected internal coils; severe unrecoverable damage. Recommended scrapping.',
            'completion_action'   => 'scrap',
            'was_machine_scraped' => '1',
            'decision_note'       => 'Cost to rewind motor exceeds machine book value.',
            'additional_cost'     => 0.00,
        ]);

        $response->assertRedirect();
        $wo->refresh();

        $this->assertTrue($wo->was_machine_scraped);
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);

        // Machine decommissioned
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_DECOMMISSIONED, $this->machine->status);
        $this->assertEquals('Decommissioned', $this->machine->current_state);
    }

    /** @test */
    public function it_calculates_worked_hours_from_completion_time_minus_max_start_and_assignment_time()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        // Maintenance started at 10:00
        $maintStart = Carbon::parse('2026-10-08 10:00:00');
        // Assignment created at 10:30 (after maintenance start)
        $assignedAt = Carbon::parse('2026-10-08 10:30:00');
        // Completion modal opened at 12:30 (2 hours after assignment)
        $completedAt = Carbon::parse('2026-10-08 12:30:00');

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => 'preventive',
            'problem_description' => 'Timing test',
            'actual_start'        => $maintStart,
        ]);

        $assignment = \App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'       => $this->tenant->id,
            'work_order_id'   => $wo->id,
            'technician_id'   => $this->user->id,
            'technician_name' => $this->user->name,
            'assignment_type' => 'internal',
            'assigned_at'     => $assignedAt,
            'hourly_rate'     => 40.00,
            'worked_hours'    => 0.00,
        ]);

        // Complete without assignmentsData: syncAssignmentWorkedHoursForCompletion calculates worked_hours
        $woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Finished timing check.',
            0.0,
            null,
            0.0,
            0.0,
            null,
            false,
            0.0,
            null,
            $completedAt
        );

        $assignment->refresh();
        // MAX(10:00, 10:30) is 10:30; 12:30 - 10:30 = 2.0 hours
        $this->assertEquals(2.0, (float) $assignment->worked_hours);

        $wo->refresh();
        // Mechanic cost = 40.00 * 2.0 = 80.00
        $this->assertEquals(80.00, $wo->mechanic_cost);
    }

    /** @test */
    public function it_verifies_obsolete_columns_removed_and_new_columns_present_in_database_schema()
    {
        $table = 'production_maintenance_work_orders';

        $obsoleteCols = [
            'labor_hours',
            'repair_hours',
            'labor_cost_rate',
            'labor_cost',
            'repair_cost',
            'mechanic_type',
            'external_mechanic_cost',
            'internal_mechanic_cost',
            'scrap_machine',
            'scrap_value',
        ];

        foreach ($obsoleteCols as $col) {
            $this->assertFalse(
                Schema::hasColumn($table, $col),
                "Obsolete column [{$col}] should NOT exist in [{$table}]."
            );
        }

        $expectedCols = [
            'spare_parts_cost',
            'total_cost',
            'mechanic_cost',
            'additional_cost',
            'was_machine_scraped',
            'external_parts_purchased',
        ];

        foreach ($expectedCols as $col) {
            $this->assertTrue(
                Schema::hasColumn($table, $col),
                "Expected column [{$col}] should exist in [{$table}]."
            );
        }
    }
}
