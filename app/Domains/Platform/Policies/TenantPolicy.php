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
}
