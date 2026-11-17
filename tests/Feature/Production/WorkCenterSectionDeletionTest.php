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
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WorkCenterSectionDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private ProductionShift $shift;
    private ExternalWorkerPayGrade $payGrade;
    private WorkCenter $workCenter;
    private WorkCenterSectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Precision Parts Ltd',
            'slug'   => 'precision-parts',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Production Manager',
            'email'     => 'manager@precision.test',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->shift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Regular Morning Shift',
            'code'             => 'SH-REG-01',
            'start_time'       => '08:00:00',
            'end_time'         => '16:30:00',
            'break_minutes'    => 30,
            'overtime_allowed' => true,
            'active'           => true,
        ]);

        $this->payGrade = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'CNC Operator Grade 1',
            'code'        => 'PG-CNC-1',
            'hourly_rate' => 150.00,
            'active'      => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Milling Department',
            'code'      => 'WC-MILL-01',
            'status'    => 'active',
        ]);

        $this->service = app(WorkCenterSectionService::class);
    }

    private function asTenant()
    {
        return $this->actingAs($this->admin)->withHeader('X-Tenant', $this->tenant->slug);
    }

    public function test_clean_section_without_assignments_can_be_deleted(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Rough Milling Bay',
            'code'            => 'SEC-ROUGH',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift->id],
        ]);

        $result = $this->service->delete($section->id);
        $this->assertTrue($result);

        $this->assertSoftDeleted('production_work_center_sections', [
            'id' => $section->id,
        ]);
    }

    public function test_section_with_assignments_unassigns_machines_and_deletes_assignments_on_deletion(): void
    {
        // 1. Create section
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Precision 5-Axis Bay',
            'code'            => 'SEC-5AXIS',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift->id],
        ]);

        // 2. Assign machine
        $machine = Machine::create([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Haas VMC 5-Axis',
            'code'        => 'MAC-HAAS-01',
            'status'      => Machine::STATUS_ACTIVE,
            'section_id'  => $section->id,
        ]);

        // 3. Assign internal employee
        $employee = Employee::create([
            'tenant_id'       => $this->tenant->id,
            'employee_id'     => 'EMP-101',
            'full_name'       => 'Master Machinist',
            'shift_id'        => $this->shift->id,
            'status'          => true,
            'job_title'       => 'Senior Operator',
            'gender'          => 'Other',
            'date_of_joining' => '2025-01-01',
        ]);
        $empAssignment = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
            'active'         => true,
        ]);

        // 4. Assign external worker allocation
        $extAllocation = ExternalWorkerAllocation::create([
            'tenant_id'                  => $this->tenant->id,
            'section_id'                 => $section->id,
            'shift_id'                   => $this->shift->id,
            'pay_grade_id'               => $this->payGrade->id,
            'number_of_external_workers' => 3,
            'active'                     => true,
        ]);

        // 5. Delete Section
        $result = $this->service->delete($section->id);
        $this->assertTrue($result);

        // Verify machine was unassigned (section_id set to null)
        $this->assertDatabaseHas('production_machines', [
            'id'         => $machine->id,
            'section_id' => null,
        ]);

        // Verify internal employee assignment was deleted
        $this->assertSoftDeleted('production_work_center_employees', [
            'id' => $empAssignment->id,
        ]);

        // Verify external worker allocation was deleted
        $this->assertSoftDeleted('production_external_worker_allocations', [
            'id' => $extAllocation->id,
        ]);

        // Verify Section was deleted
        $this->assertSoftDeleted('production_work_center_sections', [
            'id' => $section->id,
        ]);
    }

    public function test_controller_destroy_endpoint_successfully_deletes_section(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Deburring Station',
            'code'            => 'SEC-DEBURR',
            'status'          => 'active',
            'assigned_shifts' => [$this->shift->id],
        ]);

        $response = $this->asTenant()->delete(route('production.work-center-sections.destroy', $section->id));

        $response->assertRedirect(route('production.work-centers.show', $this->workCenter->id));
        $response->assertSessionHas('success', 'Section deleted successfully.');

        $this->assertSoftDeleted('production_work_center_sections', [
            'id' => $section->id,
        ]);
    }
}
