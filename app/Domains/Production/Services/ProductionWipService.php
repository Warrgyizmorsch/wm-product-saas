<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
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
            $plannedQty = $batch ? (float) $batch->planned_quantity : (float) $order->quantity_ordered;

            $wip = ProductionWip::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'production_batch_id' => $validBatchId,
                'product_id' => $order->product_id,
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

            return $wip;
        });
    }

    /**
     * Initialize a WIP record for a released Production Order.
     */
    public function initializeWip(int $orderId, ?int $batchId = null, ?int $userId = null): ProductionWip
    {
        return DB::transaction(function () use ($orderId, $batchId, $userId) {
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

            $wip = ProductionWip::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'production_batch_id' => null,
                'product_id' => $order->product_id,
                'current_routing_operation_id' => $firstOp->routing_operation_id,
                'current_schedule_operation_id' => null,
                'current_work_center_id' => $firstOp->work_center_id,
                'current_machine_id' => $firstOp->machine_id,
                'quantity' => $order->quantity_ordered,
                'available_quantity' => $order->quantity_ordered,
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
                'quantity' => $order->quantity_ordered,
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
                'quantity' => $wip->available_quantity,
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

            // If this was the last routing operation and the operation is completed, transition WIP to completed status
            $nextOpExists = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where('sequence', '>', $orderOp->sequence)
                ->exists();

            // Update quantity states
            if (!$nextOpExists) {
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
                    $q->where('routing_operation_id', $toOpId)
                        ->orWhere('id', $toOpId);
                })
                ->first();

            if (!$toOrderOp) {
                throw new InvalidArgumentException("Destination routing operation is not configured for this Production Order.");
            }

            $fromOrderOp = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where(function ($q) use ($fromOpId) {
                    $q->where('routing_operation_id', $fromOpId)
                        ->orWhere('id', $fromOpId);
                })
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
                $qhQuery->where('batch_id', $targetBatchId);
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

            if ($hasQualityHold || $hasPendingRework) {
                $batchLabel = $batch ? "Batch #{$batch->batch_number}" : "Order #{$wip->production_order_id}";
                throw new InvalidArgumentException("{$batchLabel} has an active quality hold or pending rework and cannot be transferred.");
            }

            // Calculate actual ready-to-transfer balance
            $logQuery = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('operation_id', [$fromOrderOp->id, $fromOrderOp->routing_operation_id]);
            if ($wip->production_batch_id) {
                $logQuery->where('production_batch_id', $wip->production_batch_id);
            }
            $logQty = (float) $logQuery->sum('quantity_produced');
            $txQty = (float) ProductionWipTransaction::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('from_operation_id', array_filter([$fromOpId, $fromOrderOp?->routing_operation_id, $fromOrderOp?->id]))
                ->whereIn('transaction_type', ['operation_completed', 'progress_logged', 'rework_completed'])
                ->when($wip->production_batch_id, fn($q) => $q->where('production_batch_id', $wip->production_batch_id))
                ->sum('quantity');
            $goodOutput = max($logQty, $txQty, $wip->production_batch_id ? 0.0 : (float) ($fromOrderOp?->quantity_produced ?? 0));

            $scrapQuery = \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $wip->tenant_id)
                ->where('production_order_id', $wip->production_order_id)
                ->whereIn('production_order_operation_id', [$fromOrderOp->id, $fromOrderOp->routing_operation_id]);
            if ($wip->production_batch_id) {
                $scrapQuery->where('production_batch_id', $wip->production_batch_id);
            }
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
            $tenantId = require_tenant_id();
            $sourceOp = ProductionOrderOperation::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($sourceOrderOpId);

            $order = $sourceOp->order;
            if (!$order || $order->isClosed() || $order->isCancelled()) {
                return 0.0;
            }

            // Find immediate successor operation respecting sequence
            $nextOp = ProductionOrderOperation::where('tenant_id', $sourceOp->tenant_id)
                ->where('production_order_id', $sourceOp->production_order_id)
                ->where('sequence', '>', $sourceOp->sequence)
                ->orderBy('sequence', 'asc')
                ->lockForUpdate()
                ->first();

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
                    ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
                    ->where('production_order_operation_id', $sourceOp->id)
                    ->whereIn('status', ['hold', 'failed'])
                    ->exists();

                $hasPendingRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('production_order_operation_id', [$sourceOp->id, $sourceOp->routing_operation_id])
                    ->where('status', 'pending')
                    ->exists();

                if ($hasQualityHold || $hasPendingRework) {
                    continue; // Exclude blocked or pending rework quantity from transferable calculation
                }

                $logQty = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->where('operation_id', $sourceOp->id)
                    ->sum('quantity_produced');

                $txQty = (float) ProductionWipTransaction::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('from_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->whereIn('transaction_type', ['operation_completed', 'progress_logged', 'rework_completed'])
                    ->sum('quantity');

                $goodOutput = max($logQty, $txQty, $batchId ? 0.0 : (float) ($sourceOp->quantity_produced ?? 0))
                    - (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->where('production_order_operation_id', $sourceOp->id)
                        ->sum('quantity');

                $alreadyTransferred = (float) ProductionWipTransaction::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->whereIn('from_operation_id', array_filter([$sourceOp->routing_operation_id, $sourceOp->id]))
                    ->where('transaction_type', 'transferred')
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
                        ->where('to_operation_id', $sourceOp->routing_operation_id)
                        ->where('transaction_type', 'transferred')
                        ->sum('quantity');
                }

                $processedAtCurrentOp = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOp->production_order_id)
                    ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                    ->where('operation_id', $sourceOp->id)
                    ->sum('quantity_produced')
                    + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->where('operation_id', $sourceOp->id)
                        ->sum('quantity_rejected')
                    + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                        ->where('production_order_id', $sourceOp->production_order_id)
                        ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                        ->where('operation_id', $sourceOp->id)
                        ->sum('quantity_scrapped');

                $batchCompletedAtOp = ($expectedInput > 0 && $processedAtCurrentOp >= $expectedInput)
                    || ($goodOutput >= $plannedQty && $plannedQty > 0)
                    || ($sourceOp->status === ProductionOrderOperation::STATUS_COMPLETED);

                // Transfer Chunk Determination
                $transferBatchQty = (float) ($sourceOp->transfer_batch_quantity > 0
                    ? $sourceOp->transfer_batch_quantity
                    : ($sourceOp->routingOperation?->transfer_batch_quantity ?? 0));

                $batchSize = ($sourceOp->overlap_enabled || $transferBatchQty > 0)
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
                    ? $this->getOrCreateWipForBatchOperation($sourceOp->production_order_id, $batchId, $sourceOp->routing_operation_id, $userId)
                    : ProductionWip::where('tenant_id', $tenantId)->where('production_order_id', $sourceOp->production_order_id)->first();

                $destWip = $batchId
                    ? $this->getOrCreateWipForBatchOperation($sourceOp->production_order_id, $batchId, $nextOp->routing_operation_id, $userId)
                    : $sourceWip;

                foreach ($chunks as $chunkQty) {
                    $sourceOp->quantity_transferred_out = round((float) $sourceOp->quantity_transferred_out + $chunkQty, 4);
                    $sourceOp->save();

                    $nextOp->quantity_transferred_in = round((float) $nextOp->quantity_transferred_in + $chunkQty, 4);
                    if ($nextOp->status === ProductionOrderOperation::STATUS_WAITING) {
                        $nextOp->status = ProductionOrderOperation::STATUS_READY;
                    }
                    $nextOp->save();

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
        // First operation in routing has target input based on planned batch or order quantity
        $isFirstOp = !ProductionOrderOperation::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->where('sequence', '<', $op->sequence)
            ->exists();

        if ($isFirstOp) {
            if ($batchId) {
                $batch = \App\Domains\Production\Models\ProductionBatch::find($batchId);
                if ($batch) {
                    $plannedTarget = (float) $batch->planned_quantity;
                    $processed = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                        ->where('production_order_id', $op->production_order_id)
                        ->where('production_batch_id', $batchId)
                        ->where('operation_id', $op->id)
                        ->sum('quantity_produced')
                        + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                            ->where('production_order_id', $op->production_order_id)
                            ->where('production_batch_id', $batchId)
                            ->where('operation_id', $op->id)
                            ->sum('quantity_rejected');

                    return round(max(0.0, $plannedTarget - $processed), 4);
                }
            }

            $plannedTarget = (float) ($op->order?->quantity_ordered ?? 0.0);
            $processed = (float) ($op->quantity_produced + $op->quantity_rejected);
            return round(max(0.0, $plannedTarget - $processed), 4);
        }

        $txTransferredIn = (float) ProductionWipTransaction::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
            ->whereIn('to_operation_id', array_filter([$op->routing_operation_id, $op->id]))
            ->where('transaction_type', 'transferred')
            ->sum('quantity');

        $transferredIn = max((float) ($batchId ? 0.0 : $op->quantity_transferred_in), $txTransferredIn);
        $processed = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
            ->where('operation_id', $op->id)
            ->sum('quantity_produced')
            + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                ->where('production_order_id', $op->production_order_id)
                ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                ->where('operation_id', $op->id)
                ->sum('quantity_rejected')
            + (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $op->tenant_id)
                ->where('production_order_id', $op->production_order_id)
                ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                ->where('operation_id', $op->id)
                ->sum('quantity_scrapped');

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
    public function convertWipToFinishedGoods(int $wipId, int $warehouseId, ?string $remarks = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($wipId, $warehouseId, $remarks, $userId) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($wip->available_quantity <= 0) {
                throw new InvalidArgumentException("Cannot convert WIP: This tracking card has no remaining available quantity.");
            }

            $qtyToComplete = $wip->available_quantity;

            // Trigger FG inflow receipt
            app(ProductionExecutionService::class)->receiveFinishedGoods(
                $wip->production_order_id,
                $qtyToComplete,
                'passed',
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
                'status' => 'completed',
            ]);
        });
    }
}
