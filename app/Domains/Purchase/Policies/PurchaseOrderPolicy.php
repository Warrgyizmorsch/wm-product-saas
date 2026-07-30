<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase.orders.view')
            || $user->hasPermission('purchase.orders.view.tenant')
            || $user->hasPermission('purchase.orders.view.own');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        if ($user->hasPermission('purchase.orders.view') || $user->hasPermission('purchase.orders.view.tenant')) {
            return true;
        }

        if ($user->hasPermission('purchase.orders.view.own')) {
            return $order->created_by === $user->id || $order->purchaser_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase.orders.create')
            || $user->hasPermission('purchase.orders.manage');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        if ($order->status === 'Approved' || $order->status === 'Closed' || $order->status === 'Cancelled') {
            return false;
        }

        return $user->hasPermission('purchase.orders.edit')
            || $user->hasPermission('purchase.orders.manage');
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        if ($order->status !== 'Draft') {
            return false;
        }

        return $user->hasPermission('purchase.orders.delete')
            || $user->hasPermission('purchase.orders.manage');
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchase.orders.approve')
            || $user->hasPermission('purchase.approvals.manage');
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchase.orders.approve')
            || $user->hasPermission('purchase.approvals.manage');
    }
}
