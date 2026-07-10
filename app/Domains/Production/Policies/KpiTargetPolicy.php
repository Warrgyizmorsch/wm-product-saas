<?php

namespace App\Domains\Production\Policies;

use App\Models\User;

class KpiTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasProductionPermission('production.intelligence.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasProductionPermission('production.intelligence.view');
    }
}
