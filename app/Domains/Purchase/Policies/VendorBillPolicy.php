<?php

namespace App\Domains\Purchase\Policies;

use App\Domains\Purchase\Models\VendorBill;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendorBillPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase.bills.view')
            || $user->hasPermission('accounting.bills.view');
    }

    public function view(User $user, VendorBill $bill): bool
    {
        return $user->hasPermission('purchase.bills.view')
            || $user->hasPermission('accounting.bills.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase.bills.create')
            || $user->hasPermission('accounting.bills.create');
    }

    public function update(User $user, VendorBill $bill): bool
    {
        return $user->hasPermission('purchase.bills.edit')
            || $user->hasPermission('accounting.bills.edit');
    }
}
