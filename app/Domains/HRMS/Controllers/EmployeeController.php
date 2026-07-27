<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\EmployeeRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->employeeRepository->getDirectoryData($request->all());

        return view('modules.hrms.employees.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $this->validatePayload($request);
        $validated = $this->normalizeHierarchy($validated);

        $this->employeeRepository->storeEmployee($validated, $request);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function show(Request $request, Employee $employee): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->employeeRepository->getProfileData($employee, $request->all());

        return view('modules.hrms.employees.show', $data);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $oldPlanId = $employee->leave_plan_id;

        $validated = $this->validatePayload($request, $employee);
        $validated = $this->normalizeHierarchy($validated);

        $newPlanId = !empty($validated['leave_plan_id']) ? (int)$validated['leave_plan_id'] : null;
        if ($newPlanId !== null && (int)$oldPlanId !== $newPlanId) {
            $hasPending = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPending) {
                return redirect()->route('hrms.leaves.index', ['search' => $employee->full_name])
                    ->with('error', 'Cannot change the leave plan for ' . $employee->full_name . '. Please approve or reject all pending leave requests for this employee first.');
            }

            $hasPendingEncashment = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPendingEncashment) {
                return redirect()->route('hrms.leaves.index', ['search' => $employee->full_name])
                    ->with('error', 'Cannot change the leave plan for ' . $employee->full_name . '. Please approve or reject all pending leave encashment requests for this employee first.');
            }
        }

        $this->employeeRepository->updateEmployee($employee, $validated, $request);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->employeeRepository->deleteEmployee($employee);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    private function validatePayload(Request $request, ?Employee $employee = null): array
    {
        $employeeId = $employee?->id;

        return $request->validate([
            'employee_id' => [
                'required', 'string', 'max:255',
                Rule::unique('employees', 'employee_id')->ignore($employeeId),
            ],
            'title' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => [
                'required', 'email', 'max:255',
                Rule::unique('employees', 'personal_email')->ignore($employeeId),
            ],
            'work_email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'work_email')->ignore($employeeId),
            ],
            'company_id' => ['required', 'exists:companies,id'],
            'business_unit_id' => ['nullable', 'exists:business_units,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'pay_group_id' => ['nullable', 'exists:pay_groups,id'],
            'salary_structure_id' => ['nullable', 'exists:salary_structures,id'],
            'leave_plan_id' => ['nullable', 'exists:leave_plans,id'],
            'attendance_penalty_id' => ['nullable', 'exists:attendance_penalties,id'],
            'shift_id' => ['nullable', 'exists:production_shifts,id'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'employee_stage' => ['nullable', 'string', 'max:100'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:20'],
            'personal_mobile_number' => ['nullable', 'string', 'max:50'],
            'work_mobile_number' => ['nullable', 'string', 'max:50'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function normalizeHierarchy(array $validated): array
    {
        if (!empty($validated['department_id'])) {
            $department = \App\Domains\HRMS\Models\Department::find($validated['department_id']);
            if ($department) {
                if ($department->branch_id) {
                    $validated['branch_id'] = $department->branch_id;
                }
                if ($department->business_unit_id) {
                    $validated['business_unit_id'] = $department->business_unit_id;
                }
            }
        }
        return $validated;
    }
}
