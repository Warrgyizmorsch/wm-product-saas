<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\WorkCenterSection;
use App\Models\User;

class WorkCenterSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkCenterSection $section): bool
    {
        return $section->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.work_center.manage');
    }

    public function update(User $user, WorkCenterSection $section): bool
    {
        return $user->hasProductionPermission('production.work_center.manage', $section->tenant_id);
    }

    public function delete(User $user, WorkCenterSection $section): bool
    {
        if (!$user->hasProductionPermission('production.work_center.manage', $section->tenant_id)) {
            return false;
        }

        return !$section->machines()->exists();
    }
}
