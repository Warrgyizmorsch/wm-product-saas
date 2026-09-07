<?php

namespace App\Domains\Accounting\FixedAssets\Policies;

use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetDisposalPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.disposal.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, mixed $disposal = null): bool
    {
        $tenantId = $disposal instanceof AssetDisposal ? $disposal->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.disposal.view', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.disposal.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function approve(User $user, mixed $disposal = null): bool
    {
        $tenantId = $disposal instanceof AssetDisposal ? $disposal->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.disposal.approve', [
            'tenant_id' => $tenantId,
        ]);
    }
}
