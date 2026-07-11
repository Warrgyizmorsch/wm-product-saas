<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Requests\StoreProjectMemberRequest;
use App\Domains\Projects\Requests\UpdateProjectMemberRequest;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ProjectMemberController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly ProjectMemberService $members,
    ) {
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', [ProjectMember::class, $project]);

        $this->members->add($project, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['member_page' => 1, 'tab' => 'members']))
            ->with('success', __('projects.member_added'));
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('update', $member);

        $this->members->update($member, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'members']))
            ->with('success', __('projects.member_updated'));
    }

    public function toggleActive(Project $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('update', $member);

        $wasActive = $member->is_active;
        $this->members->setActive($member, !$wasActive);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'members']))
            ->with('success', $wasActive ? __('projects.member_deactivated') : __('projects.member_activated'));
    }

    public function destroy(Project $project, int $member): RedirectResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('delete', $member);

        $this->members->remove($member);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'members']))
            ->with('success', __('projects.member_removed'));
    }
}
