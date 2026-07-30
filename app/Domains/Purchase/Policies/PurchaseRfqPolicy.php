<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\PurchaseRfq;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRfqPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase.rfqs.view')
            || $user->hasPermission('purchase.rfqs.manage');
    }

    public function view(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchase.rfqs.view')
            || $user->hasPermission('purchase.rfqs.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase.rfqs.create')
            || $user->hasPermission('purchase.rfqs.manage');
    }

    public function update(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchase.rfqs.edit')
            || $user->hasPermission('purchase.rfqs.manage');
    }

    public function delete(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchase.rfqs.delete')
            || $user->hasPermission('purchase.rfqs.manage');
    }
}
