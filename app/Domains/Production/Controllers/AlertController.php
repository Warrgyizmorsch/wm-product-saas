<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Services\AlertService;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();

        // 1. Ensure sensible default configurations exist
        $defaults = [
            ['alert_type' => 'oee_below_threshold', 'threshold' => 80.00, 'severity' => 'warning'],
            ['alert_type' => 'scrap_rate_high', 'threshold' => 5.00, 'severity' => 'critical'],
            ['alert_type' => 'machine_idle_limit', 'threshold' => 60.00, 'severity' => 'info'],
        ];

        foreach ($defaults as $d) {
            ProductionAlertConfiguration::firstOrCreate(
                ['tenant_id' => $tenantId, 'alert_type' => $d['alert_type']],
                ['threshold' => $d['threshold'], 'severity' => $d['severity'], 'active' => true]
            );
        }

        // Trigger manual audit test if run request is in url
        if (request()->has('audit')) {
            $this->alertService->checkAlerts($tenantId);
            return redirect()->back()->with('success', 'Alert check executed and timeline updated.');
        }

        $alerts = ProductionAlertConfiguration::where('tenant_id', $tenantId)->get();

        return view('modules.production.intelligence.alerts', compact('alerts'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = require_tenant_id();
        $alert = ProductionAlertConfiguration::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'threshold' => 'required|numeric',
            'severity'  => 'required|string|in:info,warning,critical',
            'active'    => 'nullable|boolean',
        ]);

        $alert->update([
            'threshold' => $request->input('threshold'),
            'severity'  => $request->input('severity'),
            'active'    => $request->has('active'),
        ]);

        return redirect()->back()->with('success', 'Alert rule updated.');
    }
}
