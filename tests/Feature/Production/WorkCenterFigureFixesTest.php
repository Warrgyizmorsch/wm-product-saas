<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\SectionCalculationService;
use App\Domains\Production\Services\WorkCenterCalculationService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Work Center figure fixes — WC-IMPL-07 (annual CTC), WC-IMPL-01 (batch output, breaks included),
 * WC-IMPL-11 (direct staff hourly rate on clock hours), WC-IMPL-08 (cost per unit "N/A").
 */
class WorkCenterFigureFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private WorkCenter $wc;
    private ProductionShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Fig', 'slug' => 'fig', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'fig@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        // 06:00–15:00 with a 60-minute break → 9 clock hours (8 net)
        $this->shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '15:00:00', 'break_minutes' => 60, 'active' => true,
        ]);

        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-F', 'status' => 'active']);
    }

    private function section(string $code): WorkCenterSection
    {
        return WorkCenterSection::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'name' => $code, 'code' => $code, 'status' => 'active', 'assigned_shifts' => [$this->shift->id],
        ]);
    }

    private function employee(float $annualCtc): Employee
    {
        return Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-' . uniqid(), 'full_name' => 'Worker ' . uniqid(),
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => $annualCtc,
            'shift_id' => $this->shift->id, 'status' => true,
        ]);
    }

    private function assign(Employee $emp, ?WorkCenterSection $section): WorkCenterEmployeeAssignment
    {
        return WorkCenterEmployeeAssignment::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'section_id' => $section?->id, 'employee_id' => $emp->id, 'active' => true,
        ]);
    }

    public function test_monthly_ctc_is_annual_ctc_divided_by_12(): void
    {
        $this->assertSame(30000.0, $this->employee(360000)->monthlyCtc());
    }

    public function test_shift_clock_hours_include_breaks(): void
    {
        $this->assertSame(9.0, $this->shift->clock_hours);
        $overnight = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'N', 'code' => 'N', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_minutes' => 30, 'active' => true]);
        $this->assertSame(8.0, $overnight->clock_hours);
        $full = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'F', 'code' => 'F', 'start_time' => '06:00:00', 'end_time' => '06:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->assertSame(24.0, $full->clock_hours);
    }

    public function test_section_labor_uses_monthly_ctc_and_batch_output_uses_full_shift_hours(): void
    {
        $section = $this->section('S-1');
        $assignment = $this->assign($this->employee(360000), $section); // monthly 30,000 → daily 1,000

        $batch = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id, 'section_id' => $section->id, 'shift_id' => $this->shift->id,
            'batch_name' => 'B1', 'capacity_per_hour' => 20, 'active' => true,
        ]);
        $batch->employees()->attach($assignment->id);

        $calc = app(SectionCalculationService::class)->calculate($section->fresh());

        // WC-IMPL-01: 20 u/h × 9 h (break included) = 180, not 160
        $this->assertEqualsWithDelta(180.0, (float) $calc['daily_manual_capacity'], 0.0001);
        // WC-IMPL-07: daily cost = annual 360,000 ÷ 12 ÷ 30 = 1,000 (was 12,000)
        $this->assertEqualsWithDelta(1000.0, (float) $calc['total_daily_internal_labor_cost'], 0.0001);

        $batchRow = $calc['calculation_details']['manual_batches']['batches'][0];
        $this->assertEqualsWithDelta(9.0, (float) $batchRow['shift_hours'], 0.0001);
        $this->assertEqualsWithDelta(180.0, (float) $batchRow['daily_cap'], 0.0001);
        // batch cost per unit = 1,000 ÷ 180
        $this->assertEqualsWithDelta(round(1000 / 180, 4), (float) $batchRow['cost_per_unit'], 0.0001);
    }

    public function test_direct_staff_hourly_rate_uses_shift_clock_hours(): void
    {
        $this->section('S-1');
        $this->assign($this->employee(648000), null); // monthly 54,000 → daily 1,800

        $result = app(WorkCenterCalculationService::class)->calculate($this->wc->fresh());
        $staff = $result['calculation_details']['direct_work_center_staff']['employees'][0];

        $this->assertEqualsWithDelta(1800.0, (float) $staff['daily_cost'], 0.0001);
        $this->assertEqualsWithDelta(9.0, (float) $staff['shift_duration_hours'], 0.0001);
        // WC-IMPL-11: 1,800 ÷ 9 clock hours = 200/hr (was ÷ 8 net hours = 225)
        $this->assertEqualsWithDelta(200.0, (float) $staff['hourly_rate'], 0.0001);
    }

    public function test_cost_per_unit_is_null_when_output_is_zero_but_cost_is_incurred(): void
    {
        $section = $this->section('S-1');
        $this->assign($this->employee(360000), $section); // labor, but no machine and no batch → output 0

        $calc = app(SectionCalculationService::class)->calculate($section->fresh());
        $this->assertEquals(0.0, (float) $calc['daily_capacity']);
        $this->assertGreaterThan(0, (float) $calc['total_daily_labor_cost']);
        $this->assertNull($calc['calculation_details']['financial_summary']['cost_per_unit']);

        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh());
        $snapshot = WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first();
        $this->assertNotNull($snapshot);
        $this->assertNull($snapshot->cost_per_unit);

        // Pages render the N/A state (show page + section calculation modal data)
        $this->withHeaders(['X-Tenant' => 'fig'])
            ->get(route('production.work-centers.show', $this->wc->id))
            ->assertOk()
            ->assertSee('N/A');
        $this->withHeaders(['X-Tenant' => 'fig'])
            ->get(route('production.work-centers.calculation-details', $this->wc->id) . '?section_id=' . $section->id)
            ->assertOk();

        $this->assertNull(cost_per_unit_value(100, 0));
        $this->assertSame(0.0, cost_per_unit_value(0, 0));
        $this->assertSame(2.5, cost_per_unit_value(10, 4));
        $this->assertStringContainsString('N/A', format_cost_per_unit(null, 500));
    }
}
