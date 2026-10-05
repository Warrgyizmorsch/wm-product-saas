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
        return $this->access->allows($user, $permission, [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $project->owner_id,
        ]);
    }

    private function authorizeOnIssue(User $user, Issue $issue, string $permission): bool
    {
        if ($this->access->allows($user, $permission, [
            'tenant_id' => $issue->tenant_id,
            'owner_id' => $issue->assignee_id,
        ])) {
            return true;
        }

        if ($this->access->allows($user, $permission, [
            'tenant_id' => $issue->tenant_id,
            'owner_id' => $issue->reporter_id,
        ])) {
            return true;
        }

        return $this->access->allows($user, $permission, [
            'tenant_id' => $issue->tenant_id,
            'owner_id' => $issue->project?->owner_id,
        ]);
    }
}
