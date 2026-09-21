<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockAdjustment;
use App\Models\User;
use App\Services\Access\AccessService;

class StockAdjustmentPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $adjustment->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $adjustment->tenant_id,
        ]);
    }

    public function approve(User $user, StockAdjustment $adjustment): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $adjustment->tenant_id,
        ]);
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $this->access->allows($user, 'inventory.products.delete', [
            'tenant_id' => $adjustment->tenant_id,
        ]);
    }
}
