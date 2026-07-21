<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveEncashment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveEncashmentApiController extends Controller
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

        return null;
    }

    /**
     * Helper to check if current user is an HR admin.
     */
    private function isHrAdmin(): bool
    {
        return auth()->check() && auth()->user()->hasHrPermission('hr.settings.manage');
    }

    /**
     * GET /api/hrms/leave-encashments/summary
     * Get summary metrics for leave encashment requests.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $isAdmin  = $this->isHrAdmin();
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = LeaveEncashment::query();
        if (!$isAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        return $this->sendSuccess([
            'is_admin'           => $isAdmin,
            'total_encashments'  => (clone $query)->count(),
            'pending_encashments'=> (clone $query)->where('status', 'pending')->count(),
            'approved_encashments'=> (clone $query)->where('status', 'approved')->count(),
            'rejected_encashments'=> (clone $query)->where('status', 'rejected')->count(),
        ], 'Leave encashment summary loaded successfully');
    }

    /**
     * GET /api/hrms/leave-encashments
     * List paginated leave encashment requests with filters.
     */
    public function indexEncashments(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $isAdmin  = $this->isHrAdmin();
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = LeaveEncashment::query()->with(['employee', 'leaveType', 'approver']);

        if ($isAdmin) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->integer('employee_id'));
            }
        } else {
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->where('id', 0);
            }
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->integer('leave_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $encashments = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($encashments, 'Leave encashment requests retrieved successfully');
    }

    /**
     * GET /api/hrms/leave-encashments/{id}
     * Get details of a single leave encashment request.
     */
    public function showEncashment(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $encashment = LeaveEncashment::with(['employee', 'leaveType', 'approver'])->find($id);

        if (!$encashment) {
            return $this->sendError("Leave encashment request with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($encashment, 'Leave encashment details loaded');
    }

    /**
     * POST /api/hrms/leave-encashments
     * Submit a new leave encashment request with policy rule validations.
     */
    public function storeEncashment(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $isAdmin = $this->isHrAdmin();

        $validated = $request->validate([
            'employee_id'    => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'leave_type_id'  => 'required|exists:leave_types,id',
            'requested_days' => 'required|numeric|min:0.5',
            'reason'         => 'nullable|string|max:1000',
        ]);

        if ($isAdmin && !empty($validated['employee_id'])) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            $employee = Employee::where('personal_email', auth()->user()->email)
                ->orWhere('office_email', auth()->user()->email)
                ->first();
        }

        if (!$employee) {
            return $this->sendError(__('hrms.leave.encashment_app.emp_not_found'), 404);
        }

        $leaveType = LeaveType::find($validated['leave_type_id']);
        if (!$leaveType) {
            return $this->sendError(__('hrms.leave.encashment_app.type_not_found'), 404);
        }

        $rules       = $leaveType->rules ?? [];
        $encashRules = $rules['encashment'] ?? [];

        $isEnabled = !empty($encashRules['enabled']) && ($encashRules['enabled'] === true || $encashRules['enabled'] === '1' || $encashRules['enabled'] === 'true');

        if (!$isEnabled) {
            return $this->sendError(__('hrms.leave.encashment_app.not_enabled', ['name' => $leaveType->name]), 422);
        }

        $frequency = $encashRules['frequency'] ?? 'anytime';
        if (!$this->isValidEncashmentMonth(Carbon::now(), $frequency)) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return $this->sendError(__('hrms.leave.encashment_app.invalid_month', ['name' => $leaveType->name, 'frequency' => $freqLabel]), 422);
        }

        $maxPerRequest = floatval($encashRules['max_days_per_request'] ?? 999.0);
        $requestedDays = round(floatval($validated['requested_days']) * 2) / 2;

        if ($requestedDays > $maxPerRequest) {
            return $this->sendError(__('hrms.leave.encashment_app.max_days_exceeded', ['name' => $leaveType->name, 'max' => $maxPerRequest]), 422);
        }

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->first();

        $remaining        = $balance ? floatval($balance->remaining) : 0.0;
        $minBalanceToKeep = floatval($encashRules['min_balance_to_keep'] ?? 0.0);

        if (($remaining - $requestedDays) < $minBalanceToKeep) {
            return $this->sendError(__('hrms.leave.encashment_app.min_balance_required', ['min' => $minBalanceToKeep, 'remaining' => $remaining]), 422);
        }

        if ($requestedDays > $remaining) {
            return $this->sendError(__('hrms.leave.encashment_app.insufficient_balance', ['remaining' => $remaining]), 422);
        }

        $encashment = LeaveEncashment::create([
            'tenant_id'      => $employee->tenant_id,
            'company_id'     => $employee->company_id,
            'employee_id'    => $employee->id,
            'leave_type_id'  => $leaveType->id,
            'requested_days' => $requestedDays,
            'status'         => 'pending',
            'reason'         => $validated['reason'] ?? null,
        ]);

        return $this->sendSuccess($encashment->load(['employee', 'leaveType']), __('hrms.leave.encashment_app.submitted_successfully'), 201);
    }

    /**
     * POST /api/hrms/leave-encashments/{id}/approve
     * Approve leave encashment request & reconcile balances.
     */
    public function approveEncashment(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can approve leave encashment requests.', 403);
        }

        $encashment = LeaveEncashment::find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        $encashment->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->reconcileEncashedBalance($encashment->employee_id, $encashment->leave_type_id);

        return $this->sendSuccess($encashment->load(['employee', 'leaveType', 'approver']), __('hrms.leave.encashment_app.approved_successfully'));
    }

    /**
     * POST /api/hrms/leave-encashments/{id}/reject
     * Reject leave encashment request & reconcile balances.
     */
    public function rejectEncashment(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can reject leave encashment requests.', 403);
        }

        $encashment = LeaveEncashment::find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        $encashment->update([
            'status'      => 'rejected',
            'approved_by' => auth()->id(),
        ]);

        $this->reconcileEncashedBalance($encashment->employee_id, $encashment->leave_type_id);

        return $this->sendSuccess($encashment, __('hrms.leave.encashment_app.rejected_successfully'));
    }

    /**
     * DELETE /api/hrms/leave-encashments/{id}
     * Delete leave encashment request & reconcile balances.
     */
    public function destroyEncashment(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can delete leave encashment requests.', 403);
        }

        $encashment = LeaveEncashment::find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        $empId  = $encashment->employee_id;
        $typeId = $encashment->leave_type_id;

        $encashment->delete();

        $this->reconcileEncashedBalance($empId, $typeId);

        return $this->sendSuccess(null, __('hrms.leave.encashment_app.deleted_successfully'));
    }

    private function isValidEncashmentMonth(Carbon $date, string $frequency): bool
    {
        $month = $date->month;

        return match ($frequency) {
            'monthly'     => true,
            'quarterly'   => in_array($month, [3, 6, 9, 12]),
            'half_yearly' => in_array($month, [6, 12]),
            'yearly'      => $month === 12,
            default       => true,
        };
    }

    private function reconcileEncashedBalance(int $employeeId, int $leaveTypeId): void
    {
        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$balance) {
            return;
        }

        $employee = Employee::with('leavePlan')->find($employeeId);
        $currentCycleStart = null;
        if ($employee && $employee->leavePlan && $employee->leavePlan->effective_from) {
            $startDate   = Carbon::parse($employee->leavePlan->effective_from);
            $now         = Carbon::now();
            $diffInYears = $startDate->diffInYears($now);
            $currentCycleStart = $startDate->copy()->addYears($diffInYears);
            if ($currentCycleStart->isAfter($now)) {
                $currentCycleStart->subYear();
            }
        }

        $query = LeaveEncashment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved');

        if ($currentCycleStart) {
            $query->where('created_at', '>=', $currentCycleStart);
        }

        $approvedEncashedSum = floatval($query->sum('requested_days'));

        $balance->update([
            'encashed' => round($approvedEncashedSum * 2) / 2,
        ]);
    }
}
