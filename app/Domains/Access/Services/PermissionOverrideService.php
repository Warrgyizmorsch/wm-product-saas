<?php

namespace App\Domains\Access\Services;

use App\Models\Access\Permission;
use App\Models\Access\RolePermission;
use App\Models\Access\UserPermissionOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionOverrideService
{
    public const STATE_INHERIT = 'inherit';
    public const STATE_ALLOW = 'allow';
    public const STATE_DENY = 'deny';

    /**
     * Platform-module permissions are never overridable from this screen.
     * AccessService::matchingOverride() returns the override's `allowed`
     * directly without running scopeMatches(), so a tenant-scoped override
     * row would satisfy a platform-wide check that no tenant-scoped *role*
     * grant can — letting a tenant admin hand themselves (or anyone else)
     * cross-tenant platform access. See TenantPolicy::viewAny()'s comment on
     * this exact risk.
     */
    private const NON_OVERRIDABLE_MODULES = ['platform'];

    public function __construct(
        private readonly AccessAuditLogger $auditLogger,
    ) {
    }

    /**
     * Every permission plus this user's current override state for it
     * (tenant-wide overrides only — branch/department-scoped overrides
     * aren't exposed through this screen, they can still be set directly
     * if a future workflow needs that granularity).
     *
     * @return list<array{permission: Permission, state: string, reason: ?string}>
     */
    public function matrixFor(User $user): array
    {
        $existing = UserPermissionOverride::query()
            ->where('user_id', $user->id)
            ->whereNull('branch_id')
            ->whereNull('department_id')
            ->where(function ($query) use ($user): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $user->tenant_id);
            })
            ->get()
            ->keyBy('permission_id');

        return Permission::query()
            ->whereNotIn('module', self::NON_OVERRIDABLE_MODULES)
            ->orderBy('module')
            ->orderBy('entity')
            ->orderBy('action')
            ->get()
            ->map(function (Permission $permission) use ($existing) {
                $override = $existing->get($permission->id);

                return [
                    'permission' => $permission,
                    'state' => $override === null
                        ? self::STATE_INHERIT
                        : ($override->allowed ? self::STATE_ALLOW : self::STATE_DENY),
                    'reason' => $override?->reason,
                ];
            })
            ->all();
    }

    /**
     * @param array<int|string, array{state?: string, reason?: string}> $overrides keyed by permission_id
     */
    public function syncOverrides(User $actor, User $user, array $overrides): void
    {
        DB::transaction(function () use ($actor, $user, $overrides): void {
            $existing = UserPermissionOverride::query()
                ->where('user_id', $user->id)
                ->whereNull('branch_id')
                ->whereNull('department_id')
                ->get()
                ->keyBy('permission_id');

            // Platform permissions are filtered out here too, not just in
            // matrixFor() — a hand-crafted POST must not be able to set one.
            $overridable = Permission::query()
                ->whereNotIn('module', self::NON_OVERRIDABLE_MODULES)
                ->pluck('id');

            foreach ($overridable as $permissionId) {
                $state = data_get($overrides, "{$permissionId}.state", self::STATE_INHERIT);
                $reason = trim((string) data_get($overrides, "{$permissionId}.reason", '')) ?: null;
                $row = $existing->get($permissionId);
                $previousState = $row === null
                    ? self::STATE_INHERIT
                    : ($row->allowed ? self::STATE_ALLOW : self::STATE_DENY);

                if ($state === self::STATE_INHERIT) {
                    if ($row !== null) {
                        $row->delete();
                        $this->logChange($actor, $user, (int) $permissionId, $previousState, $state);
                    }

                    continue;
                }

                UserPermissionOverride::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'permission_id' => $permissionId,
                        'tenant_id' => $user->tenant_id,
                        'branch_id' => null,
                        'department_id' => null,
                    ],
                    [
                        'scope' => RolePermission::SCOPE_TENANT,
                        'allowed' => $state === self::STATE_ALLOW,
                        'reason' => $reason,
                    ],
                );

                if ($previousState !== $state) {
                    $this->logChange($actor, $user, (int) $permissionId, $previousState, $state);
                }
            }
        });
    }

    private function logChange(User $actor, User $user, int $permissionId, string $before, string $after): void
    {
        $this->auditLogger->log(
            actor: $actor,
            action: 'user.permission_override.updated',
            tenantId: $user->tenant_id,
            targetUserId: $user->id,
            subjectType: 'permission',
            subjectId: $permissionId,
            before: ['state' => $before],
            after: ['state' => $after],
        );
    }
}
