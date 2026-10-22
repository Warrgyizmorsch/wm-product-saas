<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\InternalWorkerAllocation;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\InternalWorkerAllocationService;
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
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Testing Bay',
            'code'           => 'SEC-TST',
            'status'         => 'active',
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

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
     * Requirement 3: Internal Worker Allocation at Section-Shift level with temporary rate 100.
     */
    public function test_can_allocate_internal_workers_with_temporary_rate_100(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Assembly Line 1',
            'code'           => 'SEC-AL1',
            'active'         => true,
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

        $response = $this->asTenantA()->post(route('production.internal-worker-allocations.store'), [
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
            'headcount'  => 4,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $allocation = InternalWorkerAllocation::where('section_id', $section->id)
            ->where('shift_id', $this->shift1->id)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertEquals(4, $allocation->headcount);
        $this->assertEquals(100.00, (float) $allocation->rate, 'Internal rate must be 100.00');
        $this->assertTrue($allocation->active);
    }

    public function test_can_update_internal_worker_headcount(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Assembly Line 1',
            'code'           => 'SEC-AL1',
            'active'         => true,
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

        $allocation = InternalWorkerAllocation::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
            'headcount'  => 3,
            'rate'       => 100.00,
            'active'     => true,
        ]);

        $response = $this->asTenantA()->patch(
            route('production.internal-worker-allocations.headcount', $allocation->id),
            ['headcount' => 6]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(6, $allocation->fresh()->headcount);
    }

    public function test_can_toggle_and_destroy_internal_worker_allocation(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Assembly Line 1',
            'code'           => 'SEC-AL1',
            'active'         => true,
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

        $allocation = InternalWorkerAllocation::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
            'headcount'  => 2,
            'rate'       => 100.00,
            'active'     => true,
        ]);

        // Toggle to inactive
        $this->asTenantA()->patch(
            route('production.internal-worker-allocations.toggle-status', $allocation->id),
            ['active' => 0]
        );
        $this->assertFalse($allocation->fresh()->active);

        // Toggle back to active
        $this->asTenantA()->patch(
            route('production.internal-worker-allocations.toggle-status', $allocation->id),
            ['active' => 1]
        );
        $this->assertTrue($allocation->fresh()->active);

        // Destroy (soft-delete)
        $this->asTenantA()->delete(
            route('production.internal-worker-allocations.destroy', $allocation->id)
        );
        $this->assertSoftDeleted('production_internal_worker_allocations', ['id' => $allocation->id]);
    }

    public function test_internal_worker_allocation_enforces_tenant_isolation_and_shift_assignment(): void
    {
        $sectionA = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Assembly Line 1',
            'code'           => 'SEC-AL1',
            'active'         => true,
        ]);
        // Shift is NOT assigned to sectionA yet
        $service = app(InternalWorkerAllocationService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not assigned to Section');

        $service->allocate($sectionA->id, $this->shift1->id, 3, $this->tenantA->id);
    }

    /**
     * Requirement 3 (External workers): Preserved and works alongside internal workers.
     */
    public function test_can_allocate_external_workers_alongside_internal_workers(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Dual Staff Section',
            'code'           => 'SEC-DUAL',
            'active'         => true,
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

        // Allocate internal
        $this->asTenantA()->post(route('production.internal-worker-allocations.store'), [
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
            'headcount'  => 5,
        ]);

        // Allocate external
        $this->asTenantA()->post(route('production.external-worker-allocations.store'), [
            'section_id'   => $section->id,
            'shift_id'     => $this->shift1->id,
            'pay_grade_id' => $this->payGrade->id,
            'headcount'    => 2,
        ]);

        $this->assertDatabaseHas('production_internal_worker_allocations', [
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
            'headcount'  => 5,
            'rate'       => 100.00,
        ]);

        $this->assertDatabaseHas('production_external_worker_allocations', [
            'section_id'   => $section->id,
            'shift_id'     => $this->shift1->id,
            'pay_grade_id' => $this->payGrade->id,
            'headcount'    => 2,
        ]);
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id'       => $this->tenantA->id,
            'employee_id'     => 'EMP-' . uniqid(),
            'full_name'       => 'Test Employee',
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
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Welding Section',
            'code'           => 'SEC-WELD',
            'status'         => 'active',
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
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Milling Section',
            'code'           => 'SEC-MILL',
            'status'         => 'active',
        ]);
        $section2 = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Lathe Section',
            'code'           => 'SEC-LATHE',
            'status'         => 'active',
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
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Show Section',
            'code'           => 'SEC-SHOW',
            'active'         => true,
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id, 'active' => true]]);

        $response = $this->asTenantA()->get(route('production.work-centers.show', $this->workCenterA1->id));

        $response->assertOk();
        $response->assertSee('Assign Internal Employees');
        $response->assertSee('Assign Workers');
        $response->assertSee('workCenterEmployeesModal');
        $response->assertSee('sectionShiftWorkersModal_' . $section->id . '_' . $this->shift1->id);
        $response->assertSee('₹100.00 / hr');
    }
}
