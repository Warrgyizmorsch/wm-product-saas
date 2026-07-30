<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockReservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockReservationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.reservations.view')
            || $user->hasPermission('inventory.stocks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.reservations.create')
            || $user->hasPermission('sales.orders.confirm');
    }

    public function delete(User $user, StockReservation $reservation): bool
    {
        return $user->hasPermission('inventory.reservations.cancel')
            || $user->hasPermission('inventory.stocks.manage');
    }
}
