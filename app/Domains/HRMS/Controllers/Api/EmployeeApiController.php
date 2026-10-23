<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeEmploymentHistory;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\EmployeeAdhocComponent;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class EmployeeApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.employees.manage')
            || $user->hasHrPermission('hrms.employees.manage');
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)
            ->where('tenant_id', tenant_id() ?? $user->tenant_id ?? 1)
            ->first()
            ?? Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
    }

    /**
     * Null-safe authorization check supporting Web Sessions & HTTP Basic Auth.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        return null;
    }

    /**
     * GET /api/hrms/employees/summary
     * Get reference collections & dropdown enums.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess([
            'total_employees'      => Employee::count(),
            'active_employees'     => Employee::where('status', true)->count(),
            'companies'            => Company::where('status', true)->orderBy('company_name')->get(),
            'business_units'       => BusinessUnit::where('status', true)->orderBy('name')->get(),
            'branches'             => Branch::where('status', true)->orderBy('name')->get(),
            'departments'          => Department::where('status', true)->orderBy('name')->get(),
            'designations'         => Designation::where('status', true)->orderBy('name')->get(),
            'pay_groups'           => PayGroup::where('status', true)->orderBy('name')->get(),
            'leave_plans'          => LeavePlan::where('status', true)->orderBy('name')->get(),
            'employment_types'     => ['Full Time', 'Part Time', 'Contract', 'Intern', 'Consultant'],
            'employee_stages'      => ['Draft', 'Probation', 'Confirmed', 'On Notice', 'Relieved'],
            'genders'               => ['Male', 'Female', 'Other'],
            'marital_statuses'     => ['Single', 'Married', 'Divorced', 'Widowed'],
            'blood_groups'         => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'diet_preferences'     => ['Veg', 'Non Veg', 'Vegan'],
            'unmapped_users'       => \App\Models\User::query()
                ->where('tenant_id', auth()->user()?->tenant_id)
                ->whereDoesntHave('employee')
                ->orderBy('name')
                ->get(),
        ], 'Employee metadata & dropdown options loaded successfully');
    }

    // ==========================================
    // 1. EMPLOYEE PROFILE CRUD APIs
    // ==========================================

    public function indexEmployees(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search       = trim((string) $request->string('search'));
        $companyId    = $request->integer('company_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $status       = $request->filled('status') ? $request->string('status')->value() : null;
        $sort         = $request->string('sort')->value() ?: 'name_asc';

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        $query = Employee::query()
            ->with(['company', 'businessUnit', 'branch', 'department', 'designation', 'payGroup', 'salaryStructure', 'leavePlan', 'defaultShift', 'shift', 'user']);

        if (!$isHrAdmin) {
            if (!$authEmployee) {
                return $this->sendError('Employee profile not found.', 404);
            }
            $query->where(function ($q) use ($authEmployee) {
                $q->where('id', $authEmployee->id)
                  ->orWhere('reporting_manager_id', $authEmployee->id);
            });
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('personal_email', 'like', "%{$search}%")
                    ->orWhere('personal_mobile_number', 'like', "%{$search}%");
            });
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'id_asc':      $query->orderBy('employee_id', 'asc'); break;
            case 'id_desc':     $query->orderBy('employee_id', 'desc'); break;
            case 'name_desc':   $query->orderBy('full_name', 'desc'); break;
            case 'doj_asc':     $query->orderBy('date_of_joining', 'asc'); break;
            case 'doj_desc':    $query->orderBy('date_of_joining', 'desc'); break;
            case 'salary_asc':  $query->orderBy('current_salary', 'asc'); break;
            case 'salary_desc': $query->orderBy('current_salary', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('full_name', 'asc'); break;
        }

        $employees = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($employees, 'Employees list retrieved successfully');
    }

    public function showEmployee(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $authEmployee) {
            if ((int)$employee->id !== (int)$authEmployee->id && (int)$employee->reporting_manager_id !== (int)$authEmployee->id) {
                return $this->sendError('Unauthorized action. You can only view your own employee profile.', 403);
            }
        }

        // Dynamically resolve Salary Structure slab based on current_salary and pay_group_id
        $salaryStructure = null;
        if ($employee->pay_group_id) {
            $salaryStructure = SalaryStructure::query()
                ->where('pay_group_id', $employee->pay_group_id)
                ->where('min_ctc', '<=', $employee->current_salary)
                ->where('max_ctc', '>=', $employee->current_salary)
                ->where('status', true)
                ->first();
        }

        // Calculate components breakdown
        $rawItems = $salaryStructure ? $salaryStructure->items()->with('component')->get() : collect();
        $items = $rawItems->filter(function($i) {
            if (!$i->component) {
                return false;
            }
            if (in_array($i->component->code, ['TE_RECOVERY', 'ADV_RECOVERY'])) {
                if (!$i->component->is_adhoc) {
                    $i->component->update(['is_adhoc' => true]);
                }
                return false;
            }
            return !$i->component->is_adhoc;
        });

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
            if (!$item->component || $item->component->is_adhoc || in_array($item->component->code, ['TE_RECOVERY', 'ADV_RECOVERY'])) {
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
                'component_name' => $item->component->name,
                'component_code' => $item->component->code,
                'type'           => $item->component->type,
                'amount'         => $amount,
            ];

            if ($item->component->type === 'earning') {
                $totalOtherEarnings += $amount;
            }
        }

        if ($balancingItem && $balancingItem->component && !$balancingItem->component->is_adhoc && !in_array($balancingItem->component->code, ['TE_RECOVERY', 'ADV_RECOVERY'])) {
            $balancingAmount = max(0.0, $ctc - $totalOtherEarnings);
            $computedComponents[$balancingItem->id] = [
                'component_name' => $balancingItem->component->name,
                'component_code' => $balancingItem->component->code,
                'type'           => $balancingItem->component->type,
                'amount'         => $balancingAmount,
            ];
        }

        $employee->load([
            'company', 'businessUnit', 'branch', 'department', 'designation',
            'payGroup', 'leavePlan', 'defaultShift', 'shift', 'documents.requestedBy', 'employmentHistories', 'user'
        ]);

        $shiftObj = $employee->defaultShift ?: $employee->shift;
        $employeeData = $employee->toArray();
        $employeeData['shift'] = $shiftObj ? [
            'id'            => $shiftObj->id,
            'name'          => $shiftObj->name ?? '',
            'code'          => $shiftObj->code ?? '',
            'start_time'    => $shiftObj->start_time ?? '',
            'end_time'      => $shiftObj->end_time ?? '',
            'break_minutes' => (int) ($shiftObj->break_minutes ?? 0),
        ] : null;

        $adhocComponents = EmployeeAdhocComponent::where('employee_id', $employee->id)->where('status', 'pending')->with('component')->get();
        $penalties       = EmployeePenalty::where('employee_id', $employee->id)->get();

        return $this->sendSuccess([
            'employee'            => $employeeData,
            'salary_structure'    => $salaryStructure,
            'computed_components' => array_values($computedComponents),
            'adhoc_components'    => $adhocComponents,
            'penalties'           => $penalties,
        ], 'Employee profile loaded successfully');
    }

    /**
     * GET /api/hrms/employees/me
     * Load the employee profile linked to the authenticated token.
     */
    public function myProfile(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->sendError('Employee profile not found for the authenticated user.', 404);
        }

        return $this->showEmployee($employee->id);
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to create employee profile.', 403);
        }

        try {
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
            $validated['status'] = $request->input('status', '1') === '1' || $request->input('status') === 'true' || $request->input('status') === true;

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->storeTenantFile('employees', 'public');
            }

            $employee = Employee::create($validated);

            if ($request->filled('role_id') && $employee->user) {
                $employee->user->update(['role_id' => $request->role_id]);
            }

            return $this->sendSuccess($employee->load(['company', 'department', 'designation']), 'Employee created successfully', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', 422, $e->errors());
        }
    }

    public function updateEmployee(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update employee profile.', 403);
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        try {
            $oldPlanId = $employee->leave_plan_id;

            $validated = $this->validatePayload($request, $employee);
            $validated = $this->normalizeHierarchy($validated);
            $validated['status'] = $request->input('status', '1') === '1' || $request->input('status') === 'true' || $request->input('status') === true;

            $newPlanId = !empty($validated['leave_plan_id']) ? (int)$validated['leave_plan_id'] : null;
            if ($newPlanId !== null && (int)$oldPlanId !== $newPlanId) {
                $hasPending = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->exists();
                if ($hasPending) {
                    return $this->sendError("Cannot change leave plan for {$employee->full_name}. Please approve or reject all pending leave requests first.", 422);
                }

                $hasPendingEncashment = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->exists();
                if ($hasPendingEncashment) {
                    return $this->sendError("Cannot change leave plan for {$employee->full_name}. Please approve or reject all pending encashment requests first.", 422);
                }
            }

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->storeTenantFile('employees', 'public');
            } else {
                unset($validated['photo']);
            }

            $employee->update($validated);

            if ($request->filled('role_id') && $employee->user) {
                $employee->user->update(['role_id' => $request->role_id]);
            }

            $newPlanId = $employee->leave_plan_id;
            if ($oldPlanId != $newPlanId) {
                $action = $request->input('leave_transition_action', 'transfer');
                $unusedAction = $request->input('leave_transition_unused', 'carry');
                $employee->migrateToLeavePlan($oldPlanId, $newPlanId, $action, $unusedAction);
            }

            if ($newPlanId !== null && (int)$oldPlanId !== $newPlanId) {
                app(\App\Domains\HRMS\Services\LeaveBalanceService::class)->recalculateBalancesForEmployee($employee->fresh());
            }

            return $this->sendSuccess($employee->load(['company', 'department', 'designation']), 'Employee updated successfully');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', 422, $e->errors());
        }
    }

    public function destroyEmployee(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete employee profile.', 403);
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $employee->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Employee deleted successfully');
    }

    // ==========================================
    // 2. ADHOC SALARY COMPONENTS APIs
    // ==========================================

    public function storeAdhocComponent(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'amount'              => 'required|numeric|min:0',
            'payroll_month'       => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'             => 'nullable|string|max:500',
        ]);

        // Enforce Pay Group Variable / Ad-hoc Inputs Lock Day
        $employee->loadMissing('payGroup');
        if ($employee->payGroup && is_array($employee->payGroup->payroll_rules)) {
            $lockDay = (int) ($employee->payGroup->payroll_rules['variable_lock_day'] ?? 25);
            $payrollMonth = $validated['payroll_month'];
            $currentMonth = \Carbon\Carbon::now()->format('Y-m');
            if ($payrollMonth === $currentMonth && \Carbon\Carbon::now()->day > $lockDay) {
                return $this->sendError("Ad-hoc / Variable inputs for {$payrollMonth} are locked after day {$lockDay} of the month.", 422);
            }
        }

        $validated['employee_id'] = $employee->id;
        $validated['status']      = 'pending';

        $adhoc = EmployeeAdhocComponent::create($validated);

        return $this->sendSuccess($adhoc->load('component'), 'Ad-hoc salary component added successfully', 201);
    }

    public function destroyAdhocComponent(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $adhocComponent = EmployeeAdhocComponent::find($id);
        if (!$adhocComponent) {
            return $this->sendError("Ad-hoc salary component with ID '{$id}' not found.", 404);
        }

        $adhocComponent->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Ad-hoc salary component deleted successfully');
    }

    // ==========================================
    // 3. ATTENDANCE PENALTIES APIs
    // ==========================================

    public function storePenalty(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'date'           => 'required|date',
            'rule_type'      => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'payroll_month'  => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'        => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['tenant_id']   = $employee->tenant_id ?? (auth()->user()?->tenant_id ?? 1);
        $validated['status']      = 'pending';

        $penalty = EmployeePenalty::create($validated);

        return $this->sendSuccess($penalty, 'Attendance penalty instance logged successfully', 201);
    }

    public function destroyPenalty(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $penalty = EmployeePenalty::find($id);
        if (!$penalty) {
            return $this->sendError("Penalty instance with ID '{$id}' not found.", 404);
        }

        $penalty->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Attendance penalty instance deleted successfully');
    }

    // ==========================================
    // 4. EMPLOYMENT HISTORIES APIs
    // ==========================================

    public function storeEmploymentHistory(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'company_name'    => 'required|string|max:255',
            'designation'     => 'required|string|max:255',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'job_description' => 'nullable|string|max:1000',
        ]);

        $history = $employee->employmentHistories()->create($validated);

        return $this->sendSuccess($history, 'Employment history record added successfully', 201);
    }

    public function destroyEmploymentHistory(mixed $employeeId, mixed $historyId): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $history = EmployeeEmploymentHistory::find($historyId);
        if (!$history) {
            return $this->sendError("Employment history record with ID '{$historyId}' not found.", 404);
        }

        $history->delete();

        return $this->sendSuccess(['id' => (int)$historyId], 'Employment history record deleted successfully');
    }

    // ==========================================
    // 4b. EMPLOYEE DOCUMENTS APIs
    // ==========================================

    public function uploadDocument(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $request->validate([
            'document_id'        => 'nullable|exists:documents,id',
            'document_master_id' => 'required_without:document_id|exists:document_masters,id',
            'file'               => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240', // Max 10MB
            'expiry_date'        => 'nullable|date',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $file = $request->file('file');

        if ($request->filled('document_id')) {
            $document = \App\Domains\HRMS\Models\Document::find($request->integer('document_id'));
            if (!$document) {
                return $this->sendError("Document with ID '{$request->document_id}' not found.", 404);
            }
            
            $expiryApplicable = $document->has_expiry;
            if ($document->document_master_id) {
                $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::find($document->document_master_id);
                if ($documentMaster) {
                    $expiryApplicable = $documentMaster->expiry_applicable;
                }
            }

            if ($expiryApplicable && !$request->filled('expiry_date')) {
                return $this->sendError('Expiry date is required for this document.', 422, [
                    'expiry_date' => ['Expiry date is required for this document.']
                ]);
            }

            $path = $file->storeTenantFile("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $approvalRequired = true;
            if ($document->document_master_id) {
                $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::find($document->document_master_id);
                if ($documentMaster) {
                    $approvalRequired = (bool) $documentMaster->approval_required;
                }
            }
            $status = $approvalRequired ? 'uploaded' : 'approved';

            $document->update([
                'file_name'       => $file->getClientOriginalName(),
                'file_path'       => $path,
                'file_type'       => $file->getClientMimeType(),
                'file_size'       => $file->getSize(),
                'expiry_date'     => $expiryApplicable && $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'status'          => $status,
                'requested_by_id' => auth()->id(),
            ]);
        } else {
            $documentMaster = \App\Domains\HRMS\Models\DocumentMaster::find($request->integer('document_master_id'));
            if (!$documentMaster) {
                return $this->sendError("Document template with ID '{$request->document_master_id}' not found.", 404);
            }
            
            if ($documentMaster->expiry_applicable && !$request->filled('expiry_date')) {
                return $this->sendError('Expiry date is required for this document template.', 422, [
                    'expiry_date' => ['Expiry date is required for this document template.']
                ]);
            }

            $path = $file->storeTenantFile("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $approvalRequired = (bool) $documentMaster->approval_required;
            $status = $approvalRequired ? 'uploaded' : 'approved';

            $document = \App\Domains\HRMS\Models\Document::create([
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
                'status'             => $status,
                'has_expiry'         => $documentMaster->expiry_applicable,
                'expiry_date'        => $documentMaster->expiry_applicable && $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'requested_by_id'    => auth()->id(),
            ]);
        }

        return $this->sendSuccess($document, 'Document uploaded successfully', 201);
    }

    public function destroyDocument(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document record with ID '{$id}' not found.", 404);
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Document record deleted successfully');
    }

    public function approveDocument(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document record with ID '{$id}' not found.", 404);
        }

        $document->update([
            'status' => 'approved',
        ]);

        return $this->sendSuccess($document, 'Document approved successfully');
    }

    public function rejectDocument(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document record with ID '{$id}' not found.", 404);
        }

        $document->update([
            'status' => 'rejected',
        ]);

        return $this->sendSuccess($document, 'Document rejected successfully');
    }

    public function updateDocumentStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document record with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $document->update([
            'status' => $validated['status'],
        ]);

        $msg = $validated['status'] === 'approved' ? 'Document approved successfully.' : 'Document marked as rejected.';

        return $this->sendSuccess($document, $msg);
    }

    // ==========================================
    // 5. IMPORT / EXPORT APIs
    // ==========================================

    public function export(): mixed
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!app(\App\Services\Access\AccessService::class)->allows(auth()->user(), 'hrms.employees.view', [
            'tenant_id' => auth()->user()?->tenant_id,
        ])) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $employees = Employee::with(['department', 'designation', 'company'])
            ->where('tenant_id', $tenantId)
            ->orderBy('full_name')
            ->get();

        $columns = [
            'Employee Code', 'Full Name', 'Personal Email', 'Office Email',
            'Department', 'Designation', 'Company', 'Date of Joining', 'Status',
        ];

        $callback = static function () use ($employees, $columns): void {
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

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=employees_export_' . date('Ymd_His') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    public function downloadTemplate(): mixed
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!app(\App\Services\Access\AccessService::class)->allows(auth()->user(), 'hrms.employees.create', [
            'tenant_id' => auth()->user()?->tenant_id,
        ])) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $columns = ['full_name', 'personal_email', 'employee_id', 'company_id', 'department_id', 'designation_id', 'date_of_joining', 'gender'];
        $callback = static function () use ($columns): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['Jane Doe', 'jane.doe@example.com', 'EMP-0001', '1', '1', '1', '2026-01-15', 'female']);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=employees_import_template.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!app(\App\Services\Access\AccessService::class)->allows(auth()->user(), 'hrms.employees.create', [
            'tenant_id' => auth()->user()?->tenant_id,
        ])) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return $this->sendError('Unable to read the uploaded CSV file.', 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $this->sendError('The uploaded file is empty.', 422);
        }

        $header = array_map(static fn ($value) => strtolower(trim((string) $value)), $header);
        $required = ['full_name', 'company_id', 'department_id', 'designation_id', 'date_of_joining', 'gender'];
        $optional = ['employee_id', 'personal_email'];
        $colIndex = [];
        foreach (array_merge($required, $optional) as $column) {
            $colIndex[$column] = array_search($column, $header, true);
        }

        foreach ($required as $column) {
            if ($colIndex[$column] === false) {
                fclose($handle);
                return $this->sendError("Invalid template. Missing required column \"{$column}\".", 422);
            }
        }

        $importedCount = 0;
        $warnings = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty($row) || count($row) < 2) {
                continue;
            }

            $rowData = [
                'tenant_id' => $tenantId,
                'full_name' => trim($row[$colIndex['full_name']] ?? ''),
                'personal_email' => $colIndex['personal_email'] !== false ? trim($row[$colIndex['personal_email']] ?? '') : null,
                'employee_id' => $colIndex['employee_id'] !== false ? trim($row[$colIndex['employee_id']] ?? '') : null,
                'company_id' => trim($row[$colIndex['company_id']] ?? ''),
                'department_id' => trim($row[$colIndex['department_id']] ?? ''),
                'designation_id' => trim($row[$colIndex['designation_id']] ?? ''),
                'date_of_joining' => trim($row[$colIndex['date_of_joining']] ?? ''),
                'gender' => trim($row[$colIndex['gender']] ?? ''),
                'status' => true,
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
                $warnings[] = 'Row ' . $rowNum . ': ' . implode(' ', $validator->errors()->all());
                continue;
            }

            Employee::create($rowData);
            $importedCount++;
        }

        fclose($handle);

        $message = "{$importedCount} employee(s) imported successfully.";
        if ($warnings) {
            $message .= ' ' . count($warnings) . ' row(s) skipped: ' . implode(' | ', array_slice($warnings, 0, 5));
        }

        return $this->sendSuccess([
            'imported_count' => $importedCount,
            'warnings' => $warnings,
        ], $message);
    }

    // ==========================================
    // PRIVATE VALIDATION & HIERARCHY HELPERS
    // ==========================================

    private function validatePayload(Request $request, ?Employee $employee = null): array
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return $request->validate([
            'company_id'                  => ['required', 'exists:companies,id'],
            'business_unit_id'            => ['nullable', 'exists:business_units,id'],
            'branch_id'                   => ['nullable', 'exists:branches,id'],
            'department_id'               => ['required', 'exists:departments,id'],
            'designation_id'              => ['required', 'exists:designations,id'],
            'pay_group_id'                => ['nullable', 'exists:pay_groups,id'],
            'leave_plan_id'               => ['nullable', 'exists:leave_plans,id'],
            'leave_transition_action'     => ['nullable', 'string', 'in:transfer,prorate'],
            'leave_transition_unused'     => ['nullable', 'string', 'in:carry,lapse'],
            'employee_id'                 => ['nullable', 'string', 'max:255', Rule::unique('employees', 'employee_id')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($employee?->id)],
            'user_id'                     => ['required', Rule::unique('employees', 'user_id')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($employee?->id)],
            // Written straight to users.role_id — only roles the acting user may hand out.
            'role_id'                     => ['nullable', Rule::in(auth()->check()
                ? app(\App\Services\Access\AccessService::class)->assignableRoles(auth()->user(), $tenantId)->pluck('id')->all()
                : [])],
            'full_name'                   => ['required', 'string', 'max:255'],
            'nick_name'                   => ['nullable', 'string', 'max:255'],
            'blood_group'                 => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'employee_stage'              => ['nullable', 'string', 'max:255'],
            'job_title'                   => ['required', 'string', 'max:255'],
            'role'                        => ['nullable', 'string', 'max:255'],
            'employment_type'             => ['nullable', 'string', 'max:255'],
            'date_of_joining'             => ['required', 'date'],
            'office'                      => ['nullable', 'string', 'in:office,wfh,onsite'],
            'wfh_latitude'                => ['nullable', 'numeric', 'between:-90,90'],
            'wfh_longitude'               => ['nullable', 'numeric', 'between:-180,180'],
            'gender'                      => ['required', Rule::in(['male', 'female', 'other', 'Male', 'Female', 'Other'])],
            'marital_status'              => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'Single', 'Married', 'Divorced', 'Widowed'])],
            'diet_preference'             => ['nullable', Rule::in(['veg', 'non veg', 'vegan', 'Veg', 'Non Veg', 'Vegan'])],
            'aadhaar_card_number'         => ['nullable', 'string', 'max:20', Rule::unique('employees', 'aadhaar_card_number')->ignore($employee?->id)],
            'pan_card_number'             => ['nullable', 'string', 'max:20', Rule::unique('employees', 'pan_card_number')->ignore($employee?->id)],
            'photo'                       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'present_address'             => ['nullable', 'string'],
            'permanent_address'           => ['nullable', 'string'],
            'city'                        => ['nullable', 'string', 'max:255'],
            'postal_code'                 => ['nullable', 'string', 'max:20'],
            'personal_mobile_number'      => ['nullable', 'string', 'max:20', 'regex:/^(?=.*[0-9])[0-9+().\s-]+$/'],
            'home_phone'                  => ['nullable', 'string', 'max:20', 'regex:/^(?=.*[0-9])[0-9+().\s-]+$/'],
            'personal_email'              => ['nullable', 'email', 'max:255', Rule::unique('employees', 'personal_email')->ignore($employee?->id)],
            'experience'                  => ['nullable', 'numeric', 'min:0'],
            'source_of_hire'              => ['nullable', 'string', 'max:255'],
            'skill_set'                   => ['nullable', 'string'],
            'current_salary'              => ['nullable', 'numeric', 'min:0'],
            'qualification'               => ['nullable', 'string', 'max:255'],
            'reporting_manager_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'date_of_birth'               => ['nullable', 'date', 'before_or_equal:today'],
            'probation_end_date'          => ['nullable', 'date'],
            'confirmation_date'           => ['nullable', 'date'],
            'shift_id'                    => ['nullable', 'integer', 'exists:production_shifts,id'],
            'office_email'                => ['nullable', 'email', 'max:255', Rule::unique('employees', 'office_email')->ignore($employee?->id)],
            'bank_name'                   => ['nullable', 'string', 'max:255'],
            'account_number'              => ['nullable', 'string', 'max:255'],
            'ifsc_code'                   => ['nullable', 'string', 'max:50'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:255'],
            'emergency_contact_number'    => ['nullable', 'string', 'max:50', 'regex:/^(?=.*[0-9])[0-9+().\s-]+$/'],
            'emergency_contact_relation'  => ['nullable', 'string', 'max:100'],
            'status'                      => ['required', 'boolean'],
            'weekly_pattern'              => ['nullable', 'array'],
            'weekly_pattern.*'            => ['nullable', 'string'],
        ]);
    }

    private function normalizeHierarchy(array $validated): array
    {
        $company      = Company::find($validated['company_id']);
        if (!$company) {
            $this->failHierarchy('company_id', 'The selected company does not exist.');
        }

        $businessUnit = !empty($validated['business_unit_id']) ? BusinessUnit::find($validated['business_unit_id']) : null;
        if (!empty($validated['business_unit_id']) && !$businessUnit) {
            $this->failHierarchy('business_unit_id', 'The selected business unit does not exist.');
        }

        $branch       = !empty($validated['branch_id']) ? Branch::find($validated['branch_id']) : null;
        if (!empty($validated['branch_id']) && !$branch) {
            $this->failHierarchy('branch_id', 'The selected branch does not exist.');
        }

        $department   = Department::find($validated['department_id']);
        if (!$department) {
            $this->failHierarchy('department_id', 'The selected department does not exist.');
        }

        $designation  = Designation::find($validated['designation_id']);
        if (!$designation) {
            $this->failHierarchy('designation_id', 'The selected designation does not exist.');
        }

        $payGroup     = !empty($validated['pay_group_id']) ? PayGroup::find($validated['pay_group_id']) : null;
        if (!empty($validated['pay_group_id']) && !$payGroup) {
            $this->failHierarchy('pay_group_id', 'The selected pay group does not exist.');
        }

        $leavePlan    = !empty($validated['leave_plan_id']) ? LeavePlan::find($validated['leave_plan_id']) : null;
        if (!empty($validated['leave_plan_id']) && !$leavePlan) {
            $this->failHierarchy('leave_plan_id', 'The selected leave plan does not exist.');
        }

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

        $departmentBusinessUnitId = $department->business_unit_id ?: ($department->branch?->business_unit_id ?? null);

        if ($departmentBusinessUnitId !== null) {
            if ($businessUnit === null) {
                $businessUnit = BusinessUnit::find($departmentBusinessUnitId);
            } elseif ((int) $departmentBusinessUnitId !== (int) $businessUnit->id) {
                $this->failHierarchy('department_id', 'The selected department does not belong to the chosen business unit.');
            }
        }

        $departmentBranchId = $department->branch_id;

        if ($departmentBranchId !== null) {
            if ($branch === null) {
                $branch = Branch::find($departmentBranchId);
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

        $validated['company_id']       = $company->id;
        $validated['business_unit_id'] = $businessUnit?->id;
        $validated['branch_id']        = $branch?->id;
        $validated['pay_group_id']     = $payGroup?->id;
        $validated['leave_plan_id']    = $leavePlan?->id;

        return $validated;
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($id);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $employee->update(['status' => $validated['status']]);

        return $this->sendSuccess($employee, 'Employee status updated successfully');
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
}
