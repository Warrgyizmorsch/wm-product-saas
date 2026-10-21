<?php

namespace App\Domains\HRMS\Policies;

use App\Models\User;
use App\Services\Access\AccessService;

class PipPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function view(User $user, \App\Domains\HRMS\Models\PerformanceImprovementPlan $pip): bool
    {
        if ((int) $user->tenant_id !== (int) $pip->tenant_id) {
            return false;
        }

        $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);
        if ($employee && (int) $employee->id === (int) $pip->employee_id) {
            return true;
        }
        if ($employee && (int) $employee->id === (int) $pip->manager_id) {
            return true;
        }
        if ((int) $user->id === (int) $pip->hr_representative_id) {
            return true;
        }

        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        if ($model instanceof \App\Domains\HRMS\Models\PerformanceImprovementPlan) {
            if ((int) $user->tenant_id !== (int) $model->tenant_id) {
                return false;
            }
            $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);
            if ($employee && (int) $employee->id === (int) $model->manager_id) {
                return true;
            }
            if ((int) $user->id === (int) $model->hr_representative_id) {
                return true;
            }
        }

        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        if ($model instanceof \App\Domains\HRMS\Models\PerformanceImprovementPlan) {
            if ((int) $user->tenant_id !== (int) $model->tenant_id) {
                return false;
            }
        }

        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function acknowledge(User $user, \App\Domains\HRMS\Models\PerformanceImprovementPlan $pip): bool
    {
        if ((int) $user->tenant_id !== (int) $pip->tenant_id) {
            return false;
        }

        $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);
        return $employee && (int) $employee->id === (int) $pip->employee_id;
    }
}
