<?php

namespace App\Domains\HRMS\Policies;

use App\Models\User;
use App\Services\Access\AccessService;

class BroadcastPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $model = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.broadcasts.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hrms.communications.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->create($user);
    }

    public function pinComment(User $user): bool
    {
        return $this->create($user);
    }
}
