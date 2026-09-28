<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeProfileUpdateRequest;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmployeeProfileRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeHrms('hrms.employees.view');

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $status = strtolower(trim((string) $request->input('status', 'all')));
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            $status = 'all';
        }
        $search = trim((string) $request->input('search', ''));
        $departmentId = $request->input('department_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = EmployeeProfileUpdateRequest::query()
            ->with(['employee.department', 'employee.designation', 'user', 'reviewer'])
            ->when($tenantId, fn ($q) => $q->where('employee_profile_update_requests.tenant_id', $tenantId))
            ->when($status !== 'all', fn ($q) => $q->where('employee_profile_update_requests.status', $status))
            ->when(!empty($departmentId), function ($q) use ($departmentId) {
                $q->whereHas('employee', function ($eq) use ($departmentId) {
                    $eq->where('department_id', $departmentId);
                });
            })
            ->when(!empty($search), function ($q) use ($search) {
                $q->whereHas('employee', function ($eq) use ($search) {
                    $eq->where('full_name', 'like', "%{$search}%")
                       ->orWhere('employee_id', 'like', "%{$search}%")
                       ->orWhere('office_email', 'like', "%{$search}%");
                });
            });

        if ($sortBy === 'full_name') {
            $query->join('employees', 'employee_profile_update_requests.employee_id', '=', 'employees.id')
                  ->orderBy('employees.full_name', $sortOrder)
                  ->select('employee_profile_update_requests.*');
        } elseif (in_array($sortBy, ['created_at', 'reviewed_at', 'id'])) {
            $query->orderBy('employee_profile_update_requests.' . $sortBy, $sortOrder);
        } else {
            $query->latest('employee_profile_update_requests.id');
        }

        $profileRequests = $query->paginate(15)->withQueryString();

        $departments = \App\Domains\HRMS\Models\Department::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        return view('modules.hrms.employees.profile-requests.index', compact(
            'profileRequests',
            'status',
            'search',
            'departmentId',
            'departments',
            'sortBy',
            'sortOrder'
        ));
    }

    public function approve(Request $request, EmployeeProfileUpdateRequest $profileRequest): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        if ($profileRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $employee = $profileRequest->employee;
        if (!$employee) {
            return redirect()->back()->with('error', 'Associated employee record not found.');
        }

        $changes = $profileRequest->changes ?? [];
        $employeeUpdates = [];

        foreach ($changes as $field => $data) {
            $newVal = $data['new'] ?? null;
            if ($newVal === '—') {
                $newVal = null;
            }

            if ($field === 'photo') {
                if ($newVal && Storage::disk('public')->exists($newVal)) {
                    if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
                        Storage::disk('public')->delete($employee->photo);
                    }
                    $permanentPath = 'employees/' . basename($newVal);
                    Storage::disk('public')->move($newVal, $permanentPath);
                    $employeeUpdates['photo'] = $permanentPath;
                }
            } else {
                $employeeUpdates[$field] = $newVal;
            }

            if ($field === 'personal_mobile_number' && $employee->user) {
                $employee->user->update(['phone' => $newVal]);
            }
        }

        if (!empty($employeeUpdates)) {
            $employee->update($employeeUpdates);
        }

        $profileRequest->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', "Profile edit request for {$employee->full_name} has been approved.");
    }

    public function reject(Request $request, EmployeeProfileUpdateRequest $profileRequest): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        if ($profileRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $employee = $profileRequest->employee;
        $reason = $request->input('rejection_reason', 'Changes rejected by HR administrator.');

        $profileRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Profile edit request for {$employee?->full_name} has been rejected.");
    }

    private function authorizeHrms(string $permission): void
    {
        abort_unless(
            app(AccessService::class)->allows(auth()->user(), $permission, [
                'tenant_id' => auth()->user()?->tenant_id,
            ]),
            403
        );
    }
}
