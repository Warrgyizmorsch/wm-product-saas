<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Repositories\TaskListRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskListService
{
    public function __construct(
        private readonly TaskListRepositoryInterface $taskLists,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Project $project): Collection
    {
        return $this->taskLists->getForProject($project->id);
    }

    public function find(int $id): ?TaskList
    {
        return $this->taskLists->find($id);
    }

    public function create(Project $project, array $data): TaskList
    {
        return DB::transaction(function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;
            $data['position'] = $this->nextPosition($project);

            $taskList = $this->taskLists->create($data);

            $this->activity->record(
                $project,
                'tasklist.created',
                "Task list '{$taskList->name}' created",
                null,
                $taskList,
            );

            return $taskList;
        });
    }

    public function update(TaskList $taskList, array $data): TaskList
    {
        return DB::transaction(function () use ($taskList, $data) {
            $project = $taskList->project;
            $taskList = $this->taskLists->update($taskList->id, $data);

            $this->activity->record(
                $project,
                'tasklist.updated',
                "Task list '{$taskList->name}' updated",
                null,
                $taskList,
            );

            return $taskList;
        });
    }

    public function delete(TaskList $taskList): bool
    {
        return DB::transaction(function () use ($taskList) {
            $this->activity->record(
                $taskList->project,
                'tasklist.deleted',
                "Task list '{$taskList->name}' deleted",
                null,
                $taskList,
            );

            return $this->taskLists->delete($taskList->id);
        });
    }

    public function moveUp(TaskList $taskList): TaskList
    {
        return $this->swapWithSibling($taskList, '<', 'desc');
    }

    public function moveDown(TaskList $taskList): TaskList
    {
        return $this->swapWithSibling($taskList, '>', 'asc');
    }

    private function swapWithSibling(TaskList $taskList, string $operator, string $direction): TaskList
    {
        return DB::transaction(function () use ($taskList, $operator, $direction) {
            $sibling = TaskList::query()
                ->where('project_id', $taskList->project_id)
                ->where(function ($query) use ($taskList, $operator) {
                    $query->where('position', $operator, $taskList->position)
                        ->orWhere(function ($query) use ($taskList, $operator) {
                            $query->where('position', $taskList->position)
                                ->where('id', $operator, $taskList->id);
                        });
                })
                ->orderBy('position', $direction)
                ->orderBy('id', $direction)
                ->first();

            if ($sibling === null) {
                return $taskList;
            }

            $siblingPosition = $sibling->position;
            $sibling->update(['position' => $taskList->position]);
            $taskList->update(['position' => $siblingPosition]);

            return $taskList->refresh();
        });
    }

    private function nextPosition(Project $project): int
    {
        return (int) (TaskList::query()->where('project_id', $project->id)->max('position') + 1);
    }
}
