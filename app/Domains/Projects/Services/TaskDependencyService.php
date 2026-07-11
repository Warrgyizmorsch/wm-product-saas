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

    public function create(Task $task, int $dependsOnTaskId): TaskDependency
    {
        return DB::transaction(function () use ($task, $dependsOnTaskId) {
            $this->assertNoCycle($task->project_id, $task->id, $dependsOnTaskId);

            $dependency = $this->dependencies->create([
                'tenant_id' => $task->tenant_id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'depends_on_task_id' => $dependsOnTaskId,
            ]);

            $this->activity->record(
                $task->project,
                'task_dependency.created',
                "Dependency added to '{$task->title}'",
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
