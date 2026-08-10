<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OvertimeRequestRepository implements OvertimeRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        try {
            if (!Schema::hasTable('overtime_requests')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Exception $e) {
            // Silently log or ignore migration execution errors
        }

        $user = auth()->user();
        $isAdmin = true;

        $employee = null;
        if ($user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        $query = OvertimeRequest::query()->with(['employee', 'approvedByEmployee']);

        $overtimeSearch = $inputs['overtime_search'] ?? $inputs['search'] ?? '';
        $overtimeEmployeeId = $inputs['overtime_employee_id'] ?? $inputs['employee_id'] ?? '';
        $overtimeStatus = $inputs['overtime_status'] ?? $inputs['status'] ?? '';
        $overtimeSort = $inputs['overtime_sort'] ?? $inputs['sort'] ?? 'newest';

        if (!empty($overtimeEmployeeId)) {
            $query->where('employee_id', $overtimeEmployeeId);
        }

        if (!empty($overtimeStatus)) {
            $query->where('status', $overtimeStatus);
        }

        if (!empty($overtimeSearch)) {
            $query->whereHas('employee', function ($eq) use ($overtimeSearch) {
                $eq->where('full_name', 'like', "%{$overtimeSearch}%")
                    ->orWhere('employee_id', 'like', "%{$overtimeSearch}%");
            });
        }

        if ($overtimeSort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = $query->paginate(10, ['*'], 'overtime_page')->withQueryString();

        // Metric Counts
        $summaryQuery = OvertimeRequest::query();

        $totalRequests    = (clone $summaryQuery)->count();
        $pendingRequests  = (clone $summaryQuery)->where('status', 'pending')->count();
        $approvedRequests = (clone $summaryQuery)->where('status', 'approved')->count();
        $rejectedRequests = (clone $summaryQuery)->where('status', 'rejected')->count();

        $employees = Employee::where('status', true)->orderBy('full_name')->get();

        // Retrieve current tenant settings for threshold/rate/minimum request
        $tenantSettings = [
            'auto_overtime_threshold_hours' => 0.0,
            'overtime_rate_multiplier'      => 1.5,
            'min_overtime_request_hours'    => 0.5,
        ];
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && is_array($tenant->settings)) {
                $tenantSettings['auto_overtime_threshold_hours'] = $tenant->settings['auto_overtime_threshold_hours'] ?? 0.0;
                $tenantSettings['overtime_rate_multiplier']      = $tenant->settings['overtime_rate_multiplier'] ?? 1.5;
                $tenantSettings['min_overtime_request_hours']    = $tenant->settings['min_overtime_request_hours'] ?? 0.5;
            }
        }

        return compact(
            'requests',
            'employees',
            'employee',
            'isAdmin',
            'tenantSettings',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'overtimeSearch',
            'overtimeEmployeeId',
            'overtimeStatus',
            'overtimeSort'
        );
    }

    public function storeOvertimeRequest(array $validated, Request $request): OvertimeRequest
    {
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('overtime_attachments', 'public');
        }

        $user = auth()->user();
        $tenantId = $user ? $user->tenant_id : null;

        return OvertimeRequest::create([
            'tenant_id'               => $tenantId,
            'company_id'              => $validated['company_id'],
            'employee_id'             => $validated['employee_id'],
            'date'                    => $validated['date'],
            'start_time'              => $validated['start_time'],
            'end_time'                => $validated['end_time'],
            'duration_hours'          => $validated['duration_hours'],
            'approved_duration_hours' => $validated['approved_duration_hours'] ?? $validated['duration_hours'],
            'compensation_type'       => $validated['compensation_type'] ?? 'payout',
            'reason'                  => $validated['reason'],
            'attachment_path'         => $attachmentPath,
            'status'                  => 'pending',
        ]);
    }

    public function updateStatus(OvertimeRequest $requestModel, array $validated, Request $request): bool
    {
        $action  = $validated['action'];
        $comment = $validated['rejection_reason'] ?? null;
        $approvedHours = $validated['approved_duration_hours'] ?? null;

        $user = auth()->user();
        $adminEmployee = null;
        if ($user && $user->email) {
            $adminEmployee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        $finalApprovedHours = $approvedHours ?? $requestModel->duration_hours;

        if ($action === 'approved') {
            $requestModel->update([
                'status'                  => 'approved',
                'approved_by'             => $adminEmployee ? $adminEmployee->id : null,
                'approved_duration_hours' => $finalApprovedHours,
                'rejection_reason'        => null,
            ]);

            // If compensation type is comp_off, credit equivalent leave balance
            if ($requestModel->compensation_type === 'comp_off') {
                $this->creditCompOffLeave(
                    $requestModel->employee,
                    (float) $finalApprovedHours,
                    $requestModel
                );
            }
        } elseif ($action === 'rejected') {
            $requestModel->update([
                'status'           => 'rejected',
                'rejection_reason' => $comment,
            ]);
        } elseif ($action === 'pending') {
            $requestModel->update([
                'status'                  => 'pending',
                'approved_by'             => null,
                'approved_duration_hours' => null,
                'rejection_reason'        => null,
            ]);
        }

        return true;
    }

    /**
     * Credit compensatory off leave days to the employee's leave balance.
     * Auto-creates the Comp Off leave type and/or leave plan if they don't exist.
     */
    protected function creditCompOffLeave(Employee $employee, float $approvedHours, OvertimeRequest $overtime): void
    {
        try {
            $tenantId  = $employee->tenant_id;
            $companyId = $employee->company_id;

            // Step 0: Check if approved hours are less than the minimum request hours setting
            $minHours = 0.5;
            $tenant = Tenant::find($tenantId);
            if ($tenant && is_array($tenant->settings)) {
                $minHours = (float) ($tenant->settings['min_overtime_request_hours'] ?? 0.5);
            }

            if ($approvedHours < $minHours) {
                Log::info('Comp Off credit skipped: approved hours below minimum request threshold', [
                    'employee_id'    => $employee->id,
                    'overtime_id'    => $overtime->id,
                    'approved_hours' => $approvedHours,
                    'min_hours'      => $minHours,
                ]);
                return;
            }

            // Standard workday = 8 hours. Convert overtime hours to fractional days.
            $creditDays = round($approvedHours / 8, 1);
            if ($creditDays <= 0) {
                return;
            }

            // Step 1: Find the employee's assigned leave plan, or auto-create one
            $leavePlanId = $employee->leave_plan_id;
            if (!$leavePlanId) {
                // Auto-create a default leave plan for this company if none exists
                $leavePlan = LeavePlan::firstOrCreate(
                    ['company_id' => $companyId, 'name' => 'Default Leave Plan'],
                    [
                        'effective_from' => now()->startOfYear(),
                        'status'         => true,
                        'description'    => 'Auto-created by system for Comp Off tracking.',
                    ]
                );
                $leavePlanId = $leavePlan->id;
                // Assign this plan to the employee
                $employee->update(['leave_plan_id' => $leavePlanId]);
            }

            // Step 2: Find or auto-create the "Compensatory Off" leave type in this plan
            $leaveType = LeaveType::where('leave_plan_id', $leavePlanId)
                ->where('code', 'COMP_OFF')
                ->first();

            if (!$leaveType) {
                // Also check by name in case code was not set
                $leaveType = LeaveType::where('leave_plan_id', $leavePlanId)
                    ->whereRaw('LOWER(name) LIKE ?', ['%comp%off%'])
                    ->first();
            }

            if (!$leaveType) {
                // Auto-create the Comp Off leave type in the plan
                $leaveType = LeaveType::create([
                    'leave_plan_id' => $leavePlanId,
                    'name'          => 'Compensatory Off',
                    'code'          => 'COMP_OFF',
                    'description'   => 'Earned compensatory off for approved overtime hours.',
                    'type'          => 'fixed',
                    'color'         => '#6366f1',
                    'quota'         => 0,  // No fixed quota; balance is earned dynamically
                    'status'        => true,
                    'rules'         => [],
                ]);
            }

            // Step 3: Find or create the leave balance record for this employee + leave type
            $leaveBalance = LeaveBalance::firstOrCreate(
                [
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $leaveType->id,
                ],
                [
                    'tenant_id'  => $tenantId,
                    'company_id' => $companyId,
                    'allocated'  => 0.0,
                    'used'       => 0.0,
                    'encashed'   => 0.0,
                ]
            );

            // Step 4: Credit the days into the allocated balance
            $leaveBalance->increment('allocated', $creditDays);

            Log::info('Comp Off credited', [
                'employee_id'    => $employee->id,
                'overtime_id'    => $overtime->id,
                'approved_hours' => $approvedHours,
                'credit_days'    => $creditDays,
                'leave_type_id'  => $leaveType->id,
            ]);
        } catch (\Throwable $e) {
            // Log but do not block the approval if credit fails
            Log::error('Failed to credit Comp Off leave', [
                'employee_id' => $employee->id,
                'overtime_id' => $overtime->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    public function updateGlobalSettings(array $settings): bool
    {
        $user = auth()->user();
        if (!$user || !$user->tenant_id) {
            return false;
        }

        $tenant = Tenant::find($user->tenant_id);
        if ($tenant) {
            $currentSettings = $tenant->settings ?: [];
            $currentSettings['auto_overtime_threshold_hours'] = (isset($settings['auto_overtime_threshold_hours']) && $settings['auto_overtime_threshold_hours'] !== '' && $settings['auto_overtime_threshold_hours'] !== null) ? (float) $settings['auto_overtime_threshold_hours'] : null;
            $currentSettings['overtime_rate_multiplier']      = (isset($settings['overtime_rate_multiplier']) && $settings['overtime_rate_multiplier'] !== '' && $settings['overtime_rate_multiplier'] !== null) ? (float) $settings['overtime_rate_multiplier'] : 1.5;
            $currentSettings['min_overtime_request_hours']    = (isset($settings['min_overtime_request_hours']) && $settings['min_overtime_request_hours'] !== '' && $settings['min_overtime_request_hours'] !== null) ? (float) $settings['min_overtime_request_hours'] : null;
            $tenant->update(['settings' => $currentSettings]);
            return true;
        }

        return false;
    }

    public function processAutoOvertime(Employee $employee, string $date, float $extraHours): ?OvertimeRequest
    {
        $tenant = Tenant::find($employee->tenant_id);
        $settings = $tenant ? ($tenant->settings ?: []) : [];

        if (!isset($settings['auto_overtime_threshold_hours']) || $settings['auto_overtime_threshold_hours'] === '' || $settings['auto_overtime_threshold_hours'] === null) {
            return null;
        }

        $threshold = (float) $settings['auto_overtime_threshold_hours'];

        // If auto overtime threshold is 0.0 or less, auto-overtime generation is disabled.
        if ($threshold <= 0.0) {
            return null;
        }

        if ($extraHours >= $threshold) {
            // Deduplication: Skip auto overtime if a request already exists for this employee on this date
            $exists = OvertimeRequest::where('employee_id', $employee->id)
                ->where('date', $date)
                ->exists();
            if ($exists) {
                return null;
            }

            $shift = $employee->resolveShiftForDate($date);
            
            // Default fallback if no shift is resolved
            $startTime = '17:00:00';
            
            if ($shift) {
                // If the employee's shift doesn't allow overtime, return null
                if (!$shift->overtime_allowed) {
                    return null;
                }
                
                if (!empty($shift->end_time)) {
                    $startTime = $shift->end_time;
                }
            }

            $endTime = Carbon::parse($startTime)->addMinutes($extraHours * 60)->format('H:i:s');

            return OvertimeRequest::create([
                'tenant_id'               => $employee->tenant_id,
                'company_id'              => $employee->company_id,
                'employee_id'             => $employee->id,
                'date'                    => $date,
                'start_time'              => $startTime,
                'end_time'                => $endTime,
                'duration_hours'          => $extraHours,
                'approved_duration_hours' => $extraHours,
                'compensation_type'       => 'payout',
                'reason'                  => 'Auto-generated from attendance (extra work >= ' . $threshold . ' hours)',
                'status'                  => 'approved',
            ]);
        }

        return null;
    }
}
