<?php

namespace App\Domains\Access\Policies;

use App\Models\Access\Role;
use App\Models\User;
use App\Services\Access\AccessService;

class RolePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'access.roles.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    /**
     * Role is not tenant-scoped by the model (no BelongsToTenant), so
     * every ability here must check visibility explicitly — a global
     * system role (tenant_id null) is visible to everyone; a custom role
     * is visible only to the tenant that owns it.
     */
    public function view(User $actor, Role $role): bool
    {
        if (! $this->visibleTo($actor, $role)) {
            return false;
        }

        return $this->access->allows($actor, 'access.roles.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'access.roles.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    /**
     * A tenant admin's `access.permissions.manage` grant is stored at
     * SCOPE_TENANT, and AccessService's scope matching only checks that
     * the acting user's own tenant matches — it has no notion of "the
     * *target* role is a global system role". That safety rule can't be
     * expressed through allows()+scope, so it's enforced here directly:
     * a system role's permissions may only be edited by someone who
     * actually holds the super_admin role.
     */
    public function managePermissions(User $actor, Role $role): bool
    {
        if (! $this->visibleTo($actor, $role)) {
            return false;
        }

        if ($role->is_system && $role->tenant_id === null) {
            return $this->access->hasRole($actor, 'super_admin');
        }

        return $this->access->allows($actor, 'access.permissions.manage', [
            'tenant_id' => $actor->tenant_id,
        ]);
    }

    private function visibleTo(User $actor, Role $role): bool
    {
        return $role->tenant_id === null || $role->tenant_id === $actor->tenant_id;
    }
}
