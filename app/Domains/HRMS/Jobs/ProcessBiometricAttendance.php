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

        if ($logCount === 2) {
            $checkOutTime = Carbon::parse($filteredLogs[1]->punch_time);
        } elseif ($logCount > 2) {
            // Check explicit punch_type of the last punch
            $lastPunch = $filteredLogs[$logCount - 1];
            $lastPunchType = strtolower((string) ($lastPunch->punch_type ?? 'auto'));

            if (in_array($lastPunchType, ['out', 'break_in'], true) || ($logCount % 2 === 0)) {
                // Last punch represents an exit/check-out
                $checkOutTime = Carbon::parse($lastPunch->punch_time);
                $breakLimit = $logCount - 2;
            } else {
                // Odd punches with 'in' or 'auto' => employee is currently clocked in / returned from break
                $checkOutTime = null;
                $breakLimit = $logCount - 1;
            }

            // Intermediate punches between 1st punch and the final punch form break intervals
            for ($i = 1; $i < $breakLimit; $i += 2) {
                if (isset($filteredLogs[$i + 1])) {
                    $bIn = Carbon::parse($filteredLogs[$i]->punch_time);
                    $bOut = Carbon::parse($filteredLogs[$i + 1]->punch_time);
                    $dur = max(0, (int) $bIn->diffInMinutes($bOut));
                    if ($dur > 0) {
                        $breaks[] = [
                            'break_in'         => $bIn,
                            'break_out'        => $bOut,
                            'duration_minutes' => $dur,
                        ];
                    }
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
        DB::transaction(function () use ($employee, $date, $checkInTime, $checkOutTime, $netWorkHours, $totalBreakHours, $status, $breaks) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->first();

            if ($attendance) {
                $attendanceStatus = in_array($attendance->status, ['on_leave', 'holiday'])
                    ? $attendance->status
                    : $status;

                $attendance->update([
                    'check_in'          => $checkInTime,
                    'check_out'         => $checkOutTime,
                    'location_type'     => 'office',
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

            // Sync intermediate breaks
            AttendanceBreak::where('attendance_id', $attendance->id)->delete();
            foreach ($breaks as $b) {
                AttendanceBreak::create([
                    'attendance_id'    => $attendance->id,
                    'break_in'         => $b['break_in'],
                    'break_out'        => $b['break_out'],
                    'duration_minutes' => $b['duration_minutes'],
                ]);
            }
        });

        // 8. Mark raw logs in the window as processed
        BiometricPunchLog::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$windowStart, $windowEnd])
            ->update(['processed' => true]);
    }
}
