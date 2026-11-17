<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\RoutingCostService;
use App\Domains\Production\Services\SectionCalculationService;
use App\Exports\RoutingExport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routing figures — RT-IMPL-01 (dropped columns), 10 (no rounding / 1-unit notice), 11 (final machine cost),
 * 12 (operator labor per machine), 13 (two setup times), 15 (clear setups with run time).
 */
class RoutingFiguresTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private WorkCenter $wc;
    private ProductionShift $morning;
    private ProductionShift $evening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'RF', 'slug' => 'rf', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'rf@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->morning = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Morning', 'code' => 'M', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->evening = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Evening', 'code' => 'E', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-RF', 'status' => 'active']);
    }

    private function section(array $shiftIds): WorkCenterSection
    {
        return WorkCenterSection::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'name' => 'S-1', 'code' => 'S-1', 'status' => 'active', 'assigned_shifts' => $shiftIds,
        ]);
    }

    private function machine(WorkCenterSection $sec, array $attrs = []): Machine
    {
        return Machine::create(array_merge([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $sec->id,
            'name' => 'Press', 'code' => 'MC-1', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60,
        ], $attrs));
    }

    // ── RT-IMPL-11 ──────────────────────────────────────────────────────────

    public function test_routing_uses_final_machine_cost_when_entered_cost_is_blank(): void
    {
        $sec = $this->section([$this->morning->id]);
        $m = $this->machine($sec, ['operating_cost_per_hour' => 0, 'consumption_per_hour' => 2, 'energy_rate_per_unit' => 5]);

        $this->assertSame(10.0, $m->finalOperatingCostPerHour());
        $this->assertSame('energy', $m->operatingCostBreakdown()['explanation_type']);

        $res = app(SectionCalculationService::class)->getSectionResourceCosts($sec->fresh());
        $this->assertEqualsWithDelta(10.0, $res['machines'][0]['operating_cost_per_hour'], 0.0001);
        // 10/hr ÷ 60 u/h
        $this->assertEqualsWithDelta(round(10 / 60, 4), $res['machines'][0]['machine_cost_per_unit'], 0.0001);

        // entered cost wins when > 0
        $m->update(['operating_cost_per_hour' => 12]);
        $this->assertSame(12.0, $m->fresh()->finalOperatingCostPerHour());
    }

    // ── RT-IMPL-12 ──────────────────────────────────────────────────────────

    public function test_operator_labor_per_machine_is_daily_labor_over_section_hours(): void
    {
        $sec = $this->section([$this->morning->id, $this->evening->id]); // 16 h union
        $this->machine($sec);

        $emp = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'E1', 'full_name' => 'Operator',
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, // daily 1,000
            'shift_id' => $this->morning->id, 'status' => true,
        ]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $sec->id, 'employee_id' => $emp->id, 'active' => true]);
        $pg = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Helper', 'code' => 'PG-H', 'hourly_rate' => 50, 'active' => true]);
        ExternalWorkerAllocation::create(['tenant_id' => $this->tenant->id, 'section_id' => $sec->id, 'shift_id' => $this->evening->id, 'pay_grade_id' => $pg->id, 'number_of_external_workers' => 2, 'active' => true]); // 2 × 50 × 8 = 800/day

        $res = app(SectionCalculationService::class)->getSectionResourceCosts($sec->fresh());

        $this->assertEqualsWithDelta(1800.0, $res['non_batch_daily_labor_cost'], 0.0001);
        $this->assertEqualsWithDelta(16.0, $res['section_operating_hours'], 0.0001);
        // (1,000 + 800) ÷ 16 h ÷ 1 machine = 112.5/hr (old: 1,000/8 + 100 = 225/hr)
        $this->assertEqualsWithDelta(112.5, $res['operator_labor_cost_per_machine_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(112.5, $res['machines'][0]['operator_labor_cost_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(1800.0, $res['machines'][0]['non_batch_daily_labor_cost'], 0.0001);
        $this->assertSame(1, $res['machines'][0]['active_machines_count']);
    }

    // ── RT-IMPL-13 / 10 ─────────────────────────────────────────────────────

    public function test_two_setup_times_and_cost_formulas_without_rounding(): void
    {
        $sec = $this->section([$this->morning->id]);
        $m = $this->machine($sec);
        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RTG-RF-1', 'name' => 'R', 'version' => '1.0.0', 'status' => Routing::STATUS_DRAFT, 'effective_from' => now()->toDateString()]);
        $op = RoutingOperation::create([
            'tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 10, 'operation_number' => 'OP-010',
            'name' => 'Press', 'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $sec->id, 'machine_id' => $m->id,
            'setup_time_minutes' => 30, 'setup_time_per_piece_minutes' => 0.5, 'processing_time_minutes' => 0.0167, 'wait_time_minutes' => 0,
            'labor_cost_rate' => 1, 'machine_cost_rate' => 1, 'expected_yield_percentage' => 80,
        ]);

        // RT-IMPL-10: 4 decimals survive the database (was decimal(10,2) → 0.02)
        $this->assertEqualsWithDelta(0.0167, (float) $op->fresh()->processing_time_minutes, 0.000001);

        // Cost / Unit = (0.5 + 0.0167) × (1 + 1) × (100 / 80) — the same formula the form calculator / backend check use
        $expected = (0.5 + 0.0167) * 2 * 1.25;
        $calc = app(\App\Domains\Production\Services\RoutingOperationCostCalculator::class)->calculate(
            ['machine_id' => $m->id, 'setup_time_minutes' => 30, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80],
            ['machines' => [['id' => $m->id, 'speed_minutes_per_unit' => 0.0167, 'operating_cost_per_hour' => 60, 'operator_labor_cost_per_hour' => 60]]]
        );
        $this->assertEqualsWithDelta(round($expected, 4), $calc['figures']['cost_per_unit'], 0.0001);
        $this->assertSame(60.0, $calc['figures']['setup_cost_per_run']);
        // RT-IMPL-14: readers use the saved figures
        $op->update(['cost_per_unit' => $calc['figures']['cost_per_unit'], 'labor_cost_per_unit' => $calc['figures']['labor_cost_per_unit'], 'machine_cost_per_unit' => $calc['figures']['machine_cost_per_unit'], 'setup_cost_per_run' => $calc['figures']['setup_cost_per_run']]);
        $this->assertEqualsWithDelta(round($expected, 4), $op->fresh()->estimatedCostPerUnit(), 0.0001);
        // Setup cost per run = 30 × 2 (no yield)
        $this->assertSame(60.0, $op->fresh()->setupCostPerRun());

        $summary = app(RoutingCostService::class)->calculateRoutingCost($routing->id);
        $this->assertEqualsWithDelta(round($expected, 4), $summary['cost_per_unit'], 0.0001);
        $this->assertEqualsWithDelta(60.0, $summary['setup_cost_per_run'], 0.0001);

        // show page: notice + both setup columns + setup cost per run
        $this->withHeaders(['X-Tenant' => 'rf'])->get(route('production.routing.show', $routing->id))->assertOk()
            ->assertSee(__('production.routing_one_unit_notice'))
            ->assertSee('Setup Cost per Run:', false)
            ->assertSee('0.0167 min', false);

        // durations under one second
        $this->assertSame('< 1s', format_duration(0.001));
        $this->assertSame('0s', format_duration(0));
    }

    public function test_routing_form_has_both_setups_and_clears_them_with_run_time(): void
    {
        $html = $this->withHeaders(['X-Tenant' => 'rf'])->get(route('production.routing.create'))->assertOk()->getContent();
        $this->assertStringContainsString('[setup_time_per_piece_minutes]', $html);
        $this->assertStringContainsString('calculateSetupCostPerRun(operation)', $html);
        $this->assertStringContainsString('clearResourceDerivedTimes(operation)', $html);
        $this->assertStringContainsString(__('production.routing_one_unit_notice'), $html);
        $this->assertStringNotContainsString('toFixed(2)', $html);
    }

    // ── RT-IMPL-01 ──────────────────────────────────────────────────────────

    public function test_dropped_product_columns_are_not_used_any_more(): void
    {
        $this->assertFalse(property_exists(RoutingDTO::class, 'product_id'));
        $this->assertFalse(property_exists(RoutingDTO::class, 'bom_id'));
        $this->assertArrayNotHasKey('product_code', RoutingExport::availableColumns());
        $this->assertArrayHasKey('section_code', RoutingExport::availableColumns());
        $this->assertFalse(method_exists(Routing::class, 'scopeForProduct'));

        $sec = $this->section([$this->morning->id]);
        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RTG-RF-2', 'name' => 'Export Me', 'version' => '1.0.0', 'status' => Routing::STATUS_DRAFT, 'effective_from' => now()->toDateString()]);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Cut', 'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $sec->id, 'setup_time_minutes' => 5, 'processing_time_minutes' => 1]);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Pack', 'operation_type' => 'manufacturing', 'work_center_id' => $this->wc->id, 'section_id' => $sec->id, 'setup_time_minutes' => 0, 'processing_time_minutes' => 1]);

        $export = new RoutingExport($this->tenant->id);
        $rows = $export->collection();
        $this->assertCount(2, $rows, 'one row per operation');
        $mapped = $export->map($rows[0]);
        $headings = $export->headings();
        $this->assertSame('Export Me', $mapped[array_search('Routing Name', $headings, true)]);
        $this->assertSame('S-1', $mapped[array_search('Section Code', $headings, true)]);

        $this->withHeaders(['X-Tenant' => 'rf'])->get(route('production.routing.create'))->assertOk();
        $this->withHeaders(['X-Tenant' => 'rf'])->get(route('production.routing.edit', $routing->id))->assertOk();
    }
}
