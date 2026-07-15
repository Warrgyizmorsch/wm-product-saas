<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Requests\StoreProjectRequest;
use App\Domains\Projects\Requests\UpdateProjectRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\TaskListService;
use App\Domains\Projects\Services\TaskService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InlineEdit\HandlesInlineFieldUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use HandlesInlineFieldUpdates;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly ActivityLogService $activity,
        private readonly ProjectMemberService $members,
        private readonly MilestoneService $milestones,
        private readonly TaskListService $taskLists,
        private readonly TaskService $tasks,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $data = [
            'projects' => $this->projects->list($request->only(['status', 'search'])),
            'summary'  => $this->projects->summary(),
            'statuses' => Project::STATUSES,
            'filters'  => $request->only(['status', 'search']),
        ];

        $canCreate = auth()->user()->can('create', Project::class);

        if ($canCreate) {
            $data['nextCode'] = $this->projects->getNextProjectCode();
        }

        return view('modules.projects.index', $data);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projects->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project successfully created!');
    }

    public function show(Project $project, Request $request): View
    {
        $project->load(['customer', 'owner', 'manager']);

        $this->authorize('view', $project);

        $canManageMembers = auth()->user()->can('manage', [ProjectMember::class, $project]);
        $canManageMilestones = auth()->user()->can('manage', [Milestone::class, $project]);
        $canManageTaskLists = auth()->user()->can('manage', [TaskList::class, $project]);
        $canCreateTasks = auth()->user()->can('create', [Task::class, $project]);
        $canUpdateProject = auth()->user()->can('update', $project);

        $members = $this->members->list($project);
        $milestones = $this->milestones->list($project);
        $taskLists = $this->taskLists->list($project);
        $allTasks = $this->tasks->list($project);
        $tasksByList = $allTasks->groupBy('task_list_id');

        $taskFilters = array_filter(
            $request->only(['search', 'status', 'priority', 'assignee_id']),
            fn ($value) => trim((string) $value) !== '',
        );
        $hasActiveTaskFilters = $taskFilters !== [];

        $filteredTasksByList = $hasActiveTaskFilters
            ? $this->tasks->list($project, $taskFilters)->groupBy('task_list_id')
            : $tasksByList;

        return view('modules.projects.show', [
            'project'             => $project,
            'members'             => $members,
            'canManageMembers'    => $canManageMembers,
            'canUpdateProject'    => $canUpdateProject,
            'customers'           => $canUpdateProject ? Customer::query()->orderBy('name')->get() : collect(),
            'users'               => $canUpdateProject ? User::query()->orderBy('name')->get() : collect(),
            'milestones'          => $milestones,
            'canManageMilestones' => $canManageMilestones,
            'taskLists'           => $taskLists,
            'canManageTaskLists'  => $canManageTaskLists,
            'tasksByList'         => $filteredTasksByList,
            'allTasks'            => $allTasks->keyBy('id'),
            'taskFilters'         => $taskFilters,
            'hasActiveTaskFilters' => $hasActiveTaskFilters,
            'taskStatuses'        => Task::STATUSES,
            'taskPriorities'      => Task::PRIORITIES,
            'canCreateTasks'      => $canCreateTasks,
            'dashboard'           => $this->projects->dashboardStats($project, $taskLists, $tasksByList, $allTasks, $milestones, $members),
            'recentActivities'    => $this->activity->forProject($project, 5),
            'activeMembers'       => ($canManageMembers || $canManageMilestones || $canManageTaskLists || $canCreateTasks)
                ? $members->where('is_active', true)
                : collect(),
            'tenantUsers'         => ($canManageMembers || $canManageMilestones || $canManageTaskLists)
                ? User::query()->where('tenant_id', $project->tenant_id)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project = $this->projects->update($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project successfully updated!');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projects->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project successfully deleted.');
    }

    public function updateField(Request $request, Project $project): JsonResponse
    {
        return $this->handleInlineFieldUpdate($request, $project);
    }

    protected function inlineFieldSchema(): array
    {
        return [
            'name' => [
                'rules'   => ['required', 'string', 'max:255'],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'name', $value),
            ],
            'budget_amount' => [
                'rules'   => ['nullable', 'numeric', 'min:0'],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'budget_amount', $value),
            ],
            'budget_hours' => [
                'rules'   => ['nullable', 'numeric', 'min:0'],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'budget_hours', $value),
            ],
            'start_date' => [
                'rules'   => ['required', 'date'],
                'handler' => function (Project $project, $value) {
                    if ($project->end_date && Carbon::parse($value)->gt($project->end_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The start date must be a date before or equal to end date.',
                        ]);
                    }

                    return $this->projects->updateField($project, 'start_date', $value)?->format('Y-m-d');
                },
            ],
            'end_date' => [
                'rules'   => ['nullable', 'date'],
                'handler' => function (Project $project, $value) {
                    if ($value !== null && $project->start_date && Carbon::parse($value)->lt($project->start_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The end date must be a date after or equal to start date.',
                        ]);
                    }

                    return $this->projects->updateField($project, 'end_date', $value)?->format('Y-m-d');
                },
            ],
            'priority' => [
                'rules'   => ['required', Rule::in(Project::PRIORITIES)],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'priority', $value),
            ],
            'status' => [
                'rules'   => ['required', Rule::in(Project::EDITABLE_STATUSES)],
                'handler' => function (Project $project, $value) {
                    if ($value !== $project->status && !$this->projects->canTransition($project->status, $value)) {
                        throw ValidationException::withMessages([
                            'value' => "A project cannot move from '{$project->status}' to '{$value}'.",
                        ]);
                    }

                    return $this->projects->updateField($project, 'status', $value);
                },
            ],
            'customer_id' => [
                'rules'   => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', require_tenant_id())],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'customer_id', $value),
            ],
            'owner_id' => [
                'rules'   => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', require_tenant_id())],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'owner_id', $value),
            ],
            'manager_id' => [
                'rules'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', require_tenant_id())],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'manager_id', $value),
            ],
            'budget_type' => [
                'rules'   => ['nullable', Rule::in(Project::BUDGET_TYPES)],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'budget_type', $value),
            ],
            'billing_method' => [
                'rules'   => ['nullable', Rule::in(Project::BILLING_METHODS)],
                'handler' => fn (Project $project, $value) => $this->projects->updateField($project, 'billing_method', $value),
            ],
        ];
    }
}
