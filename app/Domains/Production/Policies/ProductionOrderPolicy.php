<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.order.create')
            || $user->role === 'admin';
    }

    public function update(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && !$order->isFrozen()
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function delete(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && $order->isDraft()
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function release(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && $order->isDraft()
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function issue(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function return(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function logProgress(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function receiveFg(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && ($order->isInProgress() || $order->isReleased())
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function complete(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && $order->isInProgress()
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function close(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && $order->isCompleted()
            && ($user->hasProductionPermission('production.order.update') || $user->role === 'admin');
    }

    public function cancel(User $user, ProductionOrder $order): bool
    {
        return $order->tenant_id === $user->tenant_id
            && !$order->isClosed()
            && !$order->isCompleted()
            && !$order->isCancelled()
            && ($user->hasProductionPermission('production.order.cancel') || $user->role === 'admin');
    }
}
