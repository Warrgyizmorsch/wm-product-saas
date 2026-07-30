<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase.requisitions.view')
            || $user->hasPermission('purchase.requisitions.view.tenant')
            || $user->hasPermission('purchase.requisitions.view.own');
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        if ($user->hasPermission('purchase.requisitions.view') || $user->hasPermission('purchase.requisitions.view.tenant')) {
            return true;
        }

        if ($user->hasPermission('purchase.requisitions.view.own')) {
            return $requisition->requested_by === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase.requisitions.create')
            || $user->hasPermission('purchase.requisitions.manage');
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $user->hasPermission('purchase.requisitions.edit')
            || $user->hasPermission('purchase.requisitions.manage');
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== 'Draft') {
            return false;
        }

        return $user->hasPermission('purchase.requisitions.delete')
            || $user->hasPermission('purchase.requisitions.manage');
    }

    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->hasPermission('purchase.requisitions.approve')
            || $user->hasPermission('purchase.approvals.manage');
    }
}
