<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveEncashmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->hasHrPermission('hr.settings.manage');

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'requested_days' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $leaveType = LeaveType::findOrFail($request->leave_type_id);

        $rules = $leaveType->rules ?? [];
        $encashRules = $rules['encashment'] ?? [];

        $isEnabled = !empty($encashRules['enabled']) && ($encashRules['enabled'] === true || $encashRules['enabled'] === '1' || $encashRules['enabled'] === 'true');
        
        if (!$isEnabled) {
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.not_enabled', ['name' => $leaveType->name]));
        }

        $frequency = $encashRules['frequency'] ?? 'anytime';
        if (!$this->isValidEncashmentMonth(Carbon::now(), $frequency)) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.invalid_month', ['name' => $leaveType->name, 'frequency' => $freqLabel]));
        }

        $maxPerRequest = floatval($encashRules['max_days_per_request'] ?? 999.0);
        $requestedDays = round(floatval($request->requested_days) * 2) / 2;

        if ($requestedDays > $maxPerRequest) {
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.max_days_exceeded', ['name' => $leaveType->name, 'max' => $maxPerRequest]));
        }

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->first();

        $remaining = $balance ? floatval($balance->remaining) : 0.0;
        $minBalanceToKeep = floatval($encashRules['min_balance_to_keep'] ?? 0.0);

        if (($remaining - $requestedDays) < $minBalanceToKeep) {
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.min_balance_required', ['min' => $minBalanceToKeep, 'remaining' => $remaining]));
        }

        if ($requestedDays > $remaining) {
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.insufficient_balance', ['remaining' => $remaining]));
        }

        LeaveEncashment::create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'requested_days' => $requestedDays,
            'status' => 'pending',
            'reason' => $request->reason,
        ]);

        return redirect()->back()
            ->with('success', __('hrms.leave.encashment_app.submitted_successfully'));
    }

    public function approve(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $leaveEncashment->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->reconcileEncashedBalance($leaveEncashment->employee_id, $leaveEncashment->leave_type_id);

        return redirect()->back()
            ->with('success', __('hrms.leave.encashment_app.approved_successfully'));
    }

    public function reject(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $leaveEncashment->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
        ]);

        $this->reconcileEncashedBalance($leaveEncashment->employee_id, $leaveEncashment->leave_type_id);

        return redirect()->back()
            ->with('success', __('hrms.leave.encashment_app.rejected_successfully'));
    }

    public function destroy(LeaveEncashment $leaveEncashment): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $empId = $leaveEncashment->employee_id;
        $typeId = $leaveEncashment->leave_type_id;

        $leaveEncashment->delete();

        $this->reconcileEncashedBalance($empId, $typeId);

        return redirect()->back()
            ->with('success', __('hrms.leave.encashment_app.deleted_successfully'));
    }

    private function isValidEncashmentMonth(Carbon $date, string $frequency): bool
    {
        $month = $date->month;

        return match ($frequency) {
            'monthly' => true,
            'quarterly' => in_array($month, [3, 6, 9, 12]),
            'half_yearly' => in_array($month, [6, 12]),
            'yearly' => $month === 12,
            default => true,
        };
    }

    private function reconcileEncashedBalance(int $employeeId, int $leaveTypeId): void
    {
        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$balance) {
            return;
        }

        $employee = Employee::with('leavePlan')->find($employeeId);
        $currentCycleStart = null;
        if ($employee && $employee->leavePlan && $employee->leavePlan->effective_from) {
            $startDate = Carbon::parse($employee->leavePlan->effective_from);
            $now = Carbon::now();
            $diffInYears = $startDate->diffInYears($now);
            $currentCycleStart = $startDate->copy()->addYears($diffInYears);
            if ($currentCycleStart->isAfter($now)) {
                $currentCycleStart->subYear();
            }
        }

        $query = LeaveEncashment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved');

        if ($currentCycleStart) {
            $query->where('created_at', '>=', $currentCycleStart);
        }

        $approvedEncashedSum = floatval($query->sum('requested_days'));

        $balance->update([
            'encashed' => round($approvedEncashedSum * 2) / 2,
        ]);
    }
}
