<?php

namespace App\Models\Concerns;

use App\Services\Access\AccessService;

/**
 * Authorization bridge used by existing Production policies.
 *
 * Keep policy code stable while RBAC internals evolve behind AccessService.
 */
trait HasProductionPermissions
{
    /**
     * Check if the user has a specific production permission.
     *
     * @param string $permission e.g. 'production.routing.approve'
     */
    public function hasProductionPermission(string $permission): bool
    {
        return app(AccessService::class)->allows($this, $permission, [
            'tenant_id' => $this->tenant_id,
        ]);
    }

    /**
     * Check if the user can manage production master data.
     */
    public function canManageProductionMasterData(): bool
    {
        return $this->hasProductionPermission('production.work_center.manage');
    }

    /**
     * Check if the user can create or update routings.
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
