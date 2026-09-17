<?php

namespace App\Domains\Platform\Policies;

use App\Models\Currency;
use App\Models\User;
use App\Services\Access\AccessService;

class CurrencyPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * The currency master is platform-wide, same as PlanPolicy — a tenant-scoped
     * grant must never satisfy this, only a platform-scope grant may.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'platform.currencies.manage');
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'platform.currencies.manage');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $this->access->allows($user, 'platform.currencies.manage');
    }
}
