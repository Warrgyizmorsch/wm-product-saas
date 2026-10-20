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
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));
        $isAdmin = $isHrAdmin;

        $employee = Employee::resolveForUser($user);
        $canManageRequests = $isAdmin || ($employee && (
            Employee::query()->where('reporting_manager_id', $employee->id)->exists()
            || \App\Domains\HRMS\Models\Department::query()->where('head_employee_id', $employee->id)->exists()
        ));

        $wfhSearch = $inputs['wfh_search'] ?? $inputs['search'] ?? '';
        $wfhEmployeeId = $inputs['wfh_employee_id'] ?? $inputs['employee_id'] ?? '';
        $wfhStatus = $inputs['wfh_status'] ?? $inputs['status'] ?? '';
        $wfhSort = $inputs['wfh_sort'] ?? $inputs['sort'] ?? 'newest';

        $query = WfhRequest::query()->with(['employee', 'approvedByEmployee']);
        $summaryQuery = WfhRequest::query();

        // 🔒 Apply Enterprise Scoping (Company Admin, Unit Head, Branch Manager, Dept Head, Reporting Manager, Employee)
        if (!$isHrAdmin) {
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($query, $user);
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($summaryQuery, $user);
        }

        if (!empty($wfhEmployeeId)) {
            $query->where('employee_id', $wfhEmployeeId);
        }

        if (!empty($wfhStatus)) {
            $query->where('status', $wfhStatus);
        }

        if (!empty($wfhSearch)) {
            $query->whereHas('employee', function ($eq) use ($wfhSearch) {
                $eq->where('full_name', 'like', "%{$wfhSearch}%")
                    ->orWhere('employee_id', 'like', "%{$wfhSearch}%");
            });
        }

        if ($wfhSort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($wfhSort === 'duration_high') {
            $query->orderBy('duration', 'desc');
        } elseif ($wfhSort === 'duration_low') {
            $query->orderBy('duration', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $requests = $query->paginate(10, ['*'], 'wfh_page')->withQueryString();

        // Metric Counts
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
            'canManageRequests',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'wfhSearch',
            'wfhEmployeeId',
            'wfhStatus',
            'wfhSort'
        );
    }

    public function storeWfhRequest(array $validated, Request $request): WfhRequest
    {
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->storeTenantFile('wfh_attachments', 'public');
        }

        $user = auth()->user();
        $employee = Employee::find($validated['employee_id']);
        $tenantId = $user?->tenant_id ?? $employee?->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null);
        $companyId = $validated['company_id'] ?? $employee?->company_id;

        return WfhRequest::create([
            'tenant_id'         => $tenantId,
            'company_id'        => $companyId,
            'employee_id'       => $validated['employee_id'],
            'start_date'        => $validated['start_date'],
            'end_date'          => $validated['end_date'],
            'start_date_type'   => $validated['start_date_type'] ?? 'full_day',
            'end_date_type'     => $validated['end_date_type'] ?? 'full_day',
            'duration'          => $validated['duration'] ?? 1.0,
            'notified_contacts' => $validated['notified_contacts'] ?? null,
            'reason'            => $validated['reason'],
            'wfh_latitude'      => $validated['wfh_latitude'] ?? $employee?->wfh_latitude ?? null,
            'wfh_longitude'     => $validated['wfh_longitude'] ?? $employee?->wfh_longitude ?? null,
            'attachment_path'   => $attachmentPath,
            'status'            => 'pending',
            'current_level'     => '1',
        ]);
    }

    public function updateStatus(WfhRequest $wfhRequest, array $validated, Request $request): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($wfhRequest, $validated, $request) {
            $locked = WfhRequest::whereKey($wfhRequest->id)->lockForUpdate()->firstOrFail();

            $action  = $validated['action'];
            $comment = $validated['rejection_reason'] ?? null;

            $user = auth()->user();
            $approvalService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
            $adminEmployee = $user ? $approvalService->getEmployeeFromUser($user) : null;

            if ($action === 'approved' && $user && $locked->employee) {
                $approvalService->authorizeApproval($user, $locked->employee, $locked);
            }

            if ($action === 'approved') {
                $locked->update([
                    'status'           => 'approved',
                    'current_level'    => 'approved',
                    'approved_by'      => $adminEmployee ? $adminEmployee->id : null,
                    'rejection_reason' => null,
                ]);
            } elseif ($action === 'rejected') {
                $locked->update([
                    'status'           => 'rejected',
                    'current_level'    => 'rejected',
                    'rejection_reason' => $comment,
                ]);
            } elseif ($action === 'pending') {
                $locked->update([
                    'status'           => 'pending',
                    'current_level'    => '1',
                    'approved_by'      => null,
                    'rejection_reason' => null,
                ]);
            }

            return true;
        });
    }

    /**
     * Cancel a WFH request (admin approved the employee's cancellation request).
     * WFH has no leave balance — just mark as cancelled.
     */
    public function cancelWfhRequest(WfhRequest $wfhRequest): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($wfhRequest) {
            $locked = WfhRequest::whereKey($wfhRequest->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status'        => 'cancelled',
                'current_level' => 'cancelled',
            ]);
        });
    }

    /**
     * Export WFH requests to Excel (.xlsx).
     */
    public function export(array $filters = []): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.leave_requests.approve') || $user->hasHrPermission('hrms.leaves.manage') || $user->hasHrPermission('hrms.wfh_requests.approve') || $user->hasHrPermission('hrms.wfh_requests.manage'));

        $query = WfhRequest::with(['employee.department'])->orderBy('created_at', 'desc');

        if (!$isHrAdmin) {
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->applyRelatedScope($query, $user);
        }

        $status     = $filters['status'] ?? $filters['wfh_status'] ?? null;
        $employeeId = $filters['employee_id'] ?? $filters['wfh_employee_id'] ?? null;
        $search     = $filters['search'] ?? $filters['wfh_search'] ?? null;

        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($employeeId)) {
            $query->where('employee_id', $employeeId);
        }
        if (!empty($search)) {
            $query->whereHas('employee', function ($eq) use ($search) {
                $eq->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $rows = $query->get();

        $headers = [
            'Employee Name',
            'Employee ID',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Status',
            'Applied On',
            'Reason',
        ];

        $data = $rows->map(function ($req) {
            return [
                $req->employee->full_name ?? '—',
                $req->employee->employee_id ?? '—',
                $req->start_date ? \Carbon\Carbon::parse($req->start_date)->format('d M Y') : '—',
                $req->end_date   ? \Carbon\Carbon::parse($req->end_date)->format('d M Y')   : '—',
                floatval($req->duration),
                ucfirst($req->status ?? 'pending'),
                $req->created_at ? $req->created_at->format('d M Y H:i') : '—',
                $req->reason ?? '',
            ];
        })->toArray();

        $filename = 'wfh_requests_' . now()->format('Y-m-d') . '.xlsx';

        return \App\Domains\HRMS\Helpers\XlsxHelper::export($headers, $data, $filename);
    }
}

