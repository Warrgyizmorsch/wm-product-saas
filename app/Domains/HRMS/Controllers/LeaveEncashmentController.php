<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Helpers\XlsxHelper;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Repositories\LeaveEncashmentRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveEncashmentController extends Controller
{
    public function __construct(
        private readonly LeaveEncashmentRepositoryInterface $leaveEncashmentRepository
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'requested_days' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string|max:1000',
        ]);

        $res = $this->leaveEncashmentRepository->storeEncashment($validated, $request, $request->user());

        if (!$res['success']) {
            return redirect()->back()->with('error', $res['message']);
        }

        return redirect()->back()->with('success', $res['message']);
    }

    public function approve(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $res = $this->leaveEncashmentRepository->approve($leaveEncashment, $request, $request->user());

        if (!$res['success']) {
            return redirect()->back()->with('error', $res['message']);
        }

        return redirect()->back()->with('success', $res['message']);
    }

    public function reject(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:1000']);

        $res = $this->leaveEncashmentRepository->reject($leaveEncashment, $request->rejection_reason, $request->user());

        return redirect()->back()->with('success', $res['message']);
    }

    public function destroy(LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $this->leaveEncashmentRepository->delete($leaveEncashment);

        return redirect()->back()->with('success', __('hrms.leave.encashment_app.deleted_successfully'));
    }

    public function exportEncashments(Request $request)
    {
        return $this->leaveEncashmentRepository->export($request->all());
    }
}
