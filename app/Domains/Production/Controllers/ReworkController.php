<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Requests\CompleteReworkOperationRequest;
use App\Domains\Production\Services\ReworkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReworkController extends Controller
{
    public function __construct(
        private readonly ReworkService $reworkService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();

        $query = ProductionReworkOrder::where('tenant_id', $tenantId)
            ->with(['ncr', 'originalOrder']);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('rework_number', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');

        if (! in_array($sortBy, ['id', 'rework_number', 'status', 'cost_estimate', 'actual_cost'])) {
            $sortBy = 'id';
        }
        if (! in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $reworks = $query->orderBy($sortBy, $sortOrder)->paginate(15)->withQueryString();

        return view('modules.production.quality.rework.index', compact('reworks'));
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();
        $rework = ProductionReworkOrder::where('tenant_id', $tenantId)
            ->with(['ncr', 'originalOrder', 'operations.workCenter', 'operations.machine'])
            ->findOrFail($id);

        return view('modules.production.quality.rework.show', compact('rework'));
    }

    public function startOp(Request $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();
        $this->reworkService->startOperation($id, $tenantId);

        return redirect()->back()->with('success', 'Rework operation started.');
    }

    public function completeOp(CompleteReworkOperationRequest $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $this->reworkService->completeOperation($id, $data, $tenantId);

        return redirect()->back()->with('success', 'Rework operation completed.');
    }
}
