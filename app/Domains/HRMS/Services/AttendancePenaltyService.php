<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeePenalty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendancePenaltyService
{
    /**
     * Evaluate late arrival penalty for an employee on a given date and check-in punch.
     */
    public function evaluateLateArrival(Employee $employee, string $date, mixed $punchTime, ?string &$status = null): ?EmployeePenalty
    {
        try {
            $shift = $employee->resolveShiftForDate($date);
            if (!$shift || empty($shift->start_time)) {
                return null;
            }

            if (!($punchTime instanceof Carbon)) {
                $punchTime = Carbon::parse($punchTime);
            }

            $shiftStartTime = Carbon::parse($date . ' ' . $shift->start_time);
            if ($punchTime->lte($shiftStartTime)) {
                return null;
            }

            $diffMinutes = $punchTime->diffInMinutes($shiftStartTime);
            if ($diffMinutes <= 0) {
                return null;
            }

            // Mark initial status as late if not already set to custom status
            if ($status === null || $status === 'present') {
                $status = 'late';
            }

            $penaltyRule = AttendancePenalty::where(function ($q) use ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                })
                ->where('rule_type', 'late_arrival')
                ->where('status', true)
                ->orderByRaw('company_id IS NULL ASC')
                ->first();

            if (!$penaltyRule || !is_array($penaltyRule->penalty_tiers)) {
                return null;
            }

            $graceMinutes = (int) ($penaltyRule->grace_period_minutes ?? ($shift->grace_period_minutes ?? 15));
            $bypassGrace = ($diffMinutes > $graceMinutes);

            // Count previous late arrivals in the calendar month (including days where late penalty was registered)
            $startOfMonth = Carbon::parse($date)->startOfMonth()->toDateString();
            $endOfMonth   = Carbon::parse($date)->endOfMonth()->toDateString();

            $pastLateCount = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->where('date', '<', $date)
                ->where(function ($q) use ($employee, $startOfMonth, $endOfMonth, $date) {
                    $q->where('status', 'late')
                      ->orWhereIn('date', function ($sub) use ($employee, $startOfMonth, $endOfMonth, $date) {
                          $sub->select('date')
                              ->from('employee_penalties')
                              ->where('employee_id', $employee->id)
                              ->where('rule_type', 'late_arrival')
                              ->whereBetween('date', [$startOfMonth, $endOfMonth])
                              ->where('date', '<', $date);
                      });
                })
                ->count();

            $occurrenceNum = $pastLateCount + 1;

            // If within monthly grace allowance and not bypassGrace
            if (!$bypassGrace && $occurrenceNum <= (int) ($penaltyRule->threshold_count ?? 0)) {
                return null;
            }

            foreach ($penaltyRule->penalty_tiers as $tier) {
                $min = isset($tier['min_occurrence']) ? (int) $tier['min_occurrence'] : 0;
                $max = isset($tier['max_occurrence']) && $tier['max_occurrence'] !== null && $tier['max_occurrence'] !== ''
                    ? (int) $tier['max_occurrence']
                    : null;

                if ($occurrenceNum >= $min && ($max === null || $occurrenceNum <= $max)) {
                    $action = $tier['penalty_action'] ?? 'no_deduction';
                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0.0;

                    return $this->applyPenaltyAction(
                        $employee,
                        $date,
                        'late_arrival',
                        $action,
                        $val,
                        $occurrenceNum,
                        "Late check-in occurrence #{$occurrenceNum} ({$diffMinutes} mins late).",
                        $status
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Error evaluating late arrival penalty for Employee #{$employee->id} on {$date}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Evaluate under hours (work hours deficit) penalty for an employee on a given date.
     */
    public function evaluateUnderHours(Employee $employee, string $date, float $totalWorkHours, ?string &$status = null): ?EmployeePenalty
    {
        try {
            $penaltyRule = AttendancePenalty::where(function ($q) use ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                })
                ->where('rule_type', 'under_hours')
                ->where('status', true)
                ->orderByRaw('company_id IS NULL ASC')
                ->first();

            if (!$penaltyRule || !is_array($penaltyRule->penalty_tiers)) {
                return null;
            }

            $targetHours = ($penaltyRule->grace_period_minutes > 0)
                ? ($penaltyRule->grace_period_minutes / 60)
                : 8.0;

            if ($totalWorkHours >= $targetHours) {
                return null;
            }

            $startOfMonth = Carbon::parse($date)->startOfMonth()->toDateString();
            $endOfMonth   = Carbon::parse($date)->endOfMonth()->toDateString();

            // Count previous deficit occurrences in this month
            $pastDeficitCount = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->where('total_work_hours', '>', 0)
                ->where('total_work_hours', '<', $targetHours)
                ->where('date', '<', $date)
                ->count();

            $occurrenceNum = $pastDeficitCount + 1;
            if ($occurrenceNum <= (int) ($penaltyRule->threshold_count ?? 0)) {
                return null;
            }

            $sortedTiers = collect($penaltyRule->penalty_tiers)->sortBy('hours_threshold')->all();
            foreach ($sortedTiers as $tier) {
                $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0.0;

                if ($totalWorkHours < $hoursThreshold) {
                    $action = $tier['penalty_action'] ?? 'no_deduction';
                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0.0;

                    return $this->applyPenaltyAction(
                        $employee,
                        $date,
                        'under_hours',
                        $action,
                        $val,
                        $occurrenceNum,
                        "Work hours deficit today ({$totalWorkHours} hrs, target {$targetHours} hrs).",
                        $status
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Error evaluating under hours penalty for Employee #{$employee->id} on {$date}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Evaluate missing checkout logs penalty for an employee on a given date.
     */
    public function evaluateMissingLogs(Employee $employee, string $date, int $valToCheck): ?EmployeePenalty
    {
        try {
            $penaltyRule = AttendancePenalty::where(function ($q) use ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                })
                ->where('rule_type', 'missing_logs')
                ->where('status', true)
                ->orderByRaw('company_id IS NULL ASC')
                ->first();

            if (!$penaltyRule || !is_array($penaltyRule->penalty_tiers)) {
                return null;
            }

            $occurrenceNum = $valToCheck;
            if ($occurrenceNum <= (int) ($penaltyRule->threshold_count ?? 0)) {
                return null;
            }

            foreach ($penaltyRule->penalty_tiers as $tier) {
                $min = isset($tier['min_occurrence']) ? (int) $tier['min_occurrence'] : 0;
                $max = isset($tier['max_occurrence']) && $tier['max_occurrence'] !== null && $tier['max_occurrence'] !== ''
                    ? (int) $tier['max_occurrence']
                    : null;

                if ($occurrenceNum >= $min && ($max === null || $occurrenceNum <= $max)) {
                    $action = $tier['penalty_action'] ?? 'no_deduction';
                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0.0;

                    $status = null;
                    $penalty = $this->applyPenaltyAction(
                        $employee,
                        $date,
                        'missing_logs',
                        $action,
                        $val,
                        $occurrenceNum,
                        "Missing check-out on {$date} (occurrence #{$occurrenceNum}).",
                        $status
                    );

                    if ($status !== null) {
                        Attendance::where('employee_id', $employee->id)
                            ->where('date', $date)
                            ->update(['status' => $status]);
                    }

                    return $penalty;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Error evaluating missing logs penalty for Employee #{$employee->id} on {$date}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Apply penalty actions across attendance status and employee penalties without double counting.
     */
    protected function applyPenaltyAction(
        Employee $employee,
        string $date,
        string $ruleType,
        string $action,
        float $val,
        int $occurrenceNum,
        string $reasonPrefix,
        ?string &$status = null
    ): ?EmployeePenalty {
        if ($action === 'no_deduction' || $val <= 0) {
            return null;
        }

        $payrollMonth = Carbon::parse($date)->format('Y-m');
        $tenantId = $employee->tenant_id ?? (auth()->user()?->tenant_id ?? 1);

        $existing = EmployeePenalty::where('employee_id', $employee->id)
            ->where('date', $date)
            ->where('rule_type', $ruleType)
            ->first();

        // If an administrator already marked this penalty as excused, do not overwrite it
        if ($existing && $existing->status === 'excused') {
            return $existing;
        }

        // 1. Working Day Deduction
        if ($action === 'working_hour_deduction') {
            if ($val >= 1.0) {
                $status = 'absent';
            } elseif ($val > 0) {
                $status = 'half_day';
            }

            // If deduction value exceeds 1 full day (e.g., 2.0 or 3.0 days),
            // log the excess into employee_penalties so additional days are not lost!
            if ($val > 1.0) {
                return EmployeePenalty::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date'        => $date,
                        'rule_type'   => $ruleType,
                    ],
                    [
                        'tenant_id'      => $tenantId,
                        'penalty_amount' => round($val, 2),
                        'status'         => 'pending',
                        'payroll_month'  => $payrollMonth,
                        'remarks'        => "{$reasonPrefix} Working day deduction ({$val} Day(s) total: 1 Day via absent status + " . ($val - 1.0) . " Day(s) via payroll penalty).",
                    ]
                );
            }

            return null;
        }

        // 2. Salary Deduction (Loss of Pay)
        if ($action === 'salary_deduction') {
            return EmployeePenalty::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date'        => $date,
                    'rule_type'   => $ruleType,
                ],
                [
                    'tenant_id'      => $tenantId,
                    'penalty_amount' => round($val, 2),
                    'status'         => 'pending',
                    'payroll_month'  => $payrollMonth,
                    'remarks'        => "{$reasonPrefix} Automated salary deduction applied ({$val} Day(s)).",
                ]
            );
        }

        // 3. Both Deductions (Salary & Working Day)
        if ($action === 'both_deductions') {
            // Set attendance status to visually reflect penalty on attendance sheets
            if ($val >= 1.0) {
                $status = 'absent';
            } elseif ($val > 0) {
                $status = 'half_day';
            }

            // Log total penalty value in employee_penalties
            return EmployeePenalty::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date'        => $date,
                    'rule_type'   => $ruleType,
                ],
                [
                    'tenant_id'      => $tenantId,
                    'penalty_amount' => round($val, 2),
                    'status'         => 'pending',
                    'payroll_month'  => $payrollMonth,
                    'remarks'        => "{$reasonPrefix} Both deductions applied ({$val} Day(s) penalty).",
                ]
            );
        }

        return null;
    }
}

