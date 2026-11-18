<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
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
 * "Outdated" = a stored original resource value of an operation differs from the same resource in the latest
 * section snapshot. Nothing is recalculated at check time; a recalculation with no real change keeps routings up to date.
 * Operator labor per machine = non-batch daily labor (stored per-worker daily_cost) ÷ section hours ÷ active machines.
 */
class RoutingOutdatedSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ProductionShift $shift;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private Machine $machine;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'OS', 'slug' => 'os', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'os@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->shift = ProductionShift::create(['tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'break_minutes' => 0, 'active' => true]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-OS', 'status' => 'active']);
        $this->section = WorkCenterSection::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'name' => 'Press Bay', 'code' => 'SEC-P', 'status' => 'active', 'assigned_shifts' => [$this->shift->id]]);
        // 60 u/h → 1 min/unit; 30/hr entered cost
        $this->machine = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'name' => 'Press', 'code' => 'MC-P', 'machine_type' => 'press', 'status' => Machine::STATUS_ACTIVE, 'capacity' => 60, 'operating_cost_per_hour' => 30]);
        // non-batch operator: daily 1,000 over 8 h, 1 machine → 125/hr
        $this->employee = $this->employee('E1', 360000);
    }

    private function employee(string $code, float $annual): Employee
    {
        $emp = Employee::create(['tenant_id' => $this->tenant->id, 'employee_id' => $code, 'full_name' => "Op {$code}", 'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => $annual, 'shift_id' => $this->shift->id, 'status' => true]);
        WorkCenterEmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'employee_id' => $emp->id, 'active' => true]);

        return $emp;
    }

    private function recalc(): void
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
    }

    private function resources(): array
    {
        return app(SectionCalculationService::class)->getSnapshotResourceCosts($this->section->fresh());
    }

    private function costStatus(Routing $routing): array
    {
        return app(RoutingCostStatusService::class)->check($routing->fresh());
    }

    /** Saves a routing through the web form with figures from the snapshot, like the real form. */
    private function savedRouting(): Routing
    {
        $this->recalc();
        $op = array_merge([
            'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Press', 'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id, 'section_id' => $this->section->id, 'machine_id' => $this->machine->id,
            'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.5, 'expected_yield_percentage' => 80, 'is_external' => 0,
        ]);
        $res = $this->resources();
        foreach (app(RoutingOperationCostCalculator::class)->calculate($op, $res)['figures'] as $k => $v) {
            $op[$k] = number_format($v, 4, '.', '');
        }
        $op['section_calculation_id'] = $res['section_calculation_id'];
        $op['section_snapshot_number'] = $res['snapshot_number'];

        $this->withHeaders(['X-Tenant' => 'os'])->post(route('production.routing.store'), ['name' => 'Press Route', 'operations' => [$op]])
            ->assertSessionHasNoErrors();

        return Routing::where('name', 'Press Route')->firstOrFail();
    }

    public function test_recalculation_with_no_value_change_keeps_the_routing_up_to_date(): void
    {
        $routing = $this->savedRouting();
        $this->assertFalse($this->costStatus($routing)['outdated']);

        $this->recalc(); // snapshot #2, same values
        $this->assertSame(2, $this->resources()['snapshot_number']);
        $status = $this->costStatus($routing);
        $this->assertFalse($status['outdated'], json_encode($status));
        $this->assertSame([], $status['operations']);
    }

    public function test_changed_machine_cost_and_operator_labor_are_outdated_with_old_and_new_values(): void
    {
        $routing = $this->savedRouting();

        $this->machine->update(['operating_cost_per_hour' => 60]);
        $this->recalc();
        $status = $this->costStatus($routing);
        $this->assertTrue($status['outdated']);
        $text = implode(' ', $status['operations'][0]['messages']);
        $this->assertStringContainsString('Machine MC-P operating cost changed', $text);
        $this->assertStringNotContainsString('operator labor', $text);
        $this->assertStringNotContainsString('run speed', $text);

        // operator labor: 2,000/day → 250/hr
        $this->employee->update(['current_salary' => 720000]);
        $this->recalc();
        $text = implode(' ', $this->costStatus($routing)['operations'][0]['messages']);
        $this->assertStringContainsString('operator labor changed', $text);
    }

    public function test_machine_leaving_the_section_snapshot_is_outdated_and_inactive(): void
    {
        $routing = $this->savedRouting();

        $this->machine->update(['status' => Machine::STATUS_INACTIVE]);
        $this->recalc();
        $status = $this->costStatus($routing);

        $this->assertTrue($status['outdated']);
        $this->assertStringContainsString('Machine MC-P is no longer active in section SEC-P', $status['operations'][0]['messages'][0]);
        $this->assertCount(1, $status['inactive']);
        $this->assertStringContainsString('OP-010 uses machine MC-P, which is no longer active in section SEC-P.', $status['inactive'][0]['message']);
    }

    public function test_external_operation_is_never_outdated(): void
    {
        $this->recalc();
        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RTG-EXT', 'name' => 'Ext', 'version' => '1.0.0', 'status' => Routing::STATUS_DRAFT, 'effective_from' => now()->toDateString()]);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Subcontract', 'operation_type' => 'outsourcing', 'is_external' => true, 'subcontract_cost_per_unit' => 2, 'cost_per_unit' => 2, 'processing_time_minutes' => 0]);

        $this->machine->update(['operating_cost_per_hour' => 999]);
        $this->recalc();

        $status = $this->costStatus($routing);
        $this->assertFalse($status['outdated']);
        $this->assertSame([], $status['operations']);
        $this->assertSame([], $status['inactive']);
    }

    public function test_operator_labor_sums_stored_daily_cost_of_workers_not_in_an_active_batch(): void
    {
        $inActiveBatch = $this->employee('E2', 360000);   // 1,000/day — excluded (active batch)
        $inInactiveBatch = $this->employee('E3', 360000); // 1,000/day — counted (inactive batch)

        $activeBatch = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'batch_name' => 'Active batch', 'capacity_per_hour' => 10, 'active' => true]);
        $inactiveBatch = SectionManualProduction::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'batch_name' => 'Inactive batch', 'capacity_per_hour' => 10, 'active' => false]);
        $activeBatch->employees()->attach(WorkCenterEmployeeAssignment::where('employee_id', $inActiveBatch->id)->value('id'));
        $inactiveBatch->employees()->attach(WorkCenterEmployeeAssignment::where('employee_id', $inInactiveBatch->id)->value('id'));

        $helper = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Helper', 'code' => 'PG-H', 'hourly_rate' => 50, 'active' => true]);
        $welder = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Welder', 'code' => 'PG-W', 'hourly_rate' => 100, 'active' => true]);
        ExternalWorkerAllocation::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'pay_grade_id' => $helper->id, 'number_of_external_workers' => 2, 'active' => true]); // 2 × 50 × 8 = 800 — counted
        ExternalWorkerAllocation::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'pay_grade_id' => $welder->id, 'manual_production_id' => $activeBatch->id, 'number_of_external_workers' => 1, 'active' => true]); // 800 — excluded (active batch)

        // inactive workers never count, batch or not
        $inactiveEmp = $this->employee('E4', 360000);
        $inactiveEmp->update(['status' => false]);
        $inactiveAssignment = $this->employee('E5', 360000);
        WorkCenterEmployeeAssignment::where('employee_id', $inactiveAssignment->id)->update(['active' => false]);
        $gone = ExternalWorkerPayGrade::create(['tenant_id' => $this->tenant->id, 'name' => 'Gone', 'code' => 'PG-G', 'hourly_rate' => 500, 'active' => true]);
        ExternalWorkerAllocation::create(['tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id, 'pay_grade_id' => $gone->id, 'number_of_external_workers' => 3, 'active' => false]);

        $this->recalc();
        $res = $this->resources();

        // E1 1,000 + E3 1,000 (inactive batch) + helpers 800 = 2,800/day ÷ 8 h = 350/h ÷ 1 machine
        $this->assertEqualsWithDelta(2800.0, $res['machines'][0]['non_batch_daily_labor_cost'], 0.0001);
        $this->assertEqualsWithDelta(350.0, $res['operator_labor_cost_per_machine_per_hour'], 0.0001);
        $this->assertEqualsWithDelta(350.0, $res['machines'][0]['operator_labor_cost_per_hour'], 0.0001);
        $this->assertSame(1, $res['active_machines_count']);
    }
}
