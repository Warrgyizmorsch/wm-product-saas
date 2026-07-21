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
use App\Domains\HRMS\Helpers\XlsxHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized access. Your user role does not have hr.settings.manage permission.', 403);
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

        $query = Employee::query()
            ->with(['company', 'businessUnit', 'branch', 'department', 'designation', 'payGroup', 'salaryStructure', 'leavePlan']);

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

    public function showEmployee(Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
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
                'component_name' => $item->component->name,
                'component_code' => $item->component->code,
                'type'           => $item->component->type,
                'amount'         => $amount,
            ];

            if ($item->component->type === 'earning') {
                $totalOtherEarnings += $amount;
            }
        }

        if ($balancingItem) {
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
            'payGroup', 'leavePlan', 'documents.requestedBy', 'employmentHistories'
        ]);

        $adhocComponents = EmployeeAdhocComponent::where('employee_id', $employee->id)->with('component')->get();
        $penalties       = EmployeePenalty::where('employee_id', $employee->id)->get();

        return $this->sendSuccess([
            'employee'            => $employee,
            'salary_structure'    => $salaryStructure,
            'computed_components' => array_values($computedComponents),
            'adhoc_components'    => $adhocComponents,
            'penalties'           => $penalties,
        ], 'Employee profile loaded successfully');
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        try {
            $validated = $this->validatePayload($request);
            $validated = $this->normalizeHierarchy($validated);
            $validated['status'] = $request->input('status', '1') === '1' || $request->input('status') === 'true' || $request->input('status') === true;

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('employees', 'public');
            }

            $employee = Employee::create($validated);

            return $this->sendSuccess($employee->load(['company', 'department', 'designation']), 'Employee created successfully', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', 422, $e->errors());
        }
    }

    public function updateEmployee(Request $request, Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
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
                $validated['photo'] = $request->file('photo')->store('employees', 'public');
            } else {
                unset($validated['photo']);
            }

            $employee->update($validated);

            $newPlanId = $employee->leave_plan_id;
            if ($oldPlanId != $newPlanId) {
                $action = $request->input('leave_transition_action', 'transfer');
                $unusedAction = $request->input('leave_transition_unused', 'carry');
                $employee->migrateToLeavePlan($oldPlanId, $newPlanId, $action, $unusedAction);
            }

            return $this->sendSuccess($employee->load(['company', 'department', 'designation']), 'Employee updated successfully');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', 422, $e->errors());
        }
    }

    public function destroyEmployee(Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee->delete();

        return $this->sendSuccess(null, 'Employee deleted successfully');
    }

    // ==========================================
    // 2. ADHOC SALARY COMPONENTS APIs
    // ==========================================

    public function storeAdhocComponent(Request $request, Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'amount'              => 'required|numeric|min:0',
            'payroll_month'       => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'             => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status']      = 'pending';

        $adhoc = EmployeeAdhocComponent::create($validated);

        return $this->sendSuccess($adhoc->load('component'), 'Ad-hoc salary component added successfully', 201);
    }

    public function destroyAdhocComponent(EmployeeAdhocComponent $adhocComponent): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $adhocComponent->delete();

        return $this->sendSuccess(null, 'Ad-hoc salary component deleted successfully');
    }

    // ==========================================
    // 3. ATTENDANCE PENALTIES APIs
    // ==========================================

    public function storePenalty(Request $request, Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'date'           => 'required|date',
            'rule_type'      => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'payroll_month'  => 'required|regex:/^\d{4}-\d{2}$/',
            'remarks'        => 'nullable|string|max:500',
        ]);

        $validated['employee_id'] = $employee->id;
        $validated['status']      = 'pending';

        $penalty = EmployeePenalty::create($validated);

        return $this->sendSuccess($penalty, 'Attendance penalty instance logged successfully', 201);
    }

    public function destroyPenalty(EmployeePenalty $penalty): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $penalty->delete();

        return $this->sendSuccess(null, 'Attendance penalty instance deleted successfully');
    }

    // ==========================================
    // 4. EMPLOYMENT HISTORIES APIs
    // ==========================================

    public function storeEmploymentHistory(Request $request, Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
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

    public function destroyEmploymentHistory(Employee $employee, EmployeeEmploymentHistory $history): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $history->delete();

        return $this->sendSuccess(null, 'Employment history record deleted successfully');
    }

    // ==========================================
    // 5. IMPORT / EXPORT APIs
    // ==========================================

    public function export(): mixed
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $headers = [
            'Employee ID', 'Full Name', 'Personal Email', 'Office Email',
            'Mobile Number', 'Gender', 'Marital Status', 'Employment Type',
            'Date of Joining', 'Date of Birth', 'Current Salary', 'Experience (Years)',
            'Qualification', 'Company Name', 'Department Name', 'Designation Name', 'Status'
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

    public function import(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $filePath = $request->file('file')->getRealPath();
            $rows = XlsxHelper::import($filePath);

            if (empty($rows)) {
                return $this->sendError('The Excel file is empty.', 422);
            }

            $headers = array_shift($rows);
            $headers = array_map(function ($h) {
                $h = strtolower(trim(preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $h)));
                $h = str_replace([' ', '-'], '_', $h);
                $h = preg_replace('/[^a-z0-9_]/', '', $h);
                if ($h === 'full_name' || $h === 'fullname' || $h === 'name') return 'full_name';
                if ($h === 'personal_email' || $h === 'email') return 'personal_email';
                if ($h === 'mobile_number' || $h === 'personal_mobile_number' || $h === 'phone_number' || $h === 'phone' || $h === 'mobile') return 'personal_mobile_number';
                if ($h === 'experience_years' || $h === 'experience') return 'experience';
                return $h;
            }, $headers);

            $headerMap = array_flip($headers);

            if (!isset($headerMap['full_name'])) {
                return $this->sendError("Required column 'full_name' is missing in the Excel file.", 422);
            }

            $importedCount = 0;
            $errors = [];
            $rowNumber = 1;

            DB::beginTransaction();
            foreach ($rows as $row) {
                $rowNumber++;
                if (empty(array_filter($row))) continue;
                if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');

                $data = [];
                foreach ($headerMap as $field => $index) {
                    $data[$field] = isset($row[$index]) && $row[$index] !== '' ? trim($row[$index]) : null;
                }

                $companyId = null;
                if (!empty($data['company_name'])) {
                    $company = Company::where('company_name', 'like', $data['company_name'])->first() ?: Company::first();
                    $companyId = $company?->id;
                } else {
                    $company = Company::first();
                    $companyId = $company?->id;
                }

                if (!$companyId) {
                    $errors[] = "Row {$rowNumber}: No valid company found.";
                    continue;
                }

                $departmentId = null;
                if (!empty($data['department_name'])) {
                    $dept = Department::where('name', 'like', $data['department_name'])->where('company_id', $companyId)->first();
                    if (!$dept) {
                        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $data['department_name']);
                        $deptCode = strtoupper(substr($cleanName, 0, 10)) ?: 'DEPT-' . rand(100, 999);
                        $originalCode = $deptCode;
                        $counter = 1;
                        while (Department::where('code', $deptCode)->exists()) {
                            $deptCode = substr($originalCode, 0, 7) . $counter;
                            $counter++;
                        }
                        $dept = Department::create(['company_id' => $companyId, 'name' => $data['department_name'], 'code' => $deptCode, 'status' => true]);
                    }
                    $departmentId = $dept->id;
                }

                $designationId = null;
                if (!empty($data['designation_name'])) {
                    $desg = Designation::where('name', 'like', $data['designation_name']);
                    if ($departmentId) $desg = $desg->where('department_id', $departmentId);
                    $desg = $desg->first();
                    if (!$desg) {
                        $desg = Designation::create(['department_id' => $departmentId, 'name' => $data['designation_name'], 'status' => true]);
                    }
                    $designationId = $desg->id;
                }

                $employeeData = [
                    'company_id'             => $companyId,
                    'department_id'          => $departmentId,
                    'designation_id'         => $designationId,
                    'full_name'              => $data['full_name'],
                    'personal_email'         => $data['personal_email'] ?? null,
                    'office_email'           => $data['office_email'] ?? null,
                    'personal_mobile_number' => $data['personal_mobile_number'] ?? null,
                    'gender'                 => isset($data['gender']) ? strtolower($data['gender']) : 'male',
                    'marital_status'         => isset($data['marital_status']) ? strtolower($data['marital_status']) : 'single',
                    'employment_type'        => isset($data['employment_type']) ? strtolower($data['employment_type']) : 'full-time',
                    'current_salary'         => isset($data['current_salary']) ? floatval($data['current_salary']) : 0,
                    'experience'             => isset($data['experience']) ? floatval($data['experience']) : 0,
                    'qualification'          => $data['qualification'] ?? null,
                    'status'                 => true
                ];

                if (!empty($data['date_of_joining'])) {
                    try { $employeeData['date_of_joining'] = \Carbon\Carbon::parse($data['date_of_joining'])->format('Y-m-d'); }
                    catch (\Exception $e) { $employeeData['date_of_joining'] = now()->format('Y-m-d'); }
                } else {
                    $employeeData['date_of_joining'] = now()->format('Y-m-d');
                }

                if (!empty($data['date_of_birth'])) {
                    try { $employeeData['date_of_birth'] = \Carbon\Carbon::parse($data['date_of_birth'])->format('Y-m-d'); }
                    catch (\Exception $e) {}
                }

                Employee::create($employeeData);
                $importedCount++;
            }

            DB::commit();

            return $this->sendSuccess([
                'imported_count' => $importedCount,
                'warnings'       => $errors,
            ], "{$importedCount} employees imported successfully");

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error reading Excel file: ' . $e->getMessage(), 500);
        }
    }

    // ==========================================
    // PRIVATE VALIDATION & HIERARCHY HELPERS
    // ==========================================

    private function validatePayload(Request $request, ?Employee $employee = null): array
    {
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
            'employee_id'                 => ['nullable', 'string', 'max:255', Rule::unique('employees', 'employee_id')->ignore($employee?->id)],
            'full_name'                   => ['required', 'string', 'max:255'],
            'nick_name'                   => ['nullable', 'string', 'max:255'],
            'blood_group'                 => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'employee_stage'              => ['nullable', 'string', 'max:255'],
            'job_title'                   => ['required', 'string', 'max:255'],
            'role'                        => ['nullable', 'string', 'max:255'],
            'employment_type'             => ['nullable', 'string', 'max:255'],
            'date_of_joining'             => ['required', 'date'],
            'office'                      => ['nullable', 'string', 'max:255'],
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
            'personal_mobile_number'      => ['nullable', 'string', 'max:20'],
            'home_phone'                  => ['nullable', 'string', 'max:20'],
            'personal_email'              => ['nullable', 'email', 'max:255', Rule::unique('employees', 'personal_email')->ignore($employee?->id)],
            'experience'                  => ['nullable', 'numeric', 'min:0'],
            'source_of_hire'              => ['nullable', 'string', 'max:255'],
            'skill_set'                   => ['nullable', 'string'],
            'current_salary'              => ['nullable', 'numeric', 'min:0'],
            'qualification'               => ['nullable', 'string', 'max:255'],
            'reporting_manager_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'date_of_birth'               => ['nullable', 'date'],
            'probation_end_date'          => ['nullable', 'date'],
            'confirmation_date'           => ['nullable', 'date'],
            'shift_id'                    => ['nullable', 'integer', 'exists:production_shifts,id'],
            'office_email'                => ['nullable', 'email', 'max:255', Rule::unique('employees', 'office_email')->ignore($employee?->id)],
            'bank_name'                   => ['nullable', 'string', 'max:255'],
            'account_number'              => ['nullable', 'string', 'max:255'],
            'ifsc_code'                   => ['nullable', 'string', 'max:50'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:255'],
            'emergency_contact_number'    => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation'  => ['nullable', 'string', 'max:100'],
            'status'                      => ['required'],
            'weekly_pattern'              => ['nullable', 'array'],
            'weekly_pattern.*'            => ['nullable', 'string'],
        ]);
    }

    private function normalizeHierarchy(array $validated): array
    {
        $company      = Company::findOrFail($validated['company_id']);
        $businessUnit = !empty($validated['business_unit_id']) ? BusinessUnit::findOrFail($validated['business_unit_id']) : null;
        $branch       = !empty($validated['branch_id']) ? Branch::findOrFail($validated['branch_id']) : null;
        $department   = Department::findOrFail($validated['department_id']);
        $designation  = Designation::findOrFail($validated['designation_id']);
        $payGroup     = !empty($validated['pay_group_id']) ? PayGroup::findOrFail($validated['pay_group_id']) : null;
        $leavePlan    = !empty($validated['leave_plan_id']) ? LeavePlan::findOrFail($validated['leave_plan_id']) : null;

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
