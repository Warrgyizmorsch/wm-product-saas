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

        $completed = $subTasks->filter(fn (SubTask $s) => $s->isFinished())->count();

        return (int) round(($completed / $subTasks->count()) * 100);
    }

    public function create(Task $task, array $data): SubTask
    {
        return DB::transaction(function () use ($task, $data) {
            $data['task_id'] = $task->id;
            $data['tenant_id'] = $task->tenant_id;
            $data['position'] = $this->nextPosition($task);

            if (isset($data['status'])) {
                if ($data['status'] === SubTask::STATUS_COMPLETED) {
                    $data['is_completed'] = true;
                    $data['completed_at'] = now();
                } else {
                    $data['is_completed'] = false;
                    $data['completed_at'] = null;
                }
            } elseif (isset($data['is_completed'])) {
                if ($data['is_completed']) {
                    $data['status'] = SubTask::STATUS_COMPLETED;
                    $data['completed_at'] = now();
                } else {
                    $data['status'] = SubTask::STATUS_OPEN;
                    $data['completed_at'] = null;
                }
            } else {
                $data['status'] = SubTask::STATUS_OPEN;
                $data['is_completed'] = false;
                $data['completed_at'] = null;
            }

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
            if (array_key_exists('status', $data)) {
                if ($data['status'] === SubTask::STATUS_COMPLETED) {
                    $data['is_completed'] = true;
                    $data['completed_at'] = $subTask->completed_at ?? now();
                } else {
                    $data['is_completed'] = false;
                    $data['completed_at'] = null;
                }
            } elseif (array_key_exists('is_completed', $data)) {
                if ($data['is_completed']) {
                    $data['status'] = SubTask::STATUS_COMPLETED;
                    $data['completed_at'] = $subTask->completed_at ?? now();
                } else {
                    $data['status'] = SubTask::STATUS_OPEN;
                    $data['completed_at'] = null;
                }
            }

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

    public function toggleComplete(SubTask $subTask, ?bool $forceCompleted = null): SubTask
    {
        $isCompleted = $forceCompleted !== null ? $forceCompleted : ! $subTask->isFinished();

        return DB::transaction(function () use ($subTask, $isCompleted) {
            $subTask = $this->subTasks->update($subTask->id, [
                'status'       => $isCompleted ? SubTask::STATUS_COMPLETED : SubTask::STATUS_OPEN,
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
