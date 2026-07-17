<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeEmploymentHistory;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\AttendancePenalty;
use App\Http\Controllers\Controller;
use App\Domains\HRMS\Helpers\XlsxHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'company_id' => $request->integer('company_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'status' => $request->filled('status') ? $request->string('status')->value() : null,
            'sort' => $request->string('sort')->value() ?: 'name_asc',
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

        return view('modules.hrms.employees.index', [
            'employees' => $employees,
            'filters' => $filters,
            'companies' => Company::query()->where('status', true)->orderBy('company_name')->get(),
            'businessUnits' => BusinessUnit::query()->where('status', true)->orderBy('name')->get(),
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('status', true)->orderBy('name')->get(),
            'designations' => Designation::query()->where('status', true)->orderBy('name')->get(),
            'payGroups' => PayGroup::query()->where('status', true)->orderBy('name')->get(),
            'salaryStructures' => SalaryStructure::query()->where('status', true)->orderBy('name')->get(),
            'leavePlans' => LeavePlan::query()->where('status', true)->orderBy('name')->get(),
            'attendancePenalties' => AttendancePenalty::query()->where('status', true)->orderBy('rule_type')->get(),
            'employmentTypes' => $this->employmentTypes(),
            'employeeStages' => $this->employeeStages(),
            'genders' => $this->genders(),
            'maritalStatuses' => $this->maritalStatuses(),
            'bloodGroups' => $this->bloodGroups(),
            'dietPreferences' => $this->dietPreferences(),
            'reportingManagers' => Employee::query()->orderBy('full_name')->get(),
            'shifts' => \App\Domains\Production\Models\ProductionShift::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $this->validatePayload($request);
        $validated = $this->normalizeHierarchy($validated);
        $validated['status'] = $request->input('status', '1') === '1';

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('employees', 'public');
        }

        Employee::create($validated);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $this->validatePayload($request, $employee);
        $validated = $this->normalizeHierarchy($validated);
        $validated['status'] = $request->input('status', '1') === '1';

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('employees', 'public');
        } else {
            unset($validated['photo']);
        }

        $employee->update($validated);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $employee->delete();

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'business_unit_id' => ['nullable', 'exists:business_units,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'pay_group_id' => ['nullable', 'exists:pay_groups,id'],
            'leave_plan_id' => ['nullable', 'exists:leave_plans,id'],
            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('employees', 'employee_id')->ignore($employee?->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'nick_name' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', Rule::in($this->bloodGroups())],
            'employee_stage' => ['nullable', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'date_of_joining' => ['required', 'date'],
            'office' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in($this->genders())],
            'marital_status' => ['nullable', Rule::in($this->maritalStatuses())],
            'diet_preference' => ['nullable', Rule::in($this->dietPreferences())],
            'aadhaar_card_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('employees', 'aadhaar_card_number')->ignore($employee?->id),
            ],
            'pan_card_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('employees', 'pan_card_number')->ignore($employee?->id),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'personal_mobile_number' => ['nullable', 'string', 'max:20'],
            'home_phone' => ['nullable', 'string', 'max:20'],
            'personal_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'personal_email')->ignore($employee?->id),
            ],
            'experience' => ['nullable', 'numeric', 'min:0'],
            'source_of_hire' => ['nullable', 'string', 'max:255'],
            'skill_set' => ['nullable', 'string'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date_of_birth' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'shift_id' => ['nullable', 'integer', 'exists:production_shifts,id'],
            'office_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'office_email')->ignore($employee?->id),
            ],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['0', '1'])],
            'form_mode' => ['nullable', 'string'],
            'editing_employee_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizeHierarchy(array $validated): array
    {
        $company = Company::query()->findOrFail($validated['company_id']);
        $businessUnit = !empty($validated['business_unit_id'])
            ? BusinessUnit::query()->findOrFail($validated['business_unit_id'])
            : null;
        $branch = !empty($validated['branch_id'])
            ? Branch::query()->findOrFail($validated['branch_id'])
            : null;
        $department = Department::query()->findOrFail($validated['department_id']);
        $designation = Designation::query()->findOrFail($validated['designation_id']);
        $payGroup = !empty($validated['pay_group_id'])
            ? PayGroup::query()->findOrFail($validated['pay_group_id'])
            : null;
        $leavePlan = !empty($validated['leave_plan_id'])
            ? LeavePlan::query()->findOrFail($validated['leave_plan_id'])
            : null;

        $departmentCompanyId = $this->resolveDepartmentCompanyId($department);

        if ($businessUnit !== null && (int) $businessUnit->company_id !== (int) $company->id) {
            $this->failHierarchy('business_unit_id', 'The selected business unit does not belong to the chosen company.');
        }

        if ($branch !== null) {
            if ((int) $branch->company_id !== (int) $company->id) {
                $this->failHierarchy('branch_id', 'The selected branch does not belong to the chosen company.');
            }

            if ($businessUnit !== null && (int) $branch->business_unit_id !== (int) $businessUnit->id) {
                $this->failHierarchy('branch_id', 'The selected branch does not belong to the chosen business unit.');
            }
        }

        if ($departmentCompanyId !== null && (int) $departmentCompanyId !== (int) $company->id) {
            $this->failHierarchy('department_id', 'The selected department does not belong to the chosen company.');
        }

        $departmentBusinessUnitId = $department->business_unit_id
            ?: ($department->branch?->business_unit_id ?? null);

        if ($departmentBusinessUnitId !== null) {
            if ($businessUnit === null) {
                $businessUnit = BusinessUnit::query()->find($departmentBusinessUnitId);
            } elseif ((int) $departmentBusinessUnitId !== (int) $businessUnit->id) {
                $this->failHierarchy('department_id', 'The selected department does not belong to the chosen business unit.');
            }
        }

        $departmentBranchId = $department->branch_id;

        if ($departmentBranchId !== null) {
            if ($branch === null) {
                $branch = Branch::query()->find($departmentBranchId);
            } elseif ((int) $departmentBranchId !== (int) $branch->id) {
                $this->failHierarchy('department_id', 'The selected department does not belong to the chosen branch.');
            }
        }

        if ((int) $designation->department_id !== (int) $department->id) {
            $this->failHierarchy('designation_id', 'The selected designation does not belong to the chosen department.');
        }

        if ($payGroup !== null && (int) $payGroup->company_id !== (int) $company->id) {
            $this->failHierarchy('pay_group_id', 'The selected pay group does not belong to the chosen company.');
        }

        if ($leavePlan !== null && (int) $leavePlan->company_id !== (int) $company->id) {
            $this->failHierarchy('leave_plan_id', 'The selected leave structure does not belong to the chosen company.');
        }

        $validated['company_id'] = $company->id;
        $validated['business_unit_id'] = $businessUnit?->id;
        $validated['branch_id'] = $branch?->id;
        $validated['pay_group_id'] = $payGroup?->id;
        $validated['leave_plan_id'] = $leavePlan?->id;

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function employmentTypes(): array
    {
        return ['Full Time', 'Part Time', 'Contract', 'Intern', 'Consultant'];
    }

    /**
     * @return array<int, string>
     */
    private function employeeStages(): array
    {
        return ['Draft', 'Probation', 'Confirmed', 'On Notice', 'Relieved'];
    }

    /**
     * @return array<int, string>
     */
    private function genders(): array
    {
        return ['Male', 'Female', 'Other'];
    }

    /**
     * @return array<int, string>
     */
    private function maritalStatuses(): array
    {
        return ['Single', 'Married', 'Divorced', 'Widowed'];
    }

    /**
     * @return array<int, string>
     */
    private function bloodGroups(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    /**
     * @return array<int, string>
     */
    private function dietPreferences(): array
    {
        return ['Veg', 'Non Veg', 'Vegan'];
    }

    private function failHierarchy(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function resolveDepartmentCompanyId(Department $department): ?int
    {
        if ($department->company_id !== null) {
            return (int) $department->company_id;
        }

        if ($department->branch !== null && $department->branch->company_id !== null) {
            return (int) $department->branch->company_id;
        }

        if ($department->businessUnit !== null && $department->businessUnit->company_id !== null) {
            return (int) $department->businessUnit->company_id;
        }

        return null;
    }

    public function show(Employee $employee): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dynamically resolve Salary Structure slab based on current_salary and pay_group_id
        $salaryStructure = null;
        if ($employee->pay_group_id) {
            $salaryStructure = \App\Domains\HRMS\Models\SalaryStructure::query()
                ->where('pay_group_id', $employee->pay_group_id)
                ->where('min_ctc', '<=', $employee->current_salary)
                ->where('max_ctc', '>=', $employee->current_salary)
                ->where('status', true)
                ->first();
        }

        // Dynamically resolve company Penalization Policies
        $attendancePenalties = \App\Domains\HRMS\Models\AttendancePenalty::query()
            ->where('company_id', $employee->company_id)
            ->where('status', true)
            ->get();
        $attendancePenalty = $attendancePenalties->first();

        // Calculate components breakdown
        $items = $salaryStructure ? $salaryStructure->items()->with('component')->get() : collect();
        $ctc = (float) $employee->current_salary;
        $computedComponents = [];
        $basicAmount = 0.0;

        foreach ($items as $item) {
            if (strtolower($item->component->code) === 'basic') {
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
                'item' => $item,
                'amount' => $amount,
            ];

            if ($item->component->type === 'earning') {
                $totalOtherEarnings += $amount;
            }
        }

        if ($balancingItem) {
            $balancingAmount = max(0.0, $ctc - $totalOtherEarnings);
            $computedComponents[$balancingItem->id] = [
                'item' => $balancingItem,
                'amount' => $balancingAmount,
            ];
        }

        // Load employee penalties, adhoc components, documents, and employment histories
        $employee->load(['documents.requestedBy', 'employmentHistories', 'assetRequests.requestedAsset']);

        $availableAssets = \App\Domains\HRMS\Models\Asset::query()
            ->where('company_id', $employee->company_id)
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        $adhocComponents = \App\Domains\HRMS\Models\EmployeeAdhocComponent::query()
            ->where('employee_id', $employee->id)
            ->with('component')
            ->orderBy('payroll_month', 'desc')
            ->get();

        $penalties = \App\Domains\HRMS\Models\EmployeePenalty::query()
            ->where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->get();

        // Get available adhoc components for dropdown
        $availableAdhocComponents = \App\Domains\HRMS\Models\SalaryComponent::query()
            ->where('pay_group_id', $employee->pay_group_id)
            ->where('is_adhoc', true)
            ->where('status', true)
            ->get();

        return view('modules.hrms.employees.show', compact(
            'employee',
            'salaryStructure',
            'attendancePenalty',
            'attendancePenalties',
            'computedComponents',
            'adhocComponents',
            'penalties',
            'availableAdhocComponents',
            'availableAssets'
        ));
    }

    public function storeAdhocComponent(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'amount' => 'required|numeric|min:0',
            'payroll_month' => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks' => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status'] = 'pending';

        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create($validated);

        return redirect()
            ->route('hrms.employees.show', $employee->id)
            ->with('success', 'Adhoc Salary Component added successfully.');
    }

    public function destroyAdhocComponent(\App\Domains\HRMS\Models\EmployeeAdhocComponent $adhocComponent): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $employeeId = $adhocComponent->employee_id;
        $adhocComponent->delete();

        return redirect()
            ->route('hrms.employees.show', $employeeId)
            ->with('success', 'Adhoc Salary Component deleted successfully.');
    }

    public function storePenalty(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'date' => 'required|date',
            'rule_type' => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'payroll_month' => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks' => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status'] = 'pending';

        \App\Domains\HRMS\Models\EmployeePenalty::create($validated);

        return redirect()
            ->route('hrms.employees.show', $employee->id)
            ->with('success', 'Attendance Penalty instance logged successfully.');
    }

    public function destroyPenalty(\App\Domains\HRMS\Models\EmployeePenalty $penalty): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $employeeId = $penalty->employee_id;
        $penalty->delete();

        return redirect()
            ->route('hrms.employees.show', $employeeId)
            ->with('success', 'Attendance Penalty instance deleted successfully.');
    }

    public function requestDocument(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'has_expiry' => 'nullable|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;

        \App\Domains\HRMS\Models\Document::create([
            'tenant_id' => $tenantId,
            'documentable_id' => $employee->id,
            'documentable_type' => Employee::class,
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'status' => 'requested',
            'has_expiry' => $request->boolean('has_expiry'),
            'requested_by_id' => auth()->id(),
        ]);

        return redirect()->route('hrms.employees.show', [$employee->id, 'tab' => 'documents'])
            ->with('success', 'Document request created successfully.');
    }

    public function uploadDocument(Request $request, Employee $employee): RedirectResponse
    {
        // Permission check
        $isHR = auth()->user()->hasHrPermission('hr.settings.manage');
        $isOwnProfile = auth()->id() === ($employee->user_id ?? null);
        
        abort_unless($isHR || $isOwnProfile, 403);

        $request->validate([
            'document_id' => 'nullable|exists:documents,id',
            'name' => 'nullable|required_without:document_id|string|max:255',
            'file' => 'required|file|max:10240', // Max 10MB
            'expiry_date' => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $file = $request->file('file');

        if ($request->filled('document_id')) {
            // Uploading file for an existing request
            $document = \App\Domains\HRMS\Models\Document::findOrFail($request->integer('document_id'));
            
            // Validate expiry if requested configuration demands it
            if ($document->has_expiry && !$request->filled('expiry_date')) {
                return back()->withErrors(['expiry_date' => 'Expiry date is required for this requested document.'])->withInput();
            }

            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $document->update([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'expiry_date' => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'status' => 'uploaded',
            ]);
        } else {
            // Direct upload without previous request
            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            \App\Domains\HRMS\Models\Document::create([
                'tenant_id' => $tenantId,
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'name' => $request->string('name')->value(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'expiry_date' => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'status' => 'uploaded',
                'has_expiry' => $request->filled('expiry_date'),
            ]);
        }

        return redirect()->route('hrms.employees.show', [$employee->id, 'tab' => 'documents'])
            ->with('success', 'Document uploaded successfully.');
    }

    public function destroyDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $employeeId = $document->documentable_id;

        // Delete actual file from storage if it exists
        if ($document->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('hrms.employees.show', [$employeeId, 'tab' => 'documents'])
            ->with('success', 'Document record deleted successfully.');
    }

    public function storeEmploymentHistory(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_description' => 'nullable|string|max:1000',
        ]);

        $employee->employmentHistories()->create($validated);

        return redirect()
            ->route('hrms.employees.show', [$employee->id, 'tab' => 'history'])
            ->with('success', 'Employment history record added successfully.');
    }

    public function destroyEmploymentHistory(Employee $employee, EmployeeEmploymentHistory $history): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $history->delete();

        return redirect()
            ->route('hrms.employees.show', [$employee->id, 'tab' => 'history'])
            ->with('success', 'Employment history record deleted successfully.');
    }

    /**
     * Export all employees to Excel (.xlsx) format
     */
    public function export()
    {
        $headers = [
            'Employee ID',
            'Full Name',
            'Personal Email',
            'Office Email',
            'Mobile Number',
            'Gender',
            'Marital Status',
            'Employment Type',
            'Date of Joining',
            'Date of Birth',
            'Current Salary',
            'Experience (Years)',
            'Qualification',
            'Company Name',
            'Department Name',
            'Designation Name',
            'Status'
        ];

        $employees = Employee::with(['company', 'department', 'designation'])->get();
        $data = [];

        foreach ($employees as $emp) {
            $data[] = [
                $emp->employee_id,
                $emp->full_name,
                $emp->personal_email,
                $emp->office_email,
                $emp->personal_mobile_number,
                $emp->gender,
                $emp->marital_status,
                $emp->employment_type,
                $emp->date_of_joining ? $emp->date_of_joining->format('Y-m-d') : '',
                $emp->date_of_birth ? $emp->date_of_birth->format('Y-m-d') : '',
                $emp->current_salary,
                $emp->experience,
                $emp->qualification,
                $emp->company?->company_name,
                $emp->department?->name,
                $emp->designation?->name,
                $emp->status ? 'Active' : 'Inactive'
            ];
        }

        return XlsxHelper::export($headers, $data, 'employees_export_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Download XLSX Template for Employee Import
     */
    public function downloadTemplate()
    {
        $headers = [
            'full_name',
            'personal_email',
            'office_email',
            'personal_mobile_number',
            'date_of_joining',
            'date_of_birth',
            'gender',
            'marital_status',
            'employment_type',
            'current_salary',
            'experience',
            'qualification',
            'company_name',
            'department_name',
            'designation_name'
        ];

        $data = [
            [
                'John Doe',
                'john.doe@gmail.com',
                'johnd@acme.com',
                '9876543210',
                '2026-07-11',
                '1995-05-15',
                'male',
                'single',
                'full-time',
                55000,
                3.5,
                'Bachelor of Engineering',
                'Acme Corporation',
                'Engineering',
                'Software Engineer'
            ]
        ];

        return XlsxHelper::export($headers, $data, 'employees_import_template.xlsx');
    }

    /**
     * Import employees from XLSX file
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $filePath = $request->file('file')->getRealPath();
            $rows = XlsxHelper::import($filePath);

            if (empty($rows)) {
                return redirect()->back()->with('error', 'The Excel file is empty.');
            }

            // The first row contains the headers
            $headers = array_shift($rows);
            $headers = array_map(function($h) {
                // Lowercase, trim, remove BOM, replace spaces/dashes with underscores, keep alphanumeric/underscores
                $h = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $h)));
                $h = str_replace([' ', '-'], '_', $h);
                $h = preg_replace('/[^a-z0-9_]/', '', $h);

                // Map common human-friendly export names to system fields
                if ($h === 'full_name' || $h === 'fullname' || $h === 'name') {
                    return 'full_name';
                }
                if ($h === 'personal_email' || $h === 'email') {
                    return 'personal_email';
                }
                if ($h === 'mobile_number' || $h === 'personal_mobile_number' || $h === 'phone_number' || $h === 'phone' || $h === 'mobile') {
                    return 'personal_mobile_number';
                }
                if ($h === 'experience_years' || $h === 'experience') {
                    return 'experience';
                }
                return $h;
            }, $headers);

            $headerMap = array_flip($headers);

            // Required field validation
            if (!isset($headerMap['full_name'])) {
                return redirect()->back()->with('error', "Required column 'full_name' is missing in the Excel file.");
            }

            $importedCount = 0;
            $errors = [];
            $rowNumber = 1; // headers shifted

            \DB::beginTransaction();
            foreach ($rows as $row) {
                $rowNumber++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Pad row if it has fewer elements than headers
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                }

                $data = [];
                foreach ($headerMap as $field => $index) {
                    $data[$field] = isset($row[$index]) && $row[$index] !== '' ? trim($row[$index]) : null;
                }

                // 1. Resolve Company
                $companyId = null;
                if (!empty($data['company_name'])) {
                    $company = Company::where('company_name', 'like', $data['company_name'])->first();
                    if (!$company) {
                        $company = Company::first();
                    }
                    $companyId = $company?->id;
                } else {
                    $company = Company::first();
                    $companyId = $company?->id;
                }

                if (!$companyId) {
                    $errors[] = "Row {$rowNumber}: No valid company found. Please configure a company first.";
                    continue;
                }

                // 2. Resolve Department
                $departmentId = null;
                if (!empty($data['department_name'])) {
                    $dept = Department::where('name', 'like', $data['department_name'])
                        ->where('company_id', $companyId)
                        ->first();
                    if (!$dept) {
                        // Generate a clean department code from its name
                        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $data['department_name']);
                        $deptCode = strtoupper(substr($cleanName, 0, 10));
                        if (empty($deptCode)) {
                            $deptCode = 'DEPT-' . rand(100, 999);
                        }

                        // Ensure uniqueness in the database
                        $originalCode = $deptCode;
                        $counter = 1;
                        while (Department::where('code', $deptCode)->exists()) {
                            $deptCode = substr($originalCode, 0, 7) . $counter;
                            $counter++;
                        }

                        $dept = Department::create([
                            'company_id' => $companyId,
                            'name' => $data['department_name'],
                            'code' => $deptCode,
                            'status' => true
                        ]);
                    }
                    $departmentId = $dept->id;
                }

                // 3. Resolve Designation
                $designationId = null;
                if (!empty($data['designation_name'])) {
                    $desg = Designation::where('name', 'like', $data['designation_name']);
                    if ($departmentId) {
                        $desg = $desg->where('department_id', $departmentId);
                    }
                    $desg = $desg->first();
                    if (!$desg) {
                        $desg = Designation::create([
                            'department_id' => $departmentId,
                            'name' => $data['designation_name'],
                            'status' => true
                        ]);
                    }
                    $designationId = $desg->id;
                }

                $employeeData = [
                    'company_id' => $companyId,
                    'department_id' => $departmentId,
                    'designation_id' => $designationId,
                    'full_name' => $data['full_name'],
                    'personal_email' => $data['personal_email'] ?? null,
                    'office_email' => $data['office_email'] ?? null,
                    'personal_mobile_number' => $data['personal_mobile_number'] ?? null,
                    'gender' => isset($data['gender']) ? strtolower($data['gender']) : 'male',
                    'marital_status' => isset($data['marital_status']) ? strtolower($data['marital_status']) : 'single',
                    'employment_type' => isset($data['employment_type']) ? strtolower($data['employment_type']) : 'full-time',
                    'current_salary' => isset($data['current_salary']) ? floatval($data['current_salary']) : 0,
                    'experience' => isset($data['experience']) ? floatval($data['experience']) : 0,
                    'qualification' => $data['qualification'] ?? null,
                    'status' => true
                ];

                if (!empty($data['date_of_joining'])) {
                    try {
                        $employeeData['date_of_joining'] = \Carbon\Carbon::parse($data['date_of_joining'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $employeeData['date_of_joining'] = now()->format('Y-m-d');
                    }
                } else {
                    $employeeData['date_of_joining'] = now()->format('Y-m-d');
                }

                if (!empty($data['date_of_birth'])) {
                    try {
                        $employeeData['date_of_birth'] = \Carbon\Carbon::parse($data['date_of_birth'])->format('Y-m-d');
                    } catch (\Exception $e) {}
                }

                Employee::create($employeeData);
                $importedCount++;
            }

            \DB::commit();

            if (count($errors) > 0) {
                return redirect()->back()->with('success', "Import completed: {$importedCount} employees imported successfully. Warnings: " . implode(', ', $errors));
            }

            return redirect()->back()->with('success', "{$importedCount} employees imported successfully.");

        } catch (\Exception $e) {
            \DB::rollBack();
            return redirect()->back()->with('error', 'Error reading Excel file: ' . $e->getMessage());
        }
    }
}

