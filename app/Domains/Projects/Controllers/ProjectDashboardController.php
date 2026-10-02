<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\DTO\ReportFilterDTO;
use App\Domains\Projects\Exports\ProjectReportExport;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ProjectDashboardService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ProjectDashboardController extends Controller
{
    public function __construct(
        private readonly ProjectDashboardService $dashboardService,
        private readonly AccessService $access,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeDashboard();

        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $kpis = $this->dashboardService->getPortfolioKpis($filters);
        $healthList = $this->dashboardService->getProjectsHealthList($filters);
        $workload = $this->dashboardService->getResourceWorkload($filters);
        $upcomingMilestones = $this->dashboardService->getUpcomingMilestones($filters);
        $defectDist = $this->dashboardService->getIssueSeverityDistribution($filters);

        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'project_code', 'name']);
        $clients = Customer::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return view('modules.projects.dashboard', compact(
            'kpis',
            'healthList',
            'workload',
            'upcomingMilestones',
            'defectDist',
            'filters',
            'projects',
            'clients',
            'users'
        ));
    }

    public function export(Request $request, string $format): Response
    {
        $this->authorizeDashboard();

        abort_unless(in_array($format, ['csv', 'xlsx'], true), 400, 'Unsupported export format.');

        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $filters = ReportFilterDTO::fromRequest($request, $tenantId);

        $kpis = $this->dashboardService->getPortfolioKpis($filters);
        $healthList = $this->dashboardService->getProjectsHealthList($filters, 50);

        $filename = 'Project_Executive_Dashboard_' . now()->format('Ymd_Hi');

        if ($format === 'csv') {
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            ];

            $callback = function () use ($kpis, $healthList) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Project Management Executive Dashboard']);
                fputcsv($file, ['Exported At', now()->format('d M Y H:i:s')]);
                fputcsv($file, []);
                fputcsv($file, ['KPI Metric', 'Value']);
                fputcsv($file, ['Total Projects', $kpis->total_projects]);
                fputcsv($file, ['Active Projects', $kpis->active_projects]);
                fputcsv($file, ['Completed Projects', $kpis->completed_projects]);
                fputcsv($file, ['On Hold Projects', $kpis->on_hold_projects]);
                fputcsv($file, ['Portfolio Health Score %', $kpis->portfolio_health_score . '%']);
                fputcsv($file, ['Total Budget ($)', number_format($kpis->total_budget_amount, 2)]);
                fputcsv($file, ['Total Incurred Cost ($)', number_format($kpis->total_incurred_cost, 2)]);
                fputcsv($file, ['Budget Consumed %', ($kpis->cost_consumption_percent ?? 0) . '%']);
                fputcsv($file, ['Total Budget Hours', number_format($kpis->total_budget_hours, 2)]);
                fputcsv($file, ['Total Tracked Hours', number_format($kpis->total_tracked_hours, 2)]);
                fputcsv($file, ['Hours Consumed %', ($kpis->hours_consumption_percent ?? 0) . '%']);
                fputcsv($file, ['Overdue Deliverables (Tasks)', $kpis->overdue_tasks_count]);
                fputcsv($file, ['Overdue Milestones', $kpis->overdue_milestones_count]);
                fputcsv($file, ['Open Issues', $kpis->open_issues_count]);
                fputcsv($file, ['Critical Defects', $kpis->critical_issues_count]);
                fputcsv($file, ['Unbilled Approved Hours', number_format($kpis->unbilled_approved_hours, 2)]);
                fputcsv($file, ['Unbilled Amount ($)', number_format($kpis->unbilled_amount, 2)]);
                fputcsv($file, []);
                fputcsv($file, ['Active Projects Health', 'Client', 'Health Status', 'Progress %', 'Budget Hours', 'Actual Hours']);
                foreach ($healthList as $p) {
                    fputcsv($file, [
                        $p['project_code'] . ' - ' . $p['name'],
                        $p['client_name'],
                        strtoupper($p['health_state']),
                        $p['progress'] . '%',
                        $p['budget_hours'],
                        $p['actual_hours'],
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // Excel export
        $rows = $healthList->map(fn ($p) => [
            'Project Code'  => $p['project_code'],
            'Project Name'  => $p['name'],
            'Client'        => $p['client_name'],
            'Health Status' => strtoupper($p['health_state']),
            'Progress %'    => $p['progress'] . '%',
            'Budget Hours'  => $p['budget_hours'],
            'Actual Hours'  => $p['actual_hours'],
        ]);

        return Excel::download(new ProjectReportExport('summary', $rows), $filename . '.xlsx');
    }

    private function authorizeDashboard(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $tenantId = current_tenant_id() ?? ($user->tenant_id ?? 1);

        if ($user->role === 'super_admin' || $user->role === 'admin') {
            return;
        }

        abort_unless(
            $this->access->allows($user, 'projects.dashboard.view', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'projects.projects.view', ['tenant_id' => $tenantId]),
            403,
            'Unauthorized access to Project Dashboard.'
        );
    }
}
