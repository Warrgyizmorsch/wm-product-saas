<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Repositories\MilestoneRepositoryInterface;
use App\Domains\Projects\Repositories\TaskDependencyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MilestoneService
{
    public const HEALTH_ON_TRACK = 'on_track';
    public const HEALTH_AT_RISK = 'at_risk';
    public const HEALTH_OFF_TRACK = 'off_track';
    public const HEALTH_BLOCKED = 'blocked';
    public const HEALTH_NOT_APPLICABLE = 'not_applicable';

    /**
     * How many percentage points a milestone may lag behind its expected
     * (date-based) pace before it's considered "at risk" rather than "on track".
     */
    private const AT_RISK_PACE_THRESHOLD = 15;

    public function __construct(
        private readonly MilestoneRepositoryInterface $milestones,
        private readonly TaskDependencyRepositoryInterface $dependencies,
        private readonly ActivityLogService $activity,
    ) {
    }

    public function list(Project $project): Collection
    {
        return $this->milestones->getForProject($project->id);
    }

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->milestones->paginateAll($filters, $perPage);
    }

    public function find(int $id): ?Milestone
    {
        return $this->milestones->find($id);
    }

    public function create(Project $project, array $data): Milestone
    {
        return DB::transaction(function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;

            $milestone = $this->milestones->create($data);

            $this->activity->record(
                $project,
                'milestone.created',
                "Milestone '{$milestone->name}' created",
                null,
                $milestone,
            );

            return $milestone;
        });
    }

    public function update(Milestone $milestone, array $data): Milestone
    {
        $oldStatus = $milestone->status;
        $newStatus = $data['status'] ?? $oldStatus;

        return DB::transaction(function () use ($milestone, $data, $oldStatus, $newStatus) {
            $project = $milestone->project;
            $milestone = $this->milestones->update($milestone->id, $data);

            if ($newStatus !== $oldStatus && $newStatus === Milestone::STATUS_COMPLETED) {
                $this->activity->record(
                    $project,
                    'milestone.completed',
                    "Milestone '{$milestone->name}' completed",
                    null,
                    $milestone,
                    ['old_status' => $oldStatus, 'new_status' => $newStatus],
                );
            } else {
                $this->activity->record(
                    $project,
                    'milestone.updated',
                    "Milestone '{$milestone->name}' updated",
                    null,
                    $milestone,
                );
            }

            return $milestone;
        });
    }

    public function delete(Milestone $milestone): bool
    {
        return DB::transaction(function () use ($milestone) {
            $this->activity->record(
                $milestone->project,
                'milestone.deleted',
                "Milestone '{$milestone->name}' deleted",
                null,
                $milestone,
            );

            return $this->milestones->delete($milestone->id);
        });
    }

    /**
     * Derive a milestone's schedule health, independent of its workflow `status`.
     *
     * Draft/On Hold/Completed/Closed milestones, and any milestone missing a
     * start or due date, are "not applicable" — health only judges active,
     * dated work. Among active/dated milestones: an open dependency on an
     * incomplete task makes it "blocked"; being past due while incomplete
     * makes it "off track"; running materially behind the expected date-based
     * pace makes it "at risk"; otherwise it's "on track".
     *
     * @return array{state: string, reason: ?string}
     */
    public function resolveHealth(Milestone $milestone): array
    {
        $inactiveStatuses = [
            Milestone::STATUS_DRAFT,
            Milestone::STATUS_ON_HOLD,
            Milestone::STATUS_COMPLETED,
            Milestone::STATUS_CLOSED,
        ];

        if (in_array($milestone->status, $inactiveStatuses, true)) {
            return ['state' => self::HEALTH_NOT_APPLICABLE, 'reason' => null];
        }

        if (! $milestone->start_date || ! $milestone->due_date) {
            return ['state' => self::HEALTH_NOT_APPLICABLE, 'reason' => null];
        }

        if ($this->dependencies->hasOpenDependenciesForMilestone($milestone->id)) {
            return ['state' => self::HEALTH_BLOCKED, 'reason' => 'Waiting on an incomplete dependency'];
        }

        $today = Carbon::today();
        $completion = $milestone->completion_percentage ?? 0;

        if ($today->gt($milestone->due_date) && $completion < 100) {
            $daysOverdue = (int) $milestone->due_date->diffInDays($today);

            return [
                'state' => self::HEALTH_OFF_TRACK,
                'reason' => 'Overdue by '.$daysOverdue.' day'.($daysOverdue === 1 ? '' : 's'),
            ];
        }

        $totalSpan = (int) $milestone->start_date->diffInDays($milestone->due_date);

        if ($totalSpan > 0) {
            $elapsed = min(max((int) $milestone->start_date->diffInDays($today, false), 0), $totalSpan);
            $expectedProgress = ($elapsed / $totalSpan) * 100;

            if ($completion < $expectedProgress - self::AT_RISK_PACE_THRESHOLD) {
                $behindBy = (int) round($expectedProgress - $completion);

                return [
                    'state' => self::HEALTH_AT_RISK,
                    'reason' => $behindBy.'% behind expected pace',
                ];
            }
        }

        return ['state' => self::HEALTH_ON_TRACK, 'reason' => null];
    }

    /**
     * Summarize a project's milestones for the KPI strip: status counts plus
     * a task-weighted Overall Progress. Overall Progress is deliberately
     * sum(completed tasks) / sum(total tasks) across milestones, not an
     * average of each milestone's own percentage — an average would let a
     * small, near-finished milestone misrepresent the whole project.
     *
     * Expects $milestones to already carry `tasks_count`/`completed_tasks_count`
     * (see MilestoneRepository::getForProject) — this method runs no queries.
     *
     * @return array{total: int, overdue: int, active: int, completed: int, overall_progress: int}
     */
    public function buildKpiSummary(Collection $milestones): array
    {
        $today = Carbon::today();

        $overdue = $milestones->filter(function (Milestone $milestone) use ($today) {
            return $milestone->due_date
                && $today->gt($milestone->due_date)
                && ! in_array($milestone->status, [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED], true);
        })->count();

        $totalTasks = (int) $milestones->sum('tasks_count');
        $completedTasks = (int) $milestones->sum('completed_tasks_count');

        return [
            'total' => $milestones->count(),
            'overdue' => $overdue,
            'active' => $milestones->where('status', Milestone::STATUS_ACTIVE)->count(),
            'completed' => $milestones->where('status', Milestone::STATUS_COMPLETED)->count(),
            'overall_progress' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
        ];
    }
}
