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
     * Covers close/lock/reopen — all three are the same "control which
     * periods accept postings" capability, not separate day-to-day actions.
     */
    public function manage(User $user, AccountingPeriod $period): bool
    {
        return $this->access->allows($user, 'accounting.periods.manage', [
            'tenant_id' => $period->tenant_id,
        ]);
    }
}
