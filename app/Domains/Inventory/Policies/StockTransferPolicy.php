<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockTransfer;
use App\Models\User;
use App\Services\Access\AccessService;

class StockTransferPolicy
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

        return $this->access->allows($user, 'inventory.transfers.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        $context = [
            'tenant_id'     => $transfer->tenant_id,
            'owner_id'      => $transfer->created_by ?? $user->id,
            'branch_id'     => $transfer->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $transfer->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.transfers.view', $context)
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

        return $this->access->allows($user, 'inventory.transfers.create', $context)
            || $this->access->allows($user, 'inventory.transfers.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        $context = [
            'tenant_id'     => $transfer->tenant_id,
            'owner_id'      => $transfer->created_by ?? $user->id,
            'branch_id'     => $transfer->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $transfer->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.transfers.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $this->update($user, $transfer);
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $this->update($user, $transfer);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->update($user, $transfer);
    }
}
