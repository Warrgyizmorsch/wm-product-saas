<?php

namespace App\Domains\Platform\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;

class TenantPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * Listing every tenant and creating new ones are platform-wide operations —
     * a tenant-scoped grant must not satisfy this, otherwise any tenant_owner
     * could browse every other tenant's record.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'platform.tenants.manage');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, 'platform.tenants.manage', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'platform.tenants.manage');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, 'platform.tenants.manage', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function updateStatus(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, 'platform.tenants.manage', [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Self-service: a tenant's own user viewing/changing ITS OWN subscription
     * plan. Distinct from platform.tenants.manage (which lets a platform
     * admin browse/edit every tenant) — this is scoped to the single tenant
     * passed in, which callers must always resolve as the current tenant(),
     * never an arbitrary tenant id from the request.
     */
    public function viewSubscription(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, 'tenant.subscription.manage', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function updateSubscription(User $user, Tenant $tenant): bool
    {
        return $this->access->allows($user, 'tenant.subscription.manage', [
            'tenant_id' => $tenant->id,
        ]);
    }
}
