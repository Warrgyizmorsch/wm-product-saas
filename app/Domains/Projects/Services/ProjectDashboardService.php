<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\DTO\DashboardKpiDTO;
use App\Domains\Projects\DTO\ReportFilterDTO;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectDashboardService
{
    public const HEALTH_ON_TRACK = 'on_track';
    public const HEALTH_AT_RISK  = 'at_risk';
    public const HEALTH_CRITICAL = 'critical';

    public function __construct(
        private readonly MilestoneService $milestoneService,
    ) {}

    public function getPortfolioKpis(ReportFilterDTO $filters): DashboardKpiDTO
    {
        $projectQuery = $this->buildProjectQuery($filters);
        $projectIds = (clone $projectQuery)->pluck('id')->toArray();

        $totalProjects = (clone $projectQuery)->count();
        $activeProjectsQuery = (clone $projectQuery)->where('status', Project::STATUS_ACTIVE);
        $activeProjectsCount = (clone $activeProjectsQuery)->count();
        $completedProjectsCount = (clone $projectQuery)->whereIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CLOSED])->count();
        $onHoldProjectsCount = (clone $projectQuery)->where('status', Project::STATUS_ON_HOLD)->count();

        // Health derivation across active projects
        $activeProjects = (clone $activeProjectsQuery)
            ->with(['milestones', 'tasks', 'issues'])
            ->get();

        $onTrackCount = 0;
        foreach ($activeProjects as $project) {
            $health = $this->resolveProjectHealth($project);
            if ($health['state'] === self::HEALTH_ON_TRACK) {
                $onTrackCount++;
            }
        }

        $healthScore = $activeProjectsCount > 0
            ? round(($onTrackCount / $activeProjectsCount) * 100, 1)
            : 100.0;

        $totalBudgetAmount = (float) (clone $projectQuery)->sum('budget_amount');
        $totalBudgetHours  = (float) (clone $projectQuery)->sum('budget_hours');

        // Worklogs & Costs
        $timeLogQuery = TimeLog::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds);

        if ($filters->company_id) {
            $timeLogQuery->where('company_id', $filters->company_id);
        }
        if ($filters->start_date && $filters->end_date) {
            $timeLogQuery->whereBetween('log_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()]);
        }

        $totalTrackedHours = (float) (clone $timeLogQuery)->sum('hours');
        $totalIncurredCost = (float) ((clone $timeLogQuery)
            ->selectRaw('SUM(hours * COALESCE(hourly_rate, 0)) as cost')
            ->value('cost') ?? 0.0);

        $costConsumptionPercent = $totalBudgetAmount > 0
            ? round(($totalIncurredCost / $totalBudgetAmount) * 100, 1)
            : null;

        $hoursConsumptionPercent = $totalBudgetHours > 0
            ? round(($totalTrackedHours / $totalBudgetHours) * 100, 1)
            : null;

        // Overdue tasks
        $today = Carbon::today()->toDateString();
        $overdueTasksCount = Task::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->where('due_date', '<', $today)
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->count();

        // Overdue milestones
        $overdueMilestonesCount = Milestone::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->where('due_date', '<', $today)
            ->whereNotIn('status', [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED])
            ->count();

        // Issues
        $issuesQuery = Issue::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->whereNotIn('status', [Issue::STATUS_CLOSED, Issue::STATUS_RESOLVED]);

        $openIssuesCount = (clone $issuesQuery)->count();
        $criticalIssuesCount = (clone $issuesQuery)->where('severity', 'Critical')->count();

        // Unbilled approved hours & amount
        $unbilledQuery = (clone $timeLogQuery)
            ->where('is_billable', true)
            ->where('approval_status', TimeLog::STATUS_APPROVED)
            ->where('is_invoiced', false);

        $unbilledApprovedHours = (float) (clone $unbilledQuery)->sum('hours');
        $unbilledAmount = (float) ((clone $unbilledQuery)
            ->selectRaw('SUM(hours * COALESCE(hourly_rate, 0)) as amount')
            ->value('amount') ?? 0.0);

        return new DashboardKpiDTO(
            total_projects: $totalProjects,
            active_projects: $activeProjectsCount,
            completed_projects: $completedProjectsCount,
            on_hold_projects: $onHoldProjectsCount,
            portfolio_health_score: $healthScore,
            total_budget_amount: $totalBudgetAmount,
            total_incurred_cost: $totalIncurredCost,
            cost_consumption_percent: $costConsumptionPercent,
            total_budget_hours: $totalBudgetHours,
            total_tracked_hours: $totalTrackedHours,
            hours_consumption_percent: $hoursConsumptionPercent,
            overdue_tasks_count: $overdueTasksCount,
            overdue_milestones_count: $overdueMilestonesCount,
            open_issues_count: $openIssuesCount,
            critical_issues_count: $criticalIssuesCount,
            unbilled_approved_hours: $unbilledApprovedHours,
            unbilled_amount: $unbilledAmount,
        );
    }

    public function resolveProjectHealth(Project $project): array
    {
        if ($project->status !== Project::STATUS_ACTIVE) {
            return ['state' => 'not_applicable', 'reason' => null];
        }

        $today = Carbon::today();
        $milestones = $project->milestones ?? collect();
        $tasks = $project->tasks ?? collect();
        $issues = $project->issues ?? collect();

        // 1. Critical Check: Overdue milestones
        $overdueMilestone = $milestones->first(fn ($m) =>
            $m->due_date && $m->due_date->lt($today) && !in_array($m->status, [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED], true)
        );
        if ($overdueMilestone) {
            return ['state' => self::HEALTH_CRITICAL, 'reason' => 'Milestone overdue: ' . $overdueMilestone->name];
        }

        // 1. Critical Check: Overdue tasks
        $overdueTask = $tasks->first(fn ($t) =>
            $t->due_date && Carbon::parse($t->due_date)->lt($today) && !in_array($t->status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)
        );
        if ($overdueTask) {
            return ['state' => self::HEALTH_CRITICAL, 'reason' => 'Task overdue: ' . $overdueTask->title];
        }

        // 1. Critical Check: Open Critical issues
        $criticalIssue = $issues->first(fn ($i) =>
            $i->severity === 'Critical' && !in_array($i->status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
        );
        if ($criticalIssue) {
            return ['state' => self::HEALTH_CRITICAL, 'reason' => 'Open critical defect: ' . $criticalIssue->issue_number];
        }

        // 1. Critical Check: Severe budget overrun (> 110%)
        $budgetHours = (float) ($project->budget_hours ?? 0);
        $actualHours = (float) $tasks->sum(fn ($t) => (float) $t->actual_hours);
        if ($budgetHours > 0 && $actualHours > ($budgetHours * 1.10)) {
            $overrun = round((($actualHours - $budgetHours) / $budgetHours) * 100);
            return ['state' => self::HEALTH_CRITICAL, 'reason' => "Hours overrun by {$overrun}%"];
        }

        // 2. At Risk Check: Milestones behind pace
        foreach ($milestones as $milestone) {
            $mHealth = $this->milestoneService->resolveHealth($milestone);
            if ($mHealth['state'] === MilestoneService::HEALTH_AT_RISK || $mHealth['state'] === MilestoneService::HEALTH_BLOCKED) {
                return ['state' => self::HEALTH_AT_RISK, 'reason' => 'Milestone at risk: ' . $milestone->name];
            }
        }

        // 2. At Risk Check: Open High issues
        $highIssue = $issues->first(fn ($i) =>
            $i->severity === 'High' && !in_array($i->status, [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED], true)
        );
        if ($highIssue) {
            return ['state' => self::HEALTH_AT_RISK, 'reason' => 'Open high defect: ' . $highIssue->issue_number];
        }

        // 2. At Risk Check: Near budget limit (90% - 110%)
        if ($budgetHours > 0 && $actualHours >= ($budgetHours * 0.90)) {
            $pct = round(($actualHours / $budgetHours) * 100);
            return ['state' => self::HEALTH_AT_RISK, 'reason' => "Approaching budget limit ({$pct}%)"];
        }

        return ['state' => self::HEALTH_ON_TRACK, 'reason' => null];
    }

    public function getProjectsHealthList(ReportFilterDTO $filters, int $limit = 10): Collection
    {
        $projects = $this->buildProjectQuery($filters)
            ->where('status', Project::STATUS_ACTIVE)
            ->with(['customer', 'owner', 'milestones', 'tasks', 'issues'])
            ->limit($limit)
            ->get();

        return $projects->map(function (Project $project) {
            $health = $this->resolveProjectHealth($project);

            $tasks = $project->tasks ?? collect();
            $eligibleTasks = $tasks->where('status', '!=', Task::STATUS_CANCELLED);
            $completedTasks = $eligibleTasks->where('status', Task::STATUS_COMPLETED);

            $progress = $eligibleTasks->count() > 0
                ? (int) round(($completedTasks->count() / $eligibleTasks->count()) * 100)
                : 0;

            $budgetHours = (float) ($project->budget_hours ?? 0);
            $actualHours = (float) $tasks->sum(fn ($t) => (float) $t->actual_hours);

            return [
                'id'             => $project->id,
                'project_code'   => $project->project_code,
                'name'           => $project->name,
                'client_name'    => $project->customer?->name ?? '—',
                'owner_name'     => $project->owner?->name ?? '—',
                'health_state'   => $health['state'],
                'health_reason'  => $health['reason'],
                'progress'       => $progress,
                'budget_hours'   => $budgetHours,
                'actual_hours'   => $actualHours,
                'hours_percent'  => $budgetHours > 0 ? (int) round(($actualHours / $budgetHours) * 100) : null,
                'open_issues'    => $project->issues->whereNotIn('status', [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED])->count(),
            ];
        });
    }

    public function getResourceWorkload(ReportFilterDTO $filters, int $limit = 8): Collection
    {
        $projectIds = $this->buildProjectQuery($filters)->pluck('id')->toArray();

        // Get members active on these projects
        $members = ProjectMember::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->groupBy('user_id');

        $userWorkload = collect();

        foreach ($members as $userId => $memberAssignments) {
            $user = $memberAssignments->first()->user;
            if (!$user) {
                continue;
            }

            $assignedProjectIds = $memberAssignments->pluck('project_id')->toArray();
            $totalBudgetHours = (float) $memberAssignments->sum(fn ($m) => (float) $m->budget_hours);

            $openTasksCount = Task::query()
                ->where('tenant_id', $filters->tenant_id)
                ->whereIn('project_id', $assignedProjectIds)
                ->where('assignee_id', $userId)
                ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
                ->count();

            $timeLogQuery = TimeLog::query()
                ->where('tenant_id', $filters->tenant_id)
                ->where('user_id', $userId)
                ->whereIn('project_id', $assignedProjectIds);

            if ($filters->start_date && $filters->end_date) {
                $timeLogQuery->whereBetween('log_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()]);
            }

            $loggedHours = (float) $timeLogQuery->sum('hours');

            $burnPercent = $totalBudgetHours > 0
                ? (int) round(($loggedHours / $totalBudgetHours) * 100)
                : null;

            $userWorkload->push([
                'user_id'          => $userId,
                'user_name'        => $user->name,
                'user_email'       => $user->email,
                'project_count'    => count($assignedProjectIds),
                'open_tasks'       => $openTasksCount,
                'budget_hours'     => $totalBudgetHours,
                'logged_hours'     => $loggedHours,
                'burn_percent'     => $burnPercent,
            ]);
        }

        return $userWorkload->sortByDesc('open_tasks')->take($limit)->values();
    }

    public function getUpcomingMilestones(ReportFilterDTO $filters, int $limit = 5): Collection
    {
        $projectIds = $this->buildProjectQuery($filters)->pluck('id')->toArray();

        return Milestone::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->whereNotIn('status', [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED])
            ->whereNotNull('due_date')
            ->with('project')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    public function getIssueSeverityDistribution(ReportFilterDTO $filters): array
    {
        $projectIds = $this->buildProjectQuery($filters)->pluck('id')->toArray();

        $counts = Issue::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->select('severity', DB::raw('count(*) as aggregate'))
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->toArray();

        $openCount = Issue::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->whereNotIn('status', [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED])
            ->count();

        $resolvedCount = Issue::query()
            ->where('tenant_id', $filters->tenant_id)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED])
            ->count();

        return [
            'critical' => (int) ($counts['Critical'] ?? 0),
            'high'     => (int) ($counts['High'] ?? 0),
            'medium'   => (int) ($counts['Medium'] ?? 0),
            'low'      => (int) ($counts['Low'] ?? 0),
            'open'     => $openCount,
            'resolved' => $resolvedCount,
            'total'    => array_sum($counts),
        ];
    }

    public function buildProjectQuery(ReportFilterDTO $filters): Builder
    {
        $query = Project::query()->where('tenant_id', $filters->tenant_id);

        if ($filters->company_id) {
            $query->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $query->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $query->where('id', $filters->project_id);
        }
        if ($filters->customer_id) {
            $query->where('customer_id', $filters->customer_id);
        }
        if ($filters->status) {
            $query->where('status', $filters->status);
        }
        if ($filters->priority) {
            $query->where('priority', $filters->priority);
        }
        if ($filters->user_id) {
            $userId = $filters->user_id;
            $query->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)
                  ->orWhere('manager_id', $userId)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $userId)->where('is_active', true));
            });
        }
        if ($filters->start_date && $filters->end_date) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('start_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()])
                  ->orWhereBetween('created_at', [$filters->start_date, $filters->end_date]);
            });
        }
        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('project_code', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
