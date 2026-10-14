<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use App\Domains\HRMS\Models\LeaveEncashment;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {

        // On-the-fly plan-level automatic year-end renewal check
        try {
            $now = Carbon::now();
            $plans = LeavePlan::where('status', true)->get();

            foreach ($plans as $plan) {
                if (!$plan->effective_from) {
                    continue;
                }

                $startDate = Carbon::parse($plan->effective_from);
                $diffInYears = $startDate->diffInYears($now);
                
                if ($diffInYears > 0) {
                    $currentCycleStart = $startDate->copy()->addYears($diffInYears);
                    if ($currentCycleStart->isAfter($now)) {
                        $currentCycleStart->subYear();
                    }

                    $lastRenewed = $plan->last_renewed_at ? Carbon::parse($plan->last_renewed_at) : null;
                    
                    if (!$lastRenewed || $lastRenewed->isBefore($currentCycleStart)) {
                        $employees = Employee::where('leave_plan_id', $plan->id)
                            ->where('status', true)
                            ->get();

                        foreach ($employees as $employee) {
                            foreach ($plan->types as $ltype) {
                                $balance = LeaveBalance::firstOrCreate([
                                    'tenant_id' => $employee->tenant_id,
                                    'company_id' => $employee->company_id,
                                    'employee_id' => $employee->id,
                                    'leave_type_id' => $ltype->id,
                                ], [
                                    'allocated' => floatval($ltype->quota),
                                    'used' => 0.0,
                                ]);

                                $rules = $ltype->rules ?? [];
                                $action = $rules['yearend']['action'] ?? 'lapse';
                                $maxCarry = floatval($rules['yearend']['max_carry'] ?? 0.0);

                                $remaining = floatval($balance->remaining);
                                $rollover = 0.0;

                                if ($action === 'carry_forward' && $remaining > 0.0) {
                                    $rollover = min($remaining, $maxCarry);
                                }

                                $newAllocated = floatval($ltype->quota) + $rollover;

                                $balance->update([
                                    'allocated' => $newAllocated,
                                    'used'      => 0.0,
                                    'encashed'  => 0.0,
                                ]);
                            }
                        }

                        $plan->update([
                            'last_renewed_at' => $currentCycleStart->toDateString(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silence errors in case migration is not fully run yet
        }

        $user = auth()->user();
        $canManageEncashments = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leave_encashments.approve'));
        $isHrAdmin = $canManageEncashments || ($user && $user->hasHrPermission('hrms.leave_encashments.view'));
        $isAdmin = $isHrAdmin;

        $employee = Employee::resolveForUser($user);

        $allEmployees = Employee::with('leavePlan.types')->where('status', true)->get();

        $targetBalanceEmpId = $employee ? $employee->id : ($inputs['leaves_employee_id'] ?? $inputs['employee_id'] ?? null);
        if (!$targetBalanceEmpId && $allEmployees->isNotEmpty()) {
            $targetBalanceEmpId = $allEmployees->first()->id;
        }

        // Reconcile used balances scoped to the target employee
        if ($targetBalanceEmpId) {
            try {
                $targetBalances = LeaveBalance::where('employee_id', $targetBalanceEmpId)->with(['employee.leavePlan', 'leaveType'])->get();
                foreach ($targetBalances as $bal) {
                    $currentCycleStart = null;
                    $emp = $bal->employee;
                    if ($emp && $emp->leavePlan && $emp->leavePlan->effective_from) {
                        $startDate = Carbon::parse($emp->leavePlan->effective_from);
                        $now = Carbon::now();
                        $diffInYears = $startDate->diffInYears($now);
                        $currentCycleStart = $startDate->copy()->addYears($diffInYears);
                        if ($currentCycleStart->isAfter($now)) {
                            $currentCycleStart->subYear();
                        }
                    }
                    $currentCycleStart = $currentCycleStart ?: Carbon::now()->startOfYear();

                    $query = LeaveRequest::where('employee_id', $bal->employee_id)
                        ->where('leave_type_id', $bal->leave_type_id)
                        ->where('status', 'approved');

                    if ($currentCycleStart) {
                        $query->where('start_date', '>=', $currentCycleStart);
                    }

                    $leaveRules = $bal->leaveType?->rules ?? [];
                    $isPaidLimited = strtolower((string) ($bal->leaveType?->type ?? '')) === 'paid'
                        && (($leaveRules['accrual']['quota_type'] ?? 'limited') !== 'unlimited');
                    $approvedDuration = $isPaidLimited ? $query->sum('duration') : 0.0;

                    $encashQuery = LeaveEncashment::where('employee_id', $bal->employee_id)
                        ->where('leave_type_id', $bal->leave_type_id)
                        ->where('status', 'approved');

                    if ($currentCycleStart) {
                        $encashQuery->where('created_at', '>=', $currentCycleStart);
                    }

                    $approvedEncashSum = $encashQuery->sum('requested_days');

                    $updates = [];
                    if (floatval($bal->used) !== floatval($approvedDuration)) {
                        $updates['used'] = round($approvedDuration * 2) / 2;
                    }
                    if (floatval($bal->encashed ?? 0.0) !== floatval($approvedEncashSum)) {
                        $updates['encashed'] = round($approvedEncashSum * 2) / 2;
                    }

                    if (!empty($updates)) {
                        $bal->update($updates);
                    }
                }
            } catch (\Exception $e) {
                // Silence errors
            }
        }

        // Eager-load balances in a single query to eliminate N+1 queries
        $allBalancesByEmp = LeaveBalance::whereIn('employee_id', $allEmployees->pluck('id'))
            ->whereHas('leaveType.plan', function($q) {
                $q->where('status', true);
            })
            ->whereHas('leaveType', function($q) {
                $q->where('status', true);
            })
            ->with('leaveType')
            ->get()
            ->groupBy('employee_id');

        $employeeDataMap = [];
        foreach ($allEmployees as $emp) {
            $typesList = [];
            $empBalances = $allBalancesByEmp->get($emp->id, collect());

            foreach ($empBalances as $bal) {
                if (!$bal->leaveType) continue;
                $typesList[] = [
                    'id'           => $bal->leaveType->id,
                    'name'         => $bal->leaveType->name,
                    'quota'        => floatval($bal->allocated),
                    'remaining'    => floatval($bal->remaining),
                    'type'         => $bal->leaveType->type,
                    'calculate_in' => $bal->leaveType->rules['accrual']['calculate_in'] ?? 'days',
                    'rules'        => $bal->leaveType->rules ?? [],
                ];
            }
            $employeeDataMap[$emp->id] = $typesList;
        }

        $balances = $targetBalanceEmpId
            ? ($allBalancesByEmp->get($targetBalanceEmpId) ?? collect())
            : collect();

        $leavesSearch = $inputs['leaves_search'] ?? $inputs['search'] ?? '';
        $leavesEmployeeId = $inputs['leaves_employee_id'] ?? $inputs['employee_id'] ?? '';
        $leavesStatus = $inputs['leaves_status'] ?? $inputs['status'] ?? '';
        $leavesSort = $inputs['leaves_sort'] ?? 'date_desc';

        $encashmentsSearch = $inputs['encashments_search'] ?? $inputs['search'] ?? '';
        $encashmentsEmployeeId = $inputs['encashments_employee_id'] ?? $inputs['employee_id'] ?? '';
        $encashmentsStatus = $inputs['encashments_status'] ?? $inputs['status'] ?? '';
        $encashmentsSort = $inputs['encashments_sort'] ?? 'date_desc';

        $query = LeaveRequest::query()->with(['employee', 'leaveType']);
        $encashQuery = LeaveEncashment::query()->with(['employee', 'leaveType', 'approver']);
        if ($user?->tenant_id) {
            $encashQuery->where('tenant_id', $user->tenant_id);
        } else {
            $encashQuery->whereRaw('1 = 0');
        }

        // 🔒 Apply Enterprise Scoping (Company Admin, Unit Head, Branch Manager, Dept Head, Reporting Manager, Employee)
        if (!$isHrAdmin) {
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($query, $user);
            $encashQuery->where('employee_id', $employee?->id ?? 0);
        }

        // Apply filters to Leave Requests
        if (!empty($leavesEmployeeId)) {
            $query->where('employee_id', $leavesEmployeeId);
        }
        if (!empty($leavesStatus)) {
            $query->where('status', $leavesStatus);
        }
        $payrollMonth = $inputs['payroll_month'] ?? '';
        if (!empty($payrollMonth)) {
            $carbonMonth = \Carbon\Carbon::parse($payrollMonth . '-01');
            $query->where('start_date', '<=', $carbonMonth->copy()->endOfMonth())
                  ->where('end_date', '>=', $carbonMonth->copy()->startOfMonth());
        }
        if (!empty($leavesSearch)) {
            $query->where(function ($q) use ($leavesSearch) {
                $q->whereHas('employee', function ($eq) use ($leavesSearch) {
                    $eq->where('full_name', 'like', "%{$leavesSearch}%")
                        ->orWhere('employee_id', 'like', "%{$leavesSearch}%");
                })->orWhereHas('leaveType', function ($tq) use ($leavesSearch) {
                    $tq->where('name', 'like', "%{$leavesSearch}%")
                        ->orWhere('code', 'like', "%{$leavesSearch}%");
                });
            });
        }

        // Apply sorting to Leave Requests
        if ($leavesSort === 'date_asc') {
            $query->orderBy('created_at', 'asc');
        } elseif ($leavesSort === 'duration_desc') {
            $query->orderBy('duration', 'desc');
        } elseif ($leavesSort === 'duration_asc') {
            $query->orderBy('duration', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Apply filters to Leave Encashments
        if (!empty($encashmentsEmployeeId)) {
            $encashQuery->where('employee_id', $encashmentsEmployeeId);
        }
        if (!empty($encashmentsStatus)) {
            $encashQuery->where('status', $encashmentsStatus);
        }
        if (!empty($encashmentsSearch)) {
            $encashQuery->where(function ($q) use ($encashmentsSearch) {
                $q->whereHas('employee', function ($eq) use ($encashmentsSearch) {
                    $eq->where('full_name', 'like', "%{$encashmentsSearch}%")
                        ->orWhere('employee_id', 'like', "%{$encashmentsSearch}%");
                })->orWhereHas('leaveType', function ($tq) use ($encashmentsSearch) {
                    $tq->where('name', 'like', "%{$encashmentsSearch}%")
                        ->orWhere('code', 'like', "%{$encashmentsSearch}%");
                });
            });
        }

        // Apply sorting to Leave Encashments
        if ($encashmentsSort === 'date_asc') {
            $encashQuery->orderBy('created_at', 'asc');
        } elseif ($encashmentsSort === 'days_desc') {
            $encashQuery->orderBy('requested_days', 'desc');
        } elseif ($encashmentsSort === 'days_asc') {
            $encashQuery->orderBy('requested_days', 'asc');
        } else {
            $encashQuery->orderBy('created_at', 'desc');
        }

        $leaveRequests = $query->paginate(10, ['*'], 'leaves_page')->withQueryString();
        $requests = $leaveRequests;
        $leaveEncashments = $encashQuery->paginate(10, ['*'], 'encash_page')->withQueryString();
        $encashments = $leaveEncashments;

        $employees = Employee::where('status', true)->get();
        $leaveTypes = LeaveType::where('status', true)->get();

        return compact(
            'requests',
            'leaveRequests',
            'balances',
            'employees',
            'allEmployees',
            'employee',
            'leaveTypes',
            'isAdmin',
            'canManageEncashments',
            'employeeDataMap',
            'encashments',
            'leaveEncashments',
            'leavesSearch',
            'leavesEmployeeId',
            'leavesStatus',
            'leavesSort',
            'encashmentsSearch',
            'encashmentsEmployeeId',
            'encashmentsStatus',
            'encashmentsSort'
        );
    }

    public function storeLeaveRequest(array $validated, Request $request): LeaveRequest
    {
        $leaveType = LeaveType::find($validated['leave_type_id']);
        $rules = $leaveType ? ($leaveType->rules ?? []) : [];
        $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';

        $status = 'pending';
        $currentLevel = '1';
        $emp = Employee::find($validated['employee_id']);
        $isNoticePeriod = $emp && ($emp->employee_stage === 'Notice Period' || $emp->employment_status === 'notice');
        $noticeRule = $rules['notice']['rule'] ?? ($rules['notice']['notice_rule'] ?? 'allow');

        if ($workflowLevel === 'auto') {
            if ($isNoticePeriod && $noticeRule === 'special_approval') {
                $status = 'pending';
                $currentLevel = '1';
            } else {
                $status = 'approved';
                $currentLevel = 'approved';
            }
        }

        $rules = $leaveType?->rules ?? [];
        $isPaidLimited = strtolower((string) ($leaveType?->type ?? '')) === 'paid'
            && (($rules['accrual']['quota_type'] ?? 'limited') !== 'unlimited');

        return DB::transaction(function () use ($validated, $request, $status, $currentLevel, $emp, $rules, $isPaidLimited) {
            $balance = null;
            if ($status === 'approved' && $isPaidLimited) {
                $balance = LeaveBalance::where('employee_id', $validated['employee_id'])
                    ->where('leave_type_id', $validated['leave_type_id'])->lockForUpdate()->first();
                $remaining = $balance ? (float) $balance->remaining : 0.0;
                $maxNegative = !empty($rules['accrual']['allow_negative']) ? (float) ($rules['accrual']['max_negative'] ?? 0) : 0.0;
                if (!$balance || (float) $validated['duration'] > $remaining + $maxNegative) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'duration' => __('hrms.leave.app.insufficient_balance', [
                            'remaining' => max(0, $remaining + $maxNegative),
                            'duration' => (float) $validated['duration'],
                        ]),
                    ]);
                }
            }
            $attachmentPath = $request->hasFile('attachment')
                ? $request->file('attachment')->store('leave_attachments', 'public')
                : null;
            $leaveRequest = LeaveRequest::create([
                'tenant_id'         => $emp?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null),
                'company_id'        => $validated['company_id'],
                'employee_id'       => $validated['employee_id'],
                'leave_type_id'     => $validated['leave_type_id'],
                'start_date'        => $validated['start_date'],
                'end_date'          => $validated['end_date'],
                'start_date_type'   => $validated['start_date_type'] ?? 'full_day',
                'end_date_type'     => $validated['end_date_type']   ?? 'full_day',
                'start_time'        => $validated['start_time'] ?? null,
                'end_time'          => $validated['end_time'] ?? null,
                'duration'          => $validated['duration'],
                'reason'            => $validated['reason'],
                'attachment_path'   => $attachmentPath,
                'notified_contacts' => $validated['notified_contacts'] ?? null,
                'status'            => $status,
                'current_level'     => $currentLevel,
            ]);

            if ($status === 'approved' && $isPaidLimited) {
                $balance->increment('used', (float) $validated['duration']);
            }

            return $leaveRequest;
        });
    }

    public function updateStatus(LeaveRequest $leaveRequest, array $validated, Request $request): bool
    {
        $action = $validated['action'];
        $comment = $validated['rejection_reason'] ?? null;

        $user = auth()->user();
        $approvalService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $adminEmployee = $user ? $approvalService->getEmployeeFromUser($user) : null;

        if ($action === 'approved' && $user && $leaveRequest->employee) {
            $approvalService->authorizeApproval($user, $leaveRequest->employee, $leaveRequest);
        }

        $changed = DB::transaction(function () use ($leaveRequest, $action, $comment, $adminEmployee, $user, $approvalService) {
            $lockedRequest = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->with('leaveType', 'employee')->firstOrFail();
            if ($lockedRequest->status === 'cancelled') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => __('hrms.leave.app.status_change_cancelled_error')]);
            }
            if ($action === 'approved' && !$approvalService->canApprove($user, $lockedRequest->employee, $lockedRequest)) {
                abort(403, 'Unauthorized to process leave request at current level.');
            }
            $oldStatus = $lockedRequest->status;
            $rules = $lockedRequest->leaveType?->rules ?? [];
            $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';
            $isPaidLimited = strtolower((string) ($lockedRequest->leaveType?->type ?? '')) === 'paid'
                && (($rules['accrual']['quota_type'] ?? 'limited') !== 'unlimited');
            $wasBalanceCharged = in_array($oldStatus, ['approved', 'cancellation_requested'], true);
            if ($action === 'approved') {
                $isHrOrAdmin = $user && $approvalService->isHrOrAdminActor($user);

                // Check if 2-level approval policy is active and still at Level 1
                if ($workflowLevel === '2_level' && ((string) $lockedRequest->current_level === '1' || (string) $lockedRequest->current_level === '')) {
                    // If direct manager / non-admin is approving, advance to Level 2 for HR review
                    if (! $isHrOrAdmin) {
                        $lockedRequest->update([
                            'current_level' => '2',
                        ]);
                        return true;
                    }
                }

                // Final Approval (Level 2 or 1_level or Admin Master Override)
                $lockedRequest->update([
                    'status'           => 'approved',
                    'current_level'    => 'approved',
                    'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                    'rejection_reason' => null,
                ]);

                // Adjust employee leave balance if approval state changes to approved
                if ($isPaidLimited && !$wasBalanceCharged) {
                    $balance = LeaveBalance::where('employee_id', $lockedRequest->employee_id)
                        ->where('leave_type_id', $lockedRequest->leave_type_id)->lockForUpdate()->first();
                    $remaining = $balance ? (float) $balance->remaining : 0.0;
                    $allowNegative = !empty($rules['accrual']['allow_negative']);
                    $maxNegative = $allowNegative ? (float) ($rules['accrual']['max_negative'] ?? 0) : 0.0;
                    if (!$balance || (float) $lockedRequest->duration > ($remaining + $maxNegative)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' => __('hrms.leave.app.insufficient_balance', [
                                'remaining' => max(0, $remaining + $maxNegative),
                                'duration' => (float) $lockedRequest->duration,
                            ]),
                        ]);
                    }
                    $balance->increment('used', (float) $lockedRequest->duration);
                }
            } elseif ($action === 'rejected') {
                // Restore balance if previously approved
                if ($isPaidLimited && $wasBalanceCharged) {
                    $balance = LeaveBalance::where('employee_id', $lockedRequest->employee_id)
                        ->where('leave_type_id', $lockedRequest->leave_type_id)->lockForUpdate()->first();

                    if ($balance) {
                        $balance->used = max(0, (float) $balance->used - (float) $lockedRequest->duration);
                        $balance->save();
                    }
                }

                $lockedRequest->update([
                    'status'           => 'rejected',
                    'current_level'    => 'rejected',
                    'rejection_reason' => $comment,
                ]);
            } else {
                // Adjust balance if status was approved and is changed away from approved
                if ($isPaidLimited && $wasBalanceCharged && $action !== 'approved') {
                    $balance = LeaveBalance::where('employee_id', $lockedRequest->employee_id)
                        ->where('leave_type_id', $lockedRequest->leave_type_id)->lockForUpdate()->first();

                    if ($balance) {
                        $balance->used = max(0, (float) $balance->used - (float) $lockedRequest->duration);
                        $balance->save();
                    }
                }

                $lockedRequest->update([
                    'status'           => $action,
                    'rejection_reason' => null,
                ]);
            }

            return true;
        });
        $leaveRequest->refresh();
        return $changed;
    }

    public function getPolicyRules(int $employeeId, int $leaveTypeId): array
    {
        $employee = Employee::find($employeeId);
        $leaveType = LeaveType::find($leaveTypeId);

        if (!$employee || !$leaveType) {
            return [
                'application' => ['apply_in_advance' => false, 'require_attachment' => false],
                'probation'   => ['rule' => 'allow', 'probation_rule' => 'allow'],
                'notice'      => ['rule' => 'allow', 'notice_rule' => 'allow'],
                'attachment'  => ['require_attachment' => false, 'attachment_days' => 3],
                'approval'    => ['workflow_level' => '1_level'],
                'accrual'     => [],
                'yearend'     => [],
                'encashment'  => [],
            ];
        }

        $rules = $leaveType->rules ?? [];
        $appRules = $rules['application'] ?? ($rules['attachment'] ?? []);
        
        return [
            'application' => $appRules,
            'probation'   => $rules['probation']   ?? ['rule' => 'allow', 'probation_rule' => 'allow'],
            'notice'      => $rules['notice']      ?? ['rule' => 'allow', 'notice_rule' => 'allow'],
            'attachment'  => [
                'require_attachment' => !empty($appRules['require_attachment']),
                'attachment_days'    => floatval($appRules['attachment_days'] ?? 3),
            ],
            'approval'    => $rules['approval']    ?? ['workflow_level' => '1_level'],
            'accrual'     => $rules['accrual']     ?? [],
            'yearend'     => $rules['yearend']     ?? [],
            'encashment'  => $rules['encashment']  ?? [],
        ];
    }

    /**
     * Cancel a leave request (admin approved the employee's cancellation request).
     * Marks as 'cancelled' and restores the leave balance if the request was approved.
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            $lockedRequest = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status === 'cancelled') return;
            $wasApproved = in_array($lockedRequest->status, ['approved', 'cancellation_requested'], true);

            $lockedRequest->update([
                'status'         => 'cancelled',
                'current_level'  => 'cancelled',
            ]);

            // Restore leave balance if the leave was previously approved
            if ($wasApproved) {
                $balance = LeaveBalance::where('employee_id', $lockedRequest->employee_id)
                    ->where('leave_type_id', $lockedRequest->leave_type_id)->lockForUpdate()->first();

                if ($balance) {
                    $restore = (float) $lockedRequest->duration;
                    // Ensure used doesn't go below 0
                    $newUsed = max(0, floatval($balance->used) - $restore);
                    $balance->update(['used' => $newUsed]);
                }
            }
        });
        $leaveRequest->refresh();
    }

    /**
     * Export Leave Applications to Excel (.xlsx).
     */
    public function export(array $filters = []): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve'));
        $employee = Employee::resolveForUser($user);

        $query = LeaveRequest::with(['employee.department', 'leaveType'])->orderBy('created_at', 'desc');

        if (!$isHrAdmin) {
            $query->where('employee_id', $employee ? $employee->id : 0);
        }

        $status = $filters['leaves_status'] ?? $filters['status'] ?? null;
        $empId  = $filters['leaves_employee_id'] ?? $filters['employee_id'] ?? null;
        $search = $filters['leaves_search'] ?? $filters['search'] ?? null;

        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($empId) && $isHrAdmin) {
            $query->where('employee_id', $empId);
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($eq) use ($search) {
                    $eq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                })->orWhereHas('leaveType', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        $rows = $query->get();

        $headers = [
            'Employee Name',
            'Employee ID',
            'Leave Type',
            'Start Date',
            'End Date',
            'Duration',
            'Unit',
            'Time Range',
            'Status',
            'Applied On',
            'Reason',
        ];

        $data = $rows->map(function ($req) {
            $isHourly = ($req->start_date_type === 'hourly') || ($req->leaveType && ($req->leaveType->rules['accrual']['calculate_in'] ?? 'days') === 'hours');
            $timeRange = '';
            if ($isHourly && !empty($req->start_time) && !empty($req->end_time)) {
                try {
                    $timeRange = Carbon::parse($req->start_time)->format('h:i A') . ' - ' . Carbon::parse($req->end_time)->format('h:i A');
                } catch (\Throwable $e) {
                    $timeRange = $req->start_time . ' - ' . $req->end_time;
                }
            }

            return [
                $req->employee->full_name ?? '—',
                $req->employee->employee_id ?? '—',
                $req->leaveType->name ?? '—',
                $req->start_date ? $req->start_date->format('d M Y') : '—',
                $req->end_date   ? $req->end_date->format('d M Y')   : '—',
                floatval($req->duration),
                $isHourly ? 'Hours' : 'Days',
                $timeRange ?: '—',
                ucfirst($req->status ?? 'pending'),
                $req->created_at ? $req->created_at->format('d M Y H:i') : '—',
                $req->reason ?? '',
            ];
        })->toArray();

        $filename = 'leave_applications_' . now()->format('Y-m-d') . '.xlsx';

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, $filename);
    }
}
