<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Requests\StoreTaskListRequest;
use App\Domains\Projects\Requests\UpdateTaskListRequest;
use App\Domains\Projects\Services\TaskListService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskListController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TaskListService $taskLists,
    ) {
    }

    public function store(StoreTaskListRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', [TaskList::class, $project]);

        $data = $request->validated();

        if ($request->wantsJson()) {
            // The inline-create row only ever collects a name, so default the
            // owner to whoever created it — the modal's own owner select still
            // governs the full-page flow and is left untouched.
            $data['owner_id'] = $data['owner_id'] ?? auth()->id();
        }

        $taskList = $this->taskLists->create($project, $data);

        if ($request->wantsJson()) {
            return response()->json([
                'id'   => $taskList->id,
                'name' => $taskList->name,
                'html' => $this->renderCardHtml($project, $taskList),
            ]);
        }

        $defaultUrl = $taskList->milestone_id
            ? route('projects.milestones.show', [$project, $taskList->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project);

        return redirect()
            ->to($this->backUrlWithQuery($defaultUrl, ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_added'));
    }

    /**
     * Renders a single task list card for the inline-create AJAX response,
     * reusing the same _list-card partial the Task Lists tab loop renders,
     * with real (not placeholder) sibling/task data — a just-created list
     * genuinely has no tasks yet and is always last by position.
     */
    private function renderCardHtml(Project $project, TaskList $taskList): string
    {
        $taskList->loadMissing(['owner', 'milestone']);

        $projectTaskLists = $this->taskLists->list($project);
        $index = $projectTaskLists->search(fn (TaskList $item) => $item->id === $taskList->id);

        return view('modules.projects.tasklists._list-card', [
            'project'            => $project,
            'taskList'           => $taskList,
            'index'              => $index === false ? $projectTaskLists->count() - 1 : $index,
            'taskLists'          => $projectTaskLists,
            'tasksByList'        => collect(),
            'dashboard'          => ['task_lists' => []],
            'canManageTaskLists' => auth()->user()->can('manage', [TaskList::class, $project]),
            'canCreateTasks'     => auth()->user()->can('create', [Task::class, $project]),
        ])->render();
    }

    public function update(UpdateTaskListRequest $request, Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->update($taskList, $request->validated());

        $defaultUrl = $taskList->milestone_id
            ? route('projects.milestones.show', [$project, $taskList->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project);

        return redirect()
            ->to($this->backUrlWithQuery($defaultUrl, ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_updated'));
    }

    public function destroy(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('delete', $taskList);

        $defaultUrl = $taskList->milestone_id
            ? route('projects.milestones.show', [$project, $taskList->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project);

        $this->taskLists->delete($taskList);

        return redirect()
            ->to($this->backUrlWithQuery($defaultUrl, ['tab' => 'tasklists']))
            ->with('success', __('projects.tasklist_removed'));
    }

    public function moveUp(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->moveUp($taskList);

        $defaultUrl = $taskList->milestone_id
            ? route('projects.milestones.show', [$project, $taskList->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project);

        return redirect()
            ->to($this->backUrlWithQuery($defaultUrl, ['tab' => 'tasklists']));
    }

    public function moveDown(Project $project, TaskList $taskList): RedirectResponse
    {
        $this->authorize('update', $taskList);

        $this->taskLists->moveDown($taskList);

        $defaultUrl = $taskList->milestone_id
            ? route('projects.milestones.show', [$project, $taskList->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project);

        return redirect()
            ->to($this->backUrlWithQuery($defaultUrl, ['tab' => 'tasklists']));
    }
}
