<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\Product;
use App\Models\User;
use App\Services\Access\AccessService;

class ProductPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $product->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $product->tenant_id,
        ]);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->access->allows($user, 'inventory.products.delete', [
            'tenant_id' => $product->tenant_id,
        ]);
    }
}
