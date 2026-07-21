<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveEncashment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveStructureApiController extends Controller
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
     * GET /api/hrms/leave-structure/summary
     * Get overall summary metrics & leave plan lists.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlans = LeavePlan::with(['company', 'types'])->get();
        $selectedPlanId = $request->query('plan_id');
        $selectedPlan = $selectedPlanId ? $leavePlans->firstWhere('id', $selectedPlanId) : $leavePlans->first();

        return $this->sendSuccess([
            'plans_count'     => LeavePlan::count(),
            'types_count'     => LeaveType::count(),
            'companies'       => Company::orderBy('company_name')->get(),
            'leave_plans'     => $leavePlans,
            'selected_plan'   => $selectedPlan,
        ], 'Leave structure summary loaded successfully');
    }

    // ==========================================
    // 1. LEAVE PLANS API
    // ==========================================

    public function indexPlans(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = LeavePlan::with(['company', 'types']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status') === '1' || $request->get('status') === 'true');
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $plans = $query->orderBy('name', 'asc')->get();

        return $this->sendSuccess($plans, 'Leave plans retrieved successfully');
    }

    public function showPlan(LeavePlan $leavePlan): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($leavePlan->load(['company', 'types']), 'Leave plan details loaded');
    }

    public function storePlan(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'           => 'required|max:255',
            'company_id'     => 'nullable|integer|exists:companies,id',
            'effective_from' => 'required|date',
            'description'    => 'nullable',
            'status'         => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $newPlan = LeavePlan::create([
            'company_id'     => $validated['company_id'] ?? null,
            'name'           => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'description'    => $validated['description'] ?? null,
            'status'         => $status,
        ]);

        return $this->sendSuccess($newPlan, 'Leave plan created successfully', 201);
    }

    public function updatePlan(Request $request, LeavePlan $leavePlan): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'           => 'required|max:255',
            'company_id'     => 'nullable|integer|exists:companies,id',
            'effective_from' => 'required|date',
            'description'    => 'nullable',
            'status'         => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $leavePlan->update([
            'company_id'     => $validated['company_id'] ?? null,
            'name'           => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'description'    => $validated['description'] ?? null,
            'status'         => $status,
        ]);

        return $this->sendSuccess($leavePlan, 'Leave plan updated successfully');
    }

    public function destroyPlan(LeavePlan $leavePlan): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlan->delete();

        return $this->sendSuccess(null, 'Leave plan deleted successfully');
    }

    // ==========================================
    // 2. LEAVE TYPES & POLICIES API
    // ==========================================

    public function indexTypes(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = LeaveType::with(['plan']);

        if ($request->filled('leave_plan_id')) {
            $query->where('leave_plan_id', $request->get('leave_plan_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status') === '1' || $request->get('status') === 'true');
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'name_asc');
        switch ($sort) {
            case 'name_desc':  $query->orderBy('name', 'desc'); break;
            case 'quota_asc':  $query->orderBy('quota', 'asc'); break;
            case 'quota_desc': $query->orderBy('quota', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $leaveTypes = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($leaveTypes, 'Leave types retrieved successfully');
    }

    public function showType(LeaveType $leaveType): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($leaveType->load(['plan']), 'Leave type details loaded');
    }

    public function storeType(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name'          => 'required|max:255',
            'code'          => 'required|max:50',
            'type'          => 'required|in:paid,unpaid',
            'color'         => 'required|max:20',
            'quota'         => 'required|numeric|min:0',
            'description'   => 'nullable',
            'status'        => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $leaveType = LeaveType::create([
            'leave_plan_id' => $validated['leave_plan_id'],
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'type'          => $validated['type'],
            'color'         => $validated['color'],
            'quota'         => $validated['quota'],
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($leaveType, 'Leave type created successfully', 201);
    }

    public function updateType(Request $request, LeaveType $leaveType): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name'          => 'required|max:255',
            'code'          => 'required|max:50',
            'type'          => 'required|in:paid,unpaid',
            'color'         => 'required|max:20',
            'quota'         => 'required|numeric|min:0',
            'description'   => 'nullable',
            'status'        => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $leaveType->update([
            'leave_plan_id' => $validated['leave_plan_id'],
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'type'          => $validated['type'],
            'color'         => $validated['color'],
            'quota'         => $validated['quota'],
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($leaveType, 'Leave type updated successfully');
    }

    public function updateRules(Request $request, LeaveType $leaveType): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'rules' => 'required|array'
        ]);

        $leaveType->update([
            'rules' => $validated['rules']
        ]);

        return $this->sendSuccess($leaveType, 'Leave policy rules updated successfully');
    }

    public function destroyType(LeaveType $leaveType): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveType->delete();

        return $this->sendSuccess(null, 'Leave type deleted successfully');
    }

    // ==========================================
    // 3. RENEWAL & PLAN TRANSITION API
    // ==========================================

    public function renewPlanBalances(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'leave_plan_id' => 'required|exists:leave_plans,id',
            'yearend_rules' => 'nullable|array',
        ]);

        try {
            $leavePlan = LeavePlan::findOrFail($validated['leave_plan_id']);

            // Update rules persistently in database if yearend_rules are provided
            if ($request->filled('yearend_rules')) {
                foreach ($request->yearend_rules as $typeId => $ruleSet) {
                    $ltype = LeaveType::where('leave_plan_id', $leavePlan->id)->find($typeId);
                    if ($ltype) {
                        $currentRules = $ltype->rules ?? [];
                        $currentRules['yearend'] = [
                            'action'    => $ruleSet['action'] ?? 'lapse',
                            'max_carry' => floatval($ruleSet['max_carry'] ?? 0.0),
                            'max_encash'=> floatval($currentRules['yearend']['max_encash'] ?? 0.0),
                        ];
                        $ltype->update(['rules' => $currentRules]);
                    }
                }
                $leavePlan->load('types');
            }

            $employees = Employee::where('leave_plan_id', $leavePlan->id)
                ->where('status', true)
                ->get();

            foreach ($employees as $employee) {
                foreach ($leavePlan->types as $ltype) {
                    $balance = LeaveBalance::firstOrCreate([
                        'tenant_id'     => $employee->tenant_id,
                        'company_id'    => $employee->company_id,
                        'employee_id'   => $employee->id,
                        'leave_type_id' => $ltype->id,
                    ], [
                        'allocated' => floatval($ltype->quota),
                        'used'      => 0.0,
                    ]);

                    $rules          = $ltype->rules ?? [];
                    $action         = $rules['yearend']['action'] ?? 'lapse';
                    $maxCarry       = floatval($rules['yearend']['max_carry'] ?? 0.0);
                    $maxEncash      = floatval($rules['yearend']['max_encash'] ?? 0.0);
                    $remaining      = floatval($balance->remaining);
                    $rollover       = 0.0;
                    $autoEncashDays = 0.0;

                    if ($action === 'carry_forward' && $remaining > 0.0) {
                        $rollover = min($remaining, $maxCarry);
                        $leftoverAfterCarry = max(0.0, $remaining - $rollover);
                        if ($maxEncash > 0.0 && $leftoverAfterCarry > 0.0) {
                            $autoEncashDays = min($leftoverAfterCarry, $maxEncash);
                        }
                    } elseif ($remaining > 0.0 && $maxEncash > 0.0) {
                        $autoEncashDays = min($remaining, $maxEncash);
                    }

                    if ($autoEncashDays > 0.0) {
                        LeaveEncashment::create([
                            'tenant_id'      => $employee->tenant_id,
                            'company_id'     => $employee->company_id,
                            'employee_id'    => $employee->id,
                            'leave_type_id'  => $ltype->id,
                            'requested_days' => $autoEncashDays,
                            'status'         => 'approved',
                            'reason'         => 'Year-end renewal automatic encashment',
                            'approved_by'    => auth()->id(),
                            'approved_at'    => now(),
                        ]);
                    }

                    $newAllocated = floatval($ltype->quota) + $rollover;
                    $newAllocated = round($newAllocated * 2) / 2;

                    $balance->update([
                        'allocated' => $newAllocated,
                        'used'      => 0.0,
                        'encashed'  => 0.0,
                    ]);
                }
            }

            $leavePlan->update([
                'effective_from'  => now()->toDateString(),
                'last_renewed_at' => now()->toDateString(),
                'status'          => true,
            ]);

            return $this->sendSuccess($leavePlan->load('types'), 'Leave plan balances renewed successfully for all assigned employees');
        } catch (\Exception $e) {
            return $this->sendError('Failed to renew leave plan balances: ' . $e->getMessage(), 500);
        }
    }

    public function processTransition(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_ids'            => 'required|array',
            'employee_ids.*'          => 'exists:employees,id',
            'new_leave_plan_id'       => 'required|exists:leave_plans,id',
            'leave_transition_action' => 'required|in:transfer,prorate',
            'leave_transition_unused' => 'required|in:carry,lapse,encash',
        ]);

        try {
            $employeeIds  = $validated['employee_ids'];
            $newPlanId    = $validated['new_leave_plan_id'];
            $action       = $validated['leave_transition_action'];
            $unusedAction = $validated['leave_transition_unused'];

            $pendingEmployees = [];
            foreach ($employeeIds as $empId) {
                $employee = Employee::find($empId);
                if ($employee && (int)$employee->leave_plan_id !== (int)$newPlanId) {
                    $hasPendingLeave = LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'pending')
                        ->exists();
                    $hasPendingEncash = LeaveEncashment::where('employee_id', $employee->id)
                        ->where('status', 'pending')
                        ->exists();

                    if ($hasPendingLeave || $hasPendingEncash) {
                        $pendingEmployees[] = $employee->full_name;
                    }
                }
            }

            if (!empty($pendingEmployees)) {
                return $this->sendError('Cannot transition leave plans. The following employee(s) have pending leave or encashment requests: ' . implode(', ', $pendingEmployees), 422);
            }

            $count = 0;
            foreach ($employeeIds as $empId) {
                $employee = Employee::find($empId);
                if ($employee && (int)$employee->leave_plan_id !== (int)$newPlanId) {
                    $oldPlanId = $employee->leave_plan_id;
                    
                    $employee->update([
                        'leave_plan_id' => $newPlanId
                    ]);

                    $employee->migrateToLeavePlan($oldPlanId, $newPlanId, $action, $unusedAction);
                    $count++;
                }
            }

            return $this->sendSuccess([
                'transitioned_count' => $count,
                'new_leave_plan_id'  => $newPlanId
            ], "Successfully transitioned {$count} employee(s) to the new leave plan");
        } catch (\Exception $e) {
            return $this->sendError('Leave plan transition failed: ' . $e->getMessage(), 500);
        }
    }
}
