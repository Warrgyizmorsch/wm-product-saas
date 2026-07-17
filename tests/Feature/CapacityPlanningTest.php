<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CapacityPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $product;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant
        $tenant = Tenant::create([
            'name' => 'Capacity Test Tenant',
            'slug' => 'capacity-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->tenantId = $tenant->id;

        // Create User
        $this->user = User::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Capacity Planner',
            'email' => 'planner@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);
        $this->withHeaders(['X-Tenant' => 'capacity-test']);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Units',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Product',
            'sku' => 'PROD-CAP',
            'type' => 'finished_good',
            'unit_cost' => 50.00,
            'status' => 'active',
        ]);

        // Default Active Work Center & Machine
        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'CNC Milling WC',
            'code' => 'WC-CNC',
            'efficiency_percentage' => 90.0, // 90% efficiency modifier
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name' => 'CNC Router 1',
            'code' => 'MC-CNC-01',
            'status' => 'active',
        ]);
    }

    /**
     * Test work center capacity calculation on a working day vs non-working day.
     */
    public function test_capacity_calculations_respect_calendar_and_efficiency()
    {
        $service = app(CapacityPlanningService::class);
        $monday = Carbon::parse('2026-07-20'); // Monday (working day by default fallback)
        $sunday = Carbon::parse('2026-07-19'); // Sunday (non-working day by default fallback)

        // Monday Capacity: 480 minutes (fallback shift) * 90% efficiency = 432 minutes = 7.2 hours
        $wcLoadsMonday = $service->getWorkCenterCapacity($this->tenantId, $monday, $monday);
        $this->assertEquals(7.2, $wcLoadsMonday[0]['available_hours']);

        // Sunday Capacity: 0.0 hours
        $wcLoadsSunday = $service->getWorkCenterCapacity($this->tenantId, $sunday, $sunday);
        $this->assertEquals(0.0, $wcLoadsSunday[0]['available_hours']);
    }

    /**
     * Test work center capacity updates based on setup and run times.
     */
    public function test_setup_and_run_time_aggregation()
    {
        $service = app(CapacityPlanningService::class);
        $date = Carbon::parse('2026-07-20'); // Monday

        // Create mock Production Order & Operations
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-101',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10.0000,
            'status' => 'released',
            'start_date' => $date->toDateString(),
            'end_date' => $date->copy()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-101',
            'name' => 'Milling Operation',
            'work_center_id' => $this->workCenter->id,
            'setup_time_planned' => 30.00, // 30 mins setup
            'processing_time_planned' => 12.00, // 12 mins per unit x 10 = 120 mins run
            'status' => 'ready',
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'schedule_number' => 'SCHED-101',
            'production_order_id' => $order->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'status' => 'scheduled',
        ]);

        ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'planned_start' => $date->copy()->setHour(9),
            'planned_finish' => $date->copy()->setHour(9)->addMinutes(150), // 30 setup + 120 run = 150 mins
            'planned_duration_minutes' => 150.00,
            'status' => 'ready',
        ]);

        $wcLoads = $service->getWorkCenterCapacity($this->tenantId, $date, $date);

        // 30 mins = 0.5 hours setup, 120 mins = 2.0 hours run, 150 mins = 2.5 hours total required
        $this->assertEquals(0.5, $wcLoads[0]['setup_hours']);
        $this->assertEquals(2.0, $wcLoads[0]['run_hours']);
        $this->assertEquals(2.5, $wcLoads[0]['required_hours']);
    }

    /**
     * Test calendar holidays exclude capacity.
     */
    public function test_holiday_exclusions_reduce_available_capacity()
    {
        $service = app(CapacityPlanningService::class);
        $date = Carbon::parse('2026-07-20'); // Monday

        // Create Custom Calendar
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Special Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default' => false,
        ]);

        // Link work center to custom calendar
        $this->workCenter->update(['production_calendar_id' => $calendar->id]);

        // Create Holiday
        ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenantId,
            'production_calendar_id' => $calendar->id,
            'name' => 'National Day',
            'holiday_date' => $date->toDateString(),
            'holiday_type' => 'public',
            'is_full_day' => true,
            'active' => true,
        ]);

        $wcLoads = $service->getWorkCenterCapacity($this->tenantId, $date, $date);
        $this->assertEquals(0.0, $wcLoads[0]['available_hours']);
    }

    /**
     * Test machine downtime reduces capacity.
     */
    public function test_machine_downtime_reduces_available_capacity()
    {
        $service = app(CapacityPlanningService::class);
        $date = Carbon::parse('2026-07-20'); // Monday

        // Create machine downtime log (120 minutes breakdown)
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantId,
            'machine_id' => $this->machine->id,
            'work_center_id' => $this->workCenter->id,
            'category' => 'breakdown',
            'reason' => 'Spindle overheating',
            'start_time' => $date->copy()->setHour(8),
            'end_time' => $date->copy()->setHour(10),
            'duration_minutes' => 120.00,
            'status' => 'open',
            'created_by' => $this->user->id,
        ]);

        $wcLoads = $service->getWorkCenterCapacity($this->tenantId, $date, $date);

        // Monday base capacity = 432 minutes. Deduct 120 minutes downtime = 312 minutes = 5.2 hours
        $this->assertEquals(5.2, $wcLoads[0]['available_hours']);
    }

    /**
     * Test operation rescheduling with validation rules.
     */
    public function test_operation_rescheduling_verifies_constraints_and_saves()
    {
        $service = app(CapacityPlanningService::class);
        $date = Carbon::parse('2026-07-20'); // Monday

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'ORD-201',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5.0000,
            'status' => 'released',
            'start_date' => $date->toDateString(),
            'end_date' => $date->copy()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $orderOp1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP-201',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'status' => 'ready',
        ]);

        $orderOp2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP-202',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter->id,
            'status' => 'waiting',
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'schedule_number' => 'SCHED-201',
            'production_order_id' => $order->id,
            'status' => 'scheduled',
        ]);

        $schedOp1 = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp1->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'planned_start' => $date->copy()->setHour(8),
            'planned_finish' => $date->copy()->setHour(9),
            'planned_duration_minutes' => 60.00,
            'status' => 'ready',
        ]);

        $schedOp2 = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp2->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'sequence' => 20,
            'planned_start' => $date->copy()->setHour(10),
            'planned_finish' => $date->copy()->setHour(11),
            'planned_duration_minutes' => 60.00,
            'status' => 'waiting',
        ]);

        // 1. Reschedule Op 1 to a valid slot (e.g. 8:30)
        $service->rescheduleOperation($schedOp1->id, $date->copy()->setHour(8)->setMinute(30), null, 'Customer priority change', $this->user->id);
        
        $schedOp1->refresh();
        $this->assertEquals('08:30:00', $schedOp1->planned_start->toTimeString());

        // 2. Reject Rescheduling Op 1 to an invalid slot that overlaps successor (e.g. 9:30, making it finish at 10:30, after Op 2 starts at 10:00)
        $this->expectException(\InvalidArgumentException::class);
        $service->rescheduleOperation($schedOp1->id, $date->copy()->setHour(9)->setMinute(30), null, 'Invalid overlap shift', $this->user->id);
    }
}
