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

    /** @test */
    public function it_cancels_breakdown_work_order_restores_machine_closes_downtime_and_saves_reason()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Motor overheating breakdown',
            $this->user->id,
            'critical'
        );

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_INACTIVE, $this->machine->status);
        $this->assertEquals('breakdown', $this->machine->maintenance_status);
        $this->assertEquals('Breakdown', $this->machine->current_state);

        $downtimeId = $wo->downtime_id;
        $this->assertNotNull($downtimeId);
        $downtime = ProductionMachineDowntime::find($downtimeId);
        $this->assertEquals(ProductionMachineDowntime::STATUS_OPEN, $downtime->status);

        // Cancel the breakdown work order with a reason
        $cancellationReason = 'False alarm: Thermostat sensor was faulty and replaced immediately.';
        $cancelledWo = $woService->cancelWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            $cancellationReason
        );

        // 1. Assert work order is cancelled and reason is saved in decision_note
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);
        $this->assertEquals($cancellationReason, $cancelledWo->decision_note);
        $this->assertStringContainsString($cancellationReason, $cancelledWo->work_performed);

        // 2. Assert machine status is restored to active and idle
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('none', $this->machine->maintenance_status);
        $this->assertEquals('Idle', $this->machine->current_state);

        // 3. Assert downtime record is closed with duration_minutes calculated
        $downtime->refresh();
        $this->assertEquals(ProductionMachineDowntime::STATUS_CLOSED, $downtime->status);
        $this->assertNotNull($downtime->end_time);
        $this->assertNotNull($downtime->duration_minutes);
        $this->assertGreaterThanOrEqual(0, (float) $downtime->duration_minutes);
        $this->assertStringContainsString($cancellationReason, $downtime->remarks);

        // 4. Assert cancellation logged in maintenance logs
        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'work_order_id' => $wo->id,
            'tenant_id'    => $this->tenant->id,
            'event_type'   => 'Work Order Cancelled',
        ]);
    }

    /** @test */
    public function it_requires_cancellation_reason_when_cancelling_breakdown_work_order()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Hydraulic pump failure',
            $this->user->id,
            'high'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A cancellation reason is required for breakdown work orders.");

        $woService->cancelWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            '' // Empty reason
        );
    }

    /** @test */
    public function it_cancels_non_breakdown_work_order_without_requiring_a_reason()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority'            => 'medium',
            'problem_description' => 'Regular 500-hour preventive lubrication',
        ]);

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_DRAFT, $wo->status);

        // Cancel without reason
        $cancelledWo = $woService->cancelWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            null
        );

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
    }

    /** @test */
    public function it_tests_http_cancellation_validation_and_flow_for_breakdown_and_preventive()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        // 1. Breakdown WO: HTTP cancel without reason fails validation
        $breakdownWo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Belt snapped',
            $this->user->id,
            'high'
        );

        $failResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.cancel', $breakdownWo->id), [
                'reason' => '',
            ]);
        $failResponse->assertSessionHasErrors('reason');

        // 2. Breakdown WO: HTTP cancel with reason succeeds, restores machine, closes downtime
        $successResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.cancel', $breakdownWo->id), [
                'reason' => 'Spare belt replaced on spot by operator, cancelling redundant maintenance WO.',
            ]);
        $successResponse->assertRedirect(route('production.maintenance.work-orders.show', $breakdownWo->id));
        $successResponse->assertSessionHas('success');

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('none', $this->machine->maintenance_status);

        $breakdownWo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $breakdownWo->status);
        $this->assertEquals('Spare belt replaced on spot by operator, cancelling redundant maintenance WO.', $breakdownWo->decision_note);

        $downtime = ProductionMachineDowntime::find($breakdownWo->downtime_id);
        $this->assertEquals(ProductionMachineDowntime::STATUS_CLOSED, $downtime->status);

        // 3. Preventive WO: HTTP cancel without reason succeeds without error
        $pmWo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Filter replacement',
        ]);

        $pmResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.cancel', $pmWo->id), []);
        $pmResponse->assertRedirect(route('production.maintenance.work-orders.show', $pmWo->id));
        $pmResponse->assertSessionHas('success');

        $pmWo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $pmWo->status);
    }

    /** @test */
    public function it_allows_manual_maintenance_log_before_work_order_starts_without_downtime()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $draftWo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Pre-maintenance check',
        ]);

        $this->assertNull($draftWo->downtime_id);
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_DRAFT, $draftWo->status);

        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.store-downtime-log', $draftWo->id), [
                'action_type' => 'inspection',
                'details'     => 'Initial inspection conducted before maintenance start.',
            ]);

        $response->assertRedirect(route('production.maintenance.work-orders.show', $draftWo->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_maintenance_work_order_logs', [
            'tenant_id'     => $this->tenant->id,
            'work_order_id' => $draftWo->id,
            'event_type'    => 'manual_log',
            'summary'       => 'Inspection',
        ]);
    }

    /** @test */
    public function it_disables_manual_log_creation_route_after_completion()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'To be completed',
        ]);
        $wo->update(['status' => ProductionMaintenanceWorkOrder::STATUS_COMPLETED]);

        $compResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.store-downtime-log', $wo->id), [
                'action_type' => 'cleaning',
                'details'     => 'Trying to clean after completion',
            ]);
        $compResponse->assertSessionHas('error');
    }

    /** @test */
    public function it_throws_exception_when_service_records_manual_log_on_completed_work_order()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'To be completed',
        ]);
        $wo->update(['status' => ProductionMaintenanceWorkOrder::STATUS_COMPLETED]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual logs cannot be added to a completed or cancelled work order.');
        $logService->recordManualLog($wo, $this->user->id, 'Cleaning', 'Manual log attempt');
    }

    /** @test */
    public function it_disables_manual_log_creation_route_after_cancellation()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $cancelledWo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'To be cancelled',
        ]);
        $woService->cancelWorkOrder($cancelledWo->id, $this->tenant->id, $this->user->id, 'Cancelled for test');

        $cancelledWo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);

        $cancelResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.store-downtime-log', $cancelledWo->id), [
                'action_type' => 'repair',
                'details'     => 'Trying to log repair after cancellation',
            ]);
        $cancelResponse->assertSessionHas('error');
    }

    /** @test */
    public function it_throws_exception_when_service_records_manual_log_on_cancelled_work_order()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $cancelledWo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'To be cancelled',
        ]);
        $woService->cancelWorkOrder($cancelledWo->id, $this->tenant->id, $this->user->id, 'Cancelled for test');
        $cancelledWo->refresh();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual logs cannot be added to a completed or cancelled work order.');
        $logService->recordManualLog($cancelledWo, $this->user->id, 'Repair', 'Manual log attempt');
    }

    /** @test */
    public function it_does_not_block_automatic_system_generated_logs_on_completed_or_cancelled_work_orders()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'System log test',
        ]);
        $wo->update(['status' => ProductionMaintenanceWorkOrder::STATUS_COMPLETED]);

        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);

        // System event log should succeed without exception
        $systemLog = $logService->recordEvent(
            $this->tenant->id,
            'System Audit',
            'Automatic system reconciliation performed.',
            ['audit_status' => 'verified'],
            $wo
        );

        $this->assertNotNull($systemLog->id);
        $this->assertEquals('System Audit', $systemLog->event_type);
    }

    /** @test */
    public function it_renders_compact_activity_log_without_internal_ids_or_sensitive_costs_and_toggles_manual_button()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Testing compact UI rendering',
        ]);

        $downtime = ProductionMachineDowntime::create([
            'tenant_id'      => $this->tenant->id,
            'machine_id'     => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'start_time'     => now(),
            'created_by'     => $this->user->id,
            'reason'         => 'Motor calibration check',
            'status'         => 'open',
            'category'       => 'Breakdown',
        ]);
        $wo->update(['downtime_id' => $downtime->id]);

        // Add explicit log records with internal IDs, costs, and metadata
        $logService->recordMachineDowntimeStarted($wo, $downtime->id, $this->user->id, [
            'machine_name' => $this->machine->name,
            'status'       => 'Downtime In Progress',
            'machine_id'   => $this->machine->id,
            'downtime_id'  => $downtime->id,
            'reason'       => 'Motor calibration check',
        ]);

        $assignment = \App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'       => $this->tenant->id,
            'work_order_id'   => $wo->id,
            'technician_id'   => $this->user->id,
            'technician_name' => 'Aarti Mehta',
            'assignment_type' => 'internal',
            'worked_hours'    => 2.5,
            'hourly_rate'     => 125.50,
            'notes'           => 'Align spindles and verify torque',
        ]);

        $logService->recordAssignmentCreated($wo, [
            'assignment_id'   => $assignment->id,
            'technician_id'   => $this->user->id,
            'technician_name' => 'Aarti Mehta',
            'worked_hours'    => 2.5,
            'hourly_rate'     => 125.50,
            'notes'           => 'Align spindles and verify torque',
        ], $this->user->id);

        $logService->recordWorkOrderCompleted($wo, $this->user->id, [
            'machine_id'        => $this->machine->id,
            'mechanic_cost'     => 313.75,
            'spare_parts_cost'  => 50.00,
            'additional_cost'   => 20.00,
            'work_performed'    => 'Full alignment complete',
        ]);

        // 1. In draft/in-progress: Add Manual Log button is visible
        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));

        $response->assertStatus(200);
        $html = $response->getContent();

        // Work-order event heading & timestamp only (no internal IDs or costs in log text)
        $response->assertSee('Work Order Completed');
        $response->assertSee('Machine DT Started');
        $response->assertSee($this->machine->name);
        $response->assertSee('Assignment Added');
        $response->assertSee('Aarti Mehta');
        $response->assertSee('Align spindles and verify torque');

        // Internal IDs must NOT appear in log listings
        $this->assertStringNotContainsString('Machine Id: ' . $this->machine->id, $html);
        $this->assertStringNotContainsString('Machine id: ' . $this->machine->id, $html);
        $this->assertStringNotContainsString('Downtime Id: ' . $downtime->id, $html);
        $this->assertStringNotContainsString('Downtime id: ' . $downtime->id, $html);
        $this->assertStringNotContainsString('Assignment Id: ' . $assignment->id, $html);
        $this->assertStringNotContainsString('Assignment id: ' . $assignment->id, $html);
        $this->assertStringNotContainsString('Technician Id: ' . $this->user->id, $html);
        $this->assertStringNotContainsString('Technician id: ' . $this->user->id, $html);

        // Costs must NOT appear in log listings
        $this->assertStringNotContainsString('Mechanic Cost: 313.75', $html);
        $this->assertStringNotContainsString('Mechanic cost: 313.75', $html);
        $this->assertStringNotContainsString('Hourly Rate: 125.5', $html);
        $this->assertStringNotContainsString('Hourly rate: 125.5', $html);

        // View Logs button must be present
        $response->assertSee('View Logs');
        // Add Manual Log button must be present before completion
        $response->assertSee('Add Manual Log');

        // 2. When completed: Add Manual Log button must be hidden/disabled
        $wo->update(['status' => ProductionMaintenanceWorkOrder::STATUS_COMPLETED]);

        $completedResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));

        $completedResponse->assertStatus(200);
        $completedHtml = $completedResponse->getContent();

        $completedResponse->assertSee('View Logs');
        $this->assertStringNotContainsString('Add Manual Log', $completedHtml);
    }

    /** @test */
    public function it_renders_assigned_technicians_ui_with_count_trigger_and_compact_summary(): void
    {
        // 1. Zero technicians assigned
        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'machine_id'          => $this->machine->id,
            'work_order_number'   => 'MWO-TECH-ZERO',
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority'            => ProductionMaintenanceWorkOrder::PRIORITY_MEDIUM,
            'status'              => ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
            'problem_description'=> 'Routine quarterly inspection',
            'created_by'          => $this->user->id,
        ]);

        $responseZero = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));

        $responseZero->assertStatus(200);
        $zeroHtml = $responseZero->getContent();

        // Top summary & Scope & Details should show 0 count and modal trigger
        $this->assertStringContainsString('Assigned Technicians:', $zeroHtml);
        $this->assertStringContainsString('0 Technicians Assigned', $zeroHtml);
        $this->assertStringContainsString('assignedTechniciansModal', $zeroHtml);
        $this->assertStringContainsString('No technicians have been assigned to this work order yet.', $zeroHtml);

        // 2. Multiple technicians assigned with in_progress status (testing ongoing hours and combined rates)
        $tech1 = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Vikramaditya Shinde',
            'email'     => 'vikram_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
        ]);

        $tech2 = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Aarti Mehta',
            'email'     => 'aarti_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
        ]);

        $actualStart = Carbon::now()->subHours(3);

        $woInProgress = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'machine_id'          => $this->machine->id,
            'work_order_number'   => 'MWO-TECH-MULTI',
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority'            => ProductionMaintenanceWorkOrder::PRIORITY_HIGH,
            'status'              => ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
            'actual_start'        => $actualStart,
            'problem_description'=> 'Hydraulic spindle calibration',
            'created_by'          => $this->user->id,
        ]);

        \App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'           => $this->tenant->id,
            'work_order_id'       => $woInProgress->id,
            'technician_id'       => $tech1->id,
            'technician_name'     => $tech1->name,
            'assignment_type'     => 'internal',
            'assigned_at'         => $actualStart,
            'hourly_rate'         => 200.00,
            'worked_hours'        => 0.00,
        ]);

        \App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'           => $this->tenant->id,
            'work_order_id'       => $woInProgress->id,
            'technician_name'     => 'External Specialist Contractor',
            'assignment_type'     => 'external',
            'assigned_at'         => $actualStart,
            'hourly_rate'         => 350.00,
            'worked_hours'        => 0.00,
        ]);

        $responseMulti = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $woInProgress->id));

        $responseMulti->assertStatus(200);
        $multiHtml = $responseMulti->getContent();

        // Total count: 2
        $this->assertStringContainsString('2 Technicians Assigned', $multiHtml);
        // Popover title & content with both technician names
        $this->assertStringContainsString('Vikramaditya Shinde, External Specialist Contractor', $multiHtml);
        // Combined hourly rate: 200 + 350 = 550
        $this->assertStringContainsString(format_currency(550.00) . '/hr', $multiHtml);
        // In-progress work order shows "Ongoing" label
        $this->assertStringContainsString('Ongoing', $multiHtml);
        // Modal displays individual details
        $this->assertStringContainsString('Vikramaditya Shinde', $multiHtml);
        $this->assertStringContainsString('Internal / In-House', $multiHtml);
        $this->assertStringContainsString(format_currency(200.00) . '/hr', $multiHtml);
        $this->assertStringContainsString('External Specialist Contractor', $multiHtml);
        $this->assertStringContainsString('External', $multiHtml);
        $this->assertStringContainsString(format_currency(350.00) . '/hr', $multiHtml);

        // 3. Completed work order shows final combined hours without Ongoing badge in summary
        $woCompleted = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'machine_id'          => $this->machine->id,
            'work_order_number'   => 'MWO-TECH-DONE',
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority'            => ProductionMaintenanceWorkOrder::PRIORITY_LOW,
            'status'              => ProductionMaintenanceWorkOrder::STATUS_COMPLETED,
            'actual_start'        => Carbon::now()->subHours(5),
            'actual_end'          => Carbon::now(),
            'problem_description'=> 'Filter replacement completed',
            'created_by'          => $this->user->id,
        ]);

        \App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'           => $this->tenant->id,
            'work_order_id'       => $woCompleted->id,
            'technician_id'       => $tech1->id,
            'technician_name'     => $tech1->name,
            'assignment_type'     => 'internal',
            'hourly_rate'         => 150.00,
            'worked_hours'        => 4.50,
        ]);

        $responseDone = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $woCompleted->id));

        $responseDone->assertStatus(200);
        $doneHtml = $responseDone->getContent();

        $this->assertStringContainsString('1 Technician Assigned', $doneHtml);
        $this->assertStringContainsString(format_hours(4.50), $doneHtml);
        $this->assertStringContainsString(format_currency(150.00) . '/hr', $doneHtml);
    }

    /** @test */
    public function it_enforces_unique_assignments_for_internal_and_external_technicians()
    {
        $woService = app(MaintenanceWorkOrderService::class);

        $tech1 = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kavita Iyer']);
        $tech2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rahul Sharma']);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Uniqueness test',
            'assignments'         => [
                [
                    'assignment_type'     => 'internal',
                    'technician_id'       => $tech1->id,
                    'technician_name'     => $tech1->name,
                    'expected_work_hours' => 2.0,
                    'hourly_rate'         => 120.00,
                ],
                [
                    'assignment_type'     => 'external',
                    'technician_name'     => 'Apex Precision Ltd',
                    'expected_work_hours' => 3.0,
                    'hourly_rate'         => 250.00,
                ],
            ],
        ]);

        $this->assertEquals(2, $wo->assignments()->count());

        // 1. Attempting to add existing internal technician via HTTP fails
        $response1 = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.add-assignment', $wo->id), [
                'assignments' => [
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech1->id,
                        'technician_name'     => $tech1->name,
                        'expected_work_hours' => 1.5,
                        'hourly_rate'         => 120.00,
                    ],
                ],
            ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('error');
        $this->assertEquals(2, $wo->assignments()->count());

        // 2. Attempting to add existing external technician with case-insensitive name fails
        $response2 = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.add-assignment', $wo->id), [
                'assignments' => [
                    [
                        'assignment_type'     => 'external',
                        'technician_name'     => '  apex precision ltd  ',
                        'expected_work_hours' => 1.0,
                        'hourly_rate'         => 250.00,
                    ],
                ],
            ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('error');
        $this->assertEquals(2, $wo->assignments()->count());

        // 3. Attempting to submit batch with intra-batch duplicates fails
        $response3 = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.add-assignment', $wo->id), [
                'assignments' => [
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech2->id,
                        'technician_name'     => $tech2->name,
                        'expected_work_hours' => 1.0,
                        'hourly_rate'         => 100.00,
                    ],
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech2->id,
                        'technician_name'     => $tech2->name,
                        'expected_work_hours' => 2.0,
                        'hourly_rate'         => 100.00,
                    ],
                ],
            ]);

        $response3->assertRedirect();
        $response3->assertSessionHas('error');
        $this->assertEquals(2, $wo->assignments()->count());

        // 4. Adding a new distinct technician succeeds
        $response4 = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.add-assignment', $wo->id), [
                'assignments' => [
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech2->id,
                        'technician_name'     => $tech2->name,
                        'expected_work_hours' => 1.0,
                        'hourly_rate'         => 100.00,
                    ],
                ],
            ]);

        $response4->assertRedirect();
        $response4->assertSessionHas('success');
        $this->assertEquals(3, $wo->assignments()->count());
    }

    /** @test */
    public function it_renders_activity_log_summary_with_numerical_count_and_eight_limit_pagination()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Testing activity log truncation',
        ]);

        // Initially, 1 log exists (work_order_created)
        $this->assertEquals(1, $wo->logs()->count());

        // Add 5 more logs (total 6 <= 8)
        for ($i = 1; $i <= 5; $i++) {
            $log = $logService->recordEvent(
                $this->tenant->id,
                "Log_Step_{$i}",
                "Maintenance execution step number {$i}",
                ['step' => $i],
                $wo
            );
            $log->update(['logged_at' => Carbon::now()->subMinutes(120 - ($i * 10))]);
        }

        $wo->refresh();
        $this->assertEquals(6, $wo->logs()->count());

        // When <= 8 logs: all logs displayed, no omission separator
        $responseSmall = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));
        $responseSmall->assertStatus(200);
        $smallHtml = $responseSmall->getContent();

        $this->assertStringContainsString('6 Logs', $smallHtml);
        $this->assertStringContainsString('Log Step 1', $smallHtml);
        $this->assertStringContainsString('Log Step 5', $smallHtml);
        $this->assertStringNotContainsString('omitted', $smallHtml);

        // Now add 5 more logs (total 11 > 8)
        for ($i = 6; $i <= 10; $i++) {
            $log = $logService->recordEvent(
                $this->tenant->id,
                "Log_Step_{$i}",
                "Maintenance execution step number {$i}",
                ['step' => $i],
                $wo
            );
            $log->update(['logged_at' => Carbon::now()->subMinutes(60 - (($i - 5) * 10))]);
        }

        $wo->refresh();
        $this->assertEquals(11, $wo->logs()->count());

        $responseLarge = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));

        $responseLarge->assertStatus(200);
        $largeHtml = $responseLarge->getContent();

        // 1. Shows total count numerically: "11 Logs"
        $this->assertStringContainsString('11 Logs', $largeHtml);

        // 2. Shows first 4 (oldest: Work Order Created, Step 1, Step 2, Step 3)
        $this->assertStringContainsString('Work Order Created', $largeHtml);
        $this->assertStringContainsString('Log Step 1', $largeHtml);
        $this->assertStringContainsString('Log Step 2', $largeHtml);
        $this->assertStringContainsString('Log Step 3', $largeHtml);

        // 3. Middle 3 (11 - 8 = 3: Steps 4, 5, 6) omitted with ellipsis separator
        $this->assertStringContainsString('3 earlier logs omitted', $largeHtml);
        $this->assertStringContainsString('View all 11 logs', $largeHtml);

        // 4. Shows last 4 (newest: Steps 7, 8, 9, 10)
        $this->assertStringContainsString('Log Step 7', $largeHtml);
        $this->assertStringContainsString('Log Step 8', $largeHtml);
        $this->assertStringContainsString('Log Step 9', $largeHtml);
        $this->assertStringContainsString('Log Step 10', $largeHtml);
    }

    /** @test */
    public function it_renders_view_logs_modal_without_form_action_and_with_assignment_type_badges()
    {
        $woService = app(MaintenanceWorkOrderService::class);
        $logService = app(\App\Domains\Production\Services\MaintenanceWorkOrderLogService::class);

        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sunita Rao']);

        $wo = $woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Modal test',
        ]);

        $logService->recordAssignmentCreated($wo, [
            'assignment_type' => 'internal',
            'type'            => 'internal',
            'technician_id'   => $tech->id,
            'technician_name' => $tech->name,
            'notes'           => 'Primary lead technician',
        ], $this->user->id);

        $logService->recordAssignmentCreated($wo, [
            'assignment_type' => 'external',
            'type'            => 'external',
            'technician_name' => 'Hydraulics Pro Vendor',
            'notes'           => 'External valve expert',
        ], $this->user->id);

        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.show', $wo->id));

        $response->assertStatus(200);
        $html = $response->getContent();

        // View Logs modal must NOT have formAction="#"
        $this->assertStringNotContainsString('id="viewDowntimeLogsModal" title="Maintenance Logs" formAction="#"', $html);
        $this->assertStringNotContainsString('action="#"', $html);

        // Assignment logs must display name and assignment type badge
        $this->assertStringContainsString('Sunita Rao', $html);
        $this->assertStringContainsString('Internal Employee', $html);
        $this->assertStringContainsString('Hydraulics Pro Vendor', $html);
        $this->assertStringContainsString('External Hire', $html);
    }

    /** @test */
    public function it_rejects_work_order_creation_with_duplicate_internal_or_external_technicians()
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kavita Patel']);

        // 1. Duplicate internal technician
        $responseInternal = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.store'), [
                'machine_id'          => $this->machine->id,
                'type'                => 'preventive',
                'priority'            => 'medium',
                'problem_description' => 'Test duplicate internal technician creation',
                'assignments'         => [
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech->id,
                        'technician_name'     => $tech->name,
                        'expected_work_hours' => 2,
                        'hourly_rate'         => 35.00,
                    ],
                    [
                        'assignment_type'     => 'internal',
                        'technician_id'       => $tech->id,
                        'technician_name'     => $tech->name,
                        'expected_work_hours' => 3,
                        'hourly_rate'         => 35.00,
                    ],
                ],
            ]);

        $responseInternal->assertSessionHas('error');
        $this->assertStringContainsString('Duplicate technician assignment detected', session('error'));

        // 2. Duplicate external technician
        $responseExternal = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->post(route('production.maintenance.work-orders.store'), [
                'machine_id'          => $this->machine->id,
                'type'                => 'preventive',
                'priority'            => 'medium',
                'problem_description' => 'Test duplicate external technician creation',
                'assignments'         => [
                    [
                        'assignment_type'     => 'external',
                        'technician_name'     => 'Fast Hydraulics',
                        'expected_work_hours' => 2,
                        'hourly_rate'         => 50.00,
                    ],
                    [
                        'assignment_type'     => 'external',
                        'technician_name'     => 'Fast Hydraulics',
                        'expected_work_hours' => 4,
                        'hourly_rate'         => 50.00,
                    ],
                ],
            ]);

        $responseExternal->assertSessionHas('error');
        $this->assertStringContainsString('Duplicate external technician', session('error'));
    }

    /** @test */
    public function it_renders_duplicate_prevention_ui_on_work_order_create_page()
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.maintenance.work-orders.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Alpine methods for duplicate prevention present
        $this->assertStringContainsString('isInternalAssigned', $html);
        $this->assertStringContainsString('isExternalAssigned', $html);
        $this->assertStringContainsString('hasAnyDuplicates', $html);

        // 2. Visual select indicator and disabled state present
        $this->assertStringContainsString('(Already Selected)', $html);
        $this->assertStringContainsString('isInternalAssigned', $html);

        // 3. Inline duplicate warning elements present
        $this->assertStringContainsString('Duplicate assignment is not allowed', $html);
        $this->assertStringContainsString('Cannot Submit', $html);
    }
}


