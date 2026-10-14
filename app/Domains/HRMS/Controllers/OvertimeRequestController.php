<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly OvertimeRequestRepositoryInterface $overtimeRepository
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('hrms.shift-overtime.index', array_merge(['tab' => 'overtime'], $request->all()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'date'              => 'required|date',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i',
            'compensation_type' => 'required|string|in:payout,comp_off',
            'reason'            => 'required|string|max:1000',
            'attachment'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $currentEmp = Employee::resolveForUser($user);
            if ($currentEmp) {
                $validated['employee_id'] = $currentEmp->id;
            }
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        // Check if employee's shift on this date permits overtime
        $shift = $employee->resolveShiftForDate($validated['date']);
        if ($shift && !$shift->overtime_allowed && !$isHrAdmin) {
            return redirect()->back()->withInput()->with('error', __('hrms.overtime.shift_not_allowed', ['shift' => $shift->name]));
        }

        // Check if an active/pending overtime request already exists for this employee on this date
        $exists = OvertimeRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
            ->exists();
        if ($exists) {
            return redirect()->back()->withInput()->with('error', __('hrms.overtime.request_exists'));
        }

        // Calculate hours
        $startTime = Carbon::parse($validated['start_time']);
        $endTime   = Carbon::parse($validated['end_time']);
        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }
        $durationHours = $startTime->diffInMinutes($endTime) / 60.0;

        // Fetch overtime policy rules (minimum request hours and monthly maximum cap)
        $minHours = 0.5;
        $maxMonthlyHours = null;

        $otPenaltyRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
            ->where(function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)->orWhereNull('company_id');
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        if ($otPenaltyRule && is_array($otPenaltyRule->penalty_tiers)) {
            if (isset($otPenaltyRule->penalty_tiers['min_overtime_request_hours']) && $otPenaltyRule->penalty_tiers['min_overtime_request_hours'] !== '' && $otPenaltyRule->penalty_tiers['min_overtime_request_hours'] !== null) {
                $minHours = (float) $otPenaltyRule->penalty_tiers['min_overtime_request_hours'];
            }
            if (isset($otPenaltyRule->penalty_tiers['overtime_max_monthly_hours']) && $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'] !== '' && $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'] !== null) {
                $maxMonthlyHours = (float) $otPenaltyRule->penalty_tiers['overtime_max_monthly_hours'];
            }
        } else {
            $tenantId = $employee->tenant_id ?? $user?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null);
            $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
            if ($tenant && is_array($tenant->settings)) {
                if (isset($tenant->settings['min_overtime_request_hours']) && $tenant->settings['min_overtime_request_hours'] !== '' && $tenant->settings['min_overtime_request_hours'] !== null) {
                    $minHours = (float) $tenant->settings['min_overtime_request_hours'];
                }
                if (isset($tenant->settings['overtime_max_monthly_hours']) && $tenant->settings['overtime_max_monthly_hours'] !== '' && $tenant->settings['overtime_max_monthly_hours'] !== null) {
                    $maxMonthlyHours = (float) $tenant->settings['overtime_max_monthly_hours'];
                }
            }
        }

        if ($durationHours < $minHours) {
            return redirect()->back()->withInput()->with('error', __('hrms.overtime.duration_error', ['min' => $minHours]));
        }

        if ($maxMonthlyHours !== null && $maxMonthlyHours > 0) {
            $reqDate = Carbon::parse($validated['date']);
            $existingMonthHours = (float) OvertimeRequest::where('employee_id', $employee->id)
                ->whereBetween('date', [$reqDate->copy()->startOfMonth()->toDateString(), $reqDate->copy()->endOfMonth()->toDateString()])
                ->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
                ->sum('duration_hours');

            if (($existingMonthHours + $durationHours) > $maxMonthlyHours) {
                $remainingHours = max(0.0, $maxMonthlyHours - $existingMonthHours);
                return redirect()->back()->withInput()->with('error', __('hrms.overtime.monthly_limit_exceeded', ['max' => $maxMonthlyHours, 'logged' => $existingMonthHours, 'remaining' => $remainingHours]));
            }
        }

        $validated['duration_hours']          = $durationHours;
        $validated['approved_duration_hours'] = $durationHours;
        $validated['employee_id']             = $employee->id;
        $validated['company_id']              = $employee->company_id ?? ($user?->company_id ?? 1);

        DB::transaction(function () use ($validated, $request) {
            return $this->overtimeRepository->storeOvertimeRequest($validated, $request);
        });

        // Safely dispatch notifications to HR Admins & Reporting Manager
        try {
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'New Overtime Request',
                message: "{$employee->full_name} submitted an overtime request for {$durationHours} hrs.",
                actionUrl: $actionUrl,
                type: 'overtime_request',
                iconClass: 'feather-clock'
            );

            if ($employee->reportingManager && $employee->reportingManager->user_id) {
                \App\Services\Notification\NotificationService::sendToUser(
                    userId: $employee->reportingManager->user_id,
                    title: 'New Overtime Request',
                    message: "{$employee->full_name} submitted an overtime request for {$durationHours} hrs.",
                    actionUrl: $actionUrl,
                    type: 'overtime_request',
                    iconClass: 'feather-clock'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Overtime request notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.overtime.submitted_successfully'));
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $overtimeRequest, 'approved');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        return $this->updateStatus($request, $overtimeRequest, 'rejected');
    }

    public function updateStatus(Request $request, OvertimeRequest $overtimeRequest, ?string $overrideAction = null): RedirectResponse
    {
        $user = $request->user();
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $actorEmpId = $workflowService->getEmployeeIdForActor($user);
        if ($actorEmpId && (int) $actorEmpId === (int) $overtimeRequest->employee_id) {
            abort(403, 'Self-approval is prohibited. You cannot approve your own Overtime request.');
        }

        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        if ($overtimeRequest->status === 'cancelled') {
            return redirect()->back()->with('error', __('hrms.overtime.cancelled_status_error'));
        }

        $action = $overrideAction ?? $request->input('action');
        $approvedHours = $request->input('approved_duration_hours');

        if (!$action) {
            $validated = $request->validate([
                'action'                  => 'required|in:approved,rejected,pending',
                'rejection_reason'        => 'nullable|string|max:1000',
                'approved_duration_hours' => 'nullable|numeric|min:0.5',
            ]);
            $action        = $validated['action'];
            $reason        = $validated['rejection_reason'] ?? null;
            $approvedHours = $validated['approved_duration_hours'] ?? null;
        } else {
            $reason = $request->input('rejection_reason');
        }

        DB::transaction(function () use ($overtimeRequest, $action, $reason, $approvedHours, $request) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            $this->overtimeRepository->updateStatus($locked, [
                'action'                  => $action,
                'rejection_reason'        => $reason,
                'approved_duration_hours' => $approvedHours,
            ], $request);
        });

        // Safely notify Employee
        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Request ' . ucfirst($action),
                    message: "Your overtime request for {$overtimeRequest->date} has been {$action}.",
                    actionUrl: $actionUrl,
                    type: 'overtime_' . $action,
                    iconClass: $action === 'approved' ? 'feather-check-circle' : 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Overtime status notification dispatch failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.overtime.status_updated'));
    }

    public function withdraw(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $overtimeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$overtimeRequest->canWithdraw()) {
            return redirect()->back()->with('error', __('hrms.overtime.withdraw_only_pending'));
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canWithdraw()) {
                throw new \RuntimeException(__('hrms.overtime.withdraw_only_pending'));
            }
            $locked->delete();
        });

        return redirect()->back()->with('success', __('hrms.overtime.withdraw_success'));
    }

    public function requestCancellation(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $overtimeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        if (!$overtimeRequest->canRequestCancellation()) {
            return redirect()->back()->with('error', __('hrms.overtime.only_approved_can_cancel'));
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($overtimeRequest, $validated) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canRequestCancellation()) {
                throw new \RuntimeException(__('hrms.overtime.only_approved_can_cancel'));
            }

            $updateData = ['status' => 'cancellation_requested'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('overtime_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = $validated['cancellation_reason'];
            } else {
                $updateData['rejection_reason'] = $validated['cancellation_reason'];
            }

            $locked->update($updateData);
        });

        // Safely notify HR Admins
        try {
            $empName = $overtimeRequest->employee ? $overtimeRequest->employee->full_name : 'An employee';
            $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                title: 'Overtime Cancellation Requested',
                message: "{$empName} requested cancellation for an overtime request on {$overtimeRequest->date}.",
                actionUrl: $actionUrl,
                type: 'overtime_cancellation_requested',
                iconClass: 'feather-alert-circle'
            );
        } catch (\Throwable $e) {
            Log::warning('Overtime cancellation notification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.overtime.cancellation_submitted'));
    }

    public function approveCancellation(OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'cancellation_requested') {
                throw new \RuntimeException(__('hrms.overtime.no_pending_cancellation'));
            }

            $this->overtimeRepository->revertCompOffLeave($locked);
            $locked->update(['status' => 'cancelled']);
        });

        // Safely notify Employee
        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Cancellation Approved',
                    message: "Your overtime cancellation request for {$overtimeRequest->date} has been approved.",
                    actionUrl: $actionUrl,
                    type: 'overtime_cancellation_approved',
                    iconClass: 'feather-check-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Overtime cancellation approval notification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.overtime.cancellation_approved'));
    }

    public function denyCancellation(OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        abort_unless($isHrAdmin, 403);

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'cancellation_requested') {
                throw new \RuntimeException(__('hrms.overtime.no_pending_cancellation'));
            }

            $updateData = ['status' => 'approved'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('overtime_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = null;
            }

            $locked->update($updateData);
        });

        // Safely notify Employee
        try {
            if ($overtimeRequest->employee_id) {
                $actionUrl = Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime']) : url('/hrms/shift-overtime?tab=overtime');
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employeeId: $overtimeRequest->employee_id,
                    title: 'Overtime Cancellation Denied',
                    message: "Your overtime cancellation request for {$overtimeRequest->date} was denied. Request remains approved.",
                    actionUrl: $actionUrl,
                    type: 'overtime_cancellation_denied',
                    iconClass: 'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Overtime cancellation denial notification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('hrms.overtime.cancellation_denied'));
    }

    public function destroy(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        $user = $request->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        if (!$isHrAdmin) {
            $employee = Employee::resolveForUser($user);
            if (!$employee || $overtimeRequest->employee_id !== $employee->id) {
                abort(403, 'Unauthorized action.');
            }
            if ($overtimeRequest->status !== 'pending') {
                return redirect()->back()->with('error', __('hrms.overtime.delete_only_pending'));
            }
        }

        DB::transaction(function () use ($overtimeRequest) {
            $locked = OvertimeRequest::whereKey($overtimeRequest->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['approved', 'cancellation_requested'])) {
                $this->overtimeRepository->revertCompOffLeave($locked);
            }
            $locked->delete();
        });

        return redirect()->back()->with('success', __('hrms.overtime.deleted_successfully'));
    }
}
