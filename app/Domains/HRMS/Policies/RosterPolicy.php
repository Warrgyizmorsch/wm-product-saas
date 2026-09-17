<?php

namespace App\Domains\HRMS\Policies;

use App\Models\User;
use App\Services\Access\AccessService;

class RosterPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.rosters.view', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.rosters.create', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.rosters.update', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->access->allows($user, 'hrms.rosters.update', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }
}
