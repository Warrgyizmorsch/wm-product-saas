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
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $encashments = $this->leaveEncashmentRepository->getExportData($request->all(), $request->user(), $tenantId);

        $headers = [
            'ID',
            'Employee Code',
            'Employee Name',
            'Department',
            'Leave Type',
            'Requested Days',
            'Status',
            'Reason',
            'Rejection Reason',
            'Applied Date',
        ];

        $rows = [];
        foreach ($encashments as $encash) {
            $rows[] = [
                $encash->id,
                $encash->employee->employee_id ?? 'N/A',
                $encash->employee->full_name ?? 'N/A',
                $encash->employee->department->name ?? 'N/A',
                $encash->leaveType->name ?? 'N/A',
                $encash->requested_days,
                ucfirst($encash->status),
                $encash->reason ?? '',
                $encash->rejection_reason ?? '',
                $encash->created_at ? $encash->created_at->format('Y-m-d H:i') : '',
            ];
        }

        return XlsxHelper::download('leave_encashments_' . date('Ymd_His') . '.xlsx', 'Leave Encashments', $headers, $rows);
    }
}
