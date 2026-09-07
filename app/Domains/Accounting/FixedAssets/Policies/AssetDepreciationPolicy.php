<?php

namespace App\Domains\Accounting\FixedAssets\Policies;

use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetDepreciationPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.depreciation.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, mixed $schedule = null): bool
    {
        $tenantId = $schedule instanceof AssetDepreciationSchedule ? $schedule->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.depreciation.view', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function generate(User $user, mixed $schedule = null): bool
    {
        $tenantId = $schedule instanceof AssetDepreciationSchedule ? $schedule->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.depreciation.generate', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function post(User $user, mixed $schedule = null): bool
    {
        $tenantId = $schedule instanceof AssetDepreciationSchedule ? $schedule->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.depreciation.post', [
            'tenant_id' => $tenantId,
        ]);
    }
}
