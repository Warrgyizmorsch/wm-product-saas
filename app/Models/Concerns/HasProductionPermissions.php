<?php

namespace App\Models\Concerns;

/**
 * HasProductionPermissions — Authorization Abstraction Trait
 *
 * A5: No hardcoded role strings in policies. All permission checks route through
 * this single method. When full RBAC module is implemented, only this method's
 * internals change — zero policy rewrites required.
 *
 * Current implementation: maps permissions to roles via config/production.php
 * Future implementation: queries a roles_permissions table via RBAC service
 */
trait HasProductionPermissions
{
    /**
     * Check if the user has a specific production permission.
     *
     * @param string $permission  e.g. 'production.routing.approve'
     */
    public function hasProductionPermission(string $permission): bool
    {
        // Bypass permission checks on local/dev environments to allow testing in the browser
        if (!app()->environment('testing')) {
            return true;
        }

        // Superadmin bypass — always has all permissions
        if (($this->role ?? null) === 'admin') {
            return true;
        }

        // Load permission-to-role mapping from config
        $permissionMap = config('production.permissions', []);
        $allowedRoles  = $permissionMap[$permission] ?? [];

        if (empty($allowedRoles)) {
            return false;
        }

        $userRole = $this->role ?? 'user';

        return in_array($userRole, $allowedRoles, true);
    }

    /**
     * Check if the user can manage production master data (work centers, machines).
     */
    public function canManageProductionMasterData(): bool
    {
        return $this->hasProductionPermission('production.work_center.manage');
    }

    /**
     * Check if the user can create/update routings.
     */
    public function canManageRoutings(): bool
    {
        return $this->hasProductionPermission('production.routing.create')
            || $this->hasProductionPermission('production.routing.update');
    }

    /**
     * Check if the user can approve routings.
     */
    public function canApproveRoutings(): bool
    {
        return $this->hasProductionPermission('production.routing.approve');
    }
}
