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
        $data = $this->employeeRepository->getDirectoryData($request->all());

        return view('modules.hrms.employees.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('user_id')) {
            $targetUser = \App\Models\User::find($request->user_id);
            if ($targetUser) {
                $request->merge([
                    'full_name' => $targetUser->name,
                    'personal_email' => $targetUser->email,
                ]);
            }
        }

        $validated = $this->validatePayload($request);
        $validated = $this->normalizeHierarchy($validated);

        $this->employeeRepository->storeEmployee($validated, $request);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $data = $this->employeeRepository->getProfileData($employee, $request->all());

        return view('modules.hrms.employees.show', $data);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $oldPlanId = $employee->leave_plan_id;

        $validated = $this->validatePayload($request, $employee);
        $validated = $this->normalizeHierarchy($validated);

        $newPlanId = !empty($validated['leave_plan_id']) ? (int)$validated['leave_plan_id'] : null;
        if ($newPlanId !== null && (int)$oldPlanId !== $newPlanId) {
            $hasPending = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPending) {
                return redirect()->back()
                    ->with('error', 'Cannot change the leave plan. Please approve or reject all pending leave requests first.');
            }

            $hasPendingEncashment = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPendingEncashment) {
                return redirect()->back()
                    ->with('error', 'Cannot change the leave plan. Please approve or reject all pending leave encashment requests first.');
            }
        }

        $this->employeeRepository->updateEmployee($employee, $validated, $request);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
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
                $employee ? 'required' : 'nullable', 'string', 'max:255',
                Rule::unique('employees', 'employee_id')->ignore($employeeId),
            ],
            'user_id' => [
                'nullable',
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],
            'role_id' => ['nullable', 'exists:roles,id'],

            'title' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => [
                'required', 'email', 'max:255',
                Rule::unique('employees', 'personal_email')->ignore($employeeId),
            ],
            'office_email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'office_email')->ignore($employeeId),
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
            'office' => ['nullable', 'string', 'in:office,wfh,onsite'],
            'wfh_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'wfh_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'date_of_joining' => ['required', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['required', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:20'],
            'personal_mobile_number' => ['nullable', 'string', 'max:50'],
            'home_phone' => ['nullable', 'string', 'max:50'],
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

    public function storeAdhocComponent(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'amount'              => 'required|numeric|min:0',
            'payroll_month'       => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'             => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status']      = 'pending';

        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create($validated);

        return redirect()->back()->with('success', __('hrms.employees.adhoc_add_success'));
    }

    public function destroyAdhocComponent(\App\Domains\HRMS\Models\EmployeeAdhocComponent $adhocComponent): RedirectResponse
    {
        $adhocComponent->delete();

        return redirect()->back()->with('success', __('hrms.employees.adhoc_delete_success'));
    }

    public function storePenalty(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'rule_type'      => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'payroll_month'  => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'        => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status']      = 'pending';

        \App\Domains\HRMS\Models\EmployeePenalty::create($validated);

        return redirect()->back()->with('success', __('hrms.employees.penalty_log_success'));
    }

    public function destroyPenalty(\App\Domains\HRMS\Models\EmployeePenalty $penalty): RedirectResponse
    {
        $penalty->delete();

        return redirect()->back()->with('success', __('hrms.employees.penalty_delete_success'));
    }

    public function storeEmploymentHistory(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'company_name'    => 'required|string|max:255',
            'designation'     => 'required|string|max:255',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'job_description' => 'nullable|string|max:1000',
        ]);

        $employee->employmentHistories()->create($validated);

        return redirect()->back()->with('success', __('hrms.employees.history_add_success'));
    }

    public function destroyEmploymentHistory(Employee $employee, \App\Domains\HRMS\Models\EmployeeEmploymentHistory $history): RedirectResponse
    {
        $history->delete();

        return redirect()->back()->with('success', __('hrms.employees.history_delete_success'));
    }

    public function requestDocument(Request $request, Employee $employee): RedirectResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'has_expiry'  => 'nullable|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;

        \App\Domains\HRMS\Models\Document::create([
            'tenant_id'         => $tenantId,
            'documentable_id'   => $employee->id,
            'documentable_type' => Employee::class,
            'name'              => $request->string('name')->value(),
            'description'       => $request->input('description'),
            'status'            => 'requested',
            'has_expiry'        => $request->boolean('has_expiry'),
            'requested_by_id'   => auth()->id(),
        ]);

        return redirect()->back()->with('success', __('hrms.employees.doc_request_success'));
    }

    public function uploadDocument(Request $request, Employee $employee): RedirectResponse
    {
        $request->validate([
            'document_id' => 'nullable|exists:documents,id',
            'name'        => 'nullable|required_without:document_id|string|max:255',
            'file'        => 'required|file|max:10240', // Max 10MB
            'expiry_date' => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $file = $request->file('file');

        if ($request->filled('document_id')) {
            $document = \App\Domains\HRMS\Models\Document::findOrFail($request->integer('document_id'));
            
            if ($document->has_expiry && !$request->filled('expiry_date')) {
                return redirect()->back()->withErrors(['expiry_date' => 'Expiry date is required for this requested document.'])->withInput();
            }

            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $document->update([
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
                'file_type'   => $file->getClientMimeType(),
                'file_size'   => $file->getSize(),
                'expiry_date' => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'status'      => 'uploaded',
            ]);
        } else {
            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            \App\Domains\HRMS\Models\Document::create([
                'tenant_id'         => $tenantId,
                'documentable_id'   => $employee->id,
                'documentable_type' => Employee::class,
                'name'              => $request->string('name')->value(),
                'file_name'         => $file->getClientOriginalName(),
                'file_path'         => $path,
                'file_type'         => $file->getClientMimeType(),
                'file_size'         => $file->getSize(),
                'expiry_date'       => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'status'            => 'uploaded',
                'has_expiry'        => $request->filled('expiry_date'),
            ]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        if ($document->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Document record deleted successfully.');
    }

    public function approveDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $document->update([
            'status' => 'approved',
        ]);

        return redirect()->back()->with('success', 'Document approved successfully.');
    }

    public function rejectDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $document->update([
            'status' => 'rejected',
        ]);

        return redirect()->back()->with('success', 'Document rejected successfully.');
    }

    public function updateDocumentStatus(Request $request, \App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $document->update([
            'status' => $validated['status'],
        ]);

        $msg = $validated['status'] === 'approved' ? 'Document approved successfully.' : 'Document marked as rejected.';

        return redirect()->back()->with('success', $msg);
    }
}

