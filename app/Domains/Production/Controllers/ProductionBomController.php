<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Repositories\ProductionBomRepositoryInterface;
use App\Domains\Production\Requests\StoreProductionBomRequest;
use App\Domains\Production\Requests\UpdateProductionBomRequest;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\BomExplosionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionBomController extends Controller
{
    public function __construct(
        private readonly ProductionBomRepositoryInterface $bomRepository,
        private readonly ProductionBomService $bomService,
        private readonly BomExplosionService $explosionService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['product_id', 'status', 'search']);
        $boms = $this->bomRepository->getAll($filters);
        
        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();

        return view('modules.production.bom.index', compact('boms', 'products'));
    }

    public function show(int $id, Request $request): View
    {
        $bom = $this->bomRepository->getBomWithComponents($id);
        abort_if(!$bom, 404, 'BOM not found.');

        // Requirements multi-level explosion
        $calcQty = $request->input('calc_qty') ? (float) $request->input('calc_qty') : $bom->base_quantity;
        $explosion = $this->explosionService->explode($bom->product_id, $calcQty, $bom->tenant_id);

        // BOM Cost Preview
        $costDetails = [];
        $totalCost = 0.0;
        foreach ($bom->items as $item) {
            $qty = $item->quantity;
            $grossQty = $qty * (1 + ($item->material_scrap_percentage / 100));
            $unitCost = $item->material->unit_cost ?? 0.0;
            $itemCost = $grossQty * $unitCost;
            $totalCost += $itemCost;

            $costDetails[] = [
                'material_name' => $item->material->name,
                'material_sku' => $item->material->sku,
                'quantity' => $qty,
                'scrap_percentage' => $item->material_scrap_percentage,
                'gross_quantity' => $grossQty,
                'uom_code' => $item->uom ? $item->uom->code : 'PCS',
                'unit_cost' => $unitCost,
                'total_cost' => $itemCost,
            ];
        }
        $costPerUnit = $bom->base_quantity > 0 ? ($totalCost / $bom->base_quantity) : 0.0;

        $costSummary = [
            'items' => $costDetails,
            'total_cost' => $totalCost,
            'cost_per_unit' => $costPerUnit,
        ];

        $componentProductIds = $bom->items->pluck('material_id')->unique();
        $componentBoms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->whereIn('product_id', $componentProductIds)
            ->get()
            ->groupBy('product_id');

        return view('modules.production.bom.show', compact('bom', 'explosion', 'calcQty', 'costSummary', 'componentBoms'));
    }

    public function create(Request $request): View
    {
        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        $materials = Product::whereIn('type', ['raw_material', 'component', 'finished_good', 'semi_finished'])->get(); // support multi-level
        $uoms = Uom::all();
        $routings = Routing::all();
        $selectedProductId = $request->query('product_id');

        return view('modules.production.bom.create', compact('products', 'materials', 'uoms', 'routings', 'selectedProductId'));
    }

    public function store(StoreProductionBomRequest $request): RedirectResponse
    {
        try {
            $dto = ProductionBomDTO::fromArray($request->validated());
            $bom = $this->bomService->create($dto, auth()->id() ?: 1);

            $routeParams = ['bom' => $bom->id];
            if ($request->filled('parent_product_id')) {
                $routeParams['parent_product_id'] = $request->input('parent_product_id');
            }

            return redirect()
                ->route('production.boms.show', $routeParams)
                ->with('success', 'BOM created successfully in draft mode.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $bom = $this->bomRepository->getBomWithComponents($id);
        abort_if(!$bom, 404, 'BOM not found.');
        abort_if(!$bom->isDraft() && !$bom->isUnderRevision(), 403, 'Approved BOMs cannot be edited directly.');

        $products = Product::whereIn('type', ['finished_good', 'semi_finished'])->get();
        $materials = Product::whereIn('type', ['raw_material', 'component', 'finished_good', 'semi_finished'])->get(); // support multi-level
        $uoms = Uom::all();
        $routings = Routing::all();

        return view('modules.production.bom.edit', compact('bom', 'products', 'materials', 'uoms', 'routings'));
    }

    public function update(int $id, UpdateProductionBomRequest $request): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        try {
            $dto = ProductionBomDTO::fromArray($request->validated());
            $this->bomService->update($id, $dto);

            $routeParams = ['bom' => $bom->id];
            if ($request->filled('parent_product_id')) {
                $routeParams['parent_product_id'] = $request->input('parent_product_id');
            }

            return redirect()
                ->route('production.boms.show', $routeParams)
                ->with('success', 'BOM updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->bomRepository->delete($id);

        return redirect()
            ->route('production.boms.index')
            ->with('success', 'BOM deleted successfully.');
    }

    public function submitApproval(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        try {
            $this->bomService->submitApproval($id);
            return redirect()
                ->back()
                ->with('success', 'BOM submitted for approval.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function approve(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        try {
            $this->bomService->approve($id, auth()->id() ?: 1);
            return redirect()
                ->back()
                ->with('success', 'BOM approved successfully. Any older approved version has been set to inactive.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reject(int $id, Request $request): RedirectResponse
    {
        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->bomService->reject($id, auth()->id() ?: 1, $request->input('comments'));
            return redirect()
                ->back()
                ->with('success', 'BOM rejected successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id, Request $request): RedirectResponse
    {
        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->bomService->cancel($id, auth()->id() ?: 1, $request->input('comments'));
            return redirect()
                ->back()
                ->with('success', 'BOM cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function duplicateVersion(Request $request, int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $request->validate([
            'new_version' => 'required|string|max:50',
        ]);

        try {
            $newBom = $this->bomService->duplicateVersion($id, $request->input('new_version'), auth()->id() ?: 1);
            return redirect()
                ->route('production.boms.edit', $newBom->id)
                ->with('success', "New BOM version {$newBom->version} created as draft. Review and save components.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
