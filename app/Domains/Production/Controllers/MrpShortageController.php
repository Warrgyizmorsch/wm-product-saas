<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MrpShortageController extends Controller
{
    public function __construct(
        private readonly MrpShortageService $mrpShortageService,
    ) {}

    /**
     * Display MRP Multi-Level BOM Explosion & Net Shortage Planning screen.
     */
    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();

        // Load Products & Warehouses for manual selection
        $products = Product::where('tenant_id', $tenantId)
            ->sellable()
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultWarehouseId = $warehouses->firstWhere('is_default', true)?->id ?? $warehouses->first()?->id;

        // Load Pending Material Requirements (MRs)
        $pendingMrs = $this->mrpShortageService->getPendingMaterialRequirements($tenantId);

        // Warehouse filter: null = All Warehouses (consolidated across all stores)
        $selectedWarehouseId = $request->has('warehouse_id') && $request->input('warehouse_id') !== ''
            ? (int) $request->input('warehouse_id')
            : null;

        // Automatically gather demand from ALL pending Material Requisitions (MRs)
        $demandInputs = [];
        foreach ($pendingMrs as $mr) {
            foreach ($mr->items as $item) {
                if (!$item->product_id) continue;
                $orderedQty = (float) ($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                $demandInputs[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $orderedQty,
                    'warehouse_id' => $selectedWarehouseId,
                    'source_ref' => "MR #{$mr->requirement_number}",
                ];
            }
        }

        // Run multi-level BOM explosion and shortage calculation
        $calculationResult = null;
        $currentPage = (int) $request->input('page', 1);
        $perPage = 10;
        $totalResults = 0;
        $totalPages = 1;

        if (!empty($demandInputs)) {
            $calculationResult = $this->mrpShortageService->calculateShortages(
                $demandInputs,
                $tenantId,
                $selectedWarehouseId
            );

            $allConsolidated = $calculationResult['consolidated'] ?? [];
            $totalResults = count($allConsolidated);
            $totalPages = (int) ceil($totalResults / $perPage);
            if ($totalPages < 1) $totalPages = 1;

            $offset = ($currentPage - 1) * $perPage;
            $calculationResult['consolidated'] = array_slice($allConsolidated, $offset, $perPage);
        }

        return view('modules.production.mrp-shortage.index', compact(
            'products',
            'warehouses',
            'defaultWarehouseId',
            'pendingMrs',
            'selectedWarehouseId',
            'calculationResult',
            'currentPage',
            'totalPages',
            'totalResults',
            'perPage'
        ));
    }

    /**
     * Form submit action to trigger MRP explosion calculation.
     */
    public function calculate(Request $request): RedirectResponse
    {
        $params = $request->only([
            'mode',
            'mr_ids',
            'product_id',
            'quantity',
            'warehouse_id'
        ]);

        return redirect()->route('production.mrp-shortage.index', array_filter($params));
    }

    /**
     * Action to generate consolidated Purchase Requisition (PR) for selected short items.
     */
    public function generatePr(Request $request): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $userId = auth()->id() ?: 1;

        // Fetch default warehouse for tenant if warehouse_id is empty (e.g. All Warehouses selected)
        $defaultWh = Warehouse::where('tenant_id', $tenantId)->where('is_default', 1)->first();
        $defaultWarehouseId = $defaultWh ? $defaultWh->id : Warehouse::where('tenant_id', $tenantId)->value('id');

        $inputWhId = $request->input('warehouse_id');
        $warehouseId = !empty($inputWhId) ? (int) $inputWhId : $defaultWarehouseId;

        $request->merge([
            'warehouse_id' => $warehouseId,
        ]);

        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.shortage_qty' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $itemsToProcure = [];
            $rawItems = $request->input('items', []);
            
            // Priority: Selected Filter Warehouse > Default Store
            $warehouseId = $request->input('warehouse_id');
            if (empty($warehouseId)) {
                $defaultWh = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->where('is_default', 1)->first();
                $warehouseId = $defaultWh ? $defaultWh->id : \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->value('id');
            }

            foreach ($rawItems as $key => $itemData) {
                // Must be explicitly selected by checkbox
                if (empty($itemData['selected'])) {
                    continue;
                }

                $productId = (int) $itemData['product_id'];
                $inputQty = isset($itemData['quantity']) && $itemData['quantity'] !== '' ? (float) $itemData['quantity'] : null;
                $shortageQty = isset($itemData['shortage_qty']) ? (float) $itemData['shortage_qty'] : 0.0;

                // Priority: Use typed quantity if provided, otherwise fallback to net shortage quantity
                $finalQty = $inputQty !== null ? $inputQty : $shortageQty;

                if ($finalQty <= 0) {
                    continue;
                }

                $itemsToProcure[] = [
                    'product_id' => $productId,
                    'quantity' => $finalQty,
                    'unit_cost' => (float) ($itemData['unit_cost'] ?? 0.0),
                    'warehouse_id' => $warehouseId,
                ];
            }

            if (empty($itemsToProcure)) {
                return back()->with('error', 'Please select at least one item with a quantity > 0 to generate Purchase Requisition.');
            }

            $pr = $this->mrpShortageService->generateConsolidatedPr(
                $itemsToProcure,
                $tenantId,
                $userId,
                $request->input('notes')
            );

            return redirect()->route('purchase.requisitions.show', $pr->id)
                ->with('success', "Purchase Requisition {$pr->requisition_number} successfully created with " . count($itemsToProcure) . " shortage item(s)!");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate Purchase Requisition: ' . $e->getMessage());
        }
    }
}
