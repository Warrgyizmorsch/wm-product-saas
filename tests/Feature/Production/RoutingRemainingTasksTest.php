<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionMasterImportService;
use App\Domains\Production\Services\RoutingLifecycleService;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * RT-IMPL-04, 05, 06, 08, 09, 16, 17, 18.
 */
class RoutingRemainingTasksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private Machine $machine;
    private Machine $machine2;
    private Product $product;
    private Product $material;
    private Uom $uom;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'RR', 'slug' => 'rr', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'rr@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $shift = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-RR', 'status' => 'active']);
        $this->section = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Press Bay', 'code' => 'SEC-RR', 'status' => 'active', 'assigned_shifts' => [$shift->id]]);
        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'name' => 'Press', 'code' => 'MC-R1', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60, 'operating_cost_per_hour' => 30]);
        $this->machine2 = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'name' => 'Press 2', 'code' => 'MC-R2', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 120, 'operating_cost_per_hour' => 40]);
        $emp = Employee::create(['tenant_id' => $this->tenant->id, 'employee_id' => 'E1', 'full_name' => 'Op', 'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, 'shift_id' => $shift->id, 'status' => true]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'employee_id' => $emp->id, 'active' => true]);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Piece', 'code' => 'Pcs']);
        $this->product = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Widget', 'sku' => 'FG-W', 'type' => 'finished_good', 'status' => 'active', 'uom_id' => $this->uom->id]);
        $this->material = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel', 'sku' => 'RM-S', 'type' => 'raw_material', 'status' => 'active', 'uom_id' => $this->uom->id]);

        $this->recalc();
    }

    private function recalc(): void
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    private function operation(array $overrides = []): array
    {
        $op = array_merge([
            'sequence' => 10, 'name' => 'Press', 'operation_type' => 'manufacturing',
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
        return $this->withHeaders(['X-Tenant' => 'rr']);
    }

    private function draftRouting(string $name = 'Route', array $ops = null): Routing
    {
        $this->web()->post(route('production.routing.store'), ['name' => $name, 'operations' => $ops ?? [$this->operation(), $this->operation(['sequence' => 20, 'name' => 'Pack', 'machine_id' => $this->machine2->id])]])
            ->assertSessionHasNoErrors();

        return Routing::where('name', $name)->firstOrFail();
    }

    private function activate(Routing $routing): Routing
    {
        $this->web()->post(route('production.routing.submit', $routing->id))->assertSessionHasNoErrors();
        $this->web()->post(route('production.routing.approve', $routing->id))->assertSessionHasNoErrors();

        return $routing->fresh();
    }

    private function bom(Routing $routing, string $status, string $number): ProductionBom
    {
        return ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => $number, 'bom_name' => $number, 'bom_type' => 'manufacturing', 'product_id' => $this->product->id, 'base_quantity' => 1, 'status' => $status, 'version' => $number, 'effective_date' => now()->toDateString(), 'routing_id' => $routing->id]);
    }

    private function allocate(ProductionBom $bom, RoutingOperation $op): RoutingOperationMaterial
    {
        return RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bom->id, 'routing_operation_id' => $op->id, 'material_id' => $this->material->id, 'quantity' => 2, 'uom_id' => $this->uom->id, 'sequence' => 10]);
    }

    // ── RT-IMPL-06 + RT-IMPL-05 ─────────────────────────────────────────────

    public function test_cancel_handles_each_bom_status_and_advisory_is_live(): void
    {
        $routing = $this->activate($this->draftRouting());
        $approved = $this->bom($routing, 'approved', 'B-APP');
        $pending = $this->bom($routing, 'pending_approval', 'B-PEN');
        $draft = $this->bom($routing, 'draft', 'B-DRA');

        $impact = collect(app(RoutingLifecycleService::class)->bomImpact($routing))->keyBy('bom_number');
        $this->assertSame('Becomes inactive', $impact['B-APP']['effect']);
        $this->assertSame('Goes back to draft', $impact['B-PEN']['effect']);
        $this->assertStringStartsWith('No change', $impact['B-DRA']['effect']);
        $this->web()->get(route('production.routing.show', $routing->id))->assertOk()->assertSee('B-PEN')->assertSee('Goes back to draft');

        $this->assertSame([], $draft->fresh()->routing_advisory, 'active routing → no advisory');

        $this->web()->post(route('production.routing.cancel', $routing->id), ['comments' => 'obsolete'])->assertSessionHasNoErrors();

        $this->assertSame('inactive', $approved->fresh()->status);
        $this->assertSame('draft', $pending->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertFalse((bool) $draft->fresh()->has_warning, 'no routing text stored in the BOM warning field');
        $this->assertFalse((bool) $approved->fresh()->has_warning);
        $this->assertDatabaseHas('production_bom_approvals', ['bom_id' => $pending->id, 'action' => 'Returned to Draft']);
        $this->assertDatabaseHas('production_bom_approvals', ['bom_id' => $approved->id, 'action' => 'Inactivated']);

        // live advisory on the draft BOM, still there after the BOM warning refresh
        $draft->fresh()->refreshWarningStatus();
        $advisory = $draft->fresh()->routing_advisory;
        $this->assertNotEmpty($advisory);
        $this->assertSame('not_active', $advisory[0]['code']);
        $this->assertStringContainsString('is cancelled', $advisory[0]['message']);
        $this->web()->get(route('production.boms.show', $draft->id))->assertOk()->assertSee("can't be approved until the routing is active");
    }

    // ── RT-IMPL-04 ──────────────────────────────────────────────────────────

    public function test_daily_job_activates_scheduled_and_expires_ended_routings(): void
    {
        $scheduled = $this->draftRouting('Sched');
        $scheduled->update(['status' => Routing::STATUS_SCHEDULED, 'effective_from' => now()->toDateString()]);
        $expired = $this->activate($this->draftRouting('Old'));
        $expired->update(['effective_to' => now()->subDay()->toDateString()]);
        $bom = $this->bom($expired, 'approved', 'B-OLD');

        $this->artisan('production:process-routing-dates')->assertSuccessful();

        $this->assertSame(Routing::STATUS_ACTIVE, $scheduled->fresh()->status);
        $this->assertSame(Routing::STATUS_HISTORICAL, $expired->fresh()->status);
        $this->assertSame('inactive', $bom->fresh()->status);
        $this->assertDatabaseHas('production_routing_approvals', ['routing_id' => $scheduled->id, 'action' => 'Activated']);
        $this->assertDatabaseHas('production_routing_approvals', ['routing_id' => $expired->id, 'action' => 'Retired']);
    }

    // ── RT-IMPL-08 ──────────────────────────────────────────────────────────

    public function test_alternative_rules(): void
    {
        $primary = $this->operation();
        $alt1 = $this->operation(['sequence' => 20, 'name' => 'Alt 1', 'is_alternative' => 1, 'replaces_sequence' => 10, 'priority' => 1, 'machine_id' => $this->machine2->id]);

        // alternative of an alternative
        $altOfAlt = $this->operation(['sequence' => 30, 'name' => 'Alt of alt', 'is_alternative' => 1, 'replaces_sequence' => 20, 'priority' => 1]);
        $this->web()->post(route('production.routing.store'), ['name' => 'A1', 'operations' => [$primary, $alt1, $altOfAlt]])
            ->assertSessionHasErrors();
        $this->assertStringContainsString('not another alternative', collect(session('errors')->all())->implode(' '));

        // two alternatives of the same primary with the same priority
        $alt2 = $this->operation(['sequence' => 30, 'name' => 'Alt 2', 'is_alternative' => 1, 'replaces_sequence' => 10, 'priority' => 1]);
        $this->web()->post(route('production.routing.store'), ['name' => 'A2', 'operations' => [$primary, $alt1, $alt2]])
            ->assertSessionHasErrors();
        $this->assertStringContainsString('needs its own priority', collect(session('errors')->all())->implode(' '));

        // replacing an operation of another routing
        $other = $this->draftRouting('Other');
        $foreign = $this->operation(['sequence' => 20, 'name' => 'Foreign alt', 'is_alternative' => 1, 'replaces_operation_id' => $other->operations()->first()->id, 'priority' => 1]);
        $this->web()->post(route('production.routing.store'), ['name' => 'A3', 'operations' => [$primary, $foreign]])
            ->assertSessionHasErrors();
        $this->assertStringContainsString('operation of this routing', collect(session('errors')->all())->implode(' '));

        // valid
        $alt2ok = $this->operation(['sequence' => 30, 'name' => 'Alt 2', 'is_alternative' => 1, 'replaces_sequence' => 10, 'priority' => 2]);
        $this->web()->post(route('production.routing.store'), ['name' => 'A4', 'operations' => [$primary, $alt1, $alt2ok]])->assertSessionHasNoErrors();
        $r = Routing::where('name', 'A4')->first();
        $p = $r->operations()->where('sequence', 10)->first();
        $this->assertSame(2, $r->operations()->where('replaces_operation_id', $p->id)->count());
    }

    // ── RT-IMPL-09 ──────────────────────────────────────────────────────────

    public function test_save_updates_in_place_soft_deletes_removed_and_bom_is_blocked_until_fixed(): void
    {
        $routing = $this->draftRouting();
        [$op10, $op20] = $routing->operations()->orderBy('sequence')->get()->all();
        $bom = $this->bom($routing, 'draft', 'B-ALLOC');
        $alloc = $this->allocate($bom, $op20);

        // form keeps op 10 (id sent, sequence changed), removes op 20, adds a new op
        $keep = array_merge($this->operation(['sequence' => 20, 'name' => 'Press renamed']), ['id' => $op10->id]);
        $new = $this->operation(['sequence' => 30, 'name' => 'New op', 'machine_id' => $this->machine2->id]);
        $this->web()->put(route('production.routing.update', $routing->id), ['name' => 'Route', 'version' => $routing->version, 'effective_from' => now()->toDateString(), 'operations' => [$keep, $new]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn($w) => str_contains($w, 'B-ALLOC'));

        $this->assertSame('Press renamed', RoutingOperation::find($op10->id)->name, 'kept operation updated in place (same id)');
        $this->assertSame(20, (int) RoutingOperation::find($op10->id)->sequence);
        $this->assertSoftDeleted('production_routing_operations', ['id' => $op20->id]);
        $this->assertNotNull(RoutingOperationMaterial::find($alloc->id), 'BOM allocation kept');
        $this->assertSame(2, $routing->operations()->count());

        // BOM side: advisory + submit blocked + edit shows the Deleted card
        $this->assertCount(1, $bom->fresh()->deleted_operation_advisories);
        $this->web()->get(route('production.boms.show', $bom->id))->assertOk()->assertSee('was removed from routing');
        $this->web()->get(route('production.boms.edit', $bom->id))->assertOk()->assertSee('"is_deleted":true', false);
        try {
            app(ProductionBomService::class)->submitApproval($bom->id);
            $this->fail('submit must be blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Move or remove its materials', $e->getMessage());
        }
        $alloc->update(['routing_operation_id' => $op10->id]);
        $this->assertSame([], $bom->fresh()->deleted_operation_advisories);
    }

    // ── RT-IMPL-18 ──────────────────────────────────────────────────────────

    public function test_inactive_resources_refused_advised_and_kept_in_draft_edit(): void
    {
        $routing = $this->draftRouting();
        $active = $this->activate($this->draftRouting('Approved One'));

        $this->machine2->update(['status' => Machine::STATUS_INACTIVE]);

        // save with an inactive machine refused (web)
        $this->web()->post(route('production.routing.store'), ['name' => 'X', 'operations' => [$this->operation(['machine_id' => $this->machine2->id])]])
            ->assertSessionHasErrors();
        $this->assertStringContainsString('Machine MC-R2 is inactive', collect(session('errors')->all())->implode(' '));

        // approved routing shows the advisory; list shows the badge
        $this->web()->get(route('production.routing.show', $active->id))->assertOk()
            ->assertSee('uses machine MC-R2, which is currently inactive')
            ->assertSee('create a new routing version to update');
        $this->web()->get(route('production.routing.index'))->assertOk()->assertSee(__('production.inactive_resources'));

        // draft edit keeps the inactive machine, tagged
        $this->web()->get(route('production.routing.edit', $routing->id))->assertOk()
            ->assertViewHas('keptResources', fn($k) => collect($k)->pluck('machine.label')->contains('Press 2 (MC-R2) — Inactive'));
    }

    // ── RT-IMPL-17 ──────────────────────────────────────────────────────────

    public function test_api_output_saved_values_one_key_per_value(): void
    {
        $routing = $this->draftRouting();
        $headers = ['Accept' => 'application/json', 'X-Tenant' => 'rr', 'X-API-SECRET' => $this->secret, 'Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken];
        $data = $this->withHeaders($headers)->getJson("/api/v1/production/routings/{$routing->id}")->assertOk()->json('data');

        $op = $data['operations'][0];
        foreach (['operation_sequence', 'operation_name', 'run_time_minutes', 'queue_time_minutes', 'cost_per_hour'] as $gone) {
            $this->assertArrayNotHasKey($gone, $op);
        }
        $this->assertArrayNotHasKey('product', $data);
        foreach (['labor_cost_rate_per_minute', 'machine_cost_rate_per_minute', 'labor_cost_per_unit', 'machine_cost_per_unit', 'setup_cost_per_run', 'expected_yield_percentage', 'section_snapshot_number', 'costs_calculated_at', 'is_external'] as $key) {
            $this->assertArrayHasKey($key, $op);
        }
        $saved = $routing->operations()->orderBy('sequence')->first();
        $this->assertEqualsWithDelta((float) $saved->cost_per_unit, $op['cost_per_unit'], 0.0001);
        $this->assertEqualsWithDelta((float) $routing->operations()->where('is_alternative', false)->sum('cost_per_unit'), $data['totals']['cost_per_unit'], 0.0001);
        $this->assertFalse($data['costs_outdated']);
    }

    // ── RT-IMPL-16 ──────────────────────────────────────────────────────────

    private function importCsv(array $rows): array
    {
        $head = 'routing_code,routing_name,version,operation_sequence,operation_name,operation_code,operation_type,work_center_code,section_code,machine_code,manual_batch_name,setup_time_minutes,setup_time_per_piece_minutes,yield_percentage,is_alternative,replaces_operation_number,priority,is_external,vendor_code,subcontract_cost_per_unit';
        $csv = $head . "\n" . implode("\n", array_map(fn($r) => implode(',', $r), $rows)) . "\n";
        $file = UploadedFile::fake()->createWithContent('routings.csv', $csv);

        return app(ProductionMasterImportService::class)->parseAndValidate('routings', $file, $this->tenant->id);
    }

    public function test_import_uses_same_rules_calculates_figures_and_updates_drafts_safely(): void
    {
        // section required + neither resource
        $bad = $this->importCsv([
            ['RT-I1', 'Imp', '1.0.0', '10', 'Cut', 'OP-010', 'manufacturing', 'WC-RR', '', 'MC-R1', '', '10', '', '100', 'No', '', '', 'No', '', ''],
            ['RT-I2', 'Imp2', '1.0.0', '10', 'Cut', 'OP-010', 'manufacturing', 'WC-RR', 'SEC-RR', '', '', '10', '', '100', 'No', '', '', 'No', '', ''],
        ]);
        $errors = collect($bad['preview_rows'])->pluck('errors')->flatten()->implode(' ');
        $this->assertStringContainsString('Section Code is required', $errors);
        $this->assertStringContainsString('must specify a Machine or a Manual Batch', $errors);

        // valid → figures worked out by the backend from the snapshot
        $preview = $this->importCsv([
            ['RT-I3', 'Imp3', '1.0.0', '10', 'Cut', 'OP-010', 'manufacturing', 'WC-RR', 'SEC-RR', 'MC-R1', '', '20', '0.5', '80', 'No', '', '', 'No', '', ''],
            ['RT-I3', '', '', '20', 'Pack', 'OP-020', 'manufacturing', 'WC-RR', 'SEC-RR', 'MC-R2', '', '5', '', '100', 'No', '', '', 'No', '', ''],
        ]);
        $this->assertSame(1, $preview['valid_count'], json_encode($preview['preview_rows']));
        $svc = app(ProductionMasterImportService::class);
        $svc->commit('routings', collect($preview['preview_rows'])->where('valid', true)->values()->all(), 'update', $this->tenant->id, $this->user->id);
        $routing = Routing::where('routing_number', 'RT-I3')->firstOrFail();
        $op10 = $routing->operations()->where('operation_number', 'OP-010')->first();
        $expected = app(RoutingOperationCostCalculator::class)->calculate(
            ['machine_id' => $this->machine->id, 'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80],
            app(SectionCalculationService::class)->getSnapshotResourceCosts($this->section->fresh())
        )['figures'];
        $this->assertEqualsWithDelta($expected['cost_per_unit'], (float) $op10->cost_per_unit, 0.0001);
        $this->assertNotNull($op10->section_snapshot_number);
        $this->assertEqualsWithDelta(1.0, (float) $op10->processing_time_minutes, 0.0001, 'run time from capacity (60 u/h)');

        // draft update keeps BOM allocations and operation ids (matched by operation number)
        $bom = $this->bom($routing, 'draft', 'B-IMP');
        $alloc = $this->allocate($bom, $op10);
        $upd = $this->importCsv([
            ['RT-I3', 'Imp3 v2', '1.0.0', '10', 'Cut again', 'OP-010', 'manufacturing', 'WC-RR', 'SEC-RR', 'MC-R1', '', '25', '0.5', '80', 'No', '', '', 'No', '', ''],
        ]);
        $svc->commit('routings', collect($upd['preview_rows'])->where('valid', true)->values()->all(), 'update', $this->tenant->id, $this->user->id);
        $this->assertSame('Cut again', RoutingOperation::find($op10->id)->name);
        $this->assertSame(10, (int) RoutingOperation::find($op10->id)->sequence, 'unchanged sequence is written back (not left at −id)');
        $this->assertNotNull(RoutingOperationMaterial::find($alloc->id), 'BOM allocations never deleted by import');
        $this->assertSoftDeleted('production_routing_operations', ['operation_number' => 'OP-020', 'routing_id' => $routing->id]);

        // approved routing can't be updated by import
        $this->activate($routing->fresh());
        $locked = $this->importCsv([
            ['RT-I3', 'Imp3 v3', '1.0.0', '10', 'Cut', 'OP-010', 'manufacturing', 'WC-RR', 'SEC-RR', 'MC-R1', '', '25', '', '80', 'No', '', '', 'No', '', ''],
        ]);
        $this->assertStringContainsString('Only draft routings can be updated by import', collect($locked['preview_rows'])->pluck('errors')->flatten()->implode(' '));
    }
}
