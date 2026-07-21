<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Requests\StoreScrapRequest;
use App\Domains\Production\Services\ScrapService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ScrapController extends Controller
{
    public function __construct(
        private readonly ScrapService $scrapService
    ) {}

    public function index()
    {
        $this->authorize('view', ProductionScrapDisposal::class);
        $tenantId = require_tenant_id();
        $scraps = ProductionScrapDisposal::where('tenant_id', $tenantId)
            ->with(['ncr', 'disposer'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('modules.production.quality.scrap.index', compact('scraps'));
    }

    public function store(StoreScrapRequest $request)
    {
        $this->authorize('manage', ProductionScrapDisposal::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();
        if (isset($data['cost'])) {
            $data['cost'] = convert_to_base($data['cost']);
        }

        $this->scrapService->createScrapDisposal($tenantId, $data);

        return redirect()->back()->with('success', 'Scrap registered and pending disposal approval.');
    }


    public function approve(Request $request, int $id)
    {
        $this->authorize('approve', ProductionScrapDisposal::class);
        $tenantId = require_tenant_id();
        $userId = auth()->id();
        $this->scrapService->approveDisposal($id, $userId, $tenantId);

        return redirect()->back()->with('success', 'Scrap disposal approved.');
    }
}
