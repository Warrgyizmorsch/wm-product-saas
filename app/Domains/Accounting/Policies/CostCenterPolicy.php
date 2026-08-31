<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\CostCenter;
use App\Models\User;
use App\Services\Access\AccessService;

class CostCenterPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.cost_centers.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->access->allows($user, 'accounting.cost_centers.view', [
            'tenant_id' => $costCenter->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.cost_centers.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $this->access->allows($user, 'accounting.cost_centers.update', [
            'tenant_id' => $costCenter->tenant_id,
        ]);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $this->access->allows($user, 'accounting.cost_centers.delete', [
            'tenant_id' => $costCenter->tenant_id,
        ]);
    }
}
