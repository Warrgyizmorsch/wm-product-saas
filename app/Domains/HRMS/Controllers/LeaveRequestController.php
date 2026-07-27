<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\WfhRequest;
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
        $startDate    = Carbon::parse($validated['start_date']);
        $endDate      = Carbon::parse($validated['end_date']);
        $startType    = $validated['start_date_type'] ?? 'full_day';
        $endType      = $validated['end_date_type']   ?? 'full_day';
        $duration     = 0;

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

        $validated['company_id'] = $employee->company_id;

        $this->leaveRequestRepository->storeLeaveRequest($validated, $request);

        return redirect()->route('hrms.leaves.index')->with('success', __('hrms.leave.app.submitted_successfully'));
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

        $validated = $request->validate([
            'action' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $this->leaveRequestRepository->updateStatus($leaveRequest, $validated, $request);

        $msg = $validated['action'] === 'approved' ? __('hrms.leave.app.approved_successfully') : __('hrms.leave.app.rejected_successfully');

        return redirect()->route('hrms.leaves.index')->with('success', $msg);
    }

    public function getRules(Request $request): JsonResponse
    {
        $employeeId = $request->integer('employee_id');
        $leaveTypeId = $request->integer('leave_type_id');

        $rules = $this->leaveRequestRepository->getPolicyRules($employeeId, $leaveTypeId);

        return response()->json($rules);
    }
}
