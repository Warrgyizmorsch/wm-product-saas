<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use App\Models\Access\RolePermission;
use App\Models\User;
use App\Services\Access\AccessService;

class ChangeRequestPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        $isMemberOrLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists());

        return $this->access->allows($user, 'projects.changerequests.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isMemberOrLead ? $user->id : $project->owner_id,
        ]);
    }

    public function view(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;
        $isParty = (int) $user->id === (int) $cr->requested_by
            || (int) $user->id === (int) $cr->created_by
            || (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id
            || ($project && ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists()));

        return $this->access->allows($user, 'projects.changerequests.view', [
            'tenant_id' => $project?->tenant_id ?? $cr->tenant_id,
            'owner_id'  => $isParty ? $user->id : $project?->owner_id,
        ]);
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        $isMemberOrLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists());

        return $this->access->allows($user, 'projects.changerequests.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isMemberOrLead ? $user->id : $project->owner_id,
        ]);
    }

    public function approve(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        if ($project?->isClosed()) {
            return false;
        }

        // Anti-Self-Approval guardrail (matching HRMS):
        // If the user requested or created this change request themselves, self-approval
        // is blocked unless they hold explicit SCOPE_OWN, or are Platform/Tenant/Company Admin.
        if ((int) $user->id === (int) $cr->requested_by || (int) $user->id === (int) $cr->created_by) {
            $canSelfApprove = $this->access->holdsPlatformAdminRole($user)
                || $this->access->hasRole($user, 'tenant_owner', $cr->tenant_id)
                || $this->access->hasRole($user, 'company_admin', $cr->tenant_id)
                || $this->access->hasExplicitScope($user, 'projects.changerequests.approve', RolePermission::SCOPE_OWN, $cr->tenant_id);

            if (! $canSelfApprove) {
                return false;
            }
        }

        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.changerequests.approve', [
            'tenant_id' => $project?->tenant_id ?? $cr->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project?->owner_id,
        ]);
    }

    public function markImplemented(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        if ($project?->isClosed()) {
            return false;
        }

        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.changerequests.approve', [
            'tenant_id' => $project?->tenant_id ?? $cr->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project?->owner_id,
        ]);
    }

    public function implement(User $user, ChangeRequest $cr): bool
    {
        return $this->markImplemented($user, $cr);
    }

    public function delete(User $user, ChangeRequest $cr): bool
    {
        $project = $cr->project;

        if ($project?->isClosed()) {
            return false;
        }

        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        return $this->access->allows($user, 'projects.changerequests.create', [
            'tenant_id' => $project?->tenant_id ?? $cr->tenant_id,
            'owner_id'  => $isLead ? $user->id : $project?->owner_id,
        ]);
    }
}
