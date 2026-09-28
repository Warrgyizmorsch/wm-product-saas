<?php

namespace App\Domains\Inventory\Controllers;

use App\Domains\Inventory\Models\InventoryRemnant;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\RemnantInventoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemnantController extends Controller
{
    public function __construct(
        protected RemnantInventoryService $remnantService
    ) {}

    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();

        $query = InventoryRemnant::query()
            ->where('tenant_id', $tenantId)
            ->with(['product.uom', 'warehouse', 'batch', 'sourceOrder'])
            ->latest('id');

        // Filter: Product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // Filter: Measurement Type
        if ($request->filled('measurement_type')) {
            $query->where('measurement_type', $request->input('measurement_type'));
        }

        // Filter: Warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Filter: Rack / Bin Location
        if ($request->filled('warehouse_location')) {
            $query->where('warehouse_location', 'LIKE', '%' . $request->input('warehouse_location') . '%');
        }

        // Filter: Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter: Heat / Batch Number
        if ($request->filled('heat_number')) {
            $query->where('heat_number', 'LIKE', '%' . $request->input('heat_number') . '%');
        }

        // Filter: Length Range (mm)
        if ($request->filled('min_length')) {
            $query->where('current_length', '>=', (float) $request->input('min_length'));
        }
        if ($request->filled('max_length')) {
            $query->where('current_length', '<=', (float) $request->input('max_length'));
        }

        // Filter: Width Range (mm)
        if ($request->filled('min_width')) {
            $query->where('current_width', '>=', (float) $request->input('min_width'));
        }
        if ($request->filled('max_width')) {
            $query->where('current_width', '<=', (float) $request->input('max_width'));
        }

        // Filter: Age (days)
        if ($request->filled('max_age')) {
            $days = (int) $request->input('max_age');
            $query->where('created_at', '>=', now()->subDays($days));
        }

        // Filter: Text Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('remnant_code', 'LIKE', "%{$search}%")
                  ->orWhere('warehouse_location', 'LIKE', "%{$search}%")
                  ->orWhere('heat_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('sku', 'LIKE', "%{$search}%");
                  });
            });
        }

        $remnants = $query->paginate(20)->withQueryString();

        $products = Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return view('modules.inventory.remnants.index', [
            'remnants' => $remnants,
            'products' => $products,
            'warehouses' => $warehouses,
            'filters' => $request->all(),
        ]);
    }

    public function show(int $id): View
    {
        $tenantId = require_tenant_id();

        $remnant = InventoryRemnant::where('tenant_id', $tenantId)
            ->with([
                'product.uom',
                'warehouse',
                'batch',
                'parentRemnant',
                'childRemnants',
                'sourceOrder',
                'sourceOperation',
                'consumptions.performedByUser',
                'consumptions.splitRemnant',
                'consumptions.productionOrder',
                'allocations.order',
                'confirmedByUser',
                'createdByUser',
            ])
            ->findOrFail($id);

        return view('modules.inventory.remnants.show', [
            'remnant' => $remnant,
        ]);
    }

    public function confirm(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $remnant = InventoryRemnant::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $this->remnantService->confirmRemnant($remnant->id, auth()->id());
            return redirect()->back()->with('success', "Remnant [{$remnant->remnant_code}] confirmed and released to available inventory.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function split(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $remnant = InventoryRemnant::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'split_length' => 'nullable|numeric|min:0.01',
            'split_pieces' => 'nullable|integer|min:1',
            'split_quantity' => 'nullable|numeric|min:0.01',
            'warehouse_location' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->remnantService->splitRemnant($remnant->id, $request->all(), auth()->id());
            return redirect()->route('inventory.remnants.show', $result['child']->id)
                ->with('success', "Remnant split successfully. New remnant created: [{$result['child']->remnant_code}].");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function scrap(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $remnant = InventoryRemnant::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $this->remnantService->scrapRemnant($remnant->id, $request->input('reason'), auth()->id());
            return redirect()->back()->with('success', "Remnant [{$remnant->remnant_code}] has been marked as scrap.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
