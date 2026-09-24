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

        if ($request->filled('convert_offer_id')) {
            $data['convertOffer'] = \App\Domains\HRMS\Models\JobOffer::with(['application.candidate', 'department', 'designation'])->find($request->convert_offer_id);
        }

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
        $required = ['full_name', 'company_id', 'department_id', 'designation_id', 'date_of_joining', 'gender'];
        $colIndex = [];
        foreach (array_merge($required, ['employee_id', 'personal_email']) as $col) {
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
                'personal_email'   => $colIndex['personal_email'] !== false ? trim($row[$colIndex['personal_email']] ?? '') : null,
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
                    'nullable', 'email', 'max:255',
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

        $userIdInput = $request->input('user_id');
        if (empty($userIdInput) || $userIdInput === 'auto_create' || $userIdInput === 'new') {
            if ($request->filled('office_email')) {
                $existingUser = \App\Models\User::where('email', $request->office_email)->first();
                if ($existingUser) {
                    $request->merge(['user_id' => $existingUser->id]);
                } else {
                    $newUser = \App\Models\User::create([
                        'tenant_id'     => auth()->user()?->tenant_id ?? $request->input('tenant_id'),
                        'company_id'    => $request->input('company_id'),
                        'branch_id'     => $request->input('branch_id'),
                        'department_id' => $request->input('department_id'),
                        'role_id'       => $request->input('role_id'),
                        'name'          => $request->input('full_name'),
                        'email'         => $request->input('office_email'),
                        'password'      => \Illuminate\Support\Facades\Hash::make('12345678'),
                    ]);
                    $request->merge(['user_id' => $newUser->id]);
                }
            }
        } elseif ($request->filled('user_id')) {
            $targetUser = \App\Models\User::find($request->user_id);
            if ($targetUser) {
                if (!$request->filled('full_name')) {
                    $request->merge(['full_name' => $targetUser->name]);
                }
                if (!$request->filled('personal_email')) {
                    $request->merge(['personal_email' => $targetUser->email]);
                }
                $request->merge(['office_email' => $targetUser->email]);
            }
        }

        $validated = $this->validatePayload($request);
        $validated = $this->normalizeHierarchy($validated);

        $employee = $this->employeeRepository->storeEmployee($validated, $request);

        // Process Candidate Offer Conversion link if submitted from recruitment workflow
        if ($request->filled('convert_offer_id')) {
            $offer = \App\Domains\HRMS\Models\JobOffer::find($request->convert_offer_id);
            if ($offer) {
                $offer->update(['converted_employee_id' => $employee->id]);
                $application = $offer->application;
                if ($application) {
                    $candidateObj = $application->candidate;
                    if ($candidateObj) {
                        $candidateObj->update(['status' => 'hired']);
                        if (empty($employee->resume_path) && !empty($candidateObj->resume_path)) {
                            $employee->update(['resume_path' => $candidateObj->resume_path]);
                        }
                    }
                    if ($application->fresh()->current_stage !== 'hired') {
                        $application->update([
                            'current_stage' => 'hired',
                            'stage_updated_at' => now(),
                        ]);
                        $req = $application->requisition;
                        if ($req && $req->vacancies > 0) {
                            $req->decrement('vacancies');
                            if ($req->fresh()->vacancies === 0) {
                                $req->update(['status' => 'closed']);
                            }
                        }
                    }
                }
            }
        }

        // Dispatch Welcome Email with Login Credentials to Employee
        try {
            $toEmail = $employee->personal_email ?: ($employee->office_email ?: $employee->user?->email);
            if (!empty($toEmail)) {
                $loginUrl = route('login');
                $companyName = config('app.name', 'Our Company');
                $welcomeHtml = "
                    <div style='font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; padding: 20px; background-color: #f8fafc;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;'>
                            <div style='background-color: #2563eb; padding: 20px; text-align: center; color: #ffffff;'>
                                <h2 style='margin: 0; font-size: 20px;'>Welcome to {$companyName}! 🎉</h2>
                            </div>
                            <div style='padding: 24px;'>
                                <p>Dear <strong>{$employee->full_name}</strong>,</p>
                                <p>We are delighted to welcome you to the team as <strong>" . ($employee->designation?->name ?? 'Employee') . "</strong>!</p>
                                <p>Your Employee Profile and ESS Self-Service Portal account have been set up. Here are your account login details:</p>
                                <div style='background-color: #f1f5f9; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                                    <p style='margin: 0 0 8px 0;'><strong>Portal Link:</strong> <a href='{$loginUrl}' style='color: #2563eb;'>{$loginUrl}</a></p>
                                    <p style='margin: 0 0 8px 0;'><strong>Username / Email:</strong> <code>{$toEmail}</code></p>
                                    <p style='margin: 0;'><strong>Initial Password:</strong> <code>12345678</code></p>
                                </div>
                                <p>Please log in to your Employee Portal and update your password under Account Settings upon first login.</p>
                                <div style='text-align: center; margin-top: 25px;'>
                                    <a href='{$loginUrl}' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block;'>Login to Employee Portal</a>
                                </div>
                                <br>
                                <p style='color: #64748b; font-size: 13px;'>If you have any questions, please contact the HR Department.</p>
                            </div>
                        </div>
                    </div>
                ";

                /** @var \App\Services\EmailService $emailService */
                $emailService = app(\App\Services\EmailService::class);
                $emailService->sendEmail([
                    'to'         => $toEmail,
                    'subject'    => "Welcome to {$companyName} — Your Employee Login Credentials",
                    'body_html'  => $welcomeHtml,
                    'account_id' => $request->account_id,
                ]);
            }
        } catch (\Throwable $emEx) {
            \Illuminate\Support\Facades\Log::error("Failed to send Welcome Email to employee: " . $emEx->getMessage());
        }

        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.employee.onboarded', [
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_id ?? ('EMP-' . $employee->id),
            'designation'   => $employee->designation?->name ?? 'Staff',
            'department'    => $employee->department?->name ?? 'General',
            'joining_date'  => $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->format('d M Y') : now()->format('d M Y'),
        ]);

        return redirect()
            ->route('hrms.employees.index')
            ->with('success', "🎉 Employee {$employee->full_name} created successfully and Welcome Email dispatched!");
    }

    public function show(Request $request, Employee $employee): View
    {
        $authUser = auth()->user();
        $isOwnProfile = $authUser && ($authUser->employee?->id === $employee->id || \App\Domains\HRMS\Models\Employee::resolveForUser($authUser)?->id === $employee->id);

        if (!$isOwnProfile) {
            $this->authorizeHrms('hrms.employees.view');
        }

        $data = $this->employeeRepository->getProfileData($employee, $request->all());

        return view('modules.hrms.employees.show', $data);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $authUser = auth()->user();
        $isHrOrAdmin = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', [
            'tenant_id' => $authUser->tenant_id,
        ]);
        $isOwnProfile = $authUser && (($authUser->employee?->id == $employee->id) || (\App\Domains\HRMS\Models\Employee::resolveForUser($authUser)?->id == $employee->id));

        if (!$isHrOrAdmin && !$isOwnProfile) {
            $this->authorizeHrms('hrms.employees.update');
        }

        if (!$isHrOrAdmin || $isOwnProfile) {
            // Self-profile edits must never change official company fields (role, department, designation, salary, manager, etc.)
            $request->offsetUnset('role_id');
            $request->query->remove('role_id');

            $request->merge([
                'employee_id'          => $employee->employee_id,
                'user_id'              => $employee->user_id,
                'company_id'           => $employee->company_id,
                'department_id'        => $employee->department_id,
                'designation_id'       => $employee->designation_id,
                'reporting_manager_id' => $employee->reporting_manager_id,
                'pay_group_id'         => $employee->pay_group_id,
                'salary_structure_id'  => $employee->salary_structure_id,
                'office_email'         => $employee->office_email,
                'date_of_joining'      => $employee->date_of_joining ? $employee->date_of_joining->format('Y-m-d') : null,
                'gender'               => $employee->gender,
                'full_name'            => $employee->full_name,
                'job_title'            => $employee->job_title,
                'status'               => $employee->status,
            ]);
        }

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
            ->back()
            ->with('success', 'Profile updated successfully.');
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
            // role_id is written straight to users.role_id, so only roles the
            // acting user may hand out are accepted (never super_admin for a non-super-admin).
            'role_id' => ['nullable', Rule::in(auth()->check()
                ? app(AccessService::class)->assignableRoles(auth()->user(), $tenantId)->pluck('id')->all()
                : [])],

            'title' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'personal_email' => [
                'nullable', 'email', 'max:255',
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

            $createdDoc = \App\Domains\HRMS\Models\Document::create([
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

            $documentName = $documentMaster->name;
            $catName = $documentMaster->category?->name ?? 'General';
        }

        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.uploaded', [
            'employee_name' => $employee->full_name,
            'document_type' => $documentName ?? ($file->getClientOriginalName()),
            'category_name' => $catName ?? 'General Documents',
            'uploaded_at'   => now()->format('d M Y, h:i A'),
        ]);

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        $user = auth()->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);

        if ($user && !$workflowService->canDeleteDocument($user, $document)) {
            return redirect()->back()->with('error', 'Self-deletion is prohibited. You cannot delete documents from your own employee profile.');
        }

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

        $emp = $document->documentable;
        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.approved', [
            'employee_name' => $emp?->full_name ?? 'Employee',
            'document_type' => $document->name ?? 'Document',
            'approved_by'   => auth()->user()?->name ?? 'HR Department',
            'date'          => now()->format('d M Y'),
        ]);

        return redirect()->back()->with('success', 'Document approved successfully.');
    }

    public function rejectDocument(\App\Domains\HRMS\Models\Document $document): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        $document->update([
            'status' => 'rejected',
        ]);

        $emp = $document->documentable;
        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.rejected', [
            'employee_name' => $emp?->full_name ?? 'Employee',
            'document_type' => $document->name ?? 'Document',
            'reason'        => request('reason', 'Document rejected upon verification'),
            'rejected_by'   => auth()->user()?->name ?? 'HR Department',
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

        $emp = $document->documentable;
        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.signed', [
            'employee_name'  => $emp?->full_name ?? (auth()->user()?->name ?? 'Employee'),
            'document_title' => $document->name ?? 'Document',
            'signed_at'      => now()->format('d M Y, h:i A'),
        ]);

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

    public function downloadResume(Employee $employee)
    {
        if ($employee->user_id !== auth()->id()) {
            $this->authorizeHrms('hrms.employees.view');
        }

        if (!$employee->resume_path) {
            abort(404, 'Employee resume not found.');
        }

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($employee->resume_path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->response($employee->resume_path);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($employee->resume_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->response($employee->resume_path);
        }

        abort(404, 'Resume file missing.');
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

