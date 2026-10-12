<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Services\ApprovalWorkflowService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveEncashmentRepository implements LeaveEncashmentRepositoryInterface
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflowService,
        private readonly HrmsScopeService $scopeService
    ) {}

    public function storeEncashment(array $validated, Request $request, ?User $user): array
    {
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        $employeeId = $validated['employee_id'];

        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if ($currentEmp) {
                $employeeId = $currentEmp->id;
            }
        }

        $employee = Employee::findOrFail($employeeId);
        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        $rules = $leaveType->rules ?? [];
        $encashRules = $rules['encashment'] ?? [];

        $isEnabled = !empty($encashRules['enabled']) && ($encashRules['enabled'] === true || $encashRules['enabled'] === '1' || $encashRules['enabled'] === 'true');
        
        if (!$isEnabled) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.not_enabled', ['name' => $leaveType->name])];
        }

        $frequency = $encashRules['frequency'] ?? 'anytime';
        $periods = $this->getCyclePeriods($employee, Carbon::now(), $frequency);

        if (!$periods['is_valid_month']) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.invalid_month', ['name' => $leaveType->name, 'frequency' => $freqLabel])];
        }

        if (!$this->isWithinFrequencyLimits($employee->id, $leaveType->id, $periods['start'], $periods['end'], $frequency)) {
            $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
            return ['success' => false, 'message' => "You have already submitted an encashment request in the current {$freqLabel} period."];
        }

        $maxPerRequest = floatval($encashRules['max_days_per_request'] ?? 999.0);
        $requestedDays = round(floatval($validated['requested_days']) * 2) / 2;

        if ($requestedDays > $maxPerRequest) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.max_days_exceeded', ['name' => $leaveType->name, 'max' => $maxPerRequest])];
        }

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->first();

        $remaining = $balance ? floatval($balance->remaining) : 0.0;
        $minBalanceToKeep = floatval($encashRules['min_balance_to_keep'] ?? 0.0);

        if (($remaining - $requestedDays) < $minBalanceToKeep) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.min_balance_required', ['min' => $minBalanceToKeep, 'remaining' => $remaining])];
        }

        if ($requestedDays > $remaining) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.insufficient_balance', ['remaining' => $remaining])];
        }

        LeaveEncashment::create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'requested_days' => $requestedDays,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
        ]);

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.submitted_successfully')];
    }

    public function approve(LeaveEncashment $leaveEncashment, Request $request, ?User $user): array
    {
        $actorEmpId = $this->workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $leaveEncashment->employee_id) {
            return ['success' => false, 'message' => 'Self-approval is prohibited. You cannot approve your own leave encashment request.'];
        }

        $balance = LeaveBalance::where('employee_id', $leaveEncashment->employee_id)
            ->where('leave_type_id', $leaveEncashment->leave_type_id)
            ->first();

        if (!$balance || $balance->remaining < $leaveEncashment->requested_days) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.insufficient_balance_approval')];
        }

        DB::transaction(function () use ($leaveEncashment, $balance, $user) {
            $days = floatval($leaveEncashment->requested_days);

            $balance->encashed = floatval($balance->encashed ?? 0) + $days;
            $balance->save();

            $leaveEncashment->status = 'approved';
            $leaveEncashment->approved_by = $user?->id;
            $leaveEncashment->approved_at = Carbon::now();
            $leaveEncashment->save();
        });

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.approved_successfully')];
    }

    public function reject(LeaveEncashment $leaveEncashment, ?string $reason, ?User $user): array
    {
        $leaveEncashment->status = 'rejected';
        $leaveEncashment->approved_by = $user?->id;
        $leaveEncashment->approved_at = Carbon::now();
        $leaveEncashment->rejection_reason = $reason;
        $leaveEncashment->save();

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.rejected_successfully')];
    }

    public function delete(LeaveEncashment $leaveEncashment): bool
    {
        if ($leaveEncashment->status === 'approved') {
            $balance = LeaveBalance::where('employee_id', $leaveEncashment->employee_id)
                ->where('leave_type_id', $leaveEncashment->leave_type_id)
                ->first();

            if ($balance) {
                $days = floatval($leaveEncashment->requested_days);
                $balance->encashed = max(0, floatval($balance->encashed ?? 0) - $days);
                $balance->save();
            }
        }

        return (bool) $leaveEncashment->delete();
    }

    public function getExportData(array $inputs, ?User $user, int $tenantId): array
    {
        $query = LeaveEncashment::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId);

        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        if (!$isHrAdmin) {
            $this->scopeService->applyRelatedScope($query, $user);
        }

        if (!empty($inputs['employee_id'])) {
            $query->where('employee_id', $inputs['employee_id']);
        }
        if (!empty($inputs['department_id'])) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $inputs['department_id']));
        }
        if (!empty($inputs['leave_type_id'])) {
            $query->where('leave_type_id', $inputs['leave_type_id']);
        }
        if (!empty($inputs['status'])) {
            $query->where('status', $inputs['status']);
        }

        return $query->orderBy('created_at', 'desc')->get()->all();
    }

    private function getCyclePeriods(Employee $employee, Carbon $now, string $frequency): array
    {
        $plan = $employee->leavePlan;
        $effectiveFrom = ($plan && $plan->effective_from) ? Carbon::parse($plan->effective_from) : Carbon::create($now->year, 1, 1);
        
        $monthOffset = $effectiveFrom->month; 
        $currentMonth = $now->month;
        
        $monthsSinceStart = ($currentMonth - $monthOffset + 12) % 12;

        $isValidMonth = true;
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        switch ($frequency) {
            case 'monthly':
                $isValidMonth = true;
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'quarterly':
                $quarterIndex = intdiv($monthsSinceStart, 3);
                $startMonth = ($monthOffset + ($quarterIndex * 3) - 1) % 12 + 1;
                $year = $now->year;
                if ($startMonth > $currentMonth) {
                    $year--;
                }
                $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $end = $start->copy()->addMonths(2)->endOfMonth();
                break;
            case 'half_yearly':
                $halfIndex = intdiv($monthsSinceStart, 6);
                $startMonth = ($monthOffset + ($halfIndex * 6) - 1) % 12 + 1;
                $year = $now->year;
                if ($startMonth > $currentMonth) {
                    $year--;
                }
                $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $end = $start->copy()->addMonths(5)->endOfMonth();
                break;
            case 'yearly':
                $start = Carbon::create($now->year, $monthOffset, 1)->startOfMonth();
                if ($start->isAfter($now)) {
                    $start->subYear();
                }
                $end = $start->copy()->addYear()->subDay()->endOfDay();
                break;
            case 'year_end':
                $lastMonthOfCycle = ($monthOffset + 10) % 12 + 1;
                $isValidMonth = ($currentMonth === $lastMonthOfCycle);
                $start = Carbon::create($now->year, $monthOffset, 1)->startOfMonth();
                if ($start->isAfter($now)) {
                    $start->subYear();
                }
                $end = $start->copy()->addYear()->subDay()->endOfDay();
                break;
            case 'anytime':
            default:
                $isValidMonth = true;
                $start = Carbon::create($now->year, 1, 1)->startOfDay();
                $end = Carbon::create($now->year, 12, 31)->endOfDay();
                break;
        }

        return [
            'is_valid_month' => $isValidMonth,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function isWithinFrequencyLimits(int $employeeId, int $leaveTypeId, Carbon $start, Carbon $end, string $frequency): bool
    {
        if ($frequency === 'anytime') {
            return true;
        }

        return !LeaveEncashment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('created_at', [$start, $end])
            ->exists();
    }

    public function export(array $filters = []): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $user = auth()->user();
        $encashments = $this->getExportData($filters, $user, $tenantId);

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

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $rows, 'leave_encashments_' . date('Ymd_His') . '.xlsx');
    }
}
