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
     * Helper to check if current user has HR Admin / Attendance permissions.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasHrPermission('hrms.attendance.view')
            || $user->hasHrPermission('hrms.attendance.manage')
            || $user->hasHrPermission('hr.settings.manage');
    }

    /**
     * Helper to get authenticated employee.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)
            ->where('tenant_id', tenant_id() ?? $user->tenant_id ?? 1)
            ->first()
            ?? Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
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
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $baseQuery = Attendance::where('date', $date);
        if (!$isHrAdmin && $employee) {
            $baseQuery->where('employee_id', $employee->id);
        }

        $stats = [
            'total_present' => (clone $baseQuery)->count(),
            'total_on_break' => (clone $baseQuery)
                ->whereHas('breaks', function ($q) {
                    $q->whereNull('break_out');
                })->count(),
            'total_late' => (clone $baseQuery)->where('status', 'late')->count(),
            'total_wfh'  => (clone $baseQuery)->where('location_type', 'wfh')->count(),
        ];

        return $this->sendSuccess($stats, 'Attendance summary retrieved successfully');
    }

    /**
     * Helper to transform raw Attendance model into clean API DTO.
     */
    private function transformAttendance(Attendance $att): array
    {
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();
        $isOwner = $employee && (int)$att->employee_id === (int)$employee->id;
        $isToday = $att->date && $att->date->isToday();
        $isActiveShift = !empty($att->check_in) && empty($att->check_out);
        $activeBreak = $att->breaks ? $att->breaks->whereNull('break_out')->first() : null;
        $isOnBreak = !empty($activeBreak);

        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($att->employee ?? $employee);
        $allowMultiple = (bool) ($rule?->allow_multiple_punches ?? false);
        $canClockIn = $isOwner && $isToday && (empty($att->check_in) || (!empty($att->check_out) && $allowMultiple));

        return [
            'id'                  => $att->id,
            'employee_id'         => $att->employee_id,
            'employee_code'       => $att->employee?->employee_id ?? '',
            'employee_name'       => $att->employee?->full_name ?? '',
            'department'          => $att->employee?->department?->name ?? '',
            'date'                => $att->date ? $att->date->format('Y-m-d') : null,
            'check_in'            => $att->check_in ? $att->check_in->format('H:i:s') : null,
            'check_out'           => $att->check_out ? $att->check_out->format('H:i:s') : null,
            'location_type'       => $att->location_type ?? 'office',
            'status'              => $att->status ?? 'present',
            'total_work_hours'    => (float) ($att->total_work_hours ?? 0),
            'total_break_hours'   => (float) ($att->total_break_hours ?? 0),
            'formatted_work_time' => $att->formatted_work_hours,
            'formatted_break_time'=> $att->formatted_break_hours,
            'is_on_break'         => $isOnBreak,
            'active_break_id'     => $activeBreak?->id,
            'breaks_count'        => $att->breaks->count(),
            'check_in_latitude'   => $att->check_in_latitude ? (float) $att->check_in_latitude : null,
            'check_in_longitude'  => $att->check_in_longitude ? (float) $att->check_in_longitude : null,
            'check_out_latitude'  => $att->check_out_latitude ? (float) $att->check_out_latitude : null,
            'check_out_longitude' => $att->check_out_longitude ? (float) $att->check_out_longitude : null,
            'check_in_selfie_url' => $att->check_in_selfie_path ? asset('storage/' . $att->check_in_selfie_path) : null,
            'check_out_selfie_url'=> $att->check_out_selfie_path ? asset('storage/' . $att->check_out_selfie_path) : null,
            'total_distance_km'   => (float) ($att->total_distance_km ?? 0),
            'route_snapshot_url'  => $att->route_snapshot_url,
            'route_polyline'      => $att->route_polyline,
            'capabilities'        => [
                'can_view'          => true,
                'can_clock_in'      => $canClockIn,
                'can_clock_out'     => $isOwner && ($isToday || $isActiveShift) && $isActiveShift,
                'can_start_break'   => $isOwner && ($isToday || $isActiveShift) && $isActiveShift && !$isOnBreak,
                'can_end_break'     => $isOwner && ($isToday || $isActiveShift) && $isActiveShift && $isOnBreak,
                'can_regularize'    => ($isOwner || $isHrAdmin) && !empty($att->id),
                'can_edit'          => $isHrAdmin,
            ],
        ];
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
        $date = $filters['date'] ?? null;
        $departmentId = $filters['department_id'] ?? null;
        $search = $filters['search'] ?? null;
        $statusFilter = $filters['status'] ?? null;

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $query = Attendance::with(['employee.department', 'breaks']);

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError('Employee profile not found.', 404);
            }
            $query->where('employee_id', $employee->id);
        }

        if ($date && $date !== 'all') {
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

        $paginated = $query->paginate($request->integer('per_page', 10));

        $response = [
            'items' => collect($paginated->items())->map(fn($att) => $this->transformAttendance($att))->values(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'total_pages'  => $paginated->lastPage(),
            ]
        ];

        return $this->sendSuccess($response, 'Attendance logs list retrieved successfully');
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

        $employee = $this->getAuthenticatedEmployee();

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
            ->with(['employee.department', 'breaks']);

        if ($date && $date !== 'all') {
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

        $paginated = $query->paginate($request->integer('per_page', 10));

        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', now()->format('Y-m-d'))
            ->with(['employee.department', 'breaks'])
            ->first();

        $rule = \App\Domains\HRMS\Models\AttendanceRule::forEmployee($employee);

        // Overall check: 1. Approved WFH request for today -> 2. Employee profile default -> 3. Today's attendance
        $todayStr = now()->format('Y-m-d');
        $hasWfhToday = \App\Domains\HRMS\Models\WfhRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->exists();

        $empOffice = strtolower(trim((string) $employee->office));
        $defaultLoc = $hasWfhToday ? 'wfh' : (in_array($empOffice, ['onsite', 'site', 'wfh']) ? (in_array($empOffice, ['onsite', 'site']) ? 'onsite' : 'wfh') : 'office');
        $workLoc = $todayAttendance?->location_type ?? $defaultLoc;

        $trackingSelected = $rule ? $rule->isTrackingEnabled($workLoc) : false;
        $trackingMinutes = $trackingSelected ? $rule->getTrackingMinutes($workLoc) : null;
        $selfieRequired = $workLoc === 'wfh'
            ? (bool) ($rule?->wfh_selfie ?? false)
            : ($workLoc === 'onsite' ? (bool) ($rule?->site_selfie ?? false) : false);

        $trackingConfig = [
            'enabled'                 => $trackingSelected && ($trackingMinutes ?? 0) >= 1,
            'interval_seconds'        => $trackingMinutes ? $trackingMinutes * 60 : null,
            'location_type'           => $workLoc,
            'selfie_required'         => $selfieRequired,
            'punch_allowed'           => $workLoc !== 'office' || !$rule || (bool) $rule->office_web,
            'pause_tracking_on_break' => (bool) ($rule?->pause_tracking_on_break ?? false),
            'office_break_geofence'   => (bool) ($rule?->office_break_geofence ?? false),
            'allow_multiple_punches'  => (bool) ($rule?->allow_multiple_punches ?? false),
            'location_required'       => $trackingSelected || ($workLoc === 'onsite'
                ? (bool) ($rule?->site_location ?? false)
                : ($workLoc === 'office'
                    ? (bool) ($rule?->office_geofence ?? false)
                    : (bool) (($rule?->wfh_location ?? false) || ($rule?->wfh_geofence ?? false)))),
        ];

        $response = [
            'employee' => [
                'id'            => $employee->id,
                'employee_code' => $employee->employee_id,
                'full_name'     => $employee->full_name,
                'department'    => $employee->department?->name ?? '',
                'designation'   => $employee->designation?->name ?? '',
            ],
            'today_attendance' => $todayAttendance ? $this->transformAttendance($todayAttendance) : null,
            'tracking_config'  => $trackingConfig,
            'items' => collect($paginated->items())->map(fn($att) => $this->transformAttendance($att))->values(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'total_pages'  => $paginated->lastPage(),
            ]
        ];

        return $this->sendSuccess($response, 'Personal attendance history loaded');
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
            'employee_id' => 'nullable|exists:employees,id',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'selfie'      => 'nullable|string',
        ]);

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin && !$authEmployee) {
            return $this->sendError('Employee profile not found for the authenticated user.', 404);
        }

        if (!$isHrAdmin && !empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only clock in for yourself.', 403);
        }

        $targetEmployeeId = $validated['employee_id'] ?? $authEmployee?->id;

        if (!$targetEmployeeId) {
            return $this->sendError('Employee profile not found for the authenticated user.', 404);
        }

        try {
            $attendance = $this->attendanceRepository->checkIn(
                (int) $targetEmployeeId,
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie')
            );
            return $this->sendSuccess($attendance->load('breaks'), 'Clocked in successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->sendError('Failed to clock in: ' . $e->getMessage(), 500);
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

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $authEmployee && (int) $attendance->employee_id !== (int) $authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only clock out for yourself.', 403);
        }

        $validated = $request->validate([
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'selfie'         => 'nullable|string',
            'route_snapshot' => 'nullable', // supports file upload (image) or base64 string from app
        ]);

        $routeSnapshot = $request->file('route_snapshot') ?? $request->input('route_snapshot');

        try {
            $updatedAttendance = $this->attendanceRepository->checkOut(
                $attendance->id,
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $request->input('selfie'),
                $routeSnapshot
            );
            $fresh = $updatedAttendance->fresh(['breaks', 'employee.department']);
            return $this->sendSuccess($this->transformAttendance($fresh), 'Clocked out successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Clock-out failed for attendance #{$attendance->id}: " . $e->getMessage());
            return $this->sendError('Failed to process clock-out: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/attendance/{attendance}/snapshot
     * Upload or update route snapshot directly from mobile app.
     */
    public function uploadSnapshot(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->sendError("Attendance log with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $authEmployee && (int) $attendance->employee_id !== (int) $authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only upload route snapshot for your own attendance.', 403);
        }

        $request->validate([
            'route_snapshot' => 'required',
        ]);

        try {
            $snapshotInput = $request->file('route_snapshot') ?? $request->input('route_snapshot');
            $url = $this->attendanceRepository->uploadRouteSnapshot($snapshotInput, $attendance->id);

            if (!$url) {
                return $this->sendError('Failed to process route snapshot image. Please provide a valid image file or Base64 string.', 422);
            }

            $attendance->update(['route_snapshot_url' => $url]);

            return $this->sendSuccess([
                'attendance_id'      => (int) $attendance->id,
                'route_snapshot_url' => $attendance->fresh()->route_snapshot_url,
            ], 'Route snapshot uploaded successfully');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to upload route snapshot for attendance #{$attendance->id}: " . $e->getMessage());
            return $this->sendError('Failed to process route snapshot: ' . $e->getMessage(), 500);
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

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $authEmployee && (int) $attendance->employee_id !== (int) $authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only manage break for yourself.', 403);
        }

        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        try {
            $this->attendanceRepository->breakIn($attendance->id, $latitude, $longitude);
            return $this->sendSuccess($attendance->fresh()->load('breaks'), 'Break started successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->sendError('Failed to start break: ' . $e->getMessage(), 500);
        }
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

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin && $authEmployee && (int) $attendance->employee_id !== (int) $authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only manage break for yourself.', 403);
        }

        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        try {
            $this->attendanceRepository->breakOut($attendance->id, $latitude, $longitude);
            return $this->sendSuccess($attendance->fresh()->load('breaks'), 'Break ended successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->sendError('Failed to end break: ' . $e->getMessage(), 500);
        }
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

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to manage manual attendance.', 403);
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

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete attendance logs.', 403);
        }

        $attendances = Attendance::where('date', $date)->get();
        foreach ($attendances as $attendance) {
            $attendance->breaks()->delete();
            $attendance->delete();
        }

        return $this->sendSuccess(['date' => $date], 'Attendance logs deleted successfully.');
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
            'employee_id' => 'nullable|integer|exists:employees,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
        ]);

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            if (!$authEmployee) {
                return $this->sendError('Employee profile not found for the authenticated user.', 404);
            }
            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $authEmployee->id) {
                return $this->sendError('Unauthorized action. You can only track location for yourself.', 403);
            }
            $validated['employee_id'] = $authEmployee->id;
        } elseif (empty($validated['employee_id'])) {
            return $this->sendError('employee_id is required for HR administrators.', 422);
        }

        $result = $this->attendanceRepository->trackLocation(
            (int) $validated['employee_id'],
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        $message = match ($result['status'] ?? null) {
            'below_movement_threshold' => 'Location update skipped because movement did not exceed the configured threshold.',
            'invalid_tracking_configuration' => $result['message'] ?? 'Onsite tracking is not configured correctly.',
            default => 'Location tracking processed successfully.',
        };

        return $this->sendSuccess($result, $message);
    }

    /**
     * GET /api/hrms/attendance/employee/{employee}
     * Retrieve concise attendance details and periodic location logs for an employee.
     */
    public function getEmployeeLocationLogs(Request $request, int|string $employeeId): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return $this->sendError('Employee not found.', 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            if (!$authEmployee) {
                return $this->sendError('Employee profile not found for the authenticated user.', 404);
            }
            if ((int) $employee->id !== (int) $authEmployee->id) {
                return $this->sendError('Unauthorized action. You can only view your own attendance logs.', 403);
            }
        }

        $startDate = $request->query('start_date') ?? $request->query('from');
        $endDate   = $request->query('end_date') ?? $request->query('to');
        $month     = $request->query('month'); // e.g. 2026-09
        $singleDate = $request->query('date');

        // If month is provided, resolve start & end date
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
            $endDate   = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');
        }

        // Multi-day / Date Range query
        if ($startDate && $endDate) {
            $attendances = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['locationLogs' => fn($q) => $q->orderBy('created_at', 'asc')])
                ->orderBy('date', 'desc')
                ->get();

            $history = $attendances->map(function ($attendance) {
                return [
                    'date'                => $attendance->date ? $attendance->date->format('Y-m-d') : null,
                    'status'              => $attendance->status ?? 'present',
                    'location_type'       => $attendance->location_type ?? 'office',
                    'check_in'            => $attendance->check_in ? $attendance->check_in->format('H:i:s') : null,
                    'check_in_latitude'   => $attendance->check_in_latitude ? (float)$attendance->check_in_latitude : null,
                    'check_in_longitude'  => $attendance->check_in_longitude ? (float)$attendance->check_in_longitude : null,
                    'check_out'           => $attendance->check_out ? $attendance->check_out->format('H:i:s') : null,
                    'check_out_latitude'  => $attendance->check_out_latitude ? (float)$attendance->check_out_latitude : null,
                    'check_out_longitude' => $attendance->check_out_longitude ? (float)$attendance->check_out_longitude : null,
                    'total_distance_km'   => (float) ($attendance->total_distance_km ?? 0),
                    'route_snapshot_url'  => $attendance->route_snapshot_url,
                    'route_polyline'      => $attendance->route_polyline,
                    'location_logs'       => $attendance->formatted_location_logs,
                ];
            })->values()->toArray();

            return $this->sendSuccess([
                'employee_id'   => (int) $employee->id,
                'employee_name' => $employee->full_name,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'total_records' => count($history),
                'history'       => $history,
            ], 'Attendance history and location logs retrieved successfully');
        }

        // Single Date Query (defaults to today if not provided)
        $date = $singleDate ?: Carbon::today()->format('Y-m-d');

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $date)
            ->with(['locationLogs' => fn($q) => $q->orderBy('created_at', 'asc')])
            ->first();

        if (!$attendance) {
            return $this->sendSuccess([
                'employee_id'         => (int) $employee->id,
                'employee_name'       => $employee->full_name,
                'date'                => $date,
                'status'              => 'absent',
                'location_type'       => null,
                'check_in'            => null,
                'check_in_latitude'   => null,
                'check_in_longitude'  => null,
                'check_out'           => null,
                'check_out_latitude'  => null,
                'check_out_longitude' => null,
                'total_distance_km'   => 0.0,
                'route_snapshot_url'  => null,
                'route_polyline'      => null,
                'location_logs'       => [],
            ], 'Attendance log retrieved successfully');
        }

        $data = [
            'employee_id'         => (int) $employee->id,
            'employee_name'       => $employee->full_name,
            'date'                => $attendance->date ? $attendance->date->format('Y-m-d') : $date,
            'status'              => $attendance->status ?? 'present',
            'location_type'       => $attendance->location_type ?? 'office',
            'check_in'            => $attendance->check_in ? $attendance->check_in->format('H:i:s') : null,
            'check_in_latitude'   => $attendance->check_in_latitude ? (float) $attendance->check_in_latitude : null,
            'check_in_longitude'  => $attendance->check_in_longitude ? (float) $attendance->check_in_longitude : null,
            'check_out'           => $attendance->check_out ? $attendance->check_out->format('H:i:s') : null,
            'check_out_latitude'  => $attendance->check_out_latitude ? (float) $attendance->check_out_latitude : null,
            'check_out_longitude' => $attendance->check_out_longitude ? (float) $attendance->check_out_longitude : null,
            'total_distance_km'   => (float) ($attendance->total_distance_km ?? 0),
            'route_snapshot_url'  => $attendance->route_snapshot_url,
            'route_polyline'      => $attendance->route_polyline,
            'location_logs'       => $attendance->formatted_location_logs,
        ];

        return $this->sendSuccess($data, 'Attendance and location logs retrieved successfully');
    }

    /**
     * Export attendance logs to CSV.
     */
    public function export(Request $request): mixed
    {
        return $this->attendanceRepository->export($request->all());
    }

    /**
     * Download Attendance Import CSV Template.
     */
    public function downloadTemplate(): mixed
    {
        return $this->attendanceRepository->downloadTemplate();
    }

    /**
     * Bulk Import Attendance & Biometric Punches via API.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $result = $this->attendanceRepository->import($request->file('file'));

        if (!($result['success'] ?? false)) {
            return $this->sendError($result['message'] ?? 'Failed to import attendance.', 422);
        }

        return $this->sendSuccess($result, $result['message'] ?? 'Attendance records processed successfully');
    }

    /**
     * GET /api/hrms/attendance/{attendance}/route
     * Get route history, live coordinates, polyline, and snapshot image.
     */
    public function getRoute(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::with('employee')->find($id);
        if (!$attendance) {
            return $this->sendError("Attendance session #{$id} not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin && (!$authEmployee || (int) $attendance->employee_id !== (int) $authEmployee->id)) {
            return $this->sendError('Unauthorized. You can only view route history for your own attendance.', 403);
        }

        $isActive = empty($attendance->check_out);

        $response = [
            'attendance_id'      => (int) $attendance->id,
            'employee_id'        => (int) $attendance->employee_id,
            'employee_name'      => $attendance->employee?->full_name ?? '',
            'date'               => $attendance->date ? $attendance->date->format('Y-m-d') : null,
            'status'             => $attendance->status ?? 'present',
            'is_active'          => $isActive,
            'check_in'           => $attendance->check_in ? $attendance->check_in->format('H:i:s') : null,
            'check_out'          => $attendance->check_out ? $attendance->check_out->format('H:i:s') : null,
            'total_distance_km'  => (float) ($attendance->total_distance_km ?? 0),
            'route_snapshot_url' => $attendance->route_snapshot_url,
            'route_polyline'     => $attendance->route_polyline,
            'route_summary'      => $attendance->route_summary,
            'tracking_config'    => $attendance->tracking_config,
            'waypoints'          => $attendance->formatted_location_logs,
        ];

        return $this->sendSuccess($response, 'Attendance route details loaded successfully.');
    }

    /**
     * POST /api/hrms/attendance/{attendance}/consolidate-route
     * Manually trigger route consolidation, snapshot generation, and raw logs purging.
     */
    public function consolidateRoute(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->sendError("Attendance session #{$id} not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin && (!$authEmployee || (int) $attendance->employee_id !== (int) $authEmployee->id)) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $result = app(\App\Domains\HRMS\Services\AttendanceRouteService::class)->consolidateShiftRoute($attendance);

        if (!($result['success'] ?? false)) {
            return $this->sendError($result['message'] ?? 'Unable to consolidate route.', 422, $result);
        }

        $message = ($result['already_consolidated'] ?? false)
            ? 'Route was already consolidated; existing snapshot and distance preserved.'
            : 'Route consolidated, snapshot generated, and raw logs purged successfully.';

        return $this->sendSuccess($result, $message);
    }

    /**
     * POST /api/hrms/attendance/prune-logs
     * Purge raw location logs older than N hours for completed shifts.
     */
    public function pruneLocationLogs(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized. Only HR administrators can prune attendance logs.', 403);
        }

        $hours = $request->filled('hours') ? (int) $request->input('hours') : 24;
        if ($hours < 1) {
            $hours = 24;
        }

        $deletedCount = app(\App\Domains\HRMS\Services\AttendanceRouteService::class)->pruneOldLogs($hours);

        return $this->sendSuccess([
            'pruned_count'     => $deletedCount,
            'older_than_hours' => $hours,
        ], "Successfully pruned {$deletedCount} old raw location log entries.");
    }
}
