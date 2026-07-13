<?php

namespace App\Domains\Projects\Support;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use Illuminate\Support\Collection;

/**
 * Shapes subtask/dependency data for the Task Drawer, used both on initial
 * page render (task row payload) and on AJAX refresh after a mutation, so
 * the two stay structurally identical.
 */
class TaskDrawerPayload
{
    public static function subtasks(Project $project, Task $task): array
    {
        return $task->subTasks()->with('assignee')->get()->map(fn ($subTask) => [
            'id' => $subTask->id,
            'title' => $subTask->title,
            'isCompleted' => $subTask->is_completed,
            'assigneeId' => $subTask->assignee_id,
            'assigneeName' => $subTask->assignee?->name,
            'updateUrl' => route('projects.tasks.subtasks.update', [$project, $task, $subTask]),
            'toggleUrl' => route('projects.tasks.subtasks.toggle-complete', [$project, $task, $subTask]),
            'deleteUrl' => route('projects.tasks.subtasks.destroy', [$project, $task, $subTask]),
        ])->values()->all();
    }

    public static function dependencies(Project $project, Task $task): array
    {
        return $task->dependencies()->with('dependsOn')->get()->map(fn ($dependency) => [
            'id' => $dependency->id,
            'label' => $dependency->dependsOn?->task_code . ' — ' . $dependency->dependsOn?->title,
            'deleteUrl' => route('projects.tasks.dependencies.destroy', [$project, $task, $dependency]),
        ])->values()->all();
    }

    public static function otherTasks(Task $task, Collection $allTasks): array
    {
        return $allTasks->except($task->id)
            ->map(fn ($otherTask) => [
                'id' => $otherTask->id,
                'label' => $otherTask->task_code . ' — ' . $otherTask->title,
            ])->values()->all();
    }
}
