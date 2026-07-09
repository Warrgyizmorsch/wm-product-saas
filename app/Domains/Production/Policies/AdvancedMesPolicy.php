<?php

namespace App\Domains\Production\Policies;

use App\Models\User;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Services\Access\AccessService;

class AdvancedMesPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    /**
     * Determine if the user can assign operators or manage batches/serials.
     */
    public function manage(User $user, ?int $targetTenantId = null): bool
    {
        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $targetTenantId ?? $user->tenant_id,
        ]);
    }

    /**
     * Determine if the user can execute work (Operators and above).
     */
    public function execute(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the operator can manage/accept/complete their own assignment.
     */
    public function manageOwnAssignment(User $user, ProductionOperatorAssignment $assignment): bool
    {
        return ($assignment->user_id === $user->id && $assignment->tenant_id === $user->tenant_id)
            || $this->manage($user, $assignment->tenant_id);
    }
}
