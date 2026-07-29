<?php

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Repositories\DispatchOrderRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class DispatchOrderService
{
    public function __construct(
        private readonly DispatchOrderRepository $dispatchRepo
    ) {}

    /**
     * Format pending material requirements with calculated remaining quantities and stock availability.
     */
    public function getPendingMaterialRequirementsFormatted(): array
    {
        $materialRequirements = $this->dispatchRepo->getAllPendingMaterialRequirements();

        return $materialRequirements->map(function ($requirement) {
            $itemsData = $requirement->items->map(function ($item) {
                $alreadyDispatched = $this->dispatchRepo->getDispatchedQtyForMRItem($item->id);
                $remainingQty = max(0, (float) $item->quantity - $alreadyDispatched);
                $unreservedAvail = StockService::getAvailableStock((int) $item->product_id, (int) $item->warehouse_id);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_sku' => $item->product?->sku ?? '',
                    'sku' => $item->product?->sku ?? '',
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $item->warehouse?->name ?? 'Main Warehouse',
                    'available_qty' => $unreservedAvail,
                    'quantity_ordered' => (float) $item->quantity,
                    'ordered_qty' => (float) $item->quantity,
                    'quantity_reserved' => (float) ($item->quantity_reserved ?? 0),
                    'already_dispatched' => $alreadyDispatched,
                    'dispatched_qty' => $alreadyDispatched,
                    'remaining_qty' => $remainingQty,
                    'dispatch_qty' => $remainingQty,
                    'fully_dispatched' => $remainingQty <= 0,
                ];
            })->filter(fn ($i) => $i['remaining_qty'] > 0)->values();

            return [
                'id' => $requirement->id,
                'requirement_number' => $requirement->requirement_number,
                'sales_order_number' => $requirement->salesOrder?->sales_order_number ?? 'N/A',
                'sales_order' => $requirement->salesOrder?->sales_order_number ?? 'N/A',
                'customer_name' => $requirement->salesOrder?->customer?->name ?? 'N/A',
                'customer' => $requirement->salesOrder?->customer?->name ?? 'N/A',
                'items' => $itemsData,
            ];
        })->filter(fn ($do) => count($do['items']) > 0)->values()->toArray();
    }

    /**
     * Validate and create a new Dispatch Order.
     *
     * @throws Exception
     */
    public function createDispatchOrder(array $validated, int $userId): DispatchOrder
    {
        $req = MaterialRequirement::findOrFail($validated['material_requirement_id']);

        // 1. Remaining ordered qty & Stock availability validation
        foreach ($validated['items'] as $item) {
            $mrItem = MaterialRequirementItem::find($item['material_requirement_item_id']);
            if ($mrItem) {
                $alreadyDispatched = $this->dispatchRepo->getDispatchedQtyForMRItem($mrItem->id);
                $remainingQty = max(0, (float) $mrItem->quantity - $alreadyDispatched);

                if ((float) $item['quantity'] > $remainingQty) {
                    throw new Exception("Cannot dispatch {$item['quantity']} units for '{$mrItem->product?->name}'. Maximum remaining ordered quantity is {$remainingQty}.");
                }
            }

            $unreservedAvail = StockService::getAvailableStock((int) $item['product_id'], (int) $item['warehouse_id']);
            $reservedForThisItem = $mrItem ? (float) ($mrItem->quantity_reserved ?? 0) : 0;
            $totalPhysicalStock = $unreservedAvail + $reservedForThisItem;
            $product = Product::find($item['product_id']);

            if ((float) $item['quantity'] > $totalPhysicalStock) {
                throw new Exception("Insufficient physical stock for product '{$product?->name}'. Total Stock: {$totalPhysicalStock} (Available: {$unreservedAvail} + Reserved: {$reservedForThisItem}), Requested: {$item['quantity']}");
            }
        }

        // 2. Generate dispatch number
        $dispatchNumber = $this->dispatchRepo->getNextDispatchNumber();

        // 3. Prepare data & save via repository
        $dispatchData = [
            'tenant_id' => $req->tenant_id,
            'material_requirement_id' => $req->id,
            'sales_order_id' => $req->sales_order_id,
            'dispatch_number' => $dispatchNumber,
            'dispatch_date' => $validated['dispatch_date'],
            'status' => 'Pending',
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'driver_name' => $validated['driver_name'] ?? null,
            'driver_phone' => $validated['driver_phone'] ?? null,
            'shipping_agent' => $validated['shipping_agent'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $userId,
        ];

        return $this->dispatchRepo->createDispatchOrder($dispatchData, $validated['items']);
    }

    /**
     * Confirm dispatch order, deduct stock, and update sales order status.
     *
     * @throws Exception
     */
    public function confirmDispatchOrder(DispatchOrder $dispatch): DispatchOrder
    {
        if ($dispatch->status !== 'Pending') {
            throw new Exception('Only Pending Dispatch Orders can be confirmed.');
        }

        // 1. Stock Availability Validation
        foreach ($dispatch->items as $item) {
            $mrItem = MaterialRequirementItem::find($item->material_requirement_item_id);
            $reservedForThisItem = $mrItem ? (float) ($mrItem->quantity_reserved ?? 0) : 0;
            $unreservedAvail = StockService::getAvailableStock((int) $item->product_id, (int) $item->warehouse_id);
            $totalPhysicalStock = $unreservedAvail + $reservedForThisItem;
            $qtyToDispatch = (float) ($item->quantity_dispatched ?? $item->quantity_ordered);

            if ($qtyToDispatch > $totalPhysicalStock) {
                throw new Exception("Insufficient physical stock for product '{$item->product?->name}'. Total Stock: {$totalPhysicalStock} (Available: {$unreservedAvail} + Reserved: {$reservedForThisItem}), Required: {$qtyToDispatch}");
            }
        }

        // 2. Transaction execution
        return DB::transaction(function () use ($dispatch) {
            $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

            foreach ($dispatch->items as $item) {
                $mrItem = MaterialRequirementItem::find($item->material_requirement_item_id);
                $qtyToDispatch = (float) ($item->quantity_dispatched ?? $item->quantity_ordered);

                if ($mrItem && (float) $mrItem->quantity_reserved > 0) {
                    $consumeReserved = min($qtyToDispatch, (float) $mrItem->quantity_reserved);
                    $mrItem->decrement('quantity_reserved', $consumeReserved);

                    // Release reserved_qty from warehouse stock
                    $whStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->first();

                    if ($whStock && (float) $whStock->reserved_qty > 0) {
                        $whReleaseQty = min($consumeReserved, (float) $whStock->reserved_qty);
                        $newReserved = max(0.0, (float) $whStock->reserved_qty - $whReleaseQty);
                        $whStock->update([
                            'reserved_qty' => $newReserved,
                            'available_qty' => max(0.0, (float) $whStock->quantity - $newReserved),
                        ]);
                    }
                }

                StockService::recordOutflow(
                    $tenantId,
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    $qtyToDispatch,
                    'DispatchOrder',
                    (int) $dispatch->id
                );

                $item->update(['status' => 'Dispatched']);
            }

            $dispatch->update(['status' => 'Dispatched']);

            // Update linked Sales Order status
            if ($dispatch->salesOrder) {
                $so = $dispatch->salesOrder;
                $totalOrdered = $so->items->sum('quantity');
                $totalDispatched = $this->dispatchRepo->getDispatchedQtyForSalesOrder($so->id);

                if ($totalDispatched >= $totalOrdered) {
                    $so->update(['status' => 'Shipped']);
                } else if ($totalDispatched > 0) {
                    $so->update(['status' => 'Partially Shipped']);
                }
            }

            return $dispatch;
        });
    }
}
