<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\TrendAnalysisService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly TrendAnalysisService $trendService
    ) {}

    public function historical(Request $request)
    {
        $tenantId = require_tenant_id();

        $filters = $request->only(['date_start', 'date_end', 'machine_id', 'work_center_id']);
        $period = $request->input('period', 'daily');

        // Compile trends using unified services
        $oeeTrend   = $this->trendService->getOeeTrend($tenantId, $period, $filters);
        $prodTrend  = $this->trendService->getProductionTrend($tenantId, $period, $filters);
        $downTrend  = $this->trendService->getDowntimeTrend($tenantId, $period, $filters);

        $machines     = Machine::where('tenant_id', $tenantId)->get();
        $workCenters  = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.intelligence.analytics', compact(
            'oeeTrend', 'prodTrend', 'downTrend', 'machines', 'workCenters'
        ));
    }
}
