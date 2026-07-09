<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionBom;
use App\Models\User;

class ProductionBomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionBom $bom): bool
    {
        return $bom->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.bom.create');
    }

    public function update(User $user, ProductionBom $bom): bool
    {
        return $bom->status === 'draft'
            && $user->hasProductionPermission('production.bom.update', $bom->tenant_id);
    }

    public function delete(User $user, ProductionBom $bom): bool
    {
        return $bom->status === 'draft'
            && $user->hasProductionPermission('production.bom.update', $bom->tenant_id);
    }

    public function approve(User $user, ProductionBom $bom): bool
    {
        return $bom->status === 'pending_approval'
            && $user->hasProductionPermission('production.bom.approve', $bom->tenant_id);
    }

    public function cancel(User $user, ProductionBom $bom): bool
    {
        return $user->hasProductionPermission('production.bom.approve', $bom->tenant_id);
    }
}
