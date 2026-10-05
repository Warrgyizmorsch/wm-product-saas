<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\DTO\ReportFilterDTO;
use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectReportService
{
    public const REPORT_SUMMARY               = 'summary';
    public const REPORT_TASK_STATUS           = 'task-status';
    public const REPORT_RESOURCE_UTILIZATION  = 'resource-utilization';
    public const REPORT_TIMESHEET_BILLABILITY = 'timesheet-billability';
    public const REPORT_ISSUE_DEFECT_DENSITY  = 'issue-defect-density';
    public const REPORT_MILESTONE_VARIANCE    = 'milestone-variance';
    public const REPORT_BUDGET_COST           = 'budget-cost';

    public const REPORT_IDENTIFIERS = [
        self::REPORT_SUMMARY               => 'Project Summary Report',
        self::REPORT_TASK_STATUS           => 'Task Status Report',
        self::REPORT_RESOURCE_UTILIZATION  => 'Resource Utilization & Productivity Report',
        self::REPORT_TIMESHEET_BILLABILITY => 'Timesheet & Billability Report',
        self::REPORT_ISSUE_DEFECT_DENSITY  => 'Issue & Defect Density Report',
        self::REPORT_MILESTONE_VARIANCE    => 'Milestone Variance Report',
        self::REPORT_BUDGET_COST           => 'Budget vs. Actual Cost Report',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Project Summary Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getProjectSummaryQuery(ReportFilterDTO $filters): Builder
    {
        $query = Project::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['customer', 'owner', 'manager', 'tasks']);

        $this->applyProjectFilters($query, $filters);

        if ($filters->start_date && $filters->end_date) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('start_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()])
                  ->orWhereBetween('created_at', [$filters->start_date, $filters->end_date]);
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function getProjectSummaryReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getProjectSummaryQuery($filters)->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Task Status Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getTaskStatusQuery(ReportFilterDTO $filters): Builder
    {
        $query = Task::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['project', 'taskList', 'milestone', 'assignee']);

        if ($filters->company_id) {
            $query->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $query->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $query->where('project_id', $filters->project_id);
        }
        if ($filters->status) {
            $query->where('status', $filters->status);
        }
        if ($filters->priority) {
            $query->where('priority', $filters->priority);
        }
        if ($filters->user_id) {
            $query->where('assignee_id', $filters->user_id);
        }
        if ($filters->overdue_only) {
            $query->where('due_date', '<', Carbon::today()->toDateString())
                  ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]);
        }
        if ($filters->start_date && $filters->end_date) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('due_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()])
                  ->orWhereBetween('created_at', [$filters->start_date, $filters->end_date]);
            });
        }
        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('task_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('due_date', 'asc')->orderBy('id', 'desc');
    }

    public function getTaskStatusReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getTaskStatusQuery($filters)->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Resource Utilization & Productivity Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getResourceUtilizationData(ReportFilterDTO $filters): Collection
    {
        $membersQuery = ProjectMember::query()
            ->where('tenant_id', $filters->tenant_id)
            ->where('is_active', true)
            ->with(['user', 'project']);

        if ($filters->company_id) {
            $membersQuery->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $membersQuery->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $membersQuery->where('project_id', $filters->project_id);
        }
        if ($filters->user_id) {
            $membersQuery->where('user_id', $filters->user_id);
        }

        $members = $membersQuery->get()->groupBy('user_id');
        $rows = collect();

        foreach ($members as $userId => $assignments) {
            $user = $assignments->first()->user;
            if (!$user) {
                continue;
            }

            if ($filters->search && !str_contains(strtolower($user->name), strtolower($filters->search)) && !str_contains(strtolower($user->email), strtolower($filters->search))) {
                continue;
            }

            $projectIds = $assignments->pluck('project_id')->toArray();
            $totalBudgetHours = (float) $assignments->sum(fn ($m) => (float) $m->budget_hours);

            $tasks = Task::query()
                ->where('tenant_id', $filters->tenant_id)
                ->whereIn('project_id', $projectIds)
                ->where('assignee_id', $userId)
                ->get();

            $openTasksCount = $tasks->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])->count();
            $completedTasksCount = $tasks->where('status', Task::STATUS_COMPLETED)->count();

            $timeLogQuery = TimeLog::query()
                ->where('tenant_id', $filters->tenant_id)
                ->where('user_id', $userId)
                ->whereIn('project_id', $projectIds);

            if ($filters->start_date && $filters->end_date) {
                $timeLogQuery->whereBetween('log_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()]);
            }

            $totalLoggedHours = (float) (clone $timeLogQuery)->sum('hours');
            $billableHours    = (float) (clone $timeLogQuery)->where('is_billable', true)->sum('hours');

            $billablePercent = $totalLoggedHours > 0
                ? (int) round(($billableHours / $totalLoggedHours) * 100)
                : 0;

            $burnPercent = $totalBudgetHours > 0
                ? (int) round(($totalLoggedHours / $totalBudgetHours) * 100)
                : null;

            $rows->push([
                'user_id'          => $userId,
                'user_name'        => $user->name,
                'user_email'       => $user->email,
                'project_count'    => count($projectIds),
                'open_tasks'       => $openTasksCount,
                'completed_tasks'  => $completedTasksCount,
                'budget_hours'     => $totalBudgetHours,
                'total_hours'      => $totalLoggedHours,
                'billable_hours'   => $billableHours,
                'billable_percent' => $billablePercent,
                'burn_percent'     => $burnPercent,
            ]);
        }

        return $rows->sortByDesc('total_hours')->values();
    }

    public function getResourceUtilizationReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $all = $this->getResourceUtilizationData($filters);
        $page = (int) request()->query('page', 1);

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Timesheet & Billability Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getTimesheetBillabilityQuery(ReportFilterDTO $filters): Builder
    {
        $query = TimeLog::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['project', 'task', 'user']);

        if ($filters->company_id) {
            $query->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $query->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $query->where('project_id', $filters->project_id);
        }
        if ($filters->user_id) {
            $query->where('user_id', $filters->user_id);
        }
        if ($filters->billable !== null) {
            $query->where('is_billable', $filters->billable);
        }
        if ($filters->approval_status) {
            $query->where('approval_status', $filters->approval_status);
        }
        if ($filters->invoiced_status) {
            if ($filters->invoiced_status === 'invoiced') {
                $query->where('is_invoiced', true);
            } elseif ($filters->invoiced_status === 'unbilled') {
                $query->where('is_invoiced', false);
            }
        }
        if ($filters->start_date && $filters->end_date) {
            $query->whereBetween('log_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()]);
        }

        return $query->orderBy('log_date', 'desc')->orderBy('id', 'desc');
    }

    public function getTimesheetBillabilityReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getTimesheetBillabilityQuery($filters)->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Issue & Defect Density Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getIssueDefectDensityQuery(ReportFilterDTO $filters): Builder
    {
        $query = Issue::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['project', 'task', 'assignee', 'reporter']);

        if ($filters->company_id) {
            $query->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $query->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $query->where('project_id', $filters->project_id);
        }
        if ($filters->severity) {
            $query->where('severity', $filters->severity);
        }
        if ($filters->priority) {
            $query->where('priority', $filters->priority);
        }
        if ($filters->status) {
            $query->where('status', $filters->status);
        }
        if ($filters->user_id) {
            $query->where('assignee_id', $filters->user_id);
        }
        if ($filters->start_date && $filters->end_date) {
            $query->whereBetween('created_at', [$filters->start_date, $filters->end_date]);
        }
        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('issue_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    public function getIssueDefectDensityReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getIssueDefectDensityQuery($filters)->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Milestone Variance Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getMilestoneVarianceQuery(ReportFilterDTO $filters): Builder
    {
        $query = Milestone::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['project', 'owner']);

        if ($filters->company_id) {
            $query->where('company_id', $filters->company_id);
        }
        if ($filters->branch_id) {
            $query->where('branch_id', $filters->branch_id);
        }
        if ($filters->project_id) {
            $query->where('project_id', $filters->project_id);
        }
        if ($filters->status) {
            $query->where('status', $filters->status);
        }
        if ($filters->start_date && $filters->end_date) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('due_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()])
                  ->orWhereBetween('created_at', [$filters->start_date, $filters->end_date]);
            });
        }
        if ($filters->slippage_only) {
            $today = Carbon::today()->toDateString();
            $query->where(function ($q) use ($today) {
                $q->where(fn ($sub) => $sub->whereNotNull('completed_at')->whereColumn('completed_at', '>', 'due_date'))
                  ->orWhere(fn ($sub) => $sub->whereNull('completed_at')->where('due_date', '<', $today)->whereNotIn('status', [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED]));
            });
        }
        if ($filters->search) {
            $search = $filters->search;
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('due_date', 'asc')->orderBy('id', 'desc');
    }

    public function getMilestoneVarianceReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getMilestoneVarianceQuery($filters)->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Budget vs. Actual Cost Report
    // ──────────────────────────────────────────────────────────────────────────

    public function getBudgetCostReportData(ReportFilterDTO $filters): Collection
    {
        $query = Project::query()
            ->where('tenant_id', $filters->tenant_id)
            ->with(['customer', 'changeRequests', 'timeLogs']);

        $this->applyProjectFilters($query, $filters);

        if ($filters->start_date && $filters->end_date) {
            $query->where(function ($q) use ($filters) {
                $q->whereBetween('start_date', [$filters->start_date->toDateString(), $filters->end_date->toDateString()])
                  ->orWhereBetween('created_at', [$filters->start_date, $filters->end_date]);
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->get();
        $rows = collect();

        foreach ($projects as $project) {
            $baseBudgetAmount = (float) ($project->budget_amount ?? 0);
            $baseBudgetHours  = (float) ($project->budget_hours ?? 0);

            $approvedCrs = $project->changeRequests->where('status', 'Approved');
            $crBudgetAmount = (float) $approvedCrs->sum('impact_budget');
            $crBudgetHours  = (float) $approvedCrs->sum('impact_budget_hours');

            $revisedBudgetAmount = $baseBudgetAmount + $crBudgetAmount;
            $revisedBudgetHours  = $baseBudgetHours + $crBudgetHours;

            $timeLogs = $project->timeLogs;
            $actualHours = (float) $timeLogs->sum('hours');
            $actualCost  = (float) $timeLogs->sum(fn ($tl) => (float) $tl->hours * ((float) ($tl->hourly_rate ?? 0)));

            $costVariance  = $revisedBudgetAmount - $actualCost;
            $hoursVariance = $revisedBudgetHours - $actualHours;

            $isOverCost  = $revisedBudgetAmount > 0 && $actualCost > $revisedBudgetAmount;
            $isOverHours = $revisedBudgetHours > 0 && $actualHours > $revisedBudgetHours;

            if ($filters->cost_overrun_only && !$isOverCost && !$isOverHours) {
                continue;
            }

            $costBurnPercent = $revisedBudgetAmount > 0
                ? (int) round(($actualCost / $revisedBudgetAmount) * 100)
                : null;

            $hoursBurnPercent = $revisedBudgetHours > 0
                ? (int) round(($actualHours / $revisedBudgetHours) * 100)
                : null;

            $rows->push([
                'id'                     => $project->id,
                'project_code'           => $project->project_code,
                'name'                   => $project->name,
                'client_name'            => $project->customer?->name ?? '—',
                'status'                 => $project->status,
                'base_budget_amount'     => $baseBudgetAmount,
                'cr_budget_amount'       => $crBudgetAmount,
                'revised_budget_amount'  => $revisedBudgetAmount,
                'actual_cost'            => $actualCost,
                'cost_variance'          => $costVariance,
                'cost_burn_percent'      => $costBurnPercent,
                'base_budget_hours'      => $baseBudgetHours,
                'cr_budget_hours'        => $crBudgetHours,
                'revised_budget_hours'   => $revisedBudgetHours,
                'actual_hours'           => $actualHours,
                'hours_variance'         => $hoursVariance,
                'hours_burn_percent'     => $hoursBurnPercent,
            ]);
        }

        return $rows;
    }

    public function getBudgetCostReport(ReportFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $all = $this->getBudgetCostReportData($filters);
        $page = (int) request()->query('page', 1);

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal Helper
    // ──────────────────────────────────────────────────────────────────────────

    private function applyProjectFilters(Builder $query, ReportFilterDTO $filters): void
    {
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
        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('project_code', 'like', "%{$search}%");
            });
        }
    }
}
