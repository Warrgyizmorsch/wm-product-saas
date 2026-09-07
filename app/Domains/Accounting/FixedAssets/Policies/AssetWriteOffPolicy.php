<?php

namespace App\Domains\Accounting\FixedAssets\Policies;

use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetWriteOffPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.writeoff.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.writeoff.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function approve(User $user, mixed $writeOff = null): bool
    {
        $tenantId = $writeOff instanceof AssetWriteOff ? $writeOff->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.writeoff.approve', [
            'tenant_id' => $tenantId,
        ]);
    }
}
