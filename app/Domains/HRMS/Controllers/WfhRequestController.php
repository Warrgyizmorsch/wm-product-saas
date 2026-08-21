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
        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
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
        abort_unless($request->user()->hasHrPermission('hr.settings.manage'), 403);

        if ($wfhRequest->status === 'cancelled') {
            return redirect()->back()->with('error', 'Cannot change the status of a cancelled WFH application.');
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

        $this->wfhRequestRepository->updateStatus($wfhRequest, [
            'action'           => $action,
            'rejection_reason' => $reason,
        ], $request);

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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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

    public function export(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();

        $employee = Employee::where('personal_email', $user->email)
            ->orWhere('office_email', $user->email)
            ->first();

        $query = WfhRequest::with('employee')->orderBy('created_at', 'desc');

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
                $req->start_date ? $req->start_date->format('d M Y') : '—',
                $req->end_date   ? $req->end_date->format('d M Y')   : '—',
                floatval($req->duration),
                ucfirst($req->status),
                $req->created_at ? $req->created_at->format('d M Y') : '—',
                $req->reason ?? '',
            ];
        })->toArray();

        $filename = 'wfh_requests_' . now()->format('Y-m-d') . '.xlsx';

        return XlsxHelper::export($headers, $data, $filename);
    }
}
