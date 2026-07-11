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
        return $this->authorizeOnProject($user, $project, 'projects.tasks.create');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->authorizeOnTask($user, $task, 'projects.tasks.view');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->authorizeOnTask($user, $task, 'projects.tasks.update');
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->authorizeOnTask($user, $task, 'projects.tasks.delete');
    }

    private function authorizeOnProject(User $user, Project $project, string $permission): bool
    {
        return $project->tenant_id === $user->tenant_id
            && $this->access->allows($user, $permission, [
                'tenant_id' => $project->tenant_id,
                'owner_id' => $project->owner_id,
            ]);
    }

    /**
     * Tries the "own" grant first (assignee acting on their own task), then
     * falls back to the "team" grant (project owner/manager) — AccessService's
     * scope matcher only compares a single owner_id per call, so a task's two
     * distinct owners have to be tried as two separate context checks.
     */
    private function authorizeOnTask(User $user, Task $task, string $permission): bool
    {
        if ($task->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($this->access->allows($user, $permission, [
            'tenant_id' => $task->tenant_id,
            'owner_id' => $task->assignee_id,
        ])) {
            return true;
        }

        return $this->access->allows($user, $permission, [
            'tenant_id' => $task->tenant_id,
            'owner_id' => $task->project?->owner_id,
        ]);
    }
}
