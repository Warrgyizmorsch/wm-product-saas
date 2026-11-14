<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use App\Services\Access\AccessService;

class PipPolicy
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

        return $this->access->allows($user, 'hrms.pip.view', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id])
            || ($user->employee !== null || Employee::resolveForUser($user) !== null);
    }

    public function view(User $user, PerformanceImprovementPlan $pip): bool
    {
        if ((int) $user->tenant_id !== (int) $pip->tenant_id) {
            return false;
        }

        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        $employee = Employee::resolveForUser($user);
        if ($employee && (int) $employee->id === (int) $pip->employee_id) {
            return true;
        }
        if ($employee && (int) $employee->id === (int) $pip->manager_id) {
            return true;
        }
        if ($this->scopeService->canAccessEmployee($user, $pip->employee_id)) {
            return true;
        }
        if ((int) $user->id === (int) $pip->hr_representative_id) {
            return true;
        }

        return $this->access->allows($user, 'hrms.pip.view', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function create(User $user): bool
    {
        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        return $this->access->allows($user, 'hrms.pip.create', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        if ($model instanceof PerformanceImprovementPlan) {
            if ((int) $user->tenant_id !== (int) $model->tenant_id) {
                return false;
            }
            $employee = Employee::resolveForUser($user);
            // Employee cannot evaluate or update their own PIP (anti-self-approval)
            if ($employee && (int) $employee->id === (int) $model->employee_id) {
                return false;
            }
            if ($employee && (int) $employee->id === (int) $model->manager_id) {
                return true;
            }
            if ($this->scopeService->canAccessEmployee($user, $model->employee_id)) {
                return true;
            }
            if ((int) $user->id === (int) $model->hr_representative_id) {
                return true;
            }
        }

        return $this->access->allows($user, 'hrms.pip.update', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof PerformanceImprovementPlan) {
            if ((int) $user->tenant_id !== (int) $model->tenant_id) {
                return false;
            }
        }

        if ($this->scopeService->isCompanyAdmin($user)) {
            return true;
        }

        return $this->access->allows($user, 'hrms.pip.delete', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function acknowledge(User $user, PerformanceImprovementPlan $pip): bool
    {
        if ((int) $user->tenant_id !== (int) $pip->tenant_id) {
            return false;
        }

        $employee = Employee::resolveForUser($user);
        return $employee && (int) $employee->id === (int) $pip->employee_id;
    }
}
