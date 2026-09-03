<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionWipService
{
    /**
     * Get or initialize a batch-and-operation-specific WIP record.
     * Scope: tenant_id + production_order_id + production_batch_id + current_routing_operation_id
     */
    public function getOrCreateWipForBatchOperation(
        int $orderId,
        int $batchId,
        int $operationId,
        ?int $userId = null
    ): ProductionWip {
        return DB::transaction(function () use ($orderId, $batchId, $operationId, $userId) {
            $order = ProductionOrder::findOrFail($orderId);

            $orderOp = ProductionOrderOperation::where('production_order_id', $orderId)
                ->where(function ($q) use ($operationId) {
                    $q->where('routing_operation_id', $operationId)
                        ->orWhere('id', $operationId);
                })
                ->first();

            $routingOpId = $orderOp ? $orderOp->routing_operation_id : $operationId;
            $workCenterId = $orderOp ? $orderOp->work_center_id : null;
            $machineId = $orderOp ? $orderOp->machine_id : null;

            $existing = ProductionWip::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->where('production_batch_id', $batchId)
                ->where('current_routing_operation_id', $routingOpId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $batch = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $order->tenant_id)->find($batchId);
            $validBatchId = $batch ? $batch->id : null;

            $targetOp = ProductionOrderOperation::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where(function ($q) use ($routingOpId) {
                    $q->where('routing_operation_id', $routingOpId)
                      ->orWhere('id', $routingOpId);
                })
                ->first();

            $plannedQty = $batch ? (float) $batch->planned_quantity : (float) $order->quantity_ordered;
            if ($targetOp && (float) $targetOp->target_produced_qty > 0) {
                $plannedQty = $batch ? min((float) $batch->planned_quantity, (float) $targetOp->target_produced_qty) : (float) $targetOp->target_produced_qty;
            }

            $wip = ProductionWip::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'production_batch_id' => $validBatchId,
                'product_id' => $batch ? $batch->product_id : $order->product_id,
                'current_routing_operation_id' => $routingOpId,
                'current_schedule_operation_id' => null,
                'current_work_center_id' => $workCenterId,
                'current_machine_id' => $machineId,
                'quantity' => $plannedQty,
                'available_quantity' => 0.0000,
                'completed_quantity' => 0.0000,
                'rejected_quantity' => 0.0000,
                'scrap_quantity' => 0.0000,
                'rework_quantity' => 0.0000,
                'status' => 'active',
                'material_cost' => 0.0000,
                'labor_cost' => 0.0000,
                'machine_cost' => 0.0000,
                'overhead_cost' => 0.0000,
                'total_value' => 0.0000,
                'started_at' => now(),
                'last_moved_at' => now(),
                'created_by' => $userId,
            ]);

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $order->id,
                'production_batch_id' => $batchId,
                'from_operation_id' => null,
                'to_operation_id' => $routingOpId,
                'from_work_center_id' => null,
                'to_work_center_id' => $workCenterId,
                'machine_id' => $machineId,
                'operator_id' => $userId,
                'transaction_type' => 'created',
                'quantity' => $plannedQty,
                'good_quantity' => 0.0000,
                'rejected_quantity' => 0.0000,
                'scrap_quantity' => 0.0000,
                'rework_quantity' => 0.0000,
                'cost_before' => 0.0000,
                'cost_added' => 0.0000,
                'cost_after' => 0.0000,
                'remarks' => 'WIP record created for batch operation.',
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            $this->reconcileOrderWipCards($order->id);

            return $wip;
        });
    }

    /**
     * Initialize a WIP record for a released Production Order.
     */
    public function initializeWip(int $orderId, ?int $batchId = null, ?int $userId = null): ProductionWip
    {
        return $this->initializeWipForOrder($orderId, $userId, $batchId);
    }

    /**
     * Initialize WIP tracking card for a Production Order (starts at initial routing operation).
     */
    public function initializeWipForOrder(int $orderId, ?int $userId = null, ?int $batchId = null): ProductionWip
    {
        return DB::transaction(function () use ($orderId, $userId, $batchId) {
            $order = ProductionOrder::findOrFail($orderId);

            // Fetch the first operation in sequence
            $firstOp = $order->operations()->orderBy('sequence', 'asc')->first();
            if (!$firstOp) {
                throw new InvalidArgumentException("Cannot initialize WIP: The Production Order has no routing operations.");
            }

            if ($batchId) {
                return $this->getOrCreateWipForBatchOperation($orderId, $batchId, $firstOp->routing_operation_id, $userId);
            }

            // Check if WIP already exists for this order/batch combination
            $existing = ProductionWip::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->whereNull('production_batch_id')
                ->where('current_routing_operation_id', $firstOp->routing_operation_id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $firstOpTarget = (float) ($firstOp->target_produced_qty > 0 ? $firstOp->target_produced_qty : $order->quantity_ordered);
            if (!empty($firstOp->source_product_id) && $firstOp->source_product_id !== $order->product_id) {
                $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $order->tenant_id)
                    ->where('bom_id', $order->bom_id)
                    ->where('material_id', $firstOp->source_product_id)
                    ->first();
                if ($bomItem && (float) $bomItem->quantity > 0) {
                    $firstOpTarget = (float) $order->quantity_ordered * (float) $bomItem->quantity;
                }
            }

            $wip = ProductionWip::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'production_batch_id' => null,
                'product_id' => $order->product_id,
                'current_routing_operation_id' => $firstOp->routing_operation_id,
                'current_schedule_operation_id' => null,
                'current_work_center_id' => $firstOp->work_center_id,
                'current_machine_id' => $firstOp->machine_id,
                'quantity' => $firstOpTarget,
                'available_quantity' => $firstOpTarget,
                'completed_quantity' => 0.0000,
                'rejected_quantity' => 0.0000,
                'scrap_quantity' => 0.0000,
                'rework_quantity' => 0.0000,
                'status' => 'active',
                'material_cost' => 0.0000,
                'labor_cost' => 0.0000,
                'machine_cost' => 0.0000,
                'overhead_cost' => 0.0000,
                'total_value' => 0.0000,
                'started_at' => null,
                'last_moved_at' => now(),
                'created_by' => $userId,
            ]);

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $order->id,
                'production_batch_id' => null,
                'from_operation_id' => null,
                'to_operation_id' => $firstOp->routing_operation_id,
                'from_work_center_id' => null,
                'to_work_center_id' => $firstOp->work_center_id,
                'machine_id' => $firstOp->machine_id,
                'operator_id' => $userId,
                'transaction_type' => 'created',
                'quantity' => $firstOpTarget,
                'good_quantity' => 0.0000,
                'rejected_quantity' => 0.0000,
                'scrap_quantity' => 0.0000,
                'rework_quantity' => 0.0000,
                'cost_before' => 0.0000,
                'cost_added' => 0.0000,
                'cost_after' => 0.0000,
                'remarks' => 'WIP tracking initialized.',
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            return $wip;
        });
    }

    /**
     * Start WIP operation from MES.
     */
    public function startWipOperation(int $wipId, int $scheduleOpId, ?int $userId = null): void
    {
        DB::transaction(function () use ($wipId, $scheduleOpId, $userId) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);
            $schedOp = ProductionScheduleOperation::with('orderOperation')->findOrFail($scheduleOpId);
            $orderOp = $schedOp->orderOperation;

            if ($wip->order->isClosed() || $wip->order->isCancelled()) {
                throw new InvalidArgumentException("Cannot modify WIP: Parent order is closed or cancelled.");
            }

            $wip->update([
                'current_routing_operation_id' => $orderOp ? $orderOp->routing_operation_id : $wip->current_routing_operation_id,
                'current_schedule_operation_id' => $scheduleOpId,
                'current_work_center_id' => $schedOp->work_center_id,
                'current_machine_id' => $schedOp->machine_id ?? $wip->current_machine_id,
                'started_at' => $wip->started_at ?? now(),
                'last_moved_at' => now(),
                'status' => 'active',
            ]);

            $startQty = $wip->available_quantity;
            if ($orderOp) {
                $availableInput = $this->getAvailableInputWip($orderOp, $wip->production_batch_id);
                if ($availableInput > 0) {
                    $startQty = min($startQty > 0 ? $startQty : $availableInput, $availableInput);
                }
            }

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'from_operation_id' => $wip->current_routing_operation_id,
                'to_operation_id' => $orderOp ? $orderOp->routing_operation_id : null,
                'from_work_center_id' => $wip->current_work_center_id,
                'to_work_center_id' => $schedOp->work_center_id,
                'machine_id' => $schedOp->machine_id,
                'operator_id' => $userId,
                'transaction_type' => 'operation_started',
                'quantity' => $startQty,
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Log progress and complete WIP operations.
     */
    public function completeWipOperation(
        int $wipId,
        int $orderOpId,
        float $goodQty,
        float $rejectedQty,
        float $scrapQty,
        float $setupMins,
        float $runMins,
        ?string $remarks = null,
        ?int $userId = null,
        bool $isOperationCompleted = false
    ): void {
        DB::transaction(function () use ($wipId, $orderOpId, $goodQty, $rejectedQty, $scrapQty, $setupMins, $runMins, $remarks, $userId, $isOperationCompleted) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);
            $orderOp = ProductionOrderOperation::with(['workCenter', 'routingOperation'])->findOrFail($orderOpId);

            if ($wip->order->isClosed() || $wip->order->isCancelled()) {
                throw new InvalidArgumentException("Cannot modify WIP: Parent order is closed or cancelled.");
            }

            // Resolve cost rates from routing snapshots
            $laborRate = $orderOp->routingOperation?->labor_cost_rate ?? 0.0;
            $machineRate = $orderOp->routingOperation?->machine_cost_rate ?? 0.0;
            $overheadRate = $orderOp->workCenter?->overhead_rate ?? 0.0;

            $activeMinutes = $setupMins + $runMins;
            $laborCost = $activeMinutes * $laborRate;
            $machineCost = $activeMinutes * $machineRate;
            $overheadCost = $activeMinutes * ($overheadRate / 60.0);
            $totalCostAdded = $laborCost + $machineCost + $overheadCost;

            $costBefore = $wip->total_value;

            // Update costing value
            $wip->labor_cost += $laborCost;
            $wip->machine_cost += $machineCost;
            $wip->overhead_cost += $overheadCost;
            $wip->total_value += $totalCostAdded;

            // Determine if this is the final operation of the Finished Good (FG) routing
            $nextOpExists = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where(function ($q) use ($orderOp) {
                    if ($orderOp->routing_id) {
                        $q->where('routing_id', $orderOp->routing_id);
                    } else {
                        $q->where('source_product_id', $orderOp->source_product_id);
                    }
                })
                ->where('sequence', '>', $orderOp->sequence)
                ->exists();

            $isFinalFgOperation = !$nextOpExists && (!$orderOp->is_intermediate && ($orderOp->source_product_id === null || (int) $orderOp->source_product_id === (int) $wip->order->product_id));

            // Update quantity states: only final FG operations increment FG completed_quantity
            if ($isFinalFgOperation) {
                if ($orderOp->routing_operation_id) {
                    $wip->current_routing_operation_id = $orderOp->routing_operation_id;
                }
                if ($orderOp->work_center_id) {
                    $wip->current_work_center_id = $orderOp->work_center_id;
                }
                $wip->completed_quantity += $goodQty;
                $wip->available_quantity = $wip->completed_quantity;
            } else {
                $wip->available_quantity = max(0.0000, $wip->available_quantity - $scrapQty - $rejectedQty);
            }
            $wip->rejected_quantity += $rejectedQty;
            $wip->scrap_quantity += $scrapQty;

            $isCompleted = $isOperationCompleted || $orderOp->status === ProductionOrderOperation::STATUS_COMPLETED;

            if (!$nextOpExists && $isCompleted) {
                $wip->status = 'completed';
                $wip->completed_at = now();
                if ($isFinalFgOperation && $wip->completed_quantity <= 0 && $wip->available_quantity > 0) {
                    $wip->completed_quantity = $wip->available_quantity;
                }
            }

            $wip->updated_by = $userId;
            $wip->save();

            $txType = $isCompleted ? 'operation_completed' : 'progress_logged';
            $defaultRemarks = $isCompleted ? 'Operation completed.' : 'Progress logged on operation.';

            $finalRemarks = $remarks;
            if (empty($finalRemarks) || $finalRemarks === 'Progress completed on operation.') {
                $finalRemarks = $defaultRemarks;
            }

            // Log Transaction
            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'from_operation_id' => $orderOp ? $orderOp->routing_operation_id : null,
                'to_operation_id' => null,
                'from_work_center_id' => $orderOp->work_center_id,
                'to_work_center_id' => null,
                'machine_id' => $orderOp->machine_used_id ?? $orderOp->machine_id,
                'operator_id' => $userId,
                'transaction_type' => $txType,
                'quantity' => $goodQty,
                'good_quantity' => $goodQty,
                'rejected_quantity' => $rejectedQty,
                'scrap_quantity' => $scrapQty,
                'cost_before' => $costBefore,
                'cost_added' => $totalCostAdded,
                'cost_after' => $wip->total_value,
                'remarks' => $finalRemarks,
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            // Timeline Event
            app(ProductionEventService::class)->writeEvent($wip->tenant_id, [
                'production_order_id' => $wip->production_order_id,
                'event_type' => 'WIP Updated',
                'title' => 'WIP Cost & Qty Updated',
                'description' => "WIP updated: Good: {$goodQty}, Scrap: {$scrapQty}. Added cost: " . number_format($totalCostAdded, 2),
                'severity' => 'info',
                'event_source' => 'ProductionWipService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Transfer WIP quantity to another operation step in sequence.
     */
    public function transferWip(
        int $wipId,
        ?int $fromOpId,
        ?int $toOpId,
        float $quantity,
        ?string $remarks = null,
        ?int $userId = null,
        ?string $idempotencyKey = null
    ): void {
        if ($fromOpId === null || $toOpId === null) {
            return;
        }

        if ($quantity <= 0.0) {
            throw new InvalidArgumentException("Transfer quantity must be greater than zero.");
        }

        if ($idempotencyKey) {
            $existingTx = ProductionWipTransaction::where('remarks', 'like', "%IDEMPOTENCY:{$idempotencyKey}%")->first();
            if ($existingTx) {
                return; // Idempotent skip
            }
        }

        DB::transaction(function () use ($wipId, $fromOpId, $toOpId, $quantity, $remarks, $userId, $idempotencyKey) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($wip->order->isClosed() || $wip->order->isCancelled()) {
                throw new InvalidArgumentException("Cannot transfer WIP: Parent order is closed or cancelled.");
            }

            $batchId = $wip->production_batch_id ?? \App\Domains\Production\Models\ProductionBatch::where('production_order_id', $wip->production_order_id)->value('id');
            $batch = $batchId ? \App\Domains\Production\Models\ProductionBatch::find($batchId) : null;

            $toOrderOp = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where(function ($q) use ($toOpId) {
                    $q->where('id', $toOpId)
                        ->orWhere('routing_operation_id', $toOpId);
                })
                ->orderByRaw("id = ? DESC", [$toOpId])
                ->first();

            if (!$toOrderOp) {
                throw new InvalidArgumentException("Destination routing operation is not configured for this Production Order.");
            }

            $fromOrderOp = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where(function ($q) use ($fromOpId) {
                    $q->where('id', $fromOpId)
                        ->orWhere('routing_operation_id', $fromOpId);
                })
                ->orderByRaw("id = ? DESC", [$fromOpId])
                ->first();

            if ($fromOrderOp && $toOrderOp->sequence > $fromOrderOp->sequence) {
                $nextSequence = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                    ->where('sequence', '>', $fromOrderOp->sequence)
                    ->min('sequence');

                if ($toOrderOp->sequence !== $nextSequence) {
                    throw new InvalidArgumentException("WIP cannot skip routing operations. Next allowed stage sequence is {$nextSequence}.");
                }
            }

            $targetBatchId = $wip->production_batch_id;
            if (!$targetBatchId && request()->filled('production_batch_id')) {
                $targetBatchId = (int) request()->input('production_batch_id');
            }

            // Quality Hold & Rework Validation
            $qhQuery = \App\Domains\Production\Models\ProductionQualityInspection::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('production_order_operation_id', [$fromOrderOp->id, $fromOrderOp->routing_operation_id])
                ->whereIn('status', ['hold', 'failed']);
            if ($targetBatchId) {
                $qhQuery->where(fn($sub) => $sub->whereNull('batch_id')->orWhere('batch_id', $targetBatchId));
            }
            $hasQualityHold = $qhQuery->exists();

            $rwQuery = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('production_order_operation_id', [$fromOrderOp->id, $fromOrderOp->routing_operation_id])
                ->where('status', 'pending');
            if ($targetBatchId) {
                $rwQuery->where('production_batch_id', $targetBatchId);
            }
            $hasPendingRework = $rwQuery->exists();

            if ($hasQualityHold) {
                $batchLabel = $batch ? "Batch #{$batch->batch_number}" : "Order #{$wip->production_order_id}";
                throw new InvalidArgumentException("{$batchLabel} has an active quality hold and cannot be transferred.");
            }

            // Calculate actual ready-to-transfer balance
            $fromOpIds = array_filter(array_unique([
                $fromOpId,
                $fromOrderOp?->id,
                $fromOrderOp?->routing_operation_id,
                $wip->current_routing_operation_id,
            ]));

            $logQuery = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('operation_id', $fromOpIds);
            $logQty = (float) $logQuery->sum('quantity_produced');

            $txQty = (float) ProductionWipTransaction::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('from_operation_id', $fromOpIds)
                ->whereIn('transaction_type', ['operation_completed', 'progress_logged', 'rework_completed'])
                ->sum('quantity');

            $goodOutput = ($logQty > 0 || $txQty > 0)
                ? max($logQty, $txQty)
                : (float) ($fromOrderOp?->quantity_produced ?? 0);

            $scrapQuery = \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('production_order_operation_id', $fromOpIds);
            $goodOutput -= (float) $scrapQuery->sum('quantity');

            $txQuery = ProductionWipTransaction::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('from_operation_id', array_filter([$fromOpId, $fromOrderOp?->routing_operation_id, $fromOrderOp?->id]))
                ->where('transaction_type', 'transferred');
            if ($wip->production_batch_id) {
                $txQuery->where('production_batch_id', $wip->production_batch_id);
            }
            $alreadyTransferred = (float) $txQuery->sum('quantity');

            $readyToTransfer = round(max(0.0, $goodOutput - $alreadyTransferred), 4);

            if ($quantity > ($readyToTransfer + 0.0001)) {
                $hasExactTx = ProductionWipTransaction::where('tenant_id', $wip->tenant_id)
                    ->where('production_order_id', $wip->production_order_id)
                    ->where('from_operation_id', $fromOpId)
                    ->where('to_operation_id', $toOpId)
                    ->where('transaction_type', 'transferred')
                    ->where('quantity', round($quantity, 4))
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->exists();

                if ($hasExactTx && abs($goodOutput - $alreadyTransferred) < 0.0001) {
                    return; // Idempotent: this exact transfer chunk was already executed
                }

                $batchLabel = $batch ? "batch #{$batch->batch_number}" : "order #{$wip->production_order_id}";
                throw new InvalidArgumentException("Transfer quantity (" . number_format($quantity, 2) . ") exceeds available ready-to-transfer quantity (" . number_format($readyToTransfer, 2) . ") for {$batchLabel}.");
            }

            $destWip = $batchId ? $this->getOrCreateWipForBatchOperation($wip->production_order_id, $batchId, $toOpId, $userId) : $wip;

            $fromOp = RoutingOperation::find($fromOpId);
            $toOp = RoutingOperation::find($toOpId);

            if ($fromOrderOp) {
                $fromOrderOp->quantity_transferred_out = round((float) $fromOrderOp->quantity_transferred_out + $quantity, 4);
                $fromOrderOp->save();
            }

            if ($toOrderOp) {
                $toOrderOp->quantity_transferred_in = round((float) $toOrderOp->quantity_transferred_in + $quantity, 4);
                if ($toOrderOp->status === ProductionOrderOperation::STATUS_WAITING) {
                    $toOrderOp->status = ProductionOrderOperation::STATUS_READY;
                }
                $toOrderOp->save();

                ProductionScheduleOperation::where('tenant_id', $wip->tenant_id)
                    ->where('production_order_id', $wip->production_order_id)
                    ->where('production_order_operation_id', $toOrderOp->id)
                    ->where('status', ProductionScheduleOperation::STATUS_WAITING)
                    ->update(['status' => ProductionScheduleOperation::STATUS_READY]);
            }

            $destWip->current_routing_operation_id = $toOpId;
            $destWip->available_quantity = round((float) $destWip->available_quantity + $quantity, 4);
            $destWip->save();

            if ($wip->id !== $destWip->id) {
                $wip->available_quantity = max(0.0000, round((float) $wip->available_quantity - $quantity, 4));
                if ($wip->available_quantity <= 0.0001 && $wip->status === 'active') {
                    $wip->status = 'transferred';
                }
                $wip->save();
            }

            if ($batch && $toOrderOp->sequence >= ($batch->currentOperation?->sequence ?? 0)) {
                $batch->current_operation_id = $toOrderOp->id;
                $batch->save();
            }

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $batchId,
                'from_operation_id' => $fromOpId,
                'to_operation_id' => $toOpId,
                'from_work_center_id' => $fromOp ? $fromOp->work_center_id : null,
                'to_work_center_id' => $toOrderOp->work_center_id,
                'machine_id' => $toOrderOp->machine_id,
                'operator_id' => $userId,
                'transaction_type' => 'transferred',
                'quantity' => $quantity,
                'good_quantity' => $quantity,
                'cost_before' => $wip->total_value,
                'cost_added' => 0.00,
                'cost_after' => $wip->total_value,
                'remarks' => ($remarks ?? "WIP transferred from " . ($fromOp?->name ?? 'OP') . " to " . $toOp->name . " for " . ($batch ? "batch #{$batch->batch_number}" : "order #{$wip->production_order_id}") . ".") . ($idempotencyKey ? " IDEMPOTENCY:{$idempotencyKey}" : ""),
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            app(ProductionEventService::class)->writeEvent($wip->tenant_id, [
                'production_order_id' => $wip->production_order_id,
                'event_type' => 'WIP Transferred',
                'title' => 'WIP Transferred Step',
                'description' => "Transferred {$quantity} units to routing operation step '{$toOp->name}' for " . ($batch ? "batch #{$batch->batch_number}" : "order #{$wip->production_order_id}") . ".",
                'severity' => 'info',
                'event_source' => 'ProductionWipService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Authoritative Central WIP Transfer Evaluator and Execution Engine.
     * Evaluates untransferred good output on a source operation and executes
     * sub-batch WIP transfer to the successor operation.
     */
    public function evaluateAndExecuteWipTransfers(int $sourceOrderOpId, ?int $userId = null): float
    {
        return DB::transaction(function () use ($sourceOrderOpId, $userId) {
            $sourceOp = ProductionOrderOperation::lockForUpdate()
                ->findOrFail($sourceOrderOpId);
            $tenantId = $sourceOp->tenant_id;

            $order = $sourceOp->order;
            if (!$order || $order->isClosed() || $order->isCancelled()) {
                return 0.0;
            }

            // Find immediate successor operation respecting previous_operation_id and routing
            $nextOp = ProductionOrderOperation::where('tenant_id', $sourceOp->tenant_id)
                ->where('production_order_id', $sourceOp->production_order_id)
                ->where('previous_operation_id', $sourceOp->id)
                ->lockForUpdate()
                ->first();

            if (!$nextOp) {
                $nextOp = ProductionOrderOperation::where('tenant_id', $sourceOp->tenant_id)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->where(function ($q) use ($sourceOp) {
                        if ($sourceOp->source_product_id) {
                            $q->where('source_product_id', $sourceOp->source_product_id)
                              ->orWhere('source_product_id', $sourceOp->order?->product_id);
                        } elseif ($sourceOp->routing_id) {
                            $q->where('routing_id', $sourceOp->routing_id);
                        }
                    })
                    ->where('sequence', '>', $sourceOp->sequence)
                    ->orderBy('sequence', 'asc')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$nextOp) {
                return 0.0; // Final operation has no downstream operation to transfer to
            }

            $batches = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $tenantId)
                ->where('production_order_id', $sourceOp->production_order_id)
                ->whereNotIn('status', [\App\Domains\Production\Models\ProductionBatch::STATUS_CANCELLED, \App\Domains\Production\Models\ProductionBatch::STATUS_CONSUMED])
                ->lockForUpdate()
                ->get();

            $isFirstOp = !ProductionOrderOperation::where('tenant_id', $tenantId)
                ->where('production_order_id', $sourceOp->production_order_id)
                ->where('sequence', '<', $sourceOp->sequence)
                ->exists();

            $totalTransferredAllBatches = 0.0;
            $batchList = $batches->isNotEmpty() ? $batches : [null];

            foreach ($batchList as $batch) {
                $batchId = $batch?->id;
                $plannedQty = $batch ? (float) $batch->planned_quantity : (float) ($order->quantity_ordered ?? 0.0);

                // Quality Hold and Pending Rework Check per batch & operation
                $hasQualityHold = \App\Domains\Production\Models\ProductionQualityInspection::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('batch_id')->orWhere('batch_id', $batchId)))
                    ->where('production_order_operation_id', $sourceOp->id)
                    ->whereIn('status', ['hold', 'failed'])
                    ->exists();

                $hasPendingRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
                    ->whereIn('production_order_operation_id', [$sourceOp->id, $sourceOp->routing_operation_id])
                    ->where('status', 'pending')
                    ->exists();

                if ($hasQualityHold) {
                    continue; // Exclude quality hold batches from automatic transfer calculation
                }

                $logQty = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->sum('quantity_produced');

                $txQty = (float) ProductionWipTransaction::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('from_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->whereIn('transaction_type', ['operation_completed', 'progress_logged', 'rework_completed', 'subcontract_received', 'subcontract_qc_passed', 'subcontract_completed'])
                    ->sum('quantity');

                $reworkPendingQty = (float) \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('production_order_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->where('status', '!=', 'completed')
                    ->sum('quantity');

                $scrapQty = (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('production_order_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->sum('quantity');

                $opRoutingIds = array_filter([$sourceOp->routing_operation_id, $sourceOp->id]);
                $wipCompletedQty = (float) ProductionWip::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->where(function ($q) use ($opRoutingIds) {
                        $q->whereIn('current_routing_operation_id', $opRoutingIds)
                            ->orWhereNull('current_routing_operation_id');
                    })
                    ->sum('completed_quantity');

                $rawGoodOutput = ($logQty > 0 || $txQty > 0 || $wipCompletedQty > 0)
                    ? max($logQty, $txQty, $wipCompletedQty)
                    : ($batchId ? 0.0 : (float) ($sourceOp->quantity_produced ?? 0));

                $goodOutput = round(max(0.0, $rawGoodOutput - $reworkPendingQty - $scrapQty), 4);

                $latestInspection = \App\Domains\Production\Models\ProductionQualityInspection::where('tenant_id', $tenantId)
                    ->whereIn('production_order_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->where('status', 'approved')
                    ->latest()
                    ->first();

                if ($latestInspection && (float) $latestInspection->passed_qty >= 0) {
                    $goodOutput = min($goodOutput, (float) $latestInspection->passed_qty);
                }

                $alreadyTransferred = (float) ProductionWipTransaction::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->where('transaction_type', 'transferred')
                    ->where(function ($q) use ($sourceOp) {
                        $fromOpIds = array_filter(array_unique([$sourceOp->id, $sourceOp->routing_operation_id]));
                        $q->whereIn('from_operation_id', $fromOpIds)
                          ->orWhere('remarks', 'like', "%from Op {$sourceOp->sequence}%");
                    })
                    ->sum('quantity');

                $readyToTransfer = round(max(0.0, $goodOutput - $alreadyTransferred), 4);
                if ($readyToTransfer <= 0.0) {
                    continue;
                }

                // Determine expected input to check batch completion at this source operation
                if ($isFirstOp) {
                    $expectedInput = $plannedQty;
                } else {
                    $expectedInput = (float) ProductionWipTransaction::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->where('transaction_type', 'transferred')
                        ->where(function ($q) use ($sourceOp) {
                            $toOpIds = array_filter(array_unique([$sourceOp->id, $sourceOp->routing_operation_id]));
                            $q->whereIn('to_operation_id', $toOpIds)
                              ->orWhere('remarks', 'like', "%to Op {$sourceOp->sequence}%");
                        })
                        ->sum('quantity');
                }

                $processedAtCurrentOp = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->sum('quantity_produced')
                    + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->whereIn('operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                        ->sum('quantity_rejected')
                    + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->whereIn('operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                        ->sum('quantity_scrapped');

                $batchCompletedAtOp = ($expectedInput > 0 && $processedAtCurrentOp >= $expectedInput)
                    || ($goodOutput >= $plannedQty && $plannedQty > 0)
                    || ($sourceOp->status === ProductionOrderOperation::STATUS_COMPLETED)
                    || ($sourceOp->is_external && $sourceOp->status !== 'subcontract_qc_pending' && $readyToTransfer > 0);

                // Transfer Chunk Determination
                $transferBatchQty = (float) ($sourceOp->transfer_batch_quantity > 0
                    ? $sourceOp->transfer_batch_quantity
                    : ($sourceOp->routingOperation?->transfer_batch_quantity ?? 0));

                $batchSize = (($sourceOp->queue_threshold_enabled ?? $sourceOp->overlap_enabled) || $transferBatchQty > 0)
                    ? $transferBatchQty
                    : 0.0;

                $chunks = [];
                if ($batchSize > 0) {
                    $fullChunksCount = (int) floor($readyToTransfer / $batchSize);
                    $remainder = round($readyToTransfer - ($fullChunksCount * $batchSize), 4);

                    for ($c = 0; $c < $fullChunksCount; $c++) {
                        $chunks[] = $batchSize;
                    }

                    if ($batchCompletedAtOp && $remainder > 0) {
                        $chunks[] = $remainder;
                    }
                } elseif ($batchCompletedAtOp && $readyToTransfer > 0) {
                    $chunks[] = $readyToTransfer;
                }

                if (empty($chunks)) {
                    continue;
                }

                // Get batch-and-operation-specific WIP records
                $sourceWip = $batchId
                    ? $this->getOrCreateWipForBatchOperation($sourceOp->production_order_id, $batchId, $sourceOp->routing_operation_id ?? $sourceOp->id, $userId)
                    : ProductionWip::where('tenant_id', $tenantId)->where('production_order_id', $sourceOp->production_order_id)->first();

                $destWip = $batchId
                    ? $this->getOrCreateWipForBatchOperation($sourceOp->production_order_id, $batchId, $nextOp->routing_operation_id ?? $nextOp->id, $userId)
                    : $sourceWip;

                foreach ($chunks as $chunkQty) {
                    $sourceOp->quantity_transferred_out = round((float) $sourceOp->quantity_transferred_out + $chunkQty, 4);
                    $sourceOp->save();

                    $nextOp->quantity_transferred_in = round((float) $nextOp->quantity_transferred_in + $chunkQty, 4);
                    if ($nextOp->status === ProductionOrderOperation::STATUS_WAITING) {
                        $nextOp->status = ProductionOrderOperation::STATUS_READY;
                    }
                    $nextOp->save();

                    if ($nextOp->is_external) {
                        try {
                            app(\App\Domains\Production\Services\SubcontractProcurementOrchestrator::class)->generateSubcontractRequisition($nextOp, $tenantId, $userId);
                        } catch (\Throwable $e) {
                            // Requisition fallback
                        }
                    }

                    ProductionScheduleOperation::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->where('production_order_operation_id', $nextOp->id)
                        ->where('status', ProductionScheduleOperation::STATUS_WAITING)
                        ->update(['status' => ProductionScheduleOperation::STATUS_READY]);

                    if ($destWip && $destWip->id !== $sourceWip?->id) {
                        $destWip->current_routing_operation_id = $nextOp->routing_operation_id;
                        $destWip->available_quantity = round((float) $destWip->available_quantity + $chunkQty, 4);
                        $destWip->save();
                    } elseif ($sourceWip) {
                        $sourceWip->current_routing_operation_id = $nextOp->routing_operation_id;
                        $sourceWip->available_quantity = round(max((float) $sourceWip->available_quantity, $chunkQty), 4);
                        $sourceWip->save();
                    }

                    if ($batch && $nextOp->sequence >= ($batch->currentOperation?->sequence ?? 0)) {
                        $batch->current_operation_id = $nextOp->id;
                        $batch->save();
                    }

                    ProductionWipTransaction::create([
                        'tenant_id' => $tenantId,
                        'wip_id' => $sourceWip?->id,
                        'production_order_id' => $sourceOp->production_order_id,
                        'production_batch_id' => $batchId,
                        'from_operation_id' => $sourceOp->routing_operation_id,
                        'to_operation_id' => $nextOp->routing_operation_id,
                        'from_work_center_id' => $sourceOp->work_center_id,
                        'to_work_center_id' => $nextOp->work_center_id,
                        'machine_id' => $nextOp->machine_id,
                        'operator_id' => $userId,
                        'transaction_type' => 'transferred',
                        'quantity' => $chunkQty,
                        'good_quantity' => $chunkQty,
                        'cost_before' => $sourceWip?->total_value ?? 0.0,
                        'cost_added' => 0.00,
                        'cost_after' => $sourceWip?->total_value ?? 0.0,
                        'remarks' => "Sub-batch transfer chunk of {$chunkQty} units from Op {$sourceOp->sequence} to Op {$nextOp->sequence}" . ($batch ? " for batch #{$batch->batch_number}" : "") . ".",
                        'transaction_at' => now(),
                        'created_by' => auth()->id() ?? $userId,
                    ]);

                    $totalTransferredAllBatches += $chunkQty;
                }
            }

            return $totalTransferredAllBatches;
        });
    }

    /**
     * Calculate available input WIP for downstream operations.
     */
    public function getAvailableInputWip(ProductionOrderOperation $op, ?int $batchId = null): float
    {
        // Entry-level operation in routing branch has target input based on target_produced_qty or order quantity
        $isFirstOp = !ProductionOrderOperation::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->where(function ($q) use ($op) {
                if ($op->previous_operation_id) {
                    $q->where('id', $op->previous_operation_id);
                } else {
                    $q->where('sequence', '<', $op->sequence)
                      ->where(function ($w) use ($op) {
                          if ($op->source_product_id && (int) $op->source_product_id !== (int) ($op->order?->product_id ?? 0)) {
                              $w->where('source_product_id', $op->source_product_id);
                          }
                      });
                }
            })
            ->exists();

        if ($isFirstOp) {
            if ($batchId) {
                $batch = \App\Domains\Production\Models\ProductionBatch::find($batchId);
                if ($batch) {
                    $plannedTarget = (float) $batch->planned_quantity;
                    $logProduced = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('production_batch_id', $batchId)
                        ->where('operation_id', $op->id)
                        ->sum('quantity_produced');
                    $logRejected = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('production_batch_id', $batchId)
                        ->where('operation_id', $op->id)
                        ->sum('quantity_rejected');
                    $logScrapped = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('production_batch_id', $batchId)
                        ->where('operation_id', $op->id)
                        ->sum('quantity_scrapped');
                    $disposalScrapped = (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('production_batch_id', $batchId)
                        ->where('production_order_operation_id', $op->id)
                        ->sum('quantity');

                    $scrapped = max($logScrapped, $disposalScrapped);
                    $processed = $logProduced + $logRejected + $scrapped;

                    return round(max(0.0, $plannedTarget - $processed), 4);
                }
            }

            $plannedTarget = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($op->order?->quantity_ordered ?? 0.0));
            $processed = (float) ($op->quantity_produced + $op->quantity_rejected + $op->quantity_scrapped);
            return round(max(0.0, $plannedTarget - $processed), 4);
        }

        $toOpIds = array_filter(array_unique([
            $op->id,
            $op->routing_operation_id,
            $op->routingOperation?->id,
            ...ProductionOrderOperation::where('tenant_id', $op->tenant_id)
                ->where('production_order_id', $op->production_order_id)
                ->where('sequence', $op->sequence)
                ->pluck('routing_operation_id')
                ->toArray(),
            ...ProductionOrderOperation::where('tenant_id', $op->tenant_id)
                ->where('production_order_id', $op->production_order_id)
                ->where('sequence', $op->sequence)
                ->pluck('id')
                ->toArray(),
        ]));

        $txTransferredIn = (float) ProductionWipTransaction::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
            ->where('transaction_type', 'transferred')
            ->where(function ($q) use ($toOpIds, $op) {
                $q->whereIn('to_operation_id', $toOpIds)
                  ->orWhere('remarks', 'like', "%to Op {$op->sequence}%");
            })
            ->sum('quantity');

        $transferredIn = max((float) $op->quantity_transferred_in, $txTransferredIn);
        $logProduced = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
            ->where('operation_id', $op->id)
            ->sum('quantity_produced');
        $logRejected = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
            ->where('operation_id', $op->id)
            ->sum('quantity_rejected');
        $logScrapped = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
            ->where('operation_id', $op->id)
            ->sum('quantity_scrapped');
        $disposalScrapped = (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where(fn($sub) => $sub->whereNull('production_batch_id')->orWhere('production_batch_id', $batchId)))
            ->where('production_order_operation_id', $op->id)
            ->sum('quantity');

        $scrapped = max($logScrapped, $disposalScrapped);
        $processed = $logProduced + $logRejected + $scrapped;

        return round(max(0.0, $transferredIn - $processed), 4);
    }

    /**
     * Manually adjust WIP values or quantities.
     */
    public function adjustWip(int $wipId, float $quantity, string $reason, ?int $userId = null, ?float $scrapQuantity = null, ?float $rejectedQuantity = null): void
    {
        DB::transaction(function () use ($wipId, $quantity, $reason, $userId, $scrapQuantity, $rejectedQuantity) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($wip->order->isClosed() || $wip->order->isCancelled()) {
                throw new InvalidArgumentException("Cannot adjust WIP: Parent order is closed or cancelled.");
            }

            if ($quantity < 0) {
                throw new InvalidArgumentException("WIP quantity cannot be negative.");
            }

            $oldQty = $wip->quantity;
            $oldAvailable = $wip->available_quantity;

            $wip->quantity = $quantity;

            if ($scrapQuantity !== null) {
                $wip->scrap_quantity = max(0.0000, $scrapQuantity);
            }
            if ($rejectedQuantity !== null) {
                $wip->rejected_quantity = max(0.0000, $rejectedQuantity);
            }

            $wip->available_quantity = max(0.0000, $quantity - $wip->scrap_quantity - $wip->rejected_quantity);
            $wip->updated_by = $userId;
            $wip->save();

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'from_operation_id' => $wip->current_routing_operation_id,
                'transaction_type' => 'adjusted',
                'quantity' => $quantity,
                'remarks' => "WIP adjusted from {$oldQty} to {$quantity} (Scrap: {$wip->scrap_quantity}, Rejects: {$wip->rejected_quantity}). Reason: {$reason}",
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            app(ProductionEventService::class)->writeEvent($wip->tenant_id, [
                'production_order_id' => $wip->production_order_id,
                'event_type' => 'WIP Adjusted',
                'title' => 'WIP Quantity Adjusted',
                'description' => "WIP quantity adjusted manually. Reason: {$reason}",
                'severity' => 'warning',
                'event_source' => 'ProductionWipService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Send WIP quantity to Quality Inspection status.
     */
    public function sendToQuality(int $wipId, float $quantity, ?int $userId = null): void
    {
        DB::transaction(function () use ($wipId, $quantity, $userId) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            $wip->update([
                'status' => 'quality_hold',
            ]);

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'from_operation_id' => $wip->current_routing_operation_id,
                'transaction_type' => 'sent_to_quality',
                'quantity' => $quantity,
                'remarks' => "WIP sent for quality checklist validation.",
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Process quality inspection outcome.
     */
    public function disposeInspection(int $wipId, string $result, float $qty, ?int $userId = null): void
    {
        DB::transaction(function () use ($wipId, $result, $qty, $userId) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($result === 'passed') {
                $wip->update(['status' => 'active']);

                ProductionWipTransaction::create([
                    'tenant_id' => $wip->tenant_id,
                    'wip_id' => $wip->id,
                    'production_order_id' => $wip->production_order_id,
                    'production_batch_id' => $wip->production_batch_id,
                    'transaction_type' => 'quality_approved',
                    'quantity' => $qty,
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);
            } else {
                $wip->update(['status' => 'rework']);

                ProductionWipTransaction::create([
                    'tenant_id' => $wip->tenant_id,
                    'wip_id' => $wip->id,
                    'production_order_id' => $wip->production_order_id,
                    'production_batch_id' => $wip->production_batch_id,
                    'transaction_type' => 'quality_rejected',
                    'quantity' => $qty,
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);
            }
        });
    }

    /**
     * Add material cost into the WIP calculation sheet.
     */
    public function addMaterialCost(int $orderId, float $cost): void
    {
        DB::transaction(function () use ($orderId, $cost) {
            $wip = ProductionWip::lockForUpdate()->where('production_order_id', $orderId)->first();
            if ($wip) {
                $wip->material_cost += $cost;
                $wip->total_value += $cost;
                $wip->save();
            }
        });
    }

    /**
     * Deduct material cost (returns) from the WIP calculation sheet.
     */
    public function deductMaterialCost(int $orderId, float $cost): void
    {
        DB::transaction(function () use ($orderId, $cost) {
            $wip = ProductionWip::lockForUpdate()->where('production_order_id', $orderId)->first();
            if ($wip) {
                $wip->material_cost = max(0.0000, $wip->material_cost - $cost);
                $wip->total_value = max(0.0000, $wip->total_value - $cost);
                $wip->save();
            }
        });
    }

    /**
     * Convert completed WIP into Finished Goods completion request.
     */
    public function convertWipToFinishedGoods(int $wipId, int $warehouseId, ?string $remarks = null, ?int $userId = null, string $qualityStatus = 'passed'): void
    {
        DB::transaction(function () use ($wipId, $warehouseId, $remarks, $userId, $qualityStatus) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($wip->available_quantity <= 0) {
                throw new InvalidArgumentException("Cannot convert WIP: This tracking card has no remaining available quantity.");
            }

            $qtyToComplete = (float) ($wip->completed_quantity > 0 ? $wip->completed_quantity : $wip->available_quantity);
            if ($qtyToComplete <= 0) {
                throw new InvalidArgumentException("Cannot convert WIP: This tracking card has no remaining completed or available quantity.");
            }

            // Trigger FG inflow receipt
            app(ProductionExecutionService::class)->receiveFinishedGoods(
                $wip->production_order_id,
                $qtyToComplete,
                $qualityStatus,
                $remarks ?? 'Converted from completed WIP stage.',
                $userId,
                $warehouseId,
                $wip->batch?->batch_number
            );

            // Log WIP conversion
            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'transaction_type' => 'converted_to_finished_goods',
                'quantity' => $qtyToComplete,
                'cost_before' => $wip->total_value,
                'cost_added' => 0.00,
                'cost_after' => 0.00, // Cost is moved to finished stock asset account
                'remarks' => "Completed WIP quantity of {$qtyToComplete} received into finished inventory.",
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            // Clear available quantities now that they have left the shop floor
            $wip->update([
                'quantity' => 0.0000,
                'available_quantity' => 0.0000,
                'completed_quantity' => 0.0000,
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Convert all completed WIP for an entire Production Order into Finished Goods inventory in one transaction.
     */
    public function convertOrderWipToFinishedGoods(int $orderId, int $warehouseId, ?string $remarks = null, ?int $userId = null, string $qualityStatus = 'passed'): float
    {
        return DB::transaction(function () use ($orderId, $warehouseId, $remarks, $userId, $qualityStatus) {
            $order = ProductionOrder::findOrFail($orderId);

            // Fetch all active WIP cards for this order that have completed quantity (excluding quality hold / rework)
            $wips = ProductionWip::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->whereNotIn('status', ['quality_hold', 'rework'])
                ->where(function ($q) {
                    $q->where('completed_quantity', '>', 0)
                        ->orWhere(function ($sub) {
                            $sub->where('available_quantity', '>', 0)
                                ->where('status', 'completed');
                        });
                })
                ->lockForUpdate()
                ->get();

            if ($wips->isEmpty()) {
                throw new InvalidArgumentException("No completed WIP quantities ready to transfer for Order #" . ($order->order_number ?? $orderId));
            }

            $totalConverted = 0.0;

            foreach ($wips as $wip) {
                $qtyToComplete = (float) ($wip->completed_quantity > 0 ? $wip->completed_quantity : ($wip->status === 'completed' ? $wip->available_quantity : 0.0));
                if ($qtyToComplete <= 0) {
                    continue;
                }

                // Trigger FG inflow receipt
                app(ProductionExecutionService::class)->receiveFinishedGoods(
                    $wip->production_order_id,
                    $qtyToComplete,
                    $qualityStatus,
                    $remarks ?? 'Bulk converted from order completed WIP.',
                    $userId,
                    $warehouseId,
                    $wip->batch?->batch_number
                );

                // Log WIP conversion
                ProductionWipTransaction::create([
                    'tenant_id' => $wip->tenant_id,
                    'wip_id' => $wip->id,
                    'production_order_id' => $wip->production_order_id,
                    'production_batch_id' => $wip->production_batch_id,
                    'transaction_type' => 'converted_to_finished_goods',
                    'quantity' => $qtyToComplete,
                    'cost_before' => $wip->total_value,
                    'cost_added' => 0.00,
                    'cost_after' => 0.00,
                    'remarks' => "Order Bulk Transfer: Completed WIP quantity of {$qtyToComplete} received into finished inventory.",
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);

                $remCompleted = max(0.0000, (float) $wip->completed_quantity - $qtyToComplete);
                $remAvailable = max(0.0000, (float) $wip->available_quantity - $qtyToComplete);

                $wip->update([
                    'quantity' => max(0.0000, (float) $wip->quantity - $qtyToComplete),
                    'available_quantity' => $remAvailable,
                    'completed_quantity' => $remCompleted,
                    'status' => ($remCompleted <= 0 && $remAvailable <= 0) ? 'completed' : $wip->status,
                ]);

                $totalConverted += $qtyToComplete;
            }

            return $totalConverted;
        });
    }

    /**
     * Reconcile Main Order WIP card and Batch stage WIP cards for a Production Order.
     * Ensures transferred-out upstream stage cards do not retain duplicate active quantities.
     */
    public function reconcileOrderWipCards(int $orderId): void
    {
        $order = ProductionOrder::find($orderId);
        if (!$order) {
            return;
        }

        // Align batch WIP records to their batch target product ID and consolidate duplicate batch cards
        $batchWipsByBatch = ProductionWip::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->whereNotNull('production_batch_id')
            ->get()
            ->groupBy('production_batch_id');

        foreach ($batchWipsByBatch as $bId => $cards) {
            if ($cards->count() > 1) {
                $canonical = $cards->sortByDesc(fn($c) => $c->currentRoutingOperation?->sequence ?? 0)->first();
                $duplicates = $cards->reject(fn($c) => $c->id === $canonical->id);

                $canonical->update([
                    'material_cost' => $cards->sum('material_cost'),
                    'labor_cost' => $cards->sum('labor_cost'),
                    'machine_cost' => $cards->sum('machine_cost'),
                    'overhead_cost' => $cards->sum('overhead_cost'),
                    'total_value' => $cards->sum('total_value'),
                ]);

                foreach ($duplicates as $dup) {
                    $dup->update([
                        'status' => 'transferred',
                        'available_quantity' => 0.0000,
                        'completed_quantity' => 0.0000,
                    ]);
                }
            }
        }

        ProductionWip::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->whereNotNull('production_batch_id')
            ->with('batch')
            ->get()
            ->each(function ($wip) {
                if ($wip->batch && $wip->batch->product_id && (int) $wip->product_id !== (int) $wip->batch->product_id) {
                    $wip->update(['product_id' => $wip->batch->product_id]);
                }
            });

        // Reconcile final FG operation WIP cards if final operation or entire order is completed
        $finalFgOp = ProductionOrderOperation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($order) {
                $q->where('source_product_id', $order->product_id)
                  ->orWhereNull('source_product_id');
            })
            ->where('is_intermediate', false)
            ->orderBy('sequence', 'desc')
            ->first()
            ?? ProductionOrderOperation::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->orderBy('sequence', 'desc')
            ->first();

        if ($finalFgOp) {
            $isOrderOrFinalOpCompleted = ($finalFgOp->status === ProductionOrderOperation::STATUS_COMPLETED)
                || in_array($order->status, ['completed', 'closed'])
                || !$order->operations()->whereNotIn('status', [
                    ProductionOrderOperation::STATUS_COMPLETED,
                    ProductionOrderOperation::STATUS_SKIPPED,
                    ProductionOrderOperation::STATUS_CANCELLED,
                ])->exists();

            ProductionWip::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->get()
                ->each(function ($wip) use ($finalFgOp, $order, $isOrderOrFinalOpCompleted) {
                    if ($wip->status === 'transferred') {
                        $wip->update([
                            'completed_quantity' => 0.0000,
                            'available_quantity' => 0.0000,
                        ]);
                        return;
                    }

                    if ($wip->production_batch_id === null) {
                        $sumBatchPlanned = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $order->tenant_id)
                            ->where('production_order_id', $order->id)
                            ->whereNotIn('status', ['cancelled'])
                            ->sum('planned_quantity');

                        $unallocatedQty = max(0.0000, (float) $order->quantity_ordered - (float) $sumBatchPlanned);
                        if ($unallocatedQty <= 0) {
                            $wip->update([
                                'completed_quantity' => 0.0000,
                                'available_quantity' => 0.0000,
                                'quantity' => 0.0000,
                                'status' => 'completed',
                            ]);
                            return;
                        }
                    }

                    $opSeq = $wip->currentRoutingOperation?->sequence ?? 0;
                    $isAtFinalStage = ($wip->current_routing_operation_id == $finalFgOp->routing_operation_id)
                        || ($opSeq >= $finalFgOp->sequence);

                    $isWipCompleted = ($isOrderOrFinalOpCompleted && $isAtFinalStage) || $wip->status === 'completed';

                    if ($isWipCompleted) {
                        $batchPlanned = $wip->batch ? (float) $wip->batch->planned_quantity : (float) $order->quantity_ordered;
                        $completedProduced = max((float) $wip->completed_quantity, (float) $wip->available_quantity, (float) $finalFgOp->quantity_produced, $batchPlanned);
                        $alreadyReceived = (float) $order->quantity_produced;
                        $unreceivedQty = max(0.0000, $completedProduced - $alreadyReceived);

                        $wip->update([
                            'product_id' => $order->product_id,
                            'current_routing_operation_id' => $finalFgOp->routing_operation_id ?? $wip->current_routing_operation_id,
                            'current_work_center_id' => $finalFgOp->work_center_id ?? $wip->current_work_center_id,
                            'completed_quantity' => $unreceivedQty,
                            'available_quantity' => $unreceivedQty,
                            'status' => 'completed',
                        ]);
                    }
                });
        }

        // 1. Reconcile Main Order (unbatched) WIP card
        $mainWip = ProductionWip::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->whereNull('production_batch_id')
            ->first();

        if ($mainWip) {
            $sumBatchPlanned = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->where('product_id', $order->product_id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('planned_quantity');

            $unallocatedQty = max(0.0000, (float) $order->quantity_ordered - (float) $sumBatchPlanned);
            $newStatus = ($unallocatedQty <= 0) ? 'completed' : ($mainWip->status === 'completed' ? 'active' : $mainWip->status);

            $mainWip->update([
                'available_quantity' => $unallocatedQty,
                'quantity' => $unallocatedQty,
                'status' => $newStatus,
            ]);
        }

        // 2. Reconcile Batch Stage WIP Cards
        $batches = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->get();

        foreach ($batches as $batch) {
            $batchWips = ProductionWip::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->where('production_batch_id', $batch->id)
                ->get();

            if ($batchWips->isEmpty()) {
                continue;
            }

            // Find all transferred transactions for this batch
            $transferredTxs = ProductionWipTransaction::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $orderId)
                ->where('production_batch_id', $batch->id)
                ->where('transaction_type', 'transferred')
                ->get();

            $transferredFromOpIds = $transferredTxs->pluck('from_operation_id')->filter()->unique()->toArray();

            // Find the highest sequence operation card for this batch
            $highestOpSeq = -1;
            $latestWip = null;

            foreach ($batchWips as $wip) {
                $opSeq = $wip->currentRoutingOperation?->sequence ?? 0;
                if ($opSeq >= $highestOpSeq) {
                    $highestOpSeq = $opSeq;
                    $latestWip = $wip;
                }
            }

            foreach ($batchWips as $wip) {
                $opId = $wip->current_routing_operation_id;
                $isTransferredOut = in_array($opId, $transferredFromOpIds) && ($latestWip && $wip->id !== $latestWip->id);

                if ($isTransferredOut) {
                    if ((float) $wip->available_quantity > 0.0001 || $wip->status === 'active') {
                        $wip->update([
                            'available_quantity' => 0.0000,
                            'status' => 'transferred',
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get visual Batch Pipeline data for a Production Order.
     */
    public function getBatchPipelineData(int $orderId): \Illuminate\Support\Collection
    {
        $order = ProductionOrder::with(['operations.workCenter', 'operations.routingOperation'])->find($orderId);
        if (!$order) {
            return collect();
        }

        $batches = \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $orderId)
            ->orderBy('id')
            ->get();

        $operations = $order->operations->sortBy('sequence')->values();

        return $batches->map(function ($batch) use ($order, $operations) {
            $batchProductId = $batch->product_id;
            $batchOps = $operations->filter(function ($op) use ($batchProductId, $order) {
                $opProductId = $op->source_product_id ?? $order->product_id;
                return $opProductId == $batchProductId;
            })->values();
            if ($batchOps->isEmpty()) {
                $batchOps = $operations;
            }

            $logs = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('production_batch_id', $batch->id)
                ->get();

            $scraps = \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('production_batch_id', $batch->id)
                ->get();

            $reworks = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('production_batch_id', $batch->id)
                ->get();

            $currentOpSeq = $batch->currentOperation?->sequence ?? 0;

            $stages = $batchOps->map(function ($op) use ($order, $logs, $scraps, $reworks, $batch, $currentOpSeq) {
                $opLogs = $logs->where('operation_id', $op->id);
                $produced = (float) $opLogs->sum('quantity_produced');
                $rejected = (float) $opLogs->sum('quantity_rejected');
                $scrapped = (float) $scraps->where('production_order_operation_id', $op->id)->sum('quantity');

                $reworkCompleted = (float) $reworks->whereIn('production_order_operation_id', [$op->id, $op->routing_operation_id])
                    ->where('status', 'completed')
                    ->sum('quantity');

                $reworkFailedScrapped = (float) \App\Domains\Production\Models\ProductionWipTransaction::where('tenant_id', $order->tenant_id)
                    ->where('production_order_id', $order->id)
                    ->where('production_batch_id', $batch->id)
                    ->where('from_operation_id', $op->routing_operation_id)
                    ->where('transaction_type', 'rework_failed_scrapped')
                    ->sum('quantity');

                $pendingRework = (float) $reworks->whereIn('production_order_operation_id', [$op->id, $op->routing_operation_id])
                    ->where('status', 'pending')
                    ->sum('quantity');

                $goodOutput = $produced + $reworkCompleted;
                $activeRejects = max(0.0, $pendingRework);

                $isPassed = ($op->sequence < $currentOpSeq)
                    || ($batch->status === 'completed')
                    || ($op->status === 'completed')
                    || ($batch->planned_quantity > 0 && ($goodOutput + $scrapped) >= $batch->planned_quantity);

                $isCurrent = ($batch->current_operation_id === $op->id) && !$isPassed;
                $stageStatus = $isPassed ? 'passed' : ($isCurrent ? 'active' : 'upcoming');

                $qcRequired = (bool) ($op->routingOperation?->quality_required);
                $qcInspection = \App\Domains\Production\Models\ProductionQualityInspection::where('tenant_id', $order->tenant_id)
                    ->where('production_order_id', $order->id)
                    ->where(function ($q) use ($op) {
                        $q->where('production_order_operation_id', $op->id)
                            ->orWhereNull('production_order_operation_id');
                    })
                    ->when($batch->id, function ($q) use ($batch) {
                        $q->where(fn($sub) => $sub->whereNull('batch_id')->orWhere('batch_id', $batch->id));
                    })
                    ->orderBy('id', 'desc')
                    ->first();

                $qcStatus = 'none';
                if ($qcInspection) {
                    $qcStatus = $qcInspection->result; // passed, hold, failed
                } elseif ($qcRequired) {
                    $qcStatus = 'required';
                }

                return [
                    'operation_id' => $op->id,
                    'operation_number' => $op->operation_number,
                    'name' => $op->name,
                    'work_center_name' => $op->workCenter->name ?? 'Unassigned',
                    'good_output' => $goodOutput,
                    'produced' => $produced,
                    'rework_completed' => $reworkCompleted,
                    'rework_failed_scrapped' => $reworkFailedScrapped,
                    'pending_rework' => $pendingRework,
                    'rejected' => $activeRejects,
                    'scrapped' => $scrapped,
                    'is_current' => $isCurrent,
                    'is_passed' => $isPassed,
                    'stage_status' => $stageStatus,
                    'qc_required' => $qcRequired,
                    'qc_status' => $qcStatus,
                ];
            });

            $batchProduct = \App\Domains\Inventory\Models\Product::find($batch->product_id);
            $isSfg = ((int) $order->product_id !== (int) $batch->product_id);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $batch->product_id,
                'product_name' => $batchProduct?->name ?? ($isSfg ? 'SFG Sub-Assembly' : 'FG Assembly'),
                'is_sfg' => $isSfg,
                'planned_quantity' => (float) $batch->planned_quantity,
                'actual_quantity' => (float) $batch->actual_quantity,
                'status' => $batch->status,
                'stages' => $stages,
            ];
        });
    }

    /**
     * Get Work Center WIP Summaries for an order.
     */
    public function getWorkCenterWipSummaries(int $tenantId, int $orderId, ?int $workCenterId = null): \Illuminate\Support\Collection
    {
        $this->reconcileOrderWipCards($orderId);

        $order = ProductionOrder::find($orderId);
        $finalFgOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('source_product_id', $order?->product_id)
            ->where('is_intermediate', false)
            ->orderBy('sequence', 'desc')
            ->first();

        $finalFgRoutingOpId = $finalFgOp ? (int) $finalFgOp->routing_operation_id : -1;

        $query = ProductionWip::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId);

        if ($workCenterId) {
            $query->where('current_work_center_id', $workCenterId);
        }

        $rawSummaries = $query->select('current_work_center_id')
            ->selectRaw('
                count(*) as batch_count,
                sum(available_quantity) as total_available,
                sum(case when (product_id = ? or product_id is null) and (current_routing_operation_id = ? or status = "completed" or completed_quantity > 0) then (case when completed_quantity > 0 then completed_quantity else available_quantity end) else 0 end) as total_completed,
                sum(rejected_quantity) as total_rejected,
                sum(scrap_quantity) as total_scrap,
                sum(rework_quantity) as total_rework,
                sum(case when status = "active" then available_quantity else 0 end) as total_processing,
                sum(case when status = "quality_hold" then available_quantity else 0 end) as total_hold,
                sum(total_value) as accrued_value
            ', [$order?->product_id ?? 0, $finalFgRoutingOpId ?? 0])
            ->groupBy('current_work_center_id')
            ->get();

        $workCenterIds = $rawSummaries->pluck('current_work_center_id')->filter()->toArray();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)
            ->whereIn('id', $workCenterIds)
            ->get()
            ->keyBy('id');

        return $rawSummaries->map(function ($row) use ($workCenters) {
            $wc = $workCenters->get($row->current_work_center_id);
            return [
                'work_center_id' => $row->current_work_center_id,
                'work_center_name' => $wc ? $wc->name : 'Unassigned / General Work Center',
                'work_center_code' => $wc ? $wc->code : 'GEN',
                'batch_count' => (int) $row->batch_count,
                'total_available' => (float) $row->total_available,
                'total_completed' => (float) $row->total_completed,
                'total_rejected' => (float) $row->total_rejected,
                'total_scrap' => (float) $row->total_scrap,
                'total_rework' => (float) $row->total_rework,
                'total_processing' => (float) $row->total_processing,
                'total_hold' => (float) $row->total_hold,
                'accrued_value' => (float) $row->accrued_value,
            ];
        });
    }

    /**
     * Get server-paginated WIP batches for a specific work center ordered by operational priority.
     */
    public function getPaginatedWorkCenterWips(int $tenantId, int $orderId, ?int $workCenterId, ?string $status = null, ?string $search = null, int $perPage = 5): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = ProductionWip::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->when(
                $workCenterId !== null,
                fn($q) => $q->where('current_work_center_id', $workCenterId),
                fn($q) => $q->whereNull('current_work_center_id')
            )
            ->with(['batch', 'currentRoutingOperation', 'currentWorkCenter', 'product']);

        if (!empty($status)) {
            if ($status === 'quality_hold') {
                $query->where('status', 'quality_hold');
            } elseif ($status === 'rework') {
                $query->where('status', 'rework');
            } elseif ($status === 'active' || $status === 'running') {
                $query->where('status', 'active');
            } elseif ($status === 'completed') {
                $query->where('status', 'completed');
            }
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('batch', fn($b) => $b->where('batch_number', 'like', $term))
                    ->orWhereHas('product', fn($p) => $p->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            });
        }

        // Priority ordering: 1. quality_hold, 2. rework, 3. active, 4. completed, 5. others
        $query->orderByRaw('
            CASE
                WHEN status = "quality_hold" THEN 1
                WHEN status = "rework" THEN 2
                WHEN status = "active" AND available_quantity > 0 THEN 3
                WHEN completed_quantity > 0 THEN 4
                ELSE 5
            END ASC, id DESC
        ');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get server-paginated production orders with aggregate WIP metrics.
     */
    public function getConsolidatedOrderWipSummaries(int $tenantId, ?string $search = null, ?string $status = null, int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['released', 'in_progress', 'completed'])
            ->with(['product']);

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                    ->orWhereHas('product', fn($p) => $p->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            });
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
    }

    /**
     * Record physical SFG consumption when a parent consuming operation logs progress.
     * (Rule 7, Rule 8, Rule 9 & Rule 12)
     */
    public function recordSfgConsumption(
        ProductionOrderOperation $parentOp,
        float $parentProgressDelta,
        ?int $userId = null,
        ?int $parentBatchId = null,
        ?int $childBatchId = null
    ): void {
        if ($parentProgressDelta <= 0) {
            return;
        }

        $parentOp->loadMissing('predecessorDependencies');
        $crossPreds = $parentOp->predecessorDependencies()->wherePivot('dependency_type', 'cross_assembly')->get();
        if ($crossPreds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($parentOp, $parentProgressDelta, $userId, $parentBatchId, $childBatchId, $crossPreds) {
            $tenantId = $parentOp->tenant_id;
            $order = $parentOp->order;

            foreach ($crossPreds as $predOp) {
                $childProductId = $predOp->source_product_id;
                if (!$childProductId) {
                    continue;
                }

                // BOM ratio
                $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $tenantId)
                    ->where('bom_id', $parentOp->source_bom_id ?? $order->bom_id)
                    ->where('material_id', $childProductId)
                    ->first();
                $bomRatio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;

                $requiredSfgQty = $parentProgressDelta * $bomRatio;

                // Lock predecessor operation row
                $predOpLocked = ProductionOrderOperation::lockForUpdate()->find($predOp->id);
                if ($predOpLocked) {
                    $predOpLocked->increment('quantity_consumed', $requiredSfgQty);
                }

                $predWip = ProductionWip::where('tenant_id', $tenantId)
                    ->where('production_order_id', $order->id)
                    ->where('current_routing_operation_id', $predOp->routing_operation_id)
                    ->first();
                if (!$predWip) {
                    $predWip = $this->initializeWip($order->id, null, $userId);
                }

                // Record WIP transaction with 0 cost_added (Rule 12: Cost Double-Count Protection)
                ProductionWipTransaction::create([
                    'tenant_id' => $tenantId,
                    'wip_id' => $predWip->id,
                    'production_order_id' => $order->id,
                    'production_batch_id' => $predWip->production_batch_id,
                    'from_operation_id' => $predOp->routing_operation_id,
                    'to_operation_id' => $parentOp->routing_operation_id,
                    'from_work_center_id' => $predOp->work_center_id,
                    'to_work_center_id' => $parentOp->work_center_id,
                    'operator_id' => $userId,
                    'transaction_type' => 'sfg_consumed',
                    'quantity' => $requiredSfgQty,
                    'good_quantity' => $requiredSfgQty,
                    'cost_before' => 0.0000,
                    'cost_added' => 0.0000,
                    'cost_after' => 0.0000,
                    'remarks' => "SFG consumed: {$requiredSfgQty} units from operation {$predOp->operation_number} for parent operation {$parentOp->operation_number} (Delta: {$parentProgressDelta}, BOM Ratio: {$bomRatio}).",
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);

                // Record genealogy linkage if BatchProductionService is available (F-03)
                try {
                    $resolvedChildBatchId = $childBatchId ?? $predWip->production_batch_id;
                    app(BatchProductionService::class)->recordComponentConsumptionGenealogy(
                        $order,
                        $parentOp,
                        $predOpLocked,
                        $requiredSfgQty,
                        $userId,
                        $parentBatchId,
                        $resolvedChildBatchId
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Genealogy record error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    throw $e;
                }
            }
        });
    }
}
