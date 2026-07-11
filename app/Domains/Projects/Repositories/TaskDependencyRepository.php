<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TaskDependency;
use Illuminate\Database\Eloquent\Collection;

class TaskDependencyRepository implements TaskDependencyRepositoryInterface
{
    public function getForTask(int $taskId): Collection
    {
        return TaskDependency::query()
            ->with(['dependsOn'])
            ->where('task_id', $taskId)
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?TaskDependency
    {
        return TaskDependency::query()->with(['task', 'dependsOn'])->find($id);
    }

    public function create(array $data): TaskDependency
    {
        return TaskDependency::create($data);
    }

    public function delete(int $id): bool
    {
        $dependency = TaskDependency::findOrFail($id);

        return $dependency->delete();
    }

    public function allEdgesForProject(int $projectId): Collection
    {
        return TaskDependency::query()
            ->where('project_id', $projectId)
            ->get(['task_id', 'depends_on_task_id']);
    }
}
