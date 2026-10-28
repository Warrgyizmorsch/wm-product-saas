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
            $itemsData = $requirement->items->filter(function ($item) {
                if ($item->product && $item->product->isService()) {
                    return false;
                }
                $relQty = $item->quantity_released !== null ? (float) $item->quantity_released : (float) $item->quantity;
                return $relQty > 0 || in_array($item->status, ['Released', 'Picked', 'Packed', 'Dispatched']);
            })->map(function ($item) {
                $alreadyDispatched = $this->dispatchRepo->getDispatchedQtyForMRItem($item->id);
                $relQty = $item->quantity_released !== null ? (float) $item->quantity_released : (float) $item->quantity;
                $remainingQty = max(0.0, $relQty - $alreadyDispatched);
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
                    'quantity_ordered' => $relQty,
                    'ordered_qty' => $relQty,
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
                'sales_order_id' => $requirement->sales_order_id,
                'sales_order_number' => $requirement->salesOrder?->sales_order_number ?? 'N/A',
                'sales_order' => $requirement->salesOrder?->sales_order_number ?? 'N/A',
                'customer_name' => $requirement->salesOrder?->customer?->name ?? 'N/A',
                'customer' => $requirement->salesOrder?->customer?->name ?? 'N/A',
                'freight_terms' => $requirement->salesOrder?->freight_terms ?? 'To Pay',
                'freight_amount' => (float) ($requirement->salesOrder?->freight_amount ?? 0),
                'items' => $itemsData,
            ];
        })->filter(fn ($do) => count($do['items']) > 0)->values()->toArray();
    }

    /**
     * Get unfulfilled active invoices for a given Sales Order.
     */
    public function getFormattedInvoicesForSalesOrder(int $salesOrderId): array
    {
        $invoices = \App\Domains\Sales\Models\Invoice::with(['items.product', 'items.warehouse'])
            ->where('sales_order_id', $salesOrderId)
            ->whereIn('status', ['Posted', 'Partially Paid', 'Paid'])
            ->get();

        $mr = MaterialRequirement::with('items.warehouse')
            ->where('sales_order_id', $salesOrderId)
            ->first();

        $mrItems = $mr ? $mr->items : collect();

        return $invoices->map(function ($invoice) use ($mrItems) {
            $itemsData = $invoice->items->map(function ($item) use ($mrItems) {
                $alreadyDispatched = $this->dispatchRepo->getDispatchedQtyForInvoiceItem($item->id);
                $remainingQty = max(0, (float) $item->quantity - $alreadyDispatched);

                $matchingMrItem = null;
                if (!empty($item->material_requirement_item_id)) {
                    $matchingMrItem = $mrItems->firstWhere('id', $item->material_requirement_item_id);
                }
                if (!$matchingMrItem && !empty($item->sales_order_item_id)) {
                    $matchingMrItem = $mrItems->firstWhere('sales_order_item_id', $item->sales_order_item_id);
                }
                if (!$matchingMrItem) {
                    $matchingMrItem = $mrItems->firstWhere('product_id', $item->product_id);
                }

                $warehouseId = $item->warehouse_id ?: ($matchingMrItem?->warehouse_id ?: 1);
                $warehouseName = $item->warehouse?->name ?: ($matchingMrItem?->warehouse?->name ?: 'Main Warehouse');
                $quantityReserved = (float) ($matchingMrItem?->quantity_reserved ?? 0);
                $unreservedAvail = StockService::getAvailableStock((int) $item->product_id, (int) $warehouseId);

                return [
                    'id' => $item->id,
                    'invoice_item_id' => $item->id,
                    'material_requirement_item_id' => $matchingMrItem?->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_sku' => $item->product?->sku ?? '',
                    'sku' => $item->product?->sku ?? '',
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouseName,
                    'available_qty' => $unreservedAvail,
                    'quantity_reserved' => $quantityReserved,
                    'quantity_ordered' => (float) $item->quantity,
                    'invoiced_qty' => (float) $item->quantity,
                    'already_dispatched' => $alreadyDispatched,
                    'dispatched_qty' => $alreadyDispatched,
                    'remaining_qty' => $remainingQty,
                    'dispatch_qty' => $remainingQty,
                    'fully_dispatched' => $remainingQty <= 0,
                ];
            })->filter(fn ($i) => $i['remaining_qty'] > 0)->values();

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date ? $invoice->invoice_date->format('d-M-Y') : '',
                'total_amount' => (float) $invoice->total_amount,
                'status' => $invoice->status,
                'items' => $itemsData,
            ];
        })->filter(fn ($inv) => count($inv['items']) > 0)->values()->toArray();
    }

    /**
     * Validate and create a new Dispatch Order, reserving stock immediately in Pending state.
     *
     * @throws Exception
     */
    public function createDispatchOrder(array $validated, int $userId): DispatchOrder
    {
        $mrId = $validated['material_requirement_id'] ?? null;
        $soId = $validated['sales_order_id'] ?? null;

        $req = $mrId ? MaterialRequirement::find($mrId) : ($soId ? MaterialRequirement::where('sales_order_id', $soId)->first() : null);
        $finalSalesOrderId = $soId ?? $req?->sales_order_id;

        if (!$req && $finalSalesOrderId) {
            $soForMr = \App\Domains\Sales\Models\SalesOrder::find($finalSalesOrderId);
            if ($soForMr && $soForMr->requiresMaterialRequirement()) {
                $req = app(\App\Domains\Sales\Repositories\SalesOrderRepository::class)->confirmOrder($soForMr);
            }
        }

        $finalMrId = $req?->id ?? $mrId;
        $tenantId = require_tenant_id();

        if ($finalSalesOrderId) {
            $so = \App\Domains\Sales\Models\SalesOrder::find($finalSalesOrderId);
            if ($so && !in_array($so->status, ['Confirmed', 'Partially Shipped', 'In Fulfillment'])) {
                throw new Exception("Sales Order {$so->sales_order_number} must be Confirmed before creating a Dispatch Order.");
            }
            if ($so && $so->hasOnlyServices()) {
                throw new Exception("Sales Order {$so->sales_order_number} contains only service items. Services cannot be dispatched as they do not require physical shipping.");
            }
        }

        // 1. Remaining ordered qty & Stock availability validation
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if ($product && $product->isService()) {
                throw new Exception("Product '{$product->name}' is a service item and cannot be included in physical dispatch orders.");
            }

            $mrItemId = $item['material_requirement_item_id'] ?? null;
            $mrItem = $mrItemId ? MaterialRequirementItem::find($mrItemId) : null;
            if (!$mrItem && !empty($finalSalesOrderId)) {
                $mrItem = MaterialRequirementItem::whereHas('materialRequirement', function($q) use ($finalSalesOrderId) {
                    $q->where('sales_order_id', $finalSalesOrderId);
                })->where('product_id', $item['product_id'])->first();
            }

            if ($mrItem) {
                $alreadyDispatched = $this->dispatchRepo->getDispatchedQtyForMRItem($mrItem->id);
                $relQty = $mrItem->quantity_released !== null ? (float)$mrItem->quantity_released : (float)$mrItem->quantity;
                $remainingReleased = max(0.0, $relQty - $alreadyDispatched);

                if ((float) $item['quantity'] > $remainingReleased) {
                    throw new Exception("Cannot dispatch {$item['quantity']} units for '{$mrItem->product?->name}'. Maximum remaining released quantity for dispatch is {$remainingReleased}.");
                }
            }

            $invItemId = $item['invoice_item_id'] ?? null;
            $invItem = $invItemId ? \App\Domains\Sales\Models\InvoiceItem::find($invItemId) : null;
            if ($invItem) {
                $alreadyDispatchedInv = $this->dispatchRepo->getDispatchedQtyForInvoiceItem($invItem->id);
                $remainingInvQty = max(0, (float) $invItem->quantity - $alreadyDispatchedInv);

                if ((float) $item['quantity'] > $remainingInvQty) {
                    throw new Exception("Cannot dispatch {$item['quantity']} units for '{$invItem->product?->name}'. Maximum remaining invoiced quantity is {$remainingInvQty}.");
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

        $branchId = $validated['branch_id'] ?? $req?->branch_id ?? $req?->salesOrder?->branch_id ?? branch_id() ?? auth()->user()?->branch_id;
        $companyId = $validated['company_id'] ?? $req?->company_id ?? $req?->salesOrder?->company_id ?? company_id() ?? auth()->user()?->company_id;

        // 2. Generate dispatch number
        $dispatchNumber = $this->dispatchRepo->getNextDispatchNumber($tenantId, $companyId, $branchId);

        // 3. Generate Gate Pass Number
        $year = date('Y');
        $lastGpQuery = DispatchOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('gate_pass_number');
        if ($companyId !== null) {
            $lastGpQuery->where('company_id', $companyId);
        }
        if ($branchId !== null) {
            $lastGpQuery->where('branch_id', $branchId);
        }
        $lastGp = $lastGpQuery->orderBy('id', 'desc')->value('gate_pass_number');
        $gpSeq = $lastGp ? ((int) preg_replace('/[^0-9]/', '', substr($lastGp, -4))) + 1 : 1;

        do {
            $gatePassNumber = 'GP-' . $year . '-' . str_pad($gpSeq, 4, '0', STR_PAD_LEFT);
            $gpSeq++;

            $checkGpQuery = DispatchOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gate_pass_number', $gatePassNumber);
            if ($companyId !== null) {
                $checkGpQuery->where('company_id', $companyId);
            }
            if ($branchId !== null) {
                $checkGpQuery->where('branch_id', $branchId);
            }
        } while ($checkGpQuery->exists());

        // 4. Prepare data & save via repository inside transaction
        $dispatchData = [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $validated['customer_id'] ?? ($req?->salesOrder?->customer_id),
            'transporter_id' => $validated['transporter_id'] ?? null,
            'material_requirement_id' => $finalMrId,
            'sales_order_id' => $finalSalesOrderId,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'dispatch_number' => $dispatchNumber,
            'dispatch_date' => $validated['dispatch_date'],
            'status' => 'Pending',
            'carrier' => $validated['carrier'] ?? null,
            'tracking_number' => $validated['tracking_number'] ?? null,
            'eway_bill_number' => $validated['eway_bill_number'] ?? null,
            'eway_bill_date' => $validated['eway_bill_date'] ?? null,
            'lr_number' => $validated['lr_number'] ?? null,
            'lr_date' => $validated['lr_date'] ?? null,
            'freight_terms' => $validated['freight_terms'] ?? $req?->salesOrder?->freight_terms ?? 'To Pay',
            'freight_amount' => isset($validated['freight_amount']) ? convert_to_base((float)$validated['freight_amount']) : ($req?->salesOrder?->freight_amount ?? 0.00),
            'shipping_address' => $validated['shipping_address'] ?? null,
            'total_packages' => $validated['total_packages'] ?? null,
            'gross_weight' => $validated['gross_weight'] ?? null,
            'net_weight' => $validated['net_weight'] ?? null,
            'volume_cbm' => $validated['volume_cbm'] ?? null,
            'gate_pass_number' => $gatePassNumber,
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'driver_name' => $validated['driver_name'] ?? null,
            'driver_phone' => $validated['driver_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $userId,
        ];

        return DB::transaction(function () use ($dispatchData, $validated, $tenantId) {
            $dispatch = $this->dispatchRepo->createDispatchOrder($dispatchData, $validated['items']);

            // Immediately reserve stock in Pending state, transferring any existing MR reservation
            foreach ($dispatch->items as $item) {
                $qtyToReserve = (float) ($item->quantity_dispatched ?? $item->quantity_ordered);
                $mrItem = $item->material_requirement_item_id ? MaterialRequirementItem::find($item->material_requirement_item_id) : null;
                if (!$mrItem && !empty($dispatchData['sales_order_id'])) {
                    $mrItem = MaterialRequirementItem::whereHas('materialRequirement', function($q) use ($dispatchData) {
                        $q->where('sales_order_id', $dispatchData['sales_order_id']);
                    })->where('product_id', $item->product_id)->first();

                    if ($mrItem) {
                        $item->update(['material_requirement_item_id' => $mrItem->id]);
                    }
                }
                $mrReserved = $mrItem ? (float) ($mrItem->quantity_reserved ?? 0) : 0;

                $coveredByMR = min($qtyToReserve, $mrReserved);
                $netNewReservation = max(0.0, $qtyToReserve - $coveredByMR);

                if ($mrItem && $coveredByMR > 0) {
                    // Consume MR reservation so it transfers into DO reservation without double counting
                    $mrItem->decrement('quantity_reserved', $coveredByMR);

                    $mrRes = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->whereIn('reference_type', ['DeliveryOrder', 'MaterialRequirement'])
                        ->where('reference_id', $mrItem->material_requirement_id)
                        ->where('status', 'Active')
                        ->first();

                    if ($mrRes) {
                        $remQty = max(0.0, (float)$mrRes->reserved_qty - $coveredByMR);
                        if ($remQty <= 0.0001) {
                            $mrRes->update(['reserved_qty' => 0, 'status' => 'Completed']);
                        } else {
                            $mrRes->update(['reserved_qty' => $remQty]);
                        }
                    }
                }

                if ($netNewReservation > 0) {
                    // Reserve additional unreserved stock
                    StockService::reserveStock(
                        $tenantId,
                        (int) $item->product_id,
                        (int) $item->warehouse_id,
                        $netNewReservation,
                        'DispatchOrder',
                        (int) $dispatch->id,
                        (int) $item->id
                    );
                } else {
                    // Create active StockReservation record for DO without adding duplicate warehouse reserved_qty
                    \App\Domains\Inventory\Models\StockReservation::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $item->warehouse_id,
                        'reference_type' => 'DispatchOrder',
                        'reference_id' => $dispatch->id,
                        'reference_item_id' => $item->id,
                        'reserved_qty' => $qtyToReserve,
                        'status' => 'Active',
                    ]);
                }

                $item->update(['status' => 'Reserved']);

                // Sync allocated_qty on linked SalesOrderItem
                $soItem = $item->salesOrderItem;
                if (!$soItem && !empty($dispatchData['sales_order_id'])) {
                    $soItem = \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $dispatchData['sales_order_id'])
                        ->where('product_id', $item->product_id)
                        ->first();
                }
                if ($soItem) {
                    $soItem->increment('allocated_qty', $qtyToReserve);
                    $soItem->refreshStatus();
                }
            }

            if ($dispatch->salesOrder) {
                $dispatch->salesOrder->refreshFulfillmentStatus();
            }

            return $dispatch;
        });
    }

    /**
     * Update an existing Dispatch Order and dynamically adjust stock reservations.
     * E.g. If qty updated from 10 to 8 -> releases 2 units to available stock.
     * If qty updated from 10 to 12 -> reserves 2 additional units from available stock.
     *
     * @throws Exception
     */
    public function updateDispatchOrder(DispatchOrder $dispatch, array $validated): DispatchOrder
    {
        if (in_array($dispatch->status, ['Shipped', 'Dispatched', 'Delivered', 'Cancelled'])) {
            throw new Exception("Cannot edit Dispatch Order in {$dispatch->status} status.");
        }

        $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

        return DB::transaction(function () use ($dispatch, $validated, $tenantId) {
            $dispatch->update([
                'dispatch_date' => $validated['dispatch_date'] ?? $dispatch->dispatch_date,
                'transporter_id' => $validated['transporter_id'] ?? $dispatch->transporter_id,
                'carrier' => $validated['carrier'] ?? $dispatch->carrier,
                'vehicle_number' => $validated['vehicle_number'] ?? $dispatch->vehicle_number,
                'driver_name' => $validated['driver_name'] ?? $dispatch->driver_name,
                'driver_phone' => $validated['driver_phone'] ?? $dispatch->driver_phone,
                'tracking_number' => $validated['tracking_number'] ?? $dispatch->tracking_number,
                'eway_bill_number' => $validated['eway_bill_number'] ?? $dispatch->eway_bill_number,
                'eway_bill_date' => $validated['eway_bill_date'] ?? $dispatch->eway_bill_date,
                'lr_number' => $validated['lr_number'] ?? $dispatch->lr_number,
                'lr_date' => $validated['lr_date'] ?? $dispatch->lr_date,
                'freight_terms' => $validated['freight_terms'] ?? $dispatch->freight_terms,
                'freight_amount' => isset($validated['freight_amount']) ? convert_to_base((float)$validated['freight_amount']) : $dispatch->freight_amount,
                'shipping_address' => $validated['shipping_address'] ?? $dispatch->shipping_address,
                'total_packages' => $validated['total_packages'] ?? $dispatch->total_packages,
                'gross_weight' => $validated['gross_weight'] ?? $dispatch->gross_weight,
                'net_weight' => $validated['net_weight'] ?? $dispatch->net_weight,
                'volume_cbm' => $validated['volume_cbm'] ?? $dispatch->volume_cbm,
                'notes' => $validated['notes'] ?? $dispatch->notes,
            ]);

            if (isset($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $doItem = null;
                    if (isset($itemData['id'])) {
                        $doItem = $dispatch->items->find($itemData['id']);
                    }

                    if ($doItem) {
                        $oldQty = (float) ($doItem->quantity_dispatched ?? $doItem->quantity_ordered);
                        $newQty = (float) ($itemData['quantity'] ?? $oldQty);

                        if (abs($newQty - $oldQty) > 0.0001) {
                            $mrItem = $doItem->material_requirement_item_id ? MaterialRequirementItem::find($doItem->material_requirement_item_id) : null;

                            if ($newQty < $oldQty) {
                                // Qty decreased (10 -> 8): Release difference (2 units) back to available stock
                                $diff = $oldQty - $newQty;
                                StockService::releaseStock(
                                    $tenantId,
                                    (int) $doItem->product_id,
                                    (int) $doItem->warehouse_id,
                                    $diff,
                                    'DispatchOrder',
                                    (int) $dispatch->id,
                                    (int) $doItem->id
                                );

                                if ($mrItem && (float) $mrItem->quantity_reserved >= $diff) {
                                    $mrItem->decrement('quantity_reserved', $diff);
                                }
                            } else {
                                // Qty increased (10 -> 12): Reserve additional difference (2 units)
                                $diff = $newQty - $oldQty;
                                $unreservedAvail = StockService::getAvailableStock((int) $doItem->product_id, (int) $doItem->warehouse_id);

                                if ($diff > $unreservedAvail) {
                                    throw new Exception("Cannot increase quantity by {$diff} units for product '{$doItem->product?->name}'. Insufficient available stock ({$unreservedAvail}).");
                                }

                                StockService::reserveStock(
                                    $tenantId,
                                    (int) $doItem->product_id,
                                    (int) $doItem->warehouse_id,
                                    $diff,
                                    'DispatchOrder',
                                    (int) $dispatch->id,
                                    (int) $doItem->id
                                );

                                if ($mrItem) {
                                    $mrItem->increment('quantity_reserved', $diff);
                                }
                            }

                            $doItem->update([
                                'quantity_ordered' => $newQty,
                                'quantity_dispatched' => $newQty,
                            ]);
                        }
                    }
                }
            }

            return $dispatch->fresh(['items.product', 'items.warehouse']);
        });
    }

    /**
     * Stage 1: Confirm Dispatch Order (Enables Sales Team Invoice Creation).
     * Status transitions: Pending -> Confirmed
     *
     * @throws Exception
     */
    public function confirmDispatchOrder(DispatchOrder $dispatch): DispatchOrder
    {
        if ($dispatch->status !== 'Pending') {
            throw new Exception('Only Pending Dispatch Orders can be confirmed.');
        }

        return DB::transaction(function () use ($dispatch) {
            foreach ($dispatch->items as $item) {
                $item->update(['status' => 'Confirmed']);
            }

            $dispatch->update(['status' => 'Confirmed']);

            if ($dispatch->materialRequirement) {
                $dispatch->materialRequirement->update(['status' => 'Processing']);
            }

            return $dispatch;
        });
    }

    /**
     * Stage 2: Ship / Outward Dispatch Order & Deduct Physical Stock from Warehouse.
     * Status transitions: Confirmed -> Shipped (or Dispatched)
     *
     * @throws Exception
     */
    public function shipDispatchOrder(DispatchOrder $dispatch): DispatchOrder
    {
        if (!in_array($dispatch->status, ['Confirmed', 'Pending'])) {
            throw new Exception('Only Confirmed or Pending Dispatch Orders can be shipped.');
        }

        // Auto confirm & reserve if called on a pending dispatch
        if ($dispatch->status === 'Pending') {
            $dispatch = $this->confirmDispatchOrder($dispatch);
        }

        return DB::transaction(function () use ($dispatch) {
            $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

            foreach ($dispatch->items as $item) {
                $mrItem = $item->material_requirement_item_id ? MaterialRequirementItem::find($item->material_requirement_item_id) : null;
                $qtyToDispatch = (float) ($item->quantity_dispatched ?? $item->quantity_ordered);

                // Release reserved stock before physical outflow deduction
                $whStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->first();

                if ($whStock && (float) $whStock->reserved_qty > 0) {
                    $consumeReserved = min($qtyToDispatch, (float) $whStock->reserved_qty);
                    $newReserved = max(0.0, (float) $whStock->reserved_qty - $consumeReserved);
                    $whStock->update([
                        'reserved_qty' => $newReserved,
                        'available_qty' => max(0.0, (float) $whStock->quantity - $newReserved),
                    ]);
                }

                // Fulfill active StockReservation records ONLY for this Dispatch Order
                $reservations = \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('reference_type', 'DispatchOrder')
                    ->where('reference_id', $dispatch->id)
                    ->where('status', 'Active')
                    ->get();

                foreach ($reservations as $res) {
                    $res->update([
                        'reserved_qty' => 0,
                        'status' => 'Completed',
                    ]);
                }

                $snRaw = $item->serial_numbers ?? '';
                $serialNumbers = is_array($snRaw) ? $snRaw : array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$snRaw))));

                // Physical Stock Outflow Deduction
                StockService::recordOutflow(
                    $tenantId,
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    $qtyToDispatch,
                    'DispatchOrder',
                    (int) $dispatch->id,
                    $serialNumbers
                );

                $item->update(['status' => 'Shipped']);

                // Sync sales_order_items line
                $soItem = $item->salesOrderItem;
                if (!$soItem && $dispatch->sales_order_id) {
                    $soItem = \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $dispatch->sales_order_id)
                        ->where('product_id', $item->product_id)
                        ->first();
                }
                if ($soItem) {
                    $soItem->decrement('allocated_qty', min((float)$soItem->allocated_qty, $qtyToDispatch));
                    $soItem->increment('dispatched_qty', $qtyToDispatch);
                    $soItem->refreshStatus();
                }

                if ($mrItem) {
                    $mrOrdered = (float)($mrItem->quantity_ordered > 0 ? $mrItem->quantity_ordered : $mrItem->quantity);
                    $mrDispatched = (float)$mrItem->dispatched_qty;
                    if ($mrDispatched >= $mrOrdered) {
                        $mrItem->update(['status' => 'Dispatched']);
                    } else {
                        $mrItem->update(['status' => 'Partially Dispatched']);
                    }
                }
            }

            $dispatch->update(['status' => 'Shipped']);

            // Synchronize fulfillment statuses across Material Requirement, MR Items, and Sales Order
            $this->syncFulfillmentStatuses((int)$dispatch->sales_order_id, $dispatch->material_requirement_id ? (int)$dispatch->material_requirement_id : null);

            try {
                app(\App\Domains\Sales\Services\SalesAccountingService::class)->postDispatchOrderCogsJournal($dispatch);
            } catch (\Throwable $e) {
                // Keep dispatch flow resilient
            }

            return $dispatch;
        });
    }

    /**
     * Cancel an active (Pending/Confirmed) Dispatch Order.
     * Releases stock reservations, reverts SO allocated_qty, and recalculates SO status.
     *
     * @throws Exception
     */
    public function cancelDispatchOrder(DispatchOrder $dispatch, ?string $reason = null): DispatchOrder
    {
        if (in_array($dispatch->status, ['Shipped', 'Dispatched', 'Delivered'])) {
            throw new Exception("Cannot cancel Dispatch Order {$dispatch->dispatch_number} because it has already been shipped.");
        }

        if ($dispatch->status === 'Cancelled') {
            return $dispatch;
        }

        return DB::transaction(function () use ($dispatch, $reason) {
            $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

            foreach ($dispatch->items as $item) {
                $qtyReserved = (float) ($item->quantity_dispatched ?? $item->quantity_ordered);

                // Release warehouse reserved stock
                $whStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->first();

                if ($whStock && (float) $whStock->reserved_qty > 0) {
                    $revertReserved = min($qtyReserved, (float) $whStock->reserved_qty);
                    $newReserved = max(0.0, (float) $whStock->reserved_qty - $revertReserved);
                    $whStock->update([
                        'reserved_qty' => $newReserved,
                        'available_qty' => max(0.0, (float) $whStock->quantity - $newReserved),
                    ]);
                }

                // Void stock reservations tied to this DO
                \App\Domains\Inventory\Models\StockReservation::where('tenant_id', $tenantId)
                    ->where('reference_type', 'DispatchOrder')
                    ->where('reference_id', $dispatch->id)
                    ->where('status', 'Active')
                    ->update([
                        'reserved_qty' => 0,
                        'status' => 'Cancelled',
                    ]);

                // Revert allocated_qty on SalesOrderItem
                $soItem = $item->salesOrderItem;
                if (!$soItem && $dispatch->sales_order_id) {
                    $soItem = \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $dispatch->sales_order_id)
                        ->where('product_id', $item->product_id)
                        ->first();
                }

                if ($soItem) {
                    $soItem->decrement('allocated_qty', min((float)$soItem->allocated_qty, $qtyReserved));
                    $soItem->refreshStatus();
                }

                $item->update(['status' => 'Cancelled']);
            }

            $dispatch->update([
                'status' => 'Cancelled',
                'cancellation_reason' => $reason,
            ]);

            // Re-evaluate Material Requirement status
            if ($dispatch->materialRequirement) {
                $mr = $dispatch->materialRequirement;
                $allCancelled = true;
                foreach ($mr->items as $mi) {
                    if ((float)$mi->dispatched_qty > 0) {
                        $allCancelled = false;
                        break;
                    }
                }
                $mr->update(['status' => $allCancelled ? 'Draft' : 'Processing']);
            }

            // Re-evaluate Sales Order status
            if ($dispatch->salesOrder) {
                $dispatch->salesOrder->refreshFulfillmentStatus();
            }

            $this->syncFulfillmentStatuses((int)$dispatch->sales_order_id, $dispatch->material_requirement_id ? (int)$dispatch->material_requirement_id : null);

            return $dispatch;
        });
    }

    /**
     * Synchronize fulfillment statuses across Material Requirement, MR Items, and Sales Order
     * based on actual dispatched and delivered quantities from non-cancelled Dispatch Orders.
     */
    public function syncFulfillmentStatuses(int $salesOrderId, ?int $materialRequirementId = null): void
    {
        $mrQuery = MaterialRequirement::with(['items.product'])->withoutGlobalScopes();
        if ($materialRequirementId) {
            $mr = $mrQuery->find($materialRequirementId);
        } else {
            $mr = $mrQuery->where('sales_order_id', $salesOrderId)->first();
        }

        if ($mr) {
            $physicalItems = $mr->items->filter(fn($i) => !($i->product && $i->product->isService()));
            $totalOrdered = (float) $physicalItems->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));
            $totalDispatched = (float) $physicalItems->sum(fn($i) => (float)$i->dispatched_qty);
            $totalDelivered = (float) $physicalItems->sum(fn($i) => (float)$i->delivered_qty);
            $totalReleased = (float) $physicalItems->sum(fn($i) => (float)($i->quantity_released ?? 0));

            // Sync item-level status
            foreach ($physicalItems as $item) {
                $itemOrdered = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                $itemDelivered = (float)$item->delivered_qty;
                $itemDispatched = (float)$item->dispatched_qty;
                $itemReleased = (float)($item->quantity_released ?? 0);

                if ($itemOrdered > 0 && $itemDelivered >= $itemOrdered) {
                    $itemStatus = 'Delivered';
                } elseif ($itemDelivered > 0) {
                    $itemStatus = 'Partially Delivered';
                } elseif ($itemOrdered > 0 && $itemDispatched >= $itemOrdered) {
                    $itemStatus = 'Dispatched';
                } elseif ($itemDispatched > 0) {
                    $itemStatus = 'Partially Dispatched';
                } elseif ($itemOrdered > 0 && $itemReleased >= $itemOrdered) {
                    $itemStatus = 'Released';
                } elseif ($itemReleased > 0) {
                    $itemStatus = 'Partially Ready';
                } else {
                    $itemStatus = $item->status ?: 'Pending';
                }

                if ($item->status !== $itemStatus) {
                    $item->update(['status' => $itemStatus]);
                }
            }

            // Sync MR document status
            if ($totalOrdered > 0 && $totalDelivered >= $totalOrdered) {
                $mrStatus = 'Delivered';
            } elseif ($totalDelivered > 0) {
                $mrStatus = 'Partially Delivered';
            } elseif ($totalOrdered > 0 && $totalDispatched >= $totalOrdered) {
                $mrStatus = 'Dispatched';
            } elseif ($totalDispatched > 0) {
                $mrStatus = 'Partially Dispatched';
            } elseif ($totalOrdered > 0 && $totalReleased >= $totalOrdered) {
                $mrStatus = 'Released';
            } elseif ($totalReleased > 0) {
                $mrStatus = 'Partially Ready';
            } else {
                $mrStatus = $mr->status ?: 'Pending';
            }

            if ($mr->status !== $mrStatus) {
                $mr->update(['status' => $mrStatus]);
            }
        }

        // Sync Sales Order status
        if ($salesOrderId) {
            $so = \App\Domains\Sales\Models\SalesOrder::withoutGlobalScopes()->find($salesOrderId);
            if ($so) {
                if (isset($totalOrdered) && $totalOrdered > 0 && isset($totalDelivered) && $totalDelivered >= $totalOrdered) {
                    $so->update(['status' => 'Delivered']);
                } elseif (isset($totalDelivered) && $totalDelivered > 0) {
                    $so->update(['status' => 'Partially Delivered']);
                } elseif (isset($totalOrdered) && $totalOrdered > 0 && isset($totalDispatched) && $totalDispatched >= $totalOrdered) {
                    $so->update(['status' => 'Shipped']);
                } elseif (isset($totalDispatched) && $totalDispatched > 0) {
                    $so->update(['status' => 'Partially Shipped']);
                } else {
                    $so->refreshFulfillmentStatus();
                }
            }
        }
    }
}
