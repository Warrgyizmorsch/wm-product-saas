<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class ShiftChangeRequestController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('hrms.shift-overtime.index', array_merge(['tab' => 'shift'], $request->all()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'type'               => 'required|string|in:temporary,permanent,recurring',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|required_if:type,temporary|date|after_or_equal:start_date',
            'recurring_days'     => 'nullable|required_if:type,recurring|array',
            'recurring_days.*'   => 'integer|min:0|max:6',
            'requested_shift_id' => 'nullable|exists:production_shifts,id',
            'reason'             => 'required|string|max:1000',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if ($currentEmp) {
                $validated['employee_id'] = $currentEmp->id;
            }
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        // Check for duplicate pending requests for the same employee and start date
        $existingPending = ShiftChangeRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'cancellation_requested'])
            ->where('start_date', $validated['start_date'])
            ->exists();
        if ($existingPending) {
            return redirect()->back()->with('error', __('hrms.shift_change.duplicate_pending_error'))->withInput();
        }

        // Set current shift
        $targetDate = Carbon::parse($validated['start_date']);
        $currentShift = $employee->resolveShiftForDate($targetDate);
        $currentShiftId = $currentShift ? $currentShift->id : null;

        // Prevent requesting same shift
        $requestedShiftId = $validated['requested_shift_id'] ?? null;
        if ($requestedShiftId && (int)$requestedShiftId === (int)$currentShiftId) {
            return redirect()->back()->with('error', __('hrms.shift_change.same_shift_error'))->withInput();
        }
        if (!$requestedShiftId && !$currentShiftId) {
            return redirect()->back()->with('error', __('hrms.shift_change.same_day_off_error'))->withInput();
        }

        $validated['current_shift_id'] = $currentShiftId;
        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id ?? ($user?->company_id ?? 1);

        DB::transaction(function () use ($validated, $request) {
            return $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);
        });

        // Safely notify HR Admins and Reporting Manager
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
            Log::warning('Shift change request notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.shift_change.submitted_successfully'));
    }

    public function approve(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $shiftChangeRequest, 'approved');
    }

    public function reject(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $shiftChangeRequest, 'rejected');
    }

    public function updateStatus(Request $request, ShiftChangeRequest $shiftChangeRequest, ?string $overrideAction = null): RedirectResponse
    {
        $user = $request->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $actorEmpId = $workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $shiftChangeRequest->employee_id) {
            abort(403, 'Self-approval is prohibited. You cannot approve your own shift change request.');
        }

        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        if ($shiftChangeRequest->status === 'cancelled') {
            return redirect()->back()->with('error', __('hrms.shift_change.cancelled_status_error'));
        }

        $action = $overrideAction ?? $request->input('action');
        if (!$action) {
            $validated = $request->validate([
                'action'           => 'required|in:approved,rejected,pending',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);
            $action = $validated['action'];
            $reason = $validated['rejection_reason'] ?? null;
        } else {
            $reason = $request->input('rejection_reason');
        }

        DB::transaction(function () use ($shiftChangeRequest, $action, $reason, $request) {
            $lockedRequest = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            $this->shiftChangeRepository->updateStatus($lockedRequest, [
                'action'           => $action,
                'rejection_reason' => $reason,
            ], $request);
        });

        // Safely notify Employee
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
            Log::warning('Shift change status notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.shift_change.status_updated'));
    }

    public function withdraw(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $shiftChangeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$shiftChangeRequest->canWithdraw()) {
            return redirect()->back()->with('error', __('hrms.shift_change.withdraw_only_pending'));
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canWithdraw()) {
                throw new \RuntimeException('Only pending requests can be withdrawn.');
            }
            $locked->delete();
        });

        return redirect()->back()->with('success', __('hrms.shift_change.withdraw_success'));
    }

    public function requestCancellation(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $shiftChangeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$shiftChangeRequest->canRequestCancellation()) {
            return redirect()->back()->with('error', __('hrms.shift_change.only_approved_can_cancel'));
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($shiftChangeRequest, $validated) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canRequestCancellation()) {
                throw new \RuntimeException('Only approved requests can have a cancellation requested.');
            }

            $updateData = ['status' => 'cancellation_requested'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = $validated['cancellation_reason'];
            } else {
                $updateData['rejection_reason'] = $validated['cancellation_reason'];
            }

            $locked->update($updateData);
        });

        // Safely notify HR Admins
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
            Log::warning('Shift change cancellation notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.shift_change.cancellation_submitted'));
    }

    public function approveCancellation(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'cancellation_requested') {
                throw new \RuntimeException(__('hrms.shift_change.no_pending_cancellation'));
            }

            $locked->update(['status' => 'cancelled']);

            // Revert shift roster records that were generated upon approval
            $this->shiftChangeRepository->revertShiftChange($locked);
        });

        // Safely notify Employee
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
            Log::warning('Shift change cancellation approval notification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.shift_change.cancellation_approved'));
    }

    public function denyCancellation(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'cancellation_requested') {
                throw new \RuntimeException(__('hrms.shift_change.no_pending_cancellation'));
            }

            $updateData = ['status' => 'approved'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = null;
            }

            $locked->update($updateData);
        });

        // Safely notify Employee
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
            Log::warning('Shift change cancellation denial notification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.shift_change.cancellation_denied'));
    }

    public function destroy(Request $request, ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $shiftChangeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
            if ($shiftChangeRequest->status !== 'pending') {
                return redirect()->back()->with('error', __('hrms.shift_change.delete_only_pending'));
            }
        }

        DB::transaction(function () use ($shiftChangeRequest) {
            $locked = ShiftChangeRequest::whereKey($shiftChangeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'approved') {
                $this->shiftChangeRepository->revertShiftChange($locked);
            }
            $locked->delete();
        });

        return redirect()->back()->with('success', __('hrms.shift_change.deleted_successfully'));
    }
}
