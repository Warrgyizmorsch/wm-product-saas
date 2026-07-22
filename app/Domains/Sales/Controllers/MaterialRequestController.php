<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product']);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', $search)
                    ->orWhereHas('order', function ($o) use ($search) {
                        $o->where('order_number', 'like', $search);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $slips = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('modules.sales.material-requests.index', compact('slips'));
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();

        $slip = ProductionRequisitionSlip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'items.product', 'items.uom', 'items.warehouse'])
            ->findOrFail($id);

        $items = $slip->items->map(function ($item) use ($tenantId) {
            $warehouseId = $item->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id;
            $availableStock = $warehouseId ? StockService::getAvailableStock($item->product_id, $warehouseId) : 0.0;

            $item->available_stock = $availableStock;
            return $item;
        });

        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        $existingPrItems = PurchaseRequisitionItem::where('tenant_id', $tenantId)
            ->whereHas('requisition', function ($q) use ($slip) {
                $q->where('source_type', 'material_request')
                  ->where('source_id', $slip->id)
                  ->where('status', '!=', 'Cancelled');
            })
            ->get();

        return view('modules.sales.material-requests.show', compact('slip', 'items', 'warehouses', 'existingPrItems'));
    }

    public function reserve(Request $request, int $itemId)
    {
        $request->validate([
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $quantity    = (float) $request->input('quantity');
        $tenantId    = require_tenant_id();
        $requestedWh = $request->input('warehouse_id');

        return DB::transaction(function () use ($itemId, $quantity, $tenantId, $requestedWh) {
            $item = ProductionRequisitionSlipItem::lockForUpdate()->findOrFail($itemId);
            $slip = $item->slip;

            // Prefer warehouse from the form, then from the item, then the default.
            $warehouseId = $requestedWh
                ?? $item->warehouse_id
                ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id;

            if (!$warehouseId) {
                throw new InvalidArgumentException('No warehouse resolved for stock reservation.');
            }

            $availableQty  = StockService::getAvailableStock($item->product_id, $warehouseId);
            $qtyToReserve  = min($quantity, $availableQty);

            if ($qtyToReserve <= 0) {
                return redirect()->back()->with('error', 'No available stock in the selected warehouse to reserve.');
            }

            // Reserve in the inventory layer (StockReservation / ProductWarehouseStock)
            StockService::reserveStock(
                $tenantId,
                $item->product_id,
                $warehouseId,
                $qtyToReserve,
                'Production Order',
                $slip->production_order_id,
                $item->id
            );

            // Also create / update a ProductionOrderReservation record so that
            // the issue step can reference it via reservation_id.
            $poReservation = ProductionOrderReservation::firstOrCreate(
                [
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $slip->production_order_id,
                    'product_id'          => $item->product_id,
                    'warehouse_id'        => $warehouseId,
                ],
                [
                    'bom_item_id'       => null,
                    'quantity_planned'  => $item->quantity_planned,
                    'quantity_reserved' => 0.0,
                    'quantity_issued'   => 0.0,
                    'uom_id'            => $item->uom_id,
                ]
            );
            $poReservation->increment('quantity_reserved', $qtyToReserve);

            // Store the reservation id on the slip item so issue() can look it up.
            $item->warehouse_id        = $warehouseId;
            $item->quantity_reserved  += $qtyToReserve;
            $item->save();

            $this->updateSlipStatus($slip);

            return redirect()->back()->with('success', "Reserved {$qtyToReserve} units of {$item->product->name} successfully.");
        });
    }

    public function issue(Request $request, int $itemId)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer',
            'remarks' => 'nullable|string',
        ]);

        $quantity = (float) $request->input('quantity');
        $warehouseId = $request->input('warehouse_id');
        $remarks = $request->input('remarks');
        $tenantId = require_tenant_id();

        return DB::transaction(function () use ($itemId, $quantity, $warehouseId, $remarks, $tenantId) {
            $item = ProductionRequisitionSlipItem::lockForUpdate()->findOrFail($itemId);
            $slip = $item->slip;

            $resolvedWarehouseId = $warehouseId ?: ($item->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id);
            if (!$resolvedWarehouseId) {
                throw new InvalidArgumentException('No warehouse resolved for material issue.');
            }

            $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
            if ($quantity > $remainingToIssue) {
                throw new InvalidArgumentException("Cannot issue more than the remaining planned quantity ({$remainingToIssue}).");
            }

            $availableQty = StockService::getAvailableStock($item->product_id, $resolvedWarehouseId);
            $maxAllowed = (float) $item->quantity_reserved + $availableQty;
            if ($quantity > $maxAllowed) {
                throw new InvalidArgumentException("Cannot issue {$quantity} units. Only {$maxAllowed} units are available (Reserved: {$item->quantity_reserved}, Warehouse Available: {$availableQty}).");
            }

            // recordOutflow() does TWO things:
            //   1. Calls releaseStock() internally — removes from reserved_qty, recalculates available_qty
            //   2. Deducts from physical quantity (on-hand) and creates a StockTransaction (OUT)
            StockService::recordOutflow(
                $tenantId,
                $item->product_id,
                $resolvedWarehouseId,
                $quantity,
                'Production Order',
                $slip->production_order_id
            );

            $qtyFromReserved = min($quantity, (float) $item->quantity_reserved);
            $item->quantity_reserved -= $qtyFromReserved;
            $item->quantity_issued += $quantity;
            $item->save();

            // Look up the ProductionOrderReservation that was created during reserve()
            // so we can satisfy the FK. Falls back to null (allowed since migration
            // 2026_07_16_000001 made the column nullable).
            $poReservation = ProductionOrderReservation::where('tenant_id', $tenantId)
                ->where('production_order_id', $slip->production_order_id)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $resolvedWarehouseId)
                ->first();

            if (!$poReservation) {
                $poReservation = ProductionOrderReservation::where('tenant_id', $tenantId)
                    ->where('production_order_id', $slip->production_order_id)
                    ->where('product_id', $item->product_id)
                    ->first();
            }

            // Insert into production_order_issues for MES / execution tracking compatibility
            DB::table('production_order_issues')->insert([
                'tenant_id'           => $tenantId,
                'production_order_id' => $slip->production_order_id,
                'reservation_id'      => $poReservation?->id,   // nullable FK
                'product_id'          => $item->product_id,
                'warehouse_id'        => $resolvedWarehouseId,
                'quantity_issued'     => $quantity,
                'issued_at'           => now(),
                'issued_by'           => auth()->id() ?: 1,
                'remarks'             => $remarks,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Reduce the matching ProductionOrderReservation quantity
            if ($poReservation) {
                $poReservation->increment('quantity_issued', $quantity);
                $poReservation->decrement('quantity_reserved', min($quantity, $poReservation->quantity_reserved));
            }

            $this->updateSlipStatus($slip);

            return redirect()->back()->with('success', "Issued {$quantity} units of {$item->product->name} successfully.");
        });
    }

    public function createPurchaseRequisition(Request $request, int $itemId)
    {
        $tenantId = require_tenant_id();
        $warehouseId = $request->input('warehouse_id');
        $notes = $request->input('notes');

        return DB::transaction(function () use ($itemId, $warehouseId, $notes, $tenantId) {
            $item = ProductionRequisitionSlipItem::findOrFail($itemId);
            $warehouseStock = (float) \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $warehouseId ?: $item->warehouse_id);

            $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
            $shortageQty = max(0.0, $remainingToIssue - ((float) $item->quantity_reserved + $warehouseStock));

            if ($shortageQty <= 0) {
                return redirect()->back()->with('error', 'No shortage for this item in the selected warehouse.');
            }

            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date' => now()->toDateString(),
                'status' => 'Draft',
                'source_type' => 'material_request',
                'source_id' => $item->slip->id,
                'notes' => $notes ?: 'Shortage purchase requisition generated from Material Request Slip #' . $item->slip->requisition_number,
                'requested_by' => auth()->id() ?: 1,
            ]);

            PurchaseRequisitionItem::create([
                'purchase_requisition_id' => $pr->id,
                'product_id' => $item->product_id,
                'quantity' => $shortageQty,
                'warehouse_id' => $warehouseId ?: $item->warehouse_id,
                'estimated_cost' => $item->product->unit_cost ?? 0.00,
            ]);

            return redirect()->back()->with('success', "Draft Purchase Requisition {$requisitionNumber} created for shortage quantity {$shortageQty}.");
        });
    }

    public function bulkAction(Request $request, int $slipId)
    {
        $tenantId = require_tenant_id();
        $actionType = $request->input('action_type'); // 'reserve', 'issue', 'indent'
        $itemIds = $request->input('item_ids');
        $warehouseId = $request->input('warehouse_id');
        $remarks = $request->input('remarks');
        $notes = $request->input('notes');
        $actionQtys = $request->input('action_qtys', []);

        if (empty($itemIds) || !is_array($itemIds)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        if (in_array($actionType, ['reserve', 'issue']) && !$warehouseId) {
            return redirect()->back()->with('error', 'Select a warehouse first.');
        }

        return DB::transaction(function () use ($slipId, $actionType, $itemIds, $warehouseId, $remarks, $notes, $actionQtys, $tenantId) {
            $slip = ProductionRequisitionSlip::findOrFail($slipId);
            $items = ProductionRequisitionSlipItem::whereIn('id', $itemIds)->lockForUpdate()->get();

            if ($actionType === 'reserve') {
                $count = 0;
                foreach ($items as $item) {
                    $quantity = isset($actionQtys[$item->id]) ? (float) $actionQtys[$item->id] : 0.0;
                    if ($quantity <= 0.0001) {
                        continue;
                    }

                    $remainingToReserve = max(0.0, (float) $item->quantity_planned - ((float) $item->quantity_issued + (float) $item->quantity_reserved));
                    if ($quantity > $remainingToReserve) {
                        throw new InvalidArgumentException("Cannot reserve {$quantity} units for product {$item->product->name}. Maximum remaining allowed is {$remainingToReserve}.");
                    }

                    $availableQty = StockService::getAvailableStock($item->product_id, $warehouseId);
                    if ($quantity > $availableQty) {
                        throw new InvalidArgumentException("Cannot reserve {$quantity} units for product {$item->product->name}. Only {$availableQty} units are available in this warehouse.");
                    }

                    // Reserve stock
                    StockService::reserveStock(
                        $tenantId,
                        $item->product_id,
                        $warehouseId,
                        $quantity,
                        'Production Order',
                        $slip->production_order_id,
                        $item->id
                    );

                    // ProductionOrderReservation update/create
                    $poReservation = ProductionOrderReservation::firstOrCreate(
                        [
                            'tenant_id'           => $tenantId,
                            'production_order_id' => $slip->production_order_id,
                            'product_id'          => $item->product_id,
                            'warehouse_id'        => $warehouseId,
                        ],
                        [
                            'bom_item_id'       => null,
                            'quantity_planned'  => $item->quantity_planned,
                            'quantity_reserved' => 0.0,
                            'quantity_issued'   => 0.0,
                            'uom_id'            => $item->uom_id,
                        ]
                    );
                    $poReservation->increment('quantity_reserved', $quantity);

                    $item->warehouse_id = $warehouseId;
                    $item->quantity_reserved += $quantity;
                    $item->save();
                    $count++;
                }

                if ($count === 0) {
                    return redirect()->back()->with('error', 'No valid quantities were specified for reservation.');
                }

                $this->updateSlipStatus($slip);
                return redirect()->back()->with('success', "Successfully reserved stock for {$count} items.");

            } elseif ($actionType === 'issue') {
                $count = 0;
                foreach ($items as $item) {
                    $quantity = isset($actionQtys[$item->id]) ? (float) $actionQtys[$item->id] : 0.0;
                    if ($quantity <= 0.0001) {
                        continue;
                    }

                    $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
                    if ($quantity > $remainingToIssue) {
                        throw new InvalidArgumentException("Cannot issue {$quantity} units for product {$item->product->name}. Maximum remaining allowed is {$remainingToIssue}.");
                    }

                    $availableQty = StockService::getAvailableStock($item->product_id, $warehouseId);
                    $maxAllowed = (float) $item->quantity_reserved + $availableQty;
                    if ($quantity > $maxAllowed) {
                        throw new InvalidArgumentException("Cannot issue {$quantity} units for product {$item->product->name}. Only {$maxAllowed} units are available (Reserved: {$item->quantity_reserved}, Warehouse: {$availableQty}).");
                    }

                    // Record outflow
                    StockService::recordOutflow(
                        $tenantId,
                        $item->product_id,
                        $warehouseId,
                        $quantity,
                        'Production Order',
                        $slip->production_order_id
                    );

                    $qtyFromReserved = min($quantity, (float) $item->quantity_reserved);
                    $item->quantity_reserved -= $qtyFromReserved;
                    $item->quantity_issued += $quantity;
                    $item->save();

                    $poReservation = ProductionOrderReservation::where('tenant_id', $tenantId)
                        ->where('production_order_id', $slip->production_order_id)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    if (!$poReservation) {
                        $poReservation = ProductionOrderReservation::where('tenant_id', $tenantId)
                            ->where('production_order_id', $slip->production_order_id)
                            ->where('product_id', $item->product_id)
                            ->first();
                    }

                    // Log issue in production_order_issues
                    DB::table('production_order_issues')->insert([
                        'tenant_id'           => $tenantId,
                        'production_order_id' => $slip->production_order_id,
                        'reservation_id'      => $poReservation?->id,
                        'product_id'          => $item->product_id,
                        'warehouse_id'        => $warehouseId,
                        'quantity_issued'     => $quantity,
                        'issued_at'           => now(),
                        'issued_by'           => auth()->id() ?: 1,
                        'remarks'             => $remarks,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    if ($poReservation) {
                        $poReservation->increment('quantity_issued', $quantity);
                        $poReservation->decrement('quantity_reserved', min($quantity, $poReservation->quantity_reserved));
                    }
                    $count++;
                }

                if ($count === 0) {
                    return redirect()->back()->with('error', 'No valid quantities were specified for material issue.');
                }

                $this->updateSlipStatus($slip);
                return redirect()->back()->with('success', "Successfully issued materials for {$count} items.");

            } elseif ($actionType === 'indent') {
                $shortages = [];
                foreach ($items as $item) {
                    $quantity = isset($actionQtys[$item->id]) ? (float) $actionQtys[$item->id] : 0.0;
                    if ($quantity <= 0.0001) {
                        continue;
                    }

                    $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
                    if ($quantity > $remainingToIssue) {
                        throw new InvalidArgumentException("Cannot indent {$quantity} units for product {$item->product->name}. Maximum remaining allowed is {$remainingToIssue}.");
                    }

                    $shortages[] = [
                        'item' => $item,
                        'qty' => $quantity
                    ];
                }

                if (empty($shortages)) {
                    return redirect()->back()->with('error', 'No valid quantities specified for indent creation.');
                }

                // Create consolidated PR
                $year = now()->format('Y');
                $prefix = "PR-{$year}-";
                $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                    ->where('requisition_number', 'like', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();
                $nextNum = 1;
                if ($lastPr) {
                    $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                    $nextNum = ((int) $lastNumStr) + 1;
                }
                $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

                $pr = PurchaseRequisition::create([
                    'tenant_id' => $tenantId,
                    'requisition_number' => $requisitionNumber,
                    'requisition_date' => now()->toDateString(),
                    'status' => 'Draft',
                    'source_type' => 'material_request',
                    'source_id' => $slip->id,
                    'notes' => $notes ?: 'Consolidated shortage purchase requisition generated from Material Request Slip #' . $slip->requisition_number,
                    'requested_by' => auth()->id() ?: 1,
                ]);

                foreach ($shortages as $s) {
                    $item = $s['item'];
                    $qty = $s['qty'];
                    PurchaseRequisitionItem::create([
                        'purchase_requisition_id' => $pr->id,
                        'product_id' => $item->product_id,
                        'quantity' => $qty,
                        'warehouse_id' => $warehouseId ?: $item->warehouse_id,
                        'estimated_cost' => $item->product->unit_cost ?? 0.00,
                    ]);
                }

                return redirect()->back()->with('success', "Consolidated Draft Purchase Requisition {$requisitionNumber} created for the selected shortages.");
            }

            return redirect()->back()->with('error', 'Invalid action type.');
        });
    }

    private function updateSlipStatus(ProductionRequisitionSlip $slip)
    {
        $slip->loadMissing('items');
        $allCompleted = true;
        $anyIssued = false;

        foreach ($slip->items as $item) {
            if ($item->quantity_issued < $item->quantity_planned) {
                $allCompleted = false;
            }
            if ($item->quantity_issued > 0 || $item->quantity_reserved > 0) {
                $anyIssued = true;
            }
        }

        if ($allCompleted) {
            $slip->status = 'completed';
        } elseif ($anyIssued) {
            $slip->status = 'partial';
        } else {
            $slip->status = 'pending';
        }

        $slip->save();
    }
}
