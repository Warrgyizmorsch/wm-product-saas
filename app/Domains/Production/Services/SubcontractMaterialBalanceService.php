<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrderOperation;
use Illuminate\Support\Facades\DB;

class SubcontractMaterialBalanceService
{
    /**
     * Get or calculate current vendor material balance for an outsourced operation.
     * Sent = Consumed + Returned + Scrapped + Remaining
     */
    public function getMaterialBalance(int $tenantId, int $orderId, int $operationId): array
    {
        $op = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($operationId) {
                $q->where('id', $operationId)->orWhere('routing_operation_id', $operationId);
            })
            ->first();

        if (!$op) {
            return [
                'sent' => 0.0,
                'consumed' => 0.0,
                'returned' => 0.0,
                'scrapped' => 0.0,
                'remaining' => 0.0,
            ];
        }

        // Outflows to Subcontractor Warehouse linked to this order/operation
        $sentQty = (float) StockTransaction::where('tenant_id', $tenantId)
            ->where('reference_type', 'StockTransfer')
            ->where('reference_id', $op->production_order_id)
            ->where('type', 'OUT')
            ->sum('quantity');

        // Material consumed via backflushing
        $consumedQty = (float) StockTransaction::where('tenant_id', $tenantId)
            ->where('reference_type', 'SubcontractBackflush')
            ->where('reference_id', $op->id)
            ->where('type', 'OUT')
            ->sum('quantity');

        // Material returned to Main Warehouse
        $returnedQty = (float) StockTransaction::where('tenant_id', $tenantId)
            ->where('reference_type', 'SubcontractReturn')
            ->where('reference_id', $op->id)
            ->where('type', 'IN')
            ->sum('quantity');

        // Material scrapped at vendor
        $scrappedQty = (float) StockTransaction::where('tenant_id', $tenantId)
            ->where('reference_type', 'SubcontractScrap')
            ->where('reference_id', $op->id)
            ->sum('quantity');

        $remainingQty = max(0.0, $sentQty - ($consumedQty + $returnedQty + $scrappedQty));

        return [
            'sent' => $sentQty,
            'consumed' => $consumedQty,
            'returned' => $returnedQty,
            'scrapped' => $scrappedQty,
            'remaining' => $remainingQty,
        ];
    }

    /**
     * Backflush company material from Subcontractor Warehouse upon accepted GRN.
     */
    public function backflushCompanyMaterial(
        int $tenantId,
        ProductionOrderOperation $op,
        float $acceptedQty,
        ?int $subcontractorWarehouseId = null
    ): float {
        return DB::transaction(function () use ($tenantId, $op, $acceptedQty, $subcontractorWarehouseId) {
            if ($op->material_supply_type !== 'company_supplied') {
                return 0.0;
            }

            $order = $op->order;
            if (!$order || !$order->bom_id) {
                return 0.0;
            }

            // Find subcontractor warehouse for vendor
            $whId = $subcontractorWarehouseId;
            if (!$whId && $op->vendor_id) {
                $whId = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)
                    ->where('type', 'subcontractor')
                    ->where('vendor_id', $op->vendor_id)
                    ->value('id');
            }

            if (!$whId) {
                // Fallback to any subcontractor warehouse or default warehouse
                $whId = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)
                    ->where('type', 'subcontractor')
                    ->value('id');
            }

            if (!$whId) {
                return 0.0;
            }

            $bom = \App\Domains\Production\Models\ProductionBom::with('items')->find($order->bom_id);
            if (!$bom) {
                return 0.0;
            }

            $totalBackflushed = 0.0;

            foreach ($bom->items as $item) {
                $scrapFactor = 1.0 + ((float) $item->material_scrap_percentage / 100.0);
                $reqQtyPerUnit = (float) $item->quantity * $scrapFactor;
                $componentQtyToDeduct = round($acceptedQty * $reqQtyPerUnit, 4);

                if ($componentQtyToDeduct <= 0) continue;

                // Idempotency check for this GRN/operation backflush
                $alreadyBackflushed = StockTransaction::where('tenant_id', $tenantId)
                    ->where('product_id', $item->material_id)
                    ->where('warehouse_id', $whId)
                    ->where('reference_type', 'SubcontractBackflush')
                    ->where('reference_id', $op->id)
                    ->sum('quantity');

                // Record stock outflow from vendor subcontractor warehouse
                StockService::recordOutflow(
                    tenantId: $tenantId,
                    productId: $item->material_id,
                    warehouseId: $whId,
                    quantity: $componentQtyToDeduct,
                    referenceType: 'SubcontractBackflush',
                    referenceId: $op->id
                );

                $totalBackflushed += $componentQtyToDeduct;
            }

            return $totalBackflushed;
        });
    }
}
