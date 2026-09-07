<?php

namespace App\Domains\Accounting\FixedAssets\Policies;

use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetRevaluationPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.revaluation.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.revaluation.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function approve(User $user, mixed $revaluation = null): bool
    {
        $tenantId = $revaluation instanceof AssetRevaluation ? $revaluation->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.revaluation.approve', [
            'tenant_id' => $tenantId,
        ]);
    }
}
