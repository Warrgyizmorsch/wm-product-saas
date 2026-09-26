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
        if ($this->legacyAdminRoleTextAllows($user, $permissionName, $context)) {
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
            return false;
        }

        $override = $this->matchingOverride($user, $permission->id, $context);

        if ($override !== null) {
            // Deny and allow are deliberately asymmetric. A deny always applies:
            // a revocation that silently stopped working because the caller
            // omitted a context key would be the dangerous direction to fail in.
            // An allow has to earn its scope the same way a role grant does, so
            // an override written as "own" can't hand out tenant-wide access.
            if (! $override->allowed) {
                return false;
            }

            if ($this->scopeMatches($override->scope, $user, $context)) {
                return true;
            }

            // An allow override that doesn't cover this record isn't a denial —
            // fall through and let the user's role grants speak.
        }

        // $roleIds was already resolved above for the platform-admin check and
        // neither $user nor $tenantId has changed since — roleIdsFor() costs two
        // queries now that the legacy role_id is tenant-validated, so it is not
        // worth repeating.
        if ($roleIds->isEmpty()) {
            return false;
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

        return false;
    }

    /**
     * Check if a user holds an explicit scope grant (e.g. 'own') on a permission.
     */
    public function hasExplicitScope(User $user, string $permissionName, string $scope = RolePermission::SCOPE_OWN, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $user->tenant_id;
        $roleIds = $this->roleIdsFor($user, $tenantId);
        if ($roleIds->isEmpty()) {
            return false;
        }

        $permission = Permission::query()->where('name', $permissionName)->first();
        if ($permission === null) {
            return false;
        }

        return RolePermission::query()
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->id)
            ->where('scope', $scope)
            ->exists();
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
     * Modules where permission-table naming lines up with the sidebar's
     * route-based module prefixes, and coverage is broad enough to trust as
     * a real signal (96-97 rows for production/sales/accounting, 53 for crm,
     * 36 for inventory, 11 for projects, 47 for hrms, 34 for purchase — see
     * RbacSeeder). HRMS also has a handful of legacy 'hr.'-prefixed rows
     * (not 'hrms.', the route prefix) and Purchase has separate 'grns.'
     * rows for the Goods Receipt Note workflow; neither affects this list,
     * since a user only needs *a* granted 'hrms.*'/'purchase.*' permission
     * to keep the module visible, and every seeded HR/Purchase role has one.
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
        // Routed through legacyAdminRoleTextAllows() (the one place that already
        // knows the platform-vs-tenant distinction for this legacy text field)
        // rather than comparing $user->role inline here — keeps a single place
        // to fix if that rule ever changes. The permission name passed is a
        // placeholder: legacyAdminRoleTextAllows() only inspects its
        // 'platform.' prefix, never looks up a real Permission row.
        if ($this->legacyAdminRoleTextAllows($user, 'access.roles.manage', ['tenant_id' => $user->tenant_id])) {
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
     * Roles $actor may give a user in $tenantId: system roles plus that
     * tenant's own, and super_admin only when the actor already holds it.
     * Every screen that writes a role (users.role_id or user_roles) must limit
     * its input to this list — otherwise anyone who can edit a user can make
     * them a platform admin.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor, ?int $tenantId): Collection
    {
        $query = Role::query()->where(function ($query) use ($tenantId): void {
            $query->whereNull('tenant_id')
                ->when($tenantId !== null, fn ($q) => $q->orWhere('tenant_id', $tenantId));
        });

        if (! $this->hasRole($actor, 'super_admin')) {
            $query->where('slug', '!=', 'super_admin');
        }

        return $query->orderBy('level')->get();
    }

    /**
     * The legacy users.role text column carries no tenant and is written
     * outside the RBAC screens, so 'admin'/'super_admin' there only makes a
     * platform admin on an account with no tenant. On a tenant-bound account
     * it still opens everything inside that account's own tenant (Production
     * relies on this), but never a platform.* permission or another tenant.
     *
     * @param array<string, mixed> $context
     */
    private function legacyAdminRoleTextAllows(User $user, string $permissionName, array $context): bool
    {
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return false;
        }

        if ($user->tenant_id === null) {
            return true;
        }

        return ! str_starts_with($permissionName, 'platform.')
            && $this->sameValue($context['tenant_id'] ?? $user->tenant_id, $user->tenant_id);
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
        // The legacy users.role_id column carries no tenant of its own — it is
        // implicitly "this user's role in their own home tenant", never a
        // per-tenant assignment. Both legacy branches below must therefore be
        // gated on $tenantId matching the user's OWN tenant before they
        // contribute anything: a system-template role (tenant_id null, e.g.
        // tenant_owner) being "valid for everyone" describes who the ROLE
        // DEFINITION applies to, not which TENANT it may be checked against —
        // without this guard, any user whose role_id/role text resolves to a
        // null-tenant template role would pass a permission check scoped to
        // ANY OTHER tenant's id, a cross-tenant privilege-escalation hole
        // (found while wiring the self-service Subscription page, which calls
        // allows() with an explicit target tenant_id the way TenantPolicy
        // does). The UserRole pivot below is a genuine per-assignment record
        // (its own tenant_id column), so it does not need this guard.
        $roleIds = collect();
        // A user with no home tenant (tenant_id null) is a true platform-level
        // account — its legacy role_id/role-text is allowed to be evaluated
        // against any target tenant (what that grants downstream is still
        // decided by the permission/scope checks and the platform-admin-role
        // check above). A user WITH a home tenant must match the target.
        $isOwnTenant = $user->tenant_id === null || $this->sameValue($tenantId, $user->tenant_id);

        if ($isOwnTenant && $user->role_id !== null) {
            $legacyRoleIsUsable = Role::query()
                ->whereKey($user->role_id)
                ->where(function ($query) use ($tenantId): void {
                    $query->whereNull('tenant_id')
                        ->when($tenantId !== null, fn ($q) => $q->orWhere('tenant_id', $tenantId));
                })
                ->exists();

            if ($legacyRoleIsUsable) {
                $roleIds->push($user->role_id);
            }
        } elseif ($isOwnTenant && filled($user->role) && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            // Older accounts carry only the users.role text (e.g. 'production_engineer').
            // Resolve it to the system role of that slug so its real grants apply,
            // rather than the legacy Production permission map. admin/super_admin
            // text is never resolved here: legacyAdminRoleTextAllows() handles it
            // and keeps it inside the user's own tenant.
            $textRoleId = Role::query()->whereNull('tenant_id')->where('slug', $user->role)->value('id');

            if ($textRoleId !== null) {
                $roleIds->push($textRoleId);
            }
        }

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
     * Whether a grant held at $scope covers the record described by $context.
     *
     * Every scope below TENANT compares the record's value against the user's
     * own — a branch-scoped grant means "records in *this user's* branch", not
     * "records that happen to carry any branch at all". Both sides must be
     * present for a narrow scope to match, so a caller that omits the relevant
     * context key fails closed rather than silently widening the grant.
     *
     * @param array<string, mixed> $context
     */
    private function scopeMatches(string $scope, User $user, array $context): bool
    {
        return match ($scope) {
            RolePermission::SCOPE_PLATFORM => true,
            RolePermission::SCOPE_TENANT => $this->sameValue($context['tenant_id'] ?? null, $user->tenant_id),
            RolePermission::SCOPE_COMPANY => $this->sameValue($context['company_id'] ?? null, $user->company_id),
            RolePermission::SCOPE_BRANCH => $this->sameValue($context['branch_id'] ?? null, $user->branch_id),
            RolePermission::SCOPE_DEPARTMENT => $this->sameValue($context['department_id'] ?? null, $user->department_id),
            RolePermission::SCOPE_OWN => $this->sameValue($context['owner_id'] ?? null, $user->id),
            // SCOPE_TEAM is aliased to the user's department for now: there is
            // no dedicated team_id/Team relation in the schema (HRMS's
            // Employee.reporting_manager_id is a domain-module concern this
            // shared service must not reach into), but users.department_id
            // already exists as a scope anchor. Kept as its own match arm
            // (not merged into SCOPE_DEPARTMENT) so it can be redefined later
            // without touching department-scoped grants.
            RolePermission::SCOPE_TEAM => $this->sameValue($context['department_id'] ?? null, $user->department_id),
            default => false,
        };
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        return $left !== null && $right !== null && (string) $left === (string) $right;
    }
}
