<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Models\User;

class ExternalWorkerAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExternalWorkerAllocation $allocation): bool
    {
        return (int) $allocation->tenant_id === (int) $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.work_center.manage');
    }

    public function update(User $user, ExternalWorkerAllocation $allocation): bool
    {
        return $user->hasProductionPermission('production.work_center.manage', $allocation->tenant_id)
            && (int) $allocation->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, ExternalWorkerAllocation $allocation): bool
    {
        return $user->hasProductionPermission('production.work_center.manage', $allocation->tenant_id)
            && (int) $allocation->tenant_id === (int) $user->tenant_id;
    }
}
