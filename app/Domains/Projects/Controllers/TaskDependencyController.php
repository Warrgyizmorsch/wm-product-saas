<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Requests\StoreTaskDependencyRequest;
use App\Domains\Projects\Services\TaskDependencyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TaskDependencyController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TaskDependencyService $dependencies,
    ) {
    }

    public function store(StoreTaskDependencyRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->create($task, (int) $request->validated()['depends_on_task_id']);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.dependency_added'));
    }

    public function destroy(Project $project, Task $task, TaskDependency $dependency): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->delete($dependency);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.dependency_removed'));
    }
}
