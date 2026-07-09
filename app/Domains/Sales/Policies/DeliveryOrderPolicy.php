<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\DeliveryOrder;
use App\Models\User;
use App\Services\Access\AccessService;

class DeliveryOrderPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.deliveries.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, DeliveryOrder $delivery): bool
    {
        return $this->access->allows($user, 'sales.deliveries.view', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.deliveries.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function ship(User $user, DeliveryOrder $delivery): bool
    {
        return $this->access->allows($user, 'sales.deliveries.ship', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }

    public function cancel(User $user, DeliveryOrder $delivery): bool
    {
        return $this->access->allows($user, 'sales.deliveries.cancel', [
            'tenant_id' => $delivery->tenant_id,
        ]);
    }
}
