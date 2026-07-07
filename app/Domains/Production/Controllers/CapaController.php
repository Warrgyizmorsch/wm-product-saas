<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Services\CapaService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CapaController extends Controller
{
    public function __construct(
        private readonly CapaService $capaService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();
        $capas = ProductionCapa::where('tenant_id', $tenantId)
            ->with(['ncr', 'owner'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.capas.index', compact('capas'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();
        $ncrs = ProductionNcr::where('tenant_id', $tenantId)->get();
        $users = User::where('tenant_id', $tenantId)->get();

        return view('modules.production.quality.capas.create', compact('ncrs', 'users'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();
        $data = $request->validate([
            'ncr_id'            => 'nullable|exists:production_ncrs,id',
            'action_owner_id'   => 'required|exists:users,id',
            'corrective_action' => 'required|string',
            'preventive_action' => 'nullable|string',
            'target_date'       => 'required|date',
        ]);

        $capa = $this->capaService->createCapa($tenantId, $data);

        return redirect()->route('production.quality.capas.show', $capa->id)
            ->with('success', 'CAPA registered.');
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $capa = ProductionCapa::where('tenant_id', $tenantId)->with(['ncr', 'owner'])->findOrFail($id);

        return view('modules.production.quality.capas.show', compact('capa'));
    }

    public function saveRca(Request $request, int $id)
    {
        $request->validate([
            'five_whys' => 'required|array',
            'fishbone'  => 'required|array',
        ]);

        $this->capaService->recordRca($id, $request->input('five_whys'), $request->input('fishbone'));

        return redirect()->back()->with('success', 'Root cause analysis logged.');
    }

    public function close(Request $request, int $id)
    {
        $userId = Auth::id() ?: 1;
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
