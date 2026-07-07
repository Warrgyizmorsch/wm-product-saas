<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Services\ReworkService;
use Illuminate\Http\Request;

class ReworkController extends Controller
{
    public function __construct(
        private readonly ReworkService $reworkService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();
        $reworks = ProductionReworkOrder::where('tenant_id', $tenantId)
            ->with(['ncr', 'originalOrder'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.rework.index', compact('reworks'));
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $rework = ProductionReworkOrder::where('tenant_id', $tenantId)
            ->with(['ncr', 'originalOrder', 'operations.workCenter', 'operations.machine'])
            ->findOrFail($id);

        return view('modules.production.quality.rework.show', compact('rework'));
    }

    public function startOp(Request $request, int $id)
    {
        $this->reworkService->startOperation($id);

        return redirect()->back()->with('success', 'Rework operation started.');
    }

    public function completeOp(Request $request, int $id)
    {
        $data = $request->validate([
            'setup_time_actual' => 'nullable|numeric',
        ]);

        $this->reworkService->completeOperation($id, $data);

        return redirect()->back()->with('success', 'Rework operation completed.');
    }
}
