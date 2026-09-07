<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Services\ProductionEcoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionEcoController extends Controller
{
    public function __construct(
        private readonly ProductionEcoService $ecoService
    ) {
    }

    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;

        $query = ProductionEco::where('tenant_id', $tenantId)
            ->with(['product', 'creator', 'approver', 'releaser']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('change_type')) {
            $query->where('change_type', $request->string('change_type'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('eco_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        $ecos = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('modules.production.ecos.index', compact('ecos'));
    }

    public function create(): View
    {
        $tenantId = tenant_id() ?? 1;
        $products = Product::where('tenant_id', $tenantId)->get();
        $boms = ProductionBom::where('tenant_id', $tenantId)->get();
        $routings = Routing::where('tenant_id', $tenantId)->get();

        return view('modules.production.ecos.create', compact('products', 'boms', 'routings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'change_type' => 'required|string|in:BOM_CHANGE,ROUTING_CHANGE,BOM_AND_ROUTING_CHANGE',
            'product_id' => 'required|integer',
            'current_bom_id' => 'nullable|integer',
            'proposed_bom_id' => 'nullable|integer',
            'current_routing_id' => 'nullable|integer',
            'proposed_routing_id' => 'nullable|integer',
            'effective_date' => 'nullable|date',
        ]);

        $eco = $this->ecoService->createEco($validated, auth()->id());

        return redirect()->route('production.ecos.show', $eco->id)
            ->with('success', "Engineering Change Order {$eco->eco_number} created successfully.");
    }

    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? 1;
        $eco = ProductionEco::where('tenant_id', $tenantId)
            ->with(['product', 'currentBom.items.material', 'proposedBom.items.material', 'currentBom.items.product', 'proposedBom.items.product', 'currentRouting.operations', 'proposedRouting.operations', 'items', 'approvals.user', 'creator', 'approver', 'releaser'])
            ->findOrFail($id);

        $impact = $this->ecoService->analyzeImpact($eco);

        return view('modules.production.ecos.show', compact('eco', 'impact'));
    }

    public function submit(int $id): RedirectResponse
    {
        $this->ecoService->submitForReview($id, auth()->id());
        return redirect()->back()->with('success', 'ECO submitted for engineering review.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $comments = $request->input('comments');
        $this->ecoService->approve($id, $comments, auth()->id());
        return redirect()->back()->with('success', 'ECO approved by engineering authority.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $comments = $request->input('comments');
        $this->ecoService->reject($id, $comments, auth()->id());
        return redirect()->back()->with('warning', 'ECO rejected.');
    }

    public function implement(int $id): RedirectResponse
    {
        $this->ecoService->implement($id, auth()->id());
        return redirect()->back()->with('success', 'ECO changes implemented successfully into live BOM/Routing.');
    }

    public function release(int $id): RedirectResponse
    {
        $eco = $this->ecoService->release($id, auth()->id());
        return redirect()->back()->with('success', "ECO {$eco->eco_number} released successfully. Revisions updated with effective date.");
    }

    public function close(int $id): RedirectResponse
    {
        $this->ecoService->close($id, auth()->id());
        return redirect()->back()->with('success', 'ECO closed.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $reason = $request->input('reason');
        $this->ecoService->cancel($id, $reason, auth()->id());
        return redirect()->back()->with('warning', 'ECO cancelled.');
    }

    public function edit(int $id): View
    {
        $tenantId = tenant_id() ?? 1;
        $eco = ProductionEco::where('tenant_id', $tenantId)->findOrFail($id);
        $products = Product::where('tenant_id', $tenantId)->get();
        $boms = ProductionBom::where('tenant_id', $tenantId)->get();
        $routings = Routing::where('tenant_id', $tenantId)->get();

        return view('modules.production.ecos.create', compact('eco', 'products', 'boms', 'routings'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;
        $eco = ProductionEco::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'change_type' => 'required|string|in:BOM_CHANGE,ROUTING_CHANGE,BOM_AND_ROUTING_CHANGE',
            'product_id' => 'required|integer',
            'current_bom_id' => 'nullable|integer',
            'proposed_bom_id' => 'nullable|integer',
            'current_routing_id' => 'nullable|integer',
            'proposed_routing_id' => 'nullable|integer',
            'effective_date' => 'nullable|date',
        ]);

        $eco->update($validated);

        return redirect()->route('production.ecos.show', $eco->id)
            ->with('success', "ECO {$eco->eco_number} updated successfully.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;
        $eco = ProductionEco::where('tenant_id', $tenantId)->findOrFail($id);
        $eco->delete();

        return redirect()->route('production.ecos.index')
            ->with('success', 'ECO deleted successfully.');
    }
}
