<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulePreReleaseValidationService;
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
 * Scheduling on the Work Center → Section architecture:
 * an operation runs on a Machine OR a Manual Batch inside a Section, in that resource's shifts;
 * an alternative operation is chosen at Production Order creation (no alternate machines).
 *
 * Routing: OP-10 machine (Section A, day shift) → OP-20 manual batch "Evening Crew" (Section B, evening shift)
 *          with alternative OP-25 on manual batch "Day Crew" (Section B, day shift).
 */
class SectionArchitectureSchedulingTest extends TestCase
{
    use RefreshDatabase;

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

    /** Monday */
    private const START = '2026-11-16 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'SCH', 'slug' => 'sch', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'sch@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        // Routing cost freshness (RT-IMPL-14/18) is not what this test is about — keep the order-creation gate open.
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

        // 10 pcs: setup 30 + 6 min/pc → 90 min per operation
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

    /** @param string[] $ops chosen operation numbers (one per stage) */
    private function createOrder(array $ops = ['OP-10', 'OP-20']): ProductionOrder
    {
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

    public function test_order_operations_snapshot_section_machine_and_manual_batch(): void
    {
        $ops = ProductionOrderOperation::where('production_order_id', $this->createOrder()->id)->orderBy('sequence')->get();

        $this->assertCount(2, $ops);
        $this->assertSame([$this->secA->id, $this->machine->id, null], [$ops[0]->section_id, (int) $ops[0]->machine_id, $ops[0]->section_manual_production_id]);
        $this->assertSame([$this->secB->id, null, $this->eveningCrew->id], [$ops[1]->section_id, $ops[1]->machine_id, $ops[1]->section_manual_production_id]);
    }

    public function test_forward_schedule_books_machine_and_manual_batch_in_their_own_shifts(): void
    {
        $schedule = $this->schedule($this->createOrder());
        [$machineOp, $batchOp] = [$schedule->operations->firstWhere('sequence', 10), $schedule->operations->firstWhere('sequence', 20)];

        // Machine op: Section A, day shift
        $this->assertSame($this->machine->id, (int) $machineOp->machine_id);
        $this->assertSame($this->secA->id, $machineOp->section_id);
        $this->assertNull($machineOp->section_manual_production_id);
        $this->assertSame('Machine_' . $this->machine->id, $machineOp->resource_id);
        $this->assertSame('2026-11-16 08:00:00', $machineOp->planned_start->toDateTimeString());
        $this->assertSame('2026-11-16 09:30:00', $machineOp->planned_finish->toDateTimeString());

        // Manual batch op: no machine, booked on the batch, waits for the batch's evening shift
        $this->assertNull($batchOp->machine_id);
        $this->assertSame($this->eveningCrew->id, $batchOp->section_manual_production_id);
        $this->assertSame($this->secB->id, $batchOp->section_id);
        $this->assertSame('Batch_' . $this->eveningCrew->id, $batchOp->resource_id);
        $this->assertSame('2026-11-16 16:00:00', $batchOp->planned_start->toDateTimeString());
        $this->assertSame('2026-11-16 17:30:00', $batchOp->planned_finish->toDateTimeString());
        $this->assertEmpty(collect($batchOp->warnings)->whereIn('code', ['NO_RESOURCE_ASSIGNED', 'NO_AVAILABLE_MACHINE', 'NO_SHIFTS_CONFIGURED'])->all());
    }

    public function test_alternative_operation_chosen_at_order_creation_is_the_one_scheduled(): void
    {
        $schedule = $this->schedule($this->createOrder(['OP-10', 'OP-25']));
        $this->assertCount(2, $schedule->operations);

        $altOp = $schedule->operations->firstWhere('sequence', 25);
        $this->assertSame($this->dayCrew->id, $altOp->section_manual_production_id);
        $this->assertSame('2026-11-16 09:30:00', $altOp->planned_start->toDateTimeString(), 'Day Crew works the day shift — starts right after OP-10');
    }

    public function test_second_order_queues_behind_the_first_on_the_same_machine_and_batch(): void
    {
        $this->schedule($this->createOrder());
        $second = $this->schedule($this->createOrder());

        $this->assertSame('2026-11-16 09:30:00', $second->operations->firstWhere('sequence', 10)->planned_start->toDateTimeString());
        $this->assertSame('2026-11-16 17:30:00', $second->operations->firstWhere('sequence', 20)->planned_start->toDateTimeString());
        $this->assertSame([], app(SchedulingService::class)->detectConflictsForSchedule($second));
    }

    public function test_backward_schedule_supports_machine_and_manual_batch(): void
    {
        $schedule = $this->schedule($this->createOrder(), ProductionSchedule::TYPE_BACKWARD, '2026-11-18 22:00:00');
        [$machineOp, $batchOp] = [$schedule->operations->firstWhere('sequence', 10), $schedule->operations->firstWhere('sequence', 20)];

        $this->assertSame($this->eveningCrew->id, $batchOp->section_manual_production_id);
        $this->assertSame('2026-11-18 20:30:00', $batchOp->planned_start->toDateTimeString());
        $this->assertSame('2026-11-18 22:00:00', $batchOp->planned_finish->toDateTimeString());
        $this->assertSame($this->machine->id, (int) $machineOp->machine_id);
        $this->assertSame('2026-11-18 16:00:00', $machineOp->planned_finish->toDateTimeString(), 'machine works the day shift only');
    }

    public function test_pre_release_check_passes_and_blocks_on_inactive_resources(): void
    {
        $schedule = $this->schedule($this->createOrder());
        $validator = app(SchedulePreReleaseValidationService::class);

        $result = $validator->validate($schedule);
        $this->assertTrue($result['can_release'], json_encode($result['errors']));

        $this->eveningCrew->update(['active' => false]);
        $this->assertContains('MANUAL_BATCH_INACTIVE', array_column($validator->validate($schedule)['errors'], 'code'));
        $this->eveningCrew->update(['active' => true]);

        $this->machine->update(['status' => Machine::STATUS_INACTIVE]);
        $this->assertContains('MACHINE_INACTIVE', array_column($validator->validate($schedule)['errors'], 'code'));
        $this->machine->update(['status' => Machine::STATUS_ACTIVE]);

        $this->secB->update(['status' => WorkCenterSection::STATUS_INACTIVE]);
        $this->assertContains('INACTIVE_SECTION', array_column($validator->validate($schedule)['errors'], 'code'));
    }

    public function test_schedule_screen_shows_and_releases_to_shop_floor(): void
    {
        $order = $this->createOrder();
        app(ProductionOrderService::class)->release($order->id, $this->user->id, true);
        $headers = ['X-Tenant' => 'sch'];

        $this->withHeaders($headers)->post(route('production.schedules.store'), [
            'production_order_id' => $order->id, 'scheduling_type' => ProductionSchedule::TYPE_FORWARD, 'start_date' => self::START,
        ])->assertSessionMissing('error');
        $schedule = ProductionSchedule::where('production_order_id', $order->id)->firstOrFail();

        $this->withHeaders($headers)->get(route('production.schedules.show', $schedule->id))
            ->assertOk()->assertSee('Evening Crew')->assertSee('Extruder 1')->assertSee('Packing');

        $this->withHeaders($headers)->post(route('production.schedules.release', $schedule->id), ['confirm_warnings' => 1])
            ->assertSessionMissing('error')->assertSessionMissing('warning');
        $this->assertSame(ProductionSchedule::STATUS_RELEASED, $schedule->fresh()->status);

        $this->withHeaders($headers)->get(route('production.mes.dashboard'))->assertOk();

        // Planner screens load with section / manual-batch operations
        $this->withoutExceptionHandling();
        $this->withHeaders($headers)->get(route('production.schedules.index'))->assertOk();
        $this->withHeaders($headers)->get(route('production.schedules.dispatch-board'))->assertOk();
        $board = $this->withHeaders($headers)->getJson(route('production.schedules.dispatch-board.data', ['start_date' => '2026-11-16', 'end_date' => '2026-11-20']));
        $this->assertSame(200, $board->status(), (string) $board->getContent());
        $this->withHeaders($headers)->get(route('production.schedules.calendar'))->assertOk();
        $this->withHeaders($headers)->get(route('production.schedules.work-center-view'))->assertOk();
        $this->withHeaders($headers)->getJson(route('production.schedules.pre-release-check', $schedule->id))->assertOk();
        $this->withExceptionHandling();

        // Shop floor: the first operation can be started on its planned machine
        $first = $schedule->operations()->orderBy('sequence')->firstOrFail();
        app(\App\Domains\Production\Services\MesExecutionService::class)->startOperation($first->id, null, $this->user->id);
        $this->assertSame('running', $first->fresh()->status);
    }
}
