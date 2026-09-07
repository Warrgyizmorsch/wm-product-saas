<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\Asset;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.assets.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.assets.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, mixed $asset = null): bool
    {
        $tenantId = $asset instanceof Asset ? $asset->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.assets.update', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function delete(User $user, mixed $asset = null): bool
    {
        $tenantId = $asset instanceof Asset ? $asset->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.assets.delete', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function approve(User $user, mixed $asset = null): bool
    {
        $tenantId = $asset instanceof Asset ? $asset->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.assets.approve', [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Fixed-asset financial capitalization — distinct from hrms.assets.*
     * (physical custody/allocation), gated by the fixed_assets.* permission
     * namespace instead.
     */
    public function capitalize(User $user, mixed $asset = null): bool
    {
        $tenantId = $asset instanceof Asset ? $asset->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.assets.capitalize', [
            'tenant_id' => $tenantId,
        ]);
    }
}
