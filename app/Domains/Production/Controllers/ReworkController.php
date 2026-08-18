<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Requests\CompleteReworkOperationRequest;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Services\ReworkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReworkController extends Controller
{
    public function __construct(
        private readonly ReworkService $reworkService,
        private readonly ProductionQualityRepositoryInterface $qualityRepository
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionReworkOrder::class);

        $filters = $request->only(['search', 'status']);
        $reworks = $this->qualityRepository->paginateReworkOrders($filters, 15)->withQueryString();

        return view('modules.production.quality.rework.index', compact('reworks'));
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionReworkOrder::class);

        $rework = $this->qualityRepository->findReworkOrder($id);
        abort_if(!$rework, 404, 'Rework order not found.');

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

    public function fail(Request $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->reworkService->failRework($id, $data, $tenantId);

        return redirect()->back()->with('success', 'Rework order failed and rejected quantity successfully converted to scrap.');
    }
}
