<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\Batch;
use App\Models\User;
use App\Services\Access\AccessService;

class BatchPolicy
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

        return $this->access->allows($user, 'inventory.batches.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function view(User $user, Batch $batch): bool
    {
        $context = [
            'tenant_id'     => $batch->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $batch->warehouse?->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $batch->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.batches.view', $context)
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

        return $this->access->allows($user, 'inventory.batches.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function update(User $user, Batch $batch): bool
    {
        $context = [
            'tenant_id'     => $batch->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $batch->warehouse?->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $batch->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.batches.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }
}
