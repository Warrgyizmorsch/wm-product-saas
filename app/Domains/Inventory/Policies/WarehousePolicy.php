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
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id'     => $warehouse->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $warehouse->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $warehouse->company_id ?? $user->company_id,
        ]);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->access->allows($user, 'inventory.warehouses.manage', [
            'tenant_id'     => $warehouse->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $warehouse->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $warehouse->company_id ?? $user->company_id,
        ]);
    }
}
