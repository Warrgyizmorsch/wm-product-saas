<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Repositories\WfhRequestRepositoryInterface;
use App\Http\Controllers\Controller;
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
        $user = auth()->user();
        $isAdmin = false;
        if ($user) {
            $isAdmin = $user->hasHrPermission('hr.settings.manage') 
                || $user->hasHrPermission('hr.leaves.manage') 
                || !empty($user->role_id);
        }

        $validated = $request->validate([
            'employee_id'       => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'start_date_type'   => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'     => 'required|string|in:full_day,first_half,second_half',
            'duration'          => 'required|numeric|min:0.5',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts' => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id',
        ]);

        if ($isAdmin && !empty($validated['employee_id'])) {
            $employee = Employee::findOrFail($validated['employee_id']);
        } else {
            $employee = Employee::where('personal_email', $user?->email)
                ->orWhere('office_email', $user?->email)
                ->first();
        }

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee profile not found.');
        }

        // Check for duplicate / overlapping WFH requests for this employee
        $wfhOverlap = WfhRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
            })
            ->exists();

        if ($wfhOverlap) {
            return redirect()->back()->withInput()->with('error', 'A WFH application already exists for the selected employee within this date range.');
        }

        // Check for duplicate / overlapping Leave requests for this employee
        $leaveOverlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
            })
            ->exists();

        if ($leaveOverlap) {
            return redirect()->back()->withInput()->with('error', 'A Leave application already exists for the selected employee within this date range.');
        }

        $validated['employee_id'] = $employee->id;
        $validated['company_id']  = $employee->company_id;

        $this->wfhRequestRepository->storeWfhRequest($validated, $request);

        return redirect()->route('hrms.wfh.index')->with('success', 'WFH application submitted successfully.');
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
        $user = auth()->user();
        $isAdmin = $user && ($user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.leaves.manage')
            || !empty($user->role_id));

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        $action = $overrideAction ?? $request->input('action');
        if (!$action) {
            $validated = $request->validate([
                'action' => 'required|in:approved,rejected,pending',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);
            $action = $validated['action'];
            $reason = $validated['rejection_reason'] ?? null;
        } else {
            $reason = $request->input('rejection_reason');
        }

        $this->wfhRequestRepository->updateStatus($wfhRequest, [
            'action' => $action,
            'rejection_reason' => $reason,
        ], $request);

        $msg = match($action) {
            'approved' => 'WFH request approved successfully.',
            'rejected' => 'WFH request rejected successfully.',
            default    => 'WFH request status updated to pending.',
        };

        return redirect()->route('hrms.wfh.index')->with('success', $msg);
    }
}
