<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use App\Services\Access\AccessService;

class TaskPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->authorizeOnProject($user, $project, 'projects.tasks.view');
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->isClosed()) {
            return false;
        }

        return $this->authorizeOnProject($user, $project, 'projects.tasks.create');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->authorizeOnTask($user, $task, 'projects.tasks.view');
    }

    public function update(User $user, Task $task): bool
    {
        if ($task->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnTask($user, $task, 'projects.tasks.update');
    }

    public function delete(User $user, Task $task): bool
    {
        if ($task->project?->isClosed()) {
            return false;
        }

        return $this->authorizeOnTask($user, $task, 'projects.tasks.delete');
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

    /**
     * Tries the "own" grant first (assignee acting on their own task), then
     * falls back to project scope (active member for view, lead for mutate).
     */
    private function authorizeOnTask(User $user, Task $task, string $permission): bool
    {
        $assigneeId = $task->assignee_id ?? $task->assigned_to;
        if ($assigneeId && (int) $user->id === (int) $assigneeId) {
            if ($this->access->allows($user, $permission, [
                'tenant_id' => $task->tenant_id,
                'owner_id'  => $user->id,
            ])) {
                return true;
            }
        }

        $project = $task->project;
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

        $effectiveOwnerId = ($permission === 'projects.tasks.view' ? $isMemberOrLead : $isLead)
            ? $user->id
            : $project?->owner_id;

        return $this->access->allows($user, $permission, [
            'tenant_id' => $task->tenant_id,
            'owner_id'  => $effectiveOwnerId,
        ]);
    }
}
