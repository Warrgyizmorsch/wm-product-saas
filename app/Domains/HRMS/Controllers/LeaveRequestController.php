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

        $employee = Employee::findOrFail($validated['employee_id']);

        // Calculate duration server-side from dates + session types, excluding holidays & rest days
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? 'full_day';
        $endType   = $validated['end_date_type']   ?? 'full_day';
        $duration  = 0.0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));

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

        $validated['company_id'] = $employee->company_id;

        $this->leaveRequestRepository->storeLeaveRequest($validated, $request);

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
        abort_unless($request->user()->hasHrPermission('hr.settings.manage'), 403);

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

    public function export(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();

        $employee = \App\Domains\HRMS\Models\Employee::where('personal_email', $user->email)
            ->orWhere('office_email', $user->email)
            ->first();

        $query = LeaveRequest::with(['employee', 'leaveType'])->orderBy('created_at', 'desc');

        // Non-admin: scope to own records only
        if ($employee) {
            $isAdmin = $employee->is_admin ?? false;
            if (!$isAdmin) {
                $query->where('employee_id', $employee->id);
            }
        }

        $rows = $query->get();

        $headers = [
            'Employee Name',
            'Employee ID',
            'Leave Type',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Status',
            'Applied On',
            'Reason',
        ];

        $data = $rows->map(function ($req) {
            return [
                $req->employee->full_name ?? '—',
                $req->employee->employee_id ?? '—',
                $req->leaveType->name ?? '—',
                $req->start_date ? $req->start_date->format('d M Y') : '—',
                $req->end_date   ? $req->end_date->format('d M Y')   : '—',
                floatval($req->duration),
                ucfirst($req->status),
                $req->created_at ? $req->created_at->format('d M Y') : '—',
                $req->reason ?? '',
            ];
        })->toArray();

        $filename = 'leave_applications_' . now()->format('Y-m-d') . '.xlsx';

        return XlsxHelper::export($headers, $data, $filename);
    }

    public function calculateDuration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'start_date_type' => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'   => 'required|string|in:full_day,first_half,second_half',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'];
        $endType   = $validated['end_date_type'];

        $duration = 0.0;
        $holidays = [];
        $restDays = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));

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

        return response()->json([
            'success'   => true,
            'duration'  => $duration,
            'holidays'  => $holidays,
            'rest_days' => $restDays,
        ]);
    }
}

