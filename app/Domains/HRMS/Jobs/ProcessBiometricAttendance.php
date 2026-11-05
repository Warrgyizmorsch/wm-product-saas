<?php

namespace App\Domains\HRMS\Jobs;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessBiometricAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $employeeId,
        protected string $date
    ) {}

    public function handle(): void
    {
        $employee = Employee::withoutGlobalScopes()->with('tenant')->find($this->employeeId);
        if (!$employee) {
            return;
        }

        if ($employee->tenant) {
            app(TenantContext::class)->set($employee->tenant);
        }

        // 1. Check if yesterday had an overnight shift spanning into $this->date morning
        $yesterday = Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $yesterdayShift = $employee->resolveShiftForDate($yesterday);
        if ($yesterdayShift && $yesterdayShift->isOvernight()) {
            $this->processDateAttendance($employee, $yesterday, $yesterdayShift);
        }

        // 2. Process attendance for $this->date
        $todayShift = $employee->resolveShiftForDate($this->date);
        $this->processDateAttendance($employee, $this->date, $todayShift, $yesterdayShift);
    }

    /**
     * Process biometric attendance for a specific date considering shift windows,
     * overnight shift boundaries, and intermediate punches (breaks/offsite).
     */
    protected function processDateAttendance(
        Employee $employee,
        string $date,
        ?ProductionShift $shift,
        ?ProductionShift $prevShift = null
    ): void {
        // 1. Determine shift window boundaries
        $isOvernight = $shift?->isOvernight() ?? false;

        if ($isOvernight) {
            $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($date . ' ' . $shift->start_time)->addDay()->setTimeFromTimeString($shift->end_time);
            $windowStart = $shiftStart->copy()->subHours(4);
            $windowEnd = $shiftEnd->copy()->addHours(4);
        } elseif ($shift && !empty($shift->start_time) && !empty($shift->end_time)) {
            $windowStart = Carbon::parse($date . ' ' . $shift->start_time)->subHours(3);
            $windowEnd = Carbon::parse($date . ' ' . $shift->end_time)->addHours(4);
        } else {
            $windowStart = Carbon::parse($date)->startOfDay();
            $windowEnd = Carbon::parse($date)->endOfDay();
        }

        // If yesterday had an overnight shift ending today morning, ensure today's window does not overlap with yesterday's checkout window
        if ($prevShift && $prevShift->isOvernight()) {
            $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
            $prevShiftEnd = Carbon::parse($prevDate . ' ' . $prevShift->start_time)->addDay()->setTimeFromTimeString($prevShift->end_time);
            $prevCutoff = $prevShiftEnd->copy()->addHours(2);
            if ($windowStart->lt($prevCutoff)) {
                $windowStart = $prevCutoff;
            }
        }

        // 2. Fetch raw punch logs in the resolved window
        $rawLogs = BiometricPunchLog::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$windowStart, $windowEnd])
            ->orderBy('punch_time', 'asc')
            ->get();

        if ($rawLogs->isEmpty()) {
            return;
        }

        // 3. Deduplicate double swipes / bounce punches (within 60 seconds)
        $filteredLogs = collect();
        foreach ($rawLogs as $log) {
            if ($filteredLogs->isEmpty()) {
                $filteredLogs->push($log);
                continue;
            }
            $lastLog = $filteredLogs->last();
            $diffSec = Carbon::parse($log->punch_time)->diffInSeconds(Carbon::parse($lastLog->punch_time));
            if ($diffSec >= 60) {
                $filteredLogs->push($log);
            }
        }

        $logCount = $filteredLogs->count();
        if ($logCount === 0) {
            return;
        }

        // 4. Resolve check-in, check-out, and intermediate break punches
        $checkInTime = Carbon::parse($filteredLogs[0]->punch_time);
        $checkOutTime = null;
        $breaks = [];

        $isCurrentlyOnBreak = false;
        $activeBreakStart = null;

        if ($logCount === 2) {
            $lastPunch = $filteredLogs[1];
            $lastPunchType = strtolower((string) ($lastPunch->punch_type ?? 'auto'));
            if (in_array($lastPunchType, ['break_out', 'start_break'], true)) {
                $checkOutTime = null;
                $isCurrentlyOnBreak = true;
                $activeBreakStart = Carbon::parse($lastPunch->punch_time);
            } else {
                $checkOutTime = Carbon::parse($filteredLogs[1]->punch_time);
            }
        } elseif ($logCount > 2) {
            // Check explicit punch_type of the last punch
            $lastPunch = $filteredLogs[$logCount - 1];
            $lastPunchType = strtolower((string) ($lastPunch->punch_type ?? 'auto'));

            if ($lastPunchType === 'out' || ($logCount % 2 === 0 && !in_array($lastPunchType, ['in', 'break_in', 'end_break', 'break_out', 'start_break'], true))) {
                // Last punch represents an exit/check-out
                $checkOutTime = Carbon::parse($lastPunch->punch_time);
                $breakLimit = $logCount - 2;
            } else {
                // Currently clocked in or currently on break
                $checkOutTime = null;
                if (in_array($lastPunchType, ['break_out', 'start_break'], true)) {
                    $isCurrentlyOnBreak = true;
                    $activeBreakStart = Carbon::parse($lastPunch->punch_time);
                    $breakLimit = $logCount - 1;
                } else {
                    $breakLimit = $logCount;
                }
            }

            // Intermediate punches form completed break intervals
            for ($i = 1; $i < $breakLimit; $i += 2) {
                if (isset($filteredLogs[$i + 1])) {
                    $bIn = Carbon::parse($filteredLogs[$i]->punch_time);
                    $bOut = Carbon::parse($filteredLogs[$i + 1]->punch_time);
                    $dur = max(1, (int) round($bIn->diffInMinutes($bOut)));
                    $breaks[] = [
                        'break_in'         => $bIn,
                        'break_out'        => $bOut,
                        'duration_minutes' => $dur,
                    ];
                }
            }
        }

        // 5. Calculate break hours & net work hours
        $totalBreakMinutes = collect($breaks)->sum('duration_minutes');
        $totalBreakHours = round($totalBreakMinutes / 60, 2);

        $netWorkHours = 0.00;
        if ($checkOutTime && $checkOutTime->gt($checkInTime)) {
            $totalGrossMinutes = $checkInTime->diffInMinutes($checkOutTime);
            $netMinutes = max(0, $totalGrossMinutes - $totalBreakMinutes);
            $netWorkHours = round($netMinutes / 60, 2);
        }

        // 6. Evaluate penalization policy (Late Arrival & Under Hours)
        $status = 'present';
        $penaltyService = app(\App\Domains\HRMS\Services\AttendancePenaltyService::class);
        $penaltyService->evaluateLateArrival($employee, $date, $checkInTime, $status);

        if ($checkOutTime && $netWorkHours > 0) {
            $penaltyService->evaluateUnderHours($employee, $date, $netWorkHours, $status);
        }

        // 7. Update or create the Attendance and AttendanceBreak records
        DB::transaction(function () use ($employee, $date, $checkInTime, $checkOutTime, $netWorkHours, $totalBreakHours, $status, $breaks, $isCurrentlyOnBreak, $activeBreakStart) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->first();

            // Preserve any existing active (unclosed) break before deleting completed breaks
            $existingActiveBreak = $attendance ? $attendance->activeBreak() : null;

            if ($attendance) {
                $attendanceStatus = in_array($attendance->status, ['on_leave', 'holiday'])
                    ? $attendance->status
                    : $status;

                $attendance->update([
                    'check_in'          => $checkInTime,
                    'check_out'         => $checkOutTime,
                    'location_type'     => $attendance->location_type ?: 'office',
                    'status'            => $attendanceStatus,
                    'total_work_hours'  => $netWorkHours,
                    'total_break_hours' => $totalBreakHours,
                ]);
            } else {
                $attendance = Attendance::create([
                    'tenant_id'         => $employee->tenant_id,
                    'employee_id'       => $employee->id,
                    'date'              => $date,
                    'check_in'          => $checkInTime,
                    'check_out'         => $checkOutTime,
                    'location_type'     => 'office',
                    'status'            => $status,
                    'total_work_hours'  => $netWorkHours,
                    'total_break_hours' => $totalBreakHours,
                ]);
            }

            // Sync intermediate completed breaks without deleting existing breaks
            foreach ($breaks as $b) {
                AttendanceBreak::firstOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'break_in'      => $b['break_in'],
                    ],
                    [
                        'break_out'        => $b['break_out'],
                        'duration_minutes' => $b['duration_minutes'],
                    ]
                );
            }

            // If employee is actively on break, restore or create the active break
            if ($isCurrentlyOnBreak || $existingActiveBreak) {
                $start = $activeBreakStart ?: ($existingActiveBreak?->break_in ?: Carbon::now());
                AttendanceBreak::firstOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'break_out'     => null,
                    ],
                    [
                        'break_in'         => $start,
                        'duration_minutes' => 0,
                    ]
                );
            }

            // Recalculate total break hours from all breaks
            $attendance->load('breaks');
            $actualBreakMinutes = $attendance->breaks->sum('duration_minutes');
            $actualBreakHours = round($actualBreakMinutes / 60, 2);
            $attendance->update([
                'total_break_hours' => $actualBreakHours,
            ]);
        });

        // 8. Mark raw logs in the window as processed
        BiometricPunchLog::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$windowStart, $windowEnd])
            ->update(['processed' => true]);
    }
}
