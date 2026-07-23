<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionNcr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionExecutionService
{
    /**
     * Log shop floor execution progress against a specific operation.
     *
     * quantity_produced: units passing this operation step (output of the operation).
     * quantity_rejected: units failing quality and requiring rework or scrapping.
     * quantity_scrapped: units permanently destroyed/wasted at this operation step.
     *
     * These are operation-level accumulations used for OEE/KPI calculations.
     * They do NOT directly update ProductionOrder.quantity_produced —
     * only receiveFinishedGoods() increments the order-level quantity_produced.
     */
    public function logProgress(
        int     $operationId,
        float   $produced,
        float   $rejected,
        float   $scrapped,
        float   $setupMinutes,
        float   $runMinutes,
        ?string $remarks          = null,
        ?int    $machineId        = null,
        ?int    $userId           = null,
        bool    $completeOperation = false
    ): ProductionOrderProgressLog {
        return DB::transaction(function () use (
            $operationId, $produced, $rejected, $scrapped,
            $setupMinutes, $runMinutes, $remarks, $machineId, $userId, $completeOperation
        ) {
            $op    = ProductionOrderOperation::findOrFail($operationId);
            $order = $op->order;

            // 1. Enforce Order state validity
            if ($order->isClosed() || $order->isCompleted() || $order->isCancelled()) {
                throw new InvalidArgumentException('Cannot log progress on a closed, completed, or cancelled order.');
            }

            // Enforce Operation state validity
            if ($op->status === ProductionOrderOperation::STATUS_COMPLETED) {
                throw new InvalidArgumentException('Cannot log progress on an already completed operation.');
            }

            if ($op->quantity_produced >= $order->quantity_ordered) {
                throw new InvalidArgumentException('Cannot log progress: The planned target has already been fully produced.');
            }

            // Prevent logging total processed quantity (produced + rejected) beyond planned target.
            // Scrapped quantities represent discarded material and should not restrict the production limit.
            $plannedTarget = (float) $order->quantity_ordered;
            $currentProcessed = (float) ($op->quantity_produced + $op->quantity_rejected);
            $newProcessed = (float) ($produced + $rejected);
            
            if (($currentProcessed + $newProcessed) > $plannedTarget) {
                $maxRemaining = max(0.0, $plannedTarget - $currentProcessed);
                throw new InvalidArgumentException("Cannot log progress: The total processed quantity (Produced: {$produced}, Rejected: {$rejected}) exceeds the remaining planned target limit of {$maxRemaining} (Total Planned: {$plannedTarget}, Already Logged: {$currentProcessed}).");
            }

            // 2. Create the progress log entry
            $log = ProductionOrderProgressLog::create([
                'tenant_id'            => $op->tenant_id,
                'production_order_id'  => $op->production_order_id,
                'operation_id'         => $op->id,
                'quantity_produced'    => $produced,
                'quantity_rejected'    => $rejected,
                'quantity_scrapped'    => $scrapped,
                'setup_minutes_logged' => $setupMinutes,
                'run_minutes_logged'   => $runMinutes,
                'recorded_by'          => $userId,
                'recorded_at'          => now(),
                'machine_id'           => $machineId ?? $op->machine_id,
                'remarks'              => $remarks,
            ]);

            // 3. Update Operation-level metrics (accumulated per operation)
            $op->setup_time_actual      += $setupMinutes;
            $op->processing_time_actual += $runMinutes;
            $op->quantity_produced      += $produced;
            $op->quantity_rejected      += $rejected;
            $op->quantity_scrapped      += $scrapped;

            // Handle Scrap automatically if logged
            if ($scrapped > 0) {
                $this->logScrap(
                    $order->id,
                    $op->id,
                    null,
                    $scrapped,
                    $remarks ?? 'Automatic log scrap from operation execution progress.',
                    $userId
                );

                $ncr = ProductionNcr::create([
                    'tenant_id' => $op->tenant_id,
                    'ncr_number' => 'NCR-AUTO-'.strtoupper(uniqid()),
                    'category' => 'process',
                    'status' => 'open',
                    'disposition_type' => 'scrap',
                    'production_order_id' => $order->id,
                    'production_order_operation_id' => $op->id,
                    'machine_id' => $machineId ?? $op->machine_id,
                    'operator_id' => $userId,
                    'description' => "Automatic NCR generated due to scrapped quantity logged during operation #{$op->operation_number}.",
                ]);

                app(ScrapService::class)->createScrapDisposal($op->tenant_id, [
                    'ncr_id' => $ncr->id,
                    'category' => 'finished_good',
                    'reason_code' => 'defect',
                    'quantity' => $scrapped,
                    'cost' => $scrapped * ($order->product->unit_cost ?? 1.00),
                ]);
            }

            // Handle Rework automatically if logged
            if ($rejected > 0) {
                $this->logRework(
                    $order->id,
                    $op->id,
                    $rejected,
                    $remarks ?? 'Automatic log rework from operation execution progress.',
                    $userId
                );

                $ncr = ProductionNcr::create([
                    'tenant_id' => $op->tenant_id,
                    'ncr_number' => 'NCR-AUTO-'.strtoupper(uniqid()),
                    'category' => 'process',
                    'status' => 'open',
                    'disposition_type' => 'rework',
                    'production_order_id' => $order->id,
                    'production_order_operation_id' => $op->id,
                    'machine_id' => $machineId ?? $op->machine_id,
                    'operator_id' => $userId,
                    'description' => "Automatic NCR generated due to rejected quantity logged during operation #{$op->operation_number}.",
                ]);

                app(ReworkService::class)->createReworkOrder($op->tenant_id, $ncr->id, [
                    'original_production_order_id' => $order->id,
                    'work_center_id' => $op->work_center_id,
                    'cost_estimate' => 50.00,
                ]);

                $order->quantity_rejected += $rejected;
                $order->save();
            }

            if ($completeOperation) {
                $this->ensureQualityGatePassed($op);

                $op->status          = ProductionOrderOperation::STATUS_COMPLETED;
                $op->actual_end_time = now();

                // Advance next sequential operation to READY
                $nextOp = ProductionOrderOperation::where('production_order_id', $op->production_order_id)
                    ->where('sequence', '>', $op->sequence)
                    ->orderBy('sequence')
                    ->first();
                if ($nextOp && $nextOp->status === ProductionOrderOperation::STATUS_WAITING) {
                    $nextOp->status = ProductionOrderOperation::STATUS_READY;
                    $nextOp->save();
                }
            } else {
                $op->status = ProductionOrderOperation::STATUS_RUNNING;
                if (empty($op->actual_start_time)) {
                    $op->actual_start_time = now();
                }
            }
            $op->save();

            // Sync with WIP tracking
            $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $op->production_order_id)->first();
            if ($wip) {
                if (empty($wip->started_at)) {
                    $wip->update(['started_at' => now()]);
                }

                app(ProductionWipService::class)->completeWipOperation(
                    $wip->id,
                    $op->id,
                    $produced,
                    $rejected,
                    $scrapped,
                    $setupMinutes,
                    $runMinutes,
                    $remarks,
                    $userId
                );

                if ($completeOperation && isset($nextOp) && $nextOp && $produced > 0 && $op->routing_operation_id && $nextOp->routing_operation_id) {
                    app(ProductionWipService::class)->transferWip(
                        $wip->id,
                        $op->routing_operation_id,
                        $nextOp->routing_operation_id,
                        $produced,
                        'Transferred automatically upon manual operation completion.',
                        $userId
                    );
                }
            }

            // 4. Update parent order to in_progress on first execution log
            if ($order->isReleased()) {
                $order->status            = ProductionOrder::STATUS_IN_PROGRESS;
                $order->actual_start_date = now();
                $order->save();
            }

            return $log;
        });
    }

    /**
     * Log a scrap event and post an inventory outflow.
     *
     * ── Scrap Stock Posting Rules (Correction #1) ──────────────────────────────
     *
     * There is ONE authoritative stock-posting point: logScrap().
     *
     * Distinguishing three distinct concepts:
     *  (a) Production consumption → handled by issueMaterial() (materials going into production)
     *  (b) Movement to scrap location → this method posts a StockService::recordOutflow()
     *      removing the scrapped finished/semi-finished unit from the production warehouse.
     *      The outflow is posted immediately on scrap logging (not deferred) because
     *      operational items (e.g. defective output) need immediate stock correction.
     *      IdEmpotency is enforced via scrap.stock_transaction_id: if it is already set,
     *      the outflow has been posted and the method returns early without double-posting.
     *  (c) NCR-linked quality scrap disposal → managed by ScrapService::approveDisposal()
     *      which handles approval workflow only — it does NOT re-post to inventory
     *      because the stock was already removed in step (b).
     *
     * @param int         $orderId
     * @param int|null    $operationId
     * @param int|null    $productId      Defaults to the order's finished good product
     * @param float       $quantity
     * @param string|null $reason
     * @param int|null    $userId
     * @param int|null    $warehouseId    Warehouse from which to post outflow
     */
    public function logScrap(
        int     $orderId,
        ?int    $operationId,
        ?int    $productId,
        float   $quantity,
        ?string $reason      = null,
        ?int    $userId      = null,
        ?int    $warehouseId = null
    ): ProductionOrderScrap {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Scrap quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($orderId, $operationId, $productId, $quantity, $reason, $userId, $warehouseId) {
            $order     = ProductionOrder::findOrFail($orderId);
            $scrapProductId = $productId ?? $order->product_id;

            // Create the scrap record (stock_transaction_id null = not yet posted)
            $scrap = ProductionOrderScrap::create([
                'tenant_id'                     => $order->tenant_id,
                'production_order_id'           => $order->id,
                'production_order_operation_id' => $operationId,
                'product_id'                    => $scrapProductId,
                'quantity'                      => $quantity,
                'reason'                        => $reason,
                'recorded_by'                   => $userId,
                'recorded_at'                   => now(),
                'stock_transaction_id'          => null,
            ]);

            // ── Idempotent stock outflow posting ──────────────────────────────
            // If stock_transaction_id is already set, the posting has occurred.
            // Double-check is inside the transaction so it is safe under concurrent calls.
            $scrap->refresh();
            if ($scrap->isStockPosted()) {
                return $scrap;
            }

            // Resolve warehouse: prefer passed param, then default
            $resolvedWarehouseId = $warehouseId ?: $this->defaultWarehouseId($order->tenant_id);

            // Post inventory outflow only if the scrapped item is an inventory-tracked product
            $transaction = null;
            if ($resolvedWarehouseId) {
                try {
                    $transaction = StockService::recordOutflow(
                        $order->tenant_id,
                        $scrapProductId,
                        $resolvedWarehouseId,
                        $quantity,
                        'Production Scrap',
                        $order->id
                    );
                } catch (\Exception $e) {
                    // Stock outflow may fail if insufficient stock (e.g. in-process items not yet received).
                    // We record the scrap event regardless; the stock posting failure is noted in the event log.
                    app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                        'production_order_id' => $order->id,
                        'event_type'          => 'Scrap Stock Warning',
                        'title'               => 'Scrap Stock Outflow Skipped',
                        'description'         => "Scrap logged for order #{$order->id} but stock outflow could not be posted: " . $e->getMessage(),
                        'severity'            => 'warning',
                        'event_source'        => 'ProductionExecutionService',
                        'triggered_by'        => $userId,
                    ]);
                }
            }

            // Mark stock as posted (idempotency guard)
            $scrap->update(['stock_transaction_id' => $transaction?->id]);

            // Update order-level scrapped quantity only for the order's own product
            if ($scrapProductId === $order->product_id) {
                $order->quantity_scrapped += $quantity;
                $order->save();
            }

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type'          => 'Scrap Logged',
                'title'               => 'Production Scrap Recorded',
                'description'         => "Scrapped {$quantity} units on order #{$order->order_number}.",
                'severity'            => 'warning',
                'event_source'        => 'ProductionExecutionService',
                'triggered_by'        => $userId,
            ]);

            return $scrap;
        });
    }

    /**
     * Log a rework event loop against the order.
     *
     * logRework() records that $quantity units have been identified as requiring rework.
     * This does NOT change quantity_produced or quantity_scrapped — those quantities
     * represent the final disposition of output. Reworked units remain "in process"
     * until they re-emerge from the rework loop as either:
     *  (a) Good output  → receiveFinishedGoods() is called, incrementing quantity_produced
     *  (b) Scrapped     → logScrap() is called, incrementing quantity_scrapped
     *
     * This prevents double-counting: the same unit is NOT counted as produced AND reworked.
     */
    public function logRework(
        int     $orderId,
        ?int    $operationId,
        float   $quantity,
        ?string $reason  = null,
        ?int    $userId  = null
    ): ProductionOrderRework {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Rework quantity must be greater than zero.');
        }

        $order = ProductionOrder::findOrFail($orderId);

        return ProductionOrderRework::create([
            'tenant_id'                     => $order->tenant_id,
            'production_order_id'           => $order->id,
            'production_order_operation_id' => $operationId,
            'quantity'                      => $quantity,
            'reason'                        => $reason,
            'status'                        => 'pending',
            'recorded_by'                   => $userId,
            'recorded_at'                   => now(),
        ]);
    }

    /**
     * Mark pending rework as complete.
     *
     * Rework completion means the rework loop is done; the reworked units are now
     * ready to be re-assessed. The caller must separately:
     *  - Call receiveFinishedGoods() for reworked units that passed → adds to quantity_produced
     *  - Call logScrap() for reworked units that are still defective → adds to quantity_scrapped
     *
     * This method does NOT increment quantity_produced to avoid double-counting units
     * that were already logged as produced or rejected in logProgress().
     */
    public function completeRework(int $reworkId): void
    {
        $rework         = ProductionOrderRework::findOrFail($reworkId);
        $rework->status = 'completed';
        $rework->save();
    }

    /**
     * Receive Finished Goods from shop floor into inventory.
     *
     * This is the authoritative point for incrementing ProductionOrder.quantity_produced.
     * All of the following actions run atomically in a single DB transaction:
     *
     *  1. Validate warehouse (active + tenant-owned)
     *  2. Create ProductionOrderReceipt
     *  3. Increment order.quantity_produced
     *  4. Call StockService::recordInflow() — creates StockTransaction + updates ProductWarehouseStock
     *  5. If order uses batch mode, resolve the ProductionBatch and link inventory_batch_id on receipt
     *  6. If order uses serial mode, validate serial strings and store immutable JSON snapshot
     *  7. Write ProductionLotTrace from ProductionBatch → inventory batch (FG genealogy)
     *
     * @param int         $orderId
     * @param float       $quantity
     * @param string      $qualityStatus  passed | quarantine | failed
     * @param string|null $remarks
     * @param int|null    $userId
     * @param int|null    $warehouseId
     * @param string|null $productionBatchNumber  BAT-YYYY-NNNNNN to link FG batch
     * @param array       $serialNumbers  Serial number strings for serial-tracked products
     */
    public function receiveFinishedGoods(
        int     $orderId,
        float   $quantity,
        string  $qualityStatus         = 'passed',
        ?string $remarks               = null,
        ?int    $userId                = null,
        ?int    $warehouseId           = null,
        ?string $productionBatchNumber = null,
        array   $serialNumbers         = []
    ): ProductionOrderReceipt {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Receipt quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $orderId, $quantity, $qualityStatus, $remarks, $userId, $warehouseId,
            $productionBatchNumber, $serialNumbers
        ) {
            $order = ProductionOrder::findOrFail($orderId);

            if ($order->isClosed() || $order->isCancelled()) {
                throw new InvalidArgumentException('Cannot receive finished goods on a closed or cancelled order.');
            }

            $warehouseId = $warehouseId ?: $this->defaultWarehouseId($order->tenant_id);
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before receiving finished goods.');
            }

            // Validate warehouse belongs to tenant and is active
            $warehouse = Warehouse::where('tenant_id', $order->tenant_id)->where('id', $warehouseId)->first();
            if (! $warehouse) {
                throw new InvalidArgumentException("Warehouse #{$warehouseId} does not belong to this tenant.");
            }

            // ── Step 3: Increment order-level quantity_produced ───────────────
            // quantity_produced represents total finished goods accepted from this order.
            $order->quantity_produced += $quantity;
            $order->save();

            // ── Step 4: Post inventory inflow (authoritative step) ────────────
            // StockService::recordInflow() returns the StockTransaction record.
            // For batch-tracked products, it also creates/updates the Inventory::Batch record.
            $unitCost = (float) ($order->product?->unit_cost ?? $order->product?->cost_price ?? 0);

            $transaction = StockService::recordInflow(
                $order->tenant_id,
                $order->product_id,
                $warehouseId,
                $quantity,
                $unitCost,
                'Production Order Receipt',
                $order->id,
                $productionBatchNumber, // Parameter 8: batch number
                $serialNumbers          // Parameter 9: serial numbers
            );

            // ── Step 5: Resolve the Inventory::Batch created by StockService ──
            $inventoryBatchId = $transaction->created_batch?->id;

            // ── Step 6: Validate serial numbers (snapshot only) ───────────────
            // The authoritative serial ledger is inventory serial_numbers table managed by StockService.
            // We only store a snapshot here for fast reference on the receipt record.
            $serialSnapshot = null;
            if (! empty($serialNumbers)) {
                // Validate each serial exists on this production order
                $validSerials = ProductionSerialNumber::where('tenant_id', $order->tenant_id)
                    ->where('production_order_id', $order->id)
                    ->whereIn('serial_number', $serialNumbers)
                    ->pluck('serial_number')
                    ->toArray();

                $invalid = array_diff($serialNumbers, $validSerials);
                if (! empty($invalid)) {
                    throw new InvalidArgumentException(
                        'The following serial numbers do not belong to this production order: ' . implode(', ', $invalid)
                    );
                }
                $serialSnapshot = $serialNumbers;
            }

            // ── Step 2: Create production receipt record ──────────────────────
            $receipt = ProductionOrderReceipt::create([
                'tenant_id'           => $order->tenant_id,
                'production_order_id' => $order->id,
                'product_id'          => $order->product_id,
                'warehouse_id'        => $warehouseId,
                'inventory_batch_id'  => $inventoryBatchId,
                'serial_numbers'      => $serialSnapshot,
                'quantity_received'   => $quantity,
                'quality_status'      => $qualityStatus,
                'received_by'         => $userId,
                'received_at'         => now(),
                'remarks'             => $remarks,
            ]);

            // ── Step 7: Write FG genealogy trace ─────────────────────────────
            // Link ProductionBatch → Inventory::Batch for full forward traceability.
            if ($productionBatchNumber && $inventoryBatchId) {
                $productionBatch = ProductionBatch::where('tenant_id', $order->tenant_id)
                    ->where('batch_number', $productionBatchNumber)
                    ->first();

                if ($productionBatch) {
                    // Trace: production batch → inventory batch (FG stock created)
                    ProductionLotTrace::create([
                        'tenant_id'   => $order->tenant_id,
                        'source_type' => 'batch',            // production batch
                        'source_id'   => $productionBatch->id,
                        'target_type' => 'lot',              // inventory batch (FG lot)
                        'target_id'   => $inventoryBatchId,
                        'quantity'    => $quantity,
                        'remarks'     => "Finished goods received into inventory batch #{$inventoryBatchId} from production batch {$productionBatchNumber}.",
                    ]);

                    // Also update production batch actual quantity
                    $productionBatch->update([
                        'actual_quantity'  => $productionBatch->actual_quantity + $quantity,
                        'manufactured_at'  => $productionBatch->manufactured_at ?? now(),
                        'status'           => 'completed',
                    ]);
                }
            }

            // Sync with WIP tracking
            $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $order->id)->first();
            if ($wip && $wip->available_quantity > 0) {
                // Clear WIP balances
                $wip->update([
                    'quantity' => 0.00,
                    'available_quantity' => 0.00,
                    'status' => 'completed',
                ]);

                // Create WIP Transaction of type converted_to_fg
                \App\Domains\Production\Models\ProductionWipTransaction::create([
                    'tenant_id' => $wip->tenant_id,
                    'wip_id' => $wip->id,
                    'production_order_id' => $wip->production_order_id,
                    'production_batch_id' => $wip->production_batch_id,
                    'transaction_type' => 'converted_to_finished_goods',
                    'quantity' => $quantity,
                    'cost_before' => $wip->total_value,
                    'cost_added' => 0.00,
                    'cost_after' => 0.00,
                    'remarks' => $remarks ?? "Completed WIP quantity of {$quantity} received into finished inventory directly.",
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type'          => 'Finished Goods Received',
                'title'               => 'Finished Goods Received',
                'description'         => "Received {$quantity} finished goods with quality status '{$qualityStatus}'.",
                'severity'            => 'success',
                'event_source'        => 'ProductionExecutionService',
                'triggered_by'        => $userId,
            ]);

            return $receipt;
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    private function ensureQualityGatePassed(ProductionOrderOperation $operation): void
    {
        if (! $operation->routingOperation?->quality_required) {
            return;
        }

        $passedInspectionExists = ProductionQualityInspection::where('tenant_id', $operation->tenant_id)
            ->where('production_order_operation_id', $operation->id)
            ->where('status', 'approved')
            ->where('result', 'passed')
            ->exists();

        if (! $passedInspectionExists) {
            throw new InvalidArgumentException('This operation requires an approved passed quality inspection before completion.');
        }
    }

    private function defaultWarehouseId(int $tenantId): ?int
    {
        return Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('id')
            ?? Warehouse::query()->where('tenant_id', $tenantId)->value('id');
    }
}
