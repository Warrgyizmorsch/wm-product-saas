<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\TaskList;
use Illuminate\Database\Eloquent\Collection;

class TaskListRepository implements TaskListRepositoryInterface
{
    public function getForProject(int $projectId): Collection
    {
        return TaskList::query()
            ->with(['owner', 'milestone'])
            ->where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?TaskList
    {
        return TaskList::query()->with(['owner', 'milestone', 'project'])->find($id);
    }

    public function create(array $data): TaskList
    {
        return TaskList::create($data);
    }

    public function update(int $id, array $data): TaskList
    {
        $taskList = TaskList::findOrFail($id);
        $taskList->update($data);

        return $taskList;
    }

    public function delete(int $id): bool
    {
        $taskList = TaskList::findOrFail($id);

        return $taskList->delete();
    }
}
