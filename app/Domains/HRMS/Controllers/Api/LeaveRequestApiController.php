<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveRequestApiController extends Controller
{
    public function __construct(
        protected LeaveRequestRepositoryInterface $leaveRequestRepository
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
     * Helper to check if current user is HR Admin / Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return (bool) (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.leave_requests.approve')
        );
    }

    /**
     * Helper to resolve current authenticated user's employee record.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first()
            ?? Employee::where(function ($q) use ($user) {
                $q->where('office_email', $user->email)
                  ->orWhere('personal_email', $user->email);
            })->first();
    }

    /**
     * Null-safe authorization check supporting Web Sessions & HTTP Basic Auth.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        return null;
    }

    /**
     * GET /api/hrms/leave-requests/summary
     * Get summary metrics, pending requests, and leave balance totals.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $requestsQuery = LeaveRequest::query();
        if (!$isHrAdmin) {
            $requestsQuery->where('employee_id', $employee?->id ?? 0);
        }

        $totalRequests   = (clone $requestsQuery)->count();
        $pendingRequests = (clone $requestsQuery)->where('status', 'pending')->count();
        $approvedRequests= (clone $requestsQuery)->where('status', 'approved')->count();
        $rejectedRequests= (clone $requestsQuery)->where('status', 'rejected')->count();

        $balances = $employee ? LeaveBalance::where('employee_id', $employee->id)->with('leaveType')->get() : collect();

        return $this->sendSuccess([
            'total_requests'    => $totalRequests,
            'pending_requests'  => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
            'my_balances'       => $balances,
        ], 'Leave requests summary loaded successfully');
    }

    /**
     * GET /api/hrms/leave-requests
     * Get paginated/filtered leave requests.
     */
    public function indexRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        
        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $query = LeaveRequest::query()->with(['employee', 'leaveType', 'approvedByEmployee']);

        if (!$isHrAdmin) {
            $query->where('employee_id', $employee?->id ?? 0);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10));

        $requests->getCollection()->transform(function ($req) use ($employee, $isHrAdmin) {
            $isOwner = $employee && (int)$req->employee_id === (int)$employee->id;
            $isPending = $req->status === 'pending';
            $req->capabilities = [
                'can_view'     => true,
                'can_approve'  => $isHrAdmin && $isPending,
                'can_reject'   => $isHrAdmin && $isPending,
                'can_cancel'   => ($isOwner || $isHrAdmin) && $isPending,
                'can_withdraw' => $isOwner && $isPending,
            ];
            return $req;
        });

        return $this->sendSuccess($requests, 'Leave requests retrieved successfully');
    }

    /**
     * GET /api/hrms/leave-requests/{id}
     * Get details of a single leave request.
     */
    public function showRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $leaveRequest = LeaveRequest::with(['employee', 'leaveType', 'approvedByEmployee'])
            ->where('tenant_id', tenant_id())
            ->find($id);

        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $leaveRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized access to leave request.', 403);
        }

        $isOwner = $employee && (int)$leaveRequest->employee_id === (int)$employee->id;
        $isPending = $leaveRequest->status === 'pending';
        $leaveRequest->capabilities = [
            'can_view'     => true,
            'can_approve'  => $isHrAdmin && $isPending,
            'can_reject'   => $isHrAdmin && $isPending,
            'can_cancel'   => ($isOwner || $isHrAdmin) && $isPending,
            'can_withdraw' => $isOwner && $isPending,
        ];

        return $this->sendSuccess($leaveRequest, 'Leave request details loaded');
    }

    /**
     * POST /api/hrms/leave-requests
     * Submit a new leave request with comprehensive validation rules.
     */
    public function storeRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if ($request->filled('start_date') && !$request->filled('end_date')) {
            $request->merge(['end_date' => $request->input('start_date')]);
        }

        $validated = $request->validate([
            'employee_id'       => 'nullable|integer|exists:employees,id',
            'leave_type_id'     => 'required|exists:leave_types,id',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'start_date_type'   => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'end_date_type'     => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'hours'             => 'nullable|numeric|min:0.5|max:24',
            'start_time'        => 'nullable|date_format:H:i',
            'end_time'          => 'nullable|date_format:H:i',
            'session'           => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'notified_contacts' => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id'
        ]);

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError(__('hrms.leave.app.emp_not_found'), 404);
            }
            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $employee->id) {
                return $this->sendError('You can only submit a leave request for your own employee profile.', 403);
            }
            $validated['employee_id'] = $employee->id;
        } else {
            if (empty($validated['employee_id'])) {
                return $this->sendError('employee_id is required for HR administrators.', 422);
            }
            $employee = Employee::find($validated['employee_id']);
        }

        if (!$employee) {
            return $this->sendError(__('hrms.leave.app.emp_not_found'), 404);
        }

        $leaveType = LeaveType::find($validated['leave_type_id']);
        if (!$leaveType) {
            return $this->sendError('Specified leave type does not exist.', 404);
        }

        if ($leaveType->leave_plan_id && (int) $employee->leave_plan_id !== (int) $leaveType->leave_plan_id) {
            return $this->sendError(__('hrms.leave.app.invalid_leave_type_for_employee'), 422);
        }

        if ($leaveType->plan && !$leaveType->plan->status) {
            return $this->sendError(__('hrms.leave.app.plan_inactive'), 422);
        }

        $rules        = $leaveType->rules ?? [];
        $appRules     = $rules['application'] ?? [];
        $sandwichRule = !empty($appRules['sandwich_rule']);
        $startDate    = Carbon::parse($validated['start_date']);
        $endDate      = Carbon::parse($validated['end_date']);
        $isHourly     = !empty($rules['accrual']['calculate_in']) && $rules['accrual']['calculate_in'] === 'hours';
        $startType    = $validated['start_date_type'] ?? ($isHourly ? 'hourly' : 'full_day');
        $endType      = $validated['end_date_type'] ?? ($isHourly ? 'hourly' : 'full_day');

        $duration = 0.0;
        if ($isHourly) {
            $validated['end_date'] = $validated['start_date'];
            $endDate = $startDate->copy();

            if (!empty($validated['start_time']) && !empty($validated['end_time'])) {
                if (Carbon::parse($validated['end_time'])->lte(Carbon::parse($validated['start_time']))) {
                    return $this->sendError('The To Time must be after the From Time.', 422);
                }
            }

            $duration = round(floatval($request->input('hours', 1.0)) * 2) / 2;
            if ($duration < 0.5) {
                return $this->sendError('Duration cannot be less than 0.5 hours.', 422);
            }
            $startType = 'hourly';
            $endType = 'hourly';
            $validated['start_date_type'] = 'hourly';
            $validated['end_date_type'] = 'hourly';
            $validated['session'] = 'hourly';
        } else {
            // Clear hourly time fields for full-day / multi-day leaves to prevent data pollution
            $validated['start_time'] = null;
            $validated['end_time']   = null;
            $validated['hours']      = null;
            $daysInRange = [];
            $firstWorkDay = null;
            $lastWorkDay = null;

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
                $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));
                $isWorkDay = !$isHoliday && $isActiveWorkDay;

                $daysInRange[] = [
                    'date' => $date->copy(),
                    'isWorkDay' => $isWorkDay,
                ];

                if ($isWorkDay) {
                    if ($firstWorkDay === null) {
                        $firstWorkDay = $date->copy();
                    }
                    $lastWorkDay = $date->copy();
                }
            }

            foreach ($daysInRange as $d) {
                $currDate = $d['date'];
                if ($d['isWorkDay']) {
                    if ($startDate->isSameDay($endDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($currDate->isSameDay($startDate)) {
                        $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                    } elseif ($currDate->isSameDay($endDate)) {
                        $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        $duration += 1.0;
                    }
                } else {
                    if ($sandwichRule && $firstWorkDay && $lastWorkDay && $currDate->gt($firstWorkDay) && $currDate->lt($lastWorkDay)) {
                        $duration += 1.0;
                    }
                }
            }

            if ($duration < 0.5) {
                return $this->sendError('Duration cannot be less than 0.5 days. Ensure you are not applying for leave entirely on weekends or holidays.', 422);
            }
        }

        $conflict = \App\Domains\HRMS\Helpers\SessionConflictChecker::hasConflict(
            employeeId:   $employee->id,
            newStart:     $startDate,
            newEnd:       $endDate,
            newStartType: $startType,
            newEndType:   $endType
        );

        if ($conflict) {
            return $this->sendError($conflict, 422);
        }

        // Probation Restriction
        $probationRules = $rules['probation'] ?? [];
        $probRule = $probationRules['rule'] ?? ($probationRules['probation_rule'] ?? 'allow');
        $doj = $employee->date_of_joining;
        if ($probRule === 'disallow' && ($employee->employee_stage === 'Probation' || $employee->employment_status === 'probation')) {
            return $this->sendError(__('hrms.leave.app.probation_restricted'), 422);
        }
        if ($probRule === 'allow_after_months') {
            $requiredMonths = intval($probationRules['months'] ?? ($probationRules['probation_months'] ?? 3));
            if ($doj && Carbon::parse($doj)->addMonths($requiredMonths)->isFuture()) {
                return $this->sendError(__('hrms.leave.app.probation_months_restricted', ['months' => $requiredMonths]), 422);
            }
        }

        // Notice Period Restriction
        $noticeRules = $rules['notice'] ?? [];
        $noticeRule = $noticeRules['rule'] ?? ($noticeRules['notice_rule'] ?? 'allow');
        if ($noticeRule === 'disallow' && ($employee->employee_stage === 'Notice Period' || $employee->employment_status === 'notice')) {
            return $this->sendError(__('hrms.leave.app.notice_restricted'), 422);
        }

        // Apply in Advance Rule
        if (!empty($appRules['apply_in_advance'])) {
            $advanceDays = intval($appRules['advance_days'] ?? 3);
            $minAllowedDate = Carbon::today()->addDays($advanceDays);
            if ($startDate->lt($minAllowedDate)) {
                return $this->sendError(__('hrms.leave.app.advance_restricted', ['days' => $advanceDays, 'date' => $minAllowedDate->format('Y-m-d')]), 422);
            }
        }

        // Duration Limits
        $minDuration = floatval($appRules['min_duration'] ?? ($isHourly ? 0.5 : 0.5));
        $maxDuration = floatval($appRules['max_duration'] ?? ($isHourly ? 24.0 : 365.0));
        $unitLabel   = $isHourly ? 'hour(s)' : 'day(s)';
        if ($minDuration > 0 && $duration < $minDuration) {
            return $this->sendError("The requested duration ({$duration} {$unitLabel}) is less than the minimum required ({$minDuration} {$unitLabel}).", 422);
        }
        if ($maxDuration > 0 && $duration > $maxDuration) {
            return $this->sendError("The requested duration ({$duration} {$unitLabel}) exceeds the maximum allowed ({$maxDuration} {$unitLabel}).", 422);
        }

        // Attachment Requirement (for day-based leave applications)
        if (!$isHourly && !empty($appRules['require_attachment'])) {
            $attachmentDays = intval($appRules['attachment_days'] ?? 3);
            if ($duration >= $attachmentDays && !$request->hasFile('attachment')) {
                return $this->sendError(__('hrms.leave.app.attachment_required', ['days' => $attachmentDays]), 422);
            }
        }

        // Leave Balance Availability Check (respecting Negative Leave balance policy)
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();

            if (!$balance) {
                $quota = floatval($rules['accrual']['quota_value'] ?? ($leaveType->quota ?? 0.0));
                $balance = LeaveBalance::firstOrCreate([
                    'tenant_id'     => $employee->tenant_id,
                    'company_id'    => $employee->company_id,
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $leaveType->id,
                ], [
                    'allocated' => $quota,
                    'used'      => 0.0,
                    'encashed'  => 0.0,
                ]);
            }

            $remaining = $balance ? floatval($balance->remaining) : 0.0;
            $allowNegative = !empty($rules['accrual']['allow_negative']);
            $maxNegative = floatval($rules['accrual']['max_negative'] ?? 0.0);

            if ($allowNegative) {
                $maxAllowedDuration = $remaining + $maxNegative;
                if ($duration > $maxAllowedDuration) {
                    return $this->sendError(__('hrms.leave.app.insufficient_balance_negative', [
                        'remaining'    => $remaining,
                        'max_negative' => $maxNegative,
                        'allowed'      => max(0.0, $maxAllowedDuration),
                        'duration'     => $duration
                    ]), 422);
                }
            } else {
                if ($duration > $remaining) {
                    return $this->sendError(__('hrms.leave.app.insufficient_balance', ['remaining' => $remaining, 'duration' => $duration]), 422);
                }
            }
        }

        $validated['company_id'] = $employee->company_id;
        $validated['duration'] = $duration;
        $validated['start_date'] = $startDate->format('Y-m-d');
        $validated['end_date'] = $endDate->format('Y-m-d');
        $validated['start_date_type'] = $startType;
        $validated['end_date_type'] = $endType;
        try {
            $leaveRequest = $this->leaveRequestRepository->storeLeaveRequest($validated, $request);

            $unitDisplay = $isHourly ? 'hour(s)' : 'day(s)';
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New Leave Request',
                message: "{$employee->full_name} applied for {$duration} {$unitDisplay} leave ({$startDate->format('M d')} - {$endDate->format('M d')}).",
                actionUrl: route('hrms.leaves.index'),
                type: 'leave_request',
                iconClass: 'feather-calendar'
            );
            if ($employee->reportingManager) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $employee->reportingManager,
                    title: 'New Leave Request Applied',
                    message: "{$employee->full_name} applied for {$duration} {$unitDisplay} leave.",
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_request',
                    iconClass: 'feather-calendar'
                );
            }
            if (!empty($validated['notified_contacts'])) {
                $contacts = Employee::whereIn('id', $validated['notified_contacts'])->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague Leave Notice',
                        message: "{$employee->full_name} will be on leave ({$startDate->format('M d')} - {$endDate->format('M d')}).",
                        actionUrl: route('hrms.leaves.index'),
                        type: 'leave_request',
                        iconClass: 'feather-calendar'
                    );
                }
            }
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->sendError($exception->getMessage(), 422, $exception->errors());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('API Leave notification dispatch failed: ' . $e->getMessage());
        }
        $status = $leaveRequest->status;

        return $this->sendSuccess($leaveRequest->load(['employee', 'leaveType']), $status === 'approved' ? __('hrms.leave.app.submitted_auto_approved') : __('hrms.leave.app.submitted_successfully'), 201);
    }

    /**
     * POST /api/hrms/leave-requests/{id}/approve
     * Approve leave request with multi-level workflow support.
     */
    public function approveRequest(mixed $id): JsonResponse
    {
        return $this->updateStatus(request()->merge(['status' => 'approved']), $id);
    }
    /**
     * POST /api/hrms/leave-requests/{id}/reject
     * Reject leave request with mandatory rejection reason.
     */
    public function rejectRequest(Request $request, mixed $id): JsonResponse
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        return $this->updateStatus($request->merge(['status' => 'rejected']), $id);
    }
    /**
     * PUT /api/hrms/leave-requests/{id}/status
     * Explicitly update leave request status (approved, rejected, unauthorized, unpaid) with balance sync.
     */
    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $leaveRequest = LeaveRequest::where('tenant_id', tenant_id())->find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if (!$this->canApproveLeaveRequest(auth()->user(), $leaveRequest)) {
            return $this->sendError('Unauthorized action. You do not have permission to approve or reject this leave request at its current stage.', 403);
        }

        if ($leaveRequest->status === 'cancelled') {
            return $this->sendError('Cannot change the status of a cancelled leave application.', 422);
        }

        $validated = $request->validate([
            'status'           => 'required|string|in:approved,rejected,unauthorized,unpaid',
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $status = $validated['status'];

        try {
            $this->leaveRequestRepository->updateStatus($leaveRequest, [
                'action' => $status,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ], $request);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->sendError($exception->getMessage(), 422, $exception->errors());
        }
        $leaveRequest->refresh();

        try {
            $startFormatted = \Carbon\Carbon::parse($leaveRequest->start_date)->format('d M Y');
            $endFormatted = \Carbon\Carbon::parse($leaveRequest->end_date)->format('d M Y');
            if ($leaveRequest->employee) {
                $statusText = ucfirst($status);
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $leaveRequest->employee,
                    title: "Leave Request {$statusText}",
                    message: "Your leave application ({$startFormatted} to {$endFormatted}) status is now {$statusText}." . ($status === 'rejected' && !empty($validated['rejection_reason']) ? " Reason: {$validated['rejection_reason']}" : ''),
                    actionUrl: route('hrms.leaves.index'),
                    type: 'leave_' . $status,
                    iconClass: $status === 'approved' ? 'feather-check-circle' : 'feather-x-circle'
                );
            }
            if ($status === 'approved' && !empty($leaveRequest->notified_contacts)) {
                $contacts = Employee::whereIn('id', $leaveRequest->notified_contacts)->get();
                foreach ($contacts as $contact) {
                    \App\Services\Notification\NotificationService::sendToEmployee(
                        employee: $contact,
                        title: 'Colleague Leave Approved',
                        message: "{$leaveRequest->employee?->full_name}'s leave ({$startFormatted} - {$endFormatted}) has been approved.",
                        actionUrl: route('hrms.leaves.index'),
                        type: 'leave_approved',
                        iconClass: 'feather-calendar'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('API Leave status notification failed: ' . $e->getMessage());
        }

        $message = $status === 'approved' && $leaveRequest->status === 'pending'
            ? __('hrms.leave.app.first_level_approved')
            : __('hrms.leave.app.status_updated_successfully', ['status' => $status]);
        return $this->sendSuccess($leaveRequest, $message);
    }

    /**
     * GET /api/hrms/leave-requests/balances
     * Get leave balances for specified employee or logged in user.
     */
    public function balances(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $employeeId = $request->integer('employee_id') ?: null;
        if (!$isHrAdmin || !$employeeId) {
            $employeeId = $employee?->id;
        }

        if (!$employeeId) {
            return $this->sendError('Employee profile not found.', 404);
        }

        $balances = LeaveBalance::where('employee_id', $employeeId)->with(['leaveType.plan', 'employee'])->get();

        return $this->sendSuccess($balances, 'Leave balances retrieved successfully');
    }

    /**
     * POST /api/hrms/leave-requests/calculate-duration
     * Calculate leave duration excluding holidays and rest days.
     */
    public function calculateDuration(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'     => 'nullable|integer|exists:employees,id',
            'leave_type_id'   => 'nullable|integer',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'start_date_type' => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'end_date_type'   => 'nullable|string|in:full_day,first_half,second_half,hourly',
            'hours'           => 'nullable|numeric|min:0.5|max:24',
        ]);

        $isHrAdmin = $this->isHrAdmin();
        $authEmployee = $this->getAuthenticatedEmployee();
        if (!$isHrAdmin) {
            if (!$authEmployee) {
                return $this->sendError(__('hrms.leave.app.emp_not_found'), 404);
            }
            if (!empty($validated['employee_id']) && (int) $validated['employee_id'] !== (int) $authEmployee->id) {
                return $this->sendError('You can only calculate leave duration for your own employee profile.', 403);
            }
            $validated['employee_id'] = $authEmployee->id;
        } elseif (empty($validated['employee_id'])) {
            return $this->sendError('employee_id is required for HR administrators.', 422);
        }

        $employee = Employee::find($validated['employee_id']);
        if (!$employee) {
            return $this->sendError(__('hrms.leave.app.emp_not_found'), 404);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'] ?? 'full_day';
        $endType   = $validated['end_date_type'] ?? 'full_day';

        $leaveType = !empty($validated['leave_type_id']) ? LeaveType::find($validated['leave_type_id']) : null;
        $isHourly  = $leaveType && (!empty($leaveType->rules['accrual']['calculate_in']) && $leaveType->rules['accrual']['calculate_in'] === 'hours');

        if ($isHourly) {
            $hours = round(floatval($request->input('hours', 1.0)) * 2) / 2;
            return $this->sendSuccess([
                'duration'      => $hours,
                'unit'          => 'hours',
                'holidays'      => [],
                'rest_days'     => [],
                'sandwich_rule' => false,
                'sandwich_days' => [],
            ], 'Leave duration calculated successfully');
        }

        $sandwichRule = $leaveType && !empty($leaveType->rules['application']['sandwich_rule']);

        $duration     = 0.0;
        $holidays     = [];
        $restDays     = [];
        $sandwichDays = [];
        $daysInRange  = [];
        $firstWorkDay = null;
        $lastWorkDay  = null;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            $isHoliday = \App\Domains\HRMS\Models\HolidayCalendar::isHolidayForEmployee($employee, $date);
            $isActiveWorkDay = !is_null($employee->resolveShiftForDate($date));
            $isWorkDay = !$isHoliday && $isActiveWorkDay;

            if ($isHoliday) {
                $holidays[] = $dateStr;
            }
            if (!$isActiveWorkDay) {
                $restDays[] = $dateStr;
            }

            $daysInRange[] = [
                'date'      => $date->copy(),
                'dateStr'   => $dateStr,
                'isWorkDay' => $isWorkDay,
            ];

            if ($isWorkDay) {
                if ($firstWorkDay === null) {
                    $firstWorkDay = $date->copy();
                }
                $lastWorkDay = $date->copy();
            }
        }

        foreach ($daysInRange as $d) {
            $currDate = $d['date'];
            $dateStr  = $d['dateStr'];

            if ($d['isWorkDay']) {
                if ($startDate->isSameDay($endDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($currDate->isSameDay($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($currDate->isSameDay($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                }
            } else {
                if ($sandwichRule && $firstWorkDay && $lastWorkDay && $currDate->gt($firstWorkDay) && $currDate->lt($lastWorkDay)) {
                    $duration += 1.0;
                    $sandwichDays[] = $dateStr;
                }
            }
        }

        return $this->sendSuccess([
            'duration'      => $duration,
            'unit'          => 'days',
            'holidays'      => $holidays,
            'rest_days'     => $restDays,
            'sandwich_rule' => $sandwichRule,
            'sandwich_days' => $sandwichDays,
        ], 'Leave duration calculated successfully');
    }

    public function getRules(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employeeId  = $request->integer('employee_id');
        $leaveTypeId = $request->integer('leave_type_id');

        $repo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
        $rules = $repo->getPolicyRules($employeeId, $leaveTypeId);

        return $this->sendSuccess($rules, 'Leave policy rules retrieved successfully');
    }

    public function withdraw(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $leaveRequest = LeaveRequest::where('tenant_id', tenant_id())->find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $leaveRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$leaveRequest->canWithdraw()) {
            return $this->sendError('Only pending applications can be withdrawn.', 422);
        }

        $leaveRequest->delete();

        return $this->sendSuccess(['id' => (int)$id], 'Leave application withdrawn successfully.');
    }

    public function requestCancellation(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $leaveRequest = LeaveRequest::where('tenant_id', tenant_id())->find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $leaveRequest->employee_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        if (!$leaveRequest->canRequestCancellation()) {
            return $this->sendError('Only approved applications can have a cancellation requested.', 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $leaveRequest->update([
            'status'              => 'cancellation_requested',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return $this->sendSuccess($leaveRequest, 'Cancellation request submitted. Awaiting admin approval.');
    }

    public function approveCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can approve cancellations.', 403);
        }

        $leaveRequest = LeaveRequest::where('tenant_id', tenant_id())->find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if ($leaveRequest->status !== 'cancellation_requested') {
            return $this->sendError('This application does not have a pending cancellation request.', 422);
        }

        $repo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
        $repo->cancelLeaveRequest($leaveRequest);

        return $this->sendSuccess($leaveRequest, 'Leave cancellation approved. Balance has been restored.');
    }

    public function denyCancellation(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can deny cancellations.', 403);
        }

        $leaveRequest = LeaveRequest::where('tenant_id', tenant_id())->find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        if ($leaveRequest->status !== 'cancellation_requested') {
            return $this->sendError('This application does not have a pending cancellation request.', 422);
        }

        $leaveRequest->update([
            'status'              => 'approved',
            'cancellation_reason' => null,
        ]);

        return $this->sendSuccess($leaveRequest, 'Cancellation request denied. Application remains approved.');
    }

    private function canApproveLeaveRequest(?\App\Models\User $user, LeaveRequest $leaveRequest): bool
    {
        if (!$user || !$leaveRequest->employee) {
            return false;
        }

        return app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class)->canApprove($user, $leaveRequest->employee, $leaveRequest);
    }

    /**
     * Export Leave Applications to Excel (.xlsx).
     */
    public function export(Request $request): mixed
    {
        return $this->leaveRequestRepository->export($request->all());
    }
}


