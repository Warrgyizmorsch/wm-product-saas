<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use App\Services\Access\AccessService;

class WarehousePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'inventory.warehouses.manage', [
                'tenant_id' => $warehouse->tenant_id,
            ]);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'inventory.warehouses.manage', [
                'tenant_id' => $warehouse->tenant_id,
            ]);
    }
}
