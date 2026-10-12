<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Access\AccessService;

class AccountingPeriodPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.periods.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, AccountingPeriod $period): bool
    {
        return $this->access->allows($user, 'accounting.periods.view', [
            'tenant_id' => $period->tenant_id,
        ]);
    }

    /**
     * Covers close/lock — shutting a period to new postings.
     */
    public function manage(User $user, AccountingPeriod $period): bool
    {
        return $this->access->allows($user, 'accounting.periods.manage', [
            'tenant_id' => $period->tenant_id,
        ]);
    }

    /**
     * Reopening lets postings back into books that were closed, so it's a
     * separate, stricter permission than closing them.
     */
    public function reopen(User $user, AccountingPeriod $period): bool
    {
        return $this->access->allows($user, 'accounting.periods.reopen', [
            'tenant_id' => $period->tenant_id,
        ]);
    }
}
