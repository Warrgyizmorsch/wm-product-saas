<?php

namespace App\Domains\Platform\Policies;

use App\Domains\Platform\Models\Plan;
use App\Models\User;
use App\Services\Access\AccessService;

class PlanPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * The plan catalog is platform-wide, same as TenantPolicy::viewAny — a tenant-scoped
     * grant must never satisfy this, only a platform-scope grant may.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'platform.plans.manage');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->access->allows($user, 'platform.plans.manage');
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'platform.plans.manage');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->access->allows($user, 'platform.plans.manage');
    }
}
