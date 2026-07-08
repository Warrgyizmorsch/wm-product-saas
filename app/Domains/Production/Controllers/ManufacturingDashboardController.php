<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\DashboardRefreshService;
use App\Domains\Production\Services\DashboardPreferenceService;
use App\Domains\Production\Services\KpiCalculationService;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManufacturingDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRefreshService $refreshService,
        private readonly DashboardPreferenceService $preferenceService,
        private readonly KpiCalculationService $kpiService
    ) {}

    public function executiveDashboard(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $userId   = auth()->id();

        $filters = $request->only(['date_start', 'date_end', 'work_center_id', 'machine_id']);
        
        // Fetch dashboard refresh payload
        $data = $this->refreshService->refreshExecutiveDashboard($tenantId, $filters);
        
        // Load preference
        $prefs = $this->preferenceService->getPreferences($tenantId, $userId, 'executive');

        // Fetch targets for display
        $oeeKpi          = $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'oee', $data['today_oee']['current_value']);
        $scrapKpi        = $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'scrap_rate', $data['scrap_stats']['scrap_rate']);
        $downtimeKpi     = $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'downtime', $data['utilizations']['machine_utilization'] > 0 ? max(0, 100 - $data['utilizations']['machine_utilization']) : 15.0);
        $availabilityKpi = $this->kpiService->getKpiWithTargetsAndVariance($tenantId, 'availability', $data['today_oee']['current_value'] * 1.05);

        // Fetch drop downs
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.intelligence.dashboard', compact(
            'data', 'prefs', 'oeeKpi', 'scrapKpi', 'downtimeKpi', 'availabilityKpi', 'workCenters'
        ));
    }

    public function workCenterDashboard(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        $wcSummaries = [];
        foreach ($workCenters as $wc) {
            $wcSummaries[] = $this->refreshService->refreshWorkCenterDashboard($tenantId, $wc->id);
        }

        return view('modules.production.intelligence.work-centers', compact('wcSummaries', 'workCenters'));
    }

    public function savePreferences(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $userId   = auth()->id();

        $request->validate([
            'dashboard_type' => 'required|string',
            'widgets'        => 'required|array',
            'layout'         => 'nullable|string',
        ]);

        $this->preferenceService->savePreferences($tenantId, $userId, $request->input('dashboard_type'), $request->only(['widgets', 'layout', 'default_filters']));

        return response()->json(['success' => true, 'message' => 'Dashboard preferences saved successfully.']);
    }
}
