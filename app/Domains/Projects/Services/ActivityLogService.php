<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\ActivityLog;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Repositories\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
    ) {
    }

    public function record(
        Project $project,
        string $eventType,
        string $title,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ActivityLog {
        return $this->logs->create([
            'tenant_id'    => $project->tenant_id,
            'project_id'   => $project->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'event_type'   => $eventType,
            'title'        => $title,
            'description'  => $description,
            'triggered_by' => auth()->id(),
            'metadata'     => $metadata ?: null,
        ]);
    }

    public function forProject(Project $project, int $limit = 50): Collection
    {
        return $this->logs->forProject($project->id, $limit);
    }

    /**
     * Activity for a milestone plus its child task lists, tasks, and
     * sub-tasks — meaningful project activity for the milestone, not just
     * events recorded directly against the milestone itself.
     */
    public function forMilestone(Milestone $milestone, int $limit = 50): Collection
    {
        $milestone->loadMissing(['taskLists:id,milestone_id', 'tasks:id,milestone_id']);

        $taskIds = $milestone->tasks->pluck('id')->all();
        $subTaskIds = $taskIds === []
            ? []
            : SubTask::query()->whereIn('task_id', $taskIds)->pluck('id')->all();

        return $this->logs->forSubjects($milestone->project_id, [
            Milestone::class => [$milestone->id],
            TaskList::class => $milestone->taskLists->pluck('id')->all(),
            Task::class => $taskIds,
            SubTask::class => $subTaskIds,
        ], $limit);
    }

    /**
     * Activity for a task plus its sub-tasks and dependency edges.
     * Dependency events are recorded with the TaskDependency itself as the
     * subject (see TaskDependencyService), not the task, so they'd otherwise
     * be invisible from the task's own activity feed.
     */
    public function forTask(Task $task, int $limit = 50): Collection
    {
        $task->loadMissing(['subTasks:id,task_id', 'dependencies:id,task_id']);

        return $this->logs->forSubjects($task->project_id, [
            Task::class => [$task->id],
            SubTask::class => $task->subTasks->pluck('id')->all(),
            TaskDependency::class => $task->dependencies->pluck('id')->all(),
        ], $limit);
    }
}
