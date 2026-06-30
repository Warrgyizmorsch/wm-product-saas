<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\Machine;
use App\Models\User;

class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Machine $machine): bool
    {
        return $machine->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.machine.manage');
    }

    public function update(User $user, Machine $machine): bool
    {
        return $machine->tenant_id === $user->tenant_id
            && $user->hasProductionPermission('production.machine.manage');
    }

    public function delete(User $user, Machine $machine): bool
    {
        return $machine->tenant_id === $user->tenant_id
            && $user->hasProductionPermission('production.machine.manage');
    }
}
