<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockTransfer;
use App\Models\User;
use App\Services\Access\AccessService;

class StockTransferPolicy
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

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $transfer->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $transfer->tenant_id,
        ]);
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $transfer->tenant_id,
        ]);
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $transfer->tenant_id,
        ]);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->access->allows($user, 'inventory.products.delete', [
            'tenant_id' => $transfer->tenant_id,
        ]);
    }
}
