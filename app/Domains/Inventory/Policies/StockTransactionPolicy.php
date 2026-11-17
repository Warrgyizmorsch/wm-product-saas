<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockTransaction;
use App\Models\User;
use App\Services\Access\AccessService;

class StockTransactionPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.transactions.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function view(User $user, StockTransaction $transaction): bool
    {
        $context = [
            'tenant_id'     => $transaction->tenant_id,
            'owner_id'      => $transaction->created_by ?? $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.transactions.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function create(User $user): bool
    {
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.adjustments.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }
}
