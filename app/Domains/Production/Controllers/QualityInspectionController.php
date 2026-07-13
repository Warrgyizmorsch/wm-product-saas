<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Requests\QualityInspectionResultsRequest;
use App\Domains\Production\Requests\StoreQualityInspectionRequest;
use App\Domains\Production\Services\QualityInspectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QualityInspectionController extends Controller
{
    public function __construct(
        private readonly QualityInspectionService $inspectionService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();

        $query = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->with(['plan', 'order']);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->whereHas('plan', function ($pq) use ($search) {
                $pq->where('name', 'like', $search);
            });
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->input('stage'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');

        if (! in_array($sortBy, ['id', 'stage', 'status', 'result'])) {
            $sortBy = 'id';
        }
        if (! in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $inspections = $query->orderBy($sortBy, $sortOrder)->paginate(15)->withQueryString();

        return view('modules.production.quality.inspections.index', compact('inspections'));
    }

    public function create()
    {
        $this->authorize('manage', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();
        $plans = ProductionQualityPlan::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.inspections.create', compact('plans'));
    }

    public function store(StoreQualityInspectionRequest $request)
    {
        $this->authorize('manage', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $inspection = $this->inspectionService->createInspection($tenantId, $data);

        return redirect()->route('production.inspections.show', $inspection->id)
            ->with('success', 'Quality checklist generated.');
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();
        $inspection = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->with(['plan.parameters', 'results.parameter'])
            ->findOrFail($id);

        return view('modules.production.quality.inspections.show', compact('inspection'));
    }

    public function saveResults(QualityInspectionResultsRequest $request, int $id)
    {
        $this->authorize('manage', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();

        $this->inspectionService->recordResults($id, $request->input('results'), $tenantId);

        return redirect()->back()->with('success', 'Inspection results recorded and submitted.');
    }

    public function approve(Request $request, int $id)
    {
        $this->authorize('approve', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $signature = $request->input('esignature') ?: 'SIGNED';

        $this->inspectionService->approveInspection($id, $userId, $signature, $tenantId);

        return redirect()->back()->with('success', 'Inspection approved and audited.');
    }
}
