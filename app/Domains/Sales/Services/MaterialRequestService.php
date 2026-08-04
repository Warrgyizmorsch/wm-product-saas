<?php

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialRequestService
{
    public function reserve(int $tenantId, int $itemId, float $quantity, ?int $requestedWh): float
    {
        return DB::transaction(function () use ($tenantId, $itemId, $quantity, $requestedWh) {
            $item = ProductionRequisitionSlipItem::lockForUpdate()->findOrFail($itemId);
            $slip = $item->slip;

            $sameProductItems = ProductionRequisitionSlipItem::where('production_requisition_slip_id', $slip->id)
                ->where('product_id', $item->product_id)
                ->get();

            $warehouseId = $requestedWh
                ?? $item->warehouse_id
                ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id;

            if (!$warehouseId) {
                throw new InvalidArgumentException('No warehouse resolved for stock reservation.');
            }

            $availableQty = StockService::getAvailableStock($item->product_id, $warehouseId);
            $qtyToReserve = min($quantity, $availableQty);

            if ($qtyToReserve <= 0) {
                throw new InvalidArgumentException('No available stock in the selected warehouse to reserve.');
            }

            StockService::reserveStock(
                $tenantId,
                $item->product_id,
                $warehouseId,
                $qtyToReserve,
                'Production Order',
                $slip->production_order_id,
                $item->id
            );

            $poReservation = ProductionOrderReservation::firstOrCreate(
                [
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $slip->production_order_id,
                    'product_id'          => $item->product_id,
                    'warehouse_id'        => $warehouseId,
                ],
                [
                    'bom_item_id'       => null,
                    'quantity_planned'  => $sameProductItems->sum('quantity_planned'),
                    'quantity_reserved' => 0.0,
                    'quantity_issued'   => 0.0,
                    'uom_id'            => $item->uom_id,
                ]
            );
            $poReservation->increment('quantity_reserved', $qtyToReserve);

            // Sequentially allocate reserve across matching items of this product
            $remRes = $qtyToReserve;
            foreach ($sameProductItems as $pItem) {
                if ($remRes <= 0) break;

                $pRemToRes = max(0.0, (float)$pItem->quantity_planned - ((float)$pItem->quantity_issued + (float)$pItem->quantity_reserved));
                if ($pRemToRes > 0) {
                    $alloc = min($remRes, $pRemToRes);
                    $pItem->warehouse_id = $warehouseId;
                    $pItem->quantity_reserved += $alloc;
                    $pItem->save();

                    $remRes -= $alloc;
                }
            }

            $this->updateSlipStatus($slip);

            return $qtyToReserve;
        });
    }

    public function issue(int $tenantId, int $itemId, float $quantity, ?int $warehouseId, ?string $remarks): float
    {
        return DB::transaction(function () use ($tenantId, $itemId, $quantity, $warehouseId, $remarks) {
            $item = ProductionRequisitionSlipItem::lockForUpdate()->findOrFail($itemId);
            $slip = $item->slip;

            $sameProductItems = ProductionRequisitionSlipItem::where('production_requisition_slip_id', $slip->id)
                ->where('product_id', $item->product_id)
                ->get();

            $resolvedWarehouseId = $warehouseId ?: ($item->warehouse_id ?? Warehouse::where('tenant_id', $tenantId)->orderByDesc('is_default')->first()?->id);
            if (!$resolvedWarehouseId) {
                throw new InvalidArgumentException('No warehouse resolved for material issue.');
            }

            $totalPlanned = (float) $sameProductItems->sum('quantity_planned');
            $totalIssued = (float) $sameProductItems->sum('quantity_issued');
            $totalReserved = (float) $sameProductItems->sum('quantity_reserved');
            $totalRemainingToIssue = max(0.0, $totalPlanned - $totalIssued);

            if ($quantity > $totalRemainingToIssue) {
                throw new InvalidArgumentException("Cannot issue more than the remaining planned quantity ({$totalRemainingToIssue}).");
            }

            $availableQty = StockService::getAvailableStock($item->product_id, $resolvedWarehouseId);
            $maxAllowed = $totalReserved + $availableQty;
            if ($quantity > $maxAllowed) {
                throw new InvalidArgumentException("Cannot issue {$quantity} units. Only {$maxAllowed} units are available.");
            }

            StockService::recordOutflow(
                $tenantId,
                $item->product_id,
                $resolvedWarehouseId,
                $quantity,
                'Production Order',
                $slip->production_order_id
            );

            // Sequentially allocate issue quantity across matching items of this product
            $remIssue = $quantity;
            foreach ($sameProductItems as $pItem) {
                if ($remIssue <= 0) break;

                $pRemaining = max(0.0, (float)$pItem->quantity_planned - (float)$pItem->quantity_issued);
                if ($pRemaining > 0) {
                    $alloc = min($remIssue, $pRemaining);

                    $qtyFromRes = min($alloc, (float)$pItem->quantity_reserved);
                    $pItem->quantity_reserved -= $qtyFromRes;
                    $pItem->quantity_issued += $alloc;
                    $pItem->save();

                    $remIssue -= $alloc;
                }
            }

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

            DB::table('production_order_issues')->insert([
                'tenant_id'           => $tenantId,
                'production_order_id' => $slip->production_order_id,
                'reservation_id'      => $poReservation?->id,
                'product_id'          => $item->product_id,
                'warehouse_id'        => $resolvedWarehouseId,
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

            $this->updateSlipStatus($slip);

            return $quantity;
        });
    }

    public function createPurchaseRequisition(int $tenantId, int $itemId, ?int $warehouseId, ?string $notes): PurchaseRequisition
    {
        return DB::transaction(function () use ($tenantId, $itemId, $warehouseId, $notes) {
            $item = ProductionRequisitionSlipItem::findOrFail($itemId);
            $slip = $item->slip;

            $warehouseStock = (float) StockService::getAvailableStock($item->product_id, $warehouseId ?: $item->warehouse_id);
            $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
            $shortageQty = max(0.0, $remainingToIssue - ((float) $item->quantity_reserved + $warehouseStock));

            if ($shortageQty <= 0) {
                throw new InvalidArgumentException('No shortage for this item in the selected warehouse.');
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
                'tenant_id'          => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date'   => now()->toDateString(),
                'status'             => 'Draft',
                'source_type'        => 'material_request',
                'source_id'          => $slip->id,
                'notes'              => $notes ?: 'Generated from Material Request #' . $slip->requisition_number,
                'requested_by'       => auth()->id() ?: 1,
            ]);

            PurchaseRequisitionItem::create([
                'purchase_requisition_id' => $pr->id,
                'tenant_id'               => $tenantId,
                'product_id'              => $item->product_id,
                'quantity'                => $shortageQty,
                'uom_id'                  => $item->uom_id,
                'warehouse_id'            => $warehouseId ?: $item->warehouse_id,
                'estimated_unit_cost'     => $item->product->unit_cost ?? 0.0,
                'notes'                   => "PR generated for shortage of {$item->product->name}",
            ]);

            return $pr;
        });
    }

    /**
     * Create a SINGLE Purchase Requisition with ALL selected items as line items.
     * Used by bulk action — avoids creating one PR per item.
     */
    public function createBulkPurchaseRequisition(int $tenantId, array $itemIds, ?int $warehouseId, ?string $notes): PurchaseRequisition
    {
        return DB::transaction(function () use ($tenantId, $itemIds, $warehouseId, $notes) {

            // Collect all items with shortages
            $lineItems = [];
            $slip = null;

            foreach ($itemIds as $itemId) {
                $item = ProductionRequisitionSlipItem::with('product')->find((int) $itemId);
                if (!$item) continue;

                $slip = $slip ?? $item->slip;

                $resolvedWh   = $warehouseId ?: $item->warehouse_id;
                $warehouseStock   = (float) StockService::getAvailableStock($item->product_id, $resolvedWh);
                $remainingToIssue = max(0.0, (float) $item->quantity_planned - (float) $item->quantity_issued);
                $shortageQty      = max(0.0, $remainingToIssue - ((float) $item->quantity_reserved + $warehouseStock));

                if ($shortageQty <= 0) {
                    continue; // Skip items with no shortage
                }

                $lineItems[] = [
                    'item'         => $item,
                    'shortageQty'  => $shortageQty,
                    'resolvedWh'   => $resolvedWh,
                ];
            }

            if (empty($lineItems)) {
                throw new InvalidArgumentException('No items with shortage found for the selected items and warehouse.');
            }

            // Generate single PR number
            $year   = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = $lastPr
                ? ((int) str_replace($prefix, '', $lastPr->requisition_number)) + 1
                : 1;

            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            // Create ONE PR
            $pr = PurchaseRequisition::create([
                'tenant_id'          => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date'   => now()->toDateString(),
                'status'             => 'Draft',
                'source_type'        => 'material_request',
                'source_id'          => $slip->id,
                'notes'              => $notes ?: 'Bulk PR from Material Request #' . $slip->requisition_number,
                'requested_by'       => auth()->id() ?: 1,
            ]);

            // Add ALL shortage items as line items to the SAME PR
            foreach ($lineItems as $line) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'tenant_id'               => $tenantId,
                    'product_id'              => $line['item']->product_id,
                    'quantity'                => $line['shortageQty'],
                    'uom_id'                  => $line['item']->uom_id,
                    'warehouse_id'            => $line['resolvedWh'],
                    'estimated_unit_cost'     => $line['item']->product?->unit_cost ?? 0.0,
                    'notes'                   => "Shortage for {$line['item']->product?->name}",
                ]);
            }

            return $pr;
        });
    }


    public function updateSlipStatus(ProductionRequisitionSlip $slip): void
    {
        $items = $slip->items;
        if ($items->isEmpty()) {
            return;
        }

        $allIssued = true;
        $anyIssued = false;
        $allReservedOrIssued = true;
        $anyReserved = false;

        foreach ($items as $item) {
            $planned  = (float) $item->quantity_planned;
            $issued   = (float) $item->quantity_issued;
            $reserved = (float) $item->quantity_reserved;

            if ($issued < $planned) {
                $allIssued = false;
            }
            if ($issued > 0) {
                $anyIssued = true;
            }
            if (($issued + $reserved) < $planned) {
                $allReservedOrIssued = false;
            }
            if ($reserved > 0) {
                $anyReserved = true;
            }
        }

        if ($allIssued) {
            $slip->status = 'Fully Issued';
        } elseif ($anyIssued || $allReservedOrIssued) {
            $slip->status = 'Partially Issued';
        } elseif ($anyReserved) {
            $slip->status = 'Reserved';
        } else {
            $slip->status = 'Pending';
        }

        $slip->save();
    }
}
