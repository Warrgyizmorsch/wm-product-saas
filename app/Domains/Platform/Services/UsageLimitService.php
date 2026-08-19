<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class UsageLimitService
{
    /**
     * The tenant's own max_users column always wins when set — it's the
     * per-tenant override an operator uses to grant/restrict a specific
     * tenant regardless of its plan. Only when that's blank do we fall
     * back to the linked plan's catalog limit. Null means unlimited.
     */
    public function maxUsers(Tenant $tenant): ?int
    {
        return $tenant->max_users ?? $tenant->planCatalog?->max_users;
    }

    public function currentUserCount(Tenant $tenant): int
    {
        return User::query()->where('tenant_id', $tenant->id)->count();
    }

    public function remainingUserSlots(Tenant $tenant): ?int
    {
        $max = $this->maxUsers($tenant);

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentUserCount($tenant));
    }

    /**
     * @throws UsageLimitExceededException
     */
    public function assertCanAddUser(Tenant $tenant): void
    {
        $max = $this->maxUsers($tenant);

        if ($max === null) {
            return;
        }

        if ($this->currentUserCount($tenant) >= $max) {
            throw UsageLimitExceededException::forUsers($max);
        }
    }

    /**
     * One row per tenant for the platform-wide usage console — the single
     * place an admin checks consumption against plan limits without opening
     * each tenant individually.
     *
     * @return Collection<int, array{tenant: Tenant, plan_name: string, max_users: ?int, used_users: int, remaining_users: ?int, user_percent: ?float, source: string}>
     */
    public function overview(): Collection
    {
        return Tenant::query()
            ->with('planCatalog')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) {
                $max = $this->maxUsers($tenant);
                $used = $tenant->users_count;

                return [
                    'tenant' => $tenant,
                    'plan_name' => $tenant->planCatalog?->name ?? ucfirst((string) $tenant->plan),
                    'max_users' => $max,
                    'used_users' => $used,
                    'remaining_users' => $max === null ? null : max(0, $max - $used),
                    'user_percent' => $max === null || $max === 0 ? null : min(100, round(($used / $max) * 100)),
                    'source' => $tenant->max_users !== null ? 'tenant override' : ($tenant->planCatalog !== null ? 'plan' : 'unlimited'),
                ];
            });
    }
}
