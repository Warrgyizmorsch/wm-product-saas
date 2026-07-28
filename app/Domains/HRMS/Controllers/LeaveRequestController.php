<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Helpers\SessionConflictChecker;
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
        ]);

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

        $employee = Employee::findOrFail($validated['employee_id']);

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

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = auth()->user();
        $isAdmin = $user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id);

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

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

        $msg = $validated['action'] === 'approved'
            ? __('hrms.leave.app.approved_successfully')
            : __('hrms.leave.app.rejected_successfully');

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
        $user      = auth()->user();
        $employee  = Employee::where('personal_email', $user?->email)
            ->orWhere('office_email', $user?->email)
            ->first();

        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        // Only the owning employee (or admin) can withdraw
        if (!$isAdmin && (!$employee || $employee->id !== $leaveRequest->employee_id)) {
            return redirect()->back()->with('error', 'Unauthorized action.');
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
        $user     = auth()->user();
        $employee = Employee::where('personal_email', $user?->email)
            ->orWhere('office_email', $user?->email)
            ->first();

        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin && (!$employee || $employee->id !== $leaveRequest->employee_id)) {
            return redirect()->back()->with('error', 'Unauthorized action.');
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
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

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
        $user    = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        if ($leaveRequest->status !== 'cancellation_requested') {
            return redirect()->back()->with('error', 'This application does not have a pending cancellation request.');
        }

        $leaveRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Cancellation request denied. Application remains approved.');
    }
}
