<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\EmployeeExit;
use App\Models\User;
use App\Services\Access\AccessService;

class EmployeeExitPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.employee_exits.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, EmployeeExit $exit): bool
    {
        if ($user->employee?->id === $exit->employee_id) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.view', [
            'tenant_id' => $exit->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.employee_exits.create', [
            'tenant_id' => $user->tenant_id,
        ]) || $this->access->allows($user, 'hrms.employees.update', [
            'tenant_id' => $user->tenant_id,
        ]) || ($user->employee !== null);
    }

    public function approve(User $user, mixed $exit = null): bool
    {
        $tenantId = $exit instanceof EmployeeExit ? $exit->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function approveManager(User $user, EmployeeExit $exit): bool
    {
        $userEmpId = $user->employee?->id ?? \App\Domains\HRMS\Models\Employee::where('user_id', $user->id)->value('id');
        $exit->loadMissing('employee');
        if ($userEmpId && $exit->employee && $userEmpId === $exit->employee->reporting_manager_id) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $exit->tenant_id,
        ]);
    }

    public function reject(User $user, EmployeeExit $exit): bool
    {
        $userEmpId = $user->employee?->id ?? \App\Domains\HRMS\Models\Employee::where('user_id', $user->id)->value('id');
        $exit->loadMissing('employee');
        if ($userEmpId && $exit->employee && $userEmpId === $exit->employee->reporting_manager_id) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $exit->tenant_id,
        ]);
    }
}
