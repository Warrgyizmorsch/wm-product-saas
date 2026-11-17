<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TimeLog;
use App\Models\Access\RolePermission;
use App\Models\User;
use App\Services\Access\AccessService;

class TimeLogPolicy
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

        $ownerId = $isMemberOrLead ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.timetracking.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]) || $this->access->allows($user, 'projects.tasks.view', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
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

        $ownerId = $isMemberOrLead ? $user->id : $project->owner_id;

        return $this->access->allows($user, 'projects.timetracking.log', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]) || $this->access->allows($user, 'projects.tasks.create', [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $ownerId,
        ]);
    }

    public function view(User $user, TimeLog $timeLog): bool
    {
        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.view')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.view');
    }

    public function update(User $user, TimeLog $timeLog): bool
    {
        if ($timeLog->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.log')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.update');
    }

    public function delete(User $user, TimeLog $timeLog): bool
    {
        if ($timeLog->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnLog($user, $timeLog, 'projects.timetracking.log')
            || $this->authorizeOnLog($user, $timeLog, 'projects.tasks.delete');
    }

    public function approve(User $user, TimeLog $timeLog): bool
    {
        $project = $timeLog->project;

        if ($project?->isClosed()) {
            return false;
        }

        // Anti-Self-Approval guardrail (matching HRMS ApprovalWorkflowService):
        // If the user logged this time entry themselves, self-approval is blocked
        // unless they hold explicit SCOPE_OWN on approval, or are Platform/Tenant/Company Admin.
        if ((int) $user->id === (int) $timeLog->user_id) {
            $canSelfApprove = $this->access->holdsPlatformAdminRole($user)
                || $this->access->hasRole($user, 'tenant_owner', $timeLog->tenant_id)
                || $this->access->hasRole($user, 'company_admin', $timeLog->tenant_id)
                || $this->access->hasExplicitScope($user, 'projects.timetracking.approve', RolePermission::SCOPE_OWN, $timeLog->tenant_id);

            if (! $canSelfApprove) {
                return false;
            }
        }

        $isLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        $effectiveOwnerId = $isLead ? $user->id : $project?->owner_id;

        return $this->access->allows($user, 'projects.timetracking.approve', [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $effectiveOwnerId,
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $effectiveOwnerId,
        ]);
    }

    public function approveAny(User $user): bool
    {
        $tenantId = $user->tenant_id ?? require_tenant_id();

        return $this->access->allows($user, 'projects.timetracking.approve', [
            'tenant_id' => $tenantId,
        ]) || $this->access->allows($user, 'projects.projects.update', [
            'tenant_id' => $tenantId,
        ]);
    }

    private function authorizeOnLog(User $user, TimeLog $timeLog, string $permission): bool
    {
        // Try user grant first (user owning the time entry)
        if ((int) $user->id === (int) $timeLog->user_id) {
            if ($this->access->allows($user, $permission, [
                'tenant_id' => $timeLog->tenant_id,
                'owner_id'  => $user->id,
            ])) {
                return true;
            }
        }

        $project = $timeLog->project;
        $isProjectLead = (int) $user->id === (int) $project?->owner_id
            || (int) $user->id === (int) $project?->manager_id;

        // Fall back to project lead grant (project owner / manager)
        return $this->access->allows($user, $permission, [
            'tenant_id' => $timeLog->tenant_id,
            'owner_id'  => $isProjectLead ? $user->id : $project?->owner_id,
        ]);
    }
}
