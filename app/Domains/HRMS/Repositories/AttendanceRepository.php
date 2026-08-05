<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function getEmployeeTodayAttendance(int $employeeId): ?Attendance
    {
        $today = Carbon::now()->format('Y-m-d');
        return Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->with('breaks')
            ->first();
    }

    public function checkIn(int $employeeId): Attendance
    {
        $tenantId = auth()->user()?->tenant_id;
        $today = Carbon::now()->format('Y-m-d');

        // Check if already checked in today
        $existing = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        if ($existing) {
            return $existing;
        }

        $employee = Employee::find($employeeId);
        $status = 'present';
        $locationType = $employee ? ($employee->office ?: 'office') : 'office';

        // Optional: Check if check-in is late relative to shift
        if ($employee && $employee->shift) {
            $shiftStartStr = $employee->shift->start_time; // e.g. "09:00:00"
            $nowTime = Carbon::now();
            $shiftStart = Carbon::parse($today . ' ' . $shiftStartStr);

            if ($nowTime->greaterThan($shiftStart)) {
                // Determine grace minutes configured
                $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                        $q->where('company_id', $employee->company_id)
                          ->orWhereNull('company_id');
                    })
                    ->where('rule_type', 'late_arrival')
                    ->where('status', true)
                    ->orderByRaw('company_id IS NULL ASC')
                    ->first();

                $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                $diffMinutes = $nowTime->diffInMinutes($shiftStart);

                if ($diffMinutes > 0) {
                    $status = 'late';
                    $bypassGrace = ($diffMinutes > $graceMinutes); // true if major late (outside daily grace limit)

                    // Count past late arrivals in the current calendar month
                    $pastLateCount = Attendance::where('employee_id', $employeeId)
                        ->where('status', 'late')
                        ->whereBetween('date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                        ->count();

                    $this->applyAttendancePenalty($employee, $today, 'late_arrival', $pastLateCount, $status, $bypassGrace);
                }
            }
        }

        // Find past attendances where check_out is null and date < today to auto-flag missing logs
        $pastMissingLogs = Attendance::where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->where('date', '<', $today)
            ->get();

        if ($pastMissingLogs->isNotEmpty()) {
            foreach ($pastMissingLogs as $pastLog) {
                if ($pastLog->status !== 'missing_logs') {
                    $pastLog->update(['status' => 'missing_logs']);
                    
                    // Count total missing logs in the current calendar month
                    $pastMissingCount = Attendance::where('employee_id', $employeeId)
                        ->where('status', 'missing_logs')
                        ->whereBetween('date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                        ->count();

                    $this->applyMissingLogsPenalty($employee, $pastLog->date->toDateString(), $pastMissingCount);
                }
            }
        }

        return Attendance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'date' => $today,
            'check_in' => Carbon::now(),
            'location_type' => $locationType,
            'status' => $status,
        ]);
    }

    public function checkOut(int $attendanceId): Attendance
    {
        $attendance = Attendance::findOrFail($attendanceId);

        if (!empty($attendance->check_out)) {
            return $attendance;
        }

        $now = Carbon::now();
        $attendance->check_out = $now;

        // If employee is on break, end the break automatically
        $activeBreak = $attendance->activeBreak();
        if ($activeBreak) {
            $activeBreak->update([
                'break_out' => $now,
                'duration_minutes' => max(0, intval($activeBreak->break_in->diffInMinutes($now))),
            ]);
        }

        // Re-load breaks to calculate break hours
        $attendance->load('breaks');
        $totalBreakMinutes = $attendance->breaks->sum('duration_minutes');
        $totalBreakHours = round($totalBreakMinutes / 60, 2);

        $checkIn = Carbon::parse($attendance->check_in);
        $totalDiffHours = $checkIn->diffInMinutes($now) / 60;
        $totalWorkHours = max(0, round($totalDiffHours - $totalBreakHours, 2));

        // Rely strictly on custom penalization policy to determine checkout status
        $status = $attendance->status ?: 'present';

        $employee = Employee::find($attendance->employee_id);
        if ($employee) {
            $this->applyAttendancePenalty($employee, $attendance->date->toDateString(), 'under_hours', $totalWorkHours, $status);
        }

        $attendance->update([
            'total_break_hours' => $totalBreakHours,
            'total_work_hours' => $totalWorkHours,
            'status' => $status,
        ]);

        return $attendance;
    }

    private function applyAttendancePenalty($employee, $date, $ruleType, $valToCheck, &$status, $bypassGrace = false)
    {
        $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)
                  ->orWhereNull('company_id');
            })
            ->where('rule_type', $ruleType)
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        if (!$penaltyRule || !is_array($penaltyRule->penalty_tiers)) {
            return;
        }

        if ($ruleType === 'late_arrival') {
            $occurrenceNum = $valToCheck + 1;
            
            // If within allowed monthly grace period, skip penalization (unless major late bypasses grace)
            if (!$bypassGrace && $occurrenceNum <= ($penaltyRule->threshold_count ?? 0)) {
                return;
            }

            foreach ($penaltyRule->penalty_tiers as $tier) {
                $min = isset($tier['min_occurrence']) ? (int)$tier['min_occurrence'] : 0;
                $max = isset($tier['max_occurrence']) && $tier['max_occurrence'] !== null ? (int)$tier['max_occurrence'] : null;

                if ($occurrenceNum >= $min && ($max === null || $occurrenceNum <= $max)) {
                    $action = $tier['penalty_action'] ?? '';
                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;

                    if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                        if ($val >= 1.0) {
                            $status = 'absent';
                        } elseif ($val > 0) {
                            $status = 'half_day';
                        }
                    }

                    if ($action === 'salary_deduction' || $action === 'both_deductions') {
                        if ($val > 0) {
                            $dailyRate = ($employee->current_salary > 0) ? ($employee->current_salary / 30) : 500;
                            $penaltyAmount = round($dailyRate * $val, 2);
                            
                            \App\Domains\HRMS\Models\EmployeePenalty::create([
                                'employee_id' => $employee->id,
                                'date' => $date,
                                'rule_type' => $ruleType,
                                'penalty_amount' => $penaltyAmount,
                                'status' => 'pending',
                                'payroll_month' => Carbon::parse($date)->format('Y-m'),
                                'remarks' => "Late check-in occurrence #{$occurrenceNum}. Automated salary deduction applied ({$val} Day(s)).",
                            ]);
                        }
                    }
                    break;
                }
            }
        } elseif ($ruleType === 'under_hours') {
            // Calculate target hours
            $targetHours = ($penaltyRule->grace_period_minutes > 0) ? ($penaltyRule->grace_period_minutes / 60) : 8.0;

            // Count past deficit occurrences this month
            $pastDeficitCount = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                ->where('total_work_hours', '>', 0)
                ->where('total_work_hours', '<', $targetHours)
                ->where('date', '<', $date)
                ->count();

            $occurrenceNum = $pastDeficitCount + 1;

            // If within allowed monthly grace period, skip penalization
            if ($occurrenceNum <= ($penaltyRule->threshold_count ?? 0)) {
                return;
            }

            $sortedTiers = collect($penaltyRule->penalty_tiers)->sortBy('hours_threshold')->all();
            foreach ($sortedTiers as $tier) {
                $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;

                if ($valToCheck < $hoursThreshold) {
                    $action = $tier['penalty_action'] ?? '';
                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;

                    if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                        if ($val >= 1.0) {
                            $status = 'absent';
                        } elseif ($val > 0) {
                            $status = 'half_day';
                        }
                    }

                    if ($action === 'salary_deduction' || $action === 'both_deductions') {
                        if ($val > 0) {
                            $dailyRate = ($employee->current_salary > 0) ? ($employee->current_salary / 30) : 500;
                            $penaltyAmount = round($dailyRate * $val, 2);

                            \App\Domains\HRMS\Models\EmployeePenalty::create([
                                'employee_id' => $employee->id,
                                'date' => $date,
                                'rule_type' => $ruleType,
                                'penalty_amount' => $penaltyAmount,
                                'status' => 'pending',
                                'payroll_month' => Carbon::parse($date)->format('Y-m'),
                                'remarks' => "Work hours deficit today ({$valToCheck} hours). Automated salary deduction applied ({$val} Day(s)).",
                            ]);
                        }
                    }
                    break;
                }
            }
        }
    }

    private function applyMissingLogsPenalty($employee, $date, $valToCheck)
    {
        $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)
                  ->orWhereNull('company_id');
            })
            ->where('rule_type', 'missing_logs')
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        if (!$penaltyRule || !is_array($penaltyRule->penalty_tiers)) {
            return;
        }

        $occurrenceNum = $valToCheck;

        // If within allowed monthly grace period, skip penalization
        if ($occurrenceNum <= ($penaltyRule->threshold_count ?? 0)) {
            return;
        }

        foreach ($penaltyRule->penalty_tiers as $tier) {
            $min = isset($tier['min_occurrence']) ? (int)$tier['min_occurrence'] : 0;
            $max = isset($tier['max_occurrence']) && $tier['max_occurrence'] !== null ? (int)$tier['max_occurrence'] : null;

            if ($occurrenceNum >= $min && ($max === null || $occurrenceNum <= $max)) {
                $action = $tier['penalty_action'] ?? '';
                $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;

                if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                    if ($val >= 1.0) {
                        $targetStatus = 'absent';
                    } elseif ($val > 0) {
                        $targetStatus = 'half_day';
                    } else {
                        $targetStatus = 'missing_logs';
                    }
                    Attendance::where('employee_id', $employee->id)
                        ->where('date', $date)
                        ->update(['status' => $targetStatus]);
                }

                if ($action === 'salary_deduction' || $action === 'both_deductions') {
                    if ($val > 0) {
                        $dailyRate = ($employee->current_salary > 0) ? ($employee->current_salary / 30) : 500;
                        $penaltyAmount = round($dailyRate * $val, 2);
                        
                        \App\Domains\HRMS\Models\EmployeePenalty::create([
                            'employee_id' => $employee->id,
                            'date' => $date,
                            'rule_type' => 'missing_logs',
                            'penalty_amount' => $penaltyAmount,
                            'status' => 'pending',
                            'payroll_month' => Carbon::parse($date)->format('Y-m'),
                            'remarks' => "Missing check-out on {$date} (occurrence #{$occurrenceNum}). Automated salary deduction applied ({$val} Day(s)).",
                        ]);
                    }
                }
                break;
            }
        }
    }

    public function breakIn(int $attendanceId)
    {
        $attendance = Attendance::findOrFail($attendanceId);

        // Check if already on break
        if ($attendance->isOnBreak()) {
            return $attendance->activeBreak();
        }

        return AttendanceBreak::create([
            'attendance_id' => $attendanceId,
            'break_in' => Carbon::now(),
        ]);
    }

    public function breakOut(int $attendanceId)
    {
        $attendance = Attendance::findOrFail($attendanceId);
        $activeBreak = $attendance->activeBreak();

        if (!$activeBreak) {
            return null;
        }

        $now = Carbon::now();
        $activeBreak->update([
            'break_out' => $now,
            'duration_minutes' => max(0, intval($activeBreak->break_in->diffInMinutes($now))),
        ]);

        // Recalculate total break hours and update attendance immediately
        $attendance->load('breaks');
        $totalBreakMinutes = $attendance->breaks->sum('duration_minutes');
        $totalBreakHours = round($totalBreakMinutes / 60, 2);

        $attendance->update([
            'total_break_hours' => $totalBreakHours,
        ]);

        return $activeBreak;
    }

    public function getEmployeeAttendanceLogs(int $employeeId)
    {
        return Attendance::where('employee_id', $employeeId)
            ->with('breaks')
            ->orderBy('date', 'desc')
            ->paginate(15);
    }

    public function getAllAttendanceLogs(array $filters)
    {
        $query = Attendance::query()
            ->with(['employee.department', 'employee.designation', 'breaks'])
            ->orderBy('date', 'desc')
            ->orderBy('check_in', 'desc');

        // Filter by Date
        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        } else {
            // Default to today if no filter
            $query->where('date', Carbon::today()->format('Y-m-d'));
        }

        // Filter by Department
        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        // Filter by Search (Name/ID)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate(15)->withQueryString();
    }
}
