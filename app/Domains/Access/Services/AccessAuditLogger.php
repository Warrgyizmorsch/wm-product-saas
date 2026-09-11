<?php

namespace App\Domains\Access\Services;

use App\Models\Access\AccessAuditLog;
use App\Models\User;

/**
 * Single write path for every access-control mutation (role created, role's
 * permissions changed, a user's roles changed, a user's permission override
 * changed) — called from the service layer method that performs the
 * mutation, never from a controller, so it can't be skipped by calling the
 * service a different way.
 */
class AccessAuditLogger
{
    public function log(
        ?User $actor,
        string $action,
        ?int $tenantId = null,
        ?int $targetUserId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AccessAuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
