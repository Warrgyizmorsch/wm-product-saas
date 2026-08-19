<?php

namespace App\Domains\HRMS\Jobs;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Employee;
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
        // 1. Fetch raw punch logs for this employee on this date
        $rawLogs = BiometricPunchLog::where('employee_id', $this->employeeId)
            ->whereDate('punch_time', $this->date)
            ->orderBy('punch_time', 'asc')
            ->get();

        if ($rawLogs->isEmpty()) {
            return;
        }

        $employee = Employee::find($this->employeeId);
        if (!$employee) {
            return;
        }

        // 2. Resolve punches (1st is check-in, last is check-out if more than 1)
        $checkInTime = Carbon::parse($rawLogs[0]->punch_time);
        $checkOutTime = null;
        $totalWorkHours = 0.00;

        if ($rawLogs->count() > 1) {
            $checkOutTime = Carbon::parse($rawLogs[$rawLogs->count() - 1]->punch_time);
            $totalWorkHours = round($checkInTime->diffInMinutes($checkOutTime) / 60, 2);
        }

        // 3. Check if they checked in late based on shift roster
        $status = 'present';
        $shift = $employee->resolveShiftForDate($this->date);
        if ($shift) {
            $shiftStartTimeStr = $this->date . ' ' . $shift->start_time;
            $shiftStartTime = Carbon::parse($shiftStartTimeStr);
            
            $graceMinutes = $shift->grace_period_minutes ?? 15;
            $lateThreshold = $shiftStartTime->copy()->addMinutes($graceMinutes);

            if ($checkInTime->gt($lateThreshold)) {
                $status = 'late';
            }
        }

        // 4. Update or create the Attendance record using whereDate lookup to handle SQLite text date mismatches
        DB::transaction(function () use ($employee, $checkInTime, $checkOutTime, $totalWorkHours, $status) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $this->date)
                ->first();

            if ($attendance) {
                $attendance->update([
                    'check_in' => $checkInTime,
                    'check_out' => $checkOutTime,
                    'location_type' => 'office',
                    'status' => $status,
                    'total_work_hours' => $totalWorkHours,
                    'total_break_hours' => 0.00,
                ]);
            } else {
                Attendance::create([
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'date' => $this->date,
                    'check_in' => $checkInTime,
                    'check_out' => $checkOutTime,
                    'location_type' => 'office',
                    'status' => $status,
                    'total_work_hours' => $totalWorkHours,
                    'total_break_hours' => 0.00,
                ]);
            }
        });

        // 5. Mark raw logs as processed
        BiometricPunchLog::where('employee_id', $this->employeeId)
            ->whereDate('punch_time', $this->date)
            ->update(['processed' => true]);
    }
}
