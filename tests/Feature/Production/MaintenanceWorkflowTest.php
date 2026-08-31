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
        ]);

        // Start Work Order
        $startedWo = $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        $this->assertEquals('in_progress', $startedWo->status);
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
    public function it_reports_emergency_breakdown_and_creates_downtime_and_wo()
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
        $this->assertEquals('in_progress', $wo->status);
        $this->assertNotNull($wo->downtime_id);

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $this->machine->status);
        $this->assertEquals('Breakdown', $this->machine->current_state);
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

        $woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        // Complete Work Order (2 hours labor at $50/hr work center rate = $100 labor cost)
        $completedWo = $woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Inspected machine, adjusted belt tension, completed lubrication.',
            2.0
        );

        $this->assertEquals('completed', $completedWo->status);
        $this->assertEquals(100.00, $completedWo->labor_cost);
        $this->assertEquals(100.00, $completedWo->total_cost);

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

        // 1. Report emergency breakdown
        $wo = $woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Hydraulic Pressure Seal Rupture',
            $this->user->id,
            'critical'
        );

        $this->assertNotNull($wo->downtime_id);
        $this->assertEquals('in_progress', $wo->status);

        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_UNDER_MAINTENANCE, $this->machine->status);
        $this->assertEquals('Breakdown', $this->machine->current_state);

        // 2. Resolve downtime from MES machine monitor screen (endDowntime)
        $downtimeService->endDowntime(
            $this->tenant->id,
            $wo->downtime_id,
            $this->user->id,
            'Replaced hydraulic seal ring and refilled fluid.'
        );

        // 3. Verify Work Order is now completed
        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);
        $this->assertEquals('Replaced hydraulic seal ring and refilled fluid.', $wo->work_performed);

        // 4. Verify Machine is restored to Active, Idle, and none
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('Idle', $this->machine->current_state);
        $this->assertEquals('none', $this->machine->maintenance_status);
    }
}
