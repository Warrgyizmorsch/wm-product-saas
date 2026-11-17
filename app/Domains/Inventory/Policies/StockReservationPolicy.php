<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockReservation;
use App\Models\User;
use App\Services\Access\AccessService;

class StockReservationPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.reservations.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function view(User $user, StockReservation $reservation): bool
    {
        $context = [
            'tenant_id'     => $reservation->tenant_id,
            'owner_id'      => $reservation->created_by ?? $user->id,
            'branch_id'     => $reservation->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $reservation->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.reservations.view', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function create(User $user): bool
    {
        $context = [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.reservations.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }

    public function update(User $user, StockReservation $reservation): bool
    {
        $context = [
            'tenant_id'     => $reservation->tenant_id,
            'owner_id'      => $reservation->created_by ?? $user->id,
            'branch_id'     => $reservation->branch_id ?? $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $reservation->company_id ?? $user->company_id,
        ];

        return $this->access->allows($user, 'inventory.reservations.manage', $context)
            || $this->access->allows($user, 'inventory.warehouses.manage', $context);
    }
}
