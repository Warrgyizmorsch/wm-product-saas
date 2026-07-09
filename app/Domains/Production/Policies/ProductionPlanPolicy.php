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
        return !$plan->isFrozen()
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function delete(User $user, ProductionPlan $plan): bool
    {
        return $plan->isDraft()
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function submit(User $user, ProductionPlan $plan): bool
    {
        return $plan->isDraft()
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function approve(User $user, ProductionPlan $plan): bool
    {
        return $plan->isPendingApproval()
            && ($user->hasProductionPermission('production.planning.approve', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function reject(User $user, ProductionPlan $plan): bool
    {
        return $plan->isPendingApproval()
            && ($user->hasProductionPermission('production.planning.approve', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function release(User $user, ProductionPlan $plan): bool
    {
        return ($plan->isApproved() || $plan->isMrpGenerated())
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function complete(User $user, ProductionPlan $plan): bool
    {
        return $plan->isReleased()
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function close(User $user, ProductionPlan $plan): bool
    {
        return $plan->isCompleted()
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function cancel(User $user, ProductionPlan $plan): bool
    {
        return !$plan->isClosed()
            && !$plan->isCompleted()
            && !$plan->isCancelled()
            && ($user->hasProductionPermission('production.planning.cancel', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }

    public function runMrp(User $user, ProductionPlan $plan): bool
    {
        return in_array($plan->status, [
                ProductionPlan::STATUS_DRAFT,
                ProductionPlan::STATUS_PENDING_APPROVAL,
                ProductionPlan::STATUS_APPROVED,
                ProductionPlan::STATUS_MRP_GENERATED
            ])
            && ($user->hasProductionPermission('production.planning.update', $plan->tenant_id) || ($user->role === 'admin' && $user->tenant_id === $plan->tenant_id));
    }
}
