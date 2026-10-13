<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class OvertimeRequestApiController extends Controller
{
    public function __construct(
        private readonly OvertimeRequestRepositoryInterface $overtimeRepository
    ) {}

    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

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

    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated.', 401);
            }
        }

        return null;
    }

    /**
     * Helper to check if current user is HR Admin / Overtime Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return (bool) (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.roster.manage') ||
            $user->hasHrPermission('hrms.shift_roster.manage')
        );
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

        return Employee::resolveForUser($user);
    }

    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $query = OvertimeRequest::query();
        if (!$isHrAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        $totalRequests        = (clone $query)->count();
        $pendingRequests      = (clone $query)->where('status', 'pending')->count();
        $approvedRequests     = (clone $query)->where('status', 'approved')->count();
        $rejectedRequests     = (clone $query)->where('status', 'rejected')->count();
        $cancellationRequests = (clone $query)->where('status', 'cancellation_requested')->count();
        $cancelledRequests    = (clone $query)->where('status', 'cancelled')->count();

        return $this->sendSuccess([
            'total_requests'          => $totalRequests,
            'pending_requests'        => $pendingRequests,
            'approved_requests'       => $approvedRequests,
            'rejected_requests'       => $rejectedRequests,
            'cancellation_requested'  => $cancellationRequests,
            'cancelled_requests'      => $cancelledRequests,
        ], 'Overtime requests summary loaded');
    }

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->overtimeRepository->getIndexData($request->all());

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        if (isset($data['requests']) && method_exists($data['requests'], 'getCollection')) {
            $data['requests']->getCollection()->transform(function ($req) use ($employee, $isHrAdmin) {
                $isOwner     = $employee && (int)$req->employee_id === (int)$employee->id;
                $isPending   = $req->status === 'pending';
                $isApproved  = $req->status === 'approved';
                $isCancelReq = $req->status === 'cancellation_requested';
                $req->capabilities = [
                    'can_view'           => true,
                    'can_approve'        => $isHrAdmin && $isPending,
                    'can_reject'         => $isHrAdmin && $isPending,
                    'can_withdraw'       => ($isOwner || $isHrAdmin) && $isPending,
                    'can_request_cancel' => ($isOwner || $isHrAdmin) && $isApproved,
                    'can_approve_cancel' => $isHrAdmin && $isCancelReq,
                    'can_deny_cancel'    => $isHrAdmin && $isCancelReq,
                ];
                return $req;
            });
        }

        return $this->sendSuccess($data['requests'], 'Overtime requests retrieved successfully');
    }

    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $overtimeRequest = OvertimeRequest::with(['employee', 'approvedByEmployee'])->find($id);

        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $overtimeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized access to overtime request.', 403);
        }

        $isOwner     = $employee && (int)$overtimeRequest->employee_id === (int)$employee->id;
        $isPending   = $overtimeRequest->status === 'pending';
        $isApproved  = $overtimeRequest->status === 'approved';
        $isCancelReq = $overtimeRequest->status === 'cancellation_requested';

        $overtimeRequest->capabilities = [
            'can_view'           => true,
            'can_approve'        => $isHrAdmin && $isPending,
            'can_reject'         => $isHrAdmin && $isPending,
            'can_withdraw'       => ($isOwner || $isHrAdmin) && $isPending,
            'can_request_cancel' => ($isOwner || $isHrAdmin) && $isApproved,
            'can_approve_cancel' => $isHrAdmin && $isCancelReq,
            'can_deny_cancel'    => $isHrAdmin && $isCancelReq,
        ];

        return $this->sendSuccess($overtimeRequest, 'Overtime request details loaded');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'       => 'nullable|integer|exists:employees,id',
            'date'              => 'required|date',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i',
            'compensation_type' => 'required|string|in:payout,comp_off',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError('Employee record not found.', 404);
            }
            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $employee->id) {
                return $this->sendError('You can only submit an overtime request for your own employee profile.', 403);
            }
            $validated['employee_id'] = $employee->id;
        } else {
            if (empty($validated['employee_id'])) {
                return $this->sendError('employee_id is required for HR administrators.', 422);
            }
            $employee = Employee::find($validated['employee_id']);
        }

        if (!$employee) {
            return $this->sendError('Employee record not found.', 404);
        }

        // Check if employee's shift on this date permits overtime
        $shift = $employee->resolveShiftForDate($validated['date']);
        if ($shift && !$shift->overtime_allowed && !$isHrAdmin) {
            return $this->sendError("Overtime is not allowed for your assigned shift ({$shift->name}) on this date.", 422);
        }

        // Check if an active/pending overtime request already exists for this employee on this date
        $exists = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
            ->exists();
        if ($exists) {
            return $this->sendError(__('hrms.overtime.request_exists') ?? 'An overtime request already exists for this date.', 422);
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime   = Carbon::parse($validated['end_time']);
        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }
        $durationHours = $startTime->diffInMinutes($endTime) / 60.0;

        // Fetch overtime policy rules (minimum request hours and monthly maximum cap)
        $minHours = 0.5;
        $maxMonthlyHours = null;

        $otPenaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
            ->where(function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)->orWhereNull('company_id');
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        if ($otPenaltyRule && is_array($otPenaltyRule->penalty_tiers)) {
            if (isset($otPenaltyRule->penalty_tiers['min_overtime_request_hours']) && $otPenaltyRule->penalty_tiers['min_overtime_request_hours'] !== '' && $otPenaltyRule->penalty_tiers['min_overtime_request_hours'] !== null) {
                $minHours = (float) $otPenaltyRule->penalty_tiers['min_overtime_request_hours'];
            }
            if (isset($otPenaltyRule->penalty_tiers['overtime_max_monthly_hours']) && $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'] !== '' && $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'] !== null) {
                $maxMonthlyHours = (float) $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'];
            }
        } else {
            $tenantId = $employee->tenant_id ?? auth()->user()?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null);
            $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
            if ($tenant && is_array($tenant->settings)) {
                if (isset($tenant->settings['min_overtime_request_hours']) && $tenant->settings['min_overtime_request_hours'] !== '' && $tenant->settings['min_overtime_request_hours'] !== null) {
                    $minHours = (float) $tenant->settings['min_overtime_request_hours'];
                }
                if (isset($tenant->settings['overtime_max_monthly_hours']) && $tenant->settings['overtime_max_monthly_hours'] !== '' && $tenant->settings['overtime_max_monthly_hours'] !== null) {
                    $maxMonthlyHours = (float) $tenant->settings['overtime_max_monthly_hours'];
                }
            }
        }

        if ($durationHours < $minHours) {
            return $this->sendError(__('hrms.overtime.duration_error', ['min' => $minHours]) ?? "Overtime duration must be at least {$minHours} hours.", 422);
        }

        if ($maxMonthlyHours !== null && $maxMonthlyHours > 0) {
            $reqDate = Carbon::parse($validated['date']);
            $existingMonthHours = (float) OvertimeRequest::where('employee_id', $employee->id)
                ->whereBetween('date', [$reqDate->copy()->startOfMonth()->toDateString(), $reqDate->copy()->endOfMonth()->toDateString()])
                ->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
                ->sum('duration_hours');

            if (($existingMonthHours + $durationHours) > $maxMonthlyHours) {
                $remainingHours = max(0.0, $maxMonthlyHours - $existingMonthHours);
                return $this->sendError("Monthly overtime limit of {$maxMonthlyHours} hours exceeded. You have already logged {$existingMonthHours} hrs this month (Remaining: {$remainingHours} hrs).", 422);
            }
        }

        $validated['duration_hours']          = $durationHours;
        $validated['approved_duration_hours'] = $durationHours;
        $validated['employee_id']             = $employee->id;
        $validated['company_id']              = $employee->company_id ?? (auth()->user()?->company_id ?? 1);

        $requestModel = DB::transaction(function () use ($validated, $request) {
            return $this->overtimeRepository->storeOvertimeRequest($validated, $request);
        });

        // Safely send notifications to HR Admins & Reporting Manager
        try {
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New Overtime Request',
                message: "{$employee->full_name} submitted an overtime request for {$durationHours} hrs.",
                actionUrl: $actionUrl,
                type: 'overtime_request',
                iconClass: 'feather-clock'
            );

            if ($employee->reportingManager && $employee->reportingManager->user_id) {
                \App\Services\Notification\NotificationService::sendToUser(
                    userId: $employee->reportingManager->user_id,
                    title: 'New Overtime Request',
                    message: "{$employee->full_name} submitted an overtime request for {$durationHours} hrs.",
                    actionUrl: $actionUrl,
                    type: 'overtime_request',
                    iconClass: 'feather-clock'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Overtime request notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($requestModel, 'Overtime request submitted successfully', 201);
    }

    public function approveRequest(Request $request, mixed $id): JsonResponse
    {
        return $this->updateStatus($request, $id, 'approved');
    }

    public function rejectRequest(Request $request, mixed $id): JsonResponse
    {
        return $this->updateStatus($request, $id, 'rejected');
    }

    public function updateStatus(Request $request, mixed $id, ?string $overrideAction = null): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can update overtime request status.', 403);
        }

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        // Self-approval prohibition
        $user = auth()->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $actorEmpId = $workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $overtimeRequest->employee_id) {
            return $this->sendError('Self-approval is prohibited. You cannot approve your own Overtime request.', 403);
        }

        if ($overtimeRequest->status === 'cancelled') {
            return $this->sendError('Cannot update status of a cancelled application.', 400);
        }

        $action = $overrideAction ?? $request->input('action');
        $approvedHours = $request->input('approved_duration_hours');

        if (!$action) {
            $validated = $request->validate([
                'action'                  => 'required|in:approved,rejected,pending',
                'rejection_reason'        => 'nullable|string|max:1000',
                'approved_duration_hours' => 'nullable|numeric|min:0.5',
            ]);
            $action        = $validated['action'];
            $reason        = $validated['rejection_reason'] ?? null;
            $approvedHours = $validated['approved_duration_hours'] ?? null;
        } else {
            $reason = $request->input('rejection_reason');
        }

        DB::transaction(function () use ($overtimeRequest, $action, $reason, $approvedHours, $request) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $this->overtimeRepository->updateStatus($locked, [
                'action'                  => $action,
                'rejection_reason'        => $reason,
                'approved_duration_hours' => $approvedHours,
            ], $request);
        });

        // Safely send employee notification
        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Request ' . ucfirst($action),
                    message: "Your overtime request for {$overtimeRequest->date} has been {$action}.",
                    actionUrl: $actionUrl,
                    type: 'overtime_' . $action,
                    iconClass: $action === 'approved' ? 'feather-check-circle' : 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Overtime status notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($overtimeRequest->fresh(), 'Overtime request status updated successfully');
    }

    public function withdrawRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $overtimeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$overtimeRequest->canWithdraw()) {
            return $this->sendError('Only pending requests can be withdrawn.', 422);
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
        });

        return $this->sendSuccess(['id' => (int)$id], 'Overtime request withdrawn successfully.');
    }

    public function requestCancellation(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $overtimeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$overtimeRequest->canRequestCancellation()) {
            return $this->sendError('Only approved requests can have a cancellation requested.', 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($overtimeRequest, $validated) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $updateData = ['status' => 'cancellation_requested'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('overtime_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = $validated['cancellation_reason'];
            } else {
                $updateData['rejection_reason'] = $validated['cancellation_reason'];
            }
            $locked->update($updateData);
        });

        try {
            $empName = $overtimeRequest->employee ? $overtimeRequest->employee->full_name : 'An employee';
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'Overtime Cancellation Requested',
                message: "{$empName} requested cancellation for an overtime request on {$overtimeRequest->date}.",
                actionUrl: $actionUrl,
                type: 'overtime_cancellation_requested',
                iconClass: 'feather-alert-circle'
            );
        } catch (\Throwable $e) {
            Log::warning('API Overtime cancellation notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($overtimeRequest->fresh(), 'Cancellation request submitted. Awaiting admin approval.');
    }

    public function approveCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can approve cancellations.', 403);
        }

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if ($overtimeRequest->status !== 'cancellation_requested') {
            return $this->sendError('This request does not have a pending cancellation request.', 422);
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $this->overtimeRepository->revertCompOffLeave($locked);
            $locked->update(['status' => 'cancelled']);
        });

        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Cancellation Approved',
                    message: "Your overtime cancellation request for {$overtimeRequest->date} has been approved.",
                    actionUrl: $actionUrl,
                    type: 'overtime_cancellation_approved',
                    iconClass: 'feather-check-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Overtime cancellation approval notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($overtimeRequest->fresh(), 'Overtime cancellation approved.');
    }

    public function denyCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can deny cancellations.', 403);
        }

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if ($overtimeRequest->status !== 'cancellation_requested') {
            return $this->sendError('This request does not have a pending cancellation request.', 422);
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $updateData = ['status' => 'approved'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('overtime_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = null;
            }
            $locked->update($updateData);
        });

        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Cancellation Denied',
                    message: "Your overtime cancellation request for {$overtimeRequest->date} was denied. Request remains approved.",
                    actionUrl: $actionUrl,
                    type: 'overtime_cancellation_denied',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Overtime cancellation denial notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($overtimeRequest->fresh(), 'Cancellation request denied. Request remains approved.');
    }

    public function destroy(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $overtimeRequest = OvertimeRequest::find($id);
        if (!$overtimeRequest) {
            return $this->sendError("Overtime request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin) {
            if ($overtimeRequest->employee_id !== $employee?->id) {
                return $this->sendError('Unauthorized action.', 403);
            }
            if ($overtimeRequest->status !== 'pending') {
                return $this->sendError('Only pending requests can be deleted.', 422);
            }
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['approved', 'cancellation_requested'])) {
                $this->overtimeRepository->revertCompOffLeave($locked);
            }
            $locked->delete();
        });

        return $this->sendSuccess(['id' => (int)$id], 'Overtime request deleted successfully.');
    }
}
