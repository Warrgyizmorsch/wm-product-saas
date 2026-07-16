<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TaskDependency;
use Illuminate\Database\Eloquent\Collection;

interface TaskDependencyRepositoryInterface
{
    public function getForTask(int $taskId): Collection;

    public function find(int $id): ?TaskDependency;

    public function create(array $data): TaskDependency;

    public function delete(int $id): bool;

    /**
     * All dependency edges for a project as [task_id, depends_on_task_id] pairs,
     * used for in-memory cycle detection before inserting a new edge.
     */
    public function allEdgesForProject(int $projectId): Collection;

    /**
     * Whether any task under the given milestone depends on a task that
     * isn't Completed yet — used to derive the milestone's "Blocked" health state.
     */
    public function hasOpenDependenciesForMilestone(int $milestoneId): bool;
}
