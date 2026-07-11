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
}
