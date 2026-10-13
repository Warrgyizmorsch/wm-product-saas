<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Repositories\LeaveEncashmentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveEncashmentApiController extends Controller
{
    public function __construct(
        protected LeaveEncashmentRepositoryInterface $leaveEncashmentRepository
    ) {}

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
     * Helper to check if current user is HR Admin / Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return (bool) (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.leave_requests.approve') ||
            $user->hasHrPermission('hrms.leave_encashments.approve')
        );
    }

    private function canViewAll(): bool
    {
        $user = auth()->user();
        return $this->isHrAdmin() || ($user && $user->hasHrPermission('hrms.leave_encashments.view'));
    }

    /**
     * Helper to resolve current authenticated user's employee record.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first()
            ?? Employee::where(function ($q) use ($user) {
                $q->where('office_email', $user->email)
                  ->orWhere('personal_email', $user->email);
            })->first();
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
     * GET /api/hrms/leave-encashments/summary
     * Get summary metrics for leave encashment requests.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->canViewAll();
        $query = LeaveEncashment::query();
        if (auth()->user()?->tenant_id) $query->where('tenant_id', auth()->user()->tenant_id);
        if (!$isHrAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        return $this->sendSuccess([
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

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->canViewAll();
        $canManage = $this->isHrAdmin();

        $query = LeaveEncashment::query()->with(['employee', 'leaveType', 'approver']);
        if (auth()->user()?->tenant_id) $query->where('tenant_id', auth()->user()->tenant_id);

        if (!$isHrAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
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

        $encashments->getCollection()->transform(function ($enc) use ($employee, $canManage, $isHrAdmin) {
            $isOwner = $employee && (int)$enc->employee_id === (int)$employee->id;
            $isPending = $enc->status === 'pending';
            $enc->capabilities = [
                'can_view'    => true,
                'can_approve' => $canManage && $isPending,
                'can_reject'  => $canManage && $isPending,
                'can_cancel'  => ($isOwner || $isHrAdmin) && $isPending,
            ];
            return $enc;
        });

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

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $encashment = LeaveEncashment::with(['employee', 'leaveType', 'approver'])
            ->where('tenant_id', auth()->user()->tenant_id)->find($id);

        if (!$encashment) {
            return $this->sendError("Leave encashment request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $encashment->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized access to leave encashment request.', 403);
        }

        $isOwner = $employee && (int)$encashment->employee_id === (int)$employee->id;
        $isPending = $encashment->status === 'pending';
        $encashment->capabilities = [
            'can_view'    => true,
            'can_approve' => $isHrAdmin && $isPending,
            'can_reject'  => $isHrAdmin && $isPending,
            'can_cancel'  => ($isOwner || $isHrAdmin) && $isPending,
        ];

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

        $validated = $request->validate([
            'employee_id'    => 'nullable|integer|exists:employees,id',
            'leave_type_id'  => 'required|exists:leave_types,id',
            'requested_days' => 'required|numeric|min:0.5',
            'reason'         => 'nullable|string|max:1000',
        ]);

        if ($this->isHrAdmin() && empty($validated['employee_id'])) {
            return $this->sendError('employee_id is required for HR administrators.', 422);
        }

        $employee = $this->getAuthenticatedEmployee();
        if (!$this->isHrAdmin() && $employee && !empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $employee->id) {
            return $this->sendError('You can only submit a leave encashment request for your own employee profile.', 403);
        }
        if (!$this->isHrAdmin() && !$employee) {
            return $this->sendError('Employee profile not found for the authenticated user.', 404);
        }
        if (!$this->isHrAdmin()) {
            $validated['employee_id'] = $employee->id;
        }

        $result = $this->leaveEncashmentRepository->storeEncashment($validated, $request, auth()->user());
        if (!$result['success']) return $this->sendError($result['message'], 422);
        $encashment = $result['encashment'] ?? null;
        return $this->sendSuccess($encashment?->load(['employee', 'leaveType']), $result['message'], 201);
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
            return $this->sendError('Unauthorized action. Only HR Admins can approve leave encashment.', 403);
        }

        $encashment = LeaveEncashment::where('tenant_id', auth()->user()->tenant_id)->find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        $result = $this->leaveEncashmentRepository->approve($encashment, request(), auth()->user());
        if (!$result['success']) return $this->sendError($result['message'], 422);
        $encashment->refresh();

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
            return $this->sendError('Unauthorized action. Only HR Admins can reject leave encashment.', 403);
        }

        $encashment = LeaveEncashment::where('tenant_id', auth()->user()->tenant_id)->find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        request()->validate(['rejection_reason' => 'nullable|string|max:1000']);
        $result = $this->leaveEncashmentRepository->reject($encashment, request('rejection_reason'), auth()->user());
        if (!$result['success']) return $this->sendError($result['message'], 422);
        $encashment->refresh();

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

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $encashment = LeaveEncashment::where('tenant_id', auth()->user()->tenant_id)->find($id);
        if (!$encashment) {
            return $this->sendError(__('hrms.leave.encashment_app.ticket_not_found', ['id' => $id]), 404);
        }

        if (!$isHrAdmin && $encashment->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$this->leaveEncashmentRepository->delete($encashment)) return $this->sendError('Unable to delete this encashment request.', 422);

        return $this->sendSuccess(['id' => (int)$id], __('hrms.leave.encashment_app.deleted_successfully'));
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

    private function isWithinFrequencyLimits(int $employeeId, int $leaveTypeId, Carbon $start, Carbon $end, string $frequency): bool
    {
        if ($frequency === 'anytime' || $frequency === 'any_time') {
            return true;
        }

        $exists = LeaveEncashment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('created_at', [$start, $end])
            ->exists();

        return !$exists;
    }

    private function getCyclePeriods(Employee $employee, Carbon $now, string $frequency): array
    {
        $startDate = null;
        if ($employee && $employee->leavePlan && $employee->leavePlan->effective_from) {
            $startDate = Carbon::parse($employee->leavePlan->effective_from);
        } else {
            $startDate = Carbon::create($now->year, 1, 1, 0, 0, 0);
        }

        $diffInYears = $startDate->diffInYears($now);
        $cycleStart = $startDate->copy()->addYears($diffInYears);
        if ($cycleStart->isAfter($now)) {
            $cycleStart->subYear();
        }

        $elapsedMonths = $cycleStart->diffInMonths($now);

        $periodStart = $cycleStart->copy();
        $periodEnd = $cycleStart->copy()->addYear()->subSecond();
        $isValidMonth = true;

        if ($frequency === 'monthly') {
            $periodStart = $cycleStart->copy()->addMonths($elapsedMonths);
            $periodEnd = $periodStart->copy()->addMonth()->subSecond();
            $isValidMonth = true;
        } elseif ($frequency === 'quarterly') {
            $quarterIndex = floor($elapsedMonths / 3);
            $periodStart = $cycleStart->copy()->addMonths($quarterIndex * 3);
            $periodEnd = $periodStart->copy()->addMonths(3)->subSecond();
            $isValidMonth = ($elapsedMonths % 3) === 2;
        } elseif ($frequency === 'half_yearly') {
            $halfIndex = floor($elapsedMonths / 6);
            $periodStart = $cycleStart->copy()->addMonths($halfIndex * 6);
            $periodEnd = $periodStart->copy()->addMonths(6)->subSecond();
            $isValidMonth = ($elapsedMonths % 6) === 5;
        } elseif ($frequency === 'yearly') {
            $periodStart = $cycleStart->copy();
            $periodEnd = $cycleStart->copy()->addYear()->subSecond();
            $isValidMonth = ($elapsedMonths % 12) === 11;
        }

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
            'is_valid_month' => $isValidMonth,
        ];
    }

    /**
     * Export Leave Encashment Requests to Excel (.xlsx).
     */
    public function export(Request $request): mixed
    {
        return $this->leaveEncashmentRepository->export($request->all());
    }
}
