<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Models\User;
use App\Services\Access\AccessService;

class BankReconciliationPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, BankReconciliation $reconciliation): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.view', $this->context($reconciliation));
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Working on an open reconciliation: importing, matching, posting
     * adjustments, deleting lines, editing its balances.
     */
    public function update(User $user, BankReconciliation $reconciliation): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.create', $this->context($reconciliation));
    }

    public function complete(User $user, BankReconciliation $reconciliation): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.complete', $this->context($reconciliation));
    }

    /** Unlocking a completed reconciliation is as sensitive as locking it. */
    public function reopen(User $user, BankReconciliation $reconciliation): bool
    {
        return $this->complete($user, $reconciliation);
    }

    /**
     * @return array<string, int|null>
     */
    private function context(BankReconciliation $reconciliation): array
    {
        return [
            'tenant_id' => $reconciliation->tenant_id,
            'company_id' => $reconciliation->company_id,
            'branch_id' => $reconciliation->branch_id,
        ];
    }
}
