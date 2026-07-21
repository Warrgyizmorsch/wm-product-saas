<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RosterApiController extends Controller
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
     * GET /api/hrms/roster/summary
     * Get summary metrics & reference lists for roster scheduling.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess([
            'total_shifts'    => ProductionShift::count(),
            'active_shifts'   => ProductionShift::where('active', true)->get(),
            'companies'       => Company::all(),
            'business_units'  => BusinessUnit::with('company')->get(),
            'branches'        => Branch::with('businessUnit')->get(),
            'departments'     => Department::with('company')->get(),
            'designations'    => Designation::all(),
        ], 'Roster summary retrieved successfully');
    }

    // ==========================================
    // 1. SHIFT MASTER DATA APIs
    // ==========================================

    public function indexShifts(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search    = trim((string) $request->string('search'));
        $status    = $request->filled('status') ? $request->string('status')->value() : null;
        $overtime  = $request->filled('overtime') ? $request->string('overtime')->value() : null;
        $companyId = $request->integer('company_id') ?: null;
        $sort      = $request->string('sort')->value() ?: 'name_asc';

        $query = ProductionShift::with('company');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('start_time', 'like', "%{$search}%")
                  ->orWhere('end_time', 'like', "%{$search}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('active', $status === '1' || $status === 'true');
        }
        if ($overtime !== null && $overtime !== '') {
            $query->where('overtime_allowed', $overtime === '1' || $overtime === 'true');
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        switch ($sort) {
            case 'name_desc':  $query->orderBy('name', 'desc'); break;
            case 'code_asc':   $query->orderBy('code', 'asc'); break;
            case 'code_desc':  $query->orderBy('code', 'desc'); break;
            case 'start_asc':  $query->orderBy('start_time', 'asc'); break;
            case 'start_desc': $query->orderBy('start_time', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $shifts = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($shifts, 'Production shifts retrieved successfully');
    }

    public function showShift(ProductionShift $shift): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($shift->load('company'), 'Production shift details loaded');
    }

    public function storeShift(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'             => 'required|max:255',
            'code'             => [
                'required',
                'max:50',
                Rule::unique('production_shifts', 'code')
                    ->where(function ($query) use ($request, $tenantId) {
                        return $query->where('tenant_id', $tenantId)
                            ->where('company_id', $request->company_id);
                    })
            ],
            'company_id'       => 'nullable|exists:companies,id',
            'start_time'       => 'required',
            'end_time'         => 'required',
            'break_minutes'    => 'required|integer|min:0',
            'overtime_allowed' => 'required|boolean',
            'active'           => 'required|boolean',
        ]);

        $shift = ProductionShift::create([
            'tenant_id'        => $tenantId,
            'company_id'       => $validated['company_id'] ?? null,
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'start_time'       => $validated['start_time'],
            'end_time'         => $validated['end_time'],
            'break_minutes'    => $validated['break_minutes'],
            'overtime_allowed' => $validated['overtime_allowed'],
            'active'           => $validated['active'],
        ]);

        return $this->sendSuccess($shift, 'Production shift created successfully', 201);
    }

    public function updateShift(Request $request, ProductionShift $shift): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name'             => 'required|max:255',
            'code'             => [
                'required',
                'max:50',
                Rule::unique('production_shifts', 'code')
                    ->where(function ($query) use ($request, $tenantId) {
                        return $query->where('tenant_id', $tenantId)
                            ->where('company_id', $request->company_id);
                    })
                    ->ignore($shift->id)
            ],
            'company_id'       => 'nullable|exists:companies,id',
            'start_time'       => 'required',
            'end_time'         => 'required',
            'break_minutes'    => 'required|integer|min:0',
            'overtime_allowed' => 'required|boolean',
            'active'           => 'required|boolean',
        ]);

        $shift->update([
            'company_id'       => $validated['company_id'] ?? null,
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'start_time'       => $validated['start_time'],
            'end_time'         => $validated['end_time'],
            'break_minutes'    => $validated['break_minutes'],
            'overtime_allowed' => $validated['overtime_allowed'],
            'active'           => $validated['active'],
        ]);

        return $this->sendSuccess($shift, 'Production shift updated successfully');
    }

    public function destroyShift(ProductionShift $shift): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $shift->delete();

        return $this->sendSuccess(null, 'Production shift deleted successfully');
    }

    // ==========================================
    // 2. SHIFT ROSTER MATRIX & ASSIGNMENT APIs
    // ==========================================

    public function matrix(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $companyId     = $request->integer('company_id') ?: null;
        $departmentId  = $request->integer('department_id') ?: null;
        $designationId = $request->integer('designation_id') ?: null;
        $search        = trim((string) $request->string('search'));
        $sortBy        = $request->string('sort', 'name-asc')->value();
        $daysCount     = $request->integer('days', 7);

        $startDateStr  = $request->string('start_date')->value();
        $startDate     = $startDateStr ? Carbon::parse($startDateStr) : Carbon::today();
        $endDate       = $startDate->copy()->addDays(max(1, $daysCount - 1));

        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        $query = Employee::query()->where('employees.status', true);

        if ($companyId) {
            $query->where('employees.company_id', $companyId);
        }
        if ($departmentId) {
            $query->where('employees.department_id', $departmentId);
        }
        if ($designationId) {
            $query->where('employees.designation_id', $designationId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employees.full_name', 'like', "%{$search}%")
                  ->orWhereHas('designation', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        switch ($sortBy) {
            case 'name-desc':
                $query->orderBy('employees.full_name', 'desc');
                break;
            case 'designation-asc':
                $query->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                      ->orderBy('designations.name', 'asc');
                break;
            case 'designation-desc':
                $query->leftJoin('designations', 'employees.designation_id', '=', 'designations.id')
                      ->orderBy('designations.name', 'desc');
                break;
            case 'name-asc':
            default:
                $query->orderBy('employees.full_name', 'asc');
                break;
        }

        $employees = $query->with(['department', 'designation', 'shift'])->paginate($request->integer('per_page', 10), ['employees.*']);

        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $rosterMap = [];
        foreach ($rosters as $roster) {
            $dateStr = $roster->date->format('Y-m-d');
            $rosterMap[$roster->employee_id][$dateStr] = $roster;
        }

        return $this->sendSuccess([
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
            'dates'      => $dates,
            'employees'  => $employees,
            'roster_map' => $rosterMap,
        ], 'Shift roster matrix retrieved successfully');
    }

    public function assign(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_ids'             => 'nullable|array',
            'employee_ids.*'           => 'exists:employees,id',
            'bulk_company_ids'         => 'nullable|array',
            'bulk_company_ids.*'       => 'exists:companies,id',
            'bulk_business_unit_ids'   => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids'          => 'nullable|array',
            'bulk_branch_ids.*'        => 'exists:branches,id',
            'bulk_department_ids'      => 'nullable|array',
            'bulk_department_ids.*'    => 'exists:departments,id',
            'bulk_designation_ids'     => 'nullable|array',
            'bulk_designation_ids.*'   => 'exists:designations,id',
            'shift_id'                 => 'nullable|exists:production_shifts,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
            'status'                   => 'required|string',
            'notes'                    => 'nullable|string',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        $shiftId     = $validated['shift_id'] ?? null;
        $startDate   = Carbon::parse($validated['start_date']);
        $endDate     = Carbon::parse($validated['end_date']);
        $status      = $validated['status'] ?? 'scheduled';
        $notes       = $validated['notes'] ?? null;

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids')) {
                $query->whereIn('company_id', $validated['bulk_company_ids']);
            }
            if ($request->filled('bulk_business_unit_ids')) {
                $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']);
            }
            if ($request->filled('bulk_branch_ids')) {
                $query->whereIn('branch_id', $validated['bulk_branch_ids']);
            }
            if ($request->filled('bulk_department_ids')) {
                $query->whereIn('department_id', $validated['bulk_department_ids']);
            }
            if ($request->filled('bulk_designation_ids')) {
                $query->whereIn('designation_id', $validated['bulk_designation_ids']);
            }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return $this->sendError('No matching active employees found for shift assignment.', 422);
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $assignedCount = 0;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        foreach ($employeeIds as $employeeId) {
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                
                ShiftRoster::updateOrCreate(
                    [
                        'tenant_id'   => $tenantId,
                        'employee_id' => $employeeId,
                        'date'        => $dateStr,
                    ],
                    [
                        'shift_id' => $shiftId,
                        'status'   => $status,
                        'notes'    => $notes,
                    ]
                );
                $assignedCount++;
            }
        }

        return $this->sendSuccess([
            'assigned_entries' => $assignedCount,
            'employees_count'  => count($employeeIds),
            'start_date'       => $startDate->format('Y-m-d'),
            'end_date'         => $endDate->format('Y-m-d'),
        ], 'Shifts assigned successfully');
    }

    public function updateCell(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
            'shift_id'    => 'nullable',
            'value'       => 'nullable|string',
        ]);

        $employeeId = $validated['employee_id'];
        $date = $validated['date'];
        $value = $validated['value'] ?? null;

        if ($value === null) {
            $value = isset($validated['shift_id']) ? (string)$validated['shift_id'] : 'default';
        }

        if ($value === 'default' || $value === '') {
            ShiftRoster::where([
                'employee_id' => $employeeId,
                'date' => $date,
            ])->delete();

            return $this->sendSuccess(null, 'Shift roster cell reset to default successfully');
        }

        $shiftId = $value === 'off' ? null : (int)$value;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $roster = ShiftRoster::updateOrCreate(
            [
                'tenant_id'   => $tenantId,
                'employee_id' => $employeeId,
                'date'        => $date,
            ],
            [
                'shift_id' => $shiftId,
                'status'   => 'scheduled',
            ]
        );

        return $this->sendSuccess($roster, 'Shift roster cell updated successfully');
    }

    public function updateWeeklyPattern(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'day_of_week' => 'required|integer|between:0,6',
            'value'       => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $dayOfWeek = (int)$validated['day_of_week'];
        $val = $validated['value'] ?? null;

        $pattern = $employee->weekly_pattern ?: [];
        
        if ($val === '' || $val === null || $val === 'default') {
            unset($pattern[$dayOfWeek]);
        } else {
            $pattern[$dayOfWeek] = $val === 'off' ? 'off' : (int)$val;
        }

        ksort($pattern);

        $employee->update([
            'weekly_pattern' => $pattern
        ]);

        return $this->sendSuccess($pattern, 'Weekly pattern updated successfully');
    }

    public function clear(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_ids'             => 'nullable|array',
            'employee_ids.*'           => 'exists:employees,id',
            'bulk_company_ids'         => 'nullable|array',
            'bulk_company_ids.*'       => 'exists:companies,id',
            'bulk_business_unit_ids'   => 'nullable|array',
            'bulk_business_unit_ids.*' => 'exists:business_units,id',
            'bulk_branch_ids'          => 'nullable|array',
            'bulk_branch_ids.*'        => 'exists:branches,id',
            'bulk_department_ids'      => 'nullable|array',
            'bulk_department_ids.*'    => 'exists:departments,id',
            'bulk_designation_ids'     => 'nullable|array',
            'bulk_designation_ids.*'   => 'exists:designations,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        $startDate   = $validated['start_date'];
        $endDate     = $validated['end_date'];

        if (empty($employeeIds)) {
            $query = Employee::query()->where('status', true);
            if ($request->filled('bulk_company_ids')) {
                $query->whereIn('company_id', $validated['bulk_company_ids']);
            }
            if ($request->filled('bulk_business_unit_ids')) {
                $query->whereIn('business_unit_id', $validated['bulk_business_unit_ids']);
            }
            if ($request->filled('bulk_branch_ids')) {
                $query->whereIn('branch_id', $validated['bulk_branch_ids']);
            }
            if ($request->filled('bulk_department_ids')) {
                $query->whereIn('department_id', $validated['bulk_department_ids']);
            }
            if ($request->filled('bulk_designation_ids')) {
                $query->whereIn('designation_id', $validated['bulk_designation_ids']);
            }
            $employeeIds = $query->pluck('id')->toArray();
        }

        if (empty($employeeIds)) {
            return $this->sendError('No matching active employees found for roster clearance.', 422);
        }

        $deletedCount = ShiftRoster::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->delete();

        return $this->sendSuccess([
            'deleted_entries' => $deletedCount,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
        ], 'Roster entries cleared successfully');
    }
}
