<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Repositories\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Allowed forward status transitions, per the Functional Flow workflow.
     */
    private const TRANSITIONS = [
        Task::STATUS_OPEN        => [Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD, Task::STATUS_CANCELLED],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_REVIEW, Task::STATUS_ON_HOLD, Task::STATUS_CANCELLED],
        Task::STATUS_REVIEW      => [Task::STATUS_COMPLETED, Task::STATUS_IN_PROGRESS],
        Task::STATUS_ON_HOLD     => [Task::STATUS_IN_PROGRESS],
        Task::STATUS_COMPLETED   => [],
        Task::STATUS_CANCELLED   => [],
    ];

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Project $project, array $filters = []): Collection
    {
        return $this->tasks->getForProject($project->id, $filters);
    }

    public function find(int $id): ?Task
    {
        return $this->tasks->find($id);
    }

    public function getNextTaskCode(Project $project): string
    {
        $prefix = $project->project_code . '-T-';
        $latest = $this->tasks->latestCodeForProject($project->id);

        $nextSeq = $latest ? intval(str_replace($prefix, '', $latest)) + 1 : 1;

        return $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public function create(Project $project, array $data): Task
    {
        return DB::transaction(function () use ($project, $data) {
            $taskList = TaskList::findOrFail($data['task_list_id']);

            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;
            $data['milestone_id'] = $taskList->milestone_id;
            $data['task_code'] = $this->getNextTaskCode($project);
            $data['position'] = $this->nextPosition($taskList);

            $task = $this->tasks->create($data);

            $this->activity->record(
                $project,
                'task.created',
                "Task '{$task->title}' created",
                null,
                $task,
            );

            return $task;
        });
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $project = $task->project;

            if (array_key_exists('task_list_id', $data) && (int) $data['task_list_id'] !== $task->task_list_id) {
                $taskList = TaskList::findOrFail($data['task_list_id']);
                $data['milestone_id'] = $taskList->milestone_id;
                $data['position'] = $this->nextPosition($taskList);
            }

            $task = $this->tasks->update($task->id, $data);

            $this->activity->record(
                $project,
                'task.updated',
                "Task '{$task->title}' updated",
                null,
                $task,
            );

            return $task;
        });
    }

    public function updateStatus(Task $task, string $newStatus): Task
    {
        $oldStatus = $task->status;

        if ($newStatus === $oldStatus) {
            return $task;
        }

        if (!in_array($newStatus, self::TRANSITIONS[$oldStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A task cannot move from '{$oldStatus}' to '{$newStatus}'.",
            ]);
        }

        return DB::transaction(function () use ($task, $oldStatus, $newStatus) {
            $data = ['status' => $newStatus];
            $data['completed_at'] = $newStatus === Task::STATUS_COMPLETED ? now() : null;

            $task = $this->tasks->update($task->id, $data);

            $this->activity->record(
                $task->project,
                'task.status_changed',
                "Task '{$task->title}' status changed",
                "Status changed from '{$oldStatus}' to '{$newStatus}'",
                $task,
                ['old' => $oldStatus, 'new' => $newStatus],
            );

            return $task;
        });
    }

    public function assign(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task = $this->tasks->update($task->id, $data);

            $this->activity->record(
                $task->project,
                'task.assigned',
                "Task '{$task->title}' assignment updated",
                null,
                $task,
            );

            return $task;
        });
    }

    public function delete(Task $task): bool
    {
        return DB::transaction(function () use ($task) {
            $this->activity->record(
                $task->project,
                'task.deleted',
                "Task '{$task->title}' deleted",
                null,
                $task,
            );

            return $this->tasks->delete($task->id);
        });
    }

    private function nextPosition(TaskList $taskList): int
    {
        return (int) (Task::query()->where('task_list_id', $taskList->id)->max('position') + 1);
    }
}
