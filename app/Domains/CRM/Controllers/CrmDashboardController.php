<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\Quotation;
use App\Domains\HRMS\Models\Company;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CrmDashboardController extends Controller
{
    public const VIEWS = [
        'overview'   => 'Executive Overview',
        'operations' => 'Sales Velocity & Reps',
    ];

    public function index(Request $request): View
    {
        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $data = $this->buildDashboardData($request, $tenantId);

        return view('modules.crm.dashboard.index', $data);
    }

    public function export(Request $request, string $format): Response
    {
        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $data = $this->buildDashboardData($request, $tenantId);
        $filename = sprintf('CRM_Dashboard_%s_%s', $data['startDate']->format('Ymd'), $data['endDate']->format('Ymd'));

        if ($format === 'pdf') {
            return Pdf::loadView('modules.crm.dashboard.pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download($filename . '.pdf');
        }

        // CSV Export Format
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['CRM Executive Summary Report']);
            fputcsv($file, ['Period', $data['startDate']->format('d M Y') . ' to ' . $data['endDate']->format('d M Y')]);
            fputcsv($file, []);
            fputcsv($file, ['KPI Metric', 'Value']);
            fputcsv($file, ['Total Leads Captured', $data['currentLeadsCount']]);
            fputcsv($file, ['Active Revenue Pipeline', $data['pipelineValue']]);
            fputcsv($file, ['Open Deals Count', $data['openDealsCount']]);
            fputcsv($file, ['Closed Won Revenue', $data['wonRevenue']]);
            fputcsv($file, ['Won Deals Count', $data['wonCount']]);
            fputcsv($file, ['Win Rate %', $data['winRate'] . '%']);
            fputcsv($file, ['Quotations Value', $data['totalQuotationValue']]);
            fputcsv($file, ['WhatsApp Bot Leads', $data['whatsappLeadsCount']]);
            fputcsv($file, []);
            fputcsv($file, ['Sales Funnel Stage', 'Count']);
            foreach ($data['funnelStages'] as $stage => $count) {
                fputcsv($file, [$stage, $count]);
            }
            fputcsv($file, []);
            fputcsv($file, ['Top Deal Number', 'Client', 'Stage', 'Value']);
            foreach ($data['topOpenDeals'] as $deal) {
                fputcsv($file, [$deal->deal_number, $deal->title, $deal->stage, $deal->estimated_value]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildDashboardData(Request $request, int $tenantId): array
    {
        $preset = $request->get('preset', 'this_month');
        $fromDate = $request->get('from');
        $toDate = $request->get('to');
        $companyScope = $request->get('company_scope', 'current');
        $ownerId = $request->get('owner_id');
        $leadType = $request->get('lead_type');
        $activeView = $request->get('view', 'overview');

        // Resolve Date Periods
        [$startDate, $endDate, $prevStartDate, $prevEndDate] = $this->resolveDatePeriod($preset, $fromDate, $toDate);

        // Base Queries scoped by Tenant
        $leadsQuery = Lead::where('tenant_id', $tenantId);
        $dealsQuery = CrmDeal::where('tenant_id', $tenantId);
        $quotationsQuery = Quotation::where('tenant_id', $tenantId);

        // Apply Company Scope Filter
        if ($companyScope === 'current' && current_company_id()) {
            $companyId = current_company_id();
            $leadsQuery->where('company_id', $companyId);
            $dealsQuery->where('company_id', $companyId);
            $quotationsQuery->where('company_id', $companyId);
        }

        // Apply Lead Owner Filter
        if ($ownerId) {
            $leadsQuery->where('lead_owner_id', $ownerId);
            $dealsQuery->where('owner_id', $ownerId);
            $quotationsQuery->where('sales_person_id', $ownerId);
        }

        // Apply Lead Type Filter (B2B vs B2C)
        if ($leadType) {
            $leadsQuery->where('lead_type', $leadType);
        }

        $periodLeadsQuery = (clone $leadsQuery)->whereBetween('created_at', [$startDate, $endDate]);
        $periodDealsQuery = (clone $dealsQuery)->whereBetween('created_at', [$startDate, $endDate]);
        $periodQuotationsQuery = (clone $quotationsQuery)->whereBetween('created_at', [$startDate, $endDate]);

        // 1. KPI Metrics
        $currentLeadsCount = (clone $periodLeadsQuery)->count();
        $prevLeadsCount = (clone $leadsQuery)->whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
        $leadsGrowth = $this->calculatePercentageChange($prevLeadsCount, $currentLeadsCount);

        // Open Deals & Pipeline Value
        $openDeals = (clone $periodDealsQuery)
            ->whereNotIn(DB::raw('LOWER(stage)'), ['won', 'closed won', 'lost', 'closed lost'])
            ->get();
        $pipelineValue = $openDeals->sum('estimated_value');
        $openDealsCount = $openDeals->count();

        // Won Revenue & Win Rate
        $currentWonDeals = (clone $periodDealsQuery)
            ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won'])
            ->get();
        $prevWonDealsSum = (clone $dealsQuery)
            ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won'])
            ->whereBetween('updated_at', [$prevStartDate, $prevEndDate])
            ->sum('estimated_value');

        $wonRevenue = $currentWonDeals->sum('estimated_value');
        $revenueGrowth = $this->calculatePercentageChange($prevWonDealsSum, $wonRevenue);

        $closedDealsCount = (clone $periodDealsQuery)
            ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won', 'lost', 'closed lost'])
            ->count();
        $wonCount = $currentWonDeals->count();
        $winRate = $closedDealsCount > 0 ? round(($wonCount / $closedDealsCount) * 100, 1) : 0;

        // Quotations Metrics
        $currentQuotations = (clone $periodQuotationsQuery)->get();
        $totalQuotationsCount = $currentQuotations->count();
        $totalQuotationValue = $currentQuotations->sum('total_amount');
        $pendingQuotationsCount = (clone $periodQuotationsQuery)->whereIn('status', ['draft', 'pending_approval', 'sent'])->count();

        // WhatsApp Bot Leads & Auto-Qualification
        $whatsappLeads = (clone $periodLeadsQuery)
            ->where('source', 'WhatsApp Bot')
            ->get();
        $whatsappLeadsCount = $whatsappLeads->count();
        $whatsappQualifiedCount = $whatsappLeads->whereNotIn('status', ['New', 'Lost'])->count();
        $whatsappQualificationRate = $whatsappLeadsCount > 0 ? round(($whatsappQualifiedCount / $whatsappLeadsCount) * 100, 1) : 0;

        // 2. Funnel Breakdown
        $allLeads = (clone $periodLeadsQuery)->get();
        $leadStatusCounts = $allLeads->groupBy(fn($l) => ucfirst(strtolower($l->status)))
            ->map(fn($group) => $group->count());

        $funnelStages = [
            'New'           => $leadStatusCounts->get('New', 0),
            'Contacted'     => $leadStatusCounts->get('Contacted', 0),
            'Qualified'     => $leadStatusCounts->get('Qualified', 0),
            'Quotation'     => (clone $periodQuotationsQuery)->count(),
            'Converted/Won' => $leadStatusCounts->get('Converted', 0) + $leadStatusCounts->get('Won', 0),
        ];

        // 3. Deal Pipeline Stage Breakdown
        $dealStages = (clone $periodDealsQuery)
            ->select('stage', DB::raw('COUNT(*) as count'), DB::raw('SUM(estimated_value) as total_value'))
            ->groupBy('stage')
            ->get()
            ->keyBy(fn($item) => strtolower($item->stage));

        // 4. Monthly Trend Analytics (Last 6 Months)
        $monthlyTrend = $this->getMonthlyTrend($tenantId, $companyScope, $ownerId);

        // 5. Lead Source Distribution
        $sourceBreakdown = (clone $periodLeadsQuery)
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->get()
            ->pluck('count', 'source')
            ->toArray();

        // 6. Win / Loss Reason Breakdown
        $winLossReasons = (clone $periodDealsQuery)
            ->select('close_reason', DB::raw('COUNT(*) as count'))
            ->whereNotNull('close_reason')
            ->where('close_reason', '!=', '')
            ->groupBy('close_reason')
            ->get()
            ->pluck('count', 'close_reason')
            ->toArray();

        // 7. Sales Leaderboard (Reps Performance)
        $salesLeaderboard = $this->getSalesLeaderboard($tenantId, $startDate, $endDate);

        // 8. Actionable Datasets
        $topOpenDeals = (clone $periodDealsQuery)
            ->with(['account', 'contact'])
            ->whereNotIn(DB::raw('LOWER(stage)'), ['won', 'closed won', 'lost', 'closed lost'])
            ->orderByDesc('estimated_value')
            ->take(6)
            ->get();

        $recentLeads = (clone $periodLeadsQuery)
            ->with(['crmAccount'])
            ->latest('id')
            ->take(6)
            ->get();

        $recentQuotations = (clone $periodQuotationsQuery)
            ->latest('id')
            ->take(6)
            ->get();

        $pendingFollowups = LeadFollowup::whereHas('lead', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'scheduled')
            ->orderBy('followup_date', 'asc')
            ->take(6)
            ->get();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $totalAccounts = CrmAccount::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate])->count();

        $salesOwners = User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $companies = Company::where('tenant_id', $tenantId)->get(['id', 'company_name']);

        $query = array_filter($request->only(['preset', 'from', 'to', 'company_scope', 'owner_id', 'lead_type', 'view']), fn($v) => $v !== null && $v !== '');

        return compact(
            'preset',
            'startDate',
            'endDate',
            'companyScope',
            'ownerId',
            'leadType',
            'activeView',
            'currentLeadsCount',
            'leadsGrowth',
            'pipelineValue',
            'openDealsCount',
            'wonRevenue',
            'revenueGrowth',
            'winRate',
            'wonCount',
            'totalQuotationsCount',
            'totalQuotationValue',
            'pendingQuotationsCount',
            'whatsappLeadsCount',
            'whatsappQualificationRate',
            'funnelStages',
            'dealStages',
            'monthlyTrend',
            'sourceBreakdown',
            'winLossReasons',
            'salesLeaderboard',
            'topOpenDeals',
            'recentLeads',
            'recentQuotations',
            'pendingFollowups',
            'totalCustomers',
            'totalAccounts',
            'salesOwners',
            'companies',
            'query'
        );
    }

    private function resolveDatePeriod(string $preset, ?string $from, ?string $to): array
    {
        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $prevStart = $now->copy()->subDay()->startOfDay();
                $prevEnd = $now->copy()->subDay()->endOfDay();
                break;

            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $prevStart = $now->copy()->subMonths(2)->startOfMonth();
                $prevEnd = $now->copy()->subMonths(2)->endOfMonth();
                break;

            case 'this_quarter':
                $start = $now->copy()->startOfQuarter();
                $end = $now->copy()->endOfQuarter();
                $prevStart = $now->copy()->subQuarter()->startOfQuarter();
                $prevEnd = $now->copy()->subQuarter()->endOfQuarter();
                break;

            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $prevStart = $now->copy()->subYear()->startOfYear();
                $prevEnd = $now->copy()->subYear()->endOfYear();
                break;

            case 'all_time':
                $start = Carbon::create(2020, 1, 1)->startOfDay();
                $end = $now->copy()->endOfDay();
                $prevStart = Carbon::create(2019, 1, 1)->startOfDay();
                $prevEnd = Carbon::create(2019, 12, 31)->endOfDay();
                break;

            case 'custom':
                $start = $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth();
                $end = $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay();
                $diffDays = $start->diffInDays($end) ?: 1;
                $prevStart = $start->copy()->subDays($diffDays + 1);
                $prevEnd = $start->copy()->subDay()->endOfDay();
                break;

            case 'this_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $prevStart = $now->copy()->subMonth()->startOfMonth();
                $prevEnd = $now->copy()->subMonth()->endOfMonth();
                break;
        }

        return [$start, $end, $prevStart, $prevEnd];
    }

    private function calculatePercentageChange(float|int $previous, float|int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function getMonthlyTrend(int $tenantId, string $companyScope = 'current', ?int $ownerId = null): array
    {
        $months = [];
        $leadsData = [];
        $revenueData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            $leadsQ = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$start, $end]);
            $dealsQ = CrmDeal::where('tenant_id', $tenantId)->whereBetween('updated_at', [$start, $end]);

            if ($companyScope === 'current' && current_company_id()) {
                $leadsQ->where('company_id', current_company_id());
                $dealsQ->where('company_id', current_company_id());
            }
            if ($ownerId) {
                $leadsQ->where('lead_owner_id', $ownerId);
                $dealsQ->where('owner_id', $ownerId);
            }

            $months[] = $monthDate->format('M Y');
            $leadsData[] = $leadsQ->count();
            $revenueData[] = $dealsQ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won'])->sum('estimated_value');
        }

        return [
            'labels'  => $months,
            'leads'   => $leadsData,
            'revenue' => $revenueData,
        ];
    }

    private function getSalesLeaderboard(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $users = User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $leaderboard = [];

        foreach ($users as $user) {
            $leadsCount = Lead::where('tenant_id', $tenantId)
                ->where('lead_owner_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $deals = CrmDeal::where('tenant_id', $tenantId)
                ->where('owner_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $dealsCount = $deals->count();
            $wonDeals = $deals->filter(fn($d) => in_array(strtolower((string)$d->stage), ['won', 'closed won']));
            $wonRevenue = $wonDeals->sum('estimated_value');
            $wonCount = $wonDeals->count();

            if ($leadsCount > 0 || $dealsCount > 0) {
                $leaderboard[] = [
                    'name'         => $user->name,
                    'leads_count'  => $leadsCount,
                    'deals_count'  => $dealsCount,
                    'won_count'    => $wonCount,
                    'won_revenue'  => $wonRevenue,
                    'win_rate'     => $dealsCount > 0 ? round(($wonCount / $dealsCount) * 100, 1) : 0,
                ];
            }
        }

        usort($leaderboard, fn($a, $b) => $b['won_revenue'] <=> $a['won_revenue']);
        return array_slice($leaderboard, 0, 5);
    }
}
