<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Repositories\TaskDependencyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskDependencyService
{
    public function __construct(
        private readonly TaskDependencyRepositoryInterface $dependencies,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Task $task): Collection
    {
        return $this->dependencies->getForTask($task->id);
    }

    public function create(Task $task, int $dependsOnTaskId, string $dependencyType = TaskDependency::TYPE_FINISH_TO_START): TaskDependency
    {
        return DB::transaction(function () use ($task, $dependsOnTaskId, $dependencyType) {
            if ($task->id === $dependsOnTaskId) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'A task cannot depend on itself.',
                ]);
            }

            $predecessor = Task::find($dependsOnTaskId);
            if (! $predecessor || $predecessor->project_id !== $task->project_id) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'Cannot add dependency on a task from another project.',
                ]);
            }

            $exists = TaskDependency::query()
                ->where('task_id', $task->id)
                ->where('depends_on_task_id', $dependsOnTaskId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'This dependency already exists.',
                ]);
            }

            $this->assertNoCycle($task->project_id, $task->id, $dependsOnTaskId);

            $dependency = $this->dependencies->create([
                'tenant_id'          => $task->tenant_id,
                'project_id'         => $task->project_id,
                'task_id'            => $task->id,
                'depends_on_task_id' => $dependsOnTaskId,
                'dependency_type'    => $dependencyType,
            ]);

            $this->activity->record(
                $task->project,
                'task_dependency.created',
                "Dependency added to '{$task->title}' ({$dependencyType})",
                null,
                $dependency,
            );

            return $dependency;
        });
    }

    public function delete(TaskDependency $dependency): bool
    {
        return DB::transaction(function () use ($dependency) {
            $this->activity->record(
                $dependency->task->project,
                'task_dependency.deleted',
                "Dependency removed from '{$dependency->task->title}'",
                null,
                $dependency,
            );

            return $this->dependencies->delete($dependency->id);
        });
    }

    /**
     * Enforce server-side dependency rules when a task attempts a status transition.
     * Administrative transitions (On Hold, Cancelled) are always permitted.
     */
    public function assertCanTransition(Task $task, string $newStatus): void
    {
        if (in_array($newStatus, [Task::STATUS_ON_HOLD, Task::STATUS_CANCELLED], true)) {
            return;
        }

        $task->load('dependencies.dependsOn');

        foreach ($task->dependencies as $dependency) {
            $predecessor = $dependency->dependsOn;
            if (! $predecessor) {
                continue;
            }

            $type = $dependency->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START;

            $predecessorStarted = in_array($predecessor->status, [
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_REVIEW,
                Task::STATUS_COMPLETED,
            ], true);

            $predecessorFinished = ($predecessor->status === Task::STATUS_COMPLETED);

            $attemptingStart = in_array($newStatus, [
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_REVIEW,
                Task::STATUS_COMPLETED,
            ], true);

            $attemptingFinish = ($newStatus === Task::STATUS_COMPLETED);

            switch ($type) {
                case TaskDependency::TYPE_FINISH_TO_START:
                    if ($attemptingStart && ! $predecessorFinished) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot move task to '{$newStatus}' because predecessor '{$predecessor->task_code}' ({$predecessor->title}) is not completed (Finish-to-Start).",
                        ]);
                    }
                    break;

                case TaskDependency::TYPE_START_TO_START:
                    if ($attemptingStart && ! $predecessorStarted) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot move task to '{$newStatus}' because predecessor '{$predecessor->task_code}' ({$predecessor->title}) has not started (Start-to-Start).",
                        ]);
                    }
                    break;

                case TaskDependency::TYPE_FINISH_TO_FINISH:
                    if ($attemptingFinish && ! $predecessorFinished) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot complete task because predecessor '{$predecessor->task_code}' ({$predecessor->title}) is not completed (Finish-to-Finish).",
                        ]);
                    }
                    break;

                case TaskDependency::TYPE_START_TO_FINISH:
                    if ($attemptingFinish && ! $predecessorStarted) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot complete task because predecessor '{$predecessor->task_code}' ({$predecessor->title}) has not started (Start-to-Finish).",
                        ]);
                    }
                    break;
            }
        }
    }

    /**
     * Rejects an edge task_id -> dependsOnTaskId if a path already exists from
     * dependsOnTaskId back to task_id (i.e. adding the edge would close a cycle).
     * Traversed in-memory over the project's full edge set — no recursive SQL.
     */
    private function assertNoCycle(int $projectId, int $taskId, int $dependsOnTaskId): void
    {
        $edges = $this->dependencies->allEdgesForProject($projectId);

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->task_id][] = $edge->depends_on_task_id;
        }

        $visited = [];
        $stack = [$dependsOnTaskId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $taskId) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'This dependency would create a circular chain between tasks.',
                ]);
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }
    }
}
