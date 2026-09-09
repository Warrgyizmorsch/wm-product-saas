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
use Carbon\Carbon;

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
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please provide valid credentials.', 401);
            }
        }

        return null;
    }

    /**
     * Transforms a LeavePlan model into a concise, essential-only array.
     */
    private function transformLeavePlan(LeavePlan $plan, bool $detailed = false): array
    {
        $data = [
            'id'              => $plan->id,
            'name'            => $plan->name,
            'company_id'      => $plan->company_id,
            'company_name'    => $plan->company?->company_name ?? $plan->company?->name,
            'effective_from'  => $plan->effective_from ? Carbon::parse($plan->effective_from)->format('Y-m-d') : null,
            'description'     => $plan->description,
            'status'          => (bool)$plan->status,
            'last_renewed_at' => $plan->last_renewed_at ? Carbon::parse($plan->last_renewed_at)->format('Y-m-d') : null,
        ];

        if ($plan->relationLoaded('types')) {
            $data['types_count'] = $plan->types->count();
            if ($detailed) {
                $data['types'] = $plan->types->map(fn($t) => $this->transformLeaveType($t))->values();
            }
        }

        return $data;
    }

    /**
     * Transforms a LeaveType model into a concise, essential-only array.
     */
    private function transformLeaveType(LeaveType $type): array
    {
        return [
            'id'            => $type->id,
            'leave_plan_id' => $type->leave_plan_id,
            'plan_name'     => $type->plan?->name,
            'name'          => $type->name,
            'code'          => $type->code,
            'type'          => $type->type,
            'color'         => $type->color ?? '#3b82f6',
            'quota'         => floatval($type->quota),
            'description'   => $type->description,
            'rules'         => $type->rules ?? [],
            'status'        => (bool)$type->status,
        ];
    }

    /**
     * Formats a LengthAwarePaginator into a clean, essential structure.
     */
    private function formatPaginatedData(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, callable $transformItem): array
    {
        return [
            'items' => collect($paginator->items())->map($transformItem)->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * GET /api/hrms/leave-structure/summary
     * Concise summary metrics & active leave plan overview.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlans = LeavePlan::with(['company', 'types'])->get();
        $selectedPlanId = $request->query('plan_id');
        $selectedPlan = $selectedPlanId ? $leavePlans->firstWhere('id', $selectedPlanId) : $leavePlans->first();

        $companies = Company::orderBy('company_name')->get()->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->company_name ?? $c->name,
        ])->values();

        return $this->sendSuccess([
            'plans_count'   => LeavePlan::count(),
            'types_count'   => LeaveType::count(),
            'companies'     => $companies,
            'leave_plans'   => $leavePlans->map(fn($p) => $this->transformLeavePlan($p, false))->values(),
            'selected_plan' => $selectedPlan ? $this->transformLeavePlan($selectedPlan, true) : null,
        ], 'Leave structure summary loaded successfully.');
    }

    // ==========================================
    // 1. LEAVE PLANS API
    // ==========================================

    /**
     * GET /api/hrms/leave-structure/plans
     * List all leave plans cleanly.
     */
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
        $formatted = $plans->map(fn($p) => $this->transformLeavePlan($p, false))->values();

        return $this->sendSuccess($formatted, 'Leave plans retrieved successfully.');
    }

    /**
     * GET /api/hrms/leave-structure/plans/{id}
     * Get single leave plan with its detailed types.
     */
    public function showPlan(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlan = LeavePlan::with(['company', 'types'])->find($id);
        if (!$leavePlan) {
            return $this->sendError("Leave plan with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($this->transformLeavePlan($leavePlan, true), 'Leave plan details loaded successfully.');
    }

    /**
     * POST /api/hrms/leave-structure/plans
     * Create a new leave plan.
     */
    public function storePlan(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'company_id'     => 'nullable|integer|exists:companies,id',
            'effective_from' => 'required|date',
            'description'    => 'nullable|string',
            'status'         => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true || $request->status === 1);

        $newPlan = LeavePlan::create([
            'company_id'     => $validated['company_id'] ?: (Company::first()?->id ?? 1),
            'name'           => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'description'    => $validated['description'] ?? null,
            'status'         => $status,
        ]);

        return $this->sendSuccess($this->transformLeavePlan($newPlan->load(['company', 'types']), true), 'Leave plan created successfully.', 201);
    }

    /**
     * PUT /api/hrms/leave-structure/plans/{id}
     * Update an existing leave plan.
     */
    public function updatePlan(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlan = LeavePlan::find($id);
        if (!$leavePlan) {
            return $this->sendError("Leave plan with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'company_id'     => 'nullable|integer|exists:companies,id',
            'effective_from' => 'required|date',
            'description'    => 'nullable|string',
            'status'         => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true || $request->status === 1);

        $leavePlan->update([
            'company_id'     => $validated['company_id'] ?: $leavePlan->company_id ?: (Company::first()?->id ?? 1),
            'name'           => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'description'    => $validated['description'] ?? null,
            'status'         => $status,
        ]);

        return $this->sendSuccess($this->transformLeavePlan($leavePlan->fresh(['company', 'types']), true), 'Leave plan updated successfully.');
    }

    /**
     * DELETE /api/hrms/leave-structure/plans/{id}
     * Delete a leave plan.
     */
    public function destroyPlan(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leavePlan = LeavePlan::find($id);
        if (!$leavePlan) {
            return $this->sendError("Leave plan with ID '{$id}' not found.", 404);
        }

        $leavePlan->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Leave plan deleted successfully.');
    }

    // ==========================================
    // 2. LEAVE TYPES & POLICIES API
    // ==========================================

    /**
     * GET /api/hrms/leave-structure/types
     * List paginated leave types.
     */
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

        $perPage = min($request->integer('per_page', 10), 100);
        $leaveTypes = $query->paginate($perPage);

        $formatted = $this->formatPaginatedData($leaveTypes, fn($t) => $this->transformLeaveType($t));

        return $this->sendSuccess($formatted, 'Leave types retrieved successfully.');
    }

    /**
     * GET /api/hrms/leave-structure/types/{id}
     * Get single leave type details.
     */
    public function showType(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveType = LeaveType::with(['plan'])->find($id);
        if (!$leaveType) {
            return $this->sendError("Leave type with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($this->transformLeaveType($leaveType), 'Leave type details loaded successfully.');
    }

    /**
     * POST /api/hrms/leave-structure/types
     * Create a new leave type.
     */
    public function storeType(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50',
            'type'          => 'required|in:paid,unpaid',
            'color'         => 'nullable|string|max:20',
            'quota'         => 'required|numeric|min:0',
            'description'   => 'nullable|string',
            'status'        => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true || $request->status === 1);

        $leaveType = LeaveType::create([
            'leave_plan_id' => $validated['leave_plan_id'],
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'type'          => $validated['type'],
            'color'         => $validated['color'] ?: '#3b82f6',
            'quota'         => $validated['quota'],
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($this->transformLeaveType($leaveType->load(['plan'])), 'Leave type created successfully.', 201);
    }

    /**
     * PUT /api/hrms/leave-structure/types/{id}
     * Update an existing leave type.
     */
    public function updateType(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveType = LeaveType::find($id);
        if (!$leaveType) {
            return $this->sendError("Leave type with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50',
            'type'          => 'required|in:paid,unpaid',
            'color'         => 'nullable|string|max:20',
            'quota'         => 'required|numeric|min:0',
            'description'   => 'nullable|string',
            'status'        => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true || $request->status === 1);

        $leaveType->update([
            'leave_plan_id' => $validated['leave_plan_id'],
            'name'          => $validated['name'],
            'code'          => $validated['code'],
            'type'          => $validated['type'],
            'color'         => $validated['color'] ?: '#3b82f6',
            'quota'         => $validated['quota'],
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($this->transformLeaveType($leaveType->fresh(['plan'])), 'Leave type updated successfully.');
    }

    /**
     * PUT /api/hrms/leave-structure/types/{id}/rules
     * Update policy rules for a leave type.
     */
    public function updateRules(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveType = LeaveType::find($id);
        if (!$leaveType) {
            return $this->sendError("Leave type with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'rules' => 'required|array'
        ]);

        $leaveType->update([
            'rules' => $validated['rules']
        ]);

        return $this->sendSuccess($this->transformLeaveType($leaveType->fresh(['plan'])), 'Leave policy rules updated successfully.');
    }

    /**
     * DELETE /api/hrms/leave-structure/types/{id}
     * Delete a leave type.
     */
    public function destroyType(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveType = LeaveType::find($id);
        if (!$leaveType) {
            return $this->sendError("Leave type with ID '{$id}' not found.", 404);
        }

        $leaveType->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Leave type deleted successfully.');
    }

    // ==========================================
    // 3. RENEWAL & PLAN TRANSITION API
    // ==========================================

    /**
     * POST /api/hrms/leave-structure/plans/renew
     * Year-end renewal of leave balances for assigned employees.
     */
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
            $leavePlan = LeavePlan::find($validated['leave_plan_id']);
            if (!$leavePlan) {
                return $this->sendError("Leave plan with ID '{$validated['leave_plan_id']}' not found.", 404);
            }

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

            return $this->sendSuccess($this->transformLeavePlan($leavePlan->fresh(['company', 'types']), true), 'Leave plan balances renewed successfully for all assigned employees.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to renew leave plan balances: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/leave-structure/plans/transition
     * Transition employees to a new leave plan.
     */
    public function processTransition(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_ids'            => 'required|array|min:1',
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
                'new_leave_plan_id'  => (int)$newPlanId
            ], "Successfully transitioned {$count} employee(s) to the new leave plan.");
        } catch (\Exception $e) {
            return $this->sendError('Leave plan transition failed: ' . $e->getMessage(), 500);
        }
    }
}
