<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Support\CalculationText;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Work Center part C — display and warnings:
 * WC-IMPL-09 (zero-output warning), 16 (hero pending), 17 (footers), 18 (shifts maths),
 * 12 (overhead / day), 04 (texts built at display), 05 (energy wording), 19 (keys & wording).
 */
class WorkCenterDisplayPartCTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private WorkCenter $wc;
    private ProductionShift $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'PC', 'slug' => 'pc', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'pc@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->day = ProductionShift::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '15:00:00', 'break_minutes' => 60, 'active' => true,
        ]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-PC', 'status' => 'active', 'overhead_cost' => 777]);
    }

    private function section(string $code, array $shiftIds = null): WorkCenterSection
    {
        return WorkCenterSection::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'name' => $code, 'code' => $code, 'status' => 'active', 'assigned_shifts' => $shiftIds ?? [$this->day->id],
        ]);
    }

    private function worker(WorkCenterSection $sec, string $code): WorkCenterEmployeeAssignment
    {
        $emp = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $code, 'full_name' => 'Person ' . $code,
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000,
            'shift_id' => $this->day->id, 'status' => true,
        ]);

        return WorkCenterEmployeeAssignment::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'section_id' => $sec->id, 'employee_id' => $emp->id, 'active' => true,
        ]);
    }

    private function machine(WorkCenterSection $sec, string $code, string $status): Machine
    {
        return Machine::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $sec->id,
            'name' => 'Machine ' . $code, 'code' => $code, 'machine_type' => 'lathe', 'status' => $status,
            'capacity' => 10, 'operating_cost_per_hour' => 50,
        ]);
    }

    private function page()
    {
        return $this->withHeaders(['X-Tenant' => 'pc'])->get(route('production.work-centers.show', $this->wc->id))->assertOk();
    }

    private function recalcAll(): void
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    // ── WC-IMPL-09 ──────────────────────────────────────────────────────────

    public function test_zero_output_warning_counts_active_machines_only_and_explains(): void
    {
        $sec = $this->section('S-1');
        $this->worker($sec, 'E1');
        $this->machine($sec, 'M-MAINT', Machine::STATUS_UNDER_MAINTENANCE);

        $st = $sec->fresh()->outputLaborStatus(1000.0);
        $this->assertTrue($st['zero_output'], 'machine under maintenance must not hide the warning');
        $this->assertSame(0, $st['active_machine_count']);
        $this->assertStringContainsString('1 machine assigned but none is active (1 under maintenance)', $st['reason']);
        $this->assertStringContainsString('1 worker is actively assigned', $st['reason']);
        $this->assertStringContainsString(format_currency(1000.0) . '/day', $st['reason']);

        $this->page()->assertSee('Cost with 0 Output', false)
            ->assertSee('1 machine assigned but none is active (1 under maintenance)', false);

        // no machines at all
        $sec2 = $this->section('S-2');
        $this->worker($sec2, 'E2');
        $this->assertStringStartsWith('No machines assigned and no active manual batches.', $sec2->fresh()->outputLaborStatus()['reason']);

        // an active machine clears the warning
        $this->machine($sec, 'M-OK', Machine::STATUS_ACTIVE);
        $this->assertFalse($sec->fresh()->outputLaborStatus()['zero_output']);
    }

    // ── WC-IMPL-16 ──────────────────────────────────────────────────────────

    public function test_hero_panel_shows_pending_without_snapshot_and_no_live_overhead(): void
    {
        $this->section('S-1');
        $html = $this->page()->getContent();

        $this->assertNull(WorkCenterCalculation::where('work_center_id', $this->wc->id)->first());
        // capacity/hr, daily capacity, operating window, labor, machinery, overhead, total = 7 pending markers
        $this->assertGreaterThanOrEqual(7, substr_count($html, 'wc-calc-pending'));
        $this->assertStringNotContainsString('Effective: ', $html);

        // after a calculation the figures come from the snapshot (overhead 777 / day)
        $this->recalcAll();
        $html = $this->page()->getContent();
        $this->assertSame(0, substr_count($html, 'wc-calc-pending'));
        $this->assertStringContainsString(format_currency(777.0), $html);
    }

    // ── WC-IMPL-17 ──────────────────────────────────────────────────────────

    public function test_footers_count_active_items_and_name_the_rest(): void
    {
        $sec = $this->section('S-1');
        $as = $this->worker($sec, 'E1');
        $this->machine($sec, 'M-ON', Machine::STATUS_ACTIVE);
        $this->machine($sec, 'M-OFF', Machine::STATUS_INACTIVE);
        $live = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $sec->id, 'shift_id' => $this->day->id, 'batch_name' => 'LiveBatch', 'capacity_per_hour' => 5, 'active' => true]);
        $off = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $sec->id, 'shift_id' => $this->day->id, 'batch_name' => 'OffBatch', 'capacity_per_hour' => 5, 'active' => false]);
        $off->employees()->attach($as->id);

        $this->recalcAll();
        $html = $this->page()->getContent();

        // Section Details modal: machines footer = active only (10 u/h), non-active named; status column
        $this->assertStringContainsString('Not counted:', $html);
        $this->assertStringContainsString('M-OFF (inactive)', $html);
        $this->assertStringContainsString('(1 active)', $html);
        // batches footer: +1 worker in inactive batch named
        $this->assertStringContainsString('+1 worker in inactive batch: OffBatch', $html);

        // Section calculation modal (snapshot): not-counted lists
        $snap = WorkCenterSectionCalculation::where('section_id', $sec->id)->latest('id')->first();
        $this->withHeaders(['X-Tenant' => 'pc'])
            ->get(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $sec->id, $snap->id]))
            ->assertOk()
            ->assertSee('snapshot-not-counted-machines', false)
            ->assertSee('M-OFF', false)
            ->assertSee('snapshot-not-counted-batches', false)
            ->assertSee('OffBatch', false);
    }

    // ── WC-IMPL-18 ──────────────────────────────────────────────────────────

    public function test_shifts_footer_uses_union_of_active_shifts(): void
    {
        $late = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Late', 'code' => 'L', 'start_time' => '12:00:00', 'end_time' => '20:00:00', 'break_minutes' => 30, 'active' => true]);
        $night = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'OldNight', 'code' => 'ON', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_minutes' => 0, 'active' => false]);
        $this->section('S-1', [$this->day->id, $late->id, $night->id]);

        $html = $this->page()->getContent();
        // 06:00–15:00 ∪ 12:00–20:00 = 14 h, 3 h overlap; inactive night shift excluded
        $this->assertStringContainsString(format_hours(14.0), $html);
        $this->assertStringContainsString('union of active shifts', $html);
        $this->assertStringContainsString(format_hours(3.0) . ' overlap', $html);
        $this->assertStringContainsString('(2 active, 1 inactive)', $html);
        // KPI card: no fallback without a snapshot
        $this->assertStringNotContainsString('Uncalculated Fallback', $html);
    }

    // ── WC-IMPL-12 / 05 ─────────────────────────────────────────────────────

    public function test_overhead_form_label_per_day_and_generic_energy_wording(): void
    {
        $this->withHeaders(['X-Tenant' => 'pc'])->get(route('production.work-centers.create'))->assertOk()
            ->assertSee(' / day)', false)
            ->assertSee(__('production.overhead_cost_help'), false);

        $this->section('S-1');
        $this->recalcAll();
        $html = $this->page()->getContent();
        $this->assertStringNotContainsString('Power (kW)', $html);
        $this->assertStringNotContainsString('Electricity Cost per kWh', $html);
        $this->assertStringContainsString('Consumption / hr', $html);
    }

    // ── WC-IMPL-04 / 19 ─────────────────────────────────────────────────────

    public function test_snapshots_store_numbers_not_currency_text_and_one_key_per_value(): void
    {
        $sec = $this->section('S-1');
        $this->worker($sec, 'E1');
        $this->machine($sec, 'M-1', Machine::STATUS_ACTIVE);
        $direct = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'D1', 'full_name' => 'Direct One',
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 648000, 'shift_id' => $this->day->id, 'status' => true,
        ]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => null, 'employee_id' => $direct->id, 'active' => true]);
        $this->recalcAll();

        $secSnap = WorkCenterSectionCalculation::where('section_id', $sec->id)->latest('id')->first();
        $json = json_encode($secSnap->calculation_details);
        $this->assertStringNotContainsString('$', $json, 'no currency symbol stored in the section snapshot');

        $machine = $secSnap->calculation_details['machinery']['machines'][0];
        $this->assertSame('flat', $machine['explanation_type']);
        $this->assertArrayNotHasKey('cost_explanation', $machine);
        $this->assertStringContainsString(format_currency(50.0, 4) . '/hr', CalculationText::machineExplanation($machine));

        $emp = $secSnap->calculation_details['internal_labor']['employees'][0];
        foreach (['id', 'employee_name', 'hrms_net_monthly_salary', 'shift_duration_hours', 'hourly_salary_rate', 'formula'] as $gone) {
            $this->assertArrayNotHasKey($gone, $emp, "personnel key {$gone} removed");
        }
        $this->assertStringContainsString('Monthly CTC (' . format_currency(30000.0, 4) . ')', CalculationText::employeeFormula($emp));

        $wcSnap = WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first();
        $this->assertStringNotContainsString('$', json_encode($wcSnap->calculation_details));
        $ds = $wcSnap->calculation_details['direct_work_center_staff']['employees'][0];
        foreach (['hrms_net_monthly_salary', 'monthly_ctc', 'total_active_work_centers', 'daily_salary', 'formula'] as $gone) {
            $this->assertArrayNotHasKey($gone, $ds, "direct staff key {$gone} removed");
        }
        $this->assertEqualsWithDelta(54000.0, $ds['monthly_salary'], 0.0001);

        // old snapshots keep their stored text
        $this->assertSame('OLD TEXT', CalculationText::employeeFormula(['formula' => 'OLD TEXT']));
        $this->assertSame('OLD M', CalculationText::machineExplanation(['cost_explanation' => 'OLD M']));

        // wording
        $this->page()->assertSee('Target Capacity:', false)->assertDontSee('pcs / hr', false);
    }
}
