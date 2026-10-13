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
        if ($request->filled('start_date') && !$request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('start_date')]);
        }

        $validated = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'leave_type_id'    => 'required|exists:leave_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'start_date_type'  => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'end_date_type'    => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'hours'            => 'nullable|numeric|min:0.5|max:24',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'session'          => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'reason'           => 'required|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts'   => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id',
        ]);

        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if (!$currentEmp) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.emp_not_found'));
            }
            $validated['employee_id'] = $currentEmp->id;
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        $leaveType = \App\Domains\HRMS\Models\LeaveType::with('plan')->findOrFail($validated['leave_type_id']);
        if ($leaveType->leave_plan_id && (int) $employee->leave_plan_id !== (int) $leaveType->leave_plan_id) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.invalid_leave_type_for_employee'));
        }
        if ($leaveType->plan && !$leaveType->plan->status) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.plan_inactive'));
        }

        $rules        = $leaveType->rules ?? [];
        $appRules     = $rules['application'] ?? [];
        $sandwichRule = !empty($appRules['sandwich_rule']);

        $isHourly = !empty($rules['accrual']['calculate_in']) && $rules['accrual']['calculate_in'] === 'hours';

        // Calculate duration server-side from dates + session types/hours, respecting Sandwich Rule policy
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? ($isHourly ? 'hourly' : 'full_day');
        $endType   = $validated['end_date_type']   ?? ($isHourly ? 'hourly' : 'full_day');
        $duration  = 0.0;

        if ($isHourly) {
            $validated['end_date'] = $validated['start_date'];
            $endDate = $startDate->copy();

            if (!empty($validated['start_time']) && !empty($validated['end_time'])) {
                if (Carbon::parse($validated['end_time'])->lte(Carbon::parse($validated['start_time']))) {
                    return redirect()->back()->withInput()->with('error', __('hrms.leave.app.time_validation_error'));
                }
            }

            $duration = round(floatval($request->input('hours', 1.0)) * 2) / 2;
            if ($duration < 0.5) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.min_hourly_error'));
            }
            $startType = 'hourly';
            $endType = 'hourly';
            $validated['start_date_type'] = 'hourly';
            $validated['end_date_type'] = 'hourly';
            $validated['session'] = 'hourly';
        } else {
            // Clear hourly time fields for full-day / multi-day leaves to prevent data pollution
            $validated['start_time'] = null;
            $validated['end_time']   = null;
            $validated['hours']      = null;
            // Collect all dates in range and identify work days vs non-work days
            $daysInRange = [];
            $firstWorkDay = null;
            $lastWorkDay = null;

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
                $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));
                $isWorkDay = !$isHoliday && $isActiveWorkDay;

                $daysInRange[] = [
                    'date' => $date->copy(),
                    'isWorkDay' => $isWorkDay,
                ];

                if ($isWorkDay) {
                    if ($firstWorkDay === null) {
                        $firstWorkDay = $date->copy();
                    }
                    $lastWorkDay = $date->copy();
                }
            }

            foreach ($daysInRange as $d) {
                $currDate = $d['date'];
                if ($d['isWorkDay']) {
                    if ($startDate->isSameDay($endDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($currDate->isSameDay($startDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($currDate->isSameDay($endDate)) {
                        $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        $duration += 1.0;
                    }
                } else {
                    // Non-working day (Holiday or Rest Day)
                    if ($sandwichRule && $firstWorkDay && $lastWorkDay && $currDate->gt($firstWorkDay) && $currDate->lt($lastWorkDay)) {
                        // Sandwiched between working days
                        $duration += 1.0;
                    }
                }
            }

            if ($duration < 0.5) {
                return redirect()->back()->withInput()->with('error', __('hrms.leave.app.duration_zero_error'));
            }
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
        $minDuration = floatval($appRules['min_duration'] ?? ($isHourly ? 0.5 : 0.5));
        $maxDuration = floatval($appRules['max_duration'] ?? ($isHourly ? 24.0 : 365.0));
        $unitLabel = $isHourly ? __('hrms.leave.app.hours') : __('hrms.leave.days');
        if ($minDuration > 0 && $duration < $minDuration) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.duration_min_error', ['duration' => $duration, 'unit' => $unitLabel, 'min' => $minDuration]));
        }
        if ($maxDuration > 0 && $duration > $maxDuration) {
            return redirect()->back()->withInput()->with('error', __('hrms.leave.app.duration_max_error', ['duration' => $duration, 'unit' => $unitLabel, 'max' => $maxDuration]));
        }

        // Attachment Requirement (for day-based leave applications)
        if (!$isHourly && !empty($appRules['require_attachment'])) {
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

            if (!$balance) {
                $quota = floatval($rules['accrual']['quota_value'] ?? ($leaveType->quota ?? 0.0));
                $balance = \App\Domains\HRMS\Models\LeaveBalance::firstOrCreate([
                    'tenant_id'     => $employee->tenant_id,
                    'company_id'    => $employee->company_id,
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $leaveType->id,
                ], [
                    'allocated' => $quota,
                    'used'      => 0.0,
                    'encashed'  => 0.0,
                ]);
            }

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

        try {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.leave.applied', [
                'employee_name' => $employee->full_name,
                'leave_type'    => $leaveRequest->leaveType?->name ?? 'Leave',
                'from_date'     => $startDate->format('d M Y'),
                'to_date'       => $endDate->format('d M Y'),
                'days'          => (string) $duration,
            ]);

            $unitDisplay = $isHourly ? 'hour(s)' : 'day(s)';

            // Send Notifications
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New Leave Request',
                message: "{$employee->full_name} applied for {$duration} {$unitDisplay} leave ({$startDate->format('M d')} - {$endDate->format('M d')}).",
                actionUrl: route('hrms.leaves.index'),
                type: 'leave_request',
                iconClass: 'feather-calendar'
            );
            if ($employee->reportingManager) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $employee->reportingManager,
                    title: 'New Leave Request Applied',
                    message: "{$employee->full_name} applied for {$duration} {$unitDisplay} leave.",
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_request',
                    iconClass: 'feather-calendar'
                );
            }

            if (!empty($validated['notified_contacts'])) {
                $contacts = Employee::whereIn('id', $validated['notified_contacts'])->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague Leave Notice',
                        message: "{$employee->full_name} will be on leave ({$startDate->format('M d')} - {$endDate->format('M d')}).",
                        actionUrl: route('hrms.leaves.index'),
                        type: 'leave_request',
                        iconClass: 'feather-calendar'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave applied notification dispatch failed: ' . $e->getMessage());
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
            return redirect()->back()->with('error', __('hrms.leave.app.status_change_cancelled_error'));
        }

        if (!$request->has('action') && $request->has('status')) {
            $request->merge(['action' => $request->input('status')]);
        }

        $validated = $request->validate([
            'action'           => 'required|in:approved,rejected,pending,unauthorized,unpaid',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->leaveRequestRepository->updateStatus($leaveRequest, $validated, $request);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return redirect()->back()->withErrors($ve->errors());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: 'An error occurred while updating application status.');
        }

        $startFormatted = \Carbon\Carbon::parse($leaveRequest->start_date)->format('d M Y');
        $endFormatted = \Carbon\Carbon::parse($leaveRequest->end_date)->format('d M Y');

        try {
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

            if ($validated['action'] === 'approved' && !empty($leaveRequest->notified_contacts)) {
                $contacts = Employee::whereIn('id', $leaveRequest->notified_contacts)->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague Leave Approved',
                        message: "{$leaveRequest->employee?->full_name}'s leave ({$startFormatted} - {$endFormatted}) has been approved.",
                        actionUrl: route('hrms.leaves.index'),
                        type: 'leave_approved',
                        iconClass: 'feather-calendar'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave status update notification dispatch failed: ' . $e->getMessage());
        }

        $statusLabels = [
            'approved'     => __('hrms.leave.app.approved_successfully'),
            'rejected'     => __('hrms.leave.app.rejected_successfully'),
            'pending'      => __('hrms.leave.app.status_updated_successfully', ['status' => __('hrms.leave.app.status_pending')]),
            'unauthorized' => __('hrms.leave.app.status_updated_successfully', ['status' => __('hrms.leave.app.status_unauthorized')]),
            'unpaid'       => __('hrms.leave.app.status_updated_successfully', ['status' => __('hrms.leave.app.status_unpaid')]),
        ];
        $msg = $statusLabels[$validated['action']] ?? __('hrms.leave.app.status_updated_successfully', ['status' => $validated['action']]);

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
            return redirect()->back()->with('error', __('hrms.leave.app.only_pending_withdrawn'));
        }

        $leaveRequest->delete();

        return redirect()->back()->with('success', __('hrms.leave.app.withdrawn_successfully'));
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
            return redirect()->back()->with('error', __('hrms.leave.app.only_approved_cancellation'));
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $leaveRequest->update([
            'status'              => 'cancellation_requested',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return redirect()->back()->with('success', __('hrms.leave.app.cancellation_submitted'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Approve Cancellation
    // ─────────────────────────────────────────────────────────────────────────

    public function approveCancellation(LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve')), 403, 'Unauthorized to approve cancellation.');

        if ($leaveRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', __('hrms.leave.app.no_pending_cancellation'));
        }

        // Cancel and restore the leave balance
        $this->leaveRequestRepository->cancelLeaveRequest($leaveRequest);

        return redirect()->back()->with('success', __('hrms.leave.app.cancellation_approved_restored'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin: Deny Cancellation (revert to approved)
    // ─────────────────────────────────────────────────────────────────────────

    public function denyCancellation(LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve')), 403, 'Unauthorized to deny cancellation.');

        if ($leaveRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', __('hrms.leave.app.no_pending_cancellation'));
        }

        $leaveRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return redirect()->back()->with('success', __('hrms.leave.app.cancellation_denied'));
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
            'start_date_type' => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'end_date_type'   => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'hours'           => 'nullable|numeric|min:0.5|max:24',
        ]);

        $employee  = Employee::findOrFail($validated['employee_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? 'full_day';
        $endType   = $validated['end_date_type'] ?? 'full_day';

        $leaveType = !empty($validated['leave_type_id']) ? \App\Domains\HRMS\Models\LeaveType::find($validated['leave_type_id']) : null;
        $isHourly  = $leaveType && (!empty($leaveType->rules['accrual']['calculate_in']) && $leaveType->rules['accrual']['calculate_in'] === 'hours');

        if ($isHourly) {
            $hours = round(floatval($request->input('hours', 1.0)) * 2) / 2;
            return response()->json([
                'success'       => true,
                'duration'      => $hours,
                'unit'          => 'hours',
                'holidays'      => [],
                'rest_days'     => [],
                'sandwich_rule' => false,
                'sandwich_days' => [],
            ]);
        }

        $sandwichRule = $leaveType && !empty($leaveType->rules['application']['sandwich_rule']);

        $duration     = 0.0;
        $holidays     = [];
        $restDays     = [];
        $sandwichDays = [];
        $daysInRange  = [];
        $firstWorkDay = null;
        $lastWorkDay  = null;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));
            $isWorkDay = !$isHoliday && $isActiveWorkDay;

            if ($isHoliday) {
                $holidays[] = $dateStr;
            }
            if (!$isActiveWorkDay) {
                $restDays[] = $dateStr;
            }

            $daysInRange[] = [
                'date'      => $date->copy(),
                'dateStr'   => $dateStr,
                'isWorkDay' => $isWorkDay,
            ];

            if ($isWorkDay) {
                if ($firstWorkDay === null) {
                    $firstWorkDay = $date->copy();
                }
                $lastWorkDay = $date->copy();
            }
        }

        foreach ($daysInRange as $d) {
            $currDate = $d['date'];
            $dateStr  = $d['dateStr'];

            if ($d['isWorkDay']) {
                if ($startDate->isSameDay($endDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($currDate->isSameDay($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($currDate->isSameDay($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                }
            } else {
                // Non-working day (Holiday or Rest Day)
                if ($sandwichRule && $firstWorkDay && $lastWorkDay && $currDate->gt($firstWorkDay) && $currDate->lt($lastWorkDay)) {
                    // Sandwiched between working days
                    $duration += 1.0;
                    $sandwichDays[] = $dateStr;
                }
            }
        }

        return response()->json([
            'success'       => true,
            'duration'      => $duration,
            'unit'          => 'days',
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
                return redirect()->back()->with('error', __('hrms.leave.app.only_pending_deleted'));
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($leaveRequest) {
            // If it was approved or cancellation was requested, restore leave balance before deletion
            if (in_array($leaveRequest->status, ['approved', 'cancellation_requested'])) {
                $this->leaveRequestRepository->cancelLeaveRequest($leaveRequest);
            }

            $leaveRequest->delete();
        });

        return redirect()->back()->with('success', __('hrms.leave.app.deleted_successfully'));
    }
}


