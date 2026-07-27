<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface;
use App\Http\Controllers\Controller;
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
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'session' => 'nullable|string|in:full_day,first_half,second_half',
            'duration' => 'required|numeric|min:0.5',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $validated['company_id'] = $employee->company_id;

        $this->leaveRequestRepository->storeLeaveRequest($validated, $request);

        return redirect()->route('hrms.leaves.index')->with('success', __('hrms.leave.app.success_submitted'));
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

        $msg = $validated['action'] === 'approved' ? __('hrms.leave.app.success_approved') : __('hrms.leave.app.success_rejected');

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
