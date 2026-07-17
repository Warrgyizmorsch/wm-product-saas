<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionWipService
{
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

            // Check if WIP already exists for this order/batch combination
            $existing = ProductionWip::where('production_order_id', $orderId)
                ->when($batchId, fn($q) => $q->where('production_batch_id', $batchId))
                ->first();

            if ($existing) {
                return $existing;
            }

            $wip = ProductionWip::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'production_batch_id' => $batchId,
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
                'production_batch_id' => $batchId,
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

            app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'WIP Created',
                'title' => 'WIP Track Initialized',
                'description' => "WIP record created for order #{$order->order_number}.",
                'severity' => 'info',
                'event_source' => 'ProductionWipService',
                'triggered_by' => $userId,
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
        int     $wipId,
        int     $orderOpId,
        float   $goodQty,
        float   $rejectedQty,
        float   $scrapQty,
        float   $setupMins,
        float   $runMins,
        ?string $remarks = null,
        ?int    $userId  = null
    ): void {
        DB::transaction(function () use (
            $wipId, $orderOpId, $goodQty, $rejectedQty, $scrapQty,
            $setupMins, $runMins, $remarks, $userId
        ) {
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

            // Update quantity states
            $wip->completed_quantity += $goodQty;
            $wip->rejected_quantity += $rejectedQty;
            $wip->scrap_quantity += $scrapQty;
            
            // Adjust available balance
            $wip->available_quantity = max(0.0000, $wip->available_quantity - $scrapQty);

            // If this was the last routing operation and the operation is completed, transition WIP to completed status
            $nextOpExists = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where('sequence', '>', $orderOp->sequence)
                ->exists();

            if (!$nextOpExists && $orderOp->status === ProductionOrderOperation::STATUS_COMPLETED) {
                $wip->status = 'completed';
                $wip->completed_at = now();
            }

            $wip->updated_by = $userId;
            $wip->save();

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
                'transaction_type' => 'operation_completed',
                'quantity' => $goodQty,
                'good_quantity' => $goodQty,
                'rejected_quantity' => $rejectedQty,
                'scrap_quantity' => $scrapQty,
                'cost_before' => $costBefore,
                'cost_added' => $totalCostAdded,
                'cost_after' => $wip->total_value,
                'remarks' => $remarks ?? 'Progress completed on operation.',
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
    public function transferWip(int $wipId, ?int $fromOpId, ?int $toOpId, float $quantity, ?string $remarks = null, ?int $userId = null): void
    {
        if ($fromOpId === null || $toOpId === null) {
            return;
        }

        DB::transaction(function () use ($wipId, $fromOpId, $toOpId, $quantity, $remarks, $userId) {
            $wip = ProductionWip::lockForUpdate()->findOrFail($wipId);

            if ($wip->order->isClosed() || $wip->order->isCancelled()) {
                throw new InvalidArgumentException("Cannot transfer WIP: Parent order is closed or cancelled.");
            }

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Transfer quantity must be greater than zero.");
            }

            if ($quantity > $wip->available_quantity) {
                throw new InvalidArgumentException("Transfer quantity ({$quantity}) exceeds available WIP quantity ({$wip->available_quantity}).");
            }

            $toOrderOp = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where('routing_operation_id', $toOpId)
                ->first();

            if (!$toOrderOp) {
                throw new InvalidArgumentException("Destination routing operation is not configured for this Production Order.");
            }

            // Enforce sequential movement (cannot skip forward operations)
            $fromOrderOp = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                ->where('routing_operation_id', $fromOpId)
                ->first();

            if ($fromOrderOp && $toOrderOp->sequence > $fromOrderOp->sequence) {
                $nextSequence = ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                    ->where('sequence', '>', $fromOrderOp->sequence)
                    ->min('sequence');

                if ($toOrderOp->sequence !== $nextSequence) {
                    throw new InvalidArgumentException("WIP cannot skip routing operations. Next allowed stage sequence is {$nextSequence}.");
                }
            }

            $fromOp = RoutingOperation::find($fromOpId);
            $toOp = RoutingOperation::find($toOpId);

            $wip->update([
                'current_routing_operation_id' => $toOpId,
                'current_work_center_id' => $toOrderOp->work_center_id,
                'current_machine_id' => $toOrderOp->machine_id ?? $wip->current_machine_id,
                'last_moved_at' => now(),
            ]);

            ProductionWipTransaction::create([
                'tenant_id' => $wip->tenant_id,
                'wip_id' => $wip->id,
                'production_order_id' => $wip->production_order_id,
                'production_batch_id' => $wip->production_batch_id,
                'from_operation_id' => $fromOpId,
                'to_operation_id' => $toOpId,
                'from_work_center_id' => $fromOp ? $fromOp->work_center_id : null,
                'to_work_center_id' => $toOrderOp->work_center_id,
                'machine_id' => $toOrderOp->machine_id,
                'operator_id' => $userId,
                'transaction_type' => 'transferred',
                'quantity' => $quantity,
                'cost_before' => $wip->total_value,
                'cost_added' => 0.00,
                'cost_after' => $wip->total_value,
                'remarks' => $remarks ?? "WIP transferred from " . ($fromOp?->name ?? 'OP') . " to " . $toOp->name,
                'transaction_at' => now(),
                'created_by' => $userId,
            ]);

            app(ProductionEventService::class)->writeEvent($wip->tenant_id, [
                'production_order_id' => $wip->production_order_id,
                'event_type' => 'WIP Transferred',
                'title' => 'WIP Transferred Step',
                'description' => "Transferred {$quantity} units to routing operation step '{$toOp->name}'.",
                'severity' => 'info',
                'event_source' => 'ProductionWipService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Manually adjust WIP values or quantities.
     */
    public function adjustWip(int $wipId, float $quantity, string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($wipId, $quantity, $reason, $userId) {
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
            $wip->available_quantity = max(0.0000, $quantity - $wip->scrap_quantity);
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
                'remarks' => "WIP adjusted from {$oldQty} to {$quantity}. Reason: {$reason}",
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

            if ($wip->status === 'completed' || $wip->available_quantity <= 0) {
                throw new InvalidArgumentException("Cannot convert WIP: This tracking card has already been completed or has no remaining available quantity.");
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
