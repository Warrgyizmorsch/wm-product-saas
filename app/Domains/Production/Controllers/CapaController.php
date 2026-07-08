<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Services\CapaService;
use App\Models\User;
use Illuminate\Http\Request;
use App\Domains\Production\Requests\StoreCapaRequest;
use App\Domains\Production\Requests\CapaRcaRequest;

class CapaController extends Controller
{
    public function __construct(
        private readonly CapaService $capaService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionCapa::class);
        $tenantId = require_tenant_id();

        $query = ProductionCapa::where('tenant_id', $tenantId)
            ->with(['ncr.order', 'owner']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('capa_number', 'like', $search)
                  ->orWhere('corrective_action', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');

        if (!in_array($sortBy, ['id', 'capa_number', 'status', 'target_date'])) {
            $sortBy = 'id';
        }
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $capas = $query->orderBy($sortBy, $sortOrder)->paginate(15)->withQueryString();

        return view('modules.production.quality.capas.index', compact('capas'));
    }

    public function create()
    {
        $this->authorize('manage', ProductionCapa::class);
        $tenantId = require_tenant_id();
        $ncrs = ProductionNcr::where('tenant_id', $tenantId)->get();
        $users = User::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.capas.create', compact('ncrs', 'users'));
    }

    public function store(StoreCapaRequest $request)
    {
        $this->authorize('manage', ProductionCapa::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $capa = $this->capaService->createCapa($tenantId, $data);

        return redirect()->route('production.capas.show', $capa->id)
            ->with('success', 'CAPA registered.');
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionCapa::class);
        $tenantId = require_tenant_id();
        $capa = ProductionCapa::where('tenant_id', $tenantId)->with(['ncr', 'owner'])->findOrFail($id);

        return view('modules.production.quality.capas.show', compact('capa'));
    }

    public function saveRca(CapaRcaRequest $request, int $id)
    {
        $this->authorize('manage', ProductionCapa::class);

        $this->capaService->recordRca($id, $request->input('five_whys'), $request->input('fishbone'));

        return redirect()->back()->with('success', 'Root cause analysis logged.');
    }

    public function close(Request $request, int $id)
    {
        $this->authorize('approve', ProductionCapa::class);
        $userId = auth()->id();
        $review = $request->input('effectiveness_review') ?: 'Verified effective.';
        $signature = $request->input('esignature') ?: 'CAPA-CLOSE-SIGN';

        try {
            $this->capaService->closeCapa($id, $userId, $review, $signature);
            return redirect()->back()->with('success', 'CAPA closed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
