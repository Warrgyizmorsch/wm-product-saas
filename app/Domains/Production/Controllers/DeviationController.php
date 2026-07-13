<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionDeviation;
use App\Domains\Production\Requests\StoreDeviationRequest;
use App\Domains\Production\Services\DeviationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeviationController extends Controller
{
    public function __construct(
        private readonly DeviationService $deviationService
    ) {}

    public function index()
    {
        $this->authorize('view', ProductionDeviation::class);
        $tenantId = require_tenant_id();
        $deviations = ProductionDeviation::where('tenant_id', $tenantId)
            ->with(['approver'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('modules.production.quality.deviations.index', compact('deviations'));
    }

    public function store(StoreDeviationRequest $request)
    {
        $this->authorize('manage', ProductionDeviation::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $this->deviationService->createDeviation($tenantId, $data);

        return redirect()->back()->with('success', 'Deviation / waiver request submitted.');
    }

    public function approve(Request $request, int $id)
    {
        $this->authorize('approve', ProductionDeviation::class);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $signature = $request->input('esignature') ?: 'DEV-APPROVE-SIGN';

        $this->deviationService->approveDeviation($id, $userId, $signature, $tenantId);

        return redirect()->back()->with('success', 'Deviation approved.');
    }
}
