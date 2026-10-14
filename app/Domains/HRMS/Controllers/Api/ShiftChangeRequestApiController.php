<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;

class ShiftChangeRequestApiController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository
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
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }

        return null;
    }

    /**
     * Helper to check if current user is HR Admin / Roster Manager.
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

        $query = ShiftChangeRequest::query();
        if (!$isHrAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        $totalRequests   = (clone $query)->count();
        $pendingRequests = (clone $query)->where('status', 'pending')->count();
        $approvedRequests= (clone $query)->where('status', 'approved')->count();
        $rejectedRequests= (clone $query)->where('status', 'rejected')->count();

        return $this->sendSuccess([
            'total_requests'    => $totalRequests,
            'pending_requests'  => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ], 'Shift Change requests summary loaded');
    }

    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->shiftChangeRepository->getIndexData($request->all());

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        if (isset($data['requests']) && method_exists($data['requests'], 'getCollection')) {
            $data['requests']->getCollection()->transform(function ($req) use ($employee, $isHrAdmin) {
                $isOwner = $employee && (int)$req->employee_id === (int)$employee->id;
                $isPending = $req->status === 'pending';
                $isApproved = $req->status === 'approved';
                $req->capabilities = [
                    'can_view'                 => true,
                    'can_approve'              => $isHrAdmin && $isPending,
                    'can_reject'               => $isHrAdmin && $isPending,
                    'can_cancel'               => ($isOwner || $isHrAdmin) && $isPending,
                    'can_request_cancellation' => ($isOwner || $isHrAdmin) && $isApproved,
                ];
                return $req;
            });
        }

        return $this->sendSuccess($data['requests'], 'Shift Change requests retrieved successfully');
    }

    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $shiftChangeRequest = ShiftChangeRequest::with(['employee', 'currentShift', 'requestedShift', 'approvedByEmployee'])->find($id);

        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $shiftChangeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized access to shift change request.', 403);
        }

        $isOwner = $employee && (int)$shiftChangeRequest->employee_id === (int)$employee->id;
        $isPending = $shiftChangeRequest->status === 'pending';
        $isApproved = $shiftChangeRequest->status === 'approved';
        $shiftChangeRequest->capabilities = [
            'can_view'                 => true,
            'can_approve'              => $isHrAdmin && $isPending,
            'can_reject'               => $isHrAdmin && $isPending,
            'can_cancel'               => ($isOwner || $isHrAdmin) && $isPending,
            'can_request_cancellation' => ($isOwner || $isHrAdmin) && $isApproved,
        ];

        return $this->sendSuccess($shiftChangeRequest, 'Shift Change request details loaded');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'        => 'nullable|integer|exists:employees,id',
            'type'               => 'required|string|in:temporary,permanent,recurring',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|required_if:type,temporary|date|after_or_equal:start_date',
            'recurring_days'     => 'nullable|required_if:type,recurring|array',
            'recurring_days.*'   => 'integer|min:0|max:6',
            'requested_shift_id' => 'nullable|exists:production_shifts,id',
            'reason'             => 'required|string|max:1000',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError('Employee record not found.', 404);
            }
            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $employee->id) {
                return $this->sendError('You can only submit a shift change request for your own employee profile.', 403);
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

        // Check for duplicate pending requests
        $existingPending = ShiftChangeRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'cancellation_requested'])
            ->where('start_date', $validated['start_date'])
            ->exists();
        if ($existingPending) {
            return $this->sendError('A pending shift change request already exists for this start date.', 422);
        }

        // Set current shift
        $targetDate = Carbon::parse($validated['start_date']);
        $currentShift = $employee->resolveShiftForDate($targetDate);
        $currentShiftId = $currentShift ? $currentShift->id : null;

        // Prevent requesting same shift
        $requestedShiftId = $validated['requested_shift_id'] ?? null;
        if ($requestedShiftId && (int)$requestedShiftId === (int)$currentShiftId) {
            return $this->sendError(__('hrms.shift_change.same_shift_error') ?? 'You are already scheduled for this shift on the start date.', 422);
        }
        if (!$requestedShiftId && !$currentShiftId) {
            return $this->sendError(__('hrms.shift_change.same_day_off_error') ?? 'The employee is already off on this day.', 422);
        }

        $validated['current_shift_id'] = $currentShiftId;
        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id ?? (auth()->user()?->company_id ?? 1);

        $requestModel = DB::transaction(function () use ($validated, $request) {
            return $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);
        });

        // Safely send notifications
        try {
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift']) : url('/hrms/shift-overtime?tab=shift');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New Shift Change Request',
                message: "{$employee->full_name} submitted a shift change request.",
                actionUrl: $actionUrl,
                type: 'shift_change_request',
                iconClass: 'feather-refresh-cw'
            );

            if ($employee->reportingManager && $employee->reportingManager->user_id) {
                \App\Services\Notification\NotificationService::sendToUser(
                    userId: $employee->reportingManager->user_id,
                    title: 'New Shift Change Request',
                    message: "{$employee->full_name} submitted a shift change request.",
                    actionUrl: $actionUrl,
                    type: 'shift_change_request',
                    iconClass: 'feather-refresh-cw'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Shift change notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($requestModel, 'Shift Change request submitted successfully', 201);
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
            return $this->sendError('Unauthorized action. Only HR Admins can update shift change request status.', 403);
        }

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        // Self-approval prohibition
        $user = auth()->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $actorEmpId = $workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $shiftChangeRequest->employee_id) {
            return $this->sendError('Self-approval is prohibited. You cannot approve your own shift change request.', 403);
        }

        if ($shiftChangeRequest->status === 'cancelled') {
            return $this->sendError('Cannot update the status of a cancelled application.', 400);
        }

        $action = $overrideAction ?? $request->input('action');
        $reason = $request->input('rejection_reason');

        if (!$action) {
            $validated = $request->validate([
                'action'           => 'required|in:approved,rejected,pending',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);
            $action = $validated['action'];
            $reason = $validated['rejection_reason'] ?? null;
        }

        DB::transaction(function () use ($shiftChangeRequest, $action, $reason, $request) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $this->shiftChangeRepository->updateStatus($locked, [
                'action'           => $action,
                'rejection_reason' => $reason,
            ], $request);
        });

        // Safely send employee notification
        try {
            if ($shiftChangeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift']) : url('/hrms/shift-overtime?tab=shift');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $shiftChangeRequest->employee_id,
                    title: 'Shift Change Request ' . ucfirst($action),
                    message: "Your shift change request has been {$action}.",
                    actionUrl: $actionUrl,
                    type: 'shift_change_' . $action,
                    iconClass: $action === 'approved' ? 'feather-check-circle' : 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Shift change status notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($shiftChangeRequest->fresh(), 'Shift Change request status updated successfully');
    }

    public function withdrawRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $shiftChangeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$shiftChangeRequest->canWithdraw()) {
            return $this->sendError('Only pending requests can be withdrawn.', 422);
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
        });

        return $this->sendSuccess(['id' => (int)$id], 'Shift change request withdrawn successfully.');
    }

    public function requestCancellation(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $shiftChangeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$shiftChangeRequest->canRequestCancellation()) {
            return $this->sendError('Only approved requests can have a cancellation requested.', 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($shiftChangeRequest, $validated) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $updateData = ['status' => 'cancellation_requested'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = $validated['cancellation_reason'];
            } else {
                $updateData['rejection_reason'] = $validated['cancellation_reason'];
            }
            $locked->update($updateData);
        });

        try {
            $empName = $shiftChangeRequest->employee ? $shiftChangeRequest->employee->full_name : 'An employee';
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift']) : url('/hrms/shift-overtime?tab=shift');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'Shift Change Cancellation Requested',
                message: "{$empName} requested cancellation for a shift change request.",
                actionUrl: $actionUrl,
                type: 'shift_change_cancellation_requested',
                iconClass: 'feather-alert-circle'
            );
        } catch (\Throwable $e) {
            Log::warning('API Shift change cancellation notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($shiftChangeRequest->fresh(), 'Cancellation request submitted. Awaiting admin approval.');
    }

    public function approveCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can approve cancellations.', 403);
        }

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if ($shiftChangeRequest->status !== 'cancellation_requested') {
            return $this->sendError('This request does not have a pending cancellation request.', 422);
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => 'cancelled']);
            $this->shiftChangeRepository->revertShiftChange($locked);
        });

        try {
            if ($shiftChangeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift']) : url('/hrms/shift-overtime?tab=shift');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $shiftChangeRequest->employee_id,
                    title: 'Shift Change Cancellation Approved',
                    message: 'Your shift change cancellation request has been approved.',
                    actionUrl: $actionUrl,
                    type: 'shift_change_cancellation_approved',
                    iconClass: 'feather-check-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Shift change cancellation approval notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($shiftChangeRequest->fresh(), 'Shift change cancellation approved.');
    }

    public function denyCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can deny cancellations.', 403);
        }

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if ($shiftChangeRequest->status !== 'cancellation_requested') {
            return $this->sendError('This request does not have a pending cancellation request.', 422);
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $updateData = ['status' => 'approved'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = null;
            }
            $locked->update($updateData);
        });

        try {
            if ($shiftChangeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift']) : url('/hrms/shift-overtime?tab=shift');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $shiftChangeRequest->employee_id,
                    title: 'Shift Change Cancellation Denied',
                    message: 'Your shift change cancellation request was denied. Request remains approved.',
                    actionUrl: $actionUrl,
                    type: 'shift_change_cancellation_denied',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('API Shift change cancellation denial notification failed: ' . $e->getMessage());
        }

        return $this->sendSuccess($shiftChangeRequest->fresh(), 'Cancellation request denied. Request remains approved.');
    }

    public function destroy(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $shiftChangeRequest = ShiftChangeRequest::find($id);
        if (!$shiftChangeRequest) {
            return $this->sendError("Shift Change request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $shiftChangeRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$isHrAdmin && $shiftChangeRequest->status !== 'pending') {
            return $this->sendError('Only pending requests can be deleted.', 422);
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'approved') {
                $this->shiftChangeRepository->revertShiftChange($locked);
            }
            $locked->delete();
        });

        return $this->sendSuccess(['id' => (int)$id], 'Shift Change request deleted successfully.');
    }
}
