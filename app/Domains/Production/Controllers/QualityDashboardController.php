<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScrapDisposal;
use Illuminate\Http\Request;

class QualityDashboardController extends Controller
{
    public function index()
    {
        $this->authorize('view', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();

        // 1. First Pass Yield (FPY)
        $totalFinalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where('stage', 'final')
            ->count();

        $passedFinalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where('stage', 'final')
            ->where('result', 'passed')
            ->count();

        $fpy = $totalFinalInspections > 0 ? ($passedFinalInspections / $totalFinalInspections) * 100 : 100.00;

        // 2. Inspection Breakdown KPIs
        $totalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->count();
        $pendingInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('result')
                  ->orWhereIn('status', ['pending', 'in_progress', 'draft']);
            })
            ->count();
        $passedInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->where('result', 'passed')->count();
        $failedInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->where('result', 'failed')->count();

        // 3. Scrap & Rework Counts
        $scrapCount = ProductionScrapDisposal::where('tenant_id', $tenantId)->count();
        $reworkCount = ProductionReworkOrder::where('tenant_id', $tenantId)->count();

        // 4. NCR and CAPA stats
        $ncrOpen = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'open')->count();
        $ncrClosed = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'closed')->count();

        $capaOpen = ProductionCapa::where('tenant_id', $tenantId)->whereIn('status', ['draft', 'active'])->count();
        $capaClosed = ProductionCapa::where('tenant_id', $tenantId)->where('status', 'closed')->count();

        // 5. Recent Inspections and Recent NCRs for Dashboard Tables
        $recentInspections = ProductionQualityInspection::with(['plan', 'order.product'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentNcrs = ProductionNcr::with(['order.product'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        return view('modules.production.quality.dashboard', compact(
            'fpy',
            'totalInspections',
            'pendingInspections',
            'passedInspections',
            'failedInspections',
            'scrapCount',
            'reworkCount',
            'ncrOpen',
            'ncrClosed',
            'capaOpen',
            'capaClosed',
            'recentInspections',
            'recentNcrs'
        ));
    }
}

