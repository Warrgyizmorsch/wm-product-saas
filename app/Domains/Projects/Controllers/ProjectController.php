<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\StoreProjectRequest;
use App\Domains\Projects\Requests\UpdateProjectRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\ProjectService;
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
            ->route('projects.show', $project->id)
            ->with('success', 'Project successfully created!');
    }

    public function show(int $id): View
    {
        $project = $this->projects->find($id);

        if (!$project) {
            abort(404, 'Project not found.');
        }

        $this->authorize('view', $project);

        return view('modules.projects.show', [
            'project'    => $project,
            'activities' => $this->activity->forProject($project),
        ]);
    }

    public function edit(int $id): View
    {
        $project = $this->projects->find($id);

        if (!$project) {
            abort(404, 'Project not found.');
        }

        $this->authorize('update', $project);

        return view('modules.projects.edit', [
            'project'   => $project,
            'customers' => Customer::query()->orderBy('name')->get(),
            'users'     => User::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, int $id): RedirectResponse
    {
        $project = $this->projects->find($id);

        if (!$project) {
            abort(404, 'Project not found.');
        }

        $this->authorize('update', $project);

        $project = $this->projects->update($project, $request->validated());

        return redirect()
            ->route('projects.show', $project->id)
            ->with('success', 'Project successfully updated!');
    }

    public function destroy(int $id): RedirectResponse
    {
        $project = $this->projects->find($id);

        if (!$project) {
            abort(404, 'Project not found.');
        }

        $this->authorize('delete', $project);

        $this->projects->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project successfully deleted.');
    }
}
