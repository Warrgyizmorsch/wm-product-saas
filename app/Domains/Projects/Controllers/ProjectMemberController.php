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
            'results' => $results->map(fn (User $user) => [
                'id' => $user->id,
                'text' => $user->name,
                'email' => $user->email,
                'cost_per_hour' => $this->members->calculateBaselineHourlyCost($user, $project),
            ]),
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

        if (!$project->isSetupComplete()) {
            $missing = implode(', ', $project->missingSetupFields());
            return response()->json([
                'message' => __('projects.setup_required_before_collaborators_error', ['fields' => $missing]),
            ], 422);
        }

        $member = $this->members->add($project, $request->validated());
        $activeCount = $this->members->list($project)->where('is_active', true)->count();
        $teamCount = $project->activeTeamCollaboratorsCount();

        return response()->json([
            'member' => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'rate_per_hour' => $member->rate_per_hour,
            ],
            'avatar_html' => view('modules.projects._collaborator_avatar', [
                'name' => $member->user->name,
                'index' => $activeCount - 1,
                'memberId' => $member->id,
                'role' => $this->members->roleLabel($member, $project),
                'rate' => $member->rate_per_hour,
            ])->render(),
            'active_count' => $activeCount,
            'team_collaborators_count' => $teamCount,
            'should_reload' => $teamCount === 1,
        ]);
    }

    /**
     * Pre-check removal: retrieves active tasks assigned to the collaborator
     * and active candidate team members for reassignment. If the member is
     * structural (Owner, Manager, Milestone Owner, TaskList Owner), returns
     * 422 with the blocking reason.
     */
    public function getCollaboratorActiveTasks(Project $project, int $member): JsonResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('delete', $member);

        try {
            $this->members->assertCanRemove($member);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $error = $e->validator->errors()->first('member') ?: $e->getMessage();
            return response()->json([
                'can_remove' => false,
                'reason' => $error,
            ], 422);
        }

        $activeTasks = $this->members->getActiveTasks($member)->map(fn($t) => [
            'id' => $t->id,
            'task_code' => $t->task_code,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
        ]);

        $candidates = $this->members->activeMembers($project)
            ->where('id', '!=', $member->id)
            ->map(fn($m) => [
                'user_id' => $m->user_id,
                'name' => $m->user?->name ?? 'Unknown',
                'is_manager' => (int) $m->user_id === (int) $project->manager_id,
            ])
            ->values();

        return response()->json([
            'can_remove' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->user?->name ?? 'Unknown',
            ],
            'active_tasks' => $activeTasks,
            'active_tasks_count' => $activeTasks->count(),
            'candidates' => $candidates,
        ]);
    }

    /**
     * "Collaborators" quick-remove — same soft-removal + assertRemovable()
     * business rules as the Members management tab's destroy(), triggered
     * from the avatar popover instead of a full-page form submit. Supports
     * task reassignments for any active tasks held by the departing collaborator.
     */
    public function destroyCollaborator(Request $request, Project $project, int $member): JsonResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('delete', $member);

        $taskReassignments = (array) $request->input('reassignments', []);

        try {
            $this->members->remove($member, $taskReassignments);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $error = $e->validator->errors()->first('member') ?: $e->getMessage();
            return response()->json([
                'message' => $error,
                'errors' => ['member' => [$error]],
            ], 422);
        }

        $teamCount = $project->activeTeamCollaboratorsCount();
        $hasFinancials = $project->invoices()->exists()
            || $project->timeLogs()->where('approval_status', \App\Domains\Projects\Models\TimeLog::STATUS_APPROVED)->exists();

        $reassignedCount = count($taskReassignments);

        return response()->json([
            'success' => true,
            'member_id' => $member->id,
            'active_count' => $this->members->list($project)->where('is_active', true)->count(),
            'team_collaborators_count' => $teamCount,
            'should_reload' => $reassignedCount > 0 || ($teamCount === 0 && !$hasFinancials),
            'message' => $reassignedCount > 0
                ? __('projects.collaborator_removed_tasks_reassigned', ['count' => $reassignedCount])
                : __('projects.member_removed'),
        ]);
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, int $member): RedirectResponse|JsonResponse
    {
        $member = $this->members->find($member);

        if (!$member || $member->project_id !== $project->id) {
            abort(404, __('projects.member_not_found'));
        }

        $this->authorize('update', $member);

        $member = $this->members->update($member, $request->validated());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.rate_updated_successfully'),
                'member' => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'rate_per_hour' => $member->rate_per_hour,
                ],
            ]);
        }

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
