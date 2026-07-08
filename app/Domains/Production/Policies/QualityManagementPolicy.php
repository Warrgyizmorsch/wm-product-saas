<?php

namespace App\Domains\Production\Policies;

use App\Models\User;

class QualityManagementPolicy
{
    /**
     * Determine if the user can view quality checklists, NCRs, CAPAs.
     */
    public function view(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.manage');
    }

    /**
     * Determine if the user can log/create/update quality documents.
     */
    public function manage(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.manage');
    }

    /**
     * Determine if the user can approve/close inspections, NCRs, CAPAs, deviations.
     */
    public function approve(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.approve');
    }
}
