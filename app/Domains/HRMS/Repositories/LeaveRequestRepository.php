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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class LeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        // Programmatically run migrations on-the-fly if table or column doesn't exist
        try {
            if (!Schema::hasTable('leave_encashments') || !Schema::hasColumn('leave_balances', 'encashed')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Exception $e) {
            // Silently log or ignore migration execution errors
        }

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
                                    'used' => 0.0,
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

        // Reconcile used balances
        try {
            $allBalances = LeaveBalance::with('employee.leavePlan')->get();
            foreach ($allBalances as $bal) {
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

                $query = LeaveRequest::where('employee_id', $bal->employee_id)
                    ->where('leave_type_id', $bal->leave_type_id)
                    ->where('status', 'approved');

                if ($currentCycleStart) {
                    $query->where('start_date', '>=', $currentCycleStart);
                }

                $approvedDuration = $query->sum('duration');

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

        $user = auth()->user();
        $isAdmin = true;

        $employee = Employee::where('personal_email', $user->email)
            ->orWhere('office_email', $user->email)
            ->first();

        $allEmployees = Employee::where('status', true)->get();

        $employeeDataMap = [];
        foreach ($allEmployees as $emp) {
            $balances = LeaveBalance::where('employee_id', $emp->id)
                ->whereHas('leaveType.plan', function($q) {
                    $q->where('status', true);
                })
                ->whereHas('leaveType', function($q) {
                    $q->where('status', true);
                })
                ->with('leaveType')
                ->get();
            $typesList = [];
            foreach ($balances as $bal) {
                $typesList[] = [
                    'id' => $bal->leaveType->id,
                    'name' => $bal->leaveType->name,
                    'quota' => floatval($bal->allocated),
                    'remaining' => floatval($bal->remaining),
                    'type' => $bal->leaveType->type,
                    'rules' => $bal->leaveType->rules ?? [],
                ];
            }
            $employeeDataMap[$emp->id] = $typesList;
        }

        $balances = $employee ? LeaveBalance::where('employee_id', $employee->id)->with('leaveType')->get() : collect();

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

        // Apply filters to Leave Requests
        if (!empty($leavesEmployeeId)) {
            $query->where('employee_id', $leavesEmployeeId);
        }
        if (!empty($leavesStatus)) {
            $query->where('status', $leavesStatus);
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
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        return LeaveRequest::create([
            'company_id'        => $validated['company_id'],
            'employee_id'       => $validated['employee_id'],
            'leave_type_id'     => $validated['leave_type_id'],
            'start_date'        => $validated['start_date'],
            'end_date'          => $validated['end_date'],
            'start_date_type'   => $validated['start_date_type'] ?? 'full_day',
            'end_date_type'     => $validated['end_date_type']   ?? 'full_day',
            'session'           => $validated['session'] ?? 'full_day',
            'duration'          => $validated['duration'],
            'reason'            => $validated['reason'],
            'attachment_path'   => $attachmentPath,
            'notified_contacts' => $validated['notified_contacts'] ?? null,
            'status'            => 'pending',
            'current_level'     => 1,
        ]);

    }

    public function updateStatus(LeaveRequest $leaveRequest, array $validated, Request $request): bool
    {
        $action = $validated['action'];
        $comment = $validated['rejection_reason'] ?? null;
        $oldStatus = $leaveRequest->status;

        if ($oldStatus === $action) {
            return true;
        }

        // Perform database status update
        $leaveRequest->update([
            'status' => $action,
            'rejection_reason' => ($action === 'rejected') ? $comment : null,
        ]);

        // Adjust employee leave balance if approval state changes
        if ($oldStatus === 'approved' && $action !== 'approved') {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();

            if ($balance) {
                $balance->decrement('used', floatval($leaveRequest->duration));
            }
        } elseif ($oldStatus !== 'approved' && $action === 'approved') {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();

            if ($balance) {
                $balance->increment('used', floatval($leaveRequest->duration));
            }
        }

        return true;
    }

    public function getPolicyRules(int $employeeId, int $leaveTypeId): array
    {
        $employee = Employee::find($employeeId);
        $leaveType = LeaveType::find($leaveTypeId);

        if (!$employee || !$leaveType) {
            return [
                'probation' => ['probation_rule' => 'allow'],
                'notice' => ['notice_rule' => 'allow'],
                'attachment' => ['require_attachment' => false],
                'approval' => ['workflow_level' => '1_level'],
            ];
        }

        $rules = $leaveType->rules ?? [];
        
        return [
            'probation'  => $rules['probation']  ?? ['probation_rule' => 'allow'],
            'notice'     => $rules['notice']      ?? ['notice_rule' => 'allow'],
            'attachment' => $rules['attachment']  ?? ['require_attachment' => false],
            'approval'   => $rules['approval']    ?? ['workflow_level' => '1_level'],
        ];
    }

    /**
     * Cancel a leave request (admin approved the employee's cancellation request).
     * Marks as 'cancelled' and restores the leave balance if the request was approved.
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest): void
    {
        $wasApproved = in_array($leaveRequest->status, ['approved', 'cancellation_requested']);

        $leaveRequest->update([
            'status'         => 'cancelled',
            'current_level'  => 'cancelled',
        ]);

        // Restore leave balance if the leave was previously approved
        if ($wasApproved) {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();

            if ($balance) {
                $restore = floatval($leaveRequest->duration);
                // Ensure used doesn't go below 0
                $newUsed = max(0, floatval($balance->used) - $restore);
                $balance->update(['used' => $newUsed]);
            }
        }
    }
}
