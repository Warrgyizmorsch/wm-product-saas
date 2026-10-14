<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Helpers\SessionConflictChecker;
use App\Domains\HRMS\Helpers\XlsxHelper;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\HRMS\Repositories\WfhRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WfhRequestController extends Controller
{
    public function __construct(
        private readonly WfhRequestRepositoryInterface $wfhRequestRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->wfhRequestRepository->getIndexData($request->all());

        return view('modules.hrms.wfh.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('start_date') && !$request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('start_date')]);
        }

        $validated = $request->validate([
            'employee_id'         => 'nullable|exists:employees,id',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'start_date_type'     => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'       => 'required|string|in:full_day,first_half,second_half',
            'reason'              => 'required|string|max:1000',
            'wfh_latitude'        => 'nullable|numeric|between:-90,90',
            'wfh_longitude'       => 'nullable|numeric|between:-180,180',
            'attachment'          => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts'   => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id',
        ]);

        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));
        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if (!$currentEmp) {
                abort(403, 'An employee profile is required to submit a WFH request.');
            }
            $validated['employee_id'] = $currentEmp->id;
        } elseif (empty($validated['employee_id'])) {
            $currentEmp = Employee::resolveForUser($user);
            if ($currentEmp) {
                $validated['employee_id'] = $currentEmp->id;
            } else {
                abort(422, 'Select an employee for this WFH request.');
            }
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee profile not found.');
        }

        // Calculate duration server-side from dates + session types
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? 'full_day';
        $endType   = $validated['end_date_type']   ?? 'full_day';
        $duration  = 0;

        if ($startDate->isSameDay($endDate)) {
            $duration = ($startType === 'full_day') ? 1.0 : 0.5;
        } else {
            $daysDiff = $startDate->diffInDays($endDate);
            if ($daysDiff === 1) {
                $duration  = ($startType === 'full_day') ? 1.0 : 0.5;
                $duration += ($endType   === 'full_day') ? 1.0 : 0.5;
            } else {
                $duration  = ($startType === 'full_day') ? 1.0 : 0.5;
                $duration += ($daysDiff - 1);
                $duration += ($endType   === 'full_day') ? 1.0 : 0.5;
            }
        }

        if ($duration < 0.5) {
            return redirect()->back()->withInput()->with('error', 'Duration cannot be less than 0.5 days.');
        }

        $validated['duration'] = $duration;

        // Session-aware conflict check (covers both Leave & WFH, same employee)
        $conflict = SessionConflictChecker::hasConflict(
            employeeId:   $employee->id,
            newStart:     $startDate,
            newEnd:       $endDate,
            newStartType: $startType,
            newEndType:   $endType
        );

        if ($conflict) {
            return redirect()->back()->withInput()->with('error', $conflict);
        }

        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id;

        $this->wfhRequestRepository->storeWfhRequest($validated, $request);

        try {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.wfh.applied', [
                'employee_name' => $employee->full_name,
                'date'          => $startDate->format('d M Y') . ($duration > 1 ? ' to ' . $endDate->format('d M Y') : ''),
                'reason'        => $validated['reason'] ?? '',
            ]);

            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New WFH Request',
                message: "{$employee->full_name} applied for {$duration} day(s) WFH.",
                actionUrl: route('hrms.wfh.index'),
                type: 'wfh_request',
                iconClass: 'feather-home'
            );

            if ($employee->reportingManager) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $employee->reportingManager,
                    title: 'New WFH Request Applied',
                    message: "{$employee->full_name} applied for {$duration} day(s) WFH.",
                    actionUrl: route('hrms.wfh.index'),
                    type: 'wfh_request',
                    iconClass: 'feather-home'
                );
            }

            if (!empty($validated['notified_contacts'])) {
                $contacts = Employee::whereIn('id', $validated['notified_contacts'])->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague WFH Notice',
                        message: "{$employee->full_name} will be working from home (" . $startDate->format('d M') . ($duration > 1 ? ' - ' . $endDate->format('d M') : '') . ").",
                        actionUrl: route('hrms.wfh.index'),
                        type: 'wfh_notice',
                        iconClass: 'feather-home'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WFH applied notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'WFH application submitted successfully.');
    }

    public function approve(Request $request, WfhRequest $wfhRequest): RedirectResponse
    {
        return $this->updateStatus($request, $wfhRequest, 'approved');
    }

    public function reject(Request $request, WfhRequest $wfhRequest): RedirectResponse
    {
        return $this->updateStatus($request, $wfhRequest, 'rejected');
    }

    public function updateStatus(Request $request, WfhRequest $wfhRequest, ?string $overrideAction = null): RedirectResponse
    {
        $user = $request->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $actorEmpId = $workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $wfhRequest->employee_id) {
            if (!$workflowService->allowsSelfApproval($user, $wfhRequest)) {
                abort(403, 'Self-approval is prohibited. You cannot approve your own WFH request.');
            }
        }

        abort_unless($workflowService->canApprove($user, $wfhRequest->employee, $wfhRequest), 403, 'Unauthorized to process WFH request.');

        if ($overrideAction && $wfhRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending WFH requests can be approved or rejected.');
        }

        if (in_array($wfhRequest->status, ['cancelled', 'cancellation_requested'], true)) {
            return redirect()->back()->with('error', 'This WFH application cannot be changed through the status workflow.');
        }

        $action = $overrideAction ?? $request->input('action');
        $validated = $request->validate([
            'action'           => $overrideAction ? 'nullable|in:approved,rejected,pending' : 'required|in:approved,rejected,pending',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);
        $action = $overrideAction ?? $validated['action'];
        $reason = $validated['rejection_reason'] ?? null;
        if ($action === 'rejected' && blank($reason)) {
            return redirect()->back()->withInput()->withErrors(['rejection_reason' => 'A rejection reason is required.']);
        }

        $this->wfhRequestRepository->updateStatus($wfhRequest, [
            'action'           => $action,
            'rejection_reason' => $reason,
        ], $request);

        $wfhDateStr = $wfhRequest->start_date ? \Carbon\Carbon::parse($wfhRequest->start_date)->format('d M Y') : now()->format('d M Y');

        try {
            if ($action === 'approved') {
                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.wfh.approved', [
                    'employee_name' => $wfhRequest->employee?->full_name ?? 'Employee',
                    'date'          => $wfhDateStr,
                    'approved_by'   => auth()->user()?->name ?? 'Manager',
                ]);
            } elseif ($action === 'rejected') {
                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.wfh.rejected', [
                    'employee_name' => $wfhRequest->employee?->full_name ?? 'Employee',
                    'date'          => $wfhDateStr,
                    'reason'        => $reason ?? 'No reason provided',
                    'rejected_by'   => auth()->user()?->name ?? 'Manager',
                ]);
            }

            if ($wfhRequest->employee) {
                $statusText = ucfirst($action);
                $iconClass = $action === 'approved' ? 'feather-check-circle' : 'feather-x-circle';
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $wfhRequest->employee,
                    title: "WFH Request {$statusText}",
                    message: "Your WFH request status is now {$statusText}." . ($action === 'rejected' && $reason ? " Reason: {$reason}" : ''),
                    actionUrl: route('hrms.wfh.index'),
                    type: 'wfh_' . $action,
                    iconClass: $iconClass
                );
            }

            if ($action === 'approved' && !empty($wfhRequest->notified_contacts)) {
                $contacts = Employee::whereIn('id', $wfhRequest->notified_contacts)->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague WFH Approved',
                        message: "{$wfhRequest->employee?->full_name}'s WFH ({$wfhDateStr}) has been approved.",
                        actionUrl: route('hrms.wfh.index'),
                        type: 'wfh_approved',
                        iconClass: 'feather-home'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WFH status update notification dispatch failed: ' . $e->getMessage());
        }

        $msg = match($action) {
            'approved' => 'WFH request approved successfully.',
            'rejected' => 'WFH request rejected successfully.',
            default    => 'WFH request status updated to pending.',
        };

        return redirect()->back()->with('success', $msg);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee: Withdraw (pending only)
    // ─────────────────────────────────────────────────────────────────────────

    public function withdraw(Request $request, WfhRequest $wfhRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $wfhRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$wfhRequest->canWithdraw()) {
            return redirect()->back()->with('error', 'Only pending applications can be withdrawn.');
        }

        $wfhRequest->delete();

        return redirect()->back()->with('success', 'WFH application withdrawn successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee: Request Cancellation (approved only)
    // ─────────────────────────────────────────────────────────────────────────

    public function requestCancellation(Request $request, WfhRequest $wfhRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $wfhRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$wfhRequest->canRequestCancellation()) {
            return redirect()->back()->with('error', 'Only approved applications can have a cancellation requested.');
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $wfhRequest->update([
            'status'              => 'cancellation_requested',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return redirect()->back()->with('success', 'Cancellation request submitted. Awaiting admin approval.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Approve Cancellation
    // ─────────────────────────────────────────────────────────────────────────

    public function approveCancellation(WfhRequest $wfhRequest): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage')), 403);

        if ($wfhRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This application does not have a pending cancellation request.');
        }

        $this->wfhRequestRepository->cancelWfhRequest($wfhRequest);

        return redirect()->back()->with('success', 'WFH cancellation approved.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Deny Cancellation (revert to approved)
    // ─────────────────────────────────────────────────────────────────────────

    public function denyCancellation(WfhRequest $wfhRequest): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage')), 403);

        if ($wfhRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This application does not have a pending cancellation request.');
        }

        $wfhRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Cancellation request denied. Application remains approved.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export WFH Requests to Excel
    // ─────────────────────────────────────────────────────────────────────────

    public function export(Request $request): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->wfhRequestRepository->export($request->all());
    }

    public function destroy(Request $request, WfhRequest $wfhRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));

        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $wfhRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
            if ($wfhRequest->status !== 'pending') {
                return redirect()->back()->with('error', 'Only pending applications can be deleted.');
            }
        }

        $wfhRequest->delete();

        return redirect()->back()->with('success', 'WFH application deleted successfully.');
    }
}
