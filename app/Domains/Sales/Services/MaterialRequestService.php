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
                    'quantity_planned'  => $item->quantity_planned,
                    'quantity_reserved' => 0.0,
                    'quantity_issued'   => 0.0,
                    'uom_id'            => $item->uom_id,
                ]
            );
            $poReservation->increment('quantity_reserved', $qtyToReserve);

            $item->warehouse_id = $warehouseId;
            $item->quantity_reserved += $qtyToReserve;
            $item->save();

            $this->updateSlipStatus($slip);

            return $qtyToReserve;
        });
    }

    public function issue(int $tenantId, int $itemId, float $quantity, ?int $warehouseId, ?string $remarks): float
    {
        return DB::transaction(function () use ($tenantId, $itemId, $quantity, $warehouseId, $remarks) {
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

            $qtyFromReserved = min($quantity, (float) $item->quantity_reserved);
            $item->quantity_reserved -= $qtyFromReserved;
            $item->quantity_issued += $quantity;
            $item->save();

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
