<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Requests\CapaRcaRequest;
use App\Domains\Production\Requests\StoreCapaRequest;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Services\CapaService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CapaController extends Controller
{
    public function __construct(
        private readonly CapaService $capaService,
        private readonly ProductionQualityRepositoryInterface $qualityRepository
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view', ProductionCapa::class);

        $filters = $request->only(['search', 'status']);
        $capas = $this->qualityRepository->paginateCapas($filters, 15)->withQueryString();

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

        $capa = $this->qualityRepository->findCapa($id);
        abort_if(!$capa, 404, 'CAPA record not found.');

        return view('modules.production.quality.capas.show', compact('capa'));
    }

    public function saveRca(CapaRcaRequest $request, int $id)
    {
        $this->authorize('manage', ProductionCapa::class);
        $tenantId = require_tenant_id();

        $this->capaService->recordRca($id, $request->input('five_whys'), $request->input('fishbone'), $tenantId);

        return redirect()->back()->with('success', 'Root cause analysis logged.');
    }

    public function close(Request $request, int $id)
    {
        $this->authorize('approve', ProductionCapa::class);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $review = $request->input('effectiveness_review') ?: 'Verified effective.';
        $signature = $request->input('esignature') ?: 'CAPA-CLOSE-SIGN';

        try {
            $this->capaService->closeCapa($id, $userId, $review, $signature, $tenantId);

            return redirect()->back()->with('success', 'CAPA closed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
