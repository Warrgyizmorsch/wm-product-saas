<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Repositories\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    /**
     * Allowed forward status transitions — the single source of truth for the
     * project lifecycle. Closed and Cancelled are terminal (no outgoing
     * transitions); Closing a project is always a manual action taken from
     * Completed, never automatic.
     */
    private const TRANSITIONS = [
        Project::STATUS_DRAFT     => [Project::STATUS_ACTIVE, Project::STATUS_CANCELLED],
        Project::STATUS_ACTIVE    => [Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_CANCELLED],
        Project::STATUS_ON_HOLD   => [Project::STATUS_ACTIVE, Project::STATUS_CANCELLED],
        Project::STATUS_COMPLETED => [Project::STATUS_CLOSED],
        Project::STATUS_CLOSED    => [],
        Project::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->projects->getAll($filters, $perPage);
    }

    public function find(int $id): ?Project
    {
        return $this->projects->find($id);
    }

    /**
     * Whether a project may move from one status to another.
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Single authoritative transition check, used by both the full project
     * update() path and inline status editing so the two can never drift out
     * of sync. Same-status "changes" are always allowed (no-op). Throws a
     * ValidationException keyed by $errorField otherwise.
     */
    public function assertTransitionAllowed(string $from, string $to, string $errorField = 'status'): void
    {
        if ($from !== $to && !$this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                $errorField => "A project cannot move from '{$from}' to '{$to}'.",
            ]);
        }
    }

    /**
     * The one authoritative status-change workflow: validates the transition,
     * persists it, and records project.status_changed with old/new metadata.
     * Used directly by inline status editing, and shared (via
     * persistStatusChange()) with update() when a full-form save changes
     * status, so both entry points produce identical audit records for the
     * same action. A same-status "change" is a no-op: no write, no log entry.
     */
    public function changeStatus(Project $project, string $newStatus, string $errorField = 'status'): Project
    {
        $oldStatus = $project->status;

        $this->assertTransitionAllowed($oldStatus, $newStatus, $errorField);

        if ($newStatus === $oldStatus) {
            return $project;
        }

        return DB::transaction(fn () => $this->persistStatusChange($project, [], $oldStatus, $newStatus));
    }

    /**
     * Persists a status change (optionally alongside other field changes from
     * a full-form save) and records the single project.status_changed entry
     * for it. The only place that writes a status change to the database or
     * logs it, so changeStatus() and update() can never disagree about what
     * happened.
     */
    private function persistStatusChange(Project $project, array $otherData, string $oldStatus, string $newStatus): Project
    {
        $project = $this->projects->update($project->id, array_merge($otherData, ['status' => $newStatus]));

        $this->activity->record(
            $project,
            'project.status_changed',
            "Project {$project->project_code} status changed",
            "Status changed from '{$oldStatus}' to '{$newStatus}'",
            $project,
            ['old' => $oldStatus, 'new' => $newStatus],
        );

        return $project;
    }

    /**
     * The statuses a project may be set to next, for building transition-aware
     * status pickers. Includes the current status first (so re-selecting it is
     * always a valid no-op), followed by its allowed forward transitions.
     * Terminal statuses (Closed, Cancelled) return only themselves.
     *
     * @return list<string>
     */
    public function availableStatusTransitions(Project $project): array
    {
        return array_merge([$project->status], self::TRANSITIONS[$project->status] ?? []);
    }

    public function summary(): array
    {
        return [
            'total'  => $this->projects->countAll(),
            'active' => $this->projects->countByStatus(Project::STATUS_ACTIVE),
        ];
    }

    /**
     * Build the dashboard stats (task/milestone/member/hours breakdowns) for a
     * project's details page from collections already loaded by the caller —
     * this performs no additional queries.
     */
    public function dashboardStats(
        Project $project,
        Collection $taskLists,
        Collection $tasksByList,
        Collection $allTasks,
        Collection $milestones,
        Collection $members,
    ): array {
        $doneStatuses = [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED];
        $inProgressStatuses = [Task::STATUS_IN_PROGRESS, Task::STATUS_REVIEW];

        $totalTasks = $allTasks->count();
        $doneTasks = $allTasks->whereIn('status', $doneStatuses)->count();
        $inProgressTasks = $allTasks->whereIn('status', $inProgressStatuses)->count();

        $totalMilestones = $milestones->count();
        $completedMilestones = $milestones->where('status', Milestone::STATUS_COMPLETED)->count();
        $activeMilestones = $milestones->where('status', Milestone::STATUS_ACTIVE)->count();

        $upcomingMilestones = $milestones
            ->whereNotIn('status', [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED])
            ->sortBy('due_date')
            ->take(5)
            ->values();

        $hoursTracked = (float) $allTasks->sum(fn (Task $task) => (float) $task->actual_hours);
        $budgetHours = (float) ($project->budget_hours ?? 0);

        $taskListProgress = $taskLists->mapWithKeys(function (TaskList $taskList) use ($tasksByList, $doneStatuses) {
            $listTasks = $tasksByList->get($taskList->id, collect());
            $total = $listTasks->count();
            $done = $listTasks->whereIn('status', $doneStatuses)->count();

            return [$taskList->id => [
                'total'   => $total,
                'done'    => $done,
                'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ]];
        });

        return [
            'tasks' => [
                'total'       => $totalTasks,
                'done'        => $doneTasks,
                'in_progress' => $inProgressTasks,
                'todo'        => max($totalTasks - $doneTasks - $inProgressTasks, 0),
                'percent'     => $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0,
            ],
            'milestones' => [
                'total'     => $totalMilestones,
                'completed' => $completedMilestones,
                'active'    => $activeMilestones,
                'percent'   => $totalMilestones > 0 ? (int) round($completedMilestones / $totalMilestones * 100) : 0,
                'upcoming'  => $upcomingMilestones,
            ],
            'members' => [
                'total'  => $members->count(),
                'active' => $members->where('is_active', true)->count(),
            ],
            'hours' => [
                'tracked' => $hoursTracked,
                'budget'  => $budgetHours,
                'percent' => $budgetHours > 0 ? (int) round(min($hoursTracked / $budgetHours, 1) * 100) : null,
            ],
            'task_lists' => $taskListProgress,
        ];
    }

    public function getNextProjectCode(): string
    {
        $latest = $this->projects->latestCode();

        $nextSeq = $latest ? intval(str_replace('PRJ-', '', $latest)) + 1 : 1;

        return 'PRJ-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $data['project_code'] = $this->getNextProjectCode();

            $project = $this->projects->create($data);

            $this->activity->record(
                $project,
                'project.created',
                "Project {$project->project_code} created",
                "Project '{$project->name}' created with status '{$project->status}'",
                $project,
            );

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        $oldStatus = $project->status;
        $newStatus = $data['status'] ?? $oldStatus;

        $this->assertTransitionAllowed($oldStatus, $newStatus);

        return DB::transaction(function () use ($project, $data, $oldStatus, $newStatus) {
            if ($newStatus !== $oldStatus) {
                $otherData = collect($data)->except('status')->all();

                return $this->persistStatusChange($project, $otherData, $oldStatus, $newStatus);
            }

            $project = $this->projects->update($project->id, $data);

            $this->activity->record(
                $project,
                'project.updated',
                "Project {$project->project_code} updated",
                "Project '{$project->name}' details updated",
                $project,
            );

            return $project;
        });
    }

    /**
     * Update a single field on a project (inline-edit). Kept independent of
     * update()'s status-transition machinery, since none of the fields using
     * this path today (starting with `name`) participate in that workflow.
     */
    public function updateField(Project $project, string $field, mixed $value): mixed
    {
        return DB::transaction(function () use ($project, $field, $value) {
            $project = $this->projects->update($project->id, [$field => $value]);

            $this->activity->record(
                $project,
                'project.updated',
                "Project {$project->project_code} updated",
                "Project '{$project->name}' details updated",
                $project,
            );

            return $project->{$field};
        });
    }

    public function delete(Project $project): bool
    {
        return DB::transaction(function () use ($project) {
            $this->activity->record(
                $project,
                'project.deleted',
                "Project {$project->project_code} deleted",
                "Project '{$project->name}' was deleted",
                $project,
            );

            return $this->projects->delete($project->id);
        });
    }
}
