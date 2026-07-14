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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
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
        $canUpdateAny = $data['projects']->contains(fn (Project $project) => auth()->user()->can('update', $project));

        if ($canCreate || $canUpdateAny) {
            $data['customers'] = Customer::query()->orderBy('name')->get();
            $data['users'] = User::query()->orderBy('name')->get();
        }

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
}
