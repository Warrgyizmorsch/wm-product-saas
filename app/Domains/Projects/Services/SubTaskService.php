<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Repositories\SubTaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubTaskService
{
    public function __construct(
        private readonly SubTaskRepositoryInterface $subTasks,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Task $task): Collection
    {
        return $this->subTasks->getForTask($task->id);
    }

    public function completionPercentage(Task $task): int
    {
        $subTasks = $task->relationLoaded('subTasks') ? $task->subTasks : $this->list($task);

        if ($subTasks->isEmpty()) {
            return 0;
        }

        $completed = $subTasks->where('is_completed', true)->count();

        return (int) round(($completed / $subTasks->count()) * 100);
    }

    public function create(Task $task, array $data): SubTask
    {
        return DB::transaction(function () use ($task, $data) {
            $data['task_id'] = $task->id;
            $data['tenant_id'] = $task->tenant_id;
            $data['position'] = $this->nextPosition($task);

            $subTask = $this->subTasks->create($data);

            $this->activity->record(
                $task->project,
                'subtask.created',
                "Sub-task '{$subTask->title}' created on '{$task->title}'",
                null,
                $subTask,
            );

            return $subTask;
        });
    }

    public function update(SubTask $subTask, array $data): SubTask
    {
        return DB::transaction(function () use ($subTask, $data) {
            $subTask = $this->subTasks->update($subTask->id, $data);

            $this->activity->record(
                $subTask->task->project,
                'subtask.updated',
                "Sub-task '{$subTask->title}' updated",
                null,
                $subTask,
            );

            return $subTask;
        });
    }

    public function toggleComplete(SubTask $subTask): SubTask
    {
        $isCompleted = !$subTask->is_completed;

        return DB::transaction(function () use ($subTask, $isCompleted) {
            $subTask = $this->subTasks->update($subTask->id, [
                'is_completed' => $isCompleted,
                'completed_at' => $isCompleted ? now() : null,
            ]);

            $this->activity->record(
                $subTask->task->project,
                'subtask.status_changed',
                "Sub-task '{$subTask->title}' marked as " . ($isCompleted ? 'complete' : 'incomplete'),
                null,
                $subTask,
            );

            return $subTask;
        });
    }

    public function delete(SubTask $subTask): bool
    {
        return DB::transaction(function () use ($subTask) {
            $this->activity->record(
                $subTask->task->project,
                'subtask.deleted',
                "Sub-task '{$subTask->title}' deleted",
                null,
                $subTask,
            );

            return $this->subTasks->delete($subTask->id);
        });
    }

    private function nextPosition(Task $task): int
    {
        return (int) (SubTask::query()->where('task_id', $task->id)->max('position') + 1);
    }
}
