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
        return $this->access->allows($user, 'accounting.bank_reconciliation.view', [
            'tenant_id' => $reconciliation->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function complete(User $user, BankReconciliation $reconciliation): bool
    {
        return $this->access->allows($user, 'accounting.bank_reconciliation.complete', [
            'tenant_id' => $reconciliation->tenant_id,
        ]);
    }
}
