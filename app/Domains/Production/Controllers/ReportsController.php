<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\ReportingService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportService
    ) {}

    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $machines = Machine::where('tenant_id', $tenantId)->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.intelligence.reports', compact('machines', 'workCenters'));
    }

    public function show(Request $request, string $type)
    {
        $tenantId = require_tenant_id();
        $filters = $request->only(['date_start', 'date_end', 'machine_id', 'work_center_id']);

        $reportData = match ($type) {
            'machine'      => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'  => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'     => $this->reportService->generateDowntimeReport($tenantId, $filters),
            default        => abort(404),
        };

        // If print view is requested, we pass it to a simple print layout
        $print = $request->has('print');

        return view('modules.production.intelligence.reports-detail', compact('reportData', 'type', 'print'));
    }
}
