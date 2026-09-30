<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\RetestIssueRequest;
use App\Domains\Projects\Requests\StoreIssueRequest;
use App\Domains\Projects\Requests\UpdateIssueRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\IssueService;
use App\Http\Controllers\Controller;
use App\Support\InlineEdit\HandlesInlineFieldUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IssueController extends Controller
{
    use BuildsBackUrl;
    use HandlesInlineFieldUpdates;

    public function __construct(
        private readonly IssueService $issueService,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    public function index(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', [Issue::class, $project]);

        if ($request->wantsJson()) {
            $issues = $project->issues()->with(['reporter', 'assignee', 'task'])->latest()->get();
            return response()->json(['issues' => $issues]);
        }

        return redirect()->to(route('projects.show', $project) . '?tab=issues');
    }

    public function store(StoreIssueRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [Issue::class, $project]);

        $issue = $this->issueService->create($project, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.issue_created_success', ['default' => 'Issue reported successfully.']),
                'issue' => $issue,
                'redirect' => route('projects.issues.show', [$project, $issue]),
            ]);
        }

        return redirect()->route('projects.issues.show', [$project, $issue])
            ->with('success', __('projects.issue_created_success', ['default' => 'Issue reported successfully.']));
    }

    public function show(Project $project, Issue $issue, Request $request): View
    {
        $this->authorize('view', $issue);

        $issue->load([
            'reporter',
            'assignee',
            'task',
            'documents.uploader',
            'project.members.user',
        ]);

        $canManageIssue = auth()->user()->can('update', $issue);
        $canResolveIssue = auth()->user()->can('resolve', $issue);
        $canRetestIssue = auth()->user()->can('retest', $issue);
        $activities = $this->activityLogs->forIssue($issue, 50);
        $backUrl = route('projects.show', $project) . '?tab=issues';

        $availableMembers = $project->members()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->pluck('user');

        return view('modules.projects.issues.show', [
            'project' => $project,
            'issue' => $issue,
            'canManageIssue' => $canManageIssue,
            'canResolveIssue' => $canResolveIssue,
            'canRetestIssue' => $canRetestIssue,
            'activities' => $activities,
            'backUrl' => $backUrl,
            'availableMembers' => $availableMembers,
        ]);
    }

    public function update(UpdateIssueRequest $request, Project $project, Issue $issue): RedirectResponse|JsonResponse
    {
        if ($request->input('status') === Issue::STATUS_RESOLVED) {
            $this->authorize('resolve', $issue);
        } else {
            $this->authorize('update', $issue);
        }

        $updated = $this->issueService->update($issue, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.issue_updated_success', ['default' => 'Issue updated successfully.']),
                'issue' => $updated,
            ]);
        }

        return redirect()->back()
            ->with('success', __('projects.issue_updated_success', ['default' => 'Issue updated successfully.']));
    }

    public function updateField(Request $request, Project $project, Issue $issue): JsonResponse
    {
        if ($request->input('field') === 'status' && $request->input('value') === Issue::STATUS_RESOLVED) {
            $this->authorize('resolve', $issue);
        } else {
            $this->authorize('update', $issue);
        }

        return $this->handleInlineFieldUpdate($request, $issue);
    }

    public function retest(RetestIssueRequest $request, Project $project, Issue $issue): RedirectResponse|JsonResponse
    {
        $this->authorize('retest', $issue);

        $updated = $this->issueService->retest(
            $issue,
            (bool) $request->input('passed'),
            (string) $request->input('resolution_notes', ''),
            $request->user()
        );

        $msg = $request->input('passed')
            ? __('projects.issue_retest_passed_msg', ['default' => 'Retest verified: issue closed successfully.'])
            : __('projects.issue_retest_failed_msg', ['default' => 'Retest failed: issue reopened for rework.']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'issue' => $updated,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function destroy(Request $request, Project $project, Issue $issue): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $issue);

        $this->issueService->delete($issue, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.issue_deleted_success', ['default' => 'Issue deleted successfully.']),
            ]);
        }

        return redirect()->to(route('projects.show', $project) . '?tab=issues')
            ->with('success', __('projects.issue_deleted_success', ['default' => 'Issue deleted successfully.']));
    }

    protected function inlineFieldSchema(): array
    {
        $project = request()->route('project');
        $tenantId = require_tenant_id();

        return [
            'title' => [
                'rules' => ['required', 'string', 'max:255'],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'title', $value, request()->user()),
            ],
            'description' => [
                'rules' => ['nullable', 'string'],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'description', $value, request()->user()),
            ],
            'severity' => [
                'rules' => ['required', Rule::in(Issue::SEVERITIES)],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'severity', $value, request()->user()),
            ],
            'priority' => [
                'rules' => ['required', Rule::in(Issue::PRIORITIES)],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'priority', $value, request()->user()),
            ],
            'status' => [
                'rules' => ['required', Rule::in(Issue::STATUSES)],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'status', $value, request()->user()),
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
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'assignee_id', $value, request()->user()),
            ],
            'steps_to_reproduce' => [
                'rules' => ['nullable', 'string'],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'steps_to_reproduce', $value, request()->user()),
            ],
            'resolution_notes' => [
                'rules' => ['nullable', 'string'],
                'handler' => fn (Issue $issue, $value) => $this->issueService->updateField($issue, 'resolution_notes', $value, request()->user()),
            ],
        ];
    }
}
