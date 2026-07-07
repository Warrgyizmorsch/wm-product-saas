<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Services\NcrService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NcrController extends Controller
{
    public function __construct(
        private readonly NcrService $ncrService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();
        $ncrs = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order', 'inspection'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.ncrs.index', compact('ncrs'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();
        $orders = ProductionOrder::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.ncrs.create', compact('orders'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();
        $data = $request->validate([
            'category'            => 'required|string|in:material,process,machine,human_error',
            'description'         => 'required|string',
            'production_order_id' => 'nullable|integer',
        ]);

        $ncr = $this->ncrService->createNcr($tenantId, $data);

        return redirect()->route('production.quality.ncrs.show', $ncr->id)
            ->with('success', 'NCR logged successfully.');
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $ncr = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order', 'inspection', 'reworkOrder', 'scrapDisposal'])
            ->findOrFail($id);

        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.ncrs.show', compact('ncr', 'workCenters'));
    }

    public function disposition(Request $request, int $id)
    {
        $type = $request->input('disposition_type'); // rework | scrap | use_as_is
        
        $data = $request->only([
            'original_production_order_id', 
            'cost_estimate', 
            'work_center_id', 
            'category', 
            'reason_code', 
            'quantity', 
            'cost'
        ]);

        $this->ncrService->processDisposition($id, $type, $data);

        return redirect()->back()->with('success', 'Disposition type registered.');
    }

    public function close(Request $request, int $id)
    {
        $userId = Auth::id() ?: 1;
        $signature = $request->input('esignature') ?: 'NCR-CLOSE-SIGN';

        $this->ncrService->closeNcr($id, $userId, $signature);

        return redirect()->back()->with('success', 'NCR closed successfully.');
    }
}
