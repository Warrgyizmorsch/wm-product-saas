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
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leave_encashments.approve'));
        $employeeId = $validated['employee_id'];

        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if (!$currentEmp) {
                return ['success' => false, 'message' => __('hrms.leave.encashment_app.emp_not_found')];
            }
            $employeeId = $currentEmp->id;
        }

        $employee = Employee::where('tenant_id', $user?->tenant_id)->findOrFail($employeeId);
        $leaveType = LeaveType::where('tenant_id', $employee->tenant_id)->find($validated['leave_type_id']);
        if (!$leaveType || ($leaveType->leave_plan_id && (int) $employee->leave_plan_id !== (int) $leaveType->leave_plan_id)) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.type_not_found')];
        }

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

        $maxPerRequest = floatval($encashRules['max_days_per_request'] ?? 999.0);
        $requestedDays = round(floatval($validated['requested_days']) * 2) / 2;

        if ($requestedDays > $maxPerRequest) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.max_days_exceeded', ['name' => $leaveType->name, 'max' => $maxPerRequest])];
        }

        $minBalanceToKeep = floatval($encashRules['min_balance_to_keep'] ?? 0.0);
        $result = DB::transaction(function () use ($employee, $leaveType, $requestedDays, $periods, $frequency, $minBalanceToKeep, $validated) {
            $balance = LeaveBalance::where('employee_id', $employee->id)->where('leave_type_id', $leaveType->id)->lockForUpdate()->first();
            $remaining = $balance ? (float) $balance->remaining : 0.0;
            $pendingDays = (float) LeaveEncashment::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)->where('status', 'pending')->sum('requested_days');
            $available = max(0, $remaining - $pendingDays);
            if (!$balance || $requestedDays > $available) {
                return ['success' => false, 'message' => __('hrms.leave.encashment_app.insufficient_balance', ['remaining' => $available])];
            }
            if (($available - $requestedDays) < $minBalanceToKeep) {
                return ['success' => false, 'message' => __('hrms.leave.encashment_app.min_balance_required', ['min' => $minBalanceToKeep, 'remaining' => $available])];
            }
            if ($frequency !== 'anytime' && LeaveEncashment::where('employee_id', $employee->id)->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['pending', 'approved'])->whereBetween('created_at', [$periods['start'], $periods['end']])->lockForUpdate()->exists()) {
                $freqLabel = ucfirst(str_replace('_', ' ', $frequency));
                return ['success' => false, 'message' => __('hrms.leave.encashment_app.already_submitted_period', ['period' => $freqLabel])];
            }
            $created = LeaveEncashment::create([
                'tenant_id' => $employee->tenant_id, 'company_id' => $employee->company_id,
                'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
                'requested_days' => $requestedDays, 'status' => 'pending', 'reason' => $validated['reason'] ?? null,
            ]);
            return ['success' => true, 'encashment' => $created];
        });
        if (!$result['success']) return $result;

        $createdEncashment = $result['encashment'] ?? null;
        if ($createdEncashment) {
            try {
                $days = floatval($createdEncashment->requested_days);
                $typeName = $leaveType->name ?? 'Leave';
                \App\Services\Notification\NotificationService::sendToHrAdmins(
                    title: 'New Leave Encashment Request',
                    message: "{$employee->full_name} submitted an encashment request for {$days} day(s) ({$typeName}).",
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_encashment',
                    iconClass: 'feather-dollar-sign'
                );

                if ($employee->reportingManager) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $employee->reportingManager,
                        title: 'New Leave Encashment Request',
                        message: "{$employee->full_name} submitted an encashment request for {$days} day(s) ({$typeName}).",
                        actionUrl: route('hrms.leaves.index'),
                        type: 'leave_encashment',
                        iconClass: 'feather-dollar-sign'
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Leave encashment notification failed: ' . $e->getMessage());
            }
        }

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.submitted_successfully'), 'encashment' => $result['encashment']];
    }

    public function approve(LeaveEncashment $leaveEncashment, Request $request, ?User $user): array
    {
        $actorEmpId = $this->workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $leaveEncashment->employee_id) {
            return ['success' => false, 'message' => __('hrms.leave.encashment_app.self_approval_prohibited')];
        }

        $result = DB::transaction(function () use ($leaveEncashment, $user) {
            $encashment = LeaveEncashment::whereKey($leaveEncashment->id)->lockForUpdate()->first();
            if (!$encashment || $encashment->status !== 'pending') return ['success' => false, 'message' => __('hrms.leave.encashment_app.ticket_not_found', ['id' => $leaveEncashment->id])];
            $balance = LeaveBalance::where('employee_id', $encashment->employee_id)->where('leave_type_id', $encashment->leave_type_id)->lockForUpdate()->first();
            if (!$balance || $balance->remaining < $encashment->requested_days) return ['success' => false, 'message' => __('hrms.leave.encashment_app.insufficient_balance_approval')];
            $days = (float) $encashment->requested_days;

            $balance->encashed = floatval($balance->encashed ?? 0) + $days;
            $balance->save();

            $encashment->status = 'approved'; $encashment->approved_by = $user?->id; $encashment->approved_at = Carbon::now(); $encashment->save();
            return ['success' => true];
        });
        if (!$result['success']) return $result;

        try {
            if ($leaveEncashment->employee) {
                $days = floatval($leaveEncashment->requested_days);
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $leaveEncashment->employee,
                    title: 'Leave Encashment Approved',
                    message: "Your leave encashment request for {$days} day(s) has been approved.",
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_encashment_approved',
                    iconClass: 'feather-check-circle'
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave encashment approval notification failed: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.approved_successfully')];
    }

    public function reject(LeaveEncashment $leaveEncashment, ?string $reason, ?User $user): array
    {
        $updated = DB::transaction(function () use ($leaveEncashment, $reason, $user) {
            $encashment = LeaveEncashment::whereKey($leaveEncashment->id)->lockForUpdate()->first();
            if (!$encashment || $encashment->status !== 'pending') return false;
            $encashment->status = 'rejected'; $encashment->approved_by = $user?->id;
            $encashment->approved_at = Carbon::now(); $encashment->rejection_reason = $reason; $encashment->save();
            return true;
        });
        if (!$updated) return ['success' => false, 'message' => __('hrms.leave.encashment_app.ticket_not_found', ['id' => $leaveEncashment->id])];

        try {
            if ($leaveEncashment->employee) {
                $days = floatval($leaveEncashment->requested_days);
                $reasonText = $reason ? ": {$reason}" : '.';
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $leaveEncashment->employee,
                    title: 'Leave Encashment Rejected',
                    message: "Your leave encashment request for {$days} day(s) was rejected{$reasonText}",
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_encashment_rejected',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave encashment rejection notification failed: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => __('hrms.leave.encashment_app.rejected_successfully')];
    }

    public function delete(LeaveEncashment $leaveEncashment): bool
    {
        return DB::transaction(function () use ($leaveEncashment) {
            $encashment = LeaveEncashment::whereKey($leaveEncashment->id)->lockForUpdate()->first();
            if (!$encashment) return false;
            if ($encashment->status === 'approved') {
                $balance = LeaveBalance::where('employee_id', $encashment->employee_id)->where('leave_type_id', $encashment->leave_type_id)->lockForUpdate()->first();
                if ($balance) { $balance->encashed = max(0, (float) $balance->encashed - (float) $encashment->requested_days); $balance->save(); }
            }
            return (bool) $encashment->delete();
        });
    }

    public function getExportData(array $inputs, ?User $user, int $tenantId): array
    {
        $query = LeaveEncashment::with(['employee.department', 'employee.designation', 'leaveType'])
            ->where('tenant_id', $tenantId);

        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leave_encashments.view') || $user->hasHrPermission('hrms.leave_encashments.approve'));
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
        $tenantId = (int) (auth()->user()->tenant_id ?? 0);
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
