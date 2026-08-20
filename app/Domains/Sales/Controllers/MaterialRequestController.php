<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Repositories\MaterialRequestRepository;
use App\Domains\Sales\Services\MaterialRequestService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MaterialRequestController extends Controller
{
    public function __construct(
        private readonly MaterialRequestRepository $requestRepo,
        private readonly MaterialRequestService $requestService,
    ) {}

    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $slips = $this->requestRepo->getPaginatedSlips($tenantId, $request->all(), 15);

        return view('modules.sales.material-requests.index', compact('slips'));
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $slip = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'items.product', 'items.uom', 'items.warehouse'])
            ->findOrFail($id);

        // Group items by product_id so duplicate items in the same slip merge into a single row
        $items = $slip->items->groupBy('product_id')->map(function ($itemsForProduct) use ($tenantId) {
            $first = $itemsForProduct->first();
            
            $totalPlanned  = (float) $itemsForProduct->sum('quantity_planned');
            $totalReserved = (float) $itemsForProduct->sum('quantity_reserved');
            $totalIssued   = (float) $itemsForProduct->sum('quantity_issued');
            
            $warehouseId = $first->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id;
            $availableStock = $warehouseId ? StockService::getAvailableStock($first->product_id, $warehouseId) : 0.0;

            $item = clone $first;
            $item->id = $first->id;
            $item->all_item_ids = $itemsForProduct->pluck('id')->toArray();
            $item->quantity_planned = $totalPlanned;
            $item->quantity_reserved = $totalReserved;
            $item->quantity_issued = $totalIssued;
            $item->available_stock = $availableStock;

            return $item;
        })->values();

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $existingPrItems = PurchaseRequisitionItem::where('tenant_id', $tenantId)
            ->whereHas('requisition', function ($q) use ($slip) {
                $q->where('source_type', 'material_request')
                  ->where('source_id', $slip->id)
                  ->where('status', '!=', 'Cancelled');
            })->get();

        return view('modules.sales.material-requests.show', compact('slip', 'items', 'warehouses', 'existingPrItems'));
    }

    public function reserve(Request $request, int $itemId)
    {
        $request->validate([
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $tenantId = require_tenant_id();

        try {
            $reserved = $this->requestService->reserve(
                $tenantId,
                $itemId,
                (float) $request->input('quantity'),
                $request->input('warehouse_id')
            );
            return redirect()->back()->with('success', "Reserved {$reserved} units successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function issue(Request $request, int $itemId)
    {
        $request->validate([
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer',
            'remarks'      => 'nullable|string',
        ]);

        $tenantId = require_tenant_id();

        try {
            $issued = $this->requestService->issue(
                $tenantId,
                $itemId,
                (float) $request->input('quantity'),
                $request->input('warehouse_id'),
                $request->input('remarks')
            );
            return redirect()->back()->with('success', "Issued {$issued} units successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function createPurchaseRequisition(Request $request, int $itemId)
    {
        $tenantId = require_tenant_id();

        try {
            $item = ProductionRequisitionSlipItem::findOrFail($itemId);
            
            // Find all slip items of the same product in this slip to include merged quantities
            $allItemIds = ProductionRequisitionSlipItem::where('production_requisition_slip_id', $item->production_requisition_slip_id)
                ->where('product_id', $item->product_id)
                ->pluck('id')
                ->toArray();

            $pr = $this->requestService->createBulkPurchaseRequisition(
                $tenantId,
                $allItemIds,
                $request->input('warehouse_id'),
                $request->input('notes')
            );
            return redirect()->back()->with('success', "Purchase Requisition {$pr->requisition_number} created successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulkAction(Request $request, int $id)
    {
        $request->validate([
            'action_type'   => 'required|string|in:reserve,issue,indent',
            'warehouse_id'  => 'nullable|integer',
            'item_ids'      => 'required|array',
            'item_ids.*'    => 'string',
            'action_qtys'   => 'nullable|array',
            'remarks'       => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $tenantId    = require_tenant_id();
        $actionType  = $request->input('action_type');
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $itemIds     = $request->input('item_ids', []);
        $actionQtys  = $request->input('action_qtys', []);
        $remarks     = $request->input('remarks');
        $notes       = $request->input('notes');

        // ── INDENT: Create ONE consolidated PR with all selected items ─────────
        if ($actionType === 'indent') {
            try {
                $expandedItemIds = [];
                foreach ($itemIds as $idStr) {
                    foreach (explode(',', (string) $idStr) as $parsedId) {
                        if (is_numeric($parsedId) && (int)$parsedId > 0) {
                            $expandedItemIds[] = (int)$parsedId;
                        }
                    }
                }
                $expandedItemIds = array_unique($expandedItemIds);

                $pr = $this->requestService->createBulkPurchaseRequisition(
                    $tenantId,
                    $expandedItemIds,
                    $warehouseId,
                    $notes
                );
                $itemCount = count($expandedItemIds);
                return redirect()->back()->with('success', "Purchase Requisition {$pr->requisition_number} created with {$itemCount} item(s) successfully.");
            } catch (InvalidArgumentException $e) {
                return redirect()->back()->with('error', $e->getMessage());
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Failed to create Purchase Requisition: ' . $e->getMessage());
            }
        }

        // ── RESERVE / ISSUE: Process each item individually ────────────────────
        $processedCount = 0;
        $errors = [];

        foreach ($itemIds as $idKey) {
            $idStr = (string) $idKey;
            $firstId = (int) explode(',', $idStr)[0];

            if ($firstId <= 0) {
                continue;
            }

            $qty = 0.0;
            if (isset($actionQtys[$idStr])) {
                $qty = (float) $actionQtys[$idStr];
            } elseif (isset($actionQtys[$firstId])) {
                $qty = (float) $actionQtys[$firstId];
            }

            if ($qty <= 0) {
                continue;
            }

            try {
                if ($actionType === 'reserve') {
                    $this->requestService->reserve($tenantId, $firstId, $qty, $warehouseId);
                    $processedCount++;
                } elseif ($actionType === 'issue') {
                    $this->requestService->issue($tenantId, $firstId, $qty, $warehouseId, $remarks);
                    $processedCount++;
                }
            } catch (InvalidArgumentException $e) {
                $errors[] = "Item #{$firstId}: " . $e->getMessage();
            } catch (\Exception $e) {
                $errors[] = "Item #{$firstId}: " . $e->getMessage();
            }
        }

        if ($processedCount > 0) {
            $msg = "Bulk '{$actionType}' processed for {$processedCount} item(s).";
            if (!empty($errors)) {
                $msg .= " Some items failed: " . implode('; ', $errors);
            }
            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', 'No items were processed. ' . implode('; ', $errors));
    }
}
