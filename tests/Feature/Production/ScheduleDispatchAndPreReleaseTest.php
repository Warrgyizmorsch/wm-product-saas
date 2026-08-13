<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\SchedulePreReleaseValidationService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleDispatchAndPreReleaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $planner;
    private Uom $uom;
    private Product $product;
    private WorkCenter $workCenter1;
    private Machine $primaryMachine1;
    private Machine $alternateMachine1;
    private Machine $unqualifiedMachine1;
    private SchedulingService $schedulingService;
    private CapacityPlanningService $capacityService;
    private SchedulePreReleaseValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'SaaS Precision Mfg',
            'slug'   => 'saas-precision-p3',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->planner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Senior Dispatcher',
            'email'     => 'dispatcher@saasprecision.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Piece',
            'code'      => 'PCS',
            'type'      => 'unit',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'uom_id'    => $this->uom->id,
            'name'      => 'Titanium Turbine Blade',
            'sku'       => 'FG-TURB-001',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Standard Mon-Fri Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => true,
        ]);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id'              => $this->tenant->id,
            'name'                   => '5-Axis Machining Center',
            'code'                   => 'WC-5AXIS',
            'efficiency_percentage'  => 100.0,
            'status'                 => 'active',
            'production_calendar_id' => $cal->id,
        ]);

        $this->primaryMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => '5-Axis CNC Mill 01',
            'code'           => '5AX-01',
            'status'         => 'active',
        ]);

        $this->alternateMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => '5-Axis CNC Mill 02',
            'code'           => '5AX-02',
            'status'         => 'active',
        ]);

        $this->unqualifiedMachine1 = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'name'           => 'Manual Lathe 99',
            'code'           => 'LAT-99',
            'status'         => 'active',
        ]);

        $this->schedulingService = app(SchedulingService::class);
        $this->capacityService   = app(CapacityPlanningService::class);
        $this->validationService = app(SchedulePreReleaseValidationService::class);
    }

    private function createTestOrderAndSchedule(string $number = 'ORD-3001', int $qty = 5): array
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => $number,
            'product_id'       => $this->product->id,
            'quantity_ordered' => $qty,
            'status'           => ProductionOrder::STATUS_RELEASED,
            'start_date'       => now()->next(Carbon::MONDAY)->setTime(8, 0, 0),
            'end_date'         => now()->next(Carbon::MONDAY)->setTime(8, 0, 0)->addDays(5),
        ]);

        $routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $this->product->id,
            'name'       => 'Turbine Routing',
            'status'     => 'approved',
        ]);

        $routingOp1 = RoutingOperation::create([
            'tenant_id'               => $this->tenant->id,
            'routing_id'              => $routing->id,
            'operation_number'        => 'OP-10',
            'name'                    => '5-Axis Rough Milling',
            'sequence'                => 10,
            'work_center_id'          => $this->workCenter1->id,
            'machine_id'              => $this->primaryMachine1->id,
            'setup_time_minutes'      => 30,
            'processing_time_minutes' => 12,
        ]);

        RoutingOperationAlternateMachine::create([
            'tenant_id'            => $this->tenant->id,
            'routing_operation_id' => $routingOp1->id,
            'machine_id'           => $this->alternateMachine1->id,
            'priority'             => 2,
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $this->tenant->id,
            'production_order_id'     => $order->id,
            'routing_operation_id'    => $routingOp1->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => '5-Axis Rough Milling',
            'work_center_id'          => $this->workCenter1->id,
            'machine_id'              => $this->primaryMachine1->id,
            'setup_time_planned'      => 30,
            'processing_time_planned' => 12,
            'total_time_planned'      => 90,
            'status'                  => 'waiting',
        ]);

        $startDate = Carbon::now()->next(Carbon::MONDAY)->setTime(8, 0, 0);
        $schedule  = $this->schedulingService->generateForwardSchedule($order, $startDate);

        return [$order, $schedule];
    }

    /**
     * Test 1: Dispatch Board Data API returns bounded date range, resources, operations, and metadata.
     */
    public function test_dispatch_board_api_returns_bounded_data(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-DISPATCH-01');

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $endDate   = Carbon::now()->next(Carbon::MONDAY)->addDays(7)->format('Y-m-d');

        $response = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('production.schedules.dispatch-board.data', [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'range'      => ['start', 'end'],
            'resources'  => [],
            'operations' => [],
            'downtimes'  => [],
            'capacity'   => [],
            'warnings'   => [],
            'meta'       => ['total_operations', 'total_resources'],
        ]);

        $json = $response->json();
        $this->assertEquals($startDate, $json['range']['start']);
        $this->assertEquals($endDate, $json['range']['end']);
        $this->assertCount(1, $json['operations']);

        $opData = $json['operations'][0];
        $this->assertEquals($schedule->id, $opData['schedule_id']);
        $this->assertEquals($this->primaryMachine1->id, $opData['machine_id']);
        $this->assertEquals(1, $opData['version']);
        $this->assertFalse($opData['locked']);
        $this->assertFalse($opData['manual_override']);
        $this->assertNotNull($opData['baseline_start']);
    }

    /**
     * Test 2: Dispatch Board API rejects date range exceeding 62 days.
     */
    public function test_dispatch_board_api_rejects_oversized_date_range(): void
    {
        $startDate = Carbon::now()->format('Y-m-d');
        $endDate   = Carbon::now()->addDays(70)->format('Y-m-d');

        $response = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('production.schedules.dispatch-board.data', [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]));

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    /**
     * Test 3: Dispatch Board Data API enforces tenant isolation.
     */
    public function test_dispatch_board_api_tenant_isolation(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-TENANT-A');

        // Create Tenant B
        $otherTenant = Tenant::create([
            'name'   => 'Competitor Mfg',
            'slug'   => 'competitor-mfg',
            'status' => 'active',
            'plan'   => 'basic',
        ]);

        $otherWc = WorkCenter::create([
            'tenant_id'             => $otherTenant->id,
            'name'                  => 'Other Tenant WorkCenter',
            'code'                  => 'OTHER-WC',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);

        $startDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $endDate   = Carbon::now()->next(Carbon::MONDAY)->addDays(7)->format('Y-m-d');

        $response = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('production.schedules.dispatch-board.data', [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]));

        $response->assertStatus(200);
        $resources = $response->json('resources');

        // Tenant B's work center must NOT be visible to Tenant A
        $this->assertFalse(collect($resources)->contains('id', $otherWc->id));
    }

    /**
     * Test 4: Pre-Release Validation passes clean schedule with no errors.
     */
    public function test_pre_release_validation_clean_schedule_passes(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-VALID-01');

        $result = $this->validationService->validate($schedule);

        $this->assertTrue($result['can_release']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test 5: Pre-Release Validation detects machine downtime collision as a blocking error.
     */
    public function test_pre_release_validation_detects_machine_downtime_collision(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-DOWNTIME-01');
        $op = $schedule->operations()->first();

        // Schedule maintenance downtime overlapping operation timing
        ProductionMachineDowntime::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter1->id,
            'machine_id'     => $this->primaryMachine1->id,
            'start_time'     => $op->planned_start->copy()->subMinutes(10),
            'end_time'       => $op->planned_finish->copy()->addMinutes(10),
            'category'       => 'unplanned',
            'reason'         => 'Emergency Spindle Maintenance',
            'status'         => 'scheduled',
            'created_by'     => $this->planner->id,
        ]);

        $result = $this->validationService->validate($schedule);

        $this->assertFalse($result['can_release']);
        $this->assertNotEmpty($result['errors']);
        $this->assertTrue(collect($result['errors'])->contains('code', 'MACHINE_DOWNTIME_COLLISION'));
    }

    /**
     * Test 6: Pre-Release Validation detects unqualified machine assignment as a blocking error.
     */
    public function test_pre_release_validation_detects_unqualified_machine(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-UNQUAL-01');
        $op = $schedule->operations()->first();

        // Assign unqualified machine (not primary or alternate)
        $op->update(['machine_id' => $this->unqualifiedMachine1->id]);

        $result = $this->validationService->validate($schedule);

        $this->assertFalse($result['can_release']);
        $this->assertTrue(collect($result['errors'])->contains('code', 'UNQUALIFIED_MACHINE'));
    }

    /**
     * Test 7: Pre-Release Validation treats material shortages as WARNINGS (not blocking errors).
     */
    public function test_pre_release_validation_material_shortage_generates_warning(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-MAT-SHORT-01');

        $rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'uom_id'    => $this->uom->id,
            'name'      => 'Titanium Alloy Bar Stock',
            'sku'       => 'RM-TIT-BAR',
            'type'      => 'raw_material',
            'status'    => 'active',
        ]);

        // Create reservation with shortage
        ProductionOrderReservation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id'          => $rawMaterial->id,
            'quantity_planned'    => 100.0,
            'quantity_reserved'   => 0.0,
            'quantity_issued'     => 0.0,
            'uom_id'              => $this->uom->id,
        ]);

        $result = $this->validationService->validate($schedule);

        // Material shortage MUST generate warning, but still allow can_release = true
        $this->assertTrue($result['can_release']);
        $this->assertTrue($result['has_warnings']);
        $this->assertTrue(collect($result['warnings'])->contains('code', 'MATERIAL_SHORTAGE'));
    }

    /**
     * Test 8: Server-side Release protection blocks release when errors exist.
     */
    public function test_release_action_blocks_release_when_errors_exist(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-REL-ERR-01');
        $op = $schedule->operations()->first();

        // Introduce unqualified machine error
        $op->update(['machine_id' => $this->unqualifiedMachine1->id]);

        $response = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.release', $schedule->id));

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);

        $schedule->refresh();
        $this->assertEquals(ProductionSchedule::STATUS_SCHEDULED, $schedule->status);
    }

    /**
     * Test 9: Release with warnings requires confirm_warnings = 1.
     */
    public function test_release_action_requires_explicit_confirm_warnings(): void
    {
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-REL-WARN-01');

        $rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'uom_id'    => $this->uom->id,
            'name'      => 'Alloy Ingot',
            'sku'       => 'RM-ALLOY',
            'type'      => 'raw_material',
            'status'    => 'active',
        ]);

        ProductionOrderReservation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id'          => $rawMaterial->id,
            'quantity_planned'    => 50.0,
            'quantity_reserved'   => 0.0,
            'quantity_issued'     => 0.0,
            'uom_id'              => $this->uom->id,
        ]);

        // Attempting release without confirm_warnings returns 422 requiring confirmation
        $response1 = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.release', $schedule->id));

        $response1->assertStatus(422);
        $response1->assertJsonFragment(['requires_confirmation' => true]);

        // Submitting with confirm_warnings = 1 releases schedule cleanly
        $response2 = $this->actingAs($this->planner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.release', $schedule->id), [
                'confirm_warnings' => 1,
            ]);

        $response2->assertStatus(200);
        $response2->assertJsonFragment(['success' => true]);

        $schedule->refresh();
        $this->assertEquals(ProductionSchedule::STATUS_RELEASED, $schedule->status);
    }

    /**
     * Test 10: Schedule Change History API returns tenant-scoped change logs.
     */
    public function test_schedule_change_history_api_returns_change_logs(): void
    {
        $this->actingAs($this->planner);
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-HIST-01');
        $op = $schedule->operations()->first();

        // Perform isolated shift to generate log
        $this->capacityService->rescheduleOperationWithMode(
            $op->id,
            $op->planned_start->copy()->subMinutes(15),
            $this->primaryMachine1->id,
            ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
            'Planner audit test shift',
            $this->planner->id
        );

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('production.schedules.change-history', $schedule->id));

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertNotEmpty($json['data']);
        $this->assertEquals('manual_shift', $json['data'][0]['change_type']);
        $this->assertEquals('Planner audit test shift', $json['data'][0]['reason']);
    }

    /**
     * Test 11: Adjust Operation endpoint executes Ripple Shift via HTTP POST.
     */
    public function test_adjust_operation_endpoint_executes_ripple_shift(): void
    {
        $this->actingAs($this->planner);
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-API-RIPPLE-01');
        $op = $schedule->operations()->first();

        $newStart = $op->planned_start->copy()->addMinutes(30)->format('Y-m-d H:i:s');

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.operations.adjust', $op->id), [
                'planned_start'    => $newStart,
                'machine_id'       => $this->primaryMachine1->id,
                'shift_mode'       => 'ripple',
                'reason'           => 'Planner drag move test',
                'expected_version' => $op->version,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        $op->refresh();
        $this->assertEquals(2, $op->version);
        $this->assertTrue($op->manual_override);
    }

    /**
     * Test 12: Adjust Operation endpoint validates machine qualification.
     */
    public function test_adjust_operation_endpoint_validates_machine_qualification(): void
    {
        $this->actingAs($this->planner);
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-API-UNQUAL-01');
        $op = $schedule->operations()->first();

        // Attempting to move to unqualified machine returns 422
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.operations.adjust', $op->id), [
                'planned_start'    => $op->planned_start->format('Y-m-d H:i:s'),
                'machine_id'       => $this->unqualifiedMachine1->id,
                'shift_mode'       => 'isolated',
                'reason'           => 'Invalid machine drag drop',
                'expected_version' => $op->version,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    /**
     * Test 13: Adjust Operation endpoint rejects locked operation modification.
     */
    public function test_adjust_operation_endpoint_rejects_locked_operation(): void
    {
        $this->actingAs($this->planner);
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-API-LOCKED-01');
        $op = $schedule->operations()->first();

        // Lock operation first
        $op->update(['locked' => true]);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.operations.adjust', $op->id), [
                'planned_start'    => $op->planned_start->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
                'machine_id'       => $this->primaryMachine1->id,
                'shift_mode'       => 'isolated',
                'reason'           => 'Attempt drag locked op',
                'expected_version' => $op->version,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    /**
     * Test 14: Toggle Lock endpoint updates lock state and increments version.
     */
    public function test_toggle_lock_endpoint_toggles_lock_and_increments_version(): void
    {
        $this->actingAs($this->planner);
        [$order, $schedule] = $this->createTestOrderAndSchedule('ORD-API-TOGGLE-01');
        $op = $schedule->operations()->first();
        $this->assertFalse($op->locked);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('production.schedules.operations.toggle-lock', $op->id));

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true, 'locked' => true, 'version' => 2]);

        $op->refresh();
        $this->assertTrue($op->locked);
        $this->assertEquals(2, $op->version);
    }
}
