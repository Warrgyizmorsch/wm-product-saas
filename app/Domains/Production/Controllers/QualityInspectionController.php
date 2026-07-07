<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Services\QualityInspectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QualityInspectionController extends Controller
{
    public function __construct(
        private readonly QualityInspectionService $inspectionService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();
        $inspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->with(['plan', 'order'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.inspections.index', compact('inspections'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();
        $plans = ProductionQualityPlan::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.inspections.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();
        $data = $request->validate([
            'quality_plan_id'               => 'required|exists:production_quality_plans,id',
            'stage'                         => 'required|string|in:incoming,in_process,final',
            'production_order_id'           => 'nullable|integer',
            'production_order_operation_id' => 'nullable|integer',
        ]);

        $inspection = $this->inspectionService->createInspection($tenantId, $data);

        return redirect()->route('production.quality.inspections.show', $inspection->id)
            ->with('success', 'Quality checklist generated.');
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $inspection = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->with(['plan.parameters', 'results.parameter'])
            ->findOrFail($id);

        return view('modules.production.quality.inspections.show', compact('inspection'));
    }

    public function saveResults(Request $request, int $id)
    {
        $request->validate([
            'results' => 'required|array',
        ]);

        $this->inspectionService->recordResults($id, $request->input('results'));

        return redirect()->back()->with('success', 'Inspection results recorded and submitted.');
    }

    public function approve(Request $request, int $id)
    {
        $userId = Auth::id() ?: 1;
        $signature = $request->input('esignature') ?: 'SIGNED';

        $this->inspectionService->approveInspection($id, $userId, $signature);

        return redirect()->back()->with('success', 'Inspection approved and audited.');
    }
}
