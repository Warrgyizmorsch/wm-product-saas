<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\RoutingCostService;
use App\Domains\Production\Services\RoutingLifecycleService;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RT-IMPL-07 (API uses the web form rules) and RT-IMPL-14 (calculate once, check, save, snapshot numbers,
 * readers use saved values, approval record, outdated warnings).
 */
class RoutingCalculateOnceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private Machine $machine;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'CO', 'slug' => 'co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'co@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $shift = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-CO', 'status' => 'active']);
        $this->section = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Press Bay', 'code' => 'SEC-P', 'status' => 'active', 'assigned_shifts' => [$shift->id]]);
        // 60 u/h → 1 min/unit; 30/hr → 0.5/min
        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'name' => 'Press', 'code' => 'MC-P', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60, 'operating_cost_per_hour' => 30]);
        // non-batch operator: daily 1,000 over 8 h, 1 machine → 125/hr
        $emp = Employee::create(['tenant_id' => $this->tenant->id, 'employee_id' => 'E1', 'full_name' => 'Op', 'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, 'shift_id' => $shift->id, 'status' => true]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'employee_id' => $emp->id, 'active' => true]);
    }

    private function recalc(): void
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    private function resources(): array
    {
        return app(SectionCalculationService::class)->getSnapshotResourceCosts($this->section->fresh());
    }

    /** An operation as the form sends it: figures from the ONE calculator + snapshot reference. */
    private function operation(array $overrides = []): array
    {
        $op = array_merge([
            'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Press', 'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id,
            'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80,
            'is_external' => 0,
        ], $overrides);
        $res = $this->resources();
        $calc = app(RoutingOperationCostCalculator::class)->calculate($op, $res);
        foreach ($calc['figures'] as $k => $v) {
            $op[$k] = number_format($v, 4, '.', '');
        }
        $op['section_calculation_id'] = $res['section_calculation_id'];
        $op['section_snapshot_number'] = $res['snapshot_number'];

        return $op;
    }

    private function web()
    {
        return $this->withHeaders(['X-Tenant' => 'co']);
    }

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json', 'X-Tenant' => 'co', 'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken,
        ];
    }

    // ── endpoint reads the snapshot ─────────────────────────────────────────

    public function test_resources_endpoint_reads_the_latest_section_snapshot(): void
    {
        $this->web()->getJson(route('production.sections.resources', $this->section->id))
            ->assertOk()->assertJson(['not_calculated' => true]);

        $this->recalc();
        $snap = WorkCenterSectionCalculation::where('section_id', $this->section->id)->latest('id')->first();
        $this->assertArrayHasKey('routing_resources', $snap->calculation_details);

        $res = $this->web()->getJson(route('production.sections.resources', $this->section->id))->assertOk()->json();
        $this->assertFalse($res['not_calculated']);
        $this->assertSame($snap->id, $res['section_calculation_id']);
        $this->assertSame(1, $res['snapshot_number']);
        $this->assertEqualsWithDelta(30.0, $res['machines'][0]['operating_cost_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(125.0, $res['operator_labor_cost_per_machine_per_hour'], 0.0001);

        // a live change is NOT visible until the Work Center is recalculated
        $this->machine->update(['operating_cost_per_hour' => 90]);
        $res = $this->web()->getJson(route('production.sections.resources', $this->section->id))->json();
        $this->assertEqualsWithDelta(30.0, $res['machines'][0]['operating_cost_per_hour'], 0.0001);
    }

    // ── web save: check, then save as sent ─────────────────────────────────

    public function test_web_save_checks_figures_and_saves_them_with_the_snapshot_reference(): void
    {
        $this->recalc();
        $op = $this->operation();

        $this->web()->post(route('production.routing.store'), ['name' => 'Press Route', 'operations' => [$op]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $saved = RoutingOperation::where('operation_number', 'OP-010')->firstOrFail();
        // run 1 + per piece 0.5 = 1.5 min; labor 125/60 = 2.0833; machine 0.5; yield 80 → ×1.25
        $this->assertEqualsWithDelta(1.0, $saved->processing_time_minutes, 0.0001);
        $this->assertEqualsWithDelta(2.0833, $saved->labor_cost_rate, 0.0001);
        $this->assertEqualsWithDelta(0.5, $saved->machine_cost_rate, 0.0001);
        $this->assertEqualsWithDelta((float) $op['labor_cost_per_unit'], $saved->labor_cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(round(1.5 * 0.5 * 1.25, 4), $saved->machine_cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta((float) $op['cost_per_unit'], $saved->cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(round(20 * (2.0833 + 0.5), 4), $saved->setup_cost_per_run, 0.0001);
        $this->assertSame((int) $op['section_calculation_id'], $saved->section_calculation_id);
        $this->assertSame(1, $saved->section_snapshot_number);
        $this->assertNotNull($saved->costs_calculated_at);

        // readers use the saved values
        $summary = app(RoutingCostService::class)->calculateRoutingCost($saved->routing_id);
        $this->assertEqualsWithDelta($saved->cost_per_unit, $summary['cost_per_unit'], 0.0001);
        $this->assertEqualsWithDelta($saved->setup_cost_per_run, $summary['setup_cost_per_run'], 0.0001);
        $this->web()->get(route('production.routing.show', $saved->routing_id))->assertOk()->assertSee('Snapshot #1', false)
            ->assertDontSee('routing-cost-status-banner', false);
    }

    public function test_web_save_refuses_tampered_figures_and_uncalculated_sections(): void
    {
        // not calculated yet
        $op = ['sequence' => 10, 'name' => 'Press', 'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id, 'cost_per_unit' => 1];
        $this->web()->post(route('production.routing.store'), ['name' => 'R1', 'operations' => [$op]])
            ->assertSessionHasErrors(['operations.0.section_id' => 'Section SEC-P has not been calculated yet. Recalculate the Work Center first.']);

        $this->recalc();
        $op = $this->operation();
        $op['cost_per_unit'] = '0.0001';
        $this->web()->post(route('production.routing.store'), ['name' => 'R2', 'operations' => [$op]])
            ->assertSessionHasErrors(['operations.0.cost_per_unit' => 'Operation OP-010: cost does not match the selected machine / batch. Reload the resource and save again.']);
        $this->assertSame(0, RoutingOperation::count());
    }

    // ── RT-IMPL-07: API uses the same rules ─────────────────────────────────

    public function test_api_uses_the_web_form_rules(): void
    {
        $this->recalc();
        $url = '/api/v1/production/routings';

        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'API Route', 'operations' => [$this->operation()]])
            ->assertStatus(201);
        $this->assertSame(1, RoutingOperation::where('section_id', $this->section->id)->count(), 'API keeps the section');

        $noSection = $this->operation(['section_id' => null]);
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A2', 'operations' => [$noSection]])
            ->assertStatus(422)->assertJsonPath('errors', fn($e) => isset($e['operations.0.section_id']));

        $neither = $this->operation(['machine_id' => null]);
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A3', 'operations' => [$neither]])
            ->assertStatus(422)->assertJsonPath('errors', fn($e) => isset($e['operations.0.machine_id']));

        $batch = \App\Domains\Production\Models\SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => ProductionShift::first()->id, 'batch_name' => 'B', 'capacity_per_hour' => 10, 'active' => true]);
        $both = $this->operation(['section_manual_production_id' => $batch->id]);
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A4', 'operations' => [$both]])
            ->assertStatus(422)->assertJsonPath('errors', fn($e) => isset($e['operations.0.machine_id']));

        $otherWc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC2', 'code' => 'WC-2', 'status' => 'active']);
        $wrongWc = $this->operation(['work_center_id' => $otherWc->id]);
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A5', 'operations' => [$wrongWc]])
            ->assertStatus(422)->assertJsonPath('errors', fn($e) => isset($e['operations.0.section_id']));

        // another tenant's section is not "exists" for this tenant
        $t2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'status' => 'active', 'plan' => 'enterprise']);
        $foreign = WorkCenterSection::withoutGlobalScopes()->create(['tenant_id' => $t2->id, 'work_center_id' => $otherWc->id, 'name' => 'F', 'code' => 'F', 'status' => 'active']);
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A6', 'operations' => [$this->operation(['section_id' => $foreign->id])]])
            ->assertStatus(422);

        // API figures are checked too
        $bad = $this->operation();
        $bad['machine_cost_rate'] = '9.9999';
        $this->withHeaders($this->apiHeaders())->postJson($url, ['name' => 'A7', 'operations' => [$bad]])
            ->assertStatus(422)->assertJsonPath('errors', fn($e) => isset($e['operations.0.cost_per_unit']));
    }

    // ── approval record + outdated warnings + old routings ─────────────────

    public function test_approval_snapshot_outdated_warning_and_not_calculated_notice(): void
    {
        $this->recalc();
        $this->web()->post(route('production.routing.store'), ['name' => 'Press Route', 'operations' => [$this->operation()]])->assertSessionHasNoErrors();
        $routing = Routing::where('name', 'Press Route')->firstOrFail();
        $saved = $routing->operations()->first();

        app(RoutingLifecycleService::class)->transitionOnApproval($routing->fresh(), $this->user->id);
        $snap = $routing->fresh()->cost_snapshot;
        $this->assertEqualsWithDelta($saved->cost_per_unit, $snap['cost_per_unit'], 0.0001);
        $this->assertSame(1, $snap['operations'][0]['section_snapshot_number']);

        // machine cost changes + Work Center recalculated → snapshot #2 with different figures
        $this->machine->update(['operating_cost_per_hour' => 60]);
        $this->recalc();
        $this->web()->get(route('production.routing.show', $routing->id))->assertOk()
            ->assertSee('routing-cost-status-banner', false)
            ->assertSee('Costs outdated — section SEC-P has a newer calculation (snapshot #2).', false)
            ->assertSee('Machine MC-P cost changed', false)
            ->assertSee('Create a new routing version to update.', false);
        $this->web()->get(route('production.routing.index'))->assertOk()->assertSee('routing-costs-outdated-badge', false);
        // saved figures did not move
        $this->assertEqualsWithDelta($saved->cost_per_unit, $saved->fresh()->cost_per_unit, 0.0001);

        // an old operation without saved figures
        $old = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RTG-OLD', 'name' => 'Old', 'version' => '1.0.0', 'status' => Routing::STATUS_DRAFT, 'effective_from' => now()->toDateString()]);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $old->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'X', 'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id, 'processing_time_minutes' => 1]);
        $this->web()->get(route('production.routing.show', $old->id))->assertOk()
            ->assertSee('Costs not calculated — open and save the routing', false)
            ->assertSee('Open and save the routing to refresh costs.', false);
    }
}
