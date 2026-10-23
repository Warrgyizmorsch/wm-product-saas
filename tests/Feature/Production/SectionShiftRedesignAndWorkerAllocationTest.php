<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\WorkCenterEmployeeService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SectionShiftRedesignAndWorkerAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private WorkCenter $workCenterA1;
    private WorkCenter $workCenterA2;
    private WorkCenter $workCenterB;
    private ProductionShift $shift1;
    private ProductionShift $shift2;
    private ExternalWorkerPayGrade $payGrade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A Corp',
            'slug'   => 'tenant-a-corp',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B Corp',
            'slug'   => 'tenant-b-corp',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Admin A',
            'email'     => 'admina@redesign.test',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Admin B',
            'email'     => 'adminb@redesign.test',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        // Shifts for Tenant A
        $this->shift1 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Morning Shift',
            'code'          => 'SH-01',
            'start_time'    => '06:00',
            'end_time'      => '14:00',
            'break_minutes' => 30,
            'hourly_rate'   => 150.00,
            'active'        => true,
        ]);

        $this->shift2 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Evening Shift',
            'code'          => 'SH-02',
            'start_time'    => '14:00',
            'end_time'      => '22:00',
            'break_minutes' => 30,
            'hourly_rate'   => 175.00,
            'active'        => true,
        ]);

        // Pay Grade for Tenant A
        $this->payGrade = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantA->id,
            'name'        => 'Assembler Level 1',
            'code'        => 'PG-ASM-1',
            'hourly_rate' => 120.00,
            'active'      => true,
        ]);

        // Work Centers for Tenant A
        $this->workCenterA1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Assembly Dept',
            'code'      => 'WC-ASM',
            'status'    => 'active',
        ]);

        $this->workCenterA2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Packaging Dept',
            'code'      => 'WC-PKG',
            'status'    => 'active',
        ]);

        // Baseline active sections with operating shifts so Work Centers have derived shifts
        WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Assembly Baseline Section',
            'code'            => 'SEC-BASE-A1',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA2->id,
            'name'            => 'Packaging Baseline Section',
            'code'            => 'SEC-BASE-A2',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Work Center for Tenant B
        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Tenant B WC',
            'code'      => 'WC-TB',
            'status'    => 'active',
        ]);
    }

    private function asTenantA()
    {
        return $this->actingAs($this->adminA)->withHeader('X-Tenant', $this->tenantA->slug);
    }

    private function asTenantB()
    {
        return $this->actingAs($this->adminB)->withHeader('X-Tenant', $this->tenantB->slug);
    }

    /**
     * Requirement 1 & 2: Section-Shift assignment without target_headcount.
     */
    public function test_target_headcount_column_is_dropped_from_production_section_shifts(): void
    {
        $columns = Schema::getColumnListing('production_section_shifts');
        $this->assertNotContains('target_headcount', $columns, 'target_headcount should no longer exist in production_section_shifts');
    }

    public function test_can_create_section_with_assigned_shifts_using_simple_shifts_array(): void
    {
        $response = $this->asTenantA()->post(route('production.work-center-sections.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Sub-Assembly Line',
            'code'           => 'SEC-SUB',
            'status'         => 'active',
            'shifts'         => [$this->shift1->id, $this->shift2->id],
        ]);

        $response->assertRedirect();
        $section = WorkCenterSection::where('code', 'SEC-SUB')->first();
        $this->assertNotNull($section);
        $this->assertCount(2, $section->shifts);
        $this->assertTrue($section->shifts->contains('id', $this->shift1->id));
        $this->assertTrue($section->shifts->contains('id', $this->shift2->id));
    }

    public function test_can_update_section_shifts_without_target_headcount(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Testing Bay',
            'code'            => 'SEC-TST',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $response = $this->asTenantA()->put(route('production.work-center-sections.update', $section->id), [
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Testing Bay Updated',
            'code'           => 'SEC-TST',
            'status'         => 'active',
            'shifts'         => [$this->shift2->id],
        ]);

        $response->assertRedirect();
        $section->refresh();
        $this->assertCount(1, $section->shifts);
        $this->assertTrue($section->shifts->contains('id', $this->shift2->id));
        $this->assertFalse($section->shifts->contains('id', $this->shift1->id));
    }

    /**
     * Requirement 3: Section-level Internal Employee Assignment via production.work-center-employees.store
     */
    public function test_can_assign_internal_employee_to_section(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Section Dedicated Worker']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Assembly Line 1',
            'code'            => 'SEC-AL1',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $assignment = WorkCenterEmployeeAssignment::where('work_center_id', $this->workCenterA1->id)
            ->where('section_id', $section->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->active);
        $this->assertEquals($section->id, $assignment->section_id);
    }

    public function test_can_toggle_and_destroy_section_internal_employee_assignment(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Toggle Candidate']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Assembly Line 1',
            'code'            => 'SEC-AL1',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);
        $assignment = $service->assign(
            $this->workCenterA1->id,
            $employee->id,
            $this->tenantA->id,
            true,
            $section->id
        );

        // Toggle to inactive
        $this->asTenantA()->patch(
            route('production.work-center-employees.toggle-status', $assignment->id),
            ['active' => 0]
        );
        $this->assertFalse($assignment->fresh()->active);

        // Toggle back to active
        $this->asTenantA()->patch(
            route('production.work-center-employees.toggle-status', $assignment->id),
            ['active' => 1]
        );
        $this->assertTrue($assignment->fresh()->active);

        // Destroy (soft-delete)
        $this->asTenantA()->delete(
            route('production.work-center-employees.destroy', $assignment->id)
        );
        $this->assertSoftDeleted('production_work_center_employees', ['id' => $assignment->id]);
    }

    public function test_section_internal_employee_assignment_validates_section_ownership(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Mismatch Worker']);
        $sectionB = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA2->id,
            'name'            => 'Other Section',
            'code'            => 'SEC-OTH',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Attempt to assign employee to workCenterA1 while claiming section belongs to it (it belongs to workCenterA2)
        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $sectionB->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertSessionHas('error');
    }

    /**
     * Requirement 3 (External workers): Preserved and allocated per Section + Shift.
     */
    public function test_can_allocate_external_workers_to_section_shift(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Contract Staff Section',
            'code'            => 'SEC-CONTR',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Allocate external workers
        $this->asTenantA()->post(route('production.external-worker-allocations.store'), [
            'section_id'                 => $section->id,
            'shift_id'                   => $this->shift1->id,
            'pay_grade_id'               => $this->payGrade->id,
            'number_of_external_workers' => 4,
        ]);

        $this->assertDatabaseHas('production_external_worker_allocations', [
            'section_id'                 => $section->id,
            'shift_id'                   => $this->shift1->id,
            'pay_grade_id'               => $this->payGrade->id,
            'number_of_external_workers' => 4,
            'active'                     => 1,
        ]);
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id'       => $this->tenantA->id,
            'employee_id'     => 'EMP-' . uniqid(),
            'full_name'       => 'Test Employee',
            'shift_id'        => $this->shift1->id,
            'status'          => true,
            'job_title'       => 'Operator',
            'gender'          => 'Other',
            'date_of_joining' => '2025-01-01',
        ], $overrides));
    }

    /**
     * Requirement 4: Work Center-level Internal Employee Assignment and Exclusivity.
     */
    public function test_can_assign_internal_employee_to_work_center(): void
    {
        $employee = $this->createEmployee(['full_name' => 'John Operator']);

        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_work_center_employees', [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
            'active'         => 1,
        ]);
    }

    public function test_employee_can_be_assigned_to_multiple_work_centers(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Sarah Technician']);

        // Assign to Work Center A1
        $response1 = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
        ]);
        $response1->assertRedirect();
        $response1->assertSessionHas('success');

        // Assigning same active employee to Work Center A2 must SUCCEED (multi-work center allowed)
        $response2 = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA2->id,
            'employee_id'    => $employee->id,
        ]);
        $response2->assertRedirect();
        $response2->assertSessionHas('success');

        $this->assertDatabaseHas('production_work_center_employees', [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
            'active'         => 1,
        ]);
        $this->assertDatabaseHas('production_work_center_employees', [
            'work_center_id' => $this->workCenterA2->id,
            'employee_id'    => $employee->id,
            'active'         => 1,
        ]);
    }

    public function test_employee_cannot_be_assigned_to_same_work_center_twice(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Duplicate Candidate']);

        // First assignment
        $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
        ]);

        // Attempt duplicate assignment to same work center must fail
        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_work_center_and_section_assignments_are_mutually_exclusive(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Cross Assignment Specialist']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Welding Section',
            'code'            => 'SEC-WELD',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);

        // 1. Assign to Work Center
        $wcAssignment = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id);
        $this->assertTrue($wcAssignment->active);

        // 2. Attempting to assign to Section must fail due to mutual exclusivity
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mutual exclusivity prevents holding both Work Center-level and Section-level assignments.');

        $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $section->id);
    }

    public function test_section_assignment_prevents_other_section_and_work_center_assignments(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Section Dedicated Worker']);
        $section1 = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Milling Section',
            'code'            => 'SEC-MILL',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);
        $section2 = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Lathe Section',
            'code'            => 'SEC-LATHE',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);

        // 1. Assign to Section 1
        $secAssignment = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $section1->id);
        $this->assertEquals($section1->id, $secAssignment->section_id);

        // 2. Attempting to assign to Section 2 must fail (single section exclusivity)
        try {
            $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $section2->id);
            $this->fail('Expected exception for multi-section assignment');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('An employee assigned to a Section cannot be assigned to any other Section', $e->getMessage());
        }

        // 3. Attempting to assign to Work Center must fail (mutual exclusivity)
        try {
            $service->assign($this->workCenterA2->id, $employee->id, $this->tenantA->id, true);
            $this->fail('Expected exception for mutual exclusivity with active section');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Mutual exclusivity prevents holding both Work Center-level and Section-level assignments', $e->getMessage());
        }
    }

    public function test_can_toggle_status_and_destroy_work_center_employee(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Alice Engineer']);

        $service = app(WorkCenterEmployeeService::class);
        $assignment = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id);

        // Deactivate
        $this->asTenantA()->patch(
            route('production.work-center-employees.toggle-status', $assignment->id),
            ['active' => 0]
        );
        $this->assertFalse($assignment->fresh()->active);

        // Remove (soft-delete)
        $this->asTenantA()->delete(
            route('production.work-center-employees.destroy', $assignment->id)
        );
        $this->assertSoftDeleted('production_work_center_employees', ['id' => $assignment->id]);
    }

    /**
     * Requirement 4: Detail page renders Assign Internal Employees and Assign Workers correctly.
     */
    public function test_work_center_show_page_renders_redesign_elements(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Show Tester']);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Show Section',
            'code'            => 'SEC-SHOW',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $response = $this->asTenantA()->get(route('production.work-centers.show', $this->workCenterA1->id));

        $response->assertOk();
        $response->assertSee('Assign Internal Employees');
        $response->assertSee('workCenterEmployeesModal');
        $response->assertSee('sectionEmployeesModal_' . $section->id);
        $response->assertSee('sectionShiftWorkersModal_' . $section->id . '_' . $this->shift1->id);
    }

    public function test_section_assigned_employee_is_not_displayed_in_work_center_level_assigned_employees(): void
    {
        $secEmployee = $this->createEmployee(['full_name' => 'Section Dedicated Worker']);
        $wcEmployee = $this->createEmployee(['full_name' => 'WorkCenter Floating Worker']);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Machining Bay A',
            'code'            => 'SEC-MBA',
            'active'          => true,
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Assign secEmployee to Section
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'employee_id'    => $secEmployee->id,
            'active'         => true,
        ]);

        // Assign wcEmployee to Work Center
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => null,
            'employee_id'    => $wcEmployee->id,
            'active'         => true,
        ]);

        // 1. WorkCenter model relationship checks
        $this->workCenterA1->refresh();
        $wcAssignedEmpIds = $this->workCenterA1->employeeAssignments->pluck('employee_id')->all();
        $this->assertContains($wcEmployee->id, $wcAssignedEmpIds);
        $this->assertNotContains($secEmployee->id, $wcAssignedEmpIds);

        // Section model relationship checks
        $section->refresh();
        $secAssignedEmpIds = $section->employeeAssignments->pluck('employee_id')->all();
        $this->assertContains($secEmployee->id, $secAssignedEmpIds);
        $this->assertNotContains($wcEmployee->id, $secAssignedEmpIds);

        // 2. HTTP View checks
        $response = $this->asTenantA()->get(route('production.work-centers.show', $this->workCenterA1->id));
        $response->assertOk();

        $content = $response->getContent();
        // Inside workCenterEmployeesModal, wcEmployee is listed as assigned
        $this->assertStringContainsString('WorkCenter Floating Worker', $content);
        // In the WC dropdown, secEmployee is shown as (Assigned to Section: Machining Bay A)
        $this->assertStringContainsString('(Assigned to Section: Machining Bay A)', $content);
    }

    /**
     * Requirement: Manual Production decoupled modal & worker prerequisite checks.
     */
    public function test_section_created_without_manual_production_by_default(): void
    {
        $response = $this->asTenantA()->post(route('production.work-center-sections.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Manual Check Section',
            'code'           => 'SEC-MAN-01',
            'status'         => 'active',
            'shifts'         => [$this->shift1->id],
        ]);

        $response->assertRedirect();
        $section = WorkCenterSection::where('code', 'SEC-MAN-01')->first();
        $this->assertNotNull($section);
        $this->assertFalse($section->supports_manual_production);
        $this->assertNull($section->manual_capacity_per_hour);
        $this->assertFalse($section->hasActiveWorkerAssignments());
    }

    public function test_cannot_enable_manual_production_without_active_section_worker_assignments(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Gated Section',
            'code'            => 'SEC-GATE',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $this->assertFalse($section->hasActiveWorkerAssignments());

        $response = $this->asTenantA()->patch(
            route('production.work-center-sections.update-manual-production', $section->id),
            [
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 20.0,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $section->refresh();
        $this->assertFalse($section->supports_manual_production);
        $this->assertNull($section->manual_capacity_per_hour);
    }

    public function test_work_center_level_worker_assignment_does_not_qualify_for_section_manual_production(): void
    {
        $employee = $this->createEmployee(['full_name' => 'WC Only Worker']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Unassigned Section',
            'code'            => 'SEC-UNASGN',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Assign employee to work center level (section_id is null)
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => null,
            'employee_id'    => $employee->id,
            'active'         => true,
        ]);

        // Section has no section-level workers
        $this->assertFalse($section->hasActiveWorkerAssignments());

        $response = $this->asTenantA()->patch(
            route('production.work-center-sections.update-manual-production', $section->id),
            [
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 15.0,
            ]
        );

        $response->assertSessionHas('error');
        $this->assertFalse($section->fresh()->supports_manual_production);
    }

    public function test_can_enable_manual_production_with_active_internal_employee(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Section Dedicated Worker 2']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Manual Ready Section',
            'code'            => 'SEC-MR1',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Assign to section
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
            'active'         => true,
        ]);

        $this->assertTrue($section->hasActiveWorkerAssignments());
        $counts = $section->getActiveWorkerCounts();
        $this->assertEquals(1, $counts['internal']);
        $this->assertEquals(0, $counts['external']);
        $this->assertEquals(1, $counts['total']);

        // Enable manual production
        $response = $this->asTenantA()->patch(
            route('production.work-center-sections.update-manual-production', $section->id),
            [
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 35.5,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $section->refresh();
        $this->assertTrue($section->supports_manual_production);
        $this->assertEquals(35.5, (float) $section->manual_capacity_per_hour);

        // Can disable manual production
        $disableResponse = $this->asTenantA()->patch(
            route('production.work-center-sections.update-manual-production', $section->id),
            [
                'supports_manual_production' => 0,
            ]
        );
        $disableResponse->assertSessionHas('success');
        $this->assertFalse($section->fresh()->supports_manual_production);
    }

    public function test_can_enable_manual_production_with_active_external_worker(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'External Worker Section',
            'code'            => 'SEC-EXT1',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        // Allocate external workers to section
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $this->tenantA->id,
            'section_id'                 => $section->id,
            'shift_id'                   => $this->shift1->id,
            'pay_grade_id'               => $this->payGrade->id,
            'number_of_external_workers' => 3,
            'active'                     => true,
        ]);

        $this->assertTrue($section->hasActiveWorkerAssignments());
        $counts = $section->getActiveWorkerCounts();
        $this->assertEquals(0, $counts['internal']);
        $this->assertEquals(3, $counts['external']);
        $this->assertEquals(3, $counts['total']);

        $response = $this->asTenantA()->patch(
            route('production.work-center-sections.update-manual-production', $section->id),
            [
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 40.0,
            ]
        );

        $response->assertSessionHas('success');
        $section->refresh();
        $this->assertTrue($section->supports_manual_production);
        $this->assertEquals(40.0, (float) $section->manual_capacity_per_hour);
    }

    public function test_show_page_renders_manual_production_modals_and_controls(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Show Page Section',
            'code'            => 'SEC-SPS',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $response = $this->asTenantA()->get(route('production.work-centers.show', $this->workCenterA1->id));
        $response->assertOk();

        $content = $response->getContent();
        // Check for dedicated manual production modal ID
        $this->assertStringContainsString('sectionManualProductionModal_' . $section->id, $content);
        // Check for action dropdown item and pill button
        $this->assertStringContainsString('Manual Production', $content);
        // Check that create section modal does not contain manual production input
        $this->assertStringNotContainsString('id="create_supports_manual_production"', $content);
        // Check that edit section modal does not contain manual production input
        $this->assertStringNotContainsString('id="edit_supports_manual_production_' . $section->id . '"', $content);
    }

    public function test_employee_assigned_to_section_can_be_assigned_to_another_section_after_deactivation(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Transfer Worker']);
        $sectionA = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Section Alpha',
            'code'            => 'SEC-ALPH',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);
        $sectionB = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Section Beta',
            'code'            => 'SEC-BETA',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);

        // 1. Assign to Section A
        $assignmentA = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $sectionA->id);
        $this->assertTrue($assignmentA->active);
        $this->assertEquals($sectionA->id, $assignmentA->section_id);

        // 2. Deactivate in Section A
        $service->setActive($assignmentA->id, false, $this->tenantA->id);
        $this->assertFalse($assignmentA->fresh()->active);

        // 3. Assign to Section B in the SAME Work Center (must not throw SQL duplicate entry 1062)
        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $sectionB->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $assignmentB = WorkCenterEmployeeAssignment::where('work_center_id', $this->workCenterA1->id)
            ->where('section_id', $sectionB->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($assignmentB);
        $this->assertTrue($assignmentB->active);
        // Section A record still preserved as inactive
        $this->assertFalse($assignmentA->fresh()->active);
    }

    public function test_employee_assigned_to_section_can_be_reassigned_to_same_section_when_inactive(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Reactivate Worker']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Section Gamma',
            'code'            => 'SEC-GAM',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);

        // 1. Assign to Section
        $assignment = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $section->id);
        $this->assertTrue($assignment->active);

        // 2. Deactivate
        $service->setActive($assignment->id, false, $this->tenantA->id);
        $this->assertFalse($assignment->fresh()->active);

        // 3. Re-assign to same section via store endpoint instead of toggle
        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($assignment->fresh()->active);
    }

    public function test_soft_deleted_employee_assignment_is_restored_on_reassignment(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Restore Worker']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Section Delta',
            'code'            => 'SEC-DELT',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $service = app(WorkCenterEmployeeService::class);

        // 1. Assign
        $assignment = $service->assign($this->workCenterA1->id, $employee->id, $this->tenantA->id, true, $section->id);

        // 2. Soft-delete
        $this->asTenantA()->delete(route('production.work-center-employees.destroy', $assignment->id));
        $this->assertSoftDeleted('production_work_center_employees', ['id' => $assignment->id]);

        // 3. Re-assign must restore without 1062 duplicate key error
        $response = $this->asTenantA()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $restored = $assignment->fresh();
        $this->assertNull($restored->deleted_at);
        $this->assertTrue($restored->active);
    }

    public function test_show_page_renders_select2_selectors_and_dynamic_quantity_tick_button(): void
    {
        $employee = $this->createEmployee(['full_name' => 'Select2 Worker']);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA1->id,
            'name'            => 'Select2 Section',
            'code'            => 'SEC-SEL2',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift1->id],
        ]);

        $alloc = ExternalWorkerAllocation::create([
            'tenant_id'                  => $this->tenantA->id,
            'section_id'                 => $section->id,
            'shift_id'                   => $this->shift1->id,
            'pay_grade_id'               => $this->payGrade->id,
            'number_of_external_workers' => 5,
            'active'                     => true,
        ]);

        $response = $this->asTenantA()->get(route('production.work-centers.show', $this->workCenterA1->id));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Check Select2 selectors exist
        $this->assertStringContainsString('data-select2-selector="default"', $content);
        $this->assertStringContainsString('id="secEmployeeSelect_' . $section->id . '"', $content);

        // 2. Check external worker quantity input has data-initial-value and tick button is hidden by default
        $this->assertStringContainsString('data-initial-value="5"', $content);
        $this->assertStringContainsString('ext-worker-qty-btn d-none', $content);
    }
}

