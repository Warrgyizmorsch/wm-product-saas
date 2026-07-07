<?php

namespace App\Domains\Access\Services;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    /**
     * Roles visible to a tenant: global system roles plus this tenant's
     * own custom roles. Role has no BelongsToTenant scope, so this filter
     * is the only thing standing between a tenant and every other
     * tenant's custom roles.
     */
    public function visibleTo(int $tenantId): Collection
    {
        return Role::query()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->withCount('rolePermissions')
            ->orderBy('level')
            ->get();
    }

    public function create(int $tenantId, array $data): Role
    {
        return Role::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'level' => ($data['level'] ?? null) ?: 100,
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);
    }

    /**
     * @return list<array{permission: Permission, grants: array<string, bool>}>
     */
    public function matrixFor(Role $role): array
    {
        $existing = RolePermission::query()
            ->where('role_id', $role->id)
            ->get()
            ->groupBy('permission_id');

        return Permission::query()
            ->orderBy('module')
            ->orderBy('entity')
            ->orderBy('action')
            ->get()
            ->map(function (Permission $permission) use ($existing) {
                $grantedScopes = $existing->get($permission->id, collect())->pluck('scope')->all();

                $grants = [];
                foreach (RolePermission::SCOPES as $scope) {
                    $grants[$scope] = in_array($scope, $grantedScopes, true);
                }

                return ['permission' => $permission, 'grants' => $grants];
            })
            ->all();
    }

    /**
     * Full diff against the desired grant set — a checkbox left unchecked
     * sends no key at all, so the only reliable source of truth is to
     * iterate every permission x scope combination and compare, rather
     * than trust which keys happened to arrive in the request.
     *
     * @param array<int|string, array<string, mixed>> $grants keyed by permission_id => [scope => bool]
     */
    public function syncPermissions(Role $role, array $grants): void
    {
        DB::transaction(function () use ($role, $grants) {
            $existing = RolePermission::query()
                ->where('role_id', $role->id)
                ->get()
                ->keyBy(fn (RolePermission $rp) => "{$rp->permission_id}:{$rp->scope}");

            $desired = [];

            foreach (Permission::query()->pluck('id') as $permissionId) {
                foreach (RolePermission::SCOPES as $scope) {
                    // Custom tenant roles can never hold platform scope,
                    // regardless of who is saving — enforced here, not
                    // just hidden in the view.
                    if ($scope === RolePermission::SCOPE_PLATFORM && $role->tenant_id !== null) {
                        continue;
                    }

                    if ((bool) data_get($grants, "{$permissionId}.{$scope}")) {
                        $desired["{$permissionId}:{$scope}"] = [$permissionId, $scope];
                    }
                }
            }

            foreach ($desired as $key => [$permissionId, $scope]) {
                if (! isset($existing[$key])) {
                    RolePermission::query()->create([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'scope' => $scope,
                    ]);
                }
            }

            foreach ($existing as $key => $row) {
                if (! isset($desired[$key])) {
                    $row->delete();
                }
            }
        });
    }
}
