<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\AttendanceLocationLog;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function getEmployeeTodayAttendance(int $employeeId): ?Attendance
    {
        $today = Carbon::now()->format('Y-m-d');
        $shiftCutoff = Carbon::now()->subHours(18);

        return Attendance::where('employee_id', $employeeId)
            ->where(function ($q) use ($today, $shiftCutoff) {
                $q->where('date', $today)
                  ->orWhere(function ($sub) use ($shiftCutoff) {
                      // Include active overnight shift that started yesterday within last 18 hours
                      $sub->whereNotNull('check_in')
                          ->whereNull('check_out')
                          ->where('check_in', '>=', $shiftCutoff);
                  });
            })
            ->with('breaks')
            ->latest('check_in')
            ->first();
    }

    public function checkIn(int $employeeId, ?float $latitude = null, ?float $longitude = null, ?string $selfiePath = null): Attendance
    {
        $selfiePath = $this->uploadSelfie($selfiePath, 'in');
        $tenantId = auth()->user()?->tenant_id;
        $today = Carbon::now()->format('Y-m-d');
        $shiftCutoff = Carbon::now()->subHours(18);

        // 1. Auto-flag any old unclosed attendances (older than 18 hours or from past days) as missing logs FIRST
        // This guarantees stale open shifts from previous days never block check-in indefinitely!
        $pastMissingLogs = Attendance::where('employee_id', $employeeId)
            ->whereNull('check_out')
            ->where(function ($q) use ($shiftCutoff, $today) {
                $q->where('check_in', '<', $shiftCutoff)
                  ->orWhere('date', '<', Carbon::yesterday()->format('Y-m-d'));
            })
            ->get();

        if ($pastMissingLogs->isNotEmpty()) {
            foreach ($pastMissingLogs as $pastLog) {
                if ($pastLog->status !== 'missing_logs') {
                    $pastLog->update(['status' => 'missing_logs']);

                    $pastMissingCount = Attendance::where('employee_id', $employeeId)
                        ->where('status', 'missing_logs')
                        ->whereBetween('date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                        ->count();

                    $this->applyMissingLogsPenalty(Employee::find($employeeId), $pastLog->date->toDateString(), $pastMissingCount);
                }
            }
        }

        // 2. Check if employee already has an active ongoing shift (started within last 18h) or already checked in today
        $existing = Attendance::where('employee_id', $employeeId)
            ->where(function ($q) use ($today, $shiftCutoff) {
                $q->where('date', $today)
                  ->orWhere(function ($sub) use ($shiftCutoff) {
                      $sub->whereNotNull('check_in')
                          ->whereNull('check_out')
                          ->where('check_in', '>=', $shiftCutoff);
                  });
            })
            ->latest('check_in')
            ->first();

        $employee = Employee::find($employeeId);

        if ($existing) {
            // 1. If currently clocked in (still working), return active attendance
            if (empty($existing->check_out)) {
                return $existing;
            }

            // 2. Already clocked out today: Check if multiple punches / split shifts are allowed
            $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);
            if (!$rule || empty($rule->allow_multiple_punches)) {
                return $existing; // Default: Single shift per calendar date preserved
            }

            // 3. Multi-Punch Allowed: Reopen session for the new work period
            // Calculate elapsed interim period between previous checkout and new check-in
            $prevCheckOut = \Carbon\Carbon::parse($existing->check_out);
            $now = \Carbon\Carbon::now();
            $interimMinutes = max(0, (int) $prevCheckOut->diffInMinutes($now));

            // Record the interim period as an authorized split shift interval
            AttendanceBreak::create([
                'attendance_id'    => $existing->id,
                'break_in'         => $prevCheckOut,
                'break_out'        => $now,
                'duration_minutes' => $interimMinutes,
            ]);

            $existing->load('breaks');
            $totalBreakMinutes = $existing->breaks->sum('duration_minutes');
            $totalBreakHours = round($totalBreakMinutes / 60, 2);

            $existing->update([
                'check_out'             => null,
                'check_out_latitude'    => null,
                'check_out_longitude'   => null,
                'check_out_selfie_path' => null,
                'total_break_hours'     => $totalBreakHours,
            ]);

            // Add resumption milestone breadcrumb
            if ($latitude !== null && $longitude !== null) {
                AttendanceLocationLog::create([
                    'tenant_id'     => $existing->tenant_id,
                    'attendance_id' => $existing->id,
                    'latitude'      => $latitude,
                    'longitude'     => $longitude,
                    'created_at'    => Carbon::now(),
                ]);
            }

            return $existing;
        }

        $status = 'present';

        // Check if employee has an approved WFH request for today
        $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if ($hasWfh) {
            $locationType = 'wfh';
            $expectedLat = $hasWfh->wfh_latitude ?? $employee?->wfh_latitude;
            $expectedLng = $hasWfh->wfh_longitude ?? $employee?->wfh_longitude;
        } else {
            $locationType = strtolower(trim((string) ($employee?->office ?: 'office')));
            $locationType = match ($locationType) {
                'on-site', 'site' => 'onsite',
                'work from home', 'remote' => 'wfh',
                default => in_array($locationType, ['office', 'wfh', 'onsite'], true) ? $locationType : 'office',
            };
            if ($locationType === 'wfh') {
                $expectedLat = $employee?->wfh_latitude;
                $expectedLng = $employee?->wfh_longitude;
            } else {
                $expectedLat = null;
                $expectedLng = null;
            }
        }

        // Geofencing Check
        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);
        if ($rule && $rule->isTrackingEnabled($locationType)) {
            $trackingMinutes = $rule->getTrackingMinutes($locationType);
            $movementThreshold = $locationType === 'wfh'
                ? $rule->wfh_tracking_meters
                : (in_array($locationType, ['onsite', 'site'], true) ? $rule->site_tracking_meters : null);
            if (!$trackingMinutes || ($movementThreshold !== null && (int) $movementThreshold < 1)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attendance_rule' => 'Live location tracking is enabled, but its interval or movement threshold is missing from attendance rules. Please contact HR.',
                ]);
            }
        }

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
            $wfhRadius = (int) ($rule?->wfh_tracking_meters ?? 0);

            if ($wfhLocationRequired || $wfhGeofenceEnabled || ($rule && $rule->isTrackingEnabled($locationType))) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check in for WFH. Please enable location services in your browser.',
                    ]);
                }
            }

            if ($wfhGeofenceEnabled) {
                if ($wfhRadius < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'The WFH geofence radius is missing from the attendance rules. Please contact HR.',
                    ]);
                }
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
            if ($rule && !$officeWebEnabled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Web/Mobile check-in is disabled for office employees under the current rules.',
                ]);
            }

            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            if ($officeGeofenceEnabled || ($rule && $rule->isTrackingEnabled($locationType))) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check in at the office. Please enable location services in your browser.',
                    ]);
                }

                if ($officeGeofenceEnabled) {
                $officeLat = $rule ? $rule->office_latitude : null;
                $officeLng = $rule ? $rule->office_longitude : null;
                $officeRadius = (int) ($rule?->office_radius ?? 0);

                if (is_null($officeLat) || is_null($officeLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Office geofencing coordinates are not configured in attendance rules. Please contact HR.',
                    ]);
                }

                if ($officeRadius < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'The office geofence radius is missing from the attendance rules. Please contact HR.',
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
            }
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $siteLocationRequired = $rule ? (bool)$rule->site_location : false;
            if ($siteLocationRequired || ($rule && $rule->isTrackingEnabled($locationType))) {
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
        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);
        if ($rule && $rule->isTrackingEnabled($locationType)) {
            $trackingMinutes = $rule->getTrackingMinutes($locationType);
            $movementThreshold = $locationType === 'wfh'
                ? $rule->wfh_tracking_meters
                : (in_array($locationType, ['onsite', 'site'], true) ? $rule->site_tracking_meters : null);
            if (!$trackingMinutes || ($movementThreshold !== null && (int) $movementThreshold < 1)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attendance_rule' => 'Live location tracking is enabled, but its interval or movement threshold is missing from attendance rules. Please contact HR.',
                ]);
            }
        }

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
            $wfhRadius = (int) ($rule?->wfh_tracking_meters ?? 0);

            if ($wfhLocationRequired || $wfhGeofenceEnabled || ($rule && $rule->isTrackingEnabled($locationType))) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check out for WFH. Please enable location services in your browser.',
                    ]);
                }
            }

            if ($wfhGeofenceEnabled && $wfhRadius < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'The WFH geofence radius is missing from the attendance rules. Please contact HR.',
                ]);
            }

            if ($wfhGeofenceEnabled) {
                $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $attendance->employee_id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $attendance->date)
                    ->whereDate('end_date', '>=', $attendance->date)
                    ->first();

                $expectedLat = $hasWfh?->wfh_latitude ?? $employee?->wfh_latitude;
                $expectedLng = $hasWfh?->wfh_longitude ?? $employee?->wfh_longitude;

                if (is_null($expectedLat) || is_null($expectedLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'WFH geofencing is enabled, but home coordinates are missing from the approved request and employee profile.',
                    ]);
                }

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
            if ($rule && !$officeWebEnabled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Web/Mobile check-out is disabled for office employees under the current rules.',
                ]);
            }

            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            if ($officeGeofenceEnabled || ($rule && $rule->isTrackingEnabled($locationType))) {
                if (is_null($latitude) || is_null($longitude)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Your GPS coordinates are required to check out at the office. Please enable location services in your browser.',
                    ]);
                }

                if ($officeGeofenceEnabled) {
                $officeLat = $rule ? $rule->office_latitude : null;
                $officeLng = $rule ? $rule->office_longitude : null;
                $officeRadius = (int) ($rule?->office_radius ?? 0);

                if (is_null($officeLat) || is_null($officeLng)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'Office geofencing coordinates are not configured in attendance rules. Please contact HR.',
                    ]);
                }

                if ($officeRadius < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => 'The office geofence radius is missing from the attendance rules. Please contact HR.',
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
            }
        } elseif ($locationType === 'onsite' || $locationType === 'site') {
            $siteLocationRequired = $rule ? (bool)$rule->site_location : false;
            if ($siteLocationRequired || ($rule && $rule->isTrackingEnabled($locationType))) {
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

        // Consolidate location tracking route into polyline/snapshot and purge raw logs
        try {
            app(\App\Domains\HRMS\Services\AttendanceRouteService::class)->consolidateShiftRoute($attendance);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Route consolidation skipped for Attendance #{$attendance->id}: " . $e->getMessage());
        }

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
                            $payrollMonth = Carbon::parse($date)->format('Y-m');
                            $tenantId = $employee->tenant_id ?? (auth()->user()?->tenant_id);

                            \App\Domains\HRMS\Models\EmployeePenalty::updateOrCreate(
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
                                    'remarks'        => "Late check-in occurrence #{$occurrenceNum}. Automated salary deduction applied ({$val} Day(s)).",
                                ]
                            );
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
                            $payrollMonth = Carbon::parse($date)->format('Y-m');
                            $tenantId = $employee->tenant_id ?? (auth()->user()?->tenant_id);

                            \App\Domains\HRMS\Models\EmployeePenalty::updateOrCreate(
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
                                    'remarks'        => "Work hours deficit today ({$valToCheck} hours). Automated salary deduction applied ({$val} Day(s)).",
                                ]
                            );
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
                        $payrollMonth = Carbon::parse($date)->format('Y-m');
                        $tenantId = $employee->tenant_id ?? (auth()->user()?->tenant_id);

                        \App\Domains\HRMS\Models\EmployeePenalty::updateOrCreate(
                            [
                                'employee_id' => $employee->id,
                                'date'        => $date,
                                'rule_type'   => 'missing_logs',
                            ],
                            [
                                'tenant_id'      => $tenantId,
                                'penalty_amount' => round($val, 2),
                                'status'         => 'pending',
                                'payroll_month'  => $payrollMonth,
                                'remarks'        => "Missing check-out on {$date} (occurrence #{$occurrenceNum}). Automated salary deduction applied ({$val} Day(s)).",
                            ]
                        );
                    }
                }
                break;
            }
        }
    }

    public function breakIn(int $attendanceId, ?float $latitude = null, ?float $longitude = null)
    {
        $attendance = Attendance::findOrFail($attendanceId);

        if (!$attendance->isWorking()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attendance' => 'You must be checked in and not checked out to start a break.',
            ]);
        }

        // Check if already on break
        if ($attendance->isOnBreak()) {
            return $attendance->activeBreak();
        }

        // Enhancement 2: Optional Break Geofence Validation
        $employee = Employee::find($attendance->employee_id);
        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);

        if ($rule && !empty($rule->office_break_geofence) && $attendance->location_type === 'office') {
            if (is_null($latitude) || is_null($longitude)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Your GPS coordinates are required to start a break under office geofence rules.',
                ]);
            }

            $officeLat = $rule->office_latitude;
            $officeLng = $rule->office_longitude;
            $officeRadius = (int) ($rule->office_radius ?? 0);

            if (!is_null($officeLat) && !is_null($officeLng) && $officeRadius > 0) {
                $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$officeLat, (float)$officeLng);
                if ($distance > $officeRadius) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => sprintf(
                            'Break-In failed. You are outside the office geofence range (Distance: %d meters, allowed radius: %d meters).',
                            round($distance),
                            $officeRadius
                        ),
                    ]);
                }
            }
        }

        // Record break milestone breadcrumb
        if ($latitude !== null && $longitude !== null) {
            AttendanceLocationLog::create([
                'tenant_id'     => $attendance->tenant_id,
                'attendance_id' => $attendance->id,
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'created_at'    => Carbon::now(),
            ]);
        }

        return AttendanceBreak::create([
            'attendance_id' => $attendanceId,
            'break_in'      => Carbon::now(),
        ]);
    }

    public function breakOut(int $attendanceId, ?float $latitude = null, ?float $longitude = null)
    {
        $attendance = Attendance::findOrFail($attendanceId);
        $activeBreak = $attendance->activeBreak();

        if (!$activeBreak) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'break' => 'There is no active break to end.',
            ]);
        }

        // Enhancement 2: Optional Break Geofence Validation
        $employee = Employee::find($attendance->employee_id);
        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);

        if ($rule && !empty($rule->office_break_geofence) && $attendance->location_type === 'office') {
            if (is_null($latitude) || is_null($longitude)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'location' => 'Your GPS coordinates are required to resume work under office geofence rules.',
                ]);
            }

            $officeLat = $rule->office_latitude;
            $officeLng = $rule->office_longitude;
            $officeRadius = (int) ($rule->office_radius ?? 0);

            if (!is_null($officeLat) && !is_null($officeLng) && $officeRadius > 0) {
                $distance = $this->calculateDistance((float)$latitude, (float)$longitude, (float)$officeLat, (float)$officeLng);
                if ($distance > $officeRadius) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'location' => sprintf(
                            'Resume Work failed. You are outside the office geofence range (Distance: %d meters, allowed radius: %d meters).',
                            round($distance),
                            $officeRadius
                        ),
                    ]);
                }
            }
        }

        // Record break milestone breadcrumb
        if ($latitude !== null && $longitude !== null) {
            AttendanceLocationLog::create([
                'tenant_id'     => $attendance->tenant_id,
                'attendance_id' => $attendance->id,
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'created_at'    => Carbon::now(),
            ]);
        }

        $now = Carbon::now();
        $activeBreak->update([
            'break_out'        => $now,
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
        // Find employee active checked-in shift (within last 18 hours, supports day & overnight night shifts)
        $shiftCutoff = Carbon::now()->subHours(18);
        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->where('check_in', '>=', $shiftCutoff)
            ->latest('check_in')
            ->first();

        if (!$attendance) {
            return ['status' => 'no_active_shift', 'tracking' => false];
        }

        $employee = Employee::find($employeeId);
        $locationType = strtolower(trim((string) $attendance->location_type));
        $locationType = match ($locationType) {
            'on-site', 'site' => 'onsite',
            'work from home', 'remote' => 'wfh',
            default => $locationType,
        };

        if (empty($locationType)) {
            $shiftDate = $attendance->date ? $attendance->date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $shiftDate)
                ->whereDate('end_date', '>=', $shiftDate)
                ->exists();

            if ($hasWfh) {
                $locationType = 'wfh';
            } else {
                $empLoc = strtolower(trim((string)$employee?->office));
                if (in_array($empLoc, ['onsite', 'on-site', 'site'])) {
                    $locationType = 'onsite';
                } elseif ($empLoc === 'wfh') {
                    $locationType = 'wfh';
                } else {
                    $locationType = 'office';
                }
            }
            $attendance->update(['location_type' => $locationType]);
        }

        // Fetch company rule
        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);

        // Break Telemetry Privacy Mode: Suspend tracking during authorized breaks
        if ($rule && !empty($rule->pause_tracking_on_break) && $attendance->isOnBreak()) {
            return [
                'status'           => 'paused_on_break',
                'tracking'         => false,
                'recorded'         => false,
                'interval_minutes' => $rule->getTrackingMinutes($locationType),
                'message'          => 'Periodic GPS tracking is suspended while on an authorized break.',
            ];
        }

        // If location tracking is NOT checked in Attendance Rules, SKIP IT completely
        if (!$rule || !$rule->isTrackingEnabled($locationType)) {
            return [
                'status'           => 'skipped',
                'tracking'         => false,
                'interval_minutes' => null,
                'message'          => 'Location tracking is disabled in attendance rules.',
            ];
        }

        $intervalMinutes = $rule->getTrackingMinutes($locationType);

        if (!$intervalMinutes || $intervalMinutes < 1) {
            return [
                'status' => 'invalid_tracking_configuration',
                'tracking' => false,
                'recorded' => false,
                'message' => 'The tracking interval is missing from attendance rules.',
            ];
        }

        // Ignore uninitialized (0, 0) GPS fix hardware errors
        if (abs($latitude) < 0.0001 && abs($longitude) < 0.0001) {
            return [
                'status'                    => 'ignored_invalid_coordinates',
                'tracking'                  => true,
                'tracking_interval_minutes' => $intervalMinutes,
                'next_ping_seconds'         => $intervalMinutes * 60,
            ];
        }

        // WFH and onsite thresholds filter movement from the last saved point
        // or first check-in point; they are not fixed location geofences.
        $normalizedLocationType = strtolower(trim((string) $locationType));
        $movementThreshold = in_array($normalizedLocationType, ['onsite', 'site', 'on-site'], true)
            ? (int) ($rule->site_tracking_meters ?? 0)
            : ($normalizedLocationType === 'wfh' ? (int) ($rule->wfh_tracking_meters ?? 0) : null);
        $wfhOutOfBounds = null;
        if ($movementThreshold !== null) {
            if ($movementThreshold < 1) {
                return [
                    'status'    => 'invalid_tracking_configuration',
                    'tracking'  => false,
                    'recorded'  => false,
                    'message'   => 'The movement threshold is missing from attendance rules.',
                ];
            }

            if ($normalizedLocationType === 'wfh' && (bool) $rule->wfh_geofence) {
                $checkDate = $attendance->date ? $attendance->date->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $checkDate)
                    ->whereDate('end_date', '>=', $checkDate)
                    ->first();
                $expectedLat = $hasWfh?->wfh_latitude ?? $employee?->wfh_latitude;
                $expectedLng = $hasWfh?->wfh_longitude ?? $employee?->wfh_longitude;
                if (is_null($expectedLat) || is_null($expectedLng)) {
                    return [
                        'status' => 'invalid_tracking_configuration',
                        'tracking' => false,
                        'recorded' => false,
                        'message' => 'WFH geofencing is enabled but home coordinates are missing from the approved request and employee profile.',
                    ];
                }
                $homeDistance = $this->calculateDistance($latitude, $longitude, (float) $expectedLat, (float) $expectedLng);
                if ($homeDistance > $movementThreshold) {
                    $wfhOutOfBounds = [
                        'status' => 'out_of_bounds',
                        'distance' => round($homeDistance),
                        'radius' => $movementThreshold,
                        'tracking' => true,
                        'recorded' => false,
                        'tracking_interval_minutes' => $intervalMinutes,
                        'next_ping_seconds' => $intervalMinutes * 60,
                    ];
                }
            }

            $lastLocation = \App\Domains\HRMS\Models\AttendanceLocationLog::where('attendance_id', $attendance->id)
                ->latest('created_at')
                ->first();
            $referenceLatitude = $lastLocation?->latitude ?? $attendance->check_in_latitude;
            $referenceLongitude = $lastLocation?->longitude ?? $attendance->check_in_longitude;

            if (!is_null($referenceLatitude) && !is_null($referenceLongitude)
                && abs((float) $referenceLatitude) < 0.0001
                && abs((float) $referenceLongitude) < 0.0001) {
                $referenceLatitude = null;
                $referenceLongitude = null;
            }

            if (!is_null($referenceLatitude) && !is_null($referenceLongitude)) {
                $distance = $this->calculateDistance(
                    (float) $latitude,
                    (float) $longitude,
                    (float) $referenceLatitude,
                    (float) $referenceLongitude
                );

                if ($distance <= $movementThreshold) {
                    if ($wfhOutOfBounds !== null) {
                        return $wfhOutOfBounds;
                    }
                    return [
                        'status'                    => 'below_movement_threshold',
                        'tracking'                  => true,
                        'recorded'                  => false,
                        'distance_meters'           => (int) round($distance),
                        'movement_threshold_meters' => $movementThreshold,
                        'tracking_interval_minutes' => $intervalMinutes,
                        'next_ping_seconds'         => $intervalMinutes * 60,
                    ];
                }
            }
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
            $wfhRadius = (int) ($rule?->wfh_tracking_meters ?? 0);

            if ($wfhGeofenceEnabled) {
                $checkDate = $attendance->date ? $attendance->date->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $checkDate)
                    ->whereDate('end_date', '>=', $checkDate)
                    ->first();

                $expectedLat = $hasWfh ? $hasWfh->wfh_latitude : ($employee ? $employee->wfh_latitude : null);
                $expectedLng = $hasWfh ? $hasWfh->wfh_longitude : ($employee ? $employee->wfh_longitude : null);

                if (!is_null($expectedLat) && !is_null($expectedLng)) {
                    $distance = $this->calculateDistance($latitude, $longitude, (float)$expectedLat, (float)$expectedLng);

                    if ($distance > $wfhRadius) {
                        return [
                            'status'                    => 'out_of_bounds',
                            'distance'                  => round($distance),
                            'radius'                    => $wfhRadius,
                            'tracking'                  => true,
                            'tracking_interval_minutes' => $intervalMinutes,
                            'next_ping_seconds'         => $intervalMinutes * 60,
                        ];
                    }
                }
            }
        } elseif ($locationType === 'office') {
            $officeGeofenceEnabled = $rule ? (bool)$rule->office_geofence : false;
            $officeRadius = (int) ($rule?->office_radius ?? 0);

            if ($officeGeofenceEnabled) {
                $expectedLat = $rule ? $rule->office_latitude : null;
                $expectedLng = $rule ? $rule->office_longitude : null;

                if (!is_null($expectedLat) && !is_null($expectedLng)) {
                    $distance = $this->calculateDistance($latitude, $longitude, (float)$expectedLat, (float)$expectedLng);

                    if ($distance > $officeRadius) {
                        return [
                            'status'                    => 'out_of_bounds',
                            'distance'                  => round($distance),
                            'radius'                    => $officeRadius,
                            'tracking'                  => true,
                            'tracking_interval_minutes' => $intervalMinutes,
                            'next_ping_seconds'         => $intervalMinutes * 60,
                        ];
                    }
                }
            }
        }

        return [
            'status'                    => 'ok',
            'tracking'                  => true,
            'recorded'                  => true,
            'tracking_interval_minutes' => $intervalMinutes,
            'next_ping_seconds'         => $intervalMinutes * 60,
        ];
    }

    /**
     * Export attendance records or corrections as Excel (.xlsx) or CSV.
     */
    public function export(array $filters = []): mixed
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $user = auth()->user();

        // Check if exporting from the corrections view
        if (($filters['view'] ?? '') === 'corrections') {
            return $this->exportCorrections($filters, $tenantId, $user);
        }

        // Determine date range with proper precedence
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
        } elseif (!empty($filters['date'])) {
            $startDate = Carbon::parse($filters['date'])->startOfDay();
            $endDate = Carbon::parse($filters['date'])->endOfDay();
        } elseif (!empty($filters['month']) && $filters['month'] !== 'all') {
            $parts = explode('-', $filters['month']);
            if (count($parts) === 2) {
                $startDate = Carbon::createFromDate((int)$parts[0], (int)$parts[1], 1)->startOfDay();
                $endDate = Carbon::createFromDate((int)$parts[0], (int)$parts[1], 1)->endOfMonth()->endOfDay();
            } else {
                $startDate = Carbon::today()->startOfMonth();
                $endDate = Carbon::today()->endOfMonth();
            }
        } elseif (isset($filters['month']) && $filters['month'] === 'all') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfDay();
        } else {
            // Default when neither date nor month specified: current month
            $startDate = Carbon::today()->startOfMonth();
            $endDate = Carbon::today()->endOfMonth();
        }

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');

        // Fetch scoped employees
        $empQuery = Employee::with(['department', 'designation'])->where('status', true);
        if ($tenantId) {
            $empQuery->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }
        if ($user) {
            $empQuery->forUserScope($user);
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $empQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        if (!empty($filters['department_id'])) {
            $empQuery->where('department_id', $filters['department_id']);
        }
        if (!empty($filters['employee_id'])) {
            $empQuery->where('id', $filters['employee_id']);
        }
        $employees = $empQuery->orderBy('full_name')->get();

        // Fetch attendances within date range
        $attQuery = Attendance::whereBetween('date', [$startDateStr, $endDateStr])
            ->with(['employee.department']);
        if ($tenantId) {
            $attQuery->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }
        if ($user) {
            $attQuery->forUserScope($user);
        }
        $attendances = $attQuery->get();
        $attendancesByKey = $attendances->groupBy(function ($a) {
            $d = $a->date instanceof Carbon ? $a->date->format('Y-m-d') : substr((string)$a->date, 0, 10);
            return $d . '_' . $a->employee_id;
        });

        // Fetch holidays overlapping the period
        $holidaysQuery = HolidayCalendar::where('status', true)
            ->whereBetween('holiday_date', [$startDateStr, $endDateStr]);
        if ($tenantId) {
            $holidaysQuery->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }
        $holidays = $holidaysQuery->get();

        // Fetch approved leaves overlapping the period
        $leaves = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $endDateStr)
            ->where('end_date', '>=', $startDateStr)
            ->with('leaveType')
            ->get();

        $statusFilter = !empty($filters['status']) && $filters['status'] !== 'all' ? $filters['status'] : null;

        $rows = [];
        $currentDate = $endDate->copy()->startOfDay();
        $startDay = $startDate->copy()->startOfDay();

        while ($currentDate->gte($startDay)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday

            foreach ($employees as $employee) {
                // Check if date is before employee joining date
                if ($employee->date_of_joining) {
                    $joiningDate = $employee->date_of_joining instanceof Carbon
                        ? $employee->date_of_joining->copy()->startOfDay()
                        : Carbon::parse($employee->date_of_joining)->startOfDay();
                    if ($currentDate->lt($joiningDate)) {
                        continue;
                    }
                }

                $key = $dateStr . '_' . $employee->id;
                $att = $attendancesByKey->get($key)?->first();

                $statusKey = 'absent';
                $statusLabel = 'Absent';
                $checkIn = '-';
                $checkOut = '-';
                $locationType = '-';
                $workHours = '0.00';
                $breakHours = '0.00';

                if ($att) {
                    $statusKey = $att->status ?: 'present';
                    $statusLabel = ucwords(str_replace('_', ' ', $statusKey));
                    $checkIn = $att->check_in ? $att->check_in->format('H:i:s') : '-';
                    $checkOut = $att->check_out ? $att->check_out->format('H:i:s') : '-';
                    $locationType = ucfirst($att->location_type ?: 'office');
                    $workHours = (string) ($att->total_work_hours ?? '0.00');
                    $breakHours = (string) ($att->total_break_hours ?? '0.00');
                } else {
                    // Check Holiday
                    $holiday = $holidays->first(function ($h) use ($employee, $dateStr) {
                        $hDate = $h->holiday_date instanceof Carbon ? $h->holiday_date->format('Y-m-d') : substr((string)$h->holiday_date, 0, 10);
                        if ($hDate !== $dateStr) return false;
                        if (is_null($h->company_id) && is_null($h->business_unit_id) && is_null($h->branch_id)) return true;
                        if ($h->company_id == $employee->company_id) {
                            if (is_null($h->business_unit_id) && is_null($h->branch_id)) return true;
                            if ($h->business_unit_id == $employee->business_unit_id) {
                                if (is_null($h->branch_id) || $h->branch_id == $employee->branch_id) return true;
                            }
                        }
                        return false;
                    });

                    if ($holiday) {
                        $statusKey = 'holiday';
                        $statusLabel = 'Holiday: ' . $holiday->name;
                    } else {
                        // Check Week Off
                        $isWeekOff = false;
                        if (isset($employee->weekly_pattern) && is_array($employee->weekly_pattern)) {
                            if (isset($employee->weekly_pattern[$dayOfWeek]) && $employee->weekly_pattern[$dayOfWeek] === 'off') {
                                $isWeekOff = true;
                            } elseif ($dayOfWeek === 0 && (!isset($employee->weekly_pattern[$dayOfWeek]) || $employee->weekly_pattern[$dayOfWeek] === 'off')) {
                                $isWeekOff = true;
                            }
                        } else {
                            if ($dayOfWeek === 0) {
                                $isWeekOff = true;
                            }
                        }

                        if ($isWeekOff) {
                            $statusKey = 'week_off';
                            $statusLabel = 'Week Off';
                        } else {
                            // Check Leave
                            $leave = $leaves->first(function ($l) use ($employee, $dateStr) {
                                if ($l->employee_id != $employee->id) return false;
                                $lStart = $l->start_date instanceof Carbon ? $l->start_date->format('Y-m-d') : substr((string)$l->start_date, 0, 10);
                                $lEnd = $l->end_date instanceof Carbon ? $l->end_date->format('Y-m-d') : substr((string)$l->end_date, 0, 10);
                                return $dateStr >= $lStart && $dateStr <= $lEnd;
                            });

                            if ($leave) {
                                $typeName = $leave->leaveType ? $leave->leaveType->name : 'Leave';
                                $statusKey = 'on_leave';
                                $statusLabel = 'Leave (' . $typeName . ')';
                            } else {
                                if ($currentDate->isFuture() && !$currentDate->isToday()) {
                                    $statusKey = 'scheduled';
                                    $statusLabel = 'Scheduled';
                                } else {
                                    $statusKey = 'absent';
                                    $statusLabel = 'Absent';
                                }
                            }
                        }
                    }
                }

                // Filter by status if requested
                if ($statusFilter && $statusKey !== $statusFilter) {
                    continue;
                }

                $rows[] = [
                    'employee_code'     => $employee->employee_id ?? '',
                    'employee_name'     => $employee->full_name ?? '',
                    'department'        => $employee->department?->name ?? '',
                    'date'              => $dateStr,
                    'check_in'          => $checkIn,
                    'check_out'         => $checkOut,
                    'status'            => $statusLabel,
                    'location_type'     => $locationType,
                    'total_work_hours'  => $workHours,
                    'total_break_hours' => $breakHours,
                    'check_in_raw'      => $att?->check_in?->timestamp ?? 0,
                ];
            }

            $currentDate->subDay();
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'name_asc';
        $rowsCollection = collect($rows);

        if ($sort === 'checkin_asc') {
            $sortedRows = $rowsCollection->sortBy([
                ['date', 'desc'],
                ['check_in_raw', 'asc'],
                ['employee_name', 'asc'],
            ]);
        } elseif ($sort === 'checkin_desc') {
            $sortedRows = $rowsCollection->sortBy([
                ['date', 'desc'],
                ['check_in_raw', 'desc'],
                ['employee_name', 'asc'],
            ]);
        } elseif ($sort === 'name_desc') {
            $sortedRows = $rowsCollection->sortBy([
                ['date', 'desc'],
                ['employee_name', 'desc'],
            ]);
        } else {
            // default: name_asc
            $sortedRows = $rowsCollection->sortBy([
                ['date', 'desc'],
                ['employee_name', 'asc'],
            ]);
        }

        $headers = [
            'Employee Code',
            'Employee Name',
            'Department',
            'Date',
            'Check In',
            'Check Out',
            'Status',
            'Location Type',
            'Total Work Hours',
            'Total Break Hours',
        ];

        $data = [];
        foreach ($sortedRows as $row) {
            $data[] = [
                $row['employee_code'],
                $row['employee_name'],
                $row['department'],
                $row['date'],
                $row['check_in'],
                $row['check_out'],
                $row['status'],
                $row['location_type'],
                $row['total_work_hours'],
                $row['total_break_hours'],
            ];
        }

        $format = strtolower($filters['format'] ?? 'xlsx');
        $filenameBase = 'attendance_export_' . date('Ymd_His');

        if ($format === 'csv') {
            $csvHeaders = [
                "Content-type"        => "text/csv; charset=UTF-8",
                "Content-Disposition" => "attachment; filename={$filenameBase}.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use ($data, $headers) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM so Excel opens CSV without encoding issues
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, $headers);

                foreach ($data as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $csvHeaders);
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, "{$filenameBase}.xlsx");
    }

    /**
     * Export attendance corrections.
     */
    protected function exportCorrections(array $filters, ?int $tenantId, mixed $user): mixed
    {
        $query = \App\Domains\HRMS\Models\AttendanceCorrection::with(['employee.department', 'employee.designation']);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($user) {
            $scopeService = app(\App\Domains\HRMS\Services\HrmsScopeService::class);
            $scopeService->applyRelatedScope($query, $user);
        }

        if (!empty($filters['corrections_status'])) {
            $query->where('status', $filters['corrections_status']);
        }

        if (!empty($filters['payroll_month'])) {
            $carbonMonth = Carbon::parse($filters['payroll_month'] . '-01');
            $query->whereBetween('date', [$carbonMonth->copy()->startOfMonth(), $carbonMonth->copy()->endOfMonth()]);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Employee Code',
            'Employee Name',
            'Department',
            'Date',
            'Requested Check In',
            'Requested Check Out',
            'Reason',
            'Status',
            'Rejection Reason',
        ];

        $data = [];
        foreach ($records as $item) {
            $data[] = [
                $item->employee?->employee_id ?? '',
                $item->employee?->full_name ?? '',
                $item->employee?->department?->name ?? '',
                $item->date ? $item->date->format('Y-m-d') : '',
                $item->requested_check_in ? $item->requested_check_in->format('H:i:s') : '-',
                $item->requested_check_out ? $item->requested_check_out->format('H:i:s') : '-',
                $item->reason ?? '',
                ucfirst($item->status ?? 'pending'),
                $item->rejected_reason ?? '',
            ];
        }

        $format = strtolower($filters['format'] ?? 'xlsx');
        $filenameBase = 'attendance_corrections_export_' . date('Ymd_His');

        if ($format === 'csv') {
            $csvHeaders = [
                "Content-type"        => "text/csv; charset=UTF-8",
                "Content-Disposition" => "attachment; filename={$filenameBase}.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, $headers);

                foreach ($data as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $csvHeaders);
        }

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, "{$filenameBase}.xlsx");
    }

    /**
     * Download Attendance Import CSV Template.
     */
    public function downloadTemplate(): mixed
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=attendance_import_template.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['employee_code', 'punch_datetime'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['WRG-001', '2026-09-19 09:00:00']);
            fputcsv($file, ['WRG-001', '2026-09-19 18:00:00']);
            fputcsv($file, ['WRG-002', '2026-09-19 09:30:00']);
            fputcsv($file, ['WRG-002', '2026-09-19 17:30:00']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import attendance records from CSV / Excel file.
     */
    public function import(\Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xls', 'xlsx'])) {
            return ['success' => false, 'message' => 'The file must be a file of type: csv, txt, xls, xlsx.'];
        }

        $path = $file->getRealPath();
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $rows = \App\Domains\HRMS\Helpers\XlsxHelper::import($path);
            } catch (\Throwable $e) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray(null, true, true, false);
                } catch (\Throwable $ex) {
                    return ['success' => false, 'message' => 'Failed to parse Excel file: ' . $ex->getMessage()];
                }
            }
        } else {
            if (($handle = fopen($path, 'r')) !== false) {
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        }

        if (empty($rows) || !isset($rows[0])) {
            return ['success' => false, 'message' => 'The uploaded file is empty or could not be parsed.'];
        }

        $header = array_shift($rows);
        $headerNormalized = array_map(fn($col) => strtolower(trim((string)$col)), $header);

        $colEmpCode = false;
        $colPunchDateTime = false;
        $colDate = false;
        $colCheckIn = false;
        $colCheckOut = false;
        $colStatus = false;

        foreach ($headerNormalized as $idx => $h) {
            if ($colEmpCode === false && in_array($h, ['employee_code', 'employee_id', 'emp_code', 'enroll_no', 'enrollno', 'user_id', 'userid', 'badge_no', 'card_no', 'code'])) {
                $colEmpCode = $idx;
            }
            if ($colPunchDateTime === false && in_array($h, ['punch_datetime', 'log_datetime', 'log_time', 'logtime', 'punch_time', 'punchtime', 'datetime', 'date_time', 'timestamp', 'logdate', 'punch'])) {
                $colPunchDateTime = $idx;
            }
            if ($colDate === false && in_array($h, ['date', 'punch_date', 'log_date', 'attendance_date'])) {
                $colDate = $idx;
            }
            if ($colCheckIn === false && in_array($h, ['check_in', 'checkin', 'in_time', 'intime', 'first_in', 'time_in', 'punch_in', 'in'])) {
                $colCheckIn = $idx;
            }
            if ($colCheckOut === false && in_array($h, ['check_out', 'checkout', 'out_time', 'outtime', 'last_out', 'time_out', 'punch_out', 'out'])) {
                $colCheckOut = $idx;
            }
            if ($colStatus === false && in_array($h, ['status', 'attendance_status', 'mode', 'type'])) {
                $colStatus = $idx;
            }
        }

        if ($colEmpCode === false && count($headerNormalized) > 0) {
            $colEmpCode = 0;
        }

        if ($colEmpCode === false || ($colPunchDateTime === false && $colDate === false)) {
            return ['success' => false, 'message' => 'Invalid template. "employee_code" and either "punch_datetime" or "date" column are required.'];
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $importedCount = 0;
        $skippedRows = [];
        $rowNum = 1;
        $employeeCache = [];

        foreach ($rows as $row) {
            $rowNum++;
            if (empty($row) || !array_filter($row, fn($v) => !is_null($v) && trim((string)$v) !== '')) continue;

            $empCodeStr = trim((string)($row[$colEmpCode] ?? ''));
            if (!$empCodeStr) {
                $skippedRows[] = "Row {$rowNum}: Missing employee_code.";
                continue;
            }

            if (!isset($employeeCache[$empCodeStr])) {
                $employeeCache[$empCodeStr] = Employee::where('employee_id', $empCodeStr)->orWhere('id', $empCodeStr)->first();
            }

            $employee = $employeeCache[$empCodeStr];
            if (!$employee) {
                $skippedRows[] = "Row {$rowNum}: Employee code '{$empCodeStr}' not found.";
                continue;
            }

            $statusRaw = $colStatus !== false ? strtolower(trim((string)($row[$colStatus] ?? ''))) : 'present';
            if (!in_array($statusRaw, ['present', 'absent', 'late', 'half_day', 'on_leave', 'wfh', 'weekly_off'])) {
                $statusRaw = 'present';
            }

            $dateStr = null;
            $checkInTime = null;
            $checkOutTime = null;

            if ($colPunchDateTime !== false && !empty($row[$colPunchDateTime])) {
                try {
                    $dt = Carbon::parse(trim((string)$row[$colPunchDateTime]));
                    $dateStr = $dt->format('Y-m-d');
                    $checkInTime = $dt->format('H:i:s');
                } catch (\Throwable $e) {}
            } elseif ($colDate !== false && !empty($row[$colDate])) {
                try {
                    $dateStr = Carbon::parse(trim((string)$row[$colDate]))->format('Y-m-d');
                    if ($colCheckIn !== false && !empty($row[$colCheckIn])) {
                        $checkInTime = Carbon::parse(trim((string)$row[$colCheckIn]))->format('H:i:s');
                    }
                    if ($colCheckOut !== false && !empty($row[$colCheckOut])) {
                        $checkOutTime = Carbon::parse(trim((string)$row[$colCheckOut]))->format('H:i:s');
                    }
                } catch (\Throwable $e) {}
            }

            if (!$dateStr) {
                $skippedRows[] = "Row {$rowNum}: Invalid date.";
                continue;
            }

            $punchInDatetime = $checkInTime ? Carbon::parse("{$dateStr} {$checkInTime}") : null;
            $punchOutDatetime = $checkOutTime ? Carbon::parse("{$dateStr} {$checkOutTime}") : null;

            $existingAtt = Attendance::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereDate('date', $dateStr)
                ->first();

            if ($existingAtt) {
                // If existing record already has check_in and this is a single punch import
                $newCheckIn = $existingAtt->check_in;
                $newCheckOut = $existingAtt->check_out;

                if ($punchInDatetime) {
                    if (!$newCheckIn) {
                        $newCheckIn = $punchInDatetime;
                    } elseif ($punchInDatetime->lt($newCheckIn)) {
                        if (!$newCheckOut) {
                            $newCheckOut = $newCheckIn;
                        }
                        $newCheckIn = $punchInDatetime;
                    } elseif ($punchInDatetime->gt($newCheckIn)) {
                        if (!$newCheckOut || $punchInDatetime->gt($newCheckOut)) {
                            $newCheckOut = $punchInDatetime;
                        }
                    }
                }

                if ($punchOutDatetime) {
                    if (!$newCheckOut || $punchOutDatetime->gt($newCheckOut)) {
                        $newCheckOut = $punchOutDatetime;
                    }
                }

                $workHours = 0.00;
                if ($newCheckIn && $newCheckOut && $newCheckOut->gt($newCheckIn)) {
                    $workHours = round($newCheckIn->diffInMinutes($newCheckOut) / 60, 2);
                }

                $existingAtt->update([
                    'status'           => $statusRaw ?: $existingAtt->status,
                    'check_in'         => $newCheckIn,
                    'check_out'        => $newCheckOut,
                    'total_work_hours' => $workHours > 0 ? $workHours : $existingAtt->total_work_hours,
                    'location_type'    => $existingAtt->location_type ?: 'office',
                ]);
            } else {
                $workHours = 0.00;
                if ($punchInDatetime && $punchOutDatetime && $punchOutDatetime->gt($punchInDatetime)) {
                    $workHours = round($punchInDatetime->diffInMinutes($punchOutDatetime) / 60, 2);
                }

                Attendance::create([
                    'tenant_id'        => $tenantId,
                    'employee_id'      => $employee->id,
                    'date'             => $dateStr,
                    'status'           => $statusRaw,
                    'check_in'         => $punchInDatetime ?: Carbon::parse("{$dateStr} 00:00:00"),
                    'check_out'        => $punchOutDatetime,
                    'total_work_hours' => $workHours,
                    'location_type'    => 'office',
                ]);
            }

            $importedCount++;
        }

        return [
            'success'        => true,
            'imported_count' => $importedCount,
            'warnings'       => $skippedRows,
            'message'        => "{$importedCount} attendance records processed successfully"
        ];
    }
}
