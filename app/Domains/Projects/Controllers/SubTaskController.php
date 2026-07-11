<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\StoreSubTaskRequest;
use App\Domains\Projects\Requests\UpdateSubTaskRequest;
use App\Domains\Projects\Services\SubTaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class SubTaskController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly SubTaskService $subTasks,
    ) {
    }

    public function store(StoreSubTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->create($task, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.subtask_added'));
    }

    public function update(UpdateSubTaskRequest $request, Project $project, Task $task, SubTask $subTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->update($subTask, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.subtask_updated'));
    }

    public function toggleComplete(Project $project, Task $task, SubTask $subTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->toggleComplete($subTask);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.subtask_updated'));
    }

    public function destroy(Project $project, Task $task, SubTask $subTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->delete($subTask);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.subtask_removed'));
    }
}
