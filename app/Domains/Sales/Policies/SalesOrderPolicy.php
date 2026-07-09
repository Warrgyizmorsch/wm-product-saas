<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\User;
use App\Services\Access\AccessService;

class SalesOrderPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.orders.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $this->access->allows($user, 'sales.orders.view', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->sales_person_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.orders.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, SalesOrder $order): bool
    {
        return $this->access->allows($user, 'sales.orders.update', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->sales_person_id,
        ]);
    }

    public function delete(User $user, SalesOrder $order): bool
    {
        return $this->access->allows($user, 'sales.orders.delete', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->sales_person_id,
        ]);
    }

    public function confirm(User $user, SalesOrder $order): bool
    {
        return $this->access->allows($user, 'sales.orders.confirm', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->sales_person_id,
        ]);
    }

    public function cancel(User $user, SalesOrder $order): bool
    {
        return $this->access->allows($user, 'sales.orders.cancel', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->sales_person_id,
        ]);
    }
}
