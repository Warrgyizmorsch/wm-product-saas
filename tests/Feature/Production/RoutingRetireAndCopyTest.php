<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;
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
 * RT-IMPL-02 (Retire), RT-IMPL-03 (name + version reuse), RT-IMPL-20 (Duplicate Version saves nothing),
 * and the display rule (4 decimals only when there are decimals; under 1 second = "< 1s").
 */
class RoutingRetireAndCopyTest extends TestCase
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

        $this->tenant = Tenant::create(['name' => 'RC', 'slug' => 'rc', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'rc@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $shift = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-RC', 'status' => 'active']);
        $this->section = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Press Bay', 'code' => 'SEC-R', 'status' => 'active', 'assigned_shifts' => [$shift->id]]);
        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'name' => 'Press', 'code' => 'MC-R', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60, 'operating_cost_per_hour' => 30]);
        $emp = Employee::create(['tenant_id' => $this->tenant->id, 'employee_id' => 'E1', 'full_name' => 'Op', 'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, 'shift_id' => $shift->id, 'status' => true]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'employee_id' => $emp->id, 'active' => true]);

        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    private function operation(array $overrides = []): array
    {
        $op = array_merge([
            'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Press', 'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id,
            'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80, 'is_external' => 0,
        ], $overrides);
        $res = app(SectionCalculationService::class)->getSnapshotResourceCosts($this->section->fresh());
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
        return $this->withHeaders(['X-Tenant' => 'rc']);
    }

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json', 'X-Tenant' => 'rc', 'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken,
        ];
    }

    private function activeRouting(string $name = 'Press Route', string $version = '1.0.0'): Routing
    {
        $this->web()->post(route('production.routing.store'), ['name' => $name, 'version' => $version, 'operations' => [$this->operation()]])
            ->assertSessionHasNoErrors();
        $routing = Routing::where('name', $name)->where('version', $version)->firstOrFail();
        $this->web()->post(route('production.routing.submit', $routing->id))->assertSessionHasNoErrors();
        $this->web()->post(route('production.routing.approve', $routing->id))->assertSessionHasNoErrors();

        return $routing->fresh();
    }

    // ── RT-IMPL-02: Retire ──────────────────────────────────────────────────

    public function test_retire_makes_an_active_routing_historical_and_handles_linked_boms(): void
    {
        $routing = $this->activeRouting();
        $this->assertSame(Routing::STATUS_ACTIVE, $routing->status);

        $product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Widget', 'sku' => 'FG-W', 'type' => 'finished_good', 'status' => 'active']);
        $approvedBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => 'BOM-W1', 'bom_name' => 'W', 'bom_type' => 'manufacturing', 'product_id' => $product->id, 'base_quantity' => 1, 'status' => 'approved', 'version' => '1.0.0', 'effective_date' => now()->toDateString(), 'routing_id' => $routing->id]);
        $draftBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => 'BOM-W2', 'bom_name' => 'W', 'bom_type' => 'manufacturing', 'product_id' => $product->id, 'base_quantity' => 1, 'status' => 'draft', 'version' => '2.0.0', 'effective_date' => now()->toDateString(), 'routing_id' => $routing->id]);

        $this->web()->post(route('production.routing.retire', $routing->id), ['comments' => 'Replaced by 2.0.0'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $routing->refresh();
        $this->assertSame(Routing::STATUS_HISTORICAL, $routing->status);
        $this->assertSame(now()->toDateString(), $routing->effective_to->toDateString());
        $this->assertDatabaseHas('production_routing_approvals', ['routing_id' => $routing->id, 'action' => 'Retired', 'comments' => 'Replaced by 2.0.0']);
        $this->assertSame(ProductionBom::STATUS_INACTIVE, $approvedBom->fresh()->status);
        $this->assertSame('draft', $draftBom->fresh()->status);
        // RT-IMPL-05: nothing stored on the draft BOM — the routing advisory is worked out live
        $this->assertFalse((bool) $draftBom->fresh()->has_warning);
        $this->assertStringContainsString('is historical', $draftBom->fresh()->routing_advisory);

        // historical can't be retired again; a draft can't be retired
        $this->web()->post(route('production.routing.retire', $routing->id))->assertForbidden();
        $this->web()->post(route('production.routing.store'), ['name' => 'Draft One', 'operations' => [$this->operation()]]);
        $draft = Routing::where('name', 'Draft One')->firstOrFail();
        $this->web()->post(route('production.routing.retire', $draft->id))->assertForbidden();

        // show page offers Retire only while active / scheduled
        $other = $this->activeRouting('Other Route');
        $this->web()->get(route('production.routing.show', $other->id))->assertOk()->assertSee(__('production.retire_routing'));
        $this->web()->get(route('production.routing.show', $routing->id))->assertOk()->assertDontSee('id="retireModal"', false);

        // API endpoint
        $this->withHeaders($this->apiHeaders())->postJson("/api/v1/production/routings/{$other->id}/retire")->assertOk();
        $this->assertSame(Routing::STATUS_HISTORICAL, $other->fresh()->status);
    }

    // ── RT-IMPL-20: Duplicate Version saves nothing ─────────────────────────

    public function test_web_duplicate_opens_prefilled_form_and_saves_only_on_save(): void
    {
        $routing = $this->activeRouting();
        $count = Routing::count();

        $this->web()->post(route('production.routing.duplicate', $routing->id), ['new_version' => '1.1.0'])
            ->assertRedirect(route('production.routing.create', ['source_routing_id' => $routing->id, 'version' => '1.1.0']));
        $this->assertSame($count, Routing::count(), 'the Duplicate button saves nothing');

        $this->web()->get(route('production.routing.create', ['source_routing_id' => $routing->id, 'version' => '1.1.0']))
            ->assertOk()
            ->assertSee(__('production.routing_copy_notice'))
            ->assertSee('value="1.1.0"', false)
            ->assertSee('action="' . route('production.routing.store') . '"', false)
            ->assertDontSee('name="_method"', false);

        // existing version → clear message, still nothing saved
        $this->web()->post(route('production.routing.duplicate', $routing->id), ['new_version' => '1.0.0'])
            ->assertSessionHas('error', fn($m) => str_contains($m, 'already exists'));
        $this->assertSame($count, Routing::count());
    }

    public function test_api_duplicate_returns_copy_data_without_saving_and_create_saves_it(): void
    {
        $routing = $this->activeRouting();
        $count = Routing::count();

        $res = $this->withHeaders($this->apiHeaders())->postJson("/api/v1/production/routings/{$routing->id}/duplicate", ['version' => '2.0.0'])
            ->assertOk()->json('data');
        $this->assertSame($count, Routing::count(), 'API duplicate saves nothing');
        $this->assertSame('2.0.0', $res['version']);
        $this->assertSame($routing->id, $res['source_routing_id']);
        $this->assertSame([], $res['warnings']);
        $op = $res['operations'][0];
        $this->assertEqualsWithDelta((float) $routing->operations()->first()->cost_per_unit, (float) $op['cost_per_unit'], 0.0001);
        $this->assertNotNull($op['section_snapshot_number']);

        unset($res['warnings'], $res['source_version']);
        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/production/routings', $res)->assertStatus(201);
        $new = Routing::where('version', '2.0.0')->firstOrFail();
        $this->assertSame($this->tenant->id, (int) $new->tenant_id, 'API create stores the tenant (not the user id)');
        $this->assertSame((int) $routing->revision + 1, (int) $new->revision);
        $this->assertDatabaseHas('production_routing_approvals', ['routing_id' => $new->id, 'action' => 'Revision Created']);
    }

    // ── RT-IMPL-03: cancelled name + version reuse ──────────────────────────

    public function test_cancelled_routing_name_and_version_give_a_clear_message(): void
    {
        $routing = $this->activeRouting('Reuse Route', '1.0.0');
        $this->web()->post(route('production.routing.cancel', $routing->id), ['comments' => 'x'])->assertSessionHasNoErrors();

        $svc = app(\App\Domains\Production\Services\RoutingService::class);
        try {
            $svc->checkRoutingConflicts('Reuse Route', '1.0.0', $this->tenant->id);
            $this->fail('cancelled name + version must still be reported as existing');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }
    }

    // ── display rule ────────────────────────────────────────────────────────

    public function test_display_rule_four_decimals_only_when_there_are_decimals(): void
    {
        $this->assertSame('15', format_decimal(15));
        $this->assertSame('0', format_decimal(0));
        $this->assertSame('11.5000', format_decimal(11.5));
        $this->assertSame('0.0167', format_decimal(0.0167));
        $this->assertSame('15', format_decimal(14.99999)); // 15.0000 at 4 decimals → whole
        $this->assertStringEndsWith('0', format_currency(0, 4));
        $this->assertStringNotContainsString('.', format_currency(0, 4));
        $this->assertStringContainsString('0.0042', format_currency(0.0042, 4));
        $this->assertStringContainsString('.00', format_currency(15, 2), '2-decimal callers unchanged');

        $this->assertSame('< 1s', format_duration(0.01));   // 0.6 s — never rounded up to "1s"
        $this->assertSame('< 1s', format_duration(0.0001));
        $this->assertSame('1s', format_duration(0.0167));   // 1.002 s
        $this->assertSame('0s', format_duration(0));
        $this->assertSame('11m 30s', format_duration(11.5));
    }
}
