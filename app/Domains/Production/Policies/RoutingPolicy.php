<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\Routing;
use App\Models\User;

class RoutingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.routing.create');
    }

    public function update(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $routing->isEditable()
            && $user->hasProductionPermission('production.routing.update');
    }

    public function delete(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $routing->isDraft()
            && $user->hasProductionPermission('production.routing.update');
    }

    public function submit(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $routing->isDraft()
            && $user->hasProductionPermission('production.routing.update');
    }

    public function approve(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $routing->isPendingApproval()
            && $user->hasProductionPermission('production.routing.approve');
    }

    public function reject(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $routing->isPendingApproval()
            && $user->hasProductionPermission('production.routing.approve');
    }

    public function cancel(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && !$routing->isCancelled()
            && !$routing->isHistorical()
            && $user->hasProductionPermission('production.routing.cancel');
    }

    public function duplicate(User $user, Routing $routing): bool
    {
        return $routing->tenant_id === $user->tenant_id
            && $user->hasProductionPermission('production.routing.create');
    }
}
