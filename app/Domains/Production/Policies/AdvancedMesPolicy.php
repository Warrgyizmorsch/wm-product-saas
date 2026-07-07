<?php

namespace App\Domains\Production\Policies;

use App\Models\User;
use App\Domains\Production\Models\ProductionOperatorAssignment;

class AdvancedMesPolicy
{
    /**
     * Determine if the user can assign operators or manage batches/serials.
     */
    public function manage(User $user): bool
    {
        return in_array($user->role, ['admin', 'production_manager', 'production_supervisor']);
    }

    /**
     * Determine if the user can execute work (Operators and above).
     */
    public function execute(User $user): bool
    {
        return in_array($user->role, ['admin', 'production_manager', 'production_supervisor', 'production_operator', 'operator']);
    }

    /**
     * Determine if the operator can manage/accept/complete their own assignment.
     */
    public function manageOwnAssignment(User $user, ProductionOperatorAssignment $assignment): bool
    {
        return $assignment->user_id === $user->id || $this->manage($user);
    }
}
