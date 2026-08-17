<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionOrder;
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

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = ProductionQualityInspection::with(['plan', 'order.product'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('inspection_number', 'like', "%{$search}%")
                  ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('result')) {
            $query->where('result', $request->input('result'));
        }

        $inspections = $query->orderBy($sortBy, $sortOrder)->paginate(15)->withQueryString();

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
