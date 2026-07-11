<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\AssignTaskRequest;
use App\Domains\Projects\Requests\StoreTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskStatusRequest;
use App\Domains\Projects\Services\TaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TaskService $tasks,
    ) {
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);

        $this->tasks->create($project, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_added'));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->update($task, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_updated'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_removed'));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->updateStatus($task, $request->validated()['status']);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_status_updated'));
    }

    public function assign(AssignTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->assign($task, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_assigned'));
    }
}
