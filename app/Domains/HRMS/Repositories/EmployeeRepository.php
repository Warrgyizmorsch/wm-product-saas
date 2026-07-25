<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function getDirectoryData(array $inputs): array
    {
        $filters = [
            'search' => trim((string) ($inputs['search'] ?? '')),
            'company_id' => isset($inputs['company_id']) ? (int) $inputs['company_id'] : null,
            'department_id' => isset($inputs['department_id']) ? (int) $inputs['department_id'] : null,
            'status' => isset($inputs['status']) && $inputs['status'] !== '' ? (string) $inputs['status'] : null,
            'sort' => !empty($inputs['sort']) ? (string) $inputs['sort'] : 'name_asc',
        ];

        $employees = Employee::query()
            ->with(['company', 'businessUnit', 'branch', 'department', 'designation', 'payGroup', 'salaryStructure', 'leavePlan', 'attendancePenalty'])
            ->when($filters['search'], function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%")
                        ->orWhere('personal_mobile_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['company_id'], fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->when($filters['department_id'], fn ($query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['status'] !== null && $filters['status'] !== '', function ($query) use ($filters): void {
                $query->where('status', $filters['status'] === '1');
            })
            ->when($filters['sort'], function ($query, string $sort): void {
                switch ($sort) {
                    case 'id_asc':
                        $query->orderBy('employee_id', 'asc');
                        break;
                    case 'id_desc':
                        $query->orderBy('employee_id', 'desc');
                        break;
                    case 'name_desc':
                        $query->orderBy('full_name', 'desc');
                        break;
                    case 'doj_asc':
                        $query->orderBy('date_of_joining', 'asc');
                        break;
                    case 'doj_desc':
                        $query->orderBy('date_of_joining', 'desc');
                        break;
                    case 'salary_asc':
                        $query->orderBy('current_salary', 'asc');
                        break;
                    case 'salary_desc':
                        $query->orderBy('current_salary', 'desc');
                        break;
                    case 'name_asc':
                    default:
                        $query->orderBy('full_name', 'asc');
                        break;
                }
            })
            ->paginate(10)
            ->withQueryString();

        $options = $this->getDropdownOptions();

        return array_merge([
            'employees' => $employees,
            'filters' => $filters,
        ], $options);
    }

    public function getProfileData(Employee $employee, array $inputs): array
    {
        $employee->load([
            'company', 'businessUnit', 'branch', 'department', 'designation',
            'payGroup', 'salaryStructure', 'leavePlan', 'attendancePenalty',
            'reportingManager', 'shift', 'employmentHistories',
            'assetRequests.requestedAsset',
            'assetRequests.allocatedAsset',
            'assetRequests.allocatedAssets',
        ]);

        $availableAssets = \App\Domains\HRMS\Models\Asset::query()
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        $leaveTypes = [];
        if ($employee->leavePlan) {
            $leaveTypes = \App\Domains\HRMS\Models\LeaveType::query()
                ->where('leave_plan_id', $employee->leave_plan_id)
                ->where('status', true)
                ->orderBy('name')
                ->get();
        }

        $allEmployees = Employee::query()
            ->where('id', '!=', $employee->id)
            ->orderBy('full_name')
            ->get();

        $options = $this->getDropdownOptions();

        return array_merge([
            'employee' => $employee,
            'availableAssets' => $availableAssets,
            'leaveTypes' => $leaveTypes,
            'allEmployees' => $allEmployees,
        ], $options);
    }

    public function storeEmployee(array $validated, Request $request): Employee
    {
        $validated['status'] = $request->input('status', '1') === '1';

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('employees', 'public');
        }

        return Employee::create($validated);
    }

    public function updateEmployee(Employee $employee, array $validated, Request $request): bool
    {
        $validated['status'] = $request->input('status', '1') === '1';

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $validated['photo'] = $request->file('photo')->store('employees', 'public');
        }

        return $employee->update($validated);
    }

    public function deleteEmployee(Employee $employee): bool
    {
        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }
        return $employee->delete();
    }

    public function getDropdownOptions(): array
    {
        return [
            'companies' => Company::query()->where('status', true)->orderBy('company_name')->get(),
            'businessUnits' => BusinessUnit::query()->where('status', true)->orderBy('name')->get(),
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('status', true)->orderBy('name')->get(),
            'designations' => Designation::query()->where('status', true)->orderBy('name')->get(),
            'payGroups' => PayGroup::query()->where('status', true)->orderBy('name')->get(),
            'salaryStructures' => SalaryStructure::query()->where('status', true)->orderBy('name')->get(),
            'leavePlans' => LeavePlan::query()->where('status', true)->orderBy('name')->get(),
            'attendancePenalties' => AttendancePenalty::query()->where('status', true)->orderBy('rule_type')->get(),
            'employmentTypes' => ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern'],
            'employeeStages' => ['probation' => 'Probation', 'confirmed' => 'Confirmed', 'notice' => 'Notice Period', 'exited' => 'Exited'],
            'genders' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'],
            'maritalStatuses' => ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'],
            'bloodGroups' => ['A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-'],
            'dietPreferences' => ['veg' => 'Vegetarian', 'non_veg' => 'Non-Vegetarian', 'vegan' => 'Vegan', 'eggetarian' => 'Eggetarian'],
            'reportingManagers' => Employee::query()->orderBy('full_name')->get(),
            'shifts' => \App\Domains\Production\Models\ProductionShift::query()->where('active', true)->orderBy('name')->get(),
        ];
    }
}
