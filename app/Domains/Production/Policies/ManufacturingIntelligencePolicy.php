<?php

namespace App\Domains\Production\Policies;

use App\Models\User;

class ManufacturingIntelligencePolicy
{
    /**
     * Determine if the user can view dashboards, reports, and analytics.
     */
    public function view(User $user): bool
    {
        return $user->hasProductionPermission('production.intelligence.view');
    }

    /**
     * Determine if the user can manage dashboard preferences and alert rules.
     */
    public function manage(User $user): bool
    {
        return $user->hasProductionPermission('production.intelligence.view');
    }
}
