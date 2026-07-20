<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Requests\StoreMilestoneRequest;
use App\Domains\Projects\Requests\UpdateMilestoneRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Domains\Projects\Services\TaskListService;
use App\Domains\Projects\Services\TaskService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InlineEdit\HandlesInlineFieldUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    use BuildsBackUrl;
    use HandlesInlineFieldUpdates;

    public function __construct(
        private readonly MilestoneService $milestones,
        private readonly TaskListService $taskLists,
        private readonly TaskService $tasks,
        private readonly ProjectMemberService $members,
        private readonly ActivityLogService $activity,
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

    /**
     * The Milestone Workspace: a dedicated planning hub for one milestone.
     * Access inherits from the parent project's `view` ability rather than a
     * separate milestone permission — a milestone has no independent RBAC
     * surface today.
     */
    public function show(Project $project, Milestone $milestone, Request $request): View
    {
        $this->authorize('view', $project);

        $milestone->load('owner');
        $health = $this->milestones->resolveHealth($milestone);
        $milestone->health_state = $health['state'];
        $milestone->health_reason = $health['reason'];

        $canManageMilestones = auth()->user()->can('manage', [Milestone::class, $project]);
        $canManageTaskLists = auth()->user()->can('manage', [TaskList::class, $project]);
        $canCreateTasks = auth()->user()->can('create', [Task::class, $project]);

        $taskLists = $this->taskLists->list($project)
            ->where('milestone_id', $milestone->id)
            ->values();

        $taskFilters = array_filter(
            $request->only(['search', 'status', 'priority', 'assignee_id']),
            fn ($value) => trim((string) $value) !== '',
        );
        $hasActiveTaskFilters = $taskFilters !== [];

        $allProjectTasks = $this->tasks->list($project);
        $milestoneTasks = $allProjectTasks
            ->where('milestone_id', $milestone->id)
            ->values();
        $tasksByList = $milestoneTasks->groupBy('task_list_id');

        $filteredTasksByList = $hasActiveTaskFilters
            ? $this->filterTasks($milestoneTasks, $taskFilters)->groupBy('task_list_id')
            : $tasksByList;

        $doneStatuses = [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED];

        $taskListProgress = $taskLists->mapWithKeys(function (TaskList $taskList) use ($tasksByList, $doneStatuses) {
            $listTasks = $tasksByList->get($taskList->id, collect());
            $total = $listTasks->count();
            $done = $listTasks->whereIn('status', $doneStatuses)->count();

            return [$taskList->id => [
                'total' => $total,
                'done' => $done,
                'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ]];
        });

        $members = ($canManageMilestones || $canManageTaskLists || $canCreateTasks)
            ? $this->members->list($project)
            : collect();
        $activeMembers = $members->where('is_active', true);

        $openTasks = $milestoneTasks->reject(fn (Task $task) => in_array($task->status, $doneStatuses, true));

        $upcomingTasks = $openTasks
            ->filter(fn (Task $task) => $task->due_date !== null)
            ->sortBy('due_date')
            ->take(5)
            ->values();

        $today = Carbon::today();

        $attentionTasks = $openTasks
            ->map(function (Task $task) use ($today) {
                $isBlocked = $task->dependencies->contains(
                    fn ($dependency) => $dependency->dependsOn && $dependency->dependsOn->status !== Task::STATUS_COMPLETED,
                );
                $isOverdue = $task->due_date !== null && $today->gt($task->due_date);
                $isDueSoon = !$isOverdue && $task->due_date !== null && $today->diffInDays($task->due_date) <= 2;

                $task->attention_reason = match (true) {
                    $isOverdue => 'overdue',
                    $isBlocked => 'blocked',
                    $isDueSoon => 'due_soon',
                    default => null,
                };

                return $task;
            })
            ->filter(fn (Task $task) => $task->attention_reason !== null)
            ->sortBy(fn (Task $task) => match ($task->attention_reason) {
                'overdue' => 0,
                'blocked' => 1,
                'due_soon' => 2,
            })
            ->take(10)
            ->values();

        $activeTab = in_array($request->query('tab'), ['overview', 'tasklists', 'timeline', 'activity'], true)
            ? $request->query('tab')
            : 'overview';

        $activityPerPage = 15;
        $activityPage = max((int) $request->query('activity_page', 1), 1);
        $allActivities = $this->activity->forMilestone($milestone, 200);
        $activityTotal = $allActivities->count();
        $pagedActivities = $allActivities->forPage($activityPage, $activityPerPage)->values();

        return view('modules.projects.milestones.workspace', [
            'project' => $project,
            'milestone' => $milestone,
            'canManageMilestones' => $canManageMilestones,
            'canManageTaskLists' => $canManageTaskLists,
            'canCreateTasks' => $canCreateTasks,
            'taskLists' => $taskLists,
            'tasksByList' => $filteredTasksByList,
            'allTasks' => $allProjectTasks->keyBy('id'),
            'taskFilters' => $taskFilters,
            'hasActiveTaskFilters' => $hasActiveTaskFilters,
            'taskStatuses' => Task::STATUSES,
            'taskPriorities' => Task::PRIORITIES,
            'members' => $members,
            'activeMembers' => $activeMembers,
            'milestones' => Milestone::query()->where('project_id', $project->id)->orderBy('name')->get(['id', 'name']),
            'tenantUsers' => ($canManageMilestones || $canManageTaskLists || $canCreateTasks)
                ? User::query()->where('tenant_id', $project->tenant_id)->orderBy('name')->get()
                : collect(),
            'dashboard' => ['task_lists' => $taskListProgress],
            'totalTasks' => $milestoneTasks->count(),
            'completedTasks' => $milestoneTasks->where('status', Task::STATUS_COMPLETED)->count(),
            'upcomingTasks' => $upcomingTasks,
            'attentionTasks' => $attentionTasks,
            'recentActivities' => $allActivities->take(5)->values(),
            'activities' => $pagedActivities,
            'activityPage' => $activityPage,
            'activityPerPage' => $activityPerPage,
            'activityTotal' => $activityTotal,
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Applies the same filter predicates as TaskRepository::getForProject()
     * in memory, so the Milestone Workspace can reuse the already-fetched
     * project task collection instead of issuing a second filtered query.
     */
    private function filterTasks(Collection $tasks, array $filters): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $tasks->filter(function (Task $task) use ($filters, $search) {
            if ($search !== '' && !str_contains(strtolower($task->title), strtolower($search))) {
                return false;
            }

            if (!empty($filters['status']) && $task->status !== $filters['status']) {
                return false;
            }

            if (!empty($filters['priority']) && $task->priority !== $filters['priority']) {
                return false;
            }

            if (!empty($filters['assignee_id']) && (string) $task->assignee_id !== (string) $filters['assignee_id']) {
                return false;
            }

            return true;
        })->values();
    }

    public function store(StoreMilestoneRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', [Milestone::class, $project]);

        $milestone = $this->milestones->create($project, $request->validated());

        if ($request->wantsJson()) {
            $milestone->load('owner');
            $health = $this->milestones->resolveHealth($milestone);
            $milestone->health_state = $health['state'];
            $milestone->health_reason = $health['reason'];
            $milestone->tasks_count = 0;
            $milestone->completed_tasks_count = 0;

            $html = view('modules.projects.milestones._row', [
                'project' => $project,
                'milestone' => $milestone,
                'canManageMilestones' => auth()->user()->can('manage', [Milestone::class, $project]),
            ])->render();

            return response()->json(['html' => $html, 'id' => $milestone->id]);
        }

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

    public function updateField(Request $request, Project $project, Milestone $milestone): JsonResponse
    {
        return $this->handleInlineFieldUpdate($request, $milestone);
    }

    protected function inlineFieldSchema(): array
    {
        return [
            'name' => [
                'rules'   => ['required', 'string', 'max:255'],
                'handler' => fn (Milestone $milestone, $value) => $this->milestones->updateField($milestone, 'name', $value),
            ],
            'description' => [
                'rules'   => ['nullable', 'string'],
                'handler' => fn (Milestone $milestone, $value) => $this->milestones->updateField($milestone, 'description', $value),
            ],
            'status' => [
                'rules'   => ['nullable', Rule::in(Milestone::STATUSES)],
                'handler' => fn (Milestone $milestone, $value) => $this->milestones->updateField($milestone, 'status', $value),
            ],
            'owner_id' => [
                'rules'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', require_tenant_id())],
                'handler' => fn (Milestone $milestone, $value) => $this->milestones->updateField($milestone, 'owner_id', $value),
            ],
            'start_date' => [
                'rules'   => ['nullable', 'date'],
                'handler' => function (Milestone $milestone, $value) {
                    if ($value !== null && $milestone->due_date && Carbon::parse($value)->gt($milestone->due_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The start date must be a date before or equal to due date.',
                        ]);
                    }

                    return $this->milestones->updateField($milestone, 'start_date', $value)?->format('Y-m-d');
                },
            ],
            'due_date' => [
                'rules'   => ['nullable', 'date'],
                'handler' => function (Milestone $milestone, $value) {
                    if ($value !== null && $milestone->start_date && Carbon::parse($value)->lt($milestone->start_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The due date must be a date after or equal to start date.',
                        ]);
                    }

                    return $this->milestones->updateField($milestone, 'due_date', $value)?->format('Y-m-d');
                },
            ],
        ];
    }
}
