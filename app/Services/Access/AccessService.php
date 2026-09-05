<?php

namespace App\Services\Access;

use App\Models\Access\Permission;
use App\Models\Access\Role;
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
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     department_id?: int|null,
     *     owner_id?: int|null
     * } $context
     */
    public function allows(User $user, string $permissionName, array $context = []): bool
    {
        // ── Platform Admin Override ──
        // Only truly platform-level roles bypass every check, including
        // platform-only permissions (e.g. platform.tenants.manage). tenant_owner
        // and company_admin are intentionally excluded here: RbacSeeder grants
        // them every permission at SCOPE_TENANT (not SCOPE_PLATFORM), so they
        // still get full access within their own tenant via the normal grant
        // check below — this override must not let them additionally pass
        // platform-wide checks that have no tenant_id in context, which would
        // let a tenant owner browse or switch into another tenant entirely
        // (see TenantPolicy's own comment on this exact risk).
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            return true;
        }

        $tenantId = $context['tenant_id'] ?? $user->tenant_id;
        $roleIds = $this->roleIdsFor($user, $tenantId);
        if ($roleIds->isNotEmpty()) {
            $isPlatformAdminRole = Role::query()
                ->whereIn('id', $roleIds)
                ->whereIn('slug', ['super_admin', 'admin'])
                ->exists();
            if ($isPlatformAdminRole) {
                return true;
            }
        }

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
     * Effective role IDs for a user in a tenant context — the same
     * legacy-role_id + UserRole merge that allows() uses internally,
     * exposed so admin screens can show which concrete roles are actually
     * driving a user's grants without duplicating the merge logic.
     *
     * @return Collection<int, int>
     */
    public function effectiveRoleIds(User $user, ?int $tenantId = null): Collection
    {
        return $this->roleIdsFor($user, $tenantId ?? $user->tenant_id);
    }

    /**
     * Modules where permission-table naming actually lines up with the
     * sidebar's route-based module prefixes, and coverage is broad enough to
     * trust as a real signal (96-97 rows for production/sales/accounting, 53
     * for crm, 36 for inventory, 11 for projects). HRMS and Purchase are
     * deliberately excluded: HRMS has only 3 permission rows total, all
     * prefixed 'hr.' (not 'hrms.', the route prefix), and Purchase has none
     * at all (its only related rows are prefixed 'grns.' for the separate
     * Goods Receipt Note workflow) — filtering on either would hide the
     * entire module for users who legitimately have access through
     * mechanisms other than the Permission table (legacy role checks,
     * hasHrPermission's narrow gate, etc.), which is worse than not
     * filtering at all. Revisit this list once those two modules get proper
     * per-entity permissions seeded.
     */
    private const ROLE_FILTERABLE_MODULES = ['crm', 'sales', 'inventory', 'accounting', 'production', 'projects'];

    /**
     * Distinct module prefixes (the leading segment of each granted
     * permission's `module.entity.action.scope` name — e.g. 'crm', 'sales')
     * this user holds ANY permission for, used to filter the sidebar down to
     * modules the user's role actually has access to. Only ever restricts
     * modules listed in ROLE_FILTERABLE_MODULES — every other gated module
     * (hrms, purchase) always passes this filter regardless of role, since
     * there's no reliable per-role signal to check for them yet. Independent
     * of, and layered on top of, tenant-level plan gating (see
     * tenant_allowed_modules()) — a module can be in the tenant's plan but
     * still hidden here if this user's role has no grants in it, and vice
     * versa the plan filter still wins even if the role would otherwise
     * allow it.
     *
     * @return list<string>|null null means unrestricted (platform admin — sees
     *     every module regardless of role grants)
     */
    public function allowedModulesFor(User $user): ?array
    {
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            return null;
        }

        $roleIds = $this->roleIdsFor($user, $user->tenant_id);

        if ($roleIds->isNotEmpty()) {
            $isPlatformAdminRole = Role::query()
                ->whereIn('id', $roleIds)
                ->whereIn('slug', ['super_admin', 'admin'])
                ->exists();

            if ($isPlatformAdminRole) {
                return null;
            }
        }

        $modules = collect();

        if ($roleIds->isNotEmpty()) {
            $permissionNames = RolePermission::query()
                ->whereIn('role_id', $roleIds)
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->pluck('permissions.name');

            $modules = $modules->merge($permissionNames->map(fn (string $name) => explode('.', $name)[0]));
        }

        // Legacy Production permission map (config('production.permissions'),
        // permission-name => [allowed legacy role slugs]) isn't expressed as
        // Permission/RolePermission rows at all, so a user relying on it would
        // otherwise show zero modules — check it the same way
        // allowsLegacyProductionPermission() does, for the 'production' module only.
        $legacyRole = $user->role ?: ($user->primaryRole->slug ?? null);

        if ($legacyRole !== null) {
            foreach (config('production.permissions', []) as $allowedRoles) {
                if (in_array($legacyRole, $allowedRoles, true)) {
                    $modules->push('production');
                    break;
                }
            }
        }

        // Only actually restrict modules we trust the Permission table's
        // coverage for; every other gated module (hrms, purchase today)
        // is unioned in unconditionally so it never gets hidden by this filter.
        $grantedFilterable = $modules->intersect(self::ROLE_FILTERABLE_MODULES);
        $alwaysAllowed = array_diff(
            \App\Http\Middleware\EnsureTenantModuleAccess::GATED_MODULES,
            self::ROLE_FILTERABLE_MODULES,
        );

        return $grantedFilterable->merge($alwaysAllowed)->unique()->values()->all();
    }

    /**
     * Whether $user currently holds the role with this slug, in the given
     * tenant context. This is an identity check, not a scoped-permission
     * check — allows() has no way to express "is the *target* Role being
     * edited a system role", so callers that need that (e.g. restricting
     * who may edit a system role's permissions) check role membership here
     * directly instead.
     */
    public function hasRole(User $user, string $roleSlug, ?int $tenantId = null): bool
    {
        $roleIds = $this->roleIdsFor($user, $tenantId ?? $user->tenant_id);

        if ($roleIds->isEmpty()) {
            return false;
        }

        return Role::query()->whereIn('id', $roleIds)->where('slug', $roleSlug)->exists();
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
            RolePermission::SCOPE_COMPANY => isset($context['company_id']),
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

        $legacyRole = $user->role ?: ($user->primaryRole->slug ?? null);

        return $legacyRole !== null && in_array($legacyRole, $allowedRoles, true);
    }
}
