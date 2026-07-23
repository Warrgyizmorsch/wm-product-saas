<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Requests\NcrDispositionRequest;
use App\Domains\Production\Requests\StoreNcrRequest;
use App\Domains\Production\Services\NcrService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NcrController extends Controller
{
    public function __construct(
        private readonly NcrService $ncrService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionNcr::class);
        $tenantId = require_tenant_id();

        $query = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order.product', 'inspection']);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('ncr_number', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');

        if (! in_array($sortBy, ['id', 'ncr_number', 'category', 'status'])) {
            $sortBy = 'id';
        }
        if (! in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $ncrs = $query->orderBy($sortBy, $sortOrder)->paginate(15)->withQueryString();

        return view('modules.production.quality.ncrs.index', compact('ncrs'));
    }

    public function create()
    {
        $this->authorize('manage', ProductionNcr::class);
        $tenantId = require_tenant_id();
        $orders = ProductionOrder::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.ncrs.create', compact('orders'));
    }

    public function store(StoreNcrRequest $request)
    {
        $this->authorize('manage', ProductionNcr::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $ncr = $this->ncrService->createNcr($tenantId, $data);

        return redirect()->route('production.ncrs.show', $ncr->id)
            ->with('success', 'NCR logged successfully.');
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionNcr::class);
        $tenantId = require_tenant_id();
        $ncr = ProductionNcr::where('tenant_id', $tenantId)
            ->with(['order.product', 'inspection', 'reworkOrder', 'scrapDisposal'])
            ->findOrFail($id);

        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.ncrs.show', compact('ncr', 'workCenters'));
    }

    public function disposition(NcrDispositionRequest $request, int $id)
    {
        $this->authorize('manage', ProductionNcr::class);
        $tenantId = require_tenant_id();

        $type = $request->input('disposition_type'); // rework | scrap | use_as_is

        $data = $request->only([
            'original_production_order_id',
            'cost_estimate',
            'work_center_id',
            'category',
            'reason_code',
            'quantity',
            'cost',
        ]);

        if (isset($data['cost_estimate'])) {
            $data['cost_estimate'] = convert_to_base($data['cost_estimate']);
        }
        if (isset($data['cost'])) {
            $data['cost'] = convert_to_base($data['cost']);
        }

        $this->ncrService->processDisposition($id, $type, $data, $tenantId);


        return redirect()->back()->with('success', 'Disposition type registered.');
    }

    public function close(Request $request, int $id)
    {
        $this->authorize('approve', ProductionNcr::class);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $signature = $request->input('esignature') ?: 'NCR-CLOSE-SIGN';

        $this->ncrService->closeNcr($id, $userId, $signature, $tenantId);

        return redirect()->back()->with('success', 'NCR closed successfully.');
    }
}
