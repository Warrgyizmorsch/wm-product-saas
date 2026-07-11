<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository implements TaskRepositoryInterface
{
    public function getForProject(int $projectId): Collection
    {
        return Task::query()
            ->with(['taskList', 'milestone', 'assignee', 'reviewer'])
            ->where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function getForTaskList(int $taskListId): Collection
    {
        return Task::query()
            ->with(['assignee', 'reviewer'])
            ->where('task_list_id', $taskListId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?Task
    {
        return Task::query()->with(['taskList', 'milestone', 'project', 'assignee', 'reviewer'])->find($id);
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function update(int $id, array $data): Task
    {
        $task = Task::findOrFail($id);
        $task->update($data);

        return $task;
    }

    public function delete(int $id): bool
    {
        $task = Task::findOrFail($id);

        return $task->delete();
    }

    public function latestCodeForProject(int $projectId): ?string
    {
        return Task::query()
            ->withTrashed()
            ->where('project_id', $projectId)
            ->orderByDesc('id')
            ->value('task_code');
    }

    public function deleteAllForTaskList(int $taskListId): void
    {
        Task::query()->where('task_list_id', $taskListId)->delete();
    }
}
