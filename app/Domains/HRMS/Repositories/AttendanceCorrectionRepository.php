<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeePenalty;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceCorrectionRepository implements AttendanceCorrectionRepositoryInterface
{
    public function __construct(
        private readonly HrmsScopeService $scopeService
    ) {}

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

        $correction->load(['employee', 'attendance']);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => 'Attendance correction request submitted successfully.',
            'data' => $this->transformCorrection($correction),
        ];
    }

    public function approve(AttendanceCorrection $correction, ?User $user): array
    {
        if ($correction->status !== 'pending') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => "Only pending requests can be approved. Current status: {$correction->status}.",
            ];
        }

        DB::beginTransaction();
        try {
            $correction->update([
                'status'          => 'approved',
                'approved_by'     => $user?->id,
                'approved_at'     => Carbon::now(),
                'rejected_reason' => null,
            ]);

            $attendance = $correction->attendance ?? Attendance::where('tenant_id', $correction->tenant_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $correction->date)
                ->first();

            $dateStr = $correction->date ? $correction->date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $requestedCheckIn = $correction->requested_check_in 
                ? Carbon::parse($dateStr . ' ' . $correction->requested_check_in->format('H:i:s')) 
                : null;
            $requestedCheckOut = $correction->requested_check_out 
                ? Carbon::parse($dateStr . ' ' . $correction->requested_check_out->format('H:i:s')) 
                : null;

            if ($attendance) {
                $attendance->update([
                    'check_in'         => $requestedCheckIn ?? $attendance->check_in,
                    'check_out'        => $requestedCheckOut ?? $attendance->check_out,
                    'status'           => 'present',
                    'total_work_hours' => ($requestedCheckIn && $requestedCheckOut) 
                        ? round($requestedCheckIn->diffInMinutes($requestedCheckOut) / 60, 2) 
                        : $attendance->total_work_hours,
                ]);
            } else {
                $employee = Employee::find($correction->employee_id);
                $attendance = Attendance::create([
                    'tenant_id'        => $correction->tenant_id,
                    'company_id'       => $employee?->company_id,
                    'employee_id'      => $correction->employee_id,
                    'date'             => $dateStr,
                    'check_in'         => $requestedCheckIn,
                    'check_out'        => $requestedCheckOut,
                    'status'           => 'present',
                    'total_work_hours' => ($requestedCheckIn && $requestedCheckOut) 
                        ? round($requestedCheckIn->diffInMinutes($requestedCheckOut) / 60, 2) 
                        : 0,
                ]);

                $correction->update(['attendance_id' => $attendance->id]);
            }

            EmployeePenalty::where('tenant_id', $correction->tenant_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $dateStr)
                ->where('status', 'active')
                ->delete();

            DB::commit();

            $correction->load(['employee', 'attendance']);

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

        $correction->update([
            'status'          => 'rejected',
            'rejected_reason' => $reason,
            'approved_by'     => $user?->id,
            'approved_at'     => Carbon::now(),
        ]);

        $correction->load(['employee', 'attendance']);

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

        $correction->update([
            'status' => 'cancellation_requested',
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => Carbon::now(),
        ]);

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

        DB::beginTransaction();
        try {
            $correction->update([
                'status' => 'cancelled',
                'cancellation_decided_by' => $user?->id,
                'cancellation_decided_at' => Carbon::now(),
            ]);

            DB::commit();

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

        $correction->update([
            'status' => 'approved',
            'cancellation_decided_by' => $user?->id,
            'cancellation_decided_at' => Carbon::now(),
            'cancellation_denial_reason' => $reason,
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Attendance correction cancellation denied. Record remains approved.',
            'data' => $this->transformCorrection($correction),
        ];
    }
}
