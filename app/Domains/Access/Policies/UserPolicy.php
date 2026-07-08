<?php

namespace App\Domains\Access\Policies;

use App\Models\User;
use App\Services\Access\AccessService;

class UserPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'access.users.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    public function view(User $actor, User $target): bool
    {
        return $target->tenant_id === $actor->tenant_id
            && $this->access->allows($actor, 'access.users.manage', [
                'tenant_id' => $actor->tenant_id,
            ]);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'access.users.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    public function update(User $actor, User $target): bool
    {
        return $target->tenant_id === $actor->tenant_id
            && $this->access->allows($actor, 'access.users.manage', [
                'tenant_id' => $actor->tenant_id,
            ]);
    }
}
