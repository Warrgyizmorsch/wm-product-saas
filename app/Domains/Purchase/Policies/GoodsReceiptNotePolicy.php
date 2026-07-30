<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GoodsReceiptNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase.grns.view')
            || $user->hasPermission('inventory.receipts.view');
    }

    public function view(User $user, GoodsReceiptNote $grn): bool
    {
        return $user->hasPermission('purchase.grns.view')
            || $user->hasPermission('inventory.receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase.grns.create')
            || $user->hasPermission('inventory.receipts.create');
    }

    public function approve(User $user, GoodsReceiptNote $grn): bool
    {
        return $user->hasPermission('purchase.grns.approve')
            || $user->hasPermission('inventory.receipts.manage');
    }
}
