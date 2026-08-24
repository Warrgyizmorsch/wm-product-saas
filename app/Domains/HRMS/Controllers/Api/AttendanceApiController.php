<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Repositories\AttendanceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceApiController extends Controller
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository
    ) {}

    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }
        return null;
    }

    /**
     * GET /api/hrms/attendance/summary
     * Retrieve simple stats for attendance on a given date (defaults to today).
     */
    public function summary(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $stats = [
            'total_present' => Attendance::where('date', $date)->count(),
            'total_on_break' => Attendance::where('date', $date)
                ->whereHas('breaks', function ($q) {
                    $q->whereNull('break_out');
                })->count(),
            'total_late' => Attendance::where('date', $date)->where('status', 'late')->count(),
            'total_wfh'  => Attendance::where('date', $date)->where('location_type', 'wfh')->count(),
        ];

        return $this->sendSuccess($stats, 'Attendance summary retrieved successfully');
    }

    /**
     * GET /api/hrms/attendance
     * List all attendance logs with pagination & filters.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $filters = $request->only(['date', 'department_id', 'search', 'status']);
        $date = $filters['date'] ?? Carbon::today()->format('Y-m-d');
        $departmentId = $filters['department_id'] ?? null;
        $search = $filters['search'] ?? null;
        $statusFilter = $filters['status'] ?? null;

        $query = Attendance::with(['employee.department', 'breaks']);

        if ($date) {
            $query->where('date', $date);
        }

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'date_desc');
        if ($sort === 'name_asc') {
            $query->select('attendances.*')
                ->leftJoin('employees', 'employees.id', '=', 'attendances.employee_id')
                ->orderBy('employees.full_name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->select('attendances.*')
                ->leftJoin('employees', 'employees.id', '=', 'attendances.employee_id')
                ->orderBy('employees.full_name', 'desc');
        } else {
            $query->orderBy('date', 'desc');
        }

        $attendances = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($attendances, 'Attendance logs list retrieved successfully');
    }

    /**
     * GET /api/hrms/attendance/my-attendance
     * Retrieve current logged-in employee's attendance logs.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->sendError('Employee profile not found.', 404);
        }

        $date = $request->input('date');
        $status = $request->input('status');
        $sort = $request->input('sort', 'date_desc');
        $search = $request->input('search');

        $monthFilter = $request->input('month');
        if (!$request->has('month') && !$request->has('date')) {
            $monthFilter = now()->format('Y-m');
        }

        $query = Attendance::where('employee_id', $employee->id)
            ->with('breaks');

        if ($date) {
            $query->where('date', $date);
        } elseif ($monthFilter) {
            $query->where('date', 'like', "{$monthFilter}%");
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('location_type', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%");
            });
        }

        if ($sort === 'date_asc') {
            $query->orderBy('date', 'asc');
        } else {
            $query->orderBy('date', 'desc');
        }

        $attendances = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess([
            'employee'    => $employee->load(['department', 'designation']),
            'attendances' => $attendances
        ], 'Personal attendance history loaded');
    }

    /**
     * POST /api/hrms/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'selfie'      => 'nullable|string',
        ]);

        try {
            $attendance = $this->attendanceRepository->checkIn(
                $validated['employee_id'],
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie')
            );
            return $this->sendSuccess($attendance->load('breaks'), 'Clocked in successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * POST /api/hrms/attendance/{attendance}/check-out
     */
    public function checkOut(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->sendError("Attendance log with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'selfie'    => 'nullable|string',
        ]);

        try {
            $updatedAttendance = $this->attendanceRepository->checkOut(
                $attendance->id,
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie')
            );
            return $this->sendSuccess($updatedAttendance->load('breaks'), 'Clocked out successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * POST /api/hrms/attendance/{attendance}/break-in
     */
    public function breakIn(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->sendError("Attendance log with ID '{$id}' not found.", 404);
        }

        $this->attendanceRepository->breakIn($attendance->id);
        return $this->sendSuccess($attendance->fresh()->load('breaks'), 'Break started successfully.');
    }

    /**
     * POST /api/hrms/attendance/{attendance}/break-out
     */
    public function breakOut(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->sendError("Attendance log with ID '{$id}' not found.", 404);
        }

        $this->attendanceRepository->breakOut($attendance->id);
        return $this->sendSuccess($attendance->fresh()->load('breaks'), 'Break ended successfully.');
    }

    /**
     * POST /api/hrms/attendance/manual
     * Handle manual additions/modifications of attendance records.
     */
    public function storeManual(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employeeId = $request->input('employee_id');

        if ($employeeId) {
            // SINGLE EMPLOYEE MODE: multiple dates for one employee
            $validated = $request->validate([
                'employee_id'            => 'required|exists:employees,id',
                'attendance'             => 'required|array',
                'attendance.*.date'      => 'required|date',
                'attendance.*.check_in'  => 'nullable|string',
                'attendance.*.check_out' => 'nullable|string',
                'attendance.*.status'    => 'required|string|in:auto,present,absent,late,half_day,on_leave,wfh',
            ]);

            $tenantId = auth()->user()?->tenant_id;
            $updatedRecords = [];

            foreach ($validated['attendance'] as $row) {
                $dateStr = $row['date'];
                $status = $row['status'];
                $checkInTime = $row['check_in'] ? trim($row['check_in']) : null;
                $checkOutTime = $row['check_out'] ? trim($row['check_out']) : null;

                // Constraint: marking present/late/wfh/half-day requires check_in time
                if (!$checkInTime && in_array($status, ['present', 'late', 'half_day', 'wfh'])) {
                    $employee = Employee::find($employeeId);
                    $empName = $employee ? $employee->display_name : "Employee ID {$employeeId}";
                    return $this->sendError("Employee '{$empName}' cannot be marked as " . ucfirst(str_replace('_', ' ', $status)) . " without a Check-in time on {$dateStr}.", 422);
                }

                $checkInDatetime = null;
                if ($checkInTime) {
                    $checkInDatetime = Carbon::parse($dateStr . ' ' . $checkInTime);
                } elseif (in_array($status, ['absent', 'on_leave'])) {
                    $checkInDatetime = Carbon::parse($dateStr . ' 00:00:00');
                }

                $checkOutDatetime = null;
                if ($checkOutTime) {
                    $checkOutDatetime = Carbon::parse($dateStr . ' ' . $checkOutTime);
                }

                $workHours = 0.00;
                if ($checkInDatetime && $checkOutDatetime) {
                    if ($checkOutDatetime->greaterThan($checkInDatetime)) {
                        $workHours = round($checkInDatetime->diffInMinutes($checkOutDatetime, true) / 60.0, 2);
                    }
                }

                // Auto Detect Status Logic
                if ($status === 'auto') {
                    if (!$checkInDatetime) {
                        $hasLeave = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employeeId)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $dateStr)
                            ->whereDate('end_date', '>=', $dateStr)
                            ->exists();

                        $status = $hasLeave ? 'on_leave' : 'absent';
                        $checkInDatetime = Carbon::parse($dateStr . ' 00:00:00');
                    } else {
                        $employee = Employee::find($employeeId);
                        
                        $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $dateStr)
                            ->whereDate('end_date', '>=', $dateStr)
                            ->exists();

                        $status = $hasWfh ? 'wfh' : 'present';

                        $resolvedShift = $employee ? $employee->resolveShiftForDate($dateStr) : null;
                        if ($resolvedShift) {
                            $shiftStartStr = $resolvedShift->start_time;
                            $shiftStart = Carbon::parse($dateStr . ' ' . $shiftStartStr);

                            if ($checkInDatetime->greaterThan($shiftStart)) {
                                $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                        $q->where('company_id', $employee->company_id)
                                          ->orWhereNull('company_id');
                                    })
                                    ->where('rule_type', 'late_arrival')
                                    ->where('status', true)
                                    ->orderByRaw('company_id IS NULL ASC')
                                    ->first();

                                $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                                $diffMinutes = $checkInDatetime->diffInMinutes($shiftStart);

                                if ($diffMinutes > $graceMinutes) {
                                    $status = 'late';
                                }
                            }
                        }

                        if ($checkInDatetime && $checkOutDatetime && $workHours !== null && $employee) {
                            $underHoursRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                })
                                    ->where('rule_type', 'under_hours')
                                    ->where('status', true)
                                    ->orderByRaw('company_id IS NULL ASC')
                                    ->first();

                            if ($underHoursRule && is_array($underHoursRule->penalty_tiers)) {
                                $sortedTiers = collect($underHoursRule->penalty_tiers)->sortBy('hours_threshold')->all();
                                foreach ($sortedTiers as $tier) {
                                    $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;
                                    if ($workHours < $hoursThreshold) {
                                        $action = $tier['penalty_action'] ?? '';
                                        $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;
                                        if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                                            if ($val >= 1.0) {
                                                $status = 'absent';
                                            } elseif ($val > 0) {
                                                $status = 'half_day';
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                $locationType = ($status === 'wfh') ? 'wfh' : 'office';

                $attendance = Attendance::updateOrCreate([
                    'tenant_id'   => $tenantId,
                    'employee_id' => $employeeId,
                    'date'        => $dateStr,
                ], [
                    'check_in'         => $checkInDatetime,
                    'check_out'        => $checkOutDatetime,
                    'location_type'    => $locationType,
                    'status'           => $status,
                    'total_work_hours' => $workHours,
                ]);

                $updatedRecords[] = $attendance;
            }

            return $this->sendSuccess($updatedRecords, 'Manual attendance updated successfully.');
        }

        // MULTI EMPLOYEE MODE: multiple employees for one date
        $validated = $request->validate([
            'date'                     => 'required|date',
            'attendance'               => 'required|array',
            'attendance.*.employee_id' => 'required|exists:employees,id',
            'attendance.*.check_in'    => 'nullable|string',
            'attendance.*.check_out'   => 'nullable|string',
            'attendance.*.status'      => 'required|string|in:auto,present,absent,late,half_day,on_leave,wfh',
        ]);

        $dateStr = $validated['date'];
        $tenantId = auth()->user()?->tenant_id;
        $updatedRecords = [];

        foreach ($validated['attendance'] as $row) {
            $employeeId = $row['employee_id'];
            $status = $row['status'];
            $checkInTime = $row['check_in'] ? trim($row['check_in']) : null;
            $checkOutTime = $row['check_out'] ? trim($row['check_out']) : null;

            if (!$checkInTime && in_array($status, ['present', 'late', 'half_day', 'wfh'])) {
                $employee = Employee::find($employeeId);
                $empName = $employee ? $employee->display_name : "Employee ID {$employeeId}";
                return $this->sendError("Employee '{$empName}' cannot be marked as " . ucfirst(str_replace('_', ' ', $status)) . " without a Check-in time.", 422);
            }

            $checkInDatetime = null;
            if ($checkInTime) {
                $checkInDatetime = Carbon::parse($dateStr . ' ' . $checkInTime);
            } elseif (in_array($status, ['absent', 'on_leave'])) {
                $checkInDatetime = Carbon::parse($dateStr . ' 00:00:00');
            }

            $checkOutDatetime = null;
            if ($checkOutTime) {
                $checkOutDatetime = Carbon::parse($dateStr . ' ' . $checkOutTime);
            }

            $workHours = 0.00;
            if ($checkInDatetime && $checkOutDatetime) {
                if ($checkOutDatetime->greaterThan($checkInDatetime)) {
                    $workHours = round($checkInDatetime->diffInMinutes($checkOutDatetime, true) / 60.0, 2);
                }
            }

            if ($status === 'auto') {
                if (!$checkInDatetime) {
                    $hasLeave = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employeeId)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->exists();

                    $status = $hasLeave ? 'on_leave' : 'absent';
                    $checkInDatetime = Carbon::parse($dateStr . ' 00:00:00');
                } else {
                    $employee = Employee::find($employeeId);
                    $hasWfh = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employeeId)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->exists();

                    $status = $hasWfh ? 'wfh' : 'present';

                    $resolvedShift = $employee ? $employee->resolveShiftForDate($dateStr) : null;
                    if ($resolvedShift) {
                        $shiftStartStr = $resolvedShift->start_time;
                        $shiftStart = Carbon::parse($dateStr . ' ' . $shiftStartStr);

                        if ($checkInDatetime->greaterThan($shiftStart)) {
                            $penaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                })
                                ->where('rule_type', 'late_arrival')
                                ->where('status', true)
                                ->orderByRaw('company_id IS NULL ASC')
                                ->first();

                            $graceMinutes = $penaltyRule ? (int)$penaltyRule->grace_period_minutes : 15;
                            $diffMinutes = $checkInDatetime->diffInMinutes($shiftStart);

                            if ($diffMinutes > $graceMinutes) {
                                $status = 'late';
                            }
                        }
                    }

                    if ($checkInDatetime && $checkOutDatetime && $workHours !== null && $employee) {
                        $underHoursRule = \App\Domains\HRMS\Models\AttendancePenalty::where(function ($q) use ($employee) {
                                $q->where('company_id', $employee->company_id)
                                  ->orWhereNull('company_id');
                            })
                                ->where('rule_type', 'under_hours')
                                ->where('status', true)
                                ->orderByRaw('company_id IS NULL ASC')
                                ->first();

                        if ($underHoursRule && is_array($underHoursRule->penalty_tiers)) {
                            $sortedTiers = collect($underHoursRule->penalty_tiers)->sortBy('hours_threshold')->all();
                            foreach ($sortedTiers as $tier) {
                                $hoursThreshold = isset($tier['hours_threshold']) ? floatval($tier['hours_threshold']) : 0;
                                if ($workHours < $hoursThreshold) {
                                    $action = $tier['penalty_action'] ?? '';
                                    $val = isset($tier['penalty_value']) ? floatval($tier['penalty_value']) : 0;
                                    if ($action === 'working_hour_deduction' || $action === 'both_deductions') {
                                        if ($val >= 1.0) {
                                            $status = 'absent';
                                        } elseif ($val > 0) {
                                            $status = 'half_day';
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            $locationType = ($status === 'wfh') ? 'wfh' : 'office';

            $attendance = Attendance::updateOrCreate([
                'tenant_id'   => $tenantId,
                'employee_id' => $employeeId,
                'date'        => $dateStr,
            ], [
                'check_in'         => $checkInDatetime,
                'check_out'        => $checkOutDatetime,
                'location_type'    => $locationType,
                'status'           => $status,
                'total_work_hours' => $workHours,
            ]);

            $updatedRecords[] = $attendance;
        }

        return $this->sendSuccess($updatedRecords, 'Manual attendance updated successfully.');
    }

    /**
     * DELETE /api/hrms/attendance/date/{date}
     * Remove all attendance logs for a specific date.
     */
    public function destroyDate(mixed $date): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendances = Attendance::where('date', $date)->get();
        foreach ($attendances as $attendance) {
            $attendance->breaks()->delete();
            $attendance->delete();
        }

        return $this->sendSuccess(null, 'Attendance logs deleted successfully.');
    }

    /**
     * POST /api/hrms/attendance/track-location
     */
    public function trackLocation(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
        ]);

        $result = $this->attendanceRepository->trackLocation(
            (int) $validated['employee_id'],
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        return $this->sendSuccess($result, 'Location log recorded');
    }
}
