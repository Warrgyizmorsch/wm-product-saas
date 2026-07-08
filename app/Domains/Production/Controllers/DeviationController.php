<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionDeviation;
use App\Domains\Production\Services\DeviationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->get();

        return view('modules.production.quality.deviations.index', compact('deviations'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage', ProductionDeviation::class);
        $tenantId = require_tenant_id();
        $data = $request->validate([
            'type'        => 'required|string|in:temporary,permanent,customer_waiver',
            'description' => 'required|string',
            'expiration_date' => 'nullable|date',
            'expiration_quantity' => 'nullable|numeric',
        ]);

        $this->deviationService->createDeviation($tenantId, $data);

        return redirect()->back()->with('success', 'Deviation / waiver request submitted.');
    }

    public function approve(Request $request, int $id)
    {
        $this->authorize('approve', ProductionDeviation::class);
        $userId = auth()->id();
        $signature = $request->input('esignature') ?: 'DEV-APPROVE-SIGN';

        $this->deviationService->approveDeviation($id, $userId, $signature);

        return redirect()->back()->with('success', 'Deviation approved.');
    }
}
