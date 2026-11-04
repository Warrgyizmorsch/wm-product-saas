<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Services\AttendancePenaltyService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttendanceCorrectionRepository implements AttendanceCorrectionRepositoryInterface
{
    public function __construct(
        private readonly HrmsScopeService $scopeService
    ) {}

    private function ensureColumnsExist(): void
    {
        if (!Schema::hasTable('attendance_corrections')) {
            return;
        }

        $missing = [];
        if (!Schema::hasColumn('attendance_corrections', 'approved_at')) {
            $missing['approved_at'] = true;
        }
        if (!Schema::hasColumn('attendance_corrections', 'cancellation_reason')) {
            $missing['cancellation_reason'] = true;
        }
        if (!Schema::hasColumn('attendance_corrections', 'cancellation_requested_at')) {
            $missing['cancellation_requested_at'] = true;
        }
        if (!Schema::hasColumn('attendance_corrections', 'cancellation_decided_by')) {
            $missing['cancellation_decided_by'] = true;
        }
        if (!Schema::hasColumn('attendance_corrections', 'cancellation_decided_at')) {
            $missing['cancellation_decided_at'] = true;
        }
        if (!Schema::hasColumn('attendance_corrections', 'cancellation_denial_reason')) {
            $missing['cancellation_denial_reason'] = true;
        }

        if (!empty($missing)) {
            try {
                Schema::table('attendance_corrections', function (Blueprint $table) use ($missing) {
                    if (isset($missing['approved_at'])) {
                        $table->timestamp('approved_at')->nullable()->after('approved_by');
                    }
                    if (isset($missing['cancellation_reason'])) {
                        $table->text('cancellation_reason')->nullable();
                    }
                    if (isset($missing['cancellation_requested_at'])) {
                        $table->timestamp('cancellation_requested_at')->nullable();
                    }
                    if (isset($missing['cancellation_decided_by'])) {
                        $table->unsignedBigInteger('cancellation_decided_by')->nullable();
                    }
                    if (isset($missing['cancellation_decided_at'])) {
                        $table->timestamp('cancellation_decided_at')->nullable();
                    }
                    if (isset($missing['cancellation_denial_reason'])) {
                        $table->text('cancellation_denial_reason')->nullable();
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('Could not automatically add columns to attendance_corrections: ' . $e->getMessage());
            }
        }
    }

    private function transformCorrection(AttendanceCorrection $ac): array
    {
        $data = [
            'id'                  => $ac->id,
            'date'                => $ac->date ? $ac->date->format('Y-m-d') : null,
            'requested_check_in'  => $ac->requested_check_in ? $ac->requested_check_in->format('H:i:s') : null,
            'requested_check_out' => $ac->requested_check_out ? $ac->requested_check_out->format('H:i:s') : null,
            'reason'              => $ac->reason,
            'status'              => $ac->status,
            'rejected_reason'     => $ac->rejected_reason,
            'created_at'          => $ac->created_at?->toDateTimeString(),
        ];

        if ($ac->relationLoaded('employee') && $ac->employee) {
            $data['employee'] = [
                'id'            => $ac->employee->id,
                'employee_code' => $ac->employee->employee_id,
                'full_name'     => $ac->employee->full_name,
                'photo'         => $ac->employee->photo,
            ];
        }

        if ($ac->relationLoaded('attendance') && $ac->attendance) {
            $att = $ac->attendance;
            $data['attendance'] = [
                'id'         => $att->id,
                'check_in'   => $att->check_in ? $att->check_in->toDateTimeString() : null,
                'check_out'  => $att->check_out ? $att->check_out->toDateTimeString() : null,
                'status'     => $att->status,
                'work_hours' => floatval($att->total_work_hours ?? 0),
            ];
        }

        return $data;
    }

    public function getSingleCorrection(AttendanceCorrection $correction): array
    {
        return $this->transformCorrection($correction);
    }

    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $query = AttendanceCorrection::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,photo',
                'attendance:id,check_in,check_out,status,total_work_hours',
            ]);

        $this->scopeService->applyEmployeeScope($query, $user, 'employee_id');

        if (!empty($inputs['employee_id'])) {
            $query->where('employee_id', $inputs['employee_id']);
        }

        if (!empty($inputs['status'])) {
            $query->where('status', $inputs['status']);
        }

        if (!empty($inputs['from_date'])) {
            $query->whereDate('date', '>=', $inputs['from_date']);
        }

        if (!empty($inputs['to_date'])) {
            $query->whereDate('date', '<=', $inputs['to_date']);
        }

        $perPage = min(max((int)($inputs['per_page'] ?? 15), 1), 100);
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'items' => collect($paginator->items())->map(fn($item) => $this->transformCorrection($item))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    public function storeCorrection(array $validated, ?User $user, int $tenantId): array
    {
        $employeeId = $validated['employee_id'];
        $date = $validated['date'];

        $existingPending = AttendanceCorrection::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'A pending correction request already exists for this date.',
            ];
        }

        $attendance = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->first();

        // Enforce Pay Group Attendance Lock Day
        $employee = Employee::with('payGroup')->find($employeeId);
        if ($employee && $employee->payGroup && is_array($employee->payGroup->payroll_rules)) {
            $lockDay = (int) ($employee->payGroup->payroll_rules['attendance_lock_day'] ?? 25);
            $correctionMonth = Carbon::parse($date)->format('Y-m');
            $currentMonth = Carbon::now()->format('Y-m');
            if ($correctionMonth === $currentMonth && Carbon::now()->day > $lockDay) {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => "Attendance corrections for the current cycle are locked after day {$lockDay} of the month.",
                ];
            }
        }

        $checkIn  = $validated['requested_check_in']  ? "{$date} {$validated['requested_check_in']}"  : null;
        $checkOut = $validated['requested_check_out'] ? "{$date} {$validated['requested_check_out']}" : null;

        $correction = AttendanceCorrection::create([
            'tenant_id'           => $tenantId,
            'attendance_id'       => $attendance?->id,
            'employee_id'         => $employeeId,
            'date'                => $date,
            'requested_check_in'  => $checkIn,
            'requested_check_out' => $checkOut,
            'reason'              => $validated['reason'],
            'status'              => 'pending',
        ]);

        $correction->load(['employee.reportingManager', 'attendance']);

        // Dispatch notifications to HR Admins & Reporting Manager
        try {
            $emp = $correction->employee ?? Employee::find($employeeId);
            $actionUrl = \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : url('/hrms/attendance');
            $dateFormatted = Carbon::parse($date)->format('d M Y');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'Attendance Regularization Requested',
                message: "{$emp?->full_name} submitted an attendance regularization request for {$dateFormatted}.",
                actionUrl: $actionUrl,
                type: 'attendance_correction_request',
                iconClass: 'feather-clock'
            );

            if ($emp && $emp->reportingManager) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $emp->reportingManager,
                    title: 'Attendance Regularization Requested',
                    message: "{$emp->full_name} submitted an attendance regularization request for {$dateFormatted}.",
                    actionUrl: $actionUrl,
                    type: 'attendance_correction_request',
                    iconClass: 'feather-clock'
                );
            }

            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.attendance.regularization_applied', [
                'tenant_id'     => $tenantId,
                'employee_name' => $emp?->full_name ?? 'Employee',
                'date'          => $dateFormatted,
                'check_in'      => $validated['requested_check_in'] ?? 'N/A',
                'check_out'     => $validated['requested_check_out'] ?? 'N/A',
                'reason'        => $validated['reason'] ?? '',
                'employee_id'   => $emp?->id,
                'user_id'       => $emp?->user_id,
            ], $correction);
        } catch (\Throwable $notifEx) {
            Log::warning('Attendance correction submit notification failed: ' . $notifEx->getMessage());
        }

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Attendance correction request submitted successfully.',
            'data' => $this->transformCorrection($correction),
        ];
    }

    public function approve(AttendanceCorrection $correction, ?User $user, ?string $approvedCheckIn = null, ?string $approvedCheckOut = null): array
    {
        if ($correction->status !== 'pending') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => "Only pending requests can be approved. Current status: {$correction->status}.",
            ];
        }

        $this->ensureColumnsExist();

        DB::beginTransaction();
        try {
            $dateStr = $correction->date ? $correction->date->format('Y-m-d') : Carbon::today()->format('Y-m-d');

            $finalCheckInTime = $approvedCheckIn ?: ($correction->requested_check_in ? $correction->requested_check_in->format('H:i') : null);
            $finalCheckOutTime = $approvedCheckOut ?: ($correction->requested_check_out ? $correction->requested_check_out->format('H:i') : null);

            $requestedCheckIn = $finalCheckInTime ? Carbon::parse($dateStr . ' ' . $finalCheckInTime) : null;
            $requestedCheckOut = $finalCheckOutTime ? Carbon::parse($dateStr . ' ' . $finalCheckOutTime) : null;

            $updatePayload = [
                'status'              => 'approved',
                'requested_check_in'  => $requestedCheckIn ?? $correction->requested_check_in,
                'requested_check_out' => $requestedCheckOut ?? $correction->requested_check_out,
                'approved_by'         => $user?->id,
                'rejected_reason'     => null,
            ];
            if (Schema::hasColumn('attendance_corrections', 'approved_at')) {
                $updatePayload['approved_at'] = Carbon::now();
            }

            $correction->update($updatePayload);

            $attendance = $correction->attendance ?? Attendance::where('tenant_id', $correction->tenant_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $correction->date)
                ->first();

            // Snapshot pre-correction attendance and penalty state so it can be restored on cancellation
            $preState = [
                'attendance_existed' => $attendance !== null,
                'attendance_id'      => $attendance?->id,
                'status'             => $attendance?->status,
                'check_in'           => $attendance?->check_in?->toDateTimeString(),
                'check_out'          => $attendance?->check_out?->toDateTimeString(),
                'total_work_hours'   => $attendance?->total_work_hours,
            ];

            $existingPenalties = EmployeePenalty::where('employee_id', $correction->employee_id)
                ->whereDate('date', $dateStr)
                ->whereIn('status', ['pending', 'active'])
                ->get();

            $penaltySnapshots = [];
            foreach ($existingPenalties as $pen) {
                $penaltySnapshots[] = [
                    'id'             => $pen->id,
                    'tenant_id'      => $pen->tenant_id,
                    'employee_id'    => $pen->employee_id,
                    'date'           => $pen->date instanceof Carbon ? $pen->date->format('Y-m-d') : (string)$pen->date,
                    'rule_type'      => $pen->rule_type,
                    'penalty_amount' => $pen->penalty_amount,
                    'status'         => $pen->status,
                    'payroll_month'  => $pen->payroll_month,
                    'remarks'        => $pen->remarks,
                ];
            }
            $preState['penalties'] = $penaltySnapshots;
            Cache::put("attendance_correction_prev_{$correction->id}", $preState, now()->addYears(1));

            $effectiveCheckIn = $requestedCheckIn ?? $attendance?->check_in;
            $effectiveCheckOut = $requestedCheckOut ?? $attendance?->check_out;

            $existingBreakHours = $attendance ? (float) ($attendance->total_break_hours ?? 0.00) : 0.00;
            $workHours = 0.00;
            if ($effectiveCheckIn && $effectiveCheckOut && $effectiveCheckOut->greaterThan($effectiveCheckIn)) {
                $grossHours = round($effectiveCheckIn->diffInMinutes($effectiveCheckOut) / 60, 2);
                $workHours = max(0.00, round($grossHours - $existingBreakHours, 2));
            }

            if ($attendance) {
                $attendance->update([
                    'check_in'         => $effectiveCheckIn,
                    'check_out'        => $effectiveCheckOut,
                    'status'           => 'present',
                    'total_work_hours' => $workHours,
                ]);
            } else {
                $employee = Employee::find($correction->employee_id);
                $attendance = Attendance::create([
                    'tenant_id'        => $correction->tenant_id,
                    'company_id'       => $employee?->company_id,
                    'employee_id'      => $correction->employee_id,
                    'date'             => $dateStr,
                    'check_in'         => $effectiveCheckIn ?: Carbon::parse($dateStr . ' 00:00:00'),
                    'check_out'        => $effectiveCheckOut,
                    'status'           => 'present',
                    'total_work_hours' => $workHours,
                    'location_type'    => 'office',
                ]);

                $correction->update(['attendance_id' => $attendance->id]);
            }

            // Consolidate shift route summary without purging raw logs (retained until payroll release)
            try {
                app(\App\Domains\HRMS\Services\AttendanceRouteService::class)->consolidateShiftRoute($attendance, false);
            } catch (\Throwable $e) {
                Log::warning("Route consolidation skipped on correction approval for Attendance #{$attendance->id}: " . $e->getMessage());
            }

            // Waive penalties by setting status to excused with a tracking marker so payroll skips them,
            // but the records remain intact for restoration if the correction is subsequently cancelled.
            $penaltyMarker = "[Waived by correction #{$correction->id}]";
            EmployeePenalty::where('employee_id', $correction->employee_id)
                ->whereDate('date', $dateStr)
                ->whereIn('status', ['pending', 'active'])
                ->update([
                    'status'  => 'excused',
                    'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' {$penaltyMarker}')"),
                ]);

            DB::commit();

            $correction->load(['employee', 'attendance']);

            // Notify Employee of approval
            try {
                $emp = $correction->employee ?? Employee::find($correction->employee_id);
                $dateFormatted = $correction->date ? $correction->date->format('d M Y') : 'requested date';
                $actionUrl = \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : url('/hrms/attendance');
                if ($emp) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $emp,
                        title: 'Attendance Regularization Approved',
                        message: "Your attendance regularization request for {$dateFormatted} has been approved.",
                        actionUrl: $actionUrl,
                        type: 'attendance_regularization_approved',
                        iconClass: 'feather-check-circle'
                    );
                }

                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.attendance.regularization_approved', [
                    'tenant_id'     => $correction->tenant_id,
                    'employee_name' => $emp?->full_name ?? 'Employee',
                    'date'          => $dateFormatted,
                    'approved_by'   => $user?->name ?? 'Manager',
                    'employee_id'   => $emp?->id,
                    'user_id'       => $emp?->user_id,
                ], $correction);
            } catch (\Throwable $notifEx) {
                Log::warning('Attendance regularization approval notification failed: ' . $notifEx->getMessage());
            }

            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'Attendance correction approved and attendance records updated.',
                'data' => $this->transformCorrection($correction),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve attendance correction', [
                'correction_id' => $correction->id,
                'error'         => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Failed to approve attendance correction: ' . $e->getMessage(),
            ];
        }
    }

    public function reject(AttendanceCorrection $correction, ?string $reason, ?User $user): array
    {
        if ($correction->status !== 'pending') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => "Only pending requests can be rejected. Current status: {$correction->status}.",
            ];
        }

        $this->ensureColumnsExist();

        $updatePayload = [
            'status'          => 'rejected',
            'rejected_reason' => $reason,
            'approved_by'     => $user?->id,
        ];
        if (Schema::hasColumn('attendance_corrections', 'approved_at')) {
            $updatePayload['approved_at'] = Carbon::now();
        }

        $correction->update($updatePayload);

        $correction->load(['employee', 'attendance']);

        // Notify Employee of rejection
        try {
            $emp = $correction->employee ?? Employee::find($correction->employee_id);
            $dateFormatted = $correction->date ? $correction->date->format('d M Y') : 'requested date';
            $actionUrl = \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : url('/hrms/attendance');
            if ($emp) {
                $reasonMsg = $reason ? " Reason: {$reason}" : '';
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $emp,
                    title: 'Attendance Regularization Rejected',
                    message: "Your attendance regularization request for {$dateFormatted} has been rejected.{$reasonMsg}",
                    actionUrl: $actionUrl,
                    type: 'attendance_regularization_rejected',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $notifEx) {
            Log::warning('Attendance regularization rejection notification failed: ' . $notifEx->getMessage());
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Attendance correction request rejected.',
            'data' => $this->transformCorrection($correction),
        ];
    }

    public function withdraw(AttendanceCorrection $correction, ?User $user): array
    {
        if ($correction->status !== 'pending') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Only pending correction requests can be withdrawn.',
            ];
        }

        $correction->update([
            'status' => 'cancelled',
            'rejected_reason' => 'Withdrawn by employee before manager decision',
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Attendance correction request withdrawn successfully.',
            'data' => $this->transformCorrection($correction),
        ];
    }

    public function requestCancellation(AttendanceCorrection $correction, string $reason, ?User $user): array
    {
        if ($correction->status !== 'approved') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Cancellation can only be requested for approved corrections.',
            ];
        }

        $this->ensureColumnsExist();

        $updatePayload = [
            'status' => 'cancellation_requested',
        ];
        if (Schema::hasColumn('attendance_corrections', 'cancellation_reason')) {
            $updatePayload['cancellation_reason'] = $reason;
        }
        if (Schema::hasColumn('attendance_corrections', 'cancellation_requested_at')) {
            $updatePayload['cancellation_requested_at'] = Carbon::now();
        }

        $correction->update($updatePayload);

        try {
            $correction->loadMissing(['employee.reportingManager']);
            $emp = $correction->employee;
            $dateFormatted = $correction->date ? $correction->date->format('d M Y') : 'Attendance Date';
            $actionUrl = route('hrms.attendance.index', ['view' => 'corrections']);

            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'Attendance Regularization Cancellation Requested',
                message: "{$emp?->full_name} requested cancellation for attendance regularization on {$dateFormatted}.",
                actionUrl: $actionUrl,
                type: 'attendance_regularization_cancellation_requested',
                iconClass: 'feather-alert-circle'
            );

            if ($emp?->reportingManager) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $emp->reportingManager,
                    title: 'Attendance Regularization Cancellation Requested',
                    message: "{$emp->full_name} requested cancellation for attendance regularization on {$dateFormatted}.",
                    actionUrl: $actionUrl,
                    type: 'attendance_regularization_cancellation_requested',
                    iconClass: 'feather-alert-circle'
                );
            }
        } catch (\Throwable $notifEx) {
            Log::warning('Attendance regularization cancellation request notification failed: ' . $notifEx->getMessage());
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Cancellation requested. Awaiting administrator approval.',
            'data' => $this->transformCorrection($correction),
        ];
    }

    public function approveCancellation(AttendanceCorrection $correction, ?User $user): array
    {
        if ($correction->status !== 'cancellation_requested') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'This request is not awaiting cancellation approval.',
            ];
        }

        $this->ensureColumnsExist();

        DB::beginTransaction();
        try {
            $dateStr = $correction->date ? $correction->date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $cacheKey = "attendance_correction_prev_{$correction->id}";
            $preState = Cache::get($cacheKey);

            $attendance = $correction->attendance ?? Attendance::where('tenant_id', $correction->tenant_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $correction->date)
                ->first();

            // 1. Revert attendance record to pre-correction state
            if ($preState !== null) {
                if (empty($preState['attendance_existed'])) {
                    // Attendance was synthesized/created solely by this correction for an absent day.
                    // On cancellation, remove the created attendance record so employee reverts to absent.
                    if ($attendance) {
                        $attendance->delete();
                    }
                    $correction->update(['attendance_id' => null]);
                    $attendance = null;
                } else {
                    // Attendance existed prior to correction. Revert fields back to their original values.
                    if ($attendance) {
                        $attendance->update([
                            'check_in'         => $preState['check_in'],
                            'check_out'        => $preState['check_out'],
                            'status'           => $preState['status'] ?? 'absent',
                            'total_work_hours' => $preState['total_work_hours'] ?? 0.00,
                        ]);
                    }
                }
            } else {
                // Fallback if pre-state cache is not available
                if ($attendance) {
                    $attendance->update([
                        'status'           => 'absent',
                        'check_in'         => null,
                        'check_out'        => null,
                        'total_work_hours' => 0.00,
                    ]);
                }
            }

            // 2. Restore penalties that were waived by this correction
            $penaltyMarker = "[Waived by correction #{$correction->id}]";
            $waivedPenalties = EmployeePenalty::where('employee_id', $correction->employee_id)
                ->whereDate('date', $dateStr)
                ->where('remarks', 'like', "%{$penaltyMarker}%")
                ->get();

            foreach ($waivedPenalties as $wp) {
                $cleanedRemarks = trim(str_replace($penaltyMarker, '', $wp->remarks));
                $wp->update([
                    'status'  => 'pending',
                    'remarks' => $cleanedRemarks,
                ]);
            }

            // 3. Restore any snapshotted penalties that might have been hard-deleted
            if (!empty($preState['penalties'])) {
                foreach ($preState['penalties'] as $ps) {
                    $exists = EmployeePenalty::where('employee_id', $ps['employee_id'])
                        ->whereDate('date', $ps['date'])
                        ->where('rule_type', $ps['rule_type'])
                        ->where('status', '!=', 'excused')
                        ->exists();

                    if (!$exists) {
                        EmployeePenalty::create([
                            'tenant_id'      => $ps['tenant_id'],
                            'employee_id'    => $ps['employee_id'],
                            'date'           => $ps['date'],
                            'rule_type'      => $ps['rule_type'],
                            'penalty_amount' => $ps['penalty_amount'],
                            'status'         => 'pending',
                            'payroll_month'  => $ps['payroll_month'],
                            'remarks'        => $ps['remarks'],
                        ]);
                    }
                }
            }

            // 4. If attendance record was retained with punches, re-evaluate statutory attendance penalties
            $employee = Employee::find($correction->employee_id);
            if ($employee && $attendance && $attendance->exists && $attendance->status !== 'absent') {
                $penaltyService = app(AttendancePenaltyService::class);
                if ($attendance->check_in) {
                    $penaltyService->evaluateLateArrival($employee, $dateStr, $attendance->check_in, $attendance->status);
                }
                if ($attendance->total_work_hours !== null && (float) $attendance->total_work_hours > 0) {
                    $penaltyService->evaluateUnderHours($employee, $dateStr, (float) $attendance->total_work_hours, $attendance->status);
                }
                if ($attendance->check_in && empty($attendance->check_out)) {
                    $penaltyService->evaluateMissingLogs($employee, $dateStr, 1);
                }
            }

            // 5. Update correction status to cancelled
            $cancelPayload = [
                'status' => 'cancelled',
            ];
            if (Schema::hasColumn('attendance_corrections', 'cancellation_decided_by')) {
                $cancelPayload['cancellation_decided_by'] = $user?->id;
            }
            if (Schema::hasColumn('attendance_corrections', 'cancellation_decided_at')) {
                $cancelPayload['cancellation_decided_at'] = Carbon::now();
            }
            $correction->update($cancelPayload);

            Cache::forget($cacheKey);

            DB::commit();

            try {
                $correction->loadMissing('employee');
                $dateFormatted = $correction->date ? $correction->date->format('d M Y') : 'Attendance Date';
                if ($correction->employee) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $correction->employee,
                        title: 'Attendance Regularization Cancellation Approved',
                        message: "Your cancellation request for attendance regularization on {$dateFormatted} has been approved.",
                        actionUrl: route('hrms.attendance.index', ['view' => 'corrections']),
                        type: 'attendance_regularization_cancellation_approved',
                        iconClass: 'feather-check-circle'
                    );
                }
            } catch (\Throwable $notifEx) {
                Log::warning('Attendance regularization cancellation approve notification failed: ' . $notifEx->getMessage());
            }

            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'Attendance correction cancellation approved.',
                'data' => $this->transformCorrection($correction),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Failed to approve cancellation: ' . $e->getMessage(),
            ];
        }
    }

    public function denyCancellation(AttendanceCorrection $correction, ?string $reason, ?User $user): array
    {
        if ($correction->status !== 'cancellation_requested') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'This request is not awaiting cancellation approval.',
            ];
        }

        $this->ensureColumnsExist();

        $denyPayload = [
            'status' => 'approved',
        ];
        if (Schema::hasColumn('attendance_corrections', 'cancellation_decided_by')) {
            $denyPayload['cancellation_decided_by'] = $user?->id;
        }
        if (Schema::hasColumn('attendance_corrections', 'cancellation_decided_at')) {
            $denyPayload['cancellation_decided_at'] = Carbon::now();
        }
        if (Schema::hasColumn('attendance_corrections', 'cancellation_denial_reason')) {
            $denyPayload['cancellation_denial_reason'] = $reason;
        }

        $correction->update($denyPayload);

        try {
            $correction->loadMissing('employee');
            $dateFormatted = $correction->date ? $correction->date->format('d M Y') : 'Attendance Date';
            if ($correction->employee) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $correction->employee,
                    title: 'Attendance Regularization Cancellation Denied',
                    message: "Your cancellation request for attendance regularization on {$dateFormatted} was denied. The regularization remains approved.",
                    actionUrl: route('hrms.attendance.index', ['view' => 'corrections']),
                    type: 'attendance_regularization_cancellation_denied',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $notifEx) {
            Log::warning('Attendance regularization cancellation deny notification failed: ' . $notifEx->getMessage());
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Attendance correction cancellation denied. Record remains approved.',
            'data' => $this->transformCorrection($correction),
        ];
    }
}
