<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Requests\StoreProjectMemberRequest;
use App\Domains\Projects\Requests\UpdateProjectMemberRequest;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct(
        private readonly ProjectMemberService $members,
    ) {
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', [ProjectMember::class, $project]);

        $this->members->add($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('projects.member_added'));
    }

    /**
     * "Collaborators" search — same tenant-user pool as searchOwners/
     * searchClients, but excluding users already on the project.
     */
    public function searchCollaborators(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manage', [ProjectMember::class, $project]);

        $results = $this->members->searchAvailableUsers($project, $request->query('q'));

        return response()->json([
            'results' => $results->map(fn (User $user) => ['id' => $user->id, 'text' => $user->name]),
        ]);
    }

    /**
     * "Collaborators" quick-add — a ProjectMember row with only user_id set;
     * role/rate/cost/budget stay null and are only ever set from the
     * Members management tab.
     */
    public function storeCollaborator(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manage', [ProjectMember::class, $project]);

        $member = $this->members->add($project, $request->validated());
        $activeCount = $this->members->list($project)->where('is_active', true)->count();

        return response()->json([
            'member' => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
            ],
            'avatar_html' => view('modules.projects._collaborator_avatar', [
                'name' => $member->user->name,
                'index' => $activeCount - 1,
                'memberId' => $member->id,
                'role' => $this->members->roleLabel($member, $project),
            ])->render(),
            'active_count' => $activeCount,
        ]);
    }

    /**
     * "Collaborators" quick-remove — same soft-removal + assertRemovable()
     * business rules as the Members management tab's destroy(), triggered
     * from the avatar popover instead of a full-page form submit.
     */
    public function destroyCollaborator(Project $project, int $member): JsonResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('delete', $member);

        $this->members->remove($member);

        return response()->json([
            'member_id' => $member->id,
            'active_count' => $this->members->list($project)->where('is_active', true)->count(),
        ]);
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
            ->route('projects.show', $project)
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
            ->route('projects.show', $project)
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
            ->route('projects.show', $project)
            ->with('success', __('projects.member_removed'));
    }
}
