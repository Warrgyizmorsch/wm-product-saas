<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\SerialNumber;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SerialNumberPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.serials.view')
            || $user->hasPermission('inventory.items.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.serials.create')
            || $user->hasPermission('inventory.items.edit');
    }

    public function update(User $user, SerialNumber $serial): bool
    {
        return $user->hasPermission('inventory.serials.edit')
            || $user->hasPermission('inventory.items.edit');
    }
}
