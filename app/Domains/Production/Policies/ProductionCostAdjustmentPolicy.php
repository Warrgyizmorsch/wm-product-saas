<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Models\User;
use App\Services\Access\AccessService;

class ProductionCostAdjustmentPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'production.cost_adjustment.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, ProductionCostAdjustment $adjustment): bool
    {
        return $adjustment->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'production.cost_adjustment.create', [
                'tenant_id' => $adjustment->tenant_id,
            ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'production.cost_adjustment.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, ProductionCostAdjustment $adjustment): bool
    {
        return $adjustment->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'production.cost_adjustment.update', [
                'tenant_id' => $adjustment->tenant_id,
            ]);
    }

    public function delete(User $user, ProductionCostAdjustment $adjustment): bool
    {
        return $adjustment->tenant_id === $user->tenant_id
            && $this->access->allows($user, 'production.cost_adjustment.update', [
                'tenant_id' => $adjustment->tenant_id,
            ]);
    }
}
