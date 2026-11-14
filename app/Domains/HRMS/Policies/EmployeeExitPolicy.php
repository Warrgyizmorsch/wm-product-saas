<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use App\Services\Access\AccessService;

class EmployeeExitPolicy
{
    public function __construct(
        private readonly AccessService $access,
        private readonly HrmsScopeService $scopeService
    ) {
    }

    public function viewAny(User $user): bool
    {
        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.view', [
            'tenant_id' => $user->tenant_id,
        ]) || ($user->employee !== null || Employee::resolveForUser($user) !== null);
    }

    public function view(User $user, EmployeeExit $exit): bool
    {
        $userEmpId = $user->employee?->id ?? Employee::resolveForUser($user)?->id;
        if ($userEmpId && (int) $userEmpId === (int) $exit->employee_id) {
            return true;
        }

        if ($this->scopeService->canAccessEmployee($user, $exit->employee_id)) {
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
        ]) || ($user->employee !== null || Employee::resolveForUser($user) !== null);
    }

    public function approve(User $user, mixed $exit = null): bool
    {
        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        $tenantId = $exit instanceof EmployeeExit ? $exit->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function approveManager(User $user, EmployeeExit $exit): bool
    {
        $userEmpId = $user->employee?->id ?? Employee::resolveForUser($user)?->id;
        if ($userEmpId && (int) $userEmpId === (int) $exit->employee_id) {
            return false; // Prevent conflict of interest
        }

        if ($this->scopeService->canAccessEmployee($user, $exit->employee_id)) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $exit->tenant_id,
        ]);
    }

    public function reject(User $user, EmployeeExit $exit): bool
    {
        $userEmpId = $user->employee?->id ?? Employee::resolveForUser($user)?->id;
        if ($userEmpId && (int) $userEmpId === (int) $exit->employee_id) {
            return false; // Prevent conflict of interest
        }

        if ($this->scopeService->canAccessEmployee($user, $exit->employee_id)) {
            return true;
        }

        return $this->access->allows($user, 'hrms.employee_exits.approve', [
            'tenant_id' => $exit->tenant_id,
        ]);
    }
}
