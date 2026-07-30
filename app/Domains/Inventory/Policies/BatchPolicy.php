<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\Batch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.batches.view')
            || $user->hasPermission('inventory.items.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.batches.create')
            || $user->hasPermission('inventory.items.edit');
    }

    public function update(User $user, Batch $batch): bool
    {
        return $user->hasPermission('inventory.batches.edit')
            || $user->hasPermission('inventory.items.edit');
    }
}
