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
            $op = $challan->operation;
            $isWipJobWork = $op?->isWipJobWork() ?? false;
            $challan->load('items');
            $totalDispatched = (float) $challan->items->sum('quantity');

            $challan->dispatched_wip_qty = $totalDispatched;
            $challan->save();

            foreach ($challan->items as $item) {
                $whId = $item->warehouse_id ?: $challan->warehouse_id;

                if ($isWipJobWork) {
                    $wipId = $item->production_wip_id;
                    $batchId = $item->production_batch_id;

                    if (!$wipId && $challan->production_order_id) {
                        $wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $tenantId)
                            ->where('production_order_id', $challan->production_order_id)
                            ->first();

                        if (!$wip) {
                            $batch = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $tenantId)
                                ->where('production_order_id', $challan->production_order_id)
                                ->first();

                            $wip = \App\Domains\Production\Models\ProductionWip::create([
                                'tenant_id' => $tenantId,
                                'production_order_id' => $challan->production_order_id,
                                'production_batch_id' => $batch?->id,
                                'product_id' => $item->product_id ?: $challan->productionOrder?->product_id,
                                'current_routing_operation_id' => $op?->routing_operation_id,
                                'current_work_center_id' => $op?->work_center_id,
                                'quantity' => $challan->productionOrder?->quantity_ordered ?? 0,
                                'available_quantity' => 0,
                                'completed_quantity' => 0,
                                'rejected_quantity' => 0,
                                'scrap_quantity' => 0,
                                'rework_quantity' => 0,
                                'status' => 'active',
                            ]);
                        }

                        $wipId = $wip->id;
                        if (!$batchId) {
                            $batchId = $wip->production_batch_id;
                        }

                        $item->production_wip_id = $wipId;
                        if ($batchId) {
                            $item->production_batch_id = $batchId;
                        }
                        $item->save();
                    }

                    \App\Domains\Production\Models\ProductionWipTransaction::create([
                        'tenant_id' => $tenantId,
                        'wip_id' => $wipId,
                        'production_order_id' => $challan->production_order_id,
                        'production_batch_id' => $batchId,
                        'from_operation_id' => $op?->routing_operation_id,
                        'to_operation_id' => null,
                        'from_work_center_id' => $op?->work_center_id,
                        'to_work_center_id' => null,
                        'transaction_type' => 'wip_vendor_dispatched',
                        'quantity' => (float) $item->quantity,
                        'good_quantity' => (float) $item->quantity,
                        'cost_before' => 0.00,
                        'cost_added' => 0.00,
                        'cost_after' => 0.00,
                        'remarks' => "WIP dispatched to vendor [{$challan->vendor?->name}] via Delivery Challan #{$challan->challan_number}",
                        'transaction_at' => now(),
                        'created_by' => auth()->id() ?: 1,
                    ]);
                } else if ($whId) {
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
                    'remarks' => "Dispatched {$totalDispatched} units to vendor via Delivery Challan #{$challan->challan_number} (Vehicle: {$challan->vehicle_number}).",
                ]);
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
        ?string $remarks = null,
        float $scrappedQty = 0.0
    ): void {
        DB::transaction(function () use ($challan, $tenantId, $receivedQty, $acceptedQty, $rejectedQty, $scrappedQty, $warehouseId, $remarks) {
            $op = $challan->operation;
            $order = $challan->productionOrder;
            $isWipJobWork = $op?->isWipJobWork() ?? false;

            // 1. Finished Goods stock inflow only for raw material subcontracting (NOT for intermediate WIP Job Work)
            $targetProductId = $order?->product_id;
            if (!$isWipJobWork && $targetProductId && $acceptedQty > 0) {
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

            // 2. Backflush company material components only for raw material subcontracting
            if ($op && !$isWipJobWork) {
                $this->backflushCompanyMaterial($tenantId, $op, $acceptedQty);
            }

            // 3. Update WIP transactions & operation progress
            if ($op) {
                if ($isWipJobWork) {
                    $firstItem = $challan->items->first();
                    $wipId = $firstItem?->production_wip_id;
                    $batchId = $firstItem?->production_batch_id;

                    if (!$wipId && $challan->production_order_id) {
                        $wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $tenantId)
                            ->where('production_order_id', $challan->production_order_id)
                            ->first();

                        if (!$wip) {
                            $batch = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $tenantId)
                                ->where('production_order_id', $challan->production_order_id)
                                ->first();

                            $wip = \App\Domains\Production\Models\ProductionWip::create([
                                'tenant_id' => $tenantId,
                                'production_order_id' => $challan->production_order_id,
                                'production_batch_id' => $batch?->id,
                                'product_id' => $firstItem?->product_id ?: $order?->product_id,
                                'current_routing_operation_id' => $op->routing_operation_id,
                                'current_work_center_id' => $op->work_center_id,
                                'quantity' => $order?->quantity_ordered ?? 0,
                                'available_quantity' => 0,
                                'completed_quantity' => 0,
                                'rejected_quantity' => 0,
                                'scrap_quantity' => 0,
                                'rework_quantity' => 0,
                                'status' => 'active',
                            ]);
                        }

                        $wipId = $wip->id;
                        if (!$batchId) {
                            $batchId = $wip->production_batch_id;
                        }

                        if ($firstItem) {
                            $firstItem->production_wip_id = $wipId;
                            if ($batchId) {
                                $firstItem->production_batch_id = $batchId;
                            }
                            $firstItem->save();
                        }
                    }

                    \App\Domains\Production\Models\ProductionWipTransaction::create([
                        'tenant_id' => $tenantId,
                        'wip_id' => $wipId,
                        'production_order_id' => $challan->production_order_id,
                        'production_batch_id' => $batchId,
                        'from_operation_id' => $op->routing_operation_id,
                        'to_operation_id' => null,
                        'from_work_center_id' => null,
                        'to_work_center_id' => $op->work_center_id,
                        'transaction_type' => 'subcontract_received',
                        'quantity' => $acceptedQty,
                        'good_quantity' => $acceptedQty,
                        'rejected_quantity' => $rejectedQty,
                        'scrap_quantity' => $scrappedQty,
                        'remarks' => "Received {$acceptedQty} processed WIP units from vendor [{$challan->vendor?->name}] via Challan #{$challan->challan_number} (from Op {$op->sequence}). " . ($remarks ?? ''),
                        'transaction_at' => now(),
                        'created_by' => auth()->id() ?: 1,
                    ]);
                }

                $isQcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));

                if ($isQcRequired) {
                    $op->status = 'subcontract_qc_pending';
                    $op->save();

                    \App\Domains\Production\Models\ProductionOrderProgressLog::create([
                        'tenant_id'           => $tenantId,
                        'production_order_id' => $op->production_order_id,
                        'operation_id'        => $op->id,
                        'quantity_completed'  => 0,
                        'quantity_produced'   => $receivedQty,
                        'recorded_at'          => now(),
                        'status'              => 'subcontract_qc_pending',
                        'log_type'            => 'subcontract_receipt',
                        'logged_by'           => auth()->id() ?: 1,
                        'remarks'             => "Received {$receivedQty} units from vendor [{$challan->vendor?->name}] via Challan #{$challan->challan_number} - pending QC inspection. " . ($remarks ?? ''),
                    ]);
                } else {
                    $op->quantity_produced = (float) $op->quantity_produced + $acceptedQty;
                    if ($rejectedQty > 0) {
                        $op->quantity_rejected = (float) $op->quantity_rejected + $rejectedQty;
                    }
                    if ($scrappedQty > 0) {
                        $op->quantity_scrapped = (float) $op->quantity_scrapped + $scrappedQty;
                    }

                    $targetQty = (float) ($op->target_produced_qty ?: ($order?->quantity_ordered ?: 0.0));
                    if ($targetQty > 0 && $op->quantity_produced < ($targetQty - 0.0001)) {
                        $op->status = 'running';
                    } else {
                        $op->status = 'completed';
                        $op->actual_end_time = now();
                    }
                    $op->save();

                    // Log progress
                    \App\Domains\Production\Models\ProductionOrderProgressLog::create([
                        'tenant_id'           => $tenantId,
                        'production_order_id' => $op->production_order_id,
                        'operation_id'        => $op->id,
                        'quantity_completed'  => $acceptedQty,
                        'quantity_produced'   => $acceptedQty,
                        'recorded_at'          => now(),
                        'status'              => $op->status,
                        'log_type'            => 'subcontract_receipt',
                        'logged_by'           => auth()->id() ?: 1,
                        'remarks'             => "Received {$acceptedQty} processed items from vendor [{$challan->vendor?->name}] via Challan #{$challan->challan_number}. " . ($remarks ?? ''),
                    ]);

                    // Unlock next operation on Shopfloor via WIP transfer evaluator
                    app(\App\Domains\Production\Services\ProductionWipService::class)->evaluateAndExecuteWipTransfers($op->id);
                }
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

    /**
     * Create a Subcontract Rework Delivery Challan (Return Gate Pass) to dispatch rejected WIP back to vendor for rework.
     */
    public function createReworkChallan(int $tenantId, ProductionOrderOperation $op, float $qty, array $data, int $userId): \App\Domains\Production\Models\DeliveryChallan
    {
        return DB::transaction(function () use ($tenantId, $op, $qty, $data, $userId) {
            $order = $op->order;
            $vendorId = $data['vendor_id'] ?? $op->vendor_id;

            if (!$vendorId && $order?->operations) {
                $extOp = $order->operations->firstWhere('is_external', true);
                $vendorId = $extOp?->vendor_id;
            }

            $challanNumber = 'DC-RWK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $challan = \App\Domains\Production\Models\DeliveryChallan::create([
                'tenant_id' => $tenantId,
                'challan_number' => $challanNumber,
                'type' => 'vendor_rework',
                'production_order_id' => $op->production_order_id,
                'production_order_operation_id' => $op->id,
                'vendor_id' => $vendorId,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'challan_date' => now(),
                'expected_return_date' => now()->addDays(7),
                'status' => 'dispatched',
                'dispatched_wip_qty' => $qty,
                'notes' => $data['instructions'] ?? "Subcontract Rework Dispatch for {$qty} rejected units on Op #{$op->operation_number}. Reason: " . ($data['reason'] ?? 'Defect Rectification'),
                'created_by' => $userId,
            ]);

            // Locate WIP record
            $wip = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $tenantId)
                ->where('production_order_id', $op->production_order_id)
                ->first();

            $batch = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $tenantId)
                ->where('production_order_id', $op->production_order_id)
                ->first();

            \App\Domains\Production\Models\DeliveryChallanItem::create([
                'tenant_id' => $tenantId,
                'delivery_challan_id' => $challan->id,
                'product_id' => $op->source_product_id ?: $order?->product_id,
                'production_batch_id' => $data['batch_id'] ?? $batch?->id,
                'production_wip_id' => $wip?->id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'quantity' => $qty,
                'unit_of_measure' => $order?->product?->unit_of_measure ?? 'PCS',
                'batch_number' => $batch?->batch_number,
                'notes' => "Subcontract Rework WIP Unit Sent for Vendor Rectification",
            ]);

            if ($wip) {
                \App\Domains\Production\Models\ProductionWipTransaction::create([
                    'tenant_id' => $tenantId,
                    'wip_id' => $wip->id,
                    'production_order_id' => $op->production_order_id,
                    'production_batch_id' => $batch?->id,
                    'from_operation_id' => $op->routing_operation_id,
                    'to_operation_id' => $op->routing_operation_id,
                    'from_work_center_id' => $op->work_center_id,
                    'to_work_center_id' => null,
                    'transaction_type' => 'subcontract_rework_dispatched',
                    'quantity' => $qty,
                    'good_quantity' => 0,
                    'rework_quantity' => $qty,
                    'remarks' => "Dispatched {$qty} rejected units to vendor for rework via Challan #{$challanNumber}",
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            return $challan;
        });
    }
}
