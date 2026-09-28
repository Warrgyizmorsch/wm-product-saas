<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CrmDashboardApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/crm/dashboard/metrics
     * Executive CRM Analytics & KPI Metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $totalLeads = Lead::where('tenant_id', $tenantId)->count();
        $newLeads = Lead::where('tenant_id', $tenantId)->where('status', 'New')->count();
        $qualifiedLeads = Lead::where('tenant_id', $tenantId)->whereIn('status', ['Qualified', 'Dealing'])->count();
        $wonLeads = Lead::where('tenant_id', $tenantId)->where('status', 'Won')->count();
        $lostLeads = Lead::where('tenant_id', $tenantId)->where('status', 'Lost')->count();

        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0.0;

        $totalDeals = CrmDeal::where('tenant_id', $tenantId)->count();
        $openPipelineValue = (float) CrmDeal::where('tenant_id', $tenantId)
            ->whereNotIn('stage', ['Closed Won', 'Closed Lost', 'Won', 'Lost'])
            ->sum('estimated_value');

        $wonDealsValue = (float) CrmDeal::where('tenant_id', $tenantId)
            ->whereIn('stage', ['Closed Won', 'Won'])
            ->sum('estimated_value');

        $totalQuotations = Quotation::where('tenant_id', $tenantId)->count();
        $quotationValue = (float) Quotation::where('tenant_id', $tenantId)->sum('total_amount');

        $pendingActivities = LeadFollowup::where('tenant_id', $tenantId)
            ->where('status', 'Pending')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'total_leads'         => $totalLeads,
                    'new_leads'           => $newLeads,
                    'qualified_leads'     => $qualifiedLeads,
                    'won_leads'           => $wonLeads,
                    'lost_leads'          => $lostLeads,
                    'lead_conversion_pct' => $conversionRate,
                    'total_deals'         => $totalDeals,
                    'open_pipeline_value' => $openPipelineValue,
                    'won_revenue_value'   => $wonDealsValue,
                    'total_quotations'    => $totalQuotations,
                    'total_quote_value'   => $quotationValue,
                    'pending_activities'  => $pendingActivities,
                ],
                'lead_stages' => Lead::where('tenant_id', $tenantId)
                    ->selectRaw('status, count(*) as count, sum(coalesce(expected_amount, 0)) as total_value')
                    ->groupBy('status')
                    ->get(),
                'deal_stages' => CrmDeal::where('tenant_id', $tenantId)
                    ->selectRaw('stage, count(*) as count, sum(coalesce(estimated_value, 0)) as total_value')
                    ->groupBy('stage')
                    ->get(),
            ],
        ]);
    }
}
