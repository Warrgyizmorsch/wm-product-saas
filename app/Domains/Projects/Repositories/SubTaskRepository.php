<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\SubTask;
use Illuminate\Database\Eloquent\Collection;

class SubTaskRepository implements SubTaskRepositoryInterface
{
    public function getForTask(int $taskId): Collection
    {
        return SubTask::query()
            ->with(['assignee'])
            ->where('task_id', $taskId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?SubTask
    {
        return SubTask::query()->with(['assignee', 'task'])->find($id);
    }

    public function create(array $data): SubTask
    {
        return SubTask::create($data);
    }

    public function update(int $id, array $data): SubTask
    {
        $subTask = SubTask::findOrFail($id);
        $subTask->update($data);

        return $subTask;
    }

    public function delete(int $id): bool
    {
        $subTask = SubTask::findOrFail($id);

        return $subTask->delete();
    }
}
