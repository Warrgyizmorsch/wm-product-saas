<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\DTO\ReportFilterDTO;
use App\Domains\Projects\Exports\ProjectReportExport;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\ProjectReportService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ProjectReportController extends Controller
{
    public function __construct(
        private readonly ProjectReportService $reportService,
        private readonly AccessService $access,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeReports();

        $reports = [
            [
                'id'          => ProjectReportService::REPORT_SUMMARY,
                'name'        => __('projects.report_summary_name') ?: 'Project Summary Report',
                'description' => __('projects.report_summary_desc') ?: 'Comprehensive portfolio status, client attribution, baseline budgets, and progress rollups.',
                'icon'        => 'feather-briefcase',
                'route'       => 'projects.reports.summary',
            ],
            [
                'id'          => ProjectReportService::REPORT_TASK_STATUS,
                'name'        => __('projects.report_task_status_name') ?: 'Task Status Report',
                'description' => __('projects.report_task_status_desc') ?: 'Detailed breakdown of tasks by execution state, priorities, assignees, and schedule variance.',
                'icon'        => 'feather-check-square',
                'route'       => 'projects.reports.task-status',
            ],
            [
                'id'          => ProjectReportService::REPORT_RESOURCE_UTILIZATION,
                'name'        => __('projects.report_utilization_name') ?: 'Resource Utilization & Productivity',
                'description' => __('projects.report_utilization_desc') ?: 'Team member capacity allocation, logged hours, billable ratios, and task workload.',
                'icon'        => 'feather-users',
                'route'       => 'projects.reports.resource-utilization',
            ],
            [
                'id'          => ProjectReportService::REPORT_TIMESHEET_BILLABILITY,
                'name'        => __('projects.report_timesheet_name') ?: 'Timesheet & Billability Report',
                'description' => __('projects.report_timesheet_desc') ?: 'Detailed labor worklogs, billable vs non-billable hours, hourly rates, and approval states.',
                'icon'        => 'feather-clock',
                'route'       => 'projects.reports.timesheet-billability',
            ],
            [
                'id'          => ProjectReportService::REPORT_ISSUE_DEFECT_DENSITY,
                'name'        => __('projects.report_issues_name') ?: 'Issue & Defect Density Report',
                'description' => __('projects.report_issues_desc') ?: 'Quality defect tracking, severity distributions, resolution velocity, and mean time to resolve.',
                'icon'        => 'feather-alert-triangle',
                'route'       => 'projects.reports.issue-defect-density',
            ],
            [
                'id'          => ProjectReportService::REPORT_MILESTONE_VARIANCE,
                'name'        => __('projects.report_variance_name') ?: 'Milestone Variance Report',
                'description' => __('projects.report_variance_desc') ?: 'Milestone delivery performance, planned vs actual finish dates, and schedule slippage.',
                'icon'        => 'feather-flag',
                'route'       => 'projects.reports.milestone-variance',
            ],
            [
                'id'          => ProjectReportService::REPORT_BUDGET_COST,
                'name'        => __('projects.report_budget_name') ?: 'Budget vs. Actual Cost Report',
                'description' => __('projects.report_budget_desc') ?: 'Financial budget variance, approved Change Request scope adjustments, and incurred labor costs.',
                'icon'        => 'feather-dollar-sign',
                'route'       => 'projects.reports.budget-cost',
            ],
        ];

        return view('modules.projects.reports.index', compact('reports'));
    }

    public function summary(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $projects = $this->reportService->getProjectSummaryReport($filters);
        $clients = Customer::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $statuses = Project::STATUSES;
        $priorities = Project::PRIORITIES;

        return view('modules.projects.reports.summary', compact(
            'projects', 'clients', 'users', 'statuses', 'priorities', 'filters'
        ));
    }

    public function taskStatus(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $tasks = $this->reportService->getTaskStatusReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $statuses = Task::STATUSES;
        $priorities = Task::PRIORITIES;

        return view('modules.projects.reports.task-status', compact(
            'tasks', 'projects', 'users', 'statuses', 'priorities', 'filters'
        ));
    }

    public function resourceUtilization(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $members = $this->reportService->getResourceUtilizationReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return view('modules.projects.reports.resource-utilization', compact(
            'members', 'projects', 'users', 'filters'
        ));
    }

    public function timesheetBillability(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $timeLogs = $this->reportService->getTimesheetBillabilityReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $approvalStatuses = [TimeLog::STATUS_PENDING, TimeLog::STATUS_APPROVED, TimeLog::STATUS_REJECTED];

        return view('modules.projects.reports.timesheet-billability', compact(
            'timeLogs', 'projects', 'users', 'approvalStatuses', 'filters'
        ));
    }

    public function issueDefectDensity(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $issues = $this->reportService->getIssueDefectDensityReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $severities = Issue::SEVERITIES;
        $priorities = Issue::PRIORITIES;
        $statuses = Issue::STATUSES;

        return view('modules.projects.reports.issue-defect-density', compact(
            'issues', 'projects', 'users', 'severities', 'priorities', 'statuses', 'filters'
        ));
    }

    public function milestoneVariance(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $milestones = $this->reportService->getMilestoneVarianceReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $statuses = Milestone::STATUSES;

        return view('modules.projects.reports.milestone-variance', compact(
            'milestones', 'projects', 'statuses', 'filters'
        ));
    }

    public function budgetCost(Request $request): View
    {
        $this->authorizeReports();
        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $rows = $this->reportService->getBudgetCostReport($filters);
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $clients = Customer::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $statuses = Project::STATUSES;

        return view('modules.projects.reports.budget-cost', compact(
            'rows', 'projects', 'clients', 'statuses', 'filters'
        ));
    }

    public function export(Request $request, string $report, string $format): Response
    {
        $this->authorizeReports();

        abort_unless(array_key_exists($report, ProjectReportService::REPORT_IDENTIFIERS), 404, 'Invalid report identifier.');
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 400, 'Unsupported export format.');

        $tenantId = $this->getTenantId();
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $data = match ($report) {
            ProjectReportService::REPORT_SUMMARY               => $this->reportService->getProjectSummaryQuery($filters)->get(),
            ProjectReportService::REPORT_TASK_STATUS           => $this->reportService->getTaskStatusQuery($filters)->get(),
            ProjectReportService::REPORT_RESOURCE_UTILIZATION  => $this->reportService->getResourceUtilizationData($filters),
            ProjectReportService::REPORT_TIMESHEET_BILLABILITY => $this->reportService->getTimesheetBillabilityQuery($filters)->get(),
            ProjectReportService::REPORT_ISSUE_DEFECT_DENSITY  => $this->reportService->getIssueDefectDensityQuery($filters)->get(),
            ProjectReportService::REPORT_MILESTONE_VARIANCE    => $this->reportService->getMilestoneVarianceQuery($filters)->get(),
            ProjectReportService::REPORT_BUDGET_COST           => $this->reportService->getBudgetCostReportData($filters),
            default                                            => collect(),
        };

        $filename = ProjectReportExport::filename($report);

        if ($format === 'csv') {
            return Excel::download(
                new ProjectReportExport($report, $data),
                $filename . '.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new ProjectReportExport($report, $data),
            $filename . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    private function authorizeReports(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $tenantId = $this->getTenantId();

        if ($user->role === 'super_admin' || $user->role === 'admin') {
            return;
        }

        abort_unless(
            $this->access->allows($user, 'projects.reports.view', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'projects.projects.view', ['tenant_id' => $tenantId]),
            403,
            'Unauthorized access to Project Reports.'
        );
    }

    private function getTenantId(): int
    {
        return current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
    }
}
