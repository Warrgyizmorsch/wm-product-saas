<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\SerialNumber;
use App\Models\User;
use App\Services\Access\AccessService;

class SerialNumberPolicy
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

        return $this->access->allows($user, 'inventory.serials.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function view(User $user, SerialNumber $serial): bool
    {
        $context = [
            'tenant_id'     => $serial->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $serial->warehouse?->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $serial->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.serials.view', $context)
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

        return $this->access->allows($user, 'inventory.serials.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function update(User $user, SerialNumber $serial): bool
    {
        $context = [
            'tenant_id'     => $serial->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $serial->warehouse?->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $serial->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.serials.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }
}
