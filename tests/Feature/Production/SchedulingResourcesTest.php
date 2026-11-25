<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\CapacityLevelingService;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionScheduleScenarioService;
use App\Domains\Production\Services\SchedulePreReleaseValidationService;
use App\Domains\Production\Services\SchedulingCalendarService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Scheduling after the execution-rules update (docs/production_execution_rules_draft.md, PX-05..PX-10 + RT-06/07/11):
 * an operation runs on ONE resource — a machine OR a manual batch — in THAT resource's shifts, no alternate machines,
 * downtimes are `open` / `closed`, and the release check judges material with the readiness engine.
 *
 * Routing: OP-10 machine (Section A, day shift) -> OP-20 manual batch "Evening Crew" (Section B, evening shift).
 */
class SchedulingResourcesTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-11-16 08:00:00'; // Monday

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private int $branchId;
    private Product $fg;
    private Product $rm;
    private ProductionBom $bom;
    private WorkCenter $wc;
    private WorkCenterSection $secA;
    private WorkCenterSection $secB;
    private Machine $machine;
    private SectionManualProduction $eveningCrew;
    private SectionManualProduction $dayCrew;
    /** @var array<string, RoutingOperation> */
    private array $op = [];
    /** @var array<string, RoutingOperationMaterial> */
    private array $alloc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Carbon::setTestNow('2026-11-15 09:00:00');

        $this->tenant = Tenant::create(['name' => 'SRC', 'slug' => 'src', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'src@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->mock(\App\Domains\Production\Services\RoutingCostStatusService::class, function ($mock) {
            $mock->shouldReceive('check')->andReturn(['outdated' => false, 'not_calculated' => false, 'action' => '', 'operations' => [], 'inactive' => []]);
        });

        $branch = ['name' => 'BR', 'code' => 'BR-' . $this->tenant->id, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('branches', 'tenant_id')) {
            $branch['tenant_id'] = $this->tenant->id;
        }
        $this->branchId = (int) DB::table('branches')->insertGetId($branch);
        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);

        $day = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'DAY', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'break_minutes' => 0, 'active' => true]);
        $evening = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Evening', 'code' => 'EVE', 'start_time' => '16:00:00', 'end_time' => '22:00:00', 'break_minutes' => 0, 'active' => true]);

        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Extrusion', 'code' => 'WC-EX', 'status' => 'active']);
        $this->secA = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Line A', 'code' => 'SEC-A', 'status' => 'active', 'assigned_shifts' => [$day->id]]);
        $this->secB = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Packing', 'code' => 'SEC-B', 'status' => 'active', 'assigned_shifts' => [$day->id, $evening->id]]);
        foreach ([$this->secA, $this->secB] as $section) {
            WorkCenterSectionCalculation::create(['tenant_id' => $this->tenant->id, 'section_id' => $section->id, 'snapshot_number' => 1, 'batch_id' => 'test-batch', 'trigger_type' => 'manual', 'calculation_details' => ['routing_resources' => ['machines' => [], 'manual_batches' => []]]]);
        }

        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->secA->id, 'name' => 'Extruder 1', 'code' => 'EX-1', 'status' => Machine::STATUS_ACTIVE]);
        $this->eveningCrew = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->secB->id, 'shift_id' => $evening->id, 'batch_name' => 'Evening Crew', 'capacity_per_hour' => 10, 'active' => true]);
        $this->dayCrew = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->secB->id, 'shift_id' => $day->id, 'batch_name' => 'Day Crew', 'capacity_per_hour' => 10, 'active' => true]);

        $this->fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'FG', 'sku' => 'FG', 'type' => 'finished_good', 'status' => 'active', 'uom_id' => $this->uom->id, 'unit_cost' => 1, 'supplier_method' => 'manufacture']);
        $this->rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'RM', 'sku' => 'RM', 'type' => 'raw_material', 'status' => 'active', 'uom_id' => $this->uom->id, 'unit_cost' => 1, 'supplier_method' => 'trade']);

        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RT-FG', 'name' => 'FG route', 'version' => '1.0.0', 'status' => Routing::STATUS_ACTIVE, 'effective_from' => now()->toDateString()]);
        $this->bom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => 'BOM-FG', 'bom_name' => 'FG BOM', 'bom_type' => 'manufacturing', 'product_id' => $this->fg->id, 'routing_id' => $routing->id, 'base_quantity' => 1, 'base_uom_id' => $this->uom->id, 'version' => '1.0.0', 'status' => 'approved', 'effective_date' => now()->toDateString()]);

        $this->routingOp($routing, 'OP-10', 10, null, $this->secA, $this->machine, null);
        $this->routingOp($routing, 'OP-20', 20, null, $this->secB, null, $this->eveningCrew);
        $this->routingOp($routing, 'OP-25', 25, 'OP-20', $this->secB, null, $this->dayCrew);
    }

    private function routingOp(Routing $routing, string $number, int $seq, ?string $replaces, WorkCenterSection $section, ?Machine $machine, ?SectionManualProduction $batch): void
    {
        $op = RoutingOperation::create([
            'tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => $seq, 'operation_number' => $number, 'name' => "FG {$number}",
            'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $section->id,
            'machine_id' => $machine?->id, 'section_manual_production_id' => $batch?->id,
            'is_alternative' => $replaces !== null, 'replaces_operation_id' => $replaces ? $this->op[$replaces]->id : null, 'priority' => 1,
            'setup_time_minutes' => 30, 'processing_time_minutes' => 6, 'cost_per_unit' => 1,
            'section_calculation_id' => WorkCenterSectionCalculation::where('section_id', $section->id)->value('id'), 'section_snapshot_number' => 1,
            'costs_calculated_at' => now(),
        ]);
        $this->op[$number] = $op;
        $this->alloc[$number] = RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id, 'bom_id' => $this->bom->id, 'routing_operation_id' => $op->id, 'material_id' => $this->rm->id,
            'quantity' => 1, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0, 'is_alternative' => false,
        ]);
    }

    private function createOrder(): ProductionOrder
    {
        $ops = ['OP-10', 'OP-20'];

        return app(ProductionOrderService::class)->createDirect([
            'product_id' => $this->fg->id, 'quantity_ordered' => 10, 'branch_id' => $this->branchId,
            'start_date' => '2026-11-16', 'end_date' => '2026-11-20',
            'engineering_selection_submitted' => 1,
            'selected_operation_ids' => array_map(fn ($k) => $this->op[$k]->id, $ops),
            'selected_material_ids' => array_map(fn ($k) => $this->alloc[$k]->id, $ops),
        ], $this->tenant->id, $this->user->id);
    }

    private function schedule(ProductionOrder $order, string $type = ProductionSchedule::TYPE_FORWARD, string $date = self::START): ProductionSchedule
    {
        return app(SchedulingService::class)->generateSchedule($order, Carbon::parse($date), $type)->load('operations');
    }

    // ───────────────────────────── capacity: every resource in its OWN shifts

    public function test_capacity_counts_each_resource_in_its_own_shifts(): void
    {
        $svc = app(SchedulingService::class);
        $monday = Carbon::parse('2026-11-16');

        // machine (Section A: day 480) + Evening Crew (its shift only: 360) + Day Crew (day 480) = 1320
        // (the old uniform formula gave: all work-center shifts 840 x 3 resources = 2520)
        $this->assertEqualsWithDelta(1320.0, $svc->calculateCapacity($this->wc->id, $monday), 0.01);
        $this->assertEqualsWithDelta(360.0, $svc->resourceDailyMinutes($this->wc, ['section_id' => $this->secB->id, 'manual_production_id' => $this->eveningCrew->id], $monday), 0.01);
        $this->assertEqualsWithDelta(480.0, $svc->resourceDailyMinutes($this->wc, ['section_id' => $this->secA->id, 'manual_production_id' => null], $monday), 0.01);
        $this->assertEqualsWithDelta(0.0, $svc->resourceDailyMinutes($this->wc, ['section_id' => $this->secA->id], Carbon::parse('2026-11-15')), 0.01, 'Sunday: not a working day');

        $this->eveningCrew->update(['active' => false]);
        $svc->clearCache();
        $this->assertEqualsWithDelta(960.0, $svc->calculateCapacity($this->wc->id, $monday), 0.01, 'an inactive batch adds no capacity');
    }

    public function test_batch_capacity_screen_data(): void
    {
        $this->schedule($this->createOrder());
        $rows = collect(app(CapacityPlanningService::class)->getBatchCapacity($this->tenant->id, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-18')));

        $evening = $rows->first(fn ($r) => $r['batch']->id === $this->eveningCrew->id);
        $this->assertNotNull($evening);
        $this->assertEqualsWithDelta(3 * 6.0, $evening['available_hours'], 0.01, '3 working days x the 6 h evening shift');
        $this->assertEqualsWithDelta(1.5, $evening['required_hours'], 0.01, 'the 90 minute operation');
        $this->assertSame(0, $rows->where('batch.id', $this->dayCrew->id)->first()['ops_count']);
    }

    // ───────────────────────────── dispatch board

    public function test_dispatch_board_has_a_lane_for_each_manual_batch_and_no_alternate_machines(): void
    {
        $this->schedule($this->createOrder());
        $data = app(CapacityPlanningService::class)->getDispatchBoardData($this->tenant->id, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-20'));

        $res = collect($data['resources'])->firstWhere('id', $this->wc->id);
        $this->assertEqualsCanonicalizing(['Day Crew', 'Evening Crew'], collect($res['batches'])->pluck('name')->all());
        $this->assertSame('Batch_' . $this->eveningCrew->id, collect($res['batches'])->firstWhere('name', 'Evening Crew')['resource_key']);

        $ops = collect($data['operations']);
        $machineOp = $ops->firstWhere('sequence', 10);
        $batchOp = $ops->firstWhere('sequence', 20);

        $this->assertSame('machine', $machineOp['resource_type']);
        $this->assertSame([$this->machine->id], $machineOp['qualified_machine_ids'], 'RT-11: only the operation\'s own machine');
        $this->assertSame('batch', $batchOp['resource_type']);
        $this->assertSame($this->eveningCrew->id, $batchOp['section_manual_production_id']);
        $this->assertSame('Evening Crew', $batchOp['resource_name']);
        $this->assertSame('Packing', $batchOp['section_name']);
        $this->assertSame([], $batchOp['qualified_machine_ids'], 'a batch operation has no machine');

        $filtered = app(CapacityPlanningService::class)->getDispatchBoardData($this->tenant->id, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-20'), ['manual_production_id' => $this->eveningCrew->id]);
        $this->assertCount(1, $filtered['operations']);
    }

    // ───────────────────────────── downtime: open / closed, never "until now"

    public function test_open_downtime_without_end_blocks_release_instead_of_looking_free(): void
    {
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'machine_id' => $this->machine->id,
            'category' => 'Breakdown', 'reason' => 'Spindle failure', 'start_time' => '2026-11-15 06:00:00', 'end_time' => null,
            'status' => ProductionMachineDowntime::STATUS_OPEN, 'created_by' => $this->user->id,
        ]);

        $schedule = $this->schedule($this->createOrder());
        $machineOp = $schedule->operations->firstWhere('sequence', 10);

        $this->assertContains('MACHINE_DOWNTIME_OPEN', collect($machineOp->warnings)->pluck('code')->all(), 'the slot is flagged, not silently free');

        $result = app(SchedulePreReleaseValidationService::class)->validate($schedule);
        $this->assertFalse($result['can_release']);
        $this->assertContains('MACHINE_DOWNTIME_OPEN', array_column($result['errors'], 'code'));

        // closing it clears the block
        ProductionMachineDowntime::query()->update(['status' => ProductionMachineDowntime::STATUS_CLOSED, 'end_time' => '2026-11-15 12:00:00']);
        $this->assertNotContains('MACHINE_DOWNTIME_OPEN', array_column(app(SchedulePreReleaseValidationService::class)->validate($schedule)['errors'], 'code'));
    }

    // ───────────────────────────── release check uses the readiness engine

    public function test_release_check_reports_material_not_issued_as_a_warning_from_the_readiness_engine(): void
    {
        $schedule = $this->schedule($this->createOrder());
        $result = app(SchedulePreReleaseValidationService::class)->validate($schedule);

        $this->assertTrue($result['can_release'], 'material is a warning to confirm (D-04), never a block');
        $this->assertTrue($result['has_warnings']);
        $codes = array_column($result['warnings'], 'code');
        $this->assertContains('MATERIAL_MINIMUM_NOT_ISSUED', $codes, 'UR-01: nothing issued -> "minimum quantity not issued"; ' . implode(',', $codes));
        $this->assertContains('READINESS_SUMMARY', array_column($result['info'], 'code'));
    }

    // ───────────────────────────── UR-03: Resources column (machine AND manual batch, and whether another operation is on it)

    public function test_resources_column_names_every_resource_and_flags_another_operation_on_it(): void
    {
        $first = $this->schedule($this->createOrder());
        $svc = app(\App\Domains\Production\Services\ProductionReadinessService::class);
        $machineOp = ProductionOrderOperation::findOrFail($first->operations->firstWhere('sequence', 10)->production_order_operation_id);
        $batchOp = ProductionOrderOperation::findOrFail($first->operations->firstWhere('sequence', 20)->production_order_operation_id);

        $res = $svc->evaluateOperationReadiness($machineOp)['machine']['resources'][0];
        $this->assertSame(['machine', 'Extruder 1', 'ready'], [$res['type'], $res['name'], $res['state']]);
        $res = $svc->evaluateOperationReadiness($batchOp)['machine']['resources'][0];
        $this->assertSame(['manual_batch', 'Evening Crew', 'ready'], [$res['type'], $res['name'], $res['state']]);

        // a second order books the same machine in the same time slot -> "booked" (information, not a block)
        $second = $this->schedule($this->createOrder());
        $firstSlot = $first->operations->firstWhere('sequence', 10);
        $secondSlot = $second->operations->firstWhere('sequence', 10);
        $secondSlot->update(['planned_start' => $firstSlot->planned_start, 'planned_finish' => $firstSlot->planned_finish]);
        $secondNumber = ProductionOrder::findOrFail($second->production_order_id)->order_number;

        $eval = $svc->evaluateOperationReadiness($machineOp->fresh());
        $res = $eval['machine']['resources'][0];
        $this->assertSame('booked', $res['state']);
        $this->assertStringContainsString($secondNumber, $res['detail']);
        $this->assertContains('RESOURCE_BOOKED', collect($eval['warnings'])->pluck('code')->all());
        $this->assertSame('READY', $eval['machine']['status'], 'a booking is information, the resource itself is fine');

        // ... and that other operation is running now -> "busy now"
        ProductionOrderOperation::findOrFail($secondSlot->production_order_operation_id)->update(['status' => 'running']);
        $eval = $svc->evaluateOperationReadiness($machineOp->fresh());
        $this->assertSame('busy', $eval['machine']['resources'][0]['state']);
        $this->assertContains('RESOURCE_BUSY', collect($eval['warnings'])->pluck('code')->all());
    }

    public function test_resources_column_keeps_the_existing_machine_and_batch_checks(): void
    {
        $schedule = $this->schedule($this->createOrder());
        $svc = app(\App\Domains\Production\Services\ProductionReadinessService::class);
        $machineOp = ProductionOrderOperation::findOrFail($schedule->operations->firstWhere('sequence', 10)->production_order_operation_id);
        $batchOp = ProductionOrderOperation::findOrFail($schedule->operations->firstWhere('sequence', 20)->production_order_operation_id);

        $this->machine->update(['status' => Machine::STATUS_INACTIVE]);
        $res = $svc->evaluateOperationReadiness($machineOp->fresh())['machine'];
        $this->assertSame('BLOCKED', $res['status']);
        $this->assertSame('unavailable', $res['resources'][0]['state']);

        $this->eveningCrew->update(['active' => false]);
        $res = $svc->evaluateOperationReadiness($batchOp->fresh())['machine'];
        $this->assertSame('BLOCKED', $res['status']);
        $this->assertSame('manual_batch', $res['resources'][0]['type']);
        $this->assertSame('unavailable', $res['resources'][0]['state']);
    }

    // ───────────────────────────── planner moves: the resource's own shifts, one resource per operation

    public function test_moving_a_batch_operation_works_in_the_batch_shift_and_keeps_the_batch(): void
    {
        $schedule = $this->schedule($this->createOrder());
        $batchOp = $schedule->operations->firstWhere('sequence', 20);

        // 21:30 on the evening shift (ends 22:00): 30 minutes that night, the remaining 60 in the NEXT evening shift (16:00) -> 17:00
        app(CapacityPlanningService::class)->rescheduleOperationWithMode($batchOp->id, Carbon::parse('2026-11-16 21:30:00'));

        $batchOp->refresh();
        $this->assertSame('2026-11-17 17:00:00', $batchOp->planned_finish->toDateTimeString(), 'worked out in the Evening Crew shift, not in all work-center shifts');
        $this->assertNull($batchOp->machine_id);
        $this->assertSame($this->eveningCrew->id, (int) $batchOp->section_manual_production_id);
    }

    public function test_a_machine_cannot_be_assigned_to_a_batch_operation_and_an_inactive_batch_cannot_take_a_move(): void
    {
        $schedule = $this->schedule($this->createOrder());
        $batchOp = $schedule->operations->firstWhere('sequence', 20);
        $svc = app(CapacityPlanningService::class);

        try {
            $svc->rescheduleOperationWithMode($batchOp->id, Carbon::parse('2026-11-16 17:00:00'), $this->machine->id);
            $this->fail('a machine must not be assignable to a manual batch operation');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('manual batch', $e->getMessage());
        }

        $this->eveningCrew->update(['active' => false]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inactive');
        $svc->rescheduleOperationWithMode($batchOp->id, Carbon::parse('2026-11-16 17:00:00'));
    }

    public function test_load_balance_suggestion_is_the_next_free_slot_of_the_same_resource(): void
    {
        $first = $this->schedule($this->createOrder());
        $second = $this->schedule($this->createOrder());
        $secondBatchOp = $second->operations->firstWhere('sequence', 20);
        // force a clash with the first order's batch slot
        $secondBatchOp->update(['planned_start' => $first->operations->firstWhere('sequence', 20)->planned_start, 'planned_finish' => $first->operations->firstWhere('sequence', 20)->planned_finish]);

        $suggestions = app(CapacityPlanningService::class)->getLoadBalanceSuggestions($secondBatchOp->id);

        $this->assertCount(1, $suggestions, 'one suggestion: the same resource — no alternate machines');
        $this->assertSame('batch', $suggestions[0]['resource_type']);
        $this->assertSame('Evening Crew', $suggestions[0]['machine']->name);
        $this->assertTrue($suggestions[0]['suggested_start']->gte($first->operations->firstWhere('sequence', 20)->planned_finish));
    }

    // ───────────────────────────── leveling: a manual batch double booking is resolved in time

    public function test_leveling_resolves_a_manual_batch_double_booking_by_shifting_in_time(): void
    {
        $first = $this->schedule($this->createOrder());
        $second = $this->schedule($this->createOrder());
        $firstBatch = $first->operations->firstWhere('sequence', 20);
        $secondBatch = $second->operations->firstWhere('sequence', 20);
        $secondBatch->update(['planned_start' => $firstBatch->planned_start, 'planned_finish' => $firstBatch->planned_finish]);

        $preview = app(CapacityLevelingService::class)->generatePreview($this->tenant->id, ['start_date' => '2026-11-16', 'end_date' => '2026-11-20'], $this->user->id);

        $changes = collect($preview['proposed_changes'] ?? $preview['run']['proposed_changes'] ?? $preview['changes'] ?? []);
        $this->assertTrue($changes->isNotEmpty(), 'the batch double booking is detected (it used to be machine-only): ' . json_encode(array_keys($preview)));
        $change = $changes->first();
        $this->assertNull($change['new_machine_id'], 'no machine is ever assigned to a batch operation');
        $this->assertStringContainsString('manual batch', strtolower($change['reason']));
        $this->assertSame(0, $preview['summary']['machines_reassigned'] ?? 0);
    }

    // ───────────────────────────── what-if scenarios keep the batch

    public function test_scenario_snapshot_keeps_the_manual_batch_and_levels_its_collisions(): void
    {
        $first = $this->schedule($this->createOrder());
        $second = $this->schedule($this->createOrder());
        $secondBatch = $second->operations->firstWhere('sequence', 20);
        $firstBatch = $first->operations->firstWhere('sequence', 20);
        $secondBatch->update(['planned_start' => $firstBatch->planned_start, 'planned_finish' => $firstBatch->planned_finish]);

        $svc = app(ProductionScheduleScenarioService::class);
        $scenario = $svc->createScenario($this->tenant->id, ['name' => 'What if', 'start_date' => '2026-11-16', 'end_date' => '2026-11-20'], $this->user->id);

        $scenOps = $scenario->scenarioOperations;
        $batchScenOps = $scenOps->where('section_manual_production_id', $this->eveningCrew->id);
        $this->assertCount(2, $batchScenOps, 'both batch operations keep their manual batch inside the scenario');
        $this->assertTrue($batchScenOps->every(fn ($o) => $o->machine_id === null && $o->resourceKey() === 'b' . $this->eveningCrew->id));

        $result = $svc->levelScenarioCapacity($this->tenant->id, $scenario->id);
        $this->assertSame(0, $result['machines_reassigned']);
        $this->assertSame(1, $result['operations_shifted'], 'the double booking on the batch is moved in time');
    }

    // ───────────────────────────── calendar: one row per manual batch

    public function test_calendar_has_a_row_per_manual_batch_with_its_operations(): void
    {
        $this->schedule($this->createOrder());
        $data = app(SchedulingCalendarService::class)->buildCalendarData($this->tenant->id, ['view' => 'week', 'start' => '2026-11-16']);

        $wc = collect($data['workCentersData'])->firstWhere('id', $this->wc->id);
        $rows = collect($wc['machines']);
        $evening = $rows->firstWhere('name', 'Evening Crew');

        $this->assertNotNull($evening, 'a lane for the manual batch');
        $this->assertSame('batch', $evening['type']);
        $this->assertStringContainsString('Manual batch', $evening['code']);
        $this->assertSame(1, collect($evening['lanes'])->sum(fn ($l) => count($l['operations'])), 'the batch operation sits in its own lane');
        $this->assertNull($rows->firstWhere('name', 'General / Manual lane'), 'batch operations are no longer lumped into a generic lane');
        $this->assertSame('Evening Crew (manual batch)', collect($evening['lanes'])->flatMap(fn ($l) => $l['operations'])->first()['resource']);
    }

    // ───────────────────────────── shop floor: one resource, one operation at a time

    public function test_shop_floor_start_rejects_a_machine_on_a_batch_operation_and_a_second_run_on_the_same_batch(): void
    {
        $firstOrder = $this->createOrder();
        $first = $this->schedule($firstOrder);
        $secondOrder = $this->createOrder();
        $second = $this->schedule($secondOrder);

        foreach ([$first, $second] as $schedule) {
            // predecessors done, the batch operations ready
            $machineSched = $schedule->operations->firstWhere('sequence', 10);
            $batchSched = $schedule->operations->firstWhere('sequence', 20);
            $machineSched->update(['status' => 'completed']);
            ProductionOrderOperation::whereKey($machineSched->production_order_operation_id)->update(['status' => 'completed']);
            $batchSched->update(['status' => 'ready']);
            ProductionOrderOperation::whereKey($batchSched->production_order_operation_id)->update(['status' => 'ready']);
        }

        $mes = app(MesExecutionService::class);
        $firstBatch = $first->operations->firstWhere('sequence', 20);
        $secondBatch = $second->operations->firstWhere('sequence', 20);

        try {
            $mes->startOperation($firstBatch->id, $this->machine->id, $this->user->id);
            $this->fail('a machine must not be assignable to a manual batch operation at start');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('manual batch', $e->getMessage());
        }

        $mes->startOperation($firstBatch->id, null, $this->user->id);
        $this->assertSame('running', $firstBatch->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already running another operation');
        $mes->startOperation($secondBatch->id, null, $this->user->id);
    }
}
