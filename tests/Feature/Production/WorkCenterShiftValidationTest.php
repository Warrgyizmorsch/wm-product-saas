<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\WorkCenterEmployeeService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WorkCenterShiftValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private ProductionShift $morningShift;
    private ProductionShift $nightShift;
    private ProductionShift $shortShift;
    private ProductionShift $dayShiftWithoutOt;
    private ProductionShift $dayShiftWithOt;
    private ProductionShift $longDayShift;
    private WorkCenter $workCenter;
    private WorkCenterEmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Test Manufacturing Corp',
            'slug'   => 'test-mfg-corp',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin User',
            'email'     => 'admin@testmfg.test',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->service = app(WorkCenterEmployeeService::class);

        // 1. Plant Morning Shift: 06:00 - 14:30 (OT: false)
        $this->morningShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Plant Morning Shift',
            'code'             => 'SH-MORN',
            'start_time'       => '06:00:00',
            'end_time'         => '14:30:00',
            'break_minutes'    => 30,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        // 2. Short / Midnight Shift: 01:00 - 01:10 (OT: false)
        $this->shortShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Short Midnight Shift',
            'code'             => 'SH-SHORT',
            'start_time'       => '01:00:00',
            'end_time'         => '01:10:00',
            'break_minutes'    => 0,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        // 3. Regular Day Shift without OT: 09:00 - 17:30 (OT: false)
        $this->dayShiftWithoutOt = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Day Shift No OT',
            'code'             => 'SH-DAY-NO-OT',
            'start_time'       => '09:00:00',
            'end_time'         => '17:30:00',
            'break_minutes'    => 45,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        // 4. Regular Day Shift with OT: 09:00 - 17:30 (OT: true)
        $this->dayShiftWithOt = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Day Shift With OT',
            'code'             => 'SH-DAY-OT',
            'start_time'       => '09:00:00',
            'end_time'         => '17:30:00',
            'break_minutes'    => 45,
            'overtime_allowed' => true,
            'active'           => true,
        ]);

        // 5. Extended Day Shift: 09:00 - 18:30 (OT: true)
        $this->longDayShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Long Day Shift',
            'code'             => 'SH-LONG-DAY',
            'start_time'       => '09:00:00',
            'end_time'         => '18:30:00',
            'break_minutes'    => 45,
            'overtime_allowed' => true,
            'active'           => true,
        ]);

        // 6. Night Shift: 22:00 - 06:00 (overnight)
        $this->nightShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Night Shift',
            'code'             => 'SH-NIGHT',
            'start_time'       => '22:00:00',
            'end_time'         => '06:00:00',
            'break_minutes'    => 30,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'CNC Machining Center',
            'code'      => 'WC-CNC',
            'status'    => 'active',
        ]);
    }

    private function createEmployee(?int $shiftId = null, array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'employee_id'     => 'EMP-' . uniqid(),
            'full_name'       => 'Test Employee ' . uniqid(),
            'shift_id'        => $shiftId,
            'status'          => true,
            'job_title'       => 'Machinist',
            'gender'          => 'Other',
            'date_of_joining' => '2025-01-01',
        ], $overrides));
    }

    private function asTenant()
    {
        return $this->actingAs($this->admin)->withHeader('X-Tenant', $this->tenant->slug);
    }

    public function test_employee_without_predefined_master_shift_cannot_be_assigned(): void
    {
        $employee = $this->createEmployee(null); // No shift

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Milling Section',
            'code'            => 'SEC-MILL',
            'status'          => 'active',
            'assigned_shifts' => [$this->morningShift->id],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("has no active predefined master shift in HRMS");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            $section->id
        );
    }

    public function test_employee_cannot_be_assigned_at_work_center_level_if_no_active_sections(): void
    {
        // Work center has 0 sections
        $employee = $this->createEmployee($this->morningShift->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Work Center has no active sections. Shifts are derived from active sections");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            null // Work Center level
        );
    }

    public function test_employee_cannot_be_assigned_at_work_center_level_if_sections_have_no_shifts(): void
    {
        // Work center has an active section, but no shifts assigned
        WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Empty Shifts Section',
            'code'            => 'SEC-EMPTY',
            'status'          => 'active',
            'assigned_shifts' => [],
        ]);

        $employee = $this->createEmployee($this->morningShift->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("None of the active sections in Work Center '{$this->workCenter->name}' have operating shifts assigned");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            null
        );
    }

    public function test_employee_cannot_be_assigned_to_section_with_no_assigned_shifts(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'No Shift Section',
            'code'            => 'SEC-NOSHIFT',
            'status'          => 'active',
            'assigned_shifts' => [],
        ]);

        $employee = $this->createEmployee($this->morningShift->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Section '{$section->name}' has no active operating shifts assigned");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            $section->id
        );
    }

    public function test_vikramaditya_scenario_zero_shift_overlap_is_blocked(): void
    {
        // Employee has Morning Shift: 06:00 - 14:30 (OT: false)
        $employee = $this->createEmployee($this->morningShift->id, ['full_name' => 'Vikramaditya Shinde']);

        // Section has Short Shift: 01:00 - 01:10
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'tertert',
            'code'            => 'TR',
            'status'          => 'active',
            'assigned_shifts' => [$this->shortShift->id],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Zero operational overlap between employee shift");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            $section->id
        );
    }

    public function test_employee_with_exact_shift_match_succeeds(): void
    {
        $employee = $this->createEmployee($this->morningShift->id, ['full_name' => 'Matching Employee']);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Morning Line',
            'code'            => 'SEC-MORN',
            'status'          => 'active',
            'assigned_shifts' => [$this->morningShift->id],
        ]);

        $assignment = $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            $section->id
        );

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->active);
        $this->assertEquals($section->id, $assignment->section_id);
    }

    public function test_section_level_partial_allocation_is_restricted_when_section_closes_early(): void
    {
        // Section has short operational hours: 01:00 - 01:10
        // Or say 09:00 - 12:00
        $halfDayShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Morning Half Shift',
            'code'             => 'SH-HALF',
            'start_time'       => '09:00:00',
            'end_time'         => '12:00:00',
            'break_minutes'    => 0,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Part Time Section',
            'code'            => 'SEC-PART',
            'status'          => 'active',
            'assigned_shifts' => [$halfDayShift->id],
        ]);

        // Employee works full day: 09:00 - 17:30
        $employeeFullDay = $this->createEmployee($this->dayShiftWithoutOt->id, ['full_name' => 'Full Day Worker']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Partial allocation restricted at Section level");

        $this->service->assign(
            $this->workCenter->id,
            $employeeFullDay->id,
            $this->tenant->id,
            true,
            $section->id
        );
    }

    public function test_employee_shift_lying_completely_inside_section_combined_shifts_succeeds(): void
    {
        // Section has two consecutive shifts: 06:00 - 14:30 and 14:30 - 22:00 (Total 06:00 - 22:00)
        $afternoonShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Afternoon Shift',
            'code'             => 'SH-AFT',
            'start_time'       => '14:30:00',
            'end_time'         => '22:00:00',
            'break_minutes'    => 30,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Two Shift Section',
            'code'            => 'SEC-2S',
            'status'          => 'active',
            'assigned_shifts' => [$this->morningShift->id, $afternoonShift->id],
        ]);

        // Employee has a mid-day shift: 09:00 - 17:30.
        // Lies completely inside the section's active schedule (06:00 to 22:00)
        $midDayEmployee = $this->createEmployee($this->dayShiftWithoutOt->id, ['full_name' => 'Mid Day Worker']);

        $assignment = $this->service->assign(
            $this->workCenter->id,
            $midDayEmployee->id,
            $this->tenant->id,
            true,
            $section->id
        );

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->active);
    }

    public function test_work_center_level_partial_allocation_is_allowed(): void
    {
        // Section has half day shift: 09:00 - 12:00
        $halfDayShift = ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => 'Morning Half Shift',
            'code'             => 'SH-HALF-2',
            'start_time'       => '09:00:00',
            'end_time'         => '12:00:00',
            'break_minutes'    => 0,
            'overtime_allowed' => false,
            'active'           => true,
        ]);

        WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Half Day Section',
            'code'            => 'SEC-HD',
            'status'          => 'active',
            'assigned_shifts' => [$halfDayShift->id],
        ]);

        // Employee works full day: 09:00 - 17:30
        // Partial allocation is ALLOWED at Work Center level because overlap (09:00 - 12:00) > 0
        $employeeFullDay = $this->createEmployee($this->dayShiftWithoutOt->id, ['full_name' => 'WC Supervisor']);

        $assignment = $this->service->assign(
            $this->workCenter->id,
            $employeeFullDay->id,
            $this->tenant->id,
            true,
            null // Work Center level
        );

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->active);
        $this->assertNull($assignment->section_id);
    }

    public function test_work_center_level_zero_overlap_is_blocked(): void
    {
        // Work center only has Short Shift (01:00 - 01:10)
        WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'Short Section',
            'code'            => 'SEC-SH',
            'status'          => 'active',
            'assigned_shifts' => [$this->shortShift->id],
        ]);

        // Employee with Morning Shift (06:00 - 14:30) -> Zero overlap with 01:00 - 01:10
        $employee = $this->createEmployee($this->morningShift->id, ['full_name' => 'Zero Overlap WC Worker']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Zero operational overlap between employee shift");

        $this->service->assign(
            $this->workCenter->id,
            $employee->id,
            $this->tenant->id,
            true,
            null
        );
    }

    public function test_cross_work_center_assignment_exclusivity(): void
    {
        // Work Center A
        $wcA = $this->workCenter;
        WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $wcA->id,
            'name'            => 'Section in WC A',
            'code'            => 'SEC-WCA',
            'status'          => 'active',
            'assigned_shifts' => [$this->morningShift->id],
        ]);

        // Work Center B
        $wcB = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Assembly Work Center',
            'code'      => 'WC-ASSY',
            'status'    => 'active',
        ]);
        $secB = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $wcB->id,
            'name'            => 'Section BA',
            'code'            => 'SEC-BA',
            'status'          => 'active',
            'assigned_shifts' => [$this->morningShift->id],
        ]);

        // Employee Tarun is assigned to Work Center A at Work Center level
        $tarun = $this->createEmployee($this->morningShift->id, ['full_name' => 'Tarun']);
        $this->service->assign($wcA->id, $tarun->id, $this->tenant->id, true, null);

        // Attempting to assign Tarun to Section BA of Work Center B MUST be rejected by mutual exclusivity
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("is actively assigned to Work Center '{$wcA->name}'. Mutual exclusivity prevents holding both Work Center-level and Section-level assignments.");

        $this->service->assign($wcB->id, $tarun->id, $this->tenant->id, true, $secB->id);
    }

    public function test_controller_store_catches_shift_mismatch_and_returns_error_session(): void
    {
        $employee = $this->createEmployee($this->morningShift->id, ['full_name' => 'Vikramaditya Shinde']);
        $section = WorkCenterSection::create([
            'tenant_id'       => $this->tenant->id,
            'work_center_id'  => $this->workCenter->id,
            'name'            => 'tertert',
            'code'            => 'TR',
            'status'          => 'active',
            'assigned_shifts' => [$this->shortShift->id],
        ]);

        $response = $this->asTenant()->post(route('production.work-center-employees.store'), [
            'work_center_id' => $this->workCenter->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Shift Incompatible', session('error'));

        $this->assertDatabaseMissing('production_work_center_employees', [
            'work_center_id' => $this->workCenter->id,
            'section_id'     => $section->id,
            'employee_id'    => $employee->id,
        ]);
    }
}
