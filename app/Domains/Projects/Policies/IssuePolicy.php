<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Services\Access\AccessService;

class IssuePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->authorizeOnProject($user, $project, 'projects.issues.view');
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        return $this->authorizeOnProject($user, $project, 'projects.issues.create');
    }

    public function view(User $user, Issue $issue): bool
    {
        return $this->authorizeOnIssue($user, $issue, 'projects.issues.view');
    }

    public function update(User $user, Issue $issue): bool
    {
        if ($issue->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnIssue($user, $issue, 'projects.issues.edit')
            || $this->authorizeOnIssue($user, $issue, 'projects.issues.update');
    }

    public function resolve(User $user, Issue $issue): bool
    {
        if ($issue->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnIssue($user, $issue, 'projects.issues.resolve');
    }

    public function delete(User $user, Issue $issue): bool
    {
        if ($issue->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnIssue($user, $issue, 'projects.issues.delete');
    }

    public function retest(User $user, Issue $issue): bool
    {
        if ($issue->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnIssue($user, $issue, 'projects.issues.retest');
    }

    private function authorizeOnProject(User $user, Project $project, string $permission): bool
    {
        $isMemberOrLead = (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists());

        return $this->access->allows($user, $permission, [
            'tenant_id' => $project->tenant_id,
            'owner_id'  => $isMemberOrLead ? $user->id : $project->owner_id,
        ]);
    }

    private function authorizeOnIssue(User $user, Issue $issue, string $permission): bool
    {
        $isDirectParty = (int) $user->id === (int) $issue->assignee_id
            || (int) $user->id === (int) $issue->reporter_id;

        if ($isDirectParty) {
            if ($this->access->allows($user, $permission, [
                'tenant_id' => $issue->tenant_id,
                'owner_id'  => $user->id,
            ])) {
                return true;
            }
        }

        $project = $issue->project;
        $isMemberOrLead = $project && (
            (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
            || ($project->relationLoaded('members')
                ? $project->members->where('user_id', $user->id)->where('is_active', true)->isNotEmpty()
                : $project->members()->where('user_id', $user->id)->where('is_active', true)->exists())
        );

        $isLead = $project && (
            (int) $user->id === (int) $project->owner_id
            || (int) $user->id === (int) $project->manager_id
        );

        $effectiveOwnerId = ($permission === 'projects.issues.view' ? $isMemberOrLead : $isLead)
            ? $user->id
            : $project?->owner_id;

        return $this->access->allows($user, $permission, [
            'tenant_id' => $issue->tenant_id,
            'owner_id'  => $effectiveOwnerId,
        ]);
    }
}
