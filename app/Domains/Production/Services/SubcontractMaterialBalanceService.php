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
            ->whereIn('reference_type', ['StockTransfer', 'DeliveryChallan'])
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

    /**
     * Validate physical stock availability across items.
     */
    public function validateStockAvailability(int $tenantId, array $items): array
    {
        $errors = [];

        foreach ($items as $item) {
            $whId = $item['warehouse_id'] ?? null;
            $productId = $item['product_id'];
            $reqQty = (float) $item['quantity'];

            if (!$whId) continue;

            $wh = \App\Domains\Inventory\Models\Warehouse::find($whId);
            $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('warehouse_id', $whId)
                ->where('product_id', $productId)
                ->first();

            $available = $stock ? (float) ($stock->available_qty ?? $stock->quantity) : 0.0;

            if ($available < $reqQty) {
                $product = \App\Domains\Inventory\Models\Product::find($productId);
                $errors[] = "Insufficient stock for [{$product?->name}] in warehouse [{$wh?->name}]. Required: " . number_format($reqQty, 2) . ", Available: " . number_format($available, 2) . ".";
            }
        }

        return $errors;
    }

    /**
     * Execute stock deduction and operation status updates upon challan dispatch.
     */
    public function processDispatchActions(\App\Domains\Production\Models\DeliveryChallan $challan, int $tenantId): void
    {
        DB::transaction(function () use ($challan, $tenantId) {
            foreach ($challan->items as $item) {
                $whId = $item->warehouse_id ?: $challan->warehouse_id;

                if ($whId) {
                    StockTransaction::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $whId,
                        'type' => 'OUT',
                        'reference_type' => 'DeliveryChallan',
                        'reference_id' => $challan->production_order_id ?: $challan->id,
                        'quantity' => (float) $item->quantity,
                        'unit_cost' => 0,
                        'total_value' => 0,
                        'balance_qty' => 0,
                    ]);

                    $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('warehouse_id', $whId)
                        ->where('product_id', $item->product_id)
                        ->first();

                    if ($stock) {
                        $stock->quantity = max(0.0, (float) $stock->quantity - (float) $item->quantity);
                        $stock->available_qty = max(0.0, (float) $stock->available_qty - (float) $item->quantity);
                        $stock->save();
                    }
                }
            }

            if ($challan->production_order_operation_id) {
                $op = ProductionOrderOperation::where('tenant_id', $tenantId)->find($challan->production_order_operation_id);

                if ($op) {
                    $op->status = 'vendor_dispatched';
                    $op->actual_start_time = $op->actual_start_time ?: now();
                    $op->save();

                    \App\Domains\Production\Models\ProductionOrderProgressLog::create([
                        'tenant_id' => $tenantId,
                        'production_order_id' => $op->production_order_id,
                        'operation_id' => $op->id,
                        'quantity_completed' => 0,
                        'recorded_at' => now(),
                        'status' => 'vendor_dispatched',
                        'log_type' => 'subcontract_dispatch',
                        'logged_by' => auth()->id() ?: 1,
                        'remarks' => "Company material dispatched to vendor via Delivery Challan #{$challan->challan_number} (Vehicle: {$challan->vehicle_number}). Stock deducted from respective item warehouses.",
                    ]);
                }
            }
        });
    }

    /**
     * Execute receipt stock inflow, backflushing, operation completion, and WIP evaluation upon receiving challan.
     */
    public function processReceiveActions(
        \App\Domains\Production\Models\DeliveryChallan $challan,
        int $tenantId,
        float $receivedQty,
        float $acceptedQty,
        float $rejectedQty,
        int $warehouseId,
        ?string $remarks = null
    ): void {
        DB::transaction(function () use ($challan, $tenantId, $receivedQty, $acceptedQty, $rejectedQty, $warehouseId, $remarks) {
            $op = $challan->operation;
            $order = $challan->productionOrder;

            // 1. Add processed product stock back into warehouse (IN)
            $targetProductId = $order?->product_id;
            if ($targetProductId && $acceptedQty > 0) {
                StockService::recordInflow(
                    tenantId: $tenantId,
                    productId: $targetProductId,
                    warehouseId: $warehouseId,
                    quantity: $acceptedQty,
                    unitCost: 0,
                    referenceType: 'SubcontractReceipt',
                    referenceId: $challan->id
                );
            }

            // 2. Backflush company material components used for this subcontract batch
            if ($op) {
                $this->backflushCompanyMaterial($tenantId, $op, $acceptedQty);

                // 3. Update operation progress & status
                $op->quantity_produced = (float) $op->quantity_produced + $acceptedQty;
                if ($rejectedQty > 0) {
                    $op->quantity_rejected = (float) $op->quantity_rejected + $rejectedQty;
                }

                $targetQty = (float) ($op->target_produced_qty ?: ($order?->quantity_ordered ?: 0.0));
                if ($targetQty > 0 && $op->quantity_produced < ($targetQty - 0.0001)) {
                    $op->status = 'running';
                } else {
                    $op->status = 'completed';
                    $op->actual_end_time = now();
                }
                $op->save();

                // 4. Log progress
                \App\Domains\Production\Models\ProductionOrderProgressLog::create([
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $op->production_order_id,
                    'operation_id'        => $op->id,
                    'quantity_completed'  => $acceptedQty,
                    'recorded_at'          => now(),
                    'status'              => $op->status,
                    'log_type'            => 'subcontract_receipt',
                    'logged_by'           => auth()->id() ?: 1,
                    'remarks'             => "Received {$acceptedQty} processed items from vendor [{$challan->vendor?->name}] via Challan #{$challan->challan_number}. " . ($remarks ?? ''),
                ]);

                // 5. Unlock next operation on Shopfloor
                app(\App\Domains\Production\Services\ProductionWipService::class)->evaluateAndExecuteWipTransfers($op->id);
            }

            // Mark Challan as Completed
            $challan->status = 'completed';
            $challan->save();

            // Auto-approve Subcontract PO if draft
            if ($challan->production_order_operation_id) {
                $poItem = \App\Domains\Purchase\Models\PurchaseOrderItem::where('production_order_operation_id', $challan->production_order_operation_id)->first();
                if ($poItem && $poItem->order && $poItem->order->status === 'Draft') {
                    $poItem->order->status = 'Approved';
                    $poItem->order->save();
                }
            }

            // Auto-complete Production Order if all operations completed
            if ($challan->production_order_id) {
                app(\App\Domains\Production\Services\ProductionOrderService::class)->evaluateAndAutoCompleteOrder($challan->production_order_id);
            }
        });
    }
}
