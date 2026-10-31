<?php

namespace App\Domains\HRMS\Jobs;

use App\Core\Tenant\TenantContext;
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
        $employee = Employee::withoutGlobalScopes()->with('tenant')->find($this->employeeId);
        if (!$employee) {
            return;
        }

        if ($employee->tenant) {
            app(TenantContext::class)->set($employee->tenant);
        }

        // 1. Fetch raw punch logs for this employee on this date
        $rawLogs = BiometricPunchLog::where('employee_id', $this->employeeId)
            ->whereDate('punch_time', $this->date)
            ->orderBy('punch_time', 'asc')
            ->get();

        if ($rawLogs->isEmpty()) {
            return;
        }

        // 2. Resolve punches (1st is check-in, last is check-out if more than 1)
        $checkInTime = Carbon::parse($rawLogs[0]->punch_time);
        $checkOutTime = null;
        $totalWorkHours = 0.00;

        if ($rawLogs->count() > 1) {
            $checkOutTime = Carbon::parse($rawLogs[$rawLogs->count() - 1]->punch_time);
            $totalGrossMinutes = $checkInTime->diffInMinutes($checkOutTime);
            $totalWorkHours = round($totalGrossMinutes / 60, 2);
        }

        // 3. Evaluate Penalization Policy (Late Arrival & Under Hours)
        $status = 'present';
        $penaltyService = app(\App\Domains\HRMS\Services\AttendancePenaltyService::class);
        $penaltyService->evaluateLateArrival($employee, $this->date, $checkInTime, $status);

        $existingAtt = Attendance::where('employee_id', $employee->id)->whereDate('date', $this->date)->first();
        $existingBreakHours = $existingAtt ? (float) ($existingAtt->total_break_hours ?? 0.00) : 0.00;
        $netWorkHours = $checkOutTime ? max(0.00, round($totalWorkHours - $existingBreakHours, 2)) : 0.00;

        if ($checkOutTime && $netWorkHours > 0) {
            $penaltyService->evaluateUnderHours($employee, $this->date, $netWorkHours, $status);
        }

        // 4. Update or create the Attendance record
        DB::transaction(function () use ($employee, $checkInTime, $checkOutTime, $netWorkHours, $existingBreakHours, $status) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $this->date)
                ->first();

            if ($attendance) {
                // If attendance already has a recognized custom status (e.g. on_leave, holiday), don't overwrite if manual
                $attendanceStatus = in_array($attendance->status, ['on_leave', 'holiday'])
                    ? $attendance->status
                    : $status;

                $attendance->update([
                    'check_in'          => $checkInTime,
                    'check_out'         => $checkOutTime,
                    'location_type'     => 'office',
                    'status'            => $attendanceStatus,
                    'total_work_hours'  => $netWorkHours,
                    'total_break_hours' => $existingBreakHours,
                ]);
            } else {
                Attendance::create([
                    'tenant_id'         => $employee->tenant_id,
                    'employee_id'       => $employee->id,
                    'date'              => $this->date,
                    'check_in'          => $checkInTime,
                    'check_out'         => $checkOutTime,
                    'location_type'     => 'office',
                    'status'            => $status,
                    'total_work_hours'  => $netWorkHours,
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
