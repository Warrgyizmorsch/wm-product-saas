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
        $isAdmin = false;
        if ($user) {
            $isAdmin = $user->hasHrPermission('hr.settings.manage')
                || $user->hasHrPermission('hr.leaves.manage')
                || !empty($user->role_id);
        }

        $employee = null;
        if ($user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        $query = ShiftChangeRequest::query()->with(['employee', 'currentShift', 'requestedShift', 'approvedByEmployee']);

        if ($isAdmin) {
            if (!empty($inputs['employee_id'])) {
                $query->where('employee_id', $inputs['employee_id']);
            }
        } else {
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($inputs['status'])) {
            $query->where('status', $inputs['status']);
        }

        if (!empty($inputs['search'])) {
            $search = $inputs['search'];
            $query->whereHas('employee', function ($eq) use ($search) {
                $eq->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $sort = $inputs['sort'] ?? 'newest';
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = $query->get();

        // Metric Counts
        $summaryQuery = ShiftChangeRequest::query();
        if (!$isAdmin && $employee) {
            $summaryQuery->where('employee_id', $employee->id);
        } elseif (!$isAdmin && !$employee) {
            $summaryQuery->whereRaw('1 = 0');
        }

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
            'rejectedRequests'
        );
    }

    public function storeShiftChangeRequest(array $validated, Request $request): ShiftChangeRequest
    {
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('shift_change_attachments', 'public');
        }

        $user = auth()->user();
        $tenantId = $user ? $user->tenant_id : null;

        return ShiftChangeRequest::create([
            'tenant_id'          => $tenantId,
            'company_id'         => $validated['company_id'],
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
        $adminEmployee = null;
        if ($user && $user->email) {
            $adminEmployee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        if ($action === 'approved') {
            $requestModel->update([
                'status'           => 'approved',
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null,
            ]);

            // Apply Shift Change logic
            $this->applyShiftChange($requestModel);

        } elseif ($action === 'rejected') {
            $requestModel->update([
                'status'           => 'rejected',
                'rejection_reason' => $comment,
            ]);
        } elseif ($action === 'pending') {
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

        if ($type === 'temporary') {
            $endDate = $requestModel->end_date ? Carbon::parse($requestModel->end_date) : $startDate;
            $period = CarbonPeriod::create($startDate, $endDate);

            foreach ($period as $date) {
                ShiftRoster::updateOrCreate(
                    [
                        'tenant_id'   => $requestModel->tenant_id,
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
                        'tenant_id'   => $requestModel->tenant_id,
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
                            'tenant_id'   => $requestModel->tenant_id,
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
}
