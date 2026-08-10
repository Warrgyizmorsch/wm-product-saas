<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Helpers\XlsxHelper;
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
        $periods = $this->getCyclePeriods($employee, Carbon::now(), $frequency);

        if (!$periods['is_valid_month']) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return redirect()->back()->with('error', __('hrms.leave.encashment_app.invalid_month', ['name' => $leaveType->name, 'frequency' => $freqLabel]));
        }

        if (!$this->isWithinFrequencyLimits($employee->id, $leaveType->id, $periods['start'], $periods['end'], $frequency)) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return redirect()->back()->with('error', "You have already submitted an encashment request in the current {$freqLabel} period.");
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

    // ─────────────────────────────────────────────────────────────────────────
    // Export Encashment Requests to Excel
    // ─────────────────────────────────────────────────────────────────────────

    public function exportEncashments(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();

        $employee = Employee::where('personal_email', $user->email)
            ->orWhere('office_email', $user->email)
            ->first();

        $query = LeaveEncashment::with(['employee', 'leaveType'])->orderBy('created_at', 'desc');

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
            'Requested Days',
            'Reason',
            'Status',
            'Submitted Date',
        ];

        $data = $rows->map(function ($enc) {
            return [
                $enc->employee->full_name ?? '—',
                $enc->employee->employee_id ?? '—',
                $enc->leaveType->name ?? '—',
                floatval($enc->requested_days),
                $enc->reason ?? '',
                ucfirst($enc->status),
                $enc->created_at ? $enc->created_at->format('d M Y') : '—',
            ];
        })->toArray();

        $filename = 'leave_encashments_' . now()->format('Y-m-d') . '.xlsx';

        return XlsxHelper::export($headers, $data, $filename);
    }

    private function isWithinFrequencyLimits(int $employeeId, int $leaveTypeId, Carbon $start, Carbon $end, string $frequency): bool
    {
        if ($frequency === 'anytime' || $frequency === 'any_time') {
            return true;
        }

        $exists = LeaveEncashment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('created_at', [$start, $end])
            ->exists();

        return !$exists;
    }

    private function getCyclePeriods(Employee $employee, Carbon $now, string $frequency): array
    {
        $startDate = null;
        if ($employee && $employee->leavePlan && $employee->leavePlan->effective_from) {
            $startDate = Carbon::parse($employee->leavePlan->effective_from);
        } else {
            $startDate = Carbon::create($now->year, 1, 1, 0, 0, 0);
        }

        $diffInYears = $startDate->diffInYears($now);
        $cycleStart = $startDate->copy()->addYears($diffInYears);
        if ($cycleStart->isAfter($now)) {
            $cycleStart->subYear();
        }

        $elapsedMonths = $cycleStart->diffInMonths($now);

        $periodStart = $cycleStart->copy();
        $periodEnd = $cycleStart->copy()->addYear()->subSecond();
        $isValidMonth = true;

        if ($frequency === 'monthly') {
            $periodStart = $cycleStart->copy()->addMonths($elapsedMonths);
            $periodEnd = $periodStart->copy()->addMonth()->subSecond();
            $isValidMonth = true;
        } elseif ($frequency === 'quarterly') {
            $quarterIndex = floor($elapsedMonths / 3);
            $periodStart = $cycleStart->copy()->addMonths($quarterIndex * 3);
            $periodEnd = $periodStart->copy()->addMonths(3)->subSecond();
            $isValidMonth = ($elapsedMonths % 3) === 2;
        } elseif ($frequency === 'half_yearly') {
            $halfIndex = floor($elapsedMonths / 6);
            $periodStart = $cycleStart->copy()->addMonths($halfIndex * 6);
            $periodEnd = $periodStart->copy()->addMonths(6)->subSecond();
            $isValidMonth = ($elapsedMonths % 6) === 5;
        } elseif ($frequency === 'yearly') {
            $periodStart = $cycleStart->copy();
            $periodEnd = $cycleStart->copy()->addYear()->subSecond();
            $isValidMonth = ($elapsedMonths % 12) === 11;
        }

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
            'is_valid_month' => $isValidMonth,
        ];
    }
}
