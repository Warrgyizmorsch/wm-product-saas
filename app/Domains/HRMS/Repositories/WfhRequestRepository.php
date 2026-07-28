<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class WfhRequestRepository implements WfhRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        try {
            if (!Schema::hasTable('wfh_requests')) {
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

        $query = WfhRequest::query()->with(['employee', 'approvedByEmployee']);

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
        } elseif ($sort === 'duration_high') {
            $query->orderBy('duration', 'desc');
        } elseif ($sort === 'duration_low') {
            $query->orderBy('duration', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = (clone $query)->get();

        // Metric Counts
        $summaryQuery = WfhRequest::query();
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

        return compact(
            'requests',
            'employees',
            'employee',
            'isAdmin',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests'
        );
    }

    public function storeWfhRequest(array $validated, Request $request): WfhRequest
    {
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('wfh_attachments', 'public');
        }

        $user = auth()->user();
        $tenantId = $user ? $user->tenant_id : null;

        return WfhRequest::create([
            'tenant_id'         => $tenantId,
            'company_id'        => $validated['company_id'],
            'employee_id'       => $validated['employee_id'],
            'start_date'        => $validated['start_date'],
            'end_date'          => $validated['end_date'],
            'start_date_type'   => $validated['start_date_type'] ?? 'full_day',
            'end_date_type'     => $validated['end_date_type'] ?? 'full_day',
            'duration'          => $validated['duration'] ?? 1.0,
            'notified_contacts' => $validated['notified_contacts'] ?? null,
            'reason'            => $validated['reason'],
            'attachment_path'   => $attachmentPath,
            'status'            => 'pending',
            'current_level'     => '1',
        ]);
    }

    public function updateStatus(WfhRequest $wfhRequest, array $validated, Request $request): bool
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
            $wfhRequest->update([
                'status'           => 'approved',
                'current_level'    => 'approved',
                'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                'rejection_reason' => null,
            ]);
        } elseif ($action === 'rejected') {
            $wfhRequest->update([
                'status'           => 'rejected',
                'current_level'    => 'rejected',
                'rejection_reason' => $comment,
            ]);
        } elseif ($action === 'pending') {
            $wfhRequest->update([
                'status'           => 'pending',
                'current_level'    => '1',
                'approved_by'      => null,
                'rejection_reason' => null,
            ]);
        }

        return true;
    }

    /**
     * Cancel a WFH request (admin approved the employee's cancellation request).
     * WFH has no leave balance — just mark as cancelled.
     */
    public function cancelWfhRequest(WfhRequest $wfhRequest): void
    {
        $wfhRequest->update([
            'status'        => 'cancelled',
            'current_level' => 'cancelled',
        ]);
    }
}
