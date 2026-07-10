<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\StoreMilestoneRequest;
use App\Domains\Projects\Requests\UpdateMilestoneRequest;
use App\Domains\Projects\Services\MilestoneService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly MilestoneService $milestones,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->only(['search', 'status', 'project_id']);

        $statuses = Milestone::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $projects = Project::query()
            ->orderBy('name')
            ->get(['id', 'name', 'project_code']);

        return view('modules.projects.milestones.index', [
            'milestones'  => $this->milestones->paginateAll($filters),
            'filters'     => $filters,
            'statuses'    => $statuses,
            'projects'    => $projects,
            'tenantUsers' => User::query()->where('tenant_id', tenant_id())->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMilestoneRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', [Milestone::class, $project]);

        $this->milestones->create($project, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'milestones', 'milestone_page' => 1]))
            ->with('success', __('projects.milestone_added'));
    }

    public function update(UpdateMilestoneRequest $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $this->authorize('update', $milestone);

        $this->milestones->update($milestone, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'milestones']))
            ->with('success', __('projects.milestone_updated'));
    }

    public function destroy(Project $project, Milestone $milestone): RedirectResponse
    {
        $this->authorize('delete', $milestone);

        $this->milestones->delete($milestone);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'milestones']))
            ->with('success', __('projects.milestone_removed'));
    }
}
