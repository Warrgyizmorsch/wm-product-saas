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

    public function checkIn(int $employeeId, ?float $latitude = null, ?float $longitude = null, ?string $selfiePath = null): Attendance
    {
        $selfiePath = $this->uploadSelfie($selfiePath, 'in');
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

        // Check if employee has an approved WFH request for today
        $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if ($hasWfh) {
            $locationType = 'wfh';
            $expectedLat = $hasWfh->wfh_latitude;
            $expectedLng = $hasWfh->wfh_longitude;
        } else {
            $locationType = $employee ? ($employee->office ?: 'office') : 'office';
            if ($locationType === 'wfh') {
                $expectedLat = $employee?->wfh_latitude;
                $expectedLng = $employee?->wfh_longitude;
            } else {
                $expectedLat = null;
                $expectedLng = null;
            }
        }

        // Geofencing Check
        $rule = \App\Domains\HRMS\Models\AttendanceRule::where(function ($q) use ($employee) {
                if ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                }
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        // Selfie Requirement Check
        $selfieRequired = false;
        if ($locationType === 'wfh') {
            $selfieRequired = $rule ? (bool)$rule->wfh_selfie : false;
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $selfieRequired = $rule ? (bool)$rule->site_selfie : false;
        } else {
            $selfieRequired = false; // Office does not define a selfie rule
        }

        if ($selfieRequired && is_null($selfiePath)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selfie' => 'A selfie photo is required to check in.',
            ]);
        }

        if ($locationType === 'wfh') {
            $wfhLocationRequired = $rule ? (bool)$rule->wfh_location : false;
            $wfhGeofenceEnabled = $rule ? (bool)$rule->wfh_geofence : false;
            $wfhRadius = $rule && $rule->wfh_tracking_meters ? (int)$rule->wfh_tracking_meters : 200; // default 200m

            if ($wfhLocationRequired || $wfhGeofenceEnabled) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check in for WFH. Please enable location services in your browser.',
                    ]);
                }
            }

            if ($wfhGeofenceEnabled) {
                if (is_null($expectedLat) || is_null($expectedLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'WFH geofencing is enabled, but your WFH coordinates are not configured. Please update them in your profile or contact HR.',
                    ]);
                }

                $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$expectedLat, (float)$expectedLng);

                if ($distance > $wfhRadius) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => sprintf(
                            'Check-in failed. You have an approved WFH request today, but you are outside your designated WFH geofence (Distance: %d meters, allowed radius: %d meters).',
                            round($distance),
                            $wfhRadius
                        ),
                    ]);
                }
            }
        } elseif ($locationType === 'office') {
            // Check if office web check-in is enabled. If not, reject!
            $officeWebEnabled = $rule ? (bool)$rule->office_web : false;
            if (!$officeWebEnabled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Web/Mobile check-in is disabled for office employees under the current rules.',
                ]);
            }

            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            if ($officeGeofenceEnabled) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check in at the office. Please enable location services in your browser.',
                    ]);
                }

                $officeLat = $rule ? $rule->office_latitude : null;
                $officeLng = $rule ? $rule->office_longitude : null;
                $officeRadius = $rule && $rule->office_radius ? (int)$rule->office_radius : 200; // default 200m

                if (is_null($officeLat) || is_null($officeLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Office geofencing coordinates are not configured in attendance rules. Please contact HR.',
                    ]);
                }

                $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$officeLat, (float)$officeLng);

                if ($distance > $officeRadius) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => sprintf(
                            'Check-in failed. You are outside the office geofence range (Distance: %d meters, allowed radius: %d meters).',
                            round($distance),
                            $officeRadius
                        ),
                    ]);
                }
            }
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $siteLocationRequired = $rule ? (bool)$rule->site_location : false;
            if ($siteLocationRequired) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check in for On-Site. Please enable location services in your browser.',
                    ]);
                }
            }
        }

        // Optional: Check if check-in is late relative to shift
        $resolvedShift = $employee ? $employee->resolveShiftForDate($today) : null;
        if ($resolvedShift) {
            $shiftStartStr = $resolvedShift->start_time; // e.g. "09:00:00"
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
            'check_in_latitude' => $latitude,
            'check_in_longitude' => $longitude,
            'check_in_selfie_path' => $selfiePath,
        ]);
    }

    public function checkOut(int $attendanceId, ?float $latitude = null, ?float $longitude = null, ?string $selfiePath = null): Attendance
    {
        $selfiePath = $this->uploadSelfie($selfiePath, 'out');
        $attendance = Attendance::findOrFail($attendanceId);

        if (!empty($attendance->check_out)) {
            return $attendance;
        }

        $now = Carbon::now();
        $attendance->check_out = $now;

        $employee = Employee::find($attendance->employee_id);
        $locationType = $attendance->location_type;

        // Fetch attendance rule
        $rule = \App\Domains\HRMS\Models\AttendanceRule::where(function ($q) use ($employee) {
                if ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                }
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        // Selfie Requirement Check
        $selfieRequired = false;
        if ($locationType === 'wfh') {
            $selfieRequired = $rule ? (bool)$rule->wfh_selfie : false;
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $selfieRequired = $rule ? (bool)$rule->site_selfie : false;
        } else {
            $selfieRequired = false; // Office does not define a selfie rule
        }

        if ($selfieRequired && is_null($selfiePath)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selfie' => 'A selfie photo is required to check out.',
            ]);
        }

        // Perform geofence check on clock-out
        if ($locationType === 'wfh') {
            $wfhLocationRequired = $rule ? (bool)$rule->wfh_location : false;
            $wfhGeofenceEnabled = $rule ? (bool)$rule->wfh_geofence : false;
            $wfhRadius = $rule && $rule->wfh_tracking_meters ? (int)$rule->wfh_tracking_meters : 200;

            if ($wfhLocationRequired || $wfhGeofenceEnabled) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check out for WFH. Please enable location services in your browser.',
                    ]);
                }
            }

            if ($wfhGeofenceEnabled) {
                $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $attendance->employee_id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $attendance->date)
                    ->whereDate('end_date', '>=', $attendance->date)
                    ->first();

                $expectedLat = $hasWfh ? $hasWfh->wfh_latitude : ($employee ? $employee->wfh_latitude : null);
                $expectedLng = $hasWfh ? $hasWfh->wfh_longitude : ($employee ? $employee->wfh_longitude : null);

                if (!is_null($expectedLat) && !is_null($expectedLng)) {
                    $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$expectedLat, (float)$expectedLng);

                    if ($distance > $wfhRadius) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'location' => sprintf(
                                'Check-out failed. You have an approved WFH request today, but you are outside your designated WFH geofence (Distance: %d meters, allowed radius: %d meters).',
                                round($distance),
                                $wfhRadius
                            ),
                        ]);
                    }
                }
            }
        } elseif ($locationType === 'office') {
            // Check if office web check-in is enabled. If not, reject!
            $officeWebEnabled = $rule ? (bool)$rule->office_web : false;
            if (!$officeWebEnabled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Web/Mobile check-out is disabled for office employees under the current rules.',
                ]);
            }

            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            if ($officeGeofenceEnabled) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check out at the office. Please enable location services in your browser.',
                    ]);
                }

                $officeLat = $rule ? $rule->office_latitude : null;
                $officeLng = $rule ? $rule->office_longitude : null;
                $officeRadius = $rule && $rule->office_radius ? (int)$rule->office_radius : 200;

                if (is_null($officeLat) || is_null($officeLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Office geofencing coordinates are not configured in attendance rules. Please contact HR.',
                    ]);
                }

                $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$officeLat, (float)$officeLng);

                if ($distance > $officeRadius) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => sprintf(
                            'Check-out failed. You are outside the office geofence range (Distance: %d meters, allowed radius: %d meters).',
                            round($distance),
                            $officeRadius
                        ),
                    ]);
                }
            }
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $siteLocationRequired = $rule ? (bool)$rule->site_location : false;
            if ($siteLocationRequired) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check out for On-Site. Please enable location services in your browser.',
                    ]);
                }
            }
        }

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

        if ($employee) {
            $this->applyAttendancePenalty($employee, $attendance->date->toDateString(), 'under_hours', $totalWorkHours, $status);
        }

        $attendance->update([
            'total_break_hours' => $totalBreakHours,
            'total_work_hours' => $totalWorkHours,
            'status' => $status,
            'check_out_latitude' => $latitude,
            'check_out_longitude' => $longitude,
            'check_out_selfie_path' => $selfiePath,
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

    /**
     * Calculate the distance in meters between two lat/lng coordinates.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Decode base64 image data and save it as a local file.
     */
    private function uploadSelfie(?string $imageData, string $prefix): ?string
    {
        if (is_null($imageData)) {
            return null;
        }

        // If it is already a saved relative path, return directly
        if (str_starts_with($imageData, 'selfies/')) {
            return $imageData;
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageDataBytes = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                $decodedData = base64_decode($imageDataBytes);
                if ($decodedData !== false) {
                    $fileName = 'selfie_' . $prefix . '_' . uniqid() . '.' . $type;
                    $dirPath = public_path('storage/selfies');
                    if (!file_exists($dirPath)) {
                        mkdir($dirPath, 0755, true);
                    }
                    file_put_contents($dirPath . '/' . $fileName, $decodedData);
                    return 'selfies/' . $fileName;
                }
            }
        }

        return null;
    }

    public function trackLocation(int $employeeId, float $latitude, float $longitude): array
    {
        $today = Carbon::today()->format('Y-m-d');

        // Find employee active checked-in shift today
        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return ['status' => 'no_active_shift', 'tracking' => false];
        }

        $employee = Employee::find($employeeId);
        $locationType = $attendance->location_type;

        // Fetch company rule
        $rule = \App\Domains\HRMS\Models\AttendanceRule::where(function ($q) use ($employee) {
                if ($employee) {
                    $q->where('company_id', $employee->company_id)
                      ->orWhereNull('company_id');
                }
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        // Check if tracking is enabled for this location type
        $trackingEnabled = false;
        if ($locationType === 'wfh') {
            $trackingEnabled = $rule ? (bool)$rule->wfh_tracking : false;
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $trackingEnabled = $rule ? (bool)$rule->site_tracking : false;
        } elseif ($locationType === 'office') {
            $trackingEnabled = $rule ? (bool)($rule->office_tracking ?? false) : false;
        }

        if (!$trackingEnabled) {
            return ['status' => 'disabled', 'tracking' => false];
        }

        // Save location log
        \App\Domains\HRMS\Models\AttendanceLocationLog::create([
            'tenant_id' => $attendance->tenant_id,
            'attendance_id' => $attendance->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'created_at' => Carbon::now(),
        ]);

        // Out-of-bounds check (WFH & Office geofencing)
        if ($locationType === 'wfh') {
            $wfhGeofenceEnabled = $rule ? (bool)$rule->wfh_geofence : false;
            $wfhRadius = $rule && $rule->wfh_tracking_meters ? (int)$rule->wfh_tracking_meters : 200;

            if ($wfhGeofenceEnabled) {
                $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->first();

                $expectedLat = $hasWfh ? $hasWfh->wfh_latitude : ($employee ? $employee->wfh_latitude : null);
                $expectedLng = $hasWfh ? $hasWfh->wfh_longitude : ($employee ? $employee->wfh_longitude : null);

                if (!is_null($expectedLat) && !is_null($expectedLng)) {
                    $distance = $this->calculateDistance($latitude, $longitude, (float)$expectedLat, (float)$expectedLng);

                    if ($distance > $wfhRadius) {
                        return [
                            'status' => 'out_of_bounds',
                            'distance' => round($distance),
                            'radius' => $wfhRadius,
                            'tracking' => true
                        ];
                    }
                }
            }
        } elseif ($locationType === 'office') {
            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            $officeRadius = $rule && $rule->office_radius ? (int)$rule->office_radius : 200;

            if ($officeGeofenceEnabled) {
                $expectedLat = $rule ? $rule->office_latitude : null;
                $expectedLng = $rule ? $rule->office_longitude : null;

                if (!is_null($expectedLat) && !is_null($expectedLng)) {
                    $distance = $this->calculateDistance($latitude, $longitude, (float)$expectedLat, (float)$expectedLng);

                    if ($distance > $officeRadius) {
                        return [
                            'status' => 'out_of_bounds',
                            'distance' => round($distance),
                            'radius' => $officeRadius,
                            'tracking' => true
                        ];
                    }
                }
            }
        }

        return ['status' => 'ok', 'tracking' => true];
    }
}
