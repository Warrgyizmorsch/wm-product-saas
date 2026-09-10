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

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.pip.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.performance.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }
}
