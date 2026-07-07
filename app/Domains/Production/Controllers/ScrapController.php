<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Services\ScrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScrapController extends Controller
{
    public function __construct(
        private readonly ScrapService $scrapService
    ) {}

    public function index()
    {
        $tenantId = require_tenant_id();
        $scraps = ProductionScrapDisposal::where('tenant_id', $tenantId)
            ->with(['ncr', 'disposer'])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.production.quality.scrap.index', compact('scraps'));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();
        $data = $request->validate([
            'category'    => 'required|string|in:raw_material,finished_good,scrap_metal,chemical',
            'reason_code' => 'required|string',
            'quantity'    => 'required|numeric|min:0.01',
            'cost'        => 'nullable|numeric|min:0',
        ]);

        $this->scrapService->createScrapDisposal($tenantId, $data);

        return redirect()->back()->with('success', 'Scrap registered and pending disposal approval.');
    }

    public function approve(Request $request, int $id)
    {
        $userId = Auth::id() ?: 1;
        $this->scrapService->approveDisposal($id, $userId);

        return redirect()->back()->with('success', 'Scrap disposal approved.');
    }
}
