<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\EmployeeRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
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
        $this->authorizeHrms('hrms.employees.view');

        $data = $this->employeeRepository->getDirectoryData($request->all());

        return view('modules.hrms.employees.index', $data);
    }

    public function export(Request $request)
    {
        $this->authorizeHrms('hrms.employees.view');

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $employees = Employee::with(['department', 'designation', 'company'])
            ->where('tenant_id', $tenantId)
            ->orderBy('full_name')
            ->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=employees_export_" . date('Ymd_His') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        $columns = ['Employee Code', 'Full Name', 'Personal Email', 'Office Email', 'Department', 'Designation', 'Company', 'Date of Joining', 'Status'];

        $callback = function () use ($employees, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($employees as $employee) {
                fputcsv($file, [
                    $employee->employee_id ?? '',
                    $employee->full_name ?? '',
                    $employee->personal_email ?? '',
                    $employee->office_email ?? '',
                    $employee->department?->name ?? '',
                    $employee->designation?->name ?? '',
                    $employee->company?->company_name ?? '',
                    $employee->date_of_joining ? $employee->date_of_joining->format('Y-m-d') : '',
                    $employee->status ? 'Active' : 'Inactive',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadTemplate()
    {
        $this->authorizeHrms('hrms.employees.create');

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=employees_import_template.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        $columns = ['full_name', 'personal_email', 'employee_id', 'company_id', 'department_id', 'designation_id', 'date_of_joining', 'gender'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['Jane Doe', 'jane.doe@example.com', 'EMP-0001', '1', '1', '1', '2026-01-15', 'female']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.create');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return redirect()->back()->with('error', 'The uploaded file is empty.');
        }

        $header = array_map('strtolower', array_map('trim', $header));
        $required = ['full_name', 'personal_email', 'company_id', 'department_id', 'designation_id', 'date_of_joining', 'gender'];
        $colIndex = [];
        foreach (array_merge($required, ['employee_id']) as $col) {
            $colIndex[$col] = array_search($col, $header, true);
        }

        foreach ($required as $col) {
            if ($colIndex[$col] === false) {
                fclose($handle);
                return redirect()->back()->with('error', "Invalid template. Missing required column \"{$col}\".");
            }
        }

        $successCount = 0;
        $skippedRows = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (empty($row) || count($row) < 2) {
                continue;
            }

            $rowData = [
                'tenant_id'        => $tenantId,
                'full_name'        => trim($row[$colIndex['full_name']] ?? ''),
                'personal_email'   => trim($row[$colIndex['personal_email']] ?? ''),
                'employee_id'      => $colIndex['employee_id'] !== false ? trim($row[$colIndex['employee_id']] ?? '') : null,
                'company_id'       => trim($row[$colIndex['company_id']] ?? ''),
                'department_id'    => trim($row[$colIndex['department_id']] ?? ''),
                'designation_id'   => trim($row[$colIndex['designation_id']] ?? ''),
                'date_of_joining'  => trim($row[$colIndex['date_of_joining']] ?? ''),
                'gender'           => trim($row[$colIndex['gender']] ?? ''),
                'status'           => true,
            ];

            $validator = \Illuminate\Support\Facades\Validator::make($rowData, [
                'full_name' => 'required|string|max:255',
                'personal_email' => [
                    'required', 'email', 'max:255',
                    Rule::unique('employees', 'personal_email')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                ],
                'company_id' => 'required|exists:companies,id',
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'date_of_joining' => 'required|date',
                'gender' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                $skippedRows[] = "Row {$rowNum}: " . implode(' ', $validator->errors()->all());
                continue;
            }

            Employee::create($rowData);
            $successCount++;
        }

        fclose($handle);

        $message = "{$successCount} employee(s) imported successfully.";
        if (!empty($skippedRows)) {
            $message .= ' ' . count($skippedRows) . ' row(s) skipped: ' . implode(' | ', array_slice($skippedRows, 0, 5));
        }

        return redirect()->back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.create');

        if ($request->filled('user_id')) {
            $targetUser = \App\Models\User::find($request->user_id);
            if ($targetUser) {
                if (!$request->filled('full_name')) {
                    $request->merge(['full_name' => $targetUser->name]);
                }
                if (!$request->filled('personal_email')) {
                    $request->merge(['personal_email' => $targetUser->email]);
                }
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
        $this->authorizeHrms('hrms.employees.update');

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
        $this->authorizeHrms('hrms.employees.delete');

        $this->employeeRepository->deleteEmployee($employee);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    private function validatePayload(Request $request, ?Employee $employee = null): array
    {
        $employeeId = $employee?->id;
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return $request->validate([
            'employee_id' => [
                $employee ? 'required' : 'nullable', 'string', 'max:255',
                Rule::unique('employees', 'employee_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($employeeId),
            ],
            'user_id' => [
                'required',
                Rule::unique('employees', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($employeeId),
            ],
            'role_id' => ['nullable', 'exists:roles,id'],

            'title' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => [
                'required', 'email', 'max:255',
                Rule::unique('employees', 'personal_email')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($employeeId),
            ],
            'office_email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees', 'office_email')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($employeeId),
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
        $this->authorizeHrms('hrms.employees.update');

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
        $this->authorizeHrms('hrms.employees.update');

        $adhocComponent->delete();

        return redirect()->back()->with('success', __('hrms.employees.adhoc_delete_success'));
    }

    public function storePenalty(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

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
        $this->authorizeHrms('hrms.employees.update');

        $penalty->delete();

        return redirect()->back()->with('success', __('hrms.employees.penalty_delete_success'));
    }

    public function storeEmploymentHistory(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

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
        $this->authorizeHrms('hrms.employees.update');

        $history->delete();

        return redirect()->back()->with('success', __('hrms.employees.history_delete_success'));
    }

    public function uploadDocument(Request $request, Employee $employee): RedirectResponse
    {
        $isOwnProfile = auth()->user()?->employee?->id === $employee->id;
        if (!$isOwnProfile) {
            $this->authorizeHrms('hrms.employees.update');
        }

        $request->validate([
            'document_id'        => 'nullable|exists:documents,id',
            'document_master_id' => 'required_without:document_id|exists:document_masters,id',
            'file'               => 'required|file|max:10240', // Max 10MB
            'expiry_date'        => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $file = $request->file('file');

        if ($request->filled('document_id')) {
            $document = \App\Domains\HRMS\Models\Document::findOrFail($request->integer('document_id'));
            
            $expiryApplicable = $document->has_expiry;
            if ($document->document_master_id) {
                $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::find($document->document_master_id);
                if ($documentMaster) {
                    $expiryApplicable = $documentMaster->expiry_applicable;
                }
            }

            if ($expiryApplicable && !$request->filled('expiry_date')) {
                return redirect()->back()->withErrors(['expiry_date' => 'Expiry date is required for this document.'])->withInput();
            }

            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $approvalRequired = true;
            $requiresSignature = false;
            if ($document->document_master_id) {
                $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::find($document->document_master_id);
                if ($documentMaster) {
                    $approvalRequired = (bool) $documentMaster->approval_required;
                    $requiresSignature = (bool) $documentMaster->requires_signature;
                }
            }
            $status = $requiresSignature ? 'pending_signature' : ($approvalRequired ? 'uploaded' : 'approved');

            $document->update([
                'file_name'          => $file->getClientOriginalName(),
                'file_path'          => $path,
                'file_type'          => $file->getClientMimeType(),
                'file_size'          => $file->getSize(),
                'expiry_date'        => $expiryApplicable && $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'requires_signature' => $requiresSignature,
                'status'             => $status,
                'requested_by_id'    => auth()->id(),
            ]);
        } else {
            $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::findOrFail($request->integer('document_master_id'));
            
            if ($documentMaster->expiry_applicable && !$request->filled('expiry_date')) {
                return redirect()->back()->withErrors(['expiry_date' => 'Expiry date is required for this document template.'])->withInput();
            }

            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $approvalRequired = (bool) $documentMaster->approval_required;
            $requiresSignature = (bool) $documentMaster->requires_signature;
            $status = $requiresSignature ? 'pending_signature' : ($approvalRequired ? 'uploaded' : 'approved');

            \App\Domains\HRMS\Models\Document::create([
                'tenant_id'          => $tenantId,
                'documentable_id'    => $employee->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $documentMaster->id,
                'name'               => $documentMaster->name,
                'description'        => $documentMaster->description,
                'file_name'          => $file->getClientOriginalName(),
                'file_path'          => $path,
                'file_type'          => $file->getClientMimeType(),
                'file_size'          => $file->getSize(),
                'requires_signature' => $requiresSignature,
                'status'             => $status,
                'has_expiry'         => $documentMaster->expiry_applicable,
                'expiry_date'        => $documentMaster->expiry_applicable && $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'requested_by_id'    => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        if ($document->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Document record deleted successfully.');
    }

    public function approveDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        $document->update([
            'status' => 'approved',
        ]);

        return redirect()->back()->with('success', 'Document approved successfully.');
    }

    public function rejectDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        $document->update([
            'status' => 'rejected',
        ]);

        return redirect()->back()->with('success', 'Document rejected successfully.');
    }

    public function updateDocumentStatus(Request $request, \App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,pending_signature',
        ]);

        $document->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Document status updated successfully.');
    }

    public function signDocument(Request $request, \App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'signature_image'    => 'required|string',
            'signature_position' => 'nullable|string',
            'pos_x'              => 'nullable|numeric',
            'pos_y'              => 'nullable|numeric',
        ]);

        $signatureService = app(\App\Domains\HRMS\Services\DocumentSignatureService::class);
        $signatureService->signUploadedDocument(
            $document,
            $validated['signature_image'],
            [
                'position' => $validated['signature_position'] ?? 'bottom_right',
                'pos_x'    => $request->input('pos_x'),
                'pos_y'    => $request->input('pos_y'),
            ]
        );

        return redirect()->back()->with('success', 'Document digitally signed successfully.');
    }

    public function viewSignedDocument(Request $request, \App\Domains\HRMS\Models\Document $document): View
    {
        return view('modules.hrms.employees.documents.view_signed', [
            'document' => $document,
        ]);
    }

    public function updateStatus(Request $request, Employee $employee)
    {
        $this->authorizeHrms('hrms.employees.update');

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $employee->update(['status' => $validated['status']]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee status updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Employee status updated successfully.');
    }

    public function updateStage(Request $request, Employee $employee)
    {
        $this->authorizeHrms('hrms.employees.update');

        $validated = $request->validate([
            'employee_stage' => 'required|string|in:Probation,Confirmed,Notice Period,Exited',
        ]);

        $employee->update(['employee_stage' => $validated['employee_stage']]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee stage updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Employee stage updated successfully.');
    }

    private function authorizeHrms(string $permission): void
    {
        abort_unless(
            app(AccessService::class)->allows(auth()->user(), $permission, [
                'tenant_id' => auth()->user()?->tenant_id,
            ]),
            403
        );
    }
}

