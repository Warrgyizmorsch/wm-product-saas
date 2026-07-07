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

        // 2. Scrap & Rework Counts
        $scrapCount = ProductionScrapDisposal::where('tenant_id', $tenantId)->count();
        $reworkCount = ProductionReworkOrder::where('tenant_id', $tenantId)->count();

        // 3. NCR and CAPA stats
        $ncrOpen = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'open')->count();
        $ncrClosed = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'closed')->count();
        
        $capaOpen = ProductionCapa::where('tenant_id', $tenantId)->whereIn('status', ['draft', 'active'])->count();
        $capaClosed = ProductionCapa::where('tenant_id', $tenantId)->where('status', 'closed')->count();

        return view('modules.production.quality.dashboard', compact(
            'fpy', 'scrapCount', 'reworkCount', 'ncrOpen', 'ncrClosed', 'capaOpen', 'capaClosed'
        ));
    }
}
