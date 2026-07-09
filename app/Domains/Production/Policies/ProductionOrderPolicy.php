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
        return !$order->isFrozen()
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function delete(User $user, ProductionOrder $order): bool
    {
        return $order->isDraft()
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function release(User $user, ProductionOrder $order): bool
    {
        return $order->isDraft()
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function issue(User $user, ProductionOrder $order): bool
    {
        return ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function return(User $user, ProductionOrder $order): bool
    {
        return ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function logProgress(User $user, ProductionOrder $order): bool
    {
        return ($order->isReleased() || $order->isInProgress())
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function receiveFg(User $user, ProductionOrder $order): bool
    {
        return ($order->isInProgress() || $order->isReleased())
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function complete(User $user, ProductionOrder $order): bool
    {
        return $order->isInProgress()
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function close(User $user, ProductionOrder $order): bool
    {
        return $order->isCompleted()
            && ($user->hasProductionPermission('production.order.update', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }

    public function cancel(User $user, ProductionOrder $order): bool
    {
        return !$order->isClosed()
            && !$order->isCompleted()
            && !$order->isCancelled()
            && ($user->hasProductionPermission('production.order.cancel', $order->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $order->tenant_id));
    }
}
