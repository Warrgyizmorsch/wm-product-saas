<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\EmployeeProfileUpdateRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeProfileRequestRepository implements EmployeeProfileRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $status = strtolower(trim((string) ($inputs['status'] ?? 'all')));
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            $status = 'all';
        }
        $search = trim((string) ($inputs['search'] ?? ''));
        $departmentId = $inputs['department_id'] ?? null;
        $sortBy = $inputs['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($inputs['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

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

        $departments = Department::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        return [
            'profileRequests' => $profileRequests,
            'status'          => $status,
            'search'          => $search,
            'departmentId'    => $departmentId,
            'departments'     => $departments,
            'sortBy'          => $sortBy,
            'sortOrder'       => $sortOrder,
        ];
    }

    public function approveRequest(EmployeeProfileUpdateRequest $profileRequest): bool
    {
        $employee = $profileRequest->employee;
        if (!$employee) {
            return false;
        }

        return DB::transaction(function () use ($profileRequest, $employee) {
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
                'status'           => 'approved',
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
                'rejection_reason' => null,
            ]);

            return true;
        });
    }

    public function rejectRequest(EmployeeProfileUpdateRequest $profileRequest, string $reason): bool
    {
        return DB::transaction(function () use ($profileRequest, $reason) {
            return $profileRequest->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
            ]);
        });
    }
}
