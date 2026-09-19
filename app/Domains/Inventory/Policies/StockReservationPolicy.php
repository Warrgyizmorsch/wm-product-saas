<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockReservation;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockReservationPolicy
{
    use HandlesAuthorization;

    public function __construct(private readonly ?AccessService $access = null)
    {
    }

    public function viewAny(User $user): bool
    {
        if ($this->access) {
            return $this->access->allows($user, 'inventory.products.view', [
                'tenant_id' => $user->tenant_id,
            ]);
        }
        return $user->hasPermission('inventory.reservations.view')
            || $user->hasPermission('inventory.stocks.view');
    }

    public function view(User $user, StockReservation $reservation): bool
    {
        if ($this->access) {
            return $this->access->allows($user, 'inventory.products.view', [
                'tenant_id' => $reservation->tenant_id,
            ]);
        }
        return true;
    }

    public function create(User $user): bool
    {
        if ($this->access) {
            return $this->access->allows($user, 'inventory.products.update', [
                'tenant_id' => $user->tenant_id,
            ]);
        }
        return $user->hasPermission('inventory.reservations.create')
            || $user->hasPermission('sales.orders.confirm');
    }

    public function update(User $user, StockReservation $reservation): bool
    {
        if ($this->access) {
            return $this->access->allows($user, 'inventory.products.update', [
                'tenant_id' => $reservation->tenant_id,
            ]);
        }
        return true;
    }

    public function delete(User $user, StockReservation $reservation): bool
    {
        if ($this->access) {
            return $this->access->allows($user, 'inventory.products.update', [
                'tenant_id' => $reservation->tenant_id,
            ]);
        }
        return $user->hasPermission('inventory.reservations.cancel')
            || $user->hasPermission('inventory.stocks.manage');
    }
}
