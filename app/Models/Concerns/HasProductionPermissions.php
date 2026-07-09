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
     * @param int|null $targetTenantId tenant_id of the record being acted on, if any.
     *        Defaults to the user's own tenant (safe for create/viewAny checks with no
     *        target record). Passing the record's actual tenant_id lets AccessService
     *        validate tenant-scoped grants against the *record*, not the caller, and
     *        lets platform-scoped grants bypass tenant checks entirely.
     */
    public function hasProductionPermission(string $permission, ?int $targetTenantId = null): bool
    {
        return app(AccessService::class)->allows($this, $permission, [
            'tenant_id' => $targetTenantId ?? $this->tenant_id,
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
