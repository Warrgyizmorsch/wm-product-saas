<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveEncashment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveRequestApiController extends Controller
{
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
     * Helper to check if current user is an HR admin.
     */
    private function isHrAdmin(): bool
    {
        return auth()->check() && auth()->user()->hasHrPermission('hr.settings.manage');
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

        $isAdmin = $this->isHrAdmin();
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $requestsQuery = LeaveRequest::query();
        if (!$isAdmin) {
            $requestsQuery->where('employee_id', $employee?->id ?? 0);
        }

        $totalRequests   = (clone $requestsQuery)->count();
        $pendingRequests = (clone $requestsQuery)->where('status', 'pending')->count();
        $approvedRequests= (clone $requestsQuery)->where('status', 'approved')->count();
        $rejectedRequests= (clone $requestsQuery)->where('status', 'rejected')->count();

        $balances = $employee ? LeaveBalance::where('employee_id', $employee->id)->with('leaveType')->get() : collect();

        return $this->sendSuccess([
            'is_admin'          => $isAdmin,
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

        $isAdmin = $this->isHrAdmin();
        $employee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $query = LeaveRequest::query()->with(['employee', 'leaveType', 'approvedByEmployee']);

        if ($isAdmin) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
        } else {
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->where('id', 0);
            }
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

        $leaveRequest = LeaveRequest::with(['employee', 'leaveType', 'approvedByEmployee'])->find($id);

        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

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

        $isAdmin = $this->isHrAdmin();

        $validated = $request->validate([
            'employee_id'       => $isAdmin ? 'required|exists:employees,id' : 'nullable',
            'leave_type_id'     => 'required|exists:leave_types,id',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'start_date_type'   => 'required|string|in:full_day,first_half,second_half',
            'end_date_type'     => 'required|string|in:full_day,first_half,second_half',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notified_contacts' => 'nullable|array',
            'notified_contacts.*' => 'exists:employees,id'
        ]);

        if ($isAdmin && !empty($validated['employee_id'])) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            $employee = Employee::where('personal_email', auth()->user()->email)
                ->orWhere('office_email', auth()->user()->email)
                ->first();
        }

        if (!$employee) {
            return $this->sendError(__('hrms.leave.app.emp_not_found'), 404);
        }

        $leaveType = LeaveType::find($validated['leave_type_id']);
        if (!$leaveType) {
            return $this->sendError('Specified leave type does not exist.', 404);
        }

        if ($leaveType->plan && !$leaveType->plan->status) {
            return $this->sendError(__('hrms.leave.app.plan_inactive'), 422);
        }

        $rules     = $leaveType->rules ?? [];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $startType = $validated['start_date_type'];
        $endType   = $validated['end_date_type'];

        $duration = 0;
        if ($startDate->equalTo($endDate)) {
            if ($startDate->dayOfWeek !== Carbon::SUNDAY) {
                $duration = ($startType === 'full_day') ? 1.0 : 0.5;
            }
        } else {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }
                if ($date->equalTo($startDate)) {
                    $duration += ($startType === 'full_day') ? 1.0 : 0.5;
                } elseif ($date->equalTo($endDate)) {
                    $duration += ($endType === 'full_day') ? 1.0 : 0.5;
                } else {
                    $duration += 1.0;
                }
            }
        }

        if ($duration == 0) {
            return $this->sendError(__('hrms.leave.app.duration_zero'), 422);
        }

        // Check overlapping requests
        $overlapExists = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate->format('Y-m-d'))
                  ->where('end_date', '>=', $startDate->format('Y-m-d'));
            })
            ->exists();

        if ($overlapExists) {
            return $this->sendError(__('hrms.leave.app.overlap_exists'), 422);
        }

        $appRules = $rules['application'] ?? [];

        // Probation Restriction
        $probationRules = $rules['probation'] ?? [];
        if (!empty($probationRules['probation_rule'])) {
            $probRule = $probationRules['probation_rule'];
            $doj = $employee->date_of_joining;
            if ($probRule === 'disallow' && $employee->employee_stage === 'Probation') {
                return $this->sendError(__('hrms.leave.app.probation_restricted'), 422);
            }
            if ($probRule === 'allow_after_months') {
                $requiredMonths = intval($probationRules['probation_months'] ?? 3);
                if ($doj && Carbon::parse($doj)->addMonths($requiredMonths)->isFuture()) {
                    return $this->sendError(__('hrms.leave.app.probation_months_restricted', ['months' => $requiredMonths]), 422);
                }
            }
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
        $minDuration = floatval($appRules['min_duration'] ?? 1);
        $maxDuration = floatval($appRules['max_duration'] ?? 10);
        if ($duration < $minDuration) {
            return $this->sendError(__('hrms.leave.app.min_duration_restricted', ['min' => $minDuration]), 422);
        }
        if ($duration > $maxDuration) {
            return $this->sendError(__('hrms.leave.app.max_duration_restricted', ['max' => $maxDuration]), 422);
        }

        // Attachment Requirement
        if (!empty($appRules['require_attachment'])) {
            $attachmentDays = intval($appRules['attachment_days'] ?? 3);
            if ($duration >= $attachmentDays && !$request->hasFile('attachment')) {
                return $this->sendError(__('hrms.leave.app.attachment_required', ['days' => $attachmentDays]), 422);
            }
        }

        // Leave Balance Availability
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            $remaining = $balance ? floatval($balance->remaining) : 0.0;
            if ($duration > $remaining) {
                return $this->sendError(__('hrms.leave.app.insufficient_balance', ['remaining' => $remaining, 'duration' => $duration]), 422);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        $approvalRules = $rules['approval'] ?? [];
        $status = 'pending';
        if (($approvalRules['workflow_level'] ?? '1_level') === 'auto') {
            $status = 'approved';
        }

        $leaveRequest = LeaveRequest::create([
            'tenant_id'         => auth()->user()->tenant_id,
            'company_id'        => $employee->company_id,
            'employee_id'       => $employee->id,
            'leave_type_id'     => $leaveType->id,
            'start_date'        => $startDate->format('Y-m-d'),
            'end_date'          => $endDate->format('Y-m-d'),
            'duration'          => $duration,
            'start_date_type'   => $startType,
            'end_date_type'     => $endType,
            'notified_contacts'=> $validated['notified_contacts'] ?? null,
            'reason'            => $validated['reason'],
            'status'            => $status,
            'current_level'     => $status === 'approved' ? 'approved' : '1',
            'attachment_path'   => $attachmentPath,
        ]);

        if ($status === 'approved' && $isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            if ($balance) {
                $balance->increment('used', $duration);
            }
        }

        return $this->sendSuccess($leaveRequest->load(['employee', 'leaveType']), $status === 'approved' ? __('hrms.leave.app.submitted_auto_approved') : __('hrms.leave.app.submitted_successfully'), 201);
    }

    /**
     * POST /api/hrms/leave-requests/{id}/approve
     * Approve leave request with multi-level workflow support.
     */
    public function approveRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can approve leave requests.', 403);
        }

        $leaveRequest = LeaveRequest::find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $rules = $leaveRequest->leaveType->rules ?? [];
        $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';

        if ($workflowLevel === '2_level' && $leaveRequest->current_level === '1') {
            $leaveRequest->update([
                'current_level' => '2'
            ]);
            return $this->sendSuccess($leaveRequest, __('hrms.leave.app.first_level_approved'));
        }

        $leaveRequest->update([
            'status'        => 'approved',
            'current_level' => 'approved',
            'approved_by'   => $adminEmployee ? $adminEmployee->id : null
        ]);

        $leaveType = $leaveRequest->leaveType;
        $isPaid = strtolower($leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        if ($isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveType->id)
                ->first();
            if ($balance) {
                $balance->increment('used', floatval($leaveRequest->duration));
            }
        }

        return $this->sendSuccess($leaveRequest, __('hrms.leave.app.approved_successfully'));
    }

    /**
     * POST /api/hrms/leave-requests/{id}/reject
     * Reject leave request with mandatory rejection reason.
     */
    public function rejectRequest(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can reject leave requests.', 403);
        }

        $leaveRequest = LeaveRequest::find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $leaveRequest->update([
            'status'           => 'rejected',
            'current_level'    => 'rejected',
            'rejection_reason' => $validated['rejection_reason']
        ]);

        return $this->sendSuccess($leaveRequest, __('hrms.leave.app.rejected_successfully'));
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
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized access. Only HR administrators can update leave request statuses.', 403);
        }

        $leaveRequest = LeaveRequest::find($id);
        if (!$leaveRequest) {
            return $this->sendError("Leave request with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'status'           => 'required|string|in:approved,rejected,unauthorized,unpaid',
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $status        = $validated['status'];
        $adminEmployee = Employee::where('personal_email', auth()->user()->email)
            ->orWhere('office_email', auth()->user()->email)
            ->first();

        $oldStatus = $leaveRequest->status;
        $rules     = $leaveRequest->leaveType->rules ?? [];
        $isPaid    = strtolower($leaveRequest->leaveType->type) === 'paid';
        $isLimited = empty($rules['accrual']['quota_type']) || $rules['accrual']['quota_type'] !== 'unlimited';

        // Restore balance if previously approved
        if ($oldStatus === 'approved' && $isPaid && $isLimited) {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();
            if ($balance) {
                $balance->decrement('used', floatval($leaveRequest->duration));
            }
        }

        if ($status === 'approved') {
            $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';
            if ($workflowLevel === '2_level' && $leaveRequest->current_level === '1') {
                $leaveRequest->update(['current_level' => '2']);
                return $this->sendSuccess($leaveRequest, __('hrms.leave.app.first_level_approved'));
            }

            $leaveRequest->update([
                'status'           => 'approved',
                'current_level'    => 'approved',
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null
            ]);

            if ($isPaid && $isLimited) {
                $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->first();
                if ($balance) {
                    $balance->increment('used', floatval($leaveRequest->duration));
                }
            }
        } elseif ($status === 'rejected') {
            $leaveRequest->update([
                'status'           => 'rejected',
                'current_level'    => 'rejected',
                'rejection_reason' => $validated['rejection_reason'] ?? 'Rejected by Admin'
            ]);
        } else {
            $leaveRequest->update([
                'status'           => $status,
                'current_level'    => $status,
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null
            ]);
        }

        return $this->sendSuccess($leaveRequest, __('hrms.leave.app.status_updated_successfully', ['status' => $status]));
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

        $employeeId = $request->integer('employee_id') ?: null;
        if (!$employeeId) {
            $employee = Employee::where('personal_email', auth()->user()->email)
                ->orWhere('office_email', auth()->user()->email)
                ->first();
            $employeeId = $employee?->id;
        }

        if (!$employeeId && $this->isHrAdmin()) {
            // Fallback for admin user: pick the first active employee
            $firstEmp = Employee::where('status', true)->first() ?: Employee::first();
            $employeeId = $firstEmp?->id;
        }

        if (!$employeeId) {
            return $this->sendError('Employee profile not found. Please pass ?employee_id={id} query parameter.', 404);
        }

        $balances = LeaveBalance::where('employee_id', $employeeId)->with(['leaveType.plan', 'employee'])->get();

        return $this->sendSuccess($balances, 'Leave balances retrieved successfully');
    }
}
