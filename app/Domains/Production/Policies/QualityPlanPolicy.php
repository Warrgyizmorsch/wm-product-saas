<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionQualityPlan;
use App\Models\User;

/**
 * QR-07 / QR-10 / QR-13: Quality Plan lifecycle permissions (same shape as RoutingPolicy).
 * "manage" = production.quality.manage, approve / reject / mark historical = production.quality.approve.
 */
class QualityPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.manage');
    }

    public function view(User $user, ?ProductionQualityPlan $plan = null): bool
    {
        return $user->hasProductionPermission('production.quality.manage')
            && ($plan === null || (int) $plan->tenant_id === (int) $user->tenant_id);
    }

    /** Kept for the existing API calls (Gate::authorize('manage', ProductionQualityPlan::class)). */
    public function manage(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.quality.manage');
    }

    /** Draft = edit in place; Approved = edit creates the next version. */
    public function update(User $user, ProductionQualityPlan $plan): bool
    {
        return $plan->isEditable()
            && $user->hasProductionPermission('production.quality.manage', $plan->tenant_id);
    }

    public function delete(User $user, ProductionQualityPlan $plan): bool
    {
        return $plan->isDraft()
            && $user->hasProductionPermission('production.quality.manage', $plan->tenant_id);
    }

    public function submit(User $user, ProductionQualityPlan $plan): bool
    {
        return $plan->isDraft()
            && $user->hasProductionPermission('production.quality.manage', $plan->tenant_id);
    }

    public function approve(User $user, ?ProductionQualityPlan $plan = null): bool
    {
        if ($plan === null) {
            return $user->hasProductionPermission('production.quality.approve');
        }

        return $plan->isPendingApproval()
            && $user->hasProductionPermission('production.quality.approve', $plan->tenant_id);
    }

    public function reject(User $user, ProductionQualityPlan $plan): bool
    {
        return $plan->isPendingApproval()
            && $user->hasProductionPermission('production.quality.approve', $plan->tenant_id);
    }

    public function retire(User $user, ProductionQualityPlan $plan): bool
    {
        return $plan->isApproved()
            && $user->hasProductionPermission('production.quality.approve', $plan->tenant_id);
    }
}
