<?php

namespace App\Services\Access;

use App\Models\Access\Permission;
use App\Models\Access\RolePermission;
use App\Models\Access\UserPermissionOverride;
use App\Models\Access\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class AccessService
{
    /**
     * @param array{
     *     tenant_id?: int|null,
     *     branch_id?: int|null,
     *     department_id?: int|null,
     *     owner_id?: int|null
     * } $context
     */
    public function allows(User $user, string $permissionName, array $context = []): bool
    {
        $permission = Permission::query()
            ->where('name', $permissionName)
            ->first();

        if ($permission === null) {
            return $this->allowsLegacyProductionPermission($user, $permissionName);
        }

        $tenantId = $context['tenant_id'] ?? $user->tenant_id;
        $override = $this->matchingOverride($user, $permission->id, $context);

        if ($override !== null) {
            return $override->allowed;
        }

        $roleIds = $this->roleIdsFor($user, $tenantId);

        if ($roleIds->isEmpty()) {
            return $this->allowsLegacyProductionPermission($user, $permissionName);
        }

        $grants = RolePermission::query()
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->id)
            ->get();

        foreach ($grants as $grant) {
            if ($this->scopeMatches($grant->scope, $user, $context)) {
                return true;
            }
        }

        return $this->allowsLegacyProductionPermission($user, $permissionName);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function matchingOverride(User $user, int $permissionId, array $context): ?UserPermissionOverride
    {
        $tenantId = $context['tenant_id'] ?? $user->tenant_id;
        $branchId = $context['branch_id'] ?? null;
        $departmentId = $context['department_id'] ?? null;

        return UserPermissionOverride::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permissionId)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')
                    ->when($branchId !== null, fn ($q) => $q->orWhere('branch_id', $branchId));
            })
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id')
                    ->when($departmentId !== null, fn ($q) => $q->orWhere('department_id', $departmentId));
            })
            ->orderBy('allowed')
            ->first();
    }

    /**
     * @return Collection<int, int>
     */
    private function roleIdsFor(User $user, ?int $tenantId): Collection
    {
        $roleIds = collect([$user->role_id])->filter();

        $assignedRoleIds = UserRole::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id')
                    ->when($tenantId !== null, fn ($q) => $q->orWhere('tenant_id', $tenantId));
            })
            ->pluck('role_id');

        return $roleIds
            ->merge($assignedRoleIds)
            ->unique()
            ->values();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function scopeMatches(string $scope, User $user, array $context): bool
    {
        return match ($scope) {
            RolePermission::SCOPE_PLATFORM => true,
            RolePermission::SCOPE_TENANT => $this->sameValue($context['tenant_id'] ?? null, $user->tenant_id),
            RolePermission::SCOPE_BRANCH => isset($context['branch_id']),
            RolePermission::SCOPE_DEPARTMENT => isset($context['department_id']),
            RolePermission::SCOPE_OWN => $this->sameValue($context['owner_id'] ?? null, $user->id),
            RolePermission::SCOPE_TEAM => $this->sameValue($context['owner_id'] ?? null, $user->id),
            default => false,
        };
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        return $left !== null && $right !== null && (string) $left === (string) $right;
    }

    private function allowsLegacyProductionPermission(User $user, string $permissionName): bool
    {
        $permissionMap = config('production.permissions', []);
        $allowedRoles = $permissionMap[$permissionName] ?? [];

        if (empty($allowedRoles)) {
            return false;
        }

        $legacyRole = $user->role ?? null;

        return $legacyRole !== null && in_array($legacyRole, $allowedRoles, true);
    }
}
