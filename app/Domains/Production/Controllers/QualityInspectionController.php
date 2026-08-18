<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Requests\QualityInspectionResultsRequest;
use App\Domains\Production\Requests\StoreQualityInspectionRequest;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Services\QualityInspectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QualityInspectionController extends Controller
{
    public function __construct(
        private readonly QualityInspectionService $inspectionService,
        private readonly ProductionQualityRepositoryInterface $qualityRepository
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionQualityInspection::class);

        $filters = $request->only(['search', 'status', 'result']);
        $inspections = $this->qualityRepository->paginateInspections($filters, 15)->withQueryString();

        return view('modules.production.quality.inspections.index', compact('inspections'));
    }

    public function create()
    {
        $this->authorize('manage', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();
        $plans = ProductionQualityPlan::where('tenant_id', $tenantId)->get();
        $orders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [ProductionOrder::STATUS_IN_PROGRESS, ProductionOrder::STATUS_COMPLETED])
            ->with(['operations'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.inspections.create', compact('plans', 'orders'));
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

        $inspection = $this->qualityRepository->findInspection($id);
        abort_if(!$inspection, 404, 'Quality inspection record not found.');

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

    public function operatorQuickInspection(Request $request)
    {
        $tenantId = require_tenant_id();
        $validated = $request->validate([
            'production_order_operation_id' => 'nullable|integer',
            'production_order_id' => 'nullable|integer',
            'batch_id' => 'nullable|integer',
            'result' => 'required|in:passed,hold,failed',
            'remarks' => 'nullable|string|max:500',
            'stage' => 'nullable|string|max:50',
        ]);

        $inspection = $this->inspectionService->quickOperatorInspection($tenantId, $validated, auth()->id());

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Operator Quick Quality Check logged successfully.',
                'inspection_id' => $inspection->id,
                'result' => $inspection->result,
            ]);
        }

        return redirect()->back()->with('success', 'Operator Quick Quality Check logged and approved.');
    }
}
