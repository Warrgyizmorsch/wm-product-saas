<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionPlan;
use App\Models\User;

class ProductionPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.planning.create')
            || $user->role === 'admin';
    }

    public function update(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && !$plan->isFrozen()
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function delete(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isDraft()
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function submit(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isDraft()
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function approve(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isPendingApproval()
            && ($user->hasProductionPermission('production.planning.approve') || $user->role === 'admin');
    }

    public function reject(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isPendingApproval()
            && ($user->hasProductionPermission('production.planning.approve') || $user->role === 'admin');
    }

    public function release(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && ($plan->isApproved() || $plan->isMrpGenerated())
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function complete(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isReleased()
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function close(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && $plan->isCompleted()
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }

    public function cancel(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && !$plan->isClosed()
            && !$plan->isCompleted()
            && !$plan->isCancelled()
            && ($user->hasProductionPermission('production.planning.cancel') || $user->role === 'admin');
    }

    public function runMrp(User $user, ProductionPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id
            && in_array($plan->status, [
                ProductionPlan::STATUS_DRAFT,
                ProductionPlan::STATUS_PENDING_APPROVAL,
                ProductionPlan::STATUS_APPROVED,
                ProductionPlan::STATUS_MRP_GENERATED
            ])
            && ($user->hasProductionPermission('production.planning.update') || $user->role === 'admin');
    }
}
