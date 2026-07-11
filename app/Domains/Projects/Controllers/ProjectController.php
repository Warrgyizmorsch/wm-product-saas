<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Requests\StoreProjectRequest;
use App\Domains\Projects\Requests\UpdateProjectRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\TaskListService;
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

        if (auth()->user()->can('create', Project::class)) {
            $data['customers'] = Customer::query()->orderBy('name')->get();
            $data['users'] = User::query()->orderBy('name')->get();
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

    public function show(Project $project): View
    {
        $project->load(['customer', 'owner', 'manager']);

        $this->authorize('view', $project);

        $canManageMembers = auth()->user()->can('manage', [ProjectMember::class, $project]);
        $canManageMilestones = auth()->user()->can('manage', [Milestone::class, $project]);
        $canManageTaskLists = auth()->user()->can('manage', [TaskList::class, $project]);

        return view('modules.projects.show', [
            'project'             => $project,
            'members'             => $this->members->list($project),
            'canManageMembers'    => $canManageMembers,
            'milestones'          => $this->milestones->list($project),
            'canManageMilestones' => $canManageMilestones,
            'taskLists'           => $this->taskLists->list($project),
            'canManageTaskLists'  => $canManageTaskLists,
            'tenantUsers'         => ($canManageMembers || $canManageMilestones || $canManageTaskLists)
                ? User::query()->where('tenant_id', $project->tenant_id)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('modules.projects.edit', [
            'project'   => $project,
            'customers' => Customer::query()->orderBy('name')->get(),
            'users'     => User::query()->orderBy('name')->get(),
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
