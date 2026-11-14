<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Resources\Api\WorkCenterResource;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\RoutingCostStatusService;
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
 * Short Full Review Point 1 (Op Setup Cost column) and Point 2 (DB = base currency; convert ONCE at the
 * write boundary and ONCE at the UI boundary). INR rate = 85 (config/currency.php).
 */
class ProductionCurrencyBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private Machine $machine;
    private ProductionShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'CB', 'slug' => 'cb', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'cb@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->shift = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-CB', 'status' => 'active', 'overhead_cost' => 80]);
        $this->section = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Press Bay', 'code' => 'SEC-P', 'status' => 'active', 'assigned_shifts' => [$this->shift->id]]);
        // DB base: 60 u/h → 1 min/unit; 30 USD/hr
        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'name' => 'Press', 'code' => 'MC-P', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60, 'operating_cost_per_hour' => 30]);
        // non-batch operator: 1,000 USD/day over 8 h, 1 machine → 125 USD/hr
        $emp = Employee::create(['tenant_id' => $this->tenant->id, 'employee_id' => 'E1', 'full_name' => 'Op', 'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, 'shift_id' => $this->shift->id, 'status' => true]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'employee_id' => $emp->id, 'active' => true]);
    }

    private function recalc(): void
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    private function inr()
    {
        session(['active_currency' => 'INR']);

        return $this->withHeaders(['X-Tenant' => 'cb'])->withSession(['active_currency' => 'INR']);
    }

    private function baseResources(): array
    {
        return app(SectionCalculationService::class)->getSnapshotResourceCosts($this->section->fresh());
    }

    /** What the routing form sends in INR: figures from the converted feed + the snapshot reference. */
    private function inrFormOperation(array $overrides = []): array
    {
        $op = array_merge([
            'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Press', 'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id,
            'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80,
            'is_external' => 0,
        ], $overrides);
        session(['active_currency' => 'INR']);
        $res = RoutingOperationCostCalculator::resourcesToActiveCurrency($this->baseResources());
        $calc = app(RoutingOperationCostCalculator::class)->calculate($op, $res);
        foreach ($calc['figures'] as $k => $v) {
            $op[$k] = number_format($v, 4, '.', '');
        }
        $op['section_calculation_id'] = $res['section_calculation_id'];
        $op['section_snapshot_number'] = $res['snapshot_number'];
        // the form also posts its own snapshot — in INR; the server must NOT keep it
        $op['resource_snapshot'] = json_encode(['type' => 'machine', 'operating_cost_per_hour' => 2550]);

        return $op;
    }

    public function test_section_resources_feed_is_in_the_active_currency_while_the_snapshot_stays_base(): void
    {
        $this->recalc();
        $res = $this->inr()->getJson(route('production.sections.resources', $this->section->id))->assertOk()->json();

        $this->assertSame('INR', $res['currency']);
        $this->assertEqualsWithDelta(30 * 85, $res['machines'][0]['operating_cost_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(125 * 85, $res['operator_labor_cost_per_machine_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $res['machines'][0]['speed_minutes_per_unit'], 0.0001); // not money → unchanged
        $this->assertEqualsWithDelta(30.0, $this->baseResources()['machines'][0]['operating_cost_per_hour'], 0.0001);
    }

    public function test_inr_routing_save_stores_db_base_figures_and_snapshot_and_is_not_outdated(): void
    {
        $this->recalc();
        $op = $this->inrFormOperation();

        $this->inr()->post(route('production.routing.store'), ['name' => 'INR Route', 'operations' => [$op]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $saved = RoutingOperation::where('operation_number', 'OP-010')->firstOrFail();
        $base = app(RoutingOperationCostCalculator::class)->calculate(
            ['machine_id' => $this->machine->id, 'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80],
            $this->baseResources()
        )['figures'];

        foreach (RoutingOperationCostCalculator::FIELDS as $field) {
            $this->assertEqualsWithDelta($base[$field], (float) $saved->{$field}, 0.00001, $field);
        }
        $this->assertEqualsWithDelta(2.0833, (float) $saved->labor_cost_rate, 0.0001); // 125/60 base, not ×85
        $this->assertEqualsWithDelta(30.0, $saved->resource_snapshot['operating_cost_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(125.0, $saved->resource_snapshot['operator_labor_cost_per_machine_per_hour'], 0.0001);

        $status = app(RoutingCostStatusService::class)->check($saved->routing->fresh());
        $this->assertFalse($status['outdated'] ?? false, json_encode($status));

        // machine cost doubles → outdated, message formatted once (₹ 30×85 → 60×85)
        $this->machine->update(['operating_cost_per_hour' => 60]);
        $this->recalc();
        $status = app(RoutingCostStatusService::class)->check($saved->routing->fresh());
        $this->assertTrue($status['outdated']);
        $this->assertStringContainsString('₹2,550', json_encode($status, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('₹5,100', json_encode($status, JSON_UNESCAPED_UNICODE));
    }

    public function test_inr_external_operation_converts_subcontract_cost_once(): void
    {
        $vendor = \App\Domains\Inventory\Models\Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Platers', 'code' => 'V-PL', 'status' => 'active']);
        $op = [
            'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Plating', 'operation_type' => 'outsourcing',
            'is_external' => 1, 'vendor_id' => $vendor->id, 'expected_yield_percentage' => 80, 'subcontract_cost_per_unit' => '170',
            'cost_per_unit' => number_format(170 * 1.25, 4, '.', ''), 'processing_time_minutes' => 0,
        ];
        $this->inr()->post(route('production.routing.store'), ['name' => 'Ext Route', 'operations' => [$op]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $saved = RoutingOperation::where('operation_number', 'OP-010')->firstOrFail();
        $this->assertEqualsWithDelta(2.0, (float) $saved->subcontract_cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(2.5, (float) $saved->cost_per_unit, 0.0001);
        $this->assertEqualsWithDelta(2.0, $saved->resource_snapshot['cost_per_unit'], 0.0001);
    }

    public function test_routing_show_has_op_setup_cost_column_and_converted_simulator(): void
    {
        $this->recalc();
        $this->inr()->post(route('production.routing.store'), ['name' => 'Show Route', 'operations' => [$this->inrFormOperation()]])
            ->assertSessionHasNoErrors();
        $saved = RoutingOperation::where('operation_number', 'OP-010')->firstOrFail();

        // setup per run base = 20 × (2.0833 + 0.5) = 51.666 → ₹4,391.61 (4 decimals rule)
        $html = $this->inr()->get(route('production.routing.show', $saved->routing_id))->assertOk()->getContent();
        $this->assertStringContainsString('Op Setup Cost', $html);
        $this->assertStringContainsString('routing-op-setup-cost', $html);
        $this->assertStringNotContainsString(' / run</small>', $html);
        $this->assertStringContainsString(format_currency($saved->setupCostPerRun(), 4), $html);
        // simulator gets INR numbers (cost per unit base × 85)
        $this->assertStringContainsString((string) round((float) $saved->cost_per_unit * 85, 4), $html);
    }

    public function test_routing_create_form_has_op_setup_cost_column(): void
    {
        $html = $this->inr()->get(route('production.routing.create'))->assertOk()->getContent();
        $this->assertStringContainsString('Op Setup Cost', $html);
        $this->assertStringContainsString('colspan="15"', $html);
    }

    public function test_machine_web_form_converts_once_each_way(): void
    {
        $this->inr()->post(route('production.machines.store'), [
            'section_id' => $this->section->id, 'name' => 'Lathe', 'code' => 'MC-L', 'capacity' => 10,
            'status' => Machine::STATUS_ACTIVE, 'operating_cost_per_hour' => 170, 'energy_rate_per_unit' => 8.5,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $m = Machine::where('code', 'MC-L')->firstOrFail();
        $this->assertEqualsWithDelta(2.0, (float) $m->operating_cost_per_hour, 0.0001);
        $this->assertEqualsWithDelta(0.1, (float) $m->energy_rate_per_unit, 0.0001);

        $html = $this->inr()->get(route('production.machines.edit', $m->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="operating_cost_per_hour"[^>]*value="170"|value="170"[^>]*name="operating_cost_per_hour"/', $html);
    }

    public function test_pay_grade_edit_prefills_active_currency_and_resave_keeps_the_base_rate(): void
    {
        $pg = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Helper', 'code' => 'PG-H', 'hourly_rate' => 1, 'active' => true]);

        $html = $this->inr()->get(route('production.pay-grades.edit', $pg->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="hourly_rate"[^>]*value="85"|value="85"[^>]*name="hourly_rate"/', $html);
        $this->assertStringContainsString('Hourly Rate (₹/hr)', $html);

        // re-save as shown → still 1 USD (was shrinking 85× before)
        $this->inr()->put(route('production.pay-grades.update', $pg->id), ['name' => 'Helper', 'code' => 'PG-H', 'hourly_rate' => '85', 'active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1.0, (float) $pg->fresh()->hourly_rate, 0.0001);
    }

    public function test_pay_grade_rate_change_queues_the_sections_that_use_it(): void
    {
        $pg = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Helper', 'code' => 'PG-H', 'hourly_rate' => 1, 'active' => true]);
        ExternalWorkerAllocation::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'pay_grade_id' => $pg->id, 'number_of_external_workers' => 2, 'active' => true]);
        $this->wc->update(['pending_calculation_section_ids' => [], 'calculation_countdown_end_at' => null]);

        // same rate → nothing queued
        $this->inr()->put(route('production.pay-grades.update', $pg->id), ['name' => 'Helper 2', 'code' => 'PG-H', 'hourly_rate' => '85', 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], $this->wc->fresh()->pending_calculation_section_ids ?? []);

        // new rate → section queued in its Work Center countdown
        $this->inr()->put(route('production.pay-grades.update', $pg->id), ['name' => 'Helper 2', 'code' => 'PG-H', 'hourly_rate' => '170', 'active' => 1])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(2.0, (float) $pg->fresh()->hourly_rate, 0.0001);
        $this->assertContains($this->section->id, array_map('intval', $this->wc->fresh()->pending_calculation_section_ids ?? []));
    }

    public function test_work_center_fallback_rates_and_api_resource_use_the_latest_calculation(): void
    {
        $wc = $this->wc->fresh();
        $this->assertSame(0.0, $wc->calculatedLaborCostPerHour());
        $this->assertSame(0.0, $wc->calculatedOverheadCostPerHour());

        $this->recalc();
        $wc = $this->wc->fresh();
        $calc = $wc->latestCalculation;
        $this->assertNotNull($calc);
        $this->assertEqualsWithDelta((float) $calc->labor_cost_per_hour, $wc->calculatedLaborCostPerHour(), 0.0001);
        $this->assertGreaterThan(0, $wc->calculatedLaborCostPerHour());
        $this->assertEqualsWithDelta(80 / (float) $calc->unique_operating_hours, $wc->calculatedOverheadCostPerHour(), 0.0001);

        $arr = (new WorkCenterResource($wc))->toArray(request());
        $this->assertSame(config('currency.base', 'USD'), $arr['currency']);
        $this->assertEqualsWithDelta(80.0, $arr['overhead_cost'], 0.0001);
        $this->assertEqualsWithDelta((float) $calc->total_cost_per_hour, $arr['cost_per_hour'], 0.0001);
        $this->assertSame($calc->snapshot_number, $arr['latest_calculation']['snapshot_number']);
        $this->assertArrayNotHasKey('efficiency_percentage', $arr);
    }
}
