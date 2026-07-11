<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Requests\StoreTaskListRequest;
use App\Domains\Projects\Requests\UpdateTaskListRequest;
use App\Domains\Projects\Services\TaskListService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TaskListController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TaskListService $taskLists,
    ) {
    }

    public function store(StoreTaskListRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', [TaskList::class, $project]);

        $this->taskLists->create($project, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_added'));
    }

    public function update(UpdateTaskListRequest $request, Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->update($taskList, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_updated'));
    }

    public function destroy(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('delete', $taskList);

        $this->taskLists->delete($taskList);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_removed'));
    }

    public function moveUp(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->moveUp($taskList);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']));
    }

    public function moveDown(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->moveDown($taskList);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']));
    }
}
