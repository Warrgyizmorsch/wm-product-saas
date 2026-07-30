<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\StockTransaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockTransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.stocks.view')
            || $user->hasPermission('inventory.transactions.view');
    }

    public function view(User $user, StockTransaction $transaction): bool
    {
        return $user->hasPermission('inventory.stocks.view')
            || $user->hasPermission('inventory.transactions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.stocks.adjust')
            || $user->hasPermission('inventory.transactions.create');
    }
}
