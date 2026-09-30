<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\LedgerGroup;
use App\Models\User;
use App\Services\Access\AccessService;

class LedgerGroupPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.ledger_groups.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, LedgerGroup $ledgerGroup): bool
    {
        return $this->access->allows($user, 'accounting.ledger_groups.view', [
            'tenant_id' => $ledgerGroup->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.ledger_groups.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, LedgerGroup $ledgerGroup): bool
    {
        return $this->access->allows($user, 'accounting.ledger_groups.update', [
            'tenant_id' => $ledgerGroup->tenant_id,
        ]);
    }

    public function delete(User $user, LedgerGroup $ledgerGroup): bool
    {
        return $this->access->allows($user, 'accounting.ledger_groups.delete', [
            'tenant_id' => $ledgerGroup->tenant_id,
        ]);
    }
}
