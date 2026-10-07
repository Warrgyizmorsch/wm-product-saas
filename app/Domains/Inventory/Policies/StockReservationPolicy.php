<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockReservation;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockReservationPolicy
{
    use HandlesAuthorization;

    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, StockReservation $reservation): bool
    {
        return $this->access->allows($user, 'inventory.products.view', [
            'tenant_id' => $reservation->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, StockReservation $reservation): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $reservation->tenant_id,
        ]);
    }

    public function delete(User $user, StockReservation $reservation): bool
    {
        return $this->access->allows($user, 'inventory.products.update', [
            'tenant_id' => $reservation->tenant_id,
        ]);
    }
}

