<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\AssignTaskRequest;
use App\Domains\Projects\Requests\StoreTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskStatusRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Projects\Support\TaskDrawerPayload;
use App\Http\Controllers\Controller;
use App\Support\InlineEdit\HandlesInlineFieldUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    use BuildsBackUrl;
    use HandlesInlineFieldUpdates;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly ActivityLogService $activity,
        private readonly ProjectMemberService $members,
    ) {
    }

    /**
     * The Task Workspace: the only primary way to view a task. Row clicks
     * across the module navigate here instead of opening the retired drawer.
     */
    public function show(Project $project, Task $task, Request $request): View
    {
        $this->authorize('view', $task);

        $task->load([
            'taskList',
            'milestone',
            'assignee',
            'reviewer',
            'subTasks.assignee',
            'dependencies.dependsOn',
            'dependents.task',
        ]);

        $canManageTask = auth()->user()->can('update', $task);

        $nextAction = $this->tasks->deriveNextAction($task);

        $activityPerPage = 15;
        $activityPage = max((int) $request->query('activity_page', 1), 1);
        $allActivities = $this->activity->forTask($task, 200);
        $activityTotal = $allActivities->count();
        $activities = $allActivities->forPage($activityPage, $activityPerPage)->values();

        $backUrl = $task->milestone_id
            ? route('projects.milestones.show', [$project, $task->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project) . '?tab=tasklists';

        return view('modules.projects.tasks.workspace', [
            'project' => $project,
            'task' => $task,
            'canManageTask' => $canManageTask,
            'nextAction' => $nextAction,
            'legalNextStatuses' => $this->tasks->legalTransitions($task->status),
            'backUrl' => $backUrl,
            'subtasksPayload' => TaskDrawerPayload::subtasks($project, $task),
            'dependenciesPayload' => TaskDrawerPayload::dependencies($project, $task),
            'otherTasks' => TaskDrawerPayload::otherTasks($task, $this->tasks->list($project)->keyBy('id')),
            'taskLists' => $canManageTask ? $this->cloneTaskListOptions($project) : collect(),
            'activeMembers' => $canManageTask ? $this->members->list($project)->where('is_active', true) : collect(),
            'activities' => $activities,
            'activityPage' => $activityPage,
            'activityPerPage' => $activityPerPage,
            'activityTotal' => $activityTotal,
        ]);
    }

    private function cloneTaskListOptions(Project $project): \Illuminate\Database\Eloquent\Collection
    {
        return $project->taskLists()->orderBy('position')->get(['id', 'name']);
    }

    public function updateField(Request $request, Project $project, Task $task): JsonResponse
    {
        return $this->handleInlineFieldUpdate($request, $task);
    }

    protected function inlineFieldSchema(): array
    {
        $project = request()->route('project');
        $tenantId = require_tenant_id();

        return [
            'title' => [
                'rules' => ['required', 'string', 'max:255'],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'title', $value),
            ],
            'description' => [
                'rules' => ['nullable', 'string'],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'description', $value),
            ],
            'priority' => [
                'rules' => ['required', Rule::in(Task::PRIORITIES)],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'priority', $value),
            ],
            'assignee_id' => [
                'rules' => [
                    'nullable',
                    'integer',
                    Rule::exists('project_members', 'user_id')
                        ->where('tenant_id', $tenantId)
                        ->where('project_id', $project?->id)
                        ->where('is_active', true),
                ],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'assignee_id', $value),
            ],
            'reviewer_id' => [
                'rules' => [
                    'nullable',
                    'integer',
                    Rule::exists('project_members', 'user_id')
                        ->where('tenant_id', $tenantId)
                        ->where('project_id', $project?->id)
                        ->where('is_active', true),
                ],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'reviewer_id', $value),
            ],
            'due_date' => [
                'rules' => ['nullable', 'date'],
                'handler' => function (Task $task, $value) {
                    if ($value !== null && $task->start_date && Carbon::parse($value)->lt($task->start_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The due date must be a date after or equal to start date.',
                        ]);
                    }

                    return $this->tasks->updateField($task, 'due_date', $value)?->format('Y-m-d');
                },
            ],
        ];
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

        // Status transitions are now only triggered from the Task Workspace
        // (the row-level status dropdown was retired), so always return there.
        return redirect()
            ->route('projects.tasks.show', [$project, $task])
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
