<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\WorkCenter;
use App\Models\User;

class WorkCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkCenter $workCenter): bool
    {
        return $workCenter->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.work_center.manage');
    }

    public function update(User $user, WorkCenter $workCenter): bool
    {
        return $workCenter->tenant_id === $user->tenant_id
            && $user->hasProductionPermission('production.work_center.manage');
    }

    public function delete(User $user, WorkCenter $workCenter): bool
    {
        return $workCenter->tenant_id === $user->tenant_id
            && $user->hasProductionPermission('production.work_center.manage');
    }
}
