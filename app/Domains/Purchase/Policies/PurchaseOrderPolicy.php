<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Access\AccessService;

class PurchaseOrderPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'purchase.orders.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->access->allows($user, 'purchase.orders.view', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->created_by,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'purchase.orders.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        if (in_array($order->status, ['Approved', 'Closed', 'Cancelled'], true)) {
            return false;
        }

        return $this->access->allows($user, 'purchase.orders.edit', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->created_by,
        ]);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        if ($order->status !== 'Draft') {
            return false;
        }

        return $this->access->allows($user, 'purchase.orders.delete', [
            'tenant_id' => $order->tenant_id,
            'owner_id' => $order->created_by,
        ]);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $this->access->allows($user, 'purchase.orders.approve', [
            'tenant_id' => $order->tenant_id,
        ]) || $this->access->allows($user, 'purchase.approvals.manage', [
            'tenant_id' => $order->tenant_id,
        ]);
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $this->approve($user, $order);
    }
}
