<?php

namespace App\Domains\Accounting\FixedAssets\Policies;

use App\Domains\HRMS\Models\AssetCategory;
use App\Models\User;
use App\Services\Access\AccessService;

class AssetCategoryPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.categories.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'fixed_assets.categories.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, mixed $category = null): bool
    {
        $tenantId = $category instanceof AssetCategory ? $category->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.categories.edit', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function delete(User $user, mixed $category = null): bool
    {
        $tenantId = $category instanceof AssetCategory ? $category->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'fixed_assets.categories.delete', [
            'tenant_id' => $tenantId,
        ]);
    }
}
