<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Requests\StoreProjectMemberRequest;
use App\Domains\Projects\Requests\UpdateProjectMemberRequest;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ProjectMemberController extends Controller
{
    public function __construct(
        private readonly ProjectMemberService $members,
    ) {
    }

    public function store(StoreProjectMemberRequest $request, int $project): RedirectResponse
    {
        $project = Project::findOrFail($project);

        $this->authorize('manage', [ProjectMember::class, $project]);

        $this->members->add($project, $request->validated());

        return redirect()
            ->route('projects.show', $project->id)
            ->with('success', __('projects.member_added'));
    }

    public function update(UpdateProjectMemberRequest $request, int $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('update', $member);

        $this->members->update($member, $request->validated());

        return redirect()
            ->route('projects.show', $member->project_id)
            ->with('success', __('projects.member_updated'));
    }

    public function toggleActive(int $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('update', $member);

        $wasActive = $member->is_active;
        $this->members->setActive($member, !$wasActive);

        return redirect()
            ->route('projects.show', $member->project_id)
            ->with('success', $wasActive ? __('projects.member_deactivated') : __('projects.member_activated'));
    }

    public function destroy(int $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('delete', $member);

        $projectId = $member->project_id;
        $this->members->remove($member);

        return redirect()
            ->route('projects.show', $projectId)
            ->with('success', __('projects.member_removed'));
    }
}
