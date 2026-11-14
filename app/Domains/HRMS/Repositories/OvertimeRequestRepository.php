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
use Illuminate\Support\Facades\Log;

class OvertimeRequestRepository implements OvertimeRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $user = auth()->user();
        $isHrAdmin = $user && (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.roster.manage') ||
            $user->hasHrPermission('hrms.shift_roster.manage') ||
            $user->hasHrPermission('hrms.overtime.approve') ||
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->isCompanyAdmin($user)
        );
        $isAdmin = $isHrAdmin;

        $employee = Employee::resolveForUser($user);

        $query = OvertimeRequest::query()->with(['employee', 'approvedByEmployee']);
        $summaryQuery = OvertimeRequest::query();

        // 🔒 Apply Enterprise Scoping (Company Admin, Unit Head, Branch Manager, Dept Head, Reporting Manager, Employee)
        if (!$isHrAdmin) {
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($query, $user);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($summaryQuery, $user);
        }

        $isOvertimeTab = !isset($inputs['tab']) || in_array($inputs['tab'], ['overtime', 'ot']);
        $overtimeSearch = $inputs['overtime_search'] ?? ($isOvertimeTab ? ($inputs['search'] ?? '') : '');
        $overtimeEmployeeId = $inputs['overtime_employee_id'] ?? ($isOvertimeTab ? ($inputs['employee_id'] ?? '') : '');
        $overtimeStatus = $inputs['overtime_status'] ?? ($isOvertimeTab ? ($inputs['status'] ?? '') : '');
        $overtimeSort = $inputs['overtime_sort'] ?? ($isOvertimeTab ? ($inputs['sort'] ?? 'newest') : 'newest');

        if (!empty($overtimeEmployeeId)) {
            $query->where('employee_id', $overtimeEmployeeId);
        }

        if (!empty($overtimeStatus)) {
            $query->where('status', $overtimeStatus);
        }
        $payrollMonth = $inputs['payroll_month'] ?? '';
        if (!empty($payrollMonth)) {
            $carbonMonth = Carbon::parse($payrollMonth . '-01');
            $query->whereBetween('date', [$carbonMonth->copy()->startOfMonth(), $carbonMonth->copy()->endOfMonth()]);
        }

        if (!empty($overtimeSearch)) {
            $query->where(function ($q) use ($overtimeSearch) {
                $q->whereHas('employee', function ($eq) use ($overtimeSearch) {
                    $eq->where('full_name', 'like', "%{$overtimeSearch}%")
                        ->orWhere('employee_id', 'like', "%{$overtimeSearch}%");
                })
                ->orWhere('reason', 'like', "%{$overtimeSearch}%")
                ->orWhere('compensation_type', 'like', "%{$overtimeSearch}%");
            });
        }

        if ($overtimeSort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = $query->paginate(10, ['*'], 'overtime_page')->withQueryString();

        // Metric Counts
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
            'overtime_max_monthly_hours'    => null,
            'overtime_weekend_multiplier'   => 2.0,
            'overtime_holiday_multiplier'   => 2.5,
        ];
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && is_array($tenant->settings)) {
                $tenantSettings['auto_overtime_threshold_hours'] = $tenant->settings['auto_overtime_threshold_hours'] ?? 0.0;
                $tenantSettings['overtime_rate_multiplier']      = $tenant->settings['overtime_rate_multiplier'] ?? 1.5;
                $tenantSettings['min_overtime_request_hours']    = $tenant->settings['min_overtime_request_hours'] ?? 0.5;
                $tenantSettings['overtime_max_monthly_hours']    = $tenant->settings['overtime_max_monthly_hours'] ?? null;
                $tenantSettings['overtime_weekend_multiplier']   = $tenant->settings['overtime_weekend_multiplier'] ?? 2.0;
                $tenantSettings['overtime_holiday_multiplier']   = $tenant->settings['overtime_holiday_multiplier'] ?? 2.5;
            }
        }

        $companyId = $employee?->company_id ?? ($user?->company_id ?? null);
        $otRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
            ->where(function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                } else {
                    $q->whereNull('company_id');
                }
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        if ($otRule && is_array($otRule->penalty_tiers)) {
            if (isset($otRule->penalty_tiers['auto_overtime_threshold_hours'])) {
                $tenantSettings['auto_overtime_threshold_hours'] = $otRule->penalty_tiers['auto_overtime_threshold_hours'];
            }
            if (isset($otRule->penalty_tiers['min_overtime_request_hours'])) {
                $tenantSettings['min_overtime_request_hours'] = $otRule->penalty_tiers['min_overtime_request_hours'];
            }
            if (isset($otRule->penalty_tiers['overtime_max_monthly_hours'])) {
                $tenantSettings['overtime_max_monthly_hours'] = $otRule->penalty_tiers['overtime_max_monthly_hours'];
            }
            if (isset($otRule->penalty_tiers['overtime_weekend_multiplier'])) {
                $tenantSettings['overtime_weekend_multiplier'] = $otRule->penalty_tiers['overtime_weekend_multiplier'];
            }
            if (isset($otRule->penalty_tiers['overtime_holiday_multiplier'])) {
                $tenantSettings['overtime_holiday_multiplier'] = $otRule->penalty_tiers['overtime_holiday_multiplier'];
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
            $attachmentPath = $request->file('attachment')->storeTenantFile('overtime_attachments', 'public');
        }

        $user = auth()->user();
        $employee = Employee::find($validated['employee_id']);
        $tenantId = $user?->tenant_id ?? $employee?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1);
        $companyId = $validated['company_id'] ?? $employee?->company_id ?? $user?->company_id ?? 1;

        return OvertimeRequest::create([
            'tenant_id'               => $tenantId,
            'company_id'              => $companyId,
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
        $approvalService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $adminEmployee = $user ? $approvalService->getEmployeeFromUser($user) : null;

        if ($action === 'approved' && $user && $requestModel->employee) {
            $approvalService->authorizeApproval($user, $requestModel->employee, $requestModel);
        }

        $oldStatus = $requestModel->status;
        $finalApprovedHours = $approvedHours ?? $requestModel->duration_hours;

        if ($action === 'approved') {
            $requestModel->update([
                'status'                  => 'approved',
                'approved_by'             => $adminEmployee ? $adminEmployee->id : null,
                'approved_duration_hours' => $finalApprovedHours,
                'rejection_reason'        => null,
            ]);

            // If compensation type is comp_off, credit equivalent leave balance
            $isCompOff = in_array(strtolower(str_replace(['-', ' '], '_', (string) $requestModel->compensation_type)), ['comp_off', 'compoff'], true);
            if ($isCompOff) {
                $employeeToCredit = $requestModel->employee ?: Employee::find($requestModel->employee_id);
                if ($employeeToCredit) {
                    $this->creditCompOffLeave(
                        $employeeToCredit,
                        (float) $finalApprovedHours,
                        $requestModel
                    );
                }
            }
        } elseif ($action === 'rejected') {
            if ($oldStatus === 'approved') {
                $this->revertCompOffLeave($requestModel);
            }

            $requestModel->update([
                'status'           => 'rejected',
                'rejection_reason' => $comment,
            ]);
        } elseif ($action === 'pending') {
            if ($oldStatus === 'approved') {
                $this->revertCompOffLeave($requestModel);
            }

            $requestModel->update([
                'status'                  => 'pending',
                'approved_by'             => null,
                'approved_duration_hours' => null,
                'rejection_reason'        => null,
            ]);
        }

        return true;
    }

    public function revertCompOffLeave(OvertimeRequest $overtime): void
    {
        try {
            $isCompOff = in_array(strtolower(str_replace(['-', ' '], '_', (string) $overtime->compensation_type)), ['comp_off', 'compoff'], true);
            if (!$isCompOff || !$overtime->employee_id) {
                return;
            }

            $employee = $overtime->employee ?: Employee::find($overtime->employee_id);
            if (!$employee) {
                return;
            }

            $approvedHours = (float) ($overtime->approved_duration_hours ?? $overtime->duration_hours);
            $workdayHours = $this->getEmployeeWorkdayHours($employee, (string) $overtime->date);
            if ($workdayHours <= 0) {
                $workdayHours = 8.0;
            }
            $creditDays = round($approvedHours / $workdayHours, 1);
            if ($creditDays <= 0) {
                return;
            }

            $leavePlanId = $employee->leave_plan_id;
            $leaveType = null;
            if ($leavePlanId) {
                $leaveType = LeaveType::where('leave_plan_id', $leavePlanId)
                    ->where(function ($q) {
                        $q->where('code', 'COMP_OFF')
                          ->orWhere('code', 'COMPOFF')
                          ->orWhereRaw('LOWER(name) LIKE ?', ['%comp%off%'])
                          ->orWhereRaw('LOWER(name) LIKE ?', ['%compensatory%']);
                    })
                    ->first();
            }

            if (!$leaveType) {
                $leaveType = LeaveType::where(function ($q) {
                    $q->where('code', 'COMP_OFF')
                      ->orWhere('code', 'COMPOFF')
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%comp%off%'])
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%compensatory%']);
                })->first();
            }

            if ($leaveType) {
                $balance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->lockForUpdate()
                    ->first();
                if ($balance) {
                    $newAllocated = max(0.0, (float) $balance->allocated - $creditDays);
                    $newUsed      = min((float) $balance->used, $newAllocated);

                    $balance->update([
                        'allocated' => $newAllocated,
                        'used'      => $newUsed,
                    ]);

                    if (\Illuminate\Support\Facades\Schema::hasColumn('leave_balances', 'remaining')) {
                        $balance->update(['remaining' => max(0.0, $newAllocated - $newUsed)]);
                    }

                    Log::info('Comp Off reverted', [
                        'employee_id'   => $employee->id,
                        'overtime_id'   => $overtime->id,
                        'reverted_days' => $creditDays,
                        'new_allocated' => $newAllocated,
                        'new_used'      => $newUsed,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to revert Comp Off leave: ' . $e->getMessage(), [
                'overtime_id' => $overtime->id,
            ]);
        }
    }

    /**
     * Credit compensatory off leave days to the employee's leave balance.
     * Auto-creates the Comp Off leave type and/or leave plan if they don't exist.
     */
    public function creditCompOffLeave(Employee|int|null $employee, float $approvedHours, OvertimeRequest $overtime): void
    {
        try {
            if (!$employee instanceof Employee) {
                $employee = Employee::find($employee ?: $overtime->employee_id);
            }
            if (!$employee) {
                Log::warning('Comp Off credit skipped: employee not found', ['overtime_id' => $overtime->id]);
                return;
            }

            $tenantId  = $employee->tenant_id ?? $overtime->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null);
            $companyId = $employee->company_id ?? $overtime->company_id ?? ($overtime->tenant_id ? 1 : null);

            // Step 0: Check if approved hours are less than the minimum request hours setting
            $minHours = 0.5;
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant && is_array($tenant->settings)) {
                    $minHours = (float) ($tenant->settings['min_overtime_request_hours'] ?? 0.5);
                }
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

            // Convert overtime hours to fractional days using the employee's assigned shift workday hours
            $workdayHours = $this->getEmployeeWorkdayHours($employee, (string) $overtime->date);
            if ($workdayHours <= 0) {
                $workdayHours = 8.0;
            }
            $creditDays = round($approvedHours / $workdayHours, 1);
            if ($creditDays <= 0) {
                $creditDays = 0.5;
            }

            // Step 1: Find the employee's assigned leave plan, or auto-create one
            $leavePlanId = $employee->leave_plan_id;
            if (!$leavePlanId) {
                $leavePlan = LeavePlan::where(function ($q) use ($companyId, $tenantId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                    if ($tenantId) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })->first();

                if (!$leavePlan) {
                    $planData = [
                        'name'           => 'Default Leave Plan',
                        'company_id'     => $companyId ?? 1,
                        'effective_from' => now()->startOfYear(),
                        'status'         => true,
                        'description'    => 'Auto-created by system for Comp Off tracking.',
                    ];
                    if ($tenantId) {
                        $planData['tenant_id'] = $tenantId;
                    }
                    $leavePlan = LeavePlan::create($planData);
                }

                $leavePlanId = $leavePlan->id;
                $employee->updateQuietly(['leave_plan_id' => $leavePlanId]);
            }

            // Step 2: Find or create the "Compensatory Off" leave type in this plan
            $leaveType = LeaveType::where('leave_plan_id', $leavePlanId)
                ->where(function ($q) {
                    $q->where('code', 'COMP_OFF')
                      ->orWhere('code', 'COMPOFF')
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%comp%off%'])
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%compensatory%']);
                })
                ->first();

            if (!$leaveType) {
                $existingType = LeaveType::where(function ($q) {
                    $q->where('code', 'COMP_OFF')
                      ->orWhere('code', 'COMPOFF')
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%comp%off%'])
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%compensatory%']);
                })
                ->whereHas('plan', function ($pq) use ($companyId, $tenantId) {
                    if ($companyId) {
                        $pq->where('company_id', $companyId);
                    }
                    if ($tenantId) {
                        $pq->orWhere('tenant_id', $tenantId);
                    }
                })
                ->first();

                if ($existingType && (int) $existingType->leave_plan_id === (int) $leavePlanId) {
                    $leaveType = $existingType;
                }
            }

            if (!$leaveType) {
                $typeData = [
                    'leave_plan_id' => $leavePlanId,
                    'name'          => 'Compensatory Off',
                    'code'          => 'COMP_OFF',
                    'description'   => 'Earned compensatory off for approved overtime hours.',
                    'type'          => 'paid',
                    'color'         => '#6366f1',
                    'quota'         => 0.0,
                    'status'        => true,
                    'rules'         => [
                        'approval' => ['workflow_level' => '1_level'],
                        'accrual'  => ['quota_type' => 'limited', 'calculate_in' => 'days'],
                    ],
                ];
                if ($tenantId) {
                    $typeData['tenant_id'] = $tenantId;
                }
                $leaveType = LeaveType::create($typeData);
            }

            // Step 3: Find or create the leave balance record for this employee + leave type
            $leaveBalance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->lockForUpdate()
                ->first();

            if (!$leaveBalance) {
                $balanceData = [
                    'tenant_id'     => $tenantId,
                    'company_id'    => $companyId ?? 1,
                    'employee_id'   => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'allocated'     => (float) $creditDays,
                    'used'          => 0.0,
                    'encashed'      => 0.0,
                ];
                $leaveBalance = LeaveBalance::create($balanceData);
            } else {
                $leaveBalance->increment('allocated', (float) $creditDays);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('leave_balances', 'remaining')) {
                $leaveBalance->update(['remaining' => max(0.0, (float) $leaveBalance->allocated - (float) $leaveBalance->used)]);
            }

            Log::info('Comp Off credited', [
                'employee_id'    => $employee->id,
                'overtime_id'    => $overtime->id,
                'approved_hours' => $approvedHours,
                'credit_days'    => $creditDays,
                'leave_type_id'  => $leaveType->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to credit Comp Off leave: ' . $e->getMessage(), [
                'employee_id' => $employee?->id ?? null,
                'overtime_id' => $overtime->id,
            ]);
        }
    }

    /**
     * Resolve the standard daily shift duration in hours for an employee on a given date.
     * Accurately supports 9-, 10-, or 12-hour shifts dynamically.
     */
    protected function getEmployeeWorkdayHours(Employee $employee, ?string $date = null): float
    {
        $shift = $employee->resolveShiftForDate($date);
        if ($shift) {
            $duration = (float) $shift->durationHours();
            if ($duration > 0) {
                return $duration;
            }
        }

        return 8.0;
    }

    public function processAutoOvertime(Employee $employee, string $date, float $extraHours): ?OvertimeRequest
    {
        $otRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
            ->where('company_id', $employee->company_id)
            ->where('status', true)
            ->first();

        if (!$otRule) {
            $otRule = \App\Domains\HRMS\Models\AttendancePenalty::where('rule_type', 'overtime_rules')
                ->whereNull('company_id')
                ->where('status', true)
                ->first();
        }

        $settings = [];
        if ($otRule && is_array($otRule->penalty_tiers)) {
            $settings = $otRule->penalty_tiers;
        } else {
            $tenant = Tenant::find($employee->tenant_id);
            $settings = $tenant ? ($tenant->settings ?: []) : [];
        }

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

            // Enforce monthly maximum overtime limit if configured
            $maxMonthlyHours = isset($settings['overtime_max_monthly_hours']) && $settings['overtime_max_monthly_hours'] !== '' && $settings['overtime_max_monthly_hours'] !== null
                ? (float) $settings['overtime_max_monthly_hours']
                : null;

            if ($maxMonthlyHours !== null && $maxMonthlyHours > 0) {
                $reqDate = Carbon::parse($date);
                $existingMonthlyHours = (float) OvertimeRequest::where('employee_id', $employee->id)
                    ->whereBetween('date', [$reqDate->copy()->startOfMonth()->toDateString(), $reqDate->copy()->endOfMonth()->toDateString()])
                    ->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
                    ->sum('duration_hours');

                if ($existingMonthlyHours >= $maxMonthlyHours) {
                    return null; // Monthly limit already reached
                }

                if (($existingMonthlyHours + $extraHours) > $maxMonthlyHours) {
                    $extraHours = round($maxMonthlyHours - $existingMonthlyHours, 2);
                    if ($extraHours <= 0) {
                        return null;
                    }
                }
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
