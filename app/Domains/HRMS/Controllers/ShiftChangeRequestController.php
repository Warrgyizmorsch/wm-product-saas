<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftChangeRequestController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->shiftChangeRepository->getIndexData($request->all());

        return view('modules.hrms.shift-change.index', $data);
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

        if (!$employee) {
            return redirect()->back()->with('error', __('hrms.shift_change.profile_not_found'));
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
        $validated['company_id']  = $employee->company_id;

        $this->shiftChangeRepository->storeShiftChangeRequest($validated, $request);

        \App\Services\Notification\NotificationService::sendToHrAdmins(
            title: 'New Shift Change Request',
            message: "{$employee->full_name} submitted a shift change request.",
            actionUrl: \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index') : url('/hrms/shift-overtime'),
            type: 'shift_change_request',
            iconClass: 'feather-refresh-cw'
        );

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

        $this->shiftChangeRepository->updateStatus($shiftChangeRequest, [
            'action'           => $action,
            'rejection_reason' => $reason,
        ], $request);

        if ($shiftChangeRequest->employee_id) {
            \App\Services\Notification\NotificationService::sendToEmployee(
                employeeId: $shiftChangeRequest->employee_id,
                title: 'Shift Change Request ' . ucfirst($action),
                message: "Your shift change request has been {$action}.",
                actionUrl: \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index') : url('/hrms/shift-overtime'),
                type: 'shift_change_' . $action,
                iconClass: $action === 'approved' ? 'feather-check-circle' : 'feather-x-circle'
            );
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
            return redirect()->back()->with('error', 'Only pending requests can be withdrawn.');
        }

        $shiftChangeRequest->delete();

        return redirect()->back()->with('success', 'Shift change request withdrawn successfully.');
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
            return redirect()->back()->with('error', 'Only approved requests can have a cancellation requested.');
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $updateData = ['status' => 'cancellation_requested'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
            $updateData['cancellation_reason'] = $validated['cancellation_reason'];
        } else {
            $updateData['rejection_reason'] = $validated['cancellation_reason'];
        }

        $shiftChangeRequest->update($updateData);

        return redirect()->back()->with('success', 'Cancellation request submitted. Awaiting admin approval.');
    }

    public function approveCancellation(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        if ($shiftChangeRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This request does not have a pending cancellation request.');
        }

        $shiftChangeRequest->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Shift change cancellation approved.');
    }

    public function denyCancellation(ShiftChangeRequest $shiftChangeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        if ($shiftChangeRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This request does not have a pending cancellation request.');
        }

        $updateData = ['status' => 'approved'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('shift_change_requests', 'cancellation_reason')) {
            $updateData['cancellation_reason'] = null;
        }

        $shiftChangeRequest->update($updateData);

        return redirect()->back()->with('success', 'Cancellation request denied. Request remains approved.');
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
                return redirect()->back()->with('error', 'Only pending requests can be deleted.');
            }
        }

        $shiftChangeRequest->delete();

        return redirect()->back()->with('success', __('hrms.shift_change.deleted_successfully'));
    }
}
