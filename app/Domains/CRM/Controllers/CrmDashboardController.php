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
        $this->authorize('viewAny', Lead::class);

        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $data = $this->buildDashboardData($request, $tenantId);

        return view('modules.crm.dashboard.index', $data);
    }

    public function export(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Lead::class);

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
            fputcsv($file, ['3rd-Party & Web Leads', $data['thirdPartyLeadsCount']]);
            fputcsv($file, ['Meta Ads Leads', $data['metaLeadsCount']]);
            fputcsv($file, ['WhatsApp Bot Leads', $data['whatsappLeadsCount']]);
            fputcsv($file, ['Website & Web-to-Lead', $data['webLeadsCount']]);
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
        $user = auth()->user();
        $access = app(\App\Services\Access\AccessService::class);
        $canViewTenantLeads = $user ? $access->allows($user, 'crm.leads.view', ['tenant_id' => $tenantId]) : false;

        $preset = $request->get('preset', 'this_month');
        $fromDate = $request->get('from');
        $toDate = $request->get('to');
        $companyScope = $request->get('company_scope', 'current');
        $ownerId = $request->get('owner_id');

        // If user does not have tenant-wide view, force their own owner_id
        if (!$canViewTenantLeads && $user) {
            $ownerId = $user->id;
        }

        $leadType = $request->get('lead_type', $request->get('amp;lead_type'));
        $campaign = $request->get('campaign', $request->get('amp;campaign'));
        $adset = $request->get('adset', $request->get('amp;adset'));
        $adName = $request->get('ad_name', $request->get('amp;ad_name'));
        $campaignDimension = $request->get('campaign_dimension', $request->get('amp;campaign_dimension', 'campaign'));
        $activeView = $request->get('view', $request->get('amp;view', 'overview'));

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
            $dealsQuery->whereHas('lead', fn($l) => $l->where('lead_type', $leadType));
            $quotationsQuery->where(function($q) use ($leadType) {
                $q->whereHas('lead', fn($l) => $l->where('lead_type', $leadType))
                  ->orWhereHas('deal.lead', fn($l) => $l->where('lead_type', $leadType));
            });
        }

        // Apply Campaign Filter
        if ($campaign) {
            $leadsQuery->where('utm_campaign', $campaign);
            $dealsQuery->where(function($q) use ($campaign) {
                $q->whereHas('lead', fn($l) => $l->where('utm_campaign', $campaign))
                  ->orWhere('lead_source', $campaign);
            });
            $quotationsQuery->where(function($q) use ($campaign) {
                $q->whereHas('lead', fn($l) => $l->where('utm_campaign', $campaign))
                  ->orWhereHas('deal.lead', fn($l) => $l->where('utm_campaign', $campaign))
                  ->orWhereHas('deal', fn($d) => $d->where('lead_source', $campaign));
            });
        }

        // Apply AdSet Filter
        if ($adset) {
            $leadsQuery->where('utm_term', $adset);
            $dealsQuery->whereHas('lead', fn($l) => $l->where('utm_term', $adset));
            $quotationsQuery->where(function($q) use ($adset) {
                $q->whereHas('lead', fn($l) => $l->where('utm_term', $adset))
                  ->orWhereHas('deal.lead', fn($l) => $l->where('utm_term', $adset));
            });
        }

        // Apply Ad Name Filter
        if ($adName) {
            $leadsQuery->where('utm_content', $adName);
            $dealsQuery->whereHas('lead', fn($l) => $l->where('utm_content', $adName));
            $quotationsQuery->where(function($q) use ($adName) {
                $q->whereHas('lead', fn($l) => $l->where('utm_content', $adName))
                  ->orWhereHas('deal.lead', fn($l) => $l->where('utm_content', $adName));
            });
        }

        $periodLeadsQuery = (clone $leadsQuery)->whereBetween('created_at', [$startDate, $endDate]);
        $periodDealsQuery = (clone $dealsQuery)->whereBetween('created_at', [$startDate, $endDate]);
        $periodQuotationsQuery = (clone $quotationsQuery)->whereBetween('created_at', [$startDate, $endDate]);

        // 1. KPI Metrics
        $currentLeadsCount = (clone $periodLeadsQuery)->count(); // index-only count, cheap
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

        // Every lead KPI on this page comes from ONE scan of the period's leads.
        // They used to be ~10 separate queries (and before that, whole lead lists
        // loaded into PHP), each re-reading every lead: ~1 s apiece at 1 lakh leads.
        // "Qualified" keeps the old rule: any status other than New/Lost, NULL included.
        $sourceGroups = [
            'whatsapp' => ["source IN ('WhatsApp Bot', 'WhatsApp', 'whatsapp') OR utm_source = 'whatsapp'"],
            'meta' => ["source IN ('Meta Ads', 'Facebook / Instagram', 'Meta', 'Facebook', 'Instagram') OR utm_source IN ('meta', 'facebook', 'instagram')"],
            'web' => ["source IN ('Web Form', 'Website', 'Web / Inbound', 'Web-to-Lead', 'Widget', 'Embed Widget', 'Website Form') OR utm_source IN ('website', 'web', 'embed')"],
            'third' => ["source IN ('Meta Ads', 'Facebook / Instagram', 'Meta', 'Facebook', 'Instagram', 'WhatsApp Bot', 'WhatsApp', 'whatsapp', "
                . "'Web Form', 'Website', 'Web / Inbound', 'Web-to-Lead', 'Widget', 'Embed Widget', 'Website Form', "
                . "'Justdial', 'JustDial', 'IndiaMART', 'Indiamart', 'TradeIndia', 'Tradeindia', 'Google Ads', "
                . "'API', 'Webhook', 'Third-Party', 'Third Party', 'Portal') OR utm_source IS NOT NULL"],
        ];
        $qualifiedSql = "(status IS NULL OR status NOT IN ('New', 'Lost'))";
        $wonSql = "status = 'Won'";
        $selects = [
            'COUNT(*) AS all_total',
            "SUM(CASE WHEN {$qualifiedSql} THEN 1 ELSE 0 END) AS all_qualified",
            "SUM(CASE WHEN {$wonSql} THEN 1 ELSE 0 END) AS all_won",
            "SUM(CASE WHEN (crm_deal_id IS NOT NULL AND crm_deal_id <> 0) OR LOWER(status) IN ('qualified', 'dealing', 'won') THEN 1 ELSE 0 END) AS converted_total",
            "SUM(CASE WHEN (crm_deal_id IS NOT NULL AND crm_deal_id <> 0) OR LOWER(status) IN ('qualified', 'dealing', 'won') THEN COALESCE(expected_amount, 0) ELSE 0 END) AS converted_value",
        ];
        foreach (['new', 'contacted', 'qualified', 'dealing', 'won'] as $statusKey) {
            $selects[] = "SUM(CASE WHEN LOWER(status) = '{$statusKey}' THEN 1 ELSE 0 END) AS status_{$statusKey}";
        }
        foreach ($sourceGroups as $key => [$condition]) {
            $selects[] = "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) AS {$key}_total";
            $selects[] = "SUM(CASE WHEN ({$condition}) AND {$qualifiedSql} THEN 1 ELSE 0 END) AS {$key}_qualified";
            $selects[] = "SUM(CASE WHEN ({$condition}) AND {$wonSql} THEN 1 ELSE 0 END) AS {$key}_won";
            $selects[] = "SUM(CASE WHEN ({$condition}) AND {$wonSql} THEN COALESCE(expected_amount, 0) ELSE 0 END) AS {$key}_won_revenue";
        }
        $leadKpis = (clone $periodLeadsQuery)->toBase()->selectRaw(implode(",\n", $selects))->first();
        $kpi = fn (string $column): int => (int) ($leadKpis->{$column} ?? 0);
        $kpiMoney = fn (string $column): float => (float) ($leadKpis->{$column} ?? 0);

        // WhatsApp Bot Leads & Auto-Qualification
        $whatsappLeadsCount = $kpi('whatsapp_total');
        $whatsappQualifiedCount = $kpi('whatsapp_qualified');
        $whatsappQualificationRate = $whatsappLeadsCount > 0 ? round(($whatsappQualifiedCount / $whatsappLeadsCount) * 100, 1) : 0;
        $whatsappWonCount = $kpi('whatsapp_won');
        $whatsappWonRevenue = $kpiMoney('whatsapp_won_revenue');

        // Meta (Facebook & Instagram) Leads & Conversion
        $metaLeadsCount = $kpi('meta_total');
        $metaQualifiedCount = $kpi('meta_qualified');
        $metaQualifiedRate = $metaLeadsCount > 0 ? round(($metaQualifiedCount / $metaLeadsCount) * 100, 1) : 0;
        $metaWonCount = $kpi('meta_won');
        $metaWonRevenue = $kpiMoney('meta_won_revenue');
        $metaWinRate = $metaLeadsCount > 0 ? round(($metaWonCount / $metaLeadsCount) * 100, 1) : 0;

        // Website / Embed Forms / Web-to-Lead Inbound Leads
        $webLeadsCount = $kpi('web_total');
        $webQualifiedCount = $kpi('web_qualified');
        $webWonRevenue = $kpiMoney('web_won_revenue');

        // Generic Third-Party & Inbound Ingestion (Meta, WhatsApp, Web, Justdial, IndiaMART, APIs, etc.)
        $thirdPartyLeadsCount = $kpi('third_total');
        $thirdPartyQualifiedCount = $kpi('third_qualified');
        $thirdPartyQualifiedRate = $thirdPartyLeadsCount > 0 ? round(($thirdPartyQualifiedCount / $thirdPartyLeadsCount) * 100, 1) : 0;
        $thirdPartyWonCount = $kpi('third_won');
        $thirdPartyWonRevenue = $kpiMoney('third_won_revenue');
        $thirdPartyWinRate = $thirdPartyLeadsCount > 0 ? round(($thirdPartyWonCount / $thirdPartyLeadsCount) * 100, 1) : 0;

        // 2. Enterprise Multi-Stage Sales Funnel Breakdown
        $leadStatusCounts = collect([
            'New' => $kpi('status_new'),
            'Contacted' => $kpi('status_contacted'),
            'Qualified' => $kpi('status_qualified'),
            'Dealing' => $kpi('status_dealing'),
            'Won' => $kpi('status_won'),
        ]);

        $funnelStages = [
            'New'           => $leadStatusCounts->get('New', 0),
            'Contacted'     => $leadStatusCounts->get('Contacted', 0),
            'Qualified'     => $leadStatusCounts->get('Qualified', 0),
            'Quotation'     => (clone $periodQuotationsQuery)->count(),
            'Dealing/Won'   => $leadStatusCounts->get('Dealing', 0) + $leadStatusCounts->get('Won', 0),
        ];

        $totalFunnelLeads = $currentLeadsCount;
        $contactedFunnelLeads = $kpi('all_qualified');
        $dealConvertedLeads = $kpi('converted_total');
        $dealConvertedValue = $kpiMoney('converted_value');
        if ($dealConvertedValue <= 0) {
            $dealConvertedValue = $pipelineValue;
        }

        $quotationFunnelCount = $totalQuotationsCount;
        $wonFunnelCount = $kpi('all_won');

        $funnelDetailed = [
            'stages' => [
                [
                    'name' => '1. ' . __('crm.dashboard.stage_lead_ingestion'),
                    'sub' => __('crm.dashboard.stage_lead_ingestion_sub'),
                    'count' => $totalFunnelLeads,
                    'pct_of_total' => 100,
                    'conversion_from_prev' => 100,
                    'color' => 'primary',
                    'icon' => 'feather-download-cloud',
                ],
                [
                    'name' => '2. ' . __('crm.dashboard.stage_outreach'),
                    'sub' => __('crm.dashboard.stage_outreach_sub'),
                    'count' => $contactedFunnelLeads,
                    'pct_of_total' => $totalFunnelLeads > 0 ? round(($contactedFunnelLeads / $totalFunnelLeads) * 100, 1) : 0,
                    'conversion_from_prev' => $totalFunnelLeads > 0 ? round(($contactedFunnelLeads / $totalFunnelLeads) * 100, 1) : 0,
                    'color' => 'info',
                    'icon' => 'feather-phone-call',
                ],
                [
                    'name' => '3. ' . __('crm.dashboard.stage_converted_deals'),
                    'sub' => __('crm.dashboard.stage_converted_deals_sub'),
                    'count' => $dealConvertedLeads,
                    'value' => $dealConvertedValue,
                    'pct_of_total' => $totalFunnelLeads > 0 ? round(($dealConvertedLeads / $totalFunnelLeads) * 100, 1) : 0,
                    'conversion_from_prev' => $contactedFunnelLeads > 0 ? round(($dealConvertedLeads / $contactedFunnelLeads) * 100, 1) : 0,
                    'color' => 'warning',
                    'icon' => 'feather-briefcase',
                ],
                [
                    'name' => '4. ' . __('crm.dashboard.stage_proposals'),
                    'sub' => __('crm.dashboard.stage_proposals_sub'),
                    'count' => $quotationFunnelCount,
                    'value' => $totalQuotationValue,
                    'pct_of_total' => $totalFunnelLeads > 0 ? round(($quotationFunnelCount / $totalFunnelLeads) * 100, 1) : 0,
                    'conversion_from_prev' => $dealConvertedLeads > 0 ? round(($quotationFunnelCount / $dealConvertedLeads) * 100, 1) : 0,
                    'color' => 'purple',
                    'icon' => 'feather-file-text',
                ],
                [
                    'name' => '5. ' . __('crm.dashboard.stage_closed_won'),
                    'sub' => __('crm.dashboard.stage_closed_won_sub'),
                    'count' => $wonFunnelCount,
                    'value' => $wonRevenue,
                    'pct_of_total' => $totalFunnelLeads > 0 ? round(($wonFunnelCount / $totalFunnelLeads) * 100, 1) : 0,
                    'conversion_from_prev' => $quotationFunnelCount > 0 ? round(($wonFunnelCount / $quotationFunnelCount) * 100, 1) : 0,
                    'color' => 'success',
                    'icon' => 'feather-award',
                ],
            ],
            'overall_conversion' => $totalFunnelLeads > 0 ? round(($wonFunnelCount / $totalFunnelLeads) * 100, 1) : 0
        ];

        // 3. Campaign & Marketing ROI Performance Breakdown (Multi-Dimension: Campaign, AdSet, Ad Creative)
        $dimensionCol = match($campaignDimension) {
            'adset' => 'utm_term',
            'ad_name' => 'utm_content',
            default => 'utm_campaign',
        };
        $dimensionLabel = match($campaignDimension) {
            'adset' => __('crm.dashboard.adset_label'),
            'ad_name' => __('crm.dashboard.ad_creative_label'),
            default => __('crm.dashboard.campaign_name'),
        };

        $campaignPerformance = (clone $periodLeadsQuery)
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->whereNotNull($dimensionCol)
            ->where($dimensionCol, '!=', '')
            ->select(
                DB::raw("{$dimensionCol} as campaign_name"),
                DB::raw("COALESCE(NULLIF(utm_source, ''), 'meta') as channel"),
                DB::raw("COUNT(*) as total_leads"),
                DB::raw("SUM(CASE WHEN status NOT IN ('New', 'Lost') THEN 1 ELSE 0 END) as qualified_leads"),
                DB::raw("SUM(CASE WHEN crm_deal_id IS NOT NULL OR status IN ('Won', 'Dealing') THEN 1 ELSE 0 END) as converted_deals"),
                DB::raw("SUM(CASE WHEN status = 'Won' THEN 1 ELSE 0 END) as won_deals"),
                DB::raw("SUM(CASE WHEN status = 'Won' THEN COALESCE(expected_amount, 0) ELSE 0 END) as won_revenue"),
                DB::raw("SUM(COALESCE(expected_amount, 0)) as pipeline_value")
            )
            ->groupBy('campaign_name', 'channel')
            ->orderByDesc('total_leads')
            ->take(8)
            ->get()
            ->map(function ($c) {
                $c->conversion_rate = $c->total_leads > 0 ? round(($c->won_deals / $c->total_leads) * 100, 1) : 0;
                return $c;
            });

        // Fallback to utm_campaign if selected sub-dimension has no records yet
        if ($campaignPerformance->isEmpty() && $dimensionCol !== 'utm_campaign') {
            $campaignPerformance = (clone $periodLeadsQuery)
                ->whereNotNull('utm_campaign')
                ->where('utm_campaign', '!=', '')
                ->select(
                    DB::raw("utm_campaign as campaign_name"),
                    DB::raw("COALESCE(NULLIF(utm_source, ''), 'meta') as channel"),
                    DB::raw("COUNT(*) as total_leads"),
                    DB::raw("SUM(CASE WHEN status NOT IN ('New', 'Lost') THEN 1 ELSE 0 END) as qualified_leads"),
                    DB::raw("SUM(CASE WHEN crm_deal_id IS NOT NULL OR status IN ('Won', 'Dealing') THEN 1 ELSE 0 END) as converted_deals"),
                    DB::raw("SUM(CASE WHEN status = 'Won' THEN 1 ELSE 0 END) as won_deals"),
                    DB::raw("SUM(CASE WHEN status = 'Won' THEN COALESCE(expected_amount, 0) ELSE 0 END) as won_revenue"),
                    DB::raw("SUM(COALESCE(expected_amount, 0)) as pipeline_value")
                )
                ->groupBy('campaign_name', 'channel')
                ->orderByDesc('total_leads')
                ->take(8)
                ->get()
                ->map(function ($c) {
                    $c->conversion_rate = $c->total_leads > 0 ? round(($c->won_deals / $c->total_leads) * 100, 1) : 0;
                    return $c;
                });
        }

        $campTotalLeads = (int) $campaignPerformance->sum('total_leads');
        $campQualifiedLeads = (int) $campaignPerformance->sum('qualified_leads');
        $campDealsCount = (int) $campaignPerformance->sum('converted_deals');
        $campWonCount = (int) $campaignPerformance->sum('won_deals');
        $campWonRevenue = (float) $campaignPerformance->sum('won_revenue');

        $campaignLeadIds = (clone $periodLeadsQuery)
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->select('leads.id'); // used as a subquery, never loaded into PHP

        $campContactedLeads = (clone $periodLeadsQuery)
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->where(function($q) {
                $q->where('status', '!=', 'New')
                  ->orWhereHas('followups');
            })
            ->count();

        $campQuotesCount = Quotation::where('tenant_id', $tenantId)
            ->whereIn('lead_id', $campaignLeadIds)
            ->count();

        $campaignFunnel = [
            'total_leads'       => $campTotalLeads,
            'contacted'         => $campContactedLeads,
            'qualified'         => $campQualifiedLeads,
            'deals'             => $campDealsCount,
            'proposals'         => $campQuotesCount,
            'won'               => $campWonCount,
            'revenue'           => $campWonRevenue,
            'overall_conv_rate' => $campTotalLeads > 0 ? round(($campWonCount / $campTotalLeads) * 100, 1) : 0,
        ];

        $campaignChartData = [
            'labels'  => $campaignPerformance->pluck('campaign_name')->toArray(),
            'leads'   => $campaignPerformance->pluck('total_leads')->toArray(),
            'won'     => $campaignPerformance->pluck('won_deals')->toArray(),
            'revenue' => $campaignPerformance->pluck('won_revenue')->toArray(),
        ];

        // Filter dropdown options scan every lead of the tenant (three times) and
        // change rarely, so they are cached per tenant for 10 minutes.
        [$availableCampaigns, $availableAdsets, $availableAds] = \Illuminate\Support\Facades\Cache::remember(
            "tenant_{$tenantId}_crm_dashboard_utm_options",
            600,
            fn () => $this->utmFilterOptions($tenantId)
        );

        // 4. Deal Pipeline Stage Breakdown
        $dealStages = (clone $periodDealsQuery)
            ->select('stage', DB::raw('COUNT(*) as count'), DB::raw('SUM(estimated_value) as total_value'))
            ->groupBy('stage')
            ->get()
            ->keyBy(fn($item) => strtolower($item->stage));

        // 5. Monthly Trend Analytics (Last 6 Months)
        $monthlyTrend = $this->getMonthlyTrend($tenantId, $companyScope, $ownerId, $leadType, $campaign, $adset, $adName);

        // 6. Lead Source Distribution
        $sourceBreakdown = (clone $periodLeadsQuery)
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->get()
            ->pluck('count', 'source')
            ->toArray();

        // 7. Win / Loss Reason Breakdown
        $winLossReasons = (clone $periodDealsQuery)
            ->select('close_reason', DB::raw('COUNT(*) as count'))
            ->whereNotNull('close_reason')
            ->where('close_reason', '!=', '')
            ->groupBy('close_reason')
            ->get()
            ->pluck('count', 'close_reason')
            ->toArray();

        // 8. Sales Leaderboard (Reps Performance)
        $salesLeaderboard = $this->getSalesLeaderboard($tenantId, $startDate, $endDate, $companyScope, $leadType, $campaign, $adset, $adName);

        // 9. Actionable Datasets
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

        $pendingFollowups = LeadFollowup::whereHas('lead', function($q) use ($tenantId, $companyScope, $ownerId, $leadType, $campaign, $adset, $adName) {
            $q->where('tenant_id', $tenantId);
            if ($companyScope === 'current' && current_company_id()) {
                $q->where('company_id', current_company_id());
            }
            if ($ownerId) {
                $q->where('lead_owner_id', $ownerId);
            }
            if ($leadType) {
                $q->where('lead_type', $leadType);
            }
            if ($campaign) {
                $q->where('utm_campaign', $campaign);
            }
            if ($adset) {
                $q->where('utm_term', $adset);
            }
            if ($adName) {
                $q->where('utm_content', $adName);
            }
        })
        ->where('status', 'scheduled')
        ->orderBy('followup_date', 'asc')
        ->take(6)
        ->get();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $totalAccounts = CrmAccount::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate])->count();

        $salesOwners = User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $companies = Company::where('tenant_id', $tenantId)->get(['id', 'company_name']);

        $query = [
            'preset'             => $preset,
            'company_scope'      => $companyScope,
            'owner_id'           => $ownerId,
            'lead_type'          => $leadType,
            'campaign'           => $campaign,
            'adset'              => $adset,
            'ad_name'            => $adName,
            'campaign_dimension' => $campaignDimension,
            'view'               => $activeView,
        ];
        if ($preset === 'custom') {
            $query['from'] = $fromDate;
            $query['to'] = $toDate;
        }
        $query = array_filter($query, fn($v) => $v !== null && $v !== '');

        return compact(
            'preset',
            'startDate',
            'endDate',
            'companyScope',
            'ownerId',
            'leadType',
            'campaign',
            'adset',
            'adName',
            'campaignDimension',
            'dimensionLabel',
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
            'metaLeadsCount',
            'metaQualifiedRate',
            'metaWonRevenue',
            'metaWinRate',
            'webLeadsCount',
            'thirdPartyLeadsCount',
            'thirdPartyQualifiedCount',
            'thirdPartyQualifiedRate',
            'thirdPartyWonCount',
            'thirdPartyWonRevenue',
            'thirdPartyWinRate',
            'funnelStages',
            'funnelDetailed',
            'campaignPerformance',
            'campaignChartData',
            'campaignFunnel',
            'availableCampaigns',
            'availableAdsets',
            'availableAds',
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

    /**
     * Distinct campaign / adset / ad values for the dashboard filters.
     *
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>}
     */
    private function utmFilterOptions(int $tenantId): array
    {
        return array_map(
            fn (string $column) => Lead::where('tenant_id', $tenantId)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column)
                ->filter()
                ->values()
                ->toArray(),
            ['utm_campaign', 'utm_term', 'utm_content']
        );
    }

    private function getMonthlyTrend(
        int $tenantId, 
        string $companyScope = 'current', 
        ?int $ownerId = null,
        ?string $leadType = null,
        ?string $campaign = null,
        ?string $adset = null,
        ?string $adName = null
    ): array {
        // Two grouped queries for all six months (was two queries per month).
        $rangeStart = Carbon::now()->subMonths(5)->startOfMonth();
        $rangeEnd = Carbon::now()->endOfMonth();

        $leadsQ = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $dealsQ = CrmDeal::where('tenant_id', $tenantId)->whereBetween('updated_at', [$rangeStart, $rangeEnd]);

        if ($companyScope === 'current' && current_company_id()) {
            $leadsQ->where('company_id', current_company_id());
            $dealsQ->where('company_id', current_company_id());
        }
        if ($ownerId) {
            $leadsQ->where('lead_owner_id', $ownerId);
            $dealsQ->where('owner_id', $ownerId);
        }
        if ($leadType) {
            $leadsQ->where('lead_type', $leadType);
            $dealsQ->whereHas('lead', fn($l) => $l->where('lead_type', $leadType));
        }
        if ($campaign) {
            $leadsQ->where('utm_campaign', $campaign);
            $dealsQ->where(function($q) use ($campaign) {
                $q->whereHas('lead', fn($l) => $l->where('utm_campaign', $campaign))
                  ->orWhere('lead_source', $campaign);
            });
        }
        if ($adset) {
            $leadsQ->where('utm_term', $adset);
            $dealsQ->whereHas('lead', fn($l) => $l->where('utm_term', $adset));
        }
        if ($adName) {
            $leadsQ->where('utm_content', $adName);
            $dealsQ->whereHas('lead', fn($l) => $l->where('utm_content', $adName));
        }

        $leadsByMonth = $leadsQ->toBase()
            ->selectRaw($this->yearMonthSql('created_at') . ' AS ym, COUNT(*) AS total')
            ->groupBy('ym')
            ->pluck('total', 'ym');
        $revenueByMonth = $dealsQ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won'])->toBase()
            ->selectRaw($this->yearMonthSql('crm_deals.updated_at') . ' AS ym, SUM(estimated_value) AS total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $months = [];
        $leadsData = [];
        $revenueData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $key = $monthDate->format('Y-m');
            $months[] = $monthDate->format('M Y');
            $leadsData[] = (int) ($leadsByMonth[$key] ?? 0);
            $revenueData[] = (float) ($revenueByMonth[$key] ?? 0);
        }

        return [
            'labels'  => $months,
            'leads'   => $leadsData,
            'revenue' => $revenueData,
        ];
    }

    /** "YYYY-MM" of a datetime column, on MySQL/MariaDB and on SQLite (tests). */
    private function yearMonthSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function getSalesLeaderboard(
        int $tenantId, 
        Carbon $startDate, 
        Carbon $endDate,
        string $companyScope = 'current',
        ?string $leadType = null,
        ?string $campaign = null,
        ?string $adset = null,
        ?string $adName = null
    ): array {
        $users = User::where('tenant_id', $tenantId)->get(['id', 'name']);

        // Two grouped queries for all reps (was two queries per user).
        $leadsQ = Lead::where('tenant_id', $tenantId)
            ->whereIn('lead_owner_id', $users->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $dealsQ = CrmDeal::where('tenant_id', $tenantId)
            ->whereIn('owner_id', $users->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($companyScope === 'current' && current_company_id()) {
            $leadsQ->where('company_id', current_company_id());
            $dealsQ->where('company_id', current_company_id());
        }
        if ($leadType) {
            $leadsQ->where('lead_type', $leadType);
            $dealsQ->whereHas('lead', fn($l) => $l->where('lead_type', $leadType));
        }
        if ($campaign) {
            $leadsQ->where('utm_campaign', $campaign);
            $dealsQ->where(function($q) use ($campaign) {
                $q->whereHas('lead', fn($l) => $l->where('utm_campaign', $campaign))
                  ->orWhere('lead_source', $campaign);
            });
        }
        if ($adset) {
            $leadsQ->where('utm_term', $adset);
            $dealsQ->whereHas('lead', fn($l) => $l->where('utm_term', $adset));
        }
        if ($adName) {
            $leadsQ->where('utm_content', $adName);
            $dealsQ->whereHas('lead', fn($l) => $l->where('utm_content', $adName));
        }

        $leadCounts = $leadsQ->toBase()
            ->selectRaw('lead_owner_id AS owner, COUNT(*) AS total')
            ->groupBy('lead_owner_id')
            ->pluck('total', 'owner');
        $dealStats = $dealsQ->toBase()
            ->selectRaw("crm_deals.owner_id AS owner, COUNT(*) AS total,
                SUM(CASE WHEN LOWER(stage) IN ('won', 'closed won') THEN 1 ELSE 0 END) AS won,
                SUM(CASE WHEN LOWER(stage) IN ('won', 'closed won') THEN COALESCE(estimated_value, 0) ELSE 0 END) AS won_revenue")
            ->groupBy('crm_deals.owner_id')
            ->get()
            ->keyBy('owner');

        $leaderboard = [];
        foreach ($users as $user) {
            $leadsCount = (int) ($leadCounts[$user->id] ?? 0);
            $deal = $dealStats->get($user->id);
            $dealsCount = (int) ($deal->total ?? 0);
            $wonCount = (int) ($deal->won ?? 0);
            $wonRevenue = (float) ($deal->won_revenue ?? 0);

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
