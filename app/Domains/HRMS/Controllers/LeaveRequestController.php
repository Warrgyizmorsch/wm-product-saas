<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Helpers\SessionConflictChecker;
use App\Domains\HRMS\Helpers\XlsxHelper;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository
    ) {}

    public function index(Request $request): View
    {
        $data = $this->leaveRequestRepository->getIndexData($request->all());

        return view('modules.hrms.leaves.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'leave_type_id'    => 'required|exists:leave_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'start_date_type'  => 'nullable|string|in:full_day,first_half,second_half',
            'end_date_type'    => 'nullable|string|in:full_day,first_half,second_half',
            'session'          => 'nullable|string|in:full_day,first_half,second_half',
            'reason'           => 'required|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts'   => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id',
        ]);

        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if ($currentEmp) {
                $validated['employee_id'] = $currentEmp->id;
            }
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        $leaveType = \App\Domains\HRMS\Models\LeaveType::with('plan')->findOrFail($validated['leave_type_id']);
        if ($leaveType->plan && !$leaveType->plan->status) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.plan_inactive'));
        }

        $rules        = $leaveType->rules ?? [];
        $appRules     = $rules['application'] ?? [];
        $sandwichRule = !empty($appRules['sandwich_rule']);

        // Calculate duration server-side from dates + session types, respecting Sandwich Rule policy
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? 'full_day';
        $endType   = $validated['end_date_type']   ?? 'full_day';
        $duration  = 0.0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));

            if ($sandwichRule) {
                if ($startDate->isSameDay($endDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->isSameDay($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->isSameDay($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                }
            } else {
                if (!$isHoliday && $isActiveWorkDay) {
                    if ($startDate->isSameDay($endDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($date->isSameDay($startDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($date->isSameDay($endDate)) {
                        $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        $duration += 1.0;
                    }
                }
            }
        }

        if ($duration < 0.5) {
            return redirect()->back()->withInput()->with('error', 'Duration cannot be less than 0.5 days. Ensure you are not applying for leave entirely on weekends or holidays.');
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

        // Probation Restriction
        $probationRules = $rules['probation'] ?? [];
        $probRule = $probationRules['rule'] ?? ($probationRules['probation_rule'] ?? 'allow');
        $doj = $employee->date_of_joining;
        if ($probRule === 'disallow' && ($employee->employee_stage === 'Probation' || $employee->employment_status === 'probation')) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.probation_restricted'));
        }
        if ($probRule === 'allow_after_months') {
            $requiredMonths = intval($probationRules['months'] ?? ($probationRules['probation_months'] ?? 3));
            if ($doj && Carbon::parse($doj)->addMonths($requiredMonths)->isFuture()) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.probation_months_restricted', ['months' => $requiredMonths]));
            }
        }

        // Notice Period Restriction
        $noticeRules = $rules['notice'] ?? [];
        $noticeRule = $noticeRules['rule'] ?? ($noticeRules['notice_rule'] ?? 'allow');
        if ($noticeRule === 'disallow' && ($employee->employee_stage === 'Notice Period' || $employee->employment_status === 'notice')) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.notice_restricted'));
        }

        // Apply in Advance Rule
        if (!empty($appRules['apply_in_advance'])) {
            $advanceDays = intval($appRules['advance_days'] ?? 3);
            $minAllowedDate = Carbon::today()->addDays($advanceDays);
            if ($startDate->lt($minAllowedDate)) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.advance_restricted', ['days' => $advanceDays, 'date' => $minAllowedDate->format('Y-m-d')]));
            }
        }

        // Duration Limits
        $minDuration = floatval($appRules['min_duration'] ?? 0.5);
        $maxDuration = floatval($appRules['max_duration'] ?? 365);
        if ($minDuration > 0 && $duration < $minDuration) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.min_duration_restricted', ['min' => $minDuration]));
        }
        if ($maxDuration > 0 && $duration > $maxDuration) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.max_duration_restricted', ['max' => $maxDuration]));
        }

        // Attachment Requirement
        if (!empty($appRules['require_attachment'])) {
            $attachmentDays = intval($appRules['attachment_days'] ?? 3);
            if ($duration >= $attachmentDays && !$request->hasFile('attachment')) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.attachment_required', ['days' => $attachmentDays]));
            }
        }

        // Leave Balance Availability Check (respecting Negative Leave balance policy)
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            $remaining = $balance ? floatval($balance->remaining) : 0.0;
            $allowNegative = !empty($rules['accrual']['allow_negative']);
            $maxNegative = floatval($rules['accrual']['max_negative'] ?? 0.0);

            if ($allowNegative) {
                $maxAllowedDuration = $remaining + $maxNegative;
                if ($duration > $maxAllowedDuration) {
                    return redirect()->back()->withInput()->with('error', __('hrms.leave.app.insufficient_balance_negative', [
                        'remaining'    => $remaining,
                        'max_negative' => $maxNegative,
                        'allowed'      => max(0.0, $maxAllowedDuration),
                        'duration'     => $duration
                    ]));
                }
            } else {
                if ($duration > $remaining) {
                    return redirect()->back()->withInput()->with('error', __('hrms.leave.app.insufficient_balance', ['remaining' => $remaining, 'duration' => $duration]));
                }
            }
        }

        $validated['company_id'] = $employee->company_id;

        $leaveRequest = $this->leaveRequestRepository->storeLeaveRequest($validated, $request);

        \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.leave.applied', [
            'employee_name' => $employee->full_name,
            'leave_type'    => $leaveRequest->leaveType?->name ?? 'Leave',
            'from_date'     => $startDate->format('d M Y'),
            'to_date'       => $endDate->format('d M Y'),
            'days'          => (string) $duration,
        ]);

        // Send Notifications
        \App\Services\Notification\NotificationService::sendToHrAdmins(
            title: 'New Leave Request',
            message: "{$employee->full_name} applied for {$duration} day(s) leave ({$startDate->format('M d')} - {$endDate->format('M d')}).",
            actionUrl: route('hrms.leaves.index'),
            type: 'leave_request',
            iconClass: 'feather-calendar'
        );
        if ($employee->reportingManager) {
        \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $employee->reportingManager,
                title: 'New Leave Request Applied',
                message: "{$employee->full_name} applied for {$duration} day(s) leave.",
                actionUrl: route('hrms.leaves.index'),
                type: 'leave_request',
                iconClass: 'feather-calendar'
            );
        }

        return redirect()->back()->with('success', __('hrms.leave.app.submitted_successfully'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $request->merge(['action' => 'approved']);
        return $this->updateStatus($request, $leaveRequest);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $request->merge(['action' => 'rejected']);
        return $this->updateStatus($request, $leaveRequest);
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($this->canApproveLeaveRequest($request->user(), $leaveRequest), 403, 'Unauthorized to process leave request at current level.');

        if ($leaveRequest->status === 'cancelled') {
            return redirect()->back()->with('error', 'Cannot change the status of a cancelled leave application.');
        }

        if (!$request->has('action') && $request->has('status')) {
            $request->merge(['action' => $request->input('status')]);
        }

        $validated = $request->validate([
            'action'           => 'required|in:approved,rejected,pending,unauthorized,unpaid',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $this->leaveRequestRepository->updateStatus($leaveRequest, $validated, $request);

        $startFormatted = \Carbon\Carbon::parse($leaveRequest->start_date)->format('d M Y');
        $endFormatted = \Carbon\Carbon::parse($leaveRequest->end_date)->format('d M Y');

        if ($validated['action'] === 'approved') {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.leave.approved', [
                'employee_name' => $leaveRequest->employee?->full_name ?? 'Employee',
                'leave_type'    => $leaveRequest->leaveType?->name ?? 'Leave',
                'from_date'     => $startFormatted,
                'to_date'       => $endFormatted,
                'days'          => (string) ($leaveRequest->duration ?? 1),
                'approved_by'   => auth()->user()?->name ?? 'Manager',
            ]);
        } elseif ($validated['action'] === 'rejected') {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.leave.rejected', [
                'employee_name' => $leaveRequest->employee?->full_name ?? 'Employee',
                'leave_type'    => $leaveRequest->leaveType?->name ?? 'Leave',
                'from_date'     => $startFormatted,
                'to_date'       => $endFormatted,
                'reason'        => $validated['rejection_reason'] ?? 'Not specified',
                'rejected_by'   => auth()->user()?->name ?? 'Manager',
            ]);
        }

        // Send Status Notification to Employee
        if ($leaveRequest->employee) {
            $statusText = ucfirst($validated['action']);
            $iconClass = $validated['action'] === 'approved' ? 'feather-check-circle' : 'feather-x-circle';
        \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $leaveRequest->employee,
                title: "Leave Request {$statusText}",
                message: "Your leave application ({$startFormatted} to {$endFormatted}) status is now {$statusText}.",
                actionUrl: route('hrms.leaves.index'),
                type: 'leave_' . $validated['action'],
                iconClass: $iconClass
            );
        }

        $statusLabels = [
            'approved'     => __('hrms.leave.app.approved_successfully') ?? 'Leave application approved successfully.',
            'rejected'     => __('hrms.leave.app.rejected_successfully') ?? 'Leave application rejected successfully.',
            'pending'      => 'Leave application set to pending successfully.',
            'unauthorized' => 'Leave application set to unauthorized successfully.',
            'unpaid'       => 'Leave application set to unpaid successfully.',
        ];
        $msg = $statusLabels[$validated['action']] ?? 'Leave application status updated successfully.';

        return redirect()->back()->with('success', $msg);
    }

    public function getRules(Request $request): JsonResponse
    {
        $employeeId  = $request->integer('employee_id');
        $leaveTypeId = $request->integer('leave_type_id');

        $rules = $this->leaveRequestRepository->getPolicyRules($employeeId, $leaveTypeId);

        return response()->json($rules);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee: Withdraw (pending only)
    // ─────────────────────────────────────────────────────────────────────────

    public function withdraw(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $leaveRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$leaveRequest->canWithdraw()) {
            return redirect()->back()->with('error', 'Only pending applications can be withdrawn.');
        }

        $leaveRequest->delete();

        return redirect()->back()->with('success', 'Leave application withdrawn successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee: Request Cancellation (approved only)
    // ─────────────────────────────────────────────────────────────────────────

    public function requestCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $leaveRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$leaveRequest->canRequestCancellation()) {
            return redirect()->back()->with('error', 'Only approved applications can have a cancellation requested.');
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $leaveRequest->update([
            'status'              => 'cancellation_requested',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return redirect()->back()->with('success', 'Cancellation request submitted. Awaiting admin approval.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Approve Cancellation
    // ─────────────────────────────────────────────────────────────────────────

    public function approveCancellation(LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($leaveRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This application does not have a pending cancellation request.');
        }

        // Cancel and restore the leave balance
        $this->leaveRequestRepository->cancelLeaveRequest($leaveRequest);

        return redirect()->back()->with('success', 'Leave cancellation approved. Balance has been restored.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Deny Cancellation (revert to approved)
    // ─────────────────────────────────────────────────────────────────────────

    public function denyCancellation(LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($leaveRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This application does not have a pending cancellation request.');
        }

        $leaveRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Cancellation request denied. Application remains approved.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export Leave Applications to Excel
    // ─────────────────────────────────────────────────────────────────────────

    public function export(Request $request): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->leaveRequestRepository->export($request->all());
    }

    public function calculateDuration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'leave_type_id'   => 'nullable|integer',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'start_date_type' => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'   => 'required|string|in:full_day,first_half,second_half',
        ]);

        $employee  = Employee::findOrFail($validated['employee_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'];
        $endType   = $validated['end_date_type'];

        $leaveType = !empty($validated['leave_type_id']) ? \App\Domains\HRMS\Models\LeaveType::find($validated['leave_type_id']) : null;
        $sandwichRule = $leaveType && !empty($leaveType->rules['application']['sandwich_rule']);

        $duration     = 0.0;
        $holidays     = [];
        $restDays     = [];
        $sandwichDays = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));
            $isNonWorkDay = $isHoliday || !$isActiveWorkDay;

            if ($sandwichRule) {
                if ($isHoliday) {
                    $holidays[] = $dateStr;
                }
                if (!$isActiveWorkDay) {
                    $restDays[] = $dateStr;
                }

                if ($startDate->isSameDay($endDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->isSameDay($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->isSameDay($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                    if ($isNonWorkDay) {
                        $sandwichDays[] = $dateStr;
                    }
                }
            } else {
                if ($isHoliday) {
                    $holidays[] = $dateStr;
                } elseif (!$isActiveWorkDay) {
                    $restDays[] = $dateStr;
                } else {
                    if ($startDate->isSameDay($endDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($date->isSameDay($startDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($date->isSameDay($endDate)) {
                        $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        $duration += 1.0;
                    }
                }
            }
        }

        return response()->json([
            'success'       => true,
            'duration'      => $duration,
            'holidays'      => $holidays,
            'rest_days'     => $restDays,
            'sandwich_rule' => $sandwichRule,
            'sandwich_days' => $sandwichDays,
        ]);
    }

    public function canApproveLeaveRequest(?\App\Models\User $user, LeaveRequest $leaveRequest): bool
    {
        if (!$user || !$leaveRequest->employee) {
            return false;
        }

        return app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class)->canApprove($user, $leaveRequest->employee, $leaveRequest);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));

        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $leaveRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
            if ($leaveRequest->status !== 'pending') {
                return redirect()->back()->with('error', 'Only pending applications can be deleted.');
            }
        }

        // If it was approved or cancellation was requested, restore leave balance before deletion
        if (in_array($leaveRequest->status, ['approved', 'cancellation_requested'])) {
            $this->leaveRequestRepository->cancelLeaveRequest($leaveRequest);
        }

        $leaveRequest->delete();

        return redirect()->back()->with('success', 'Leave application deleted successfully.');
    }
}


