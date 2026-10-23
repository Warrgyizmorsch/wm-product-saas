<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Models\ShiftRoster;
use App\Domains\Production\Models\ProductionShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class ShiftChangeRequestRepository implements ShiftChangeRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        try {
            if (!Schema::hasTable('shift_change_requests')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Exception $e) {
            // Silently log or ignore migration execution errors
        }

        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.roster.manage') || $user->hasHrPermission('hrms.shift_roster.manage'));
        $isAdmin = $isHrAdmin;

        $employee = Employee::resolveForUser($user);

        $query = ShiftChangeRequest::query()->with(['employee', 'currentShift', 'requestedShift', 'approvedByEmployee']);
        $summaryQuery = ShiftChangeRequest::query();

        // 🔒 Apply Enterprise Scoping (Company Admin, Unit Head, Branch Manager, Dept Head, Reporting Manager, Employee)
        if (!$isHrAdmin) {
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($query, $user);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($summaryQuery, $user);
        }

        $isShiftTab = !isset($inputs['tab']) || in_array($inputs['tab'], ['shift', 'shift-change', 'shift_change']);
        $shiftSearch = $inputs['shift_search'] ?? ($isShiftTab ? ($inputs['search'] ?? '') : '');
        $shiftEmployeeId = $inputs['shift_employee_id'] ?? ($isShiftTab ? ($inputs['employee_id'] ?? '') : '');
        $shiftStatus = $inputs['shift_status'] ?? ($isShiftTab ? ($inputs['status'] ?? '') : '');
        $shiftSort = $inputs['shift_sort'] ?? ($isShiftTab ? ($inputs['sort'] ?? 'newest') : 'newest');

        if (!empty($shiftEmployeeId)) {
            $query->where('employee_id', $shiftEmployeeId);
        }

        if (!empty($shiftStatus)) {
            $query->where('status', $shiftStatus);
        }

        if (!empty($shiftSearch)) {
            $query->where(function ($q) use ($shiftSearch) {
                $q->whereHas('employee', function ($eq) use ($shiftSearch) {
                    $eq->where('full_name', 'like', "%{$shiftSearch}%")
                        ->orWhere('employee_id', 'like', "%{$shiftSearch}%");
                })
                ->orWhereHas('requestedShift', function ($sq) use ($shiftSearch) {
                    $sq->where('name', 'like', "%{$shiftSearch}%");
                })
                ->orWhereHas('currentShift', function ($sq) use ($shiftSearch) {
                    $sq->where('name', 'like', "%{$shiftSearch}%");
                })
                ->orWhere('reason', 'like', "%{$shiftSearch}%");
            });
        }

        if ($shiftSort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = $query->paginate(10, ['*'], 'shift_page')->withQueryString();

        // Metric Counts
        $totalRequests    = (clone $summaryQuery)->count();
        $pendingRequests  = (clone $summaryQuery)->where('status', 'pending')->count();
        $approvedRequests = (clone $summaryQuery)->where('status', 'approved')->count();
        $rejectedRequests = (clone $summaryQuery)->where('status', 'rejected')->count();

        $employees = Employee::where('status', true)->orderBy('full_name')->get();
        $shifts = ProductionShift::where('active', true)->orderBy('name')->get();

        return compact(
            'requests',
            'employees',
            'employee',
            'isAdmin',
            'shifts',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'shiftSearch',
            'shiftEmployeeId',
            'shiftStatus',
            'shiftSort'
        );
    }

    public function storeShiftChangeRequest(array $validated, Request $request): ShiftChangeRequest
    {
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->storeTenantFile('shift_change_attachments', 'public');
        }

        $user = auth()->user();
        $employee = Employee::find($validated['employee_id']);
        $tenantId = $user?->tenant_id ?? $employee?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1);
        $companyId = $validated['company_id'] ?? $employee?->company_id ?? $user?->company_id ?? 1;

        return ShiftChangeRequest::create([
            'tenant_id'          => $tenantId,
            'company_id'         => $companyId,
            'employee_id'        => $validated['employee_id'],
            'type'               => $validated['type'],
            'start_date'         => $validated['start_date'],
            'end_date'           => $validated['end_date'] ?? null,
            'recurring_days'     => $validated['recurring_days'] ?? null,
            'current_shift_id'   => $validated['current_shift_id'] ?? null,
            'requested_shift_id' => $validated['requested_shift_id'] ?? null,
            'reason'             => $validated['reason'],
            'attachment_path'    => $attachmentPath,
            'status'             => 'pending',
        ]);
    }

    public function updateStatus(ShiftChangeRequest $requestModel, array $validated, Request $request): bool
    {
        $action  = $validated['action'];
        $comment = $validated['rejection_reason'] ?? null;

        $user = auth()->user();
        $approvalService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $adminEmployee = $user ? $approvalService->getEmployeeFromUser($user) : null;

        if ($action === 'approved' && $user && $requestModel->employee) {
            $approvalService->authorizeApproval($user, $requestModel->employee, $requestModel);
        }

        $oldStatus = $requestModel->status;

        if ($action === 'approved') {
            $requestModel->update([
                'status'           => 'approved',
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null,
            ]);

            // Apply Shift Change logic
            $this->applyShiftChange($requestModel);

        } elseif ($action === 'rejected') {
            if ($oldStatus === 'approved') {
                $this->revertShiftChange($requestModel);
            }

            $requestModel->update([
                'status'           => 'rejected',
                'rejection_reason' => $comment,
            ]);
        } elseif ($action === 'pending') {
            if ($oldStatus === 'approved') {
                $this->revertShiftChange($requestModel);
            }

            $requestModel->update([
                'status'           => 'pending',
                'approved_by'      => null,
                'rejection_reason' => null,
            ]);
        }

        return true;
    }

    private function applyShiftChange(ShiftChangeRequest $requestModel): void
    {
        $employee = Employee::findOrFail($requestModel->employee_id);
        $type = $requestModel->type;
        $startDate = Carbon::parse($requestModel->start_date);
        $requestedShiftId = $requestModel->requested_shift_id;
        $tenantId = $requestModel->tenant_id ?? $employee->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1);

        if (!$requestModel->tenant_id && $tenantId) {
            $requestModel->update(['tenant_id' => $tenantId]);
        }

        if ($type === 'temporary') {
            $endDate = $requestModel->end_date ? Carbon::parse($requestModel->end_date) : $startDate;
            $period = CarbonPeriod::create($startDate, $endDate);

            foreach ($period as $date) {
                ShiftRoster::updateOrCreate(
                    [
                        'tenant_id'   => $tenantId,
                        'employee_id' => $employee->id,
                        'date'        => $date->format('Y-m-d'),
                    ],
                    [
                        'shift_id'    => $requestedShiftId,
                        'status'      => 'approved',
                        'notes'       => "Approved Temporary Shift Change (Req #{$requestModel->id})",
                    ]
                );
            }
        } elseif ($type === 'permanent') {
            // Update the Employee's default shift
            $employee->update(['shift_id' => $requestedShiftId]);

            // Generate roster records for the next 30 days to reflect change on immediate grid
            for ($i = 0; $i < 30; $i++) {
                $date = $startDate->copy()->addDays($i);
                ShiftRoster::updateOrCreate(
                    [
                        'tenant_id'   => $tenantId,
                        'employee_id' => $employee->id,
                        'date'        => $date->format('Y-m-d'),
                    ],
                    [
                        'shift_id'    => $requestedShiftId,
                        'status'      => 'approved',
                        'notes'       => "Permanent Shift Assignment (Req #{$requestModel->id})",
                    ]
                );
            }
        } elseif ($type === 'recurring') {
            // Update Employee's weekly pattern
            $weeklyPattern = $employee->weekly_pattern ?: [];
            $recurringDays = $requestModel->recurring_days ?: [];

            foreach ($recurringDays as $day) {
                $weeklyPattern[(int) $day] = $requestedShiftId ?: 'off';
            }

            $employee->update(['weekly_pattern' => $weeklyPattern]);

            // Generate override records for matching days of the week in the next 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dayOfWeek = $date->dayOfWeek;

                if (in_array($dayOfWeek, $recurringDays)) {
                    ShiftRoster::updateOrCreate(
                        [
                            'tenant_id'   => $tenantId,
                            'employee_id' => $employee->id,
                            'date'        => $date->format('Y-m-d'),
                        ],
                        [
                            'shift_id'    => $requestedShiftId,
                            'status'      => 'approved',
                            'notes'       => "Approved Recurring Weekday Shift Change (Req #{$requestModel->id})",
                        ]
                    );
                }
            }
        }
    }

    public function revertShiftChange(ShiftChangeRequest $requestModel): void
    {
        $employee = Employee::find($requestModel->employee_id);
        if (!$employee) {
            return;
        }

        // Delete roster records generated for this specific shift change request
        ShiftRoster::where('employee_id', $employee->id)
            ->where('notes', 'like', "%Req #{$requestModel->id}%")
            ->delete();

        if ($requestModel->type === 'permanent') {
            if ($requestModel->requested_shift_id && (int) $employee->shift_id === (int) $requestModel->requested_shift_id) {
                $employee->update(['shift_id' => $requestModel->current_shift_id]);
            }
        } elseif ($requestModel->type === 'recurring') {
            $weeklyPattern = $employee->weekly_pattern ?: [];
            $recurringDays = $requestModel->recurring_days ?: [];
            foreach ($recurringDays as $day) {
                $weeklyPattern[(int) $day] = $requestModel->current_shift_id ?: 'off';
            }
            $employee->update(['weekly_pattern' => $weeklyPattern]);
        }
    }
}
