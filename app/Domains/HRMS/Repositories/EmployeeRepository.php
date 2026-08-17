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
            'reportingManager', 'shift', 'employmentHistories', 'documents',
            'assets.category',
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

        $salaryStructure = null;
        if ($employee->pay_group_id) {
            $salaryStructure = \App\Domains\HRMS\Models\SalaryStructure::query()
                ->where('pay_group_id', $employee->pay_group_id)
                ->where('min_ctc', '<=', $employee->current_salary)
                ->where('max_ctc', '>=', $employee->current_salary)
                ->where('status', true)
                ->first();
        }
        if (!$salaryStructure && $employee->salaryStructure) {
            $salaryStructure = $employee->salaryStructure;
        }

        $computedComponents = [];
        if ($salaryStructure) {
            $items = $salaryStructure->items()->with('component')->get();
            $ctc = (float) $employee->current_salary;
            $basicAmount = 0.0;

            foreach ($items as $item) {
                if ($item->component && strtolower($item->component->code) === 'basic') {
                    if ($item->calculation_type === 'fixed') {
                        $basicAmount = (float) $item->value;
                    } elseif ($item->calculation_type === 'percentage_of_ctc') {
                        $basicAmount = ($item->value / 100) * $ctc;
                    }
                    break;
                }
            }

            $totalOtherEarnings = 0.0;
            $balancingItem = null;

            foreach ($items as $item) {
                if (!$item->component) {
                    continue;
                }
                $amount = 0.0;
                if ($item->calculation_type === 'fixed') {
                    $amount = (float) $item->value;
                } elseif ($item->calculation_type === 'percentage_of_ctc') {
                    $amount = ($item->value / 100) * $ctc;
                } elseif ($item->calculation_type === 'percentage_of_basic') {
                    $amount = ($item->value / 100) * $basicAmount;
                } elseif ($item->calculation_type === 'balancing') {
                    $balancingItem = $item;
                    continue;
                }

                $computedComponents[$item->id] = [
                    'item'   => $item,
                    'amount' => $amount,
                ];

                if ($item->component->type === 'earning') {
                    $totalOtherEarnings += $amount;
                }
            }

            if ($balancingItem && $balancingItem->component) {
                $balancingAmount = max(0.0, $ctc - $totalOtherEarnings);
                $computedComponents[$balancingItem->id] = [
                    'item'   => $balancingItem,
                    'amount' => $balancingAmount,
                ];
            }
        }

        $adhocComponents = \App\Domains\HRMS\Models\EmployeeAdhocComponent::where('employee_id', $employee->id)->get();
        $penalties = \App\Domains\HRMS\Models\EmployeePenalty::where('employee_id', $employee->id)->get();

        // Initialize missing leave balances for active types in the employee's assigned leave plan
        if ($employee->leavePlan) {
            foreach ($employee->leavePlan->types()->where('status', true)->get() as $lt) {
                \App\Domains\HRMS\Models\LeaveBalance::firstOrCreate([
                    'tenant_id'     => $employee->tenant_id,
                    'company_id'    => $employee->company_id,
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $lt->id,
                ], [
                    'allocated' => floatval($lt->quota),
                    'used'      => 0.0,
                ]);
            }
        }

        // Leave balances (allowances) for the assigned leave plan
        $leaveAllowances = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $employee->id)
            ->whereHas('leaveType', function ($query) {
                $query->where('status', true);
            })
            ->with('leaveType')
            ->get();

        // Filtered & paginated leave requests
        $leaveRequestsQuery = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderBy('created_at', 'desc');
        if (!empty($inputs['leave_type'])) {
            $leaveRequestsQuery->where('leave_type_id', (int) $inputs['leave_type']);
        }
        if (!empty($inputs['leave_status'])) {
            $leaveRequestsQuery->where('status', $inputs['leave_status']);
        }
        if (!empty($inputs['leave_search'])) {
            $leaveRequestsQuery->where('reason', 'like', '%' . $inputs['leave_search'] . '%');
        }
        $leaveRequests = $leaveRequestsQuery->paginate(10, ['*'], 'leave_page')->withQueryString();

        $empLeaveRequests = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
        $empLeaveEncashments = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
        $encashmentRequests = $empLeaveEncashments;

        $empWfhRequests = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employee->id)->orderBy('created_at', 'desc')->get();
        $empShiftChangeRequests = \App\Domains\HRMS\Models\ShiftChangeRequest::where('employee_id', $employee->id)->with(['currentShift', 'requestedShift'])->orderBy('created_at', 'desc')->get();
        $empOvertimeRequests = \App\Domains\HRMS\Models\OvertimeRequest::where('employee_id', $employee->id)->orderBy('created_at', 'desc')->get();
        $documents = \App\Domains\HRMS\Models\Document::where('documentable_type', Employee::class)
            ->where('documentable_id', $employee->id)
            ->paginate(10, ['*'], 'doc_page')
            ->withQueryString();
        $attendancePenalty = $employee->attendancePenalty;
        $attendancePenalties = \App\Domains\HRMS\Models\AttendancePenalty::where('status', true)->get();
        $empAttendances = \App\Domains\HRMS\Models\Attendance::where('employee_id', $employee->id)->with(['breaks', 'locationLogs'])->orderBy('date', 'desc')->get();
        $todayAttendance = \App\Domains\HRMS\Models\Attendance::where('employee_id', $employee->id)->where('date', \Carbon\Carbon::today()->format('Y-m-d'))->first();

        $isAdminUser = true;

        $availableAdhocComponents = \App\Domains\HRMS\Models\SalaryComponent::adhoc()->where('status', true)->get();
        if ($availableAdhocComponents->isEmpty()) {
            $availableAdhocComponents = \App\Domains\HRMS\Models\SalaryComponent::where('status', true)->get();
        }

        $employeeDataMap = [];
        $allEmpForMap = Employee::with(['leavePlan.types'])->get();
        foreach ($allEmpForMap as $emp) {
            $typesList = [];
            if ($emp->leavePlan) {
                foreach ($emp->leavePlan->types as $lt) {
                    if ($lt->status) {
                        $bal = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $emp->id)
                            ->where('leave_type_id', $lt->id)
                            ->first();

                        $allocated = $bal ? floatval($bal->allocated) : floatval($lt->quota);
                        $used = $bal ? floatval($bal->used) : 0;
                        $remaining = max(0, $allocated - $used);

                        $typesList[] = [
                            'id'        => $lt->id,
                            'name'      => $lt->name,
                            'code'      => $lt->code,
                            'type'      => $lt->type,
                            'quota'     => $allocated,
                            'used'      => $used,
                            'remaining' => $remaining,
                            'rules'     => $lt->rules ?? [],
                        ];
                    }
                }
            }
            $employeeDataMap[$emp->id] = $typesList;
        }

        $options = $this->getDropdownOptions();

        $documentMasters = \App\Domains\HRMS\Models\DocumentMaster::where('status', 'active')->orderBy('name')->get();
        $activeTabName = $inputs['active_tab'] ?? $inputs['tab'] ?? 'overview';

        return array_merge([
            'employee'                  => $employee,
            'availableAssets'           => $availableAssets,
            'leaveTypes'                => $leaveTypes,
            'documentMasters'           => $documentMasters,
            'allEmployees'              => $allEmployees,
            'salaryStructure'           => $salaryStructure,
            'computedComponents'        => $computedComponents,
            'adhocComponents'           => $adhocComponents,
            'availableAdhocComponents'  => $availableAdhocComponents,
            'penalties'                 => $penalties,
            'leaveAllowances'           => $leaveAllowances,
            'leaveRequests'             => $leaveRequests,
            'encashmentRequests'        => $encashmentRequests,
            'empLeaveRequests'          => $empLeaveRequests,
            'empLeaveEncashments'       => $empLeaveEncashments,
            'empWfhRequests'            => $empWfhRequests,
            'empShiftChangeRequests'    => $empShiftChangeRequests,
            'empOvertimeRequests'       => $empOvertimeRequests,
            'documents'                 => $documents,
            'attendancePenalty'         => $attendancePenalty,
            'attendancePenalties'       => $attendancePenalties,
            'empAttendances'            => $empAttendances,
            'todayAttendance'           => $todayAttendance,
            'isAdminUser'               => $isAdminUser,
            'isAdmin'                   => $isAdminUser,
            'employeeDataMap'           => $employeeDataMap,
            'activeTabName'             => $activeTabName,
        ], $options);
    }

    public function storeEmployee(array $validated, Request $request): Employee
    {
        $validated['status'] = $request->input('status', '1') === '1';

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee = Employee::create($validated);

        if ($request->filled('role_id') && $employee->user) {
            $employee->user->update(['role_id' => $request->role_id]);
        }

        return $employee;
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

        $updated = $employee->update($validated);

        if ($request->filled('role_id') && $employee->user) {
            $employee->user->update(['role_id' => $request->role_id]);
        }

        return $updated;
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
            'unmappedUsers' => \App\Models\User::query()
                ->where('tenant_id', auth()->user()?->tenant_id)
                ->whereDoesntHave('employee')
                ->orderBy('name')
                ->get(),
            'roles' => \App\Models\Access\Role::all(),
        ];
    }
}
