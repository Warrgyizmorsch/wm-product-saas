<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionOrderRework;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionExecutionService
{
    /**
     * Log shop floor execution progress against a specific operation.
     */
    public function logProgress(
        int $operationId,
        float $produced,
        float $rejected,
        float $scrapped,
        float $setupMinutes,
        float $runMinutes,
        ?string $remarks = null,
        ?int $machineId = null,
        ?int $userId = null,
        bool $completeOperation = false
    ): ProductionOrderProgressLog {
        return DB::transaction(function () use (
            $operationId, $produced, $rejected, $scrapped, 
            $setupMinutes, $runMinutes, $remarks, $machineId, $userId, $completeOperation
        ) {
            $op = ProductionOrderOperation::findOrFail($operationId);
            $order = $op->order;

            // 1. Enforce Order state validity
            if ($order->isClosed() || $order->isCompleted() || $order->isCancelled()) {
                throw new InvalidArgumentException("Cannot log progress on a closed, completed, or cancelled order.");
            }

            // 2. Create the progress log entry
            $log = ProductionOrderProgressLog::create([
                'tenant_id'           => $op->tenant_id,
                'production_order_id' => $op->production_order_id,
                'operation_id'        => $op->id,
                'quantity_produced'   => $produced,
                'quantity_rejected'   => $rejected,
                'quantity_scrapped'   => $scrapped,
                'setup_minutes_logged'=> $setupMinutes,
                'run_minutes_logged'  => $runMinutes,
                'recorded_by'         => $userId,
                'recorded_at'         => now(),
                'machine_id'          => $machineId ?? $op->machine_id,
                'remarks'             => $remarks,
            ]);

            // 3. Update Operation metrics
            $op->setup_time_actual += $setupMinutes;
            $op->processing_time_actual += $runMinutes;
            $op->quantity_produced += $produced;
            $op->quantity_rejected += $rejected;
            $op->quantity_scrapped += $scrapped;

            // Update operation status
            if ($completeOperation) {
                $op->status = ProductionOrderOperation::STATUS_COMPLETED;
                $op->actual_end_time = now();

                // Make next operation in sequence "Ready"
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

            // 4. Update Parent Order state
            if ($order->isReleased()) {
                $order->status = ProductionOrder::STATUS_IN_PROGRESS;
                $order->actual_start_date = now();
                $order->save();
            }

            return $log;
        });
    }

    /**
     * Log a dedicated scrap event against the order.
     */
    public function logScrap(
        int $orderId,
        ?int $operationId,
        ?int $productId,
        float $quantity,
        ?string $reason = null,
        ?int $userId = null
    ): ProductionOrderScrap {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Scrap quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($orderId, $operationId, $productId, $quantity, $reason, $userId) {
            $order = ProductionOrder::findOrFail($orderId);

            $scrap = ProductionOrderScrap::create([
                'tenant_id'                      => $order->tenant_id,
                'production_order_id'            => $order->id,
                'production_order_operation_id'  => $operationId,
                'product_id'                     => $productId,
                'quantity'                       => $quantity,
                'reason'                         => $reason,
                'recorded_by'                    => $userId,
                'recorded_at'                    => now(),
            ]);

            if ($productId === null || $productId === $order->product_id) {
                // Scrapping parent finished goods product
                $order->quantity_scrapped += $quantity;
                $order->save();
            }

            return $scrap;
        });
    }

    /**
     * Log a dedicated rework event loop against the order.
     */
    public function logRework(
        int $orderId,
        ?int $operationId,
        float $quantity,
        ?string $reason = null,
        ?int $userId = null
    ): ProductionOrderRework {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Rework quantity must be greater than zero.");
        }

        $order = ProductionOrder::findOrFail($orderId);

        return ProductionOrderRework::create([
            'tenant_id'                      => $order->tenant_id,
            'production_order_id'            => $order->id,
            'production_order_operation_id'  => $operationId,
            'quantity'                       => $quantity,
            'reason'                         => $reason,
            'status'                         => 'pending',
            'recorded_by'                    => $userId,
            'recorded_at'                    => now(),
        ]);
    }

    /**
     * Complete pending rework status loops.
     */
    public function completeRework(int $reworkId): void
    {
        $rework = ProductionOrderRework::findOrFail($reworkId);
        $rework->status = 'completed';
        $rework->save();
    }

    /**
     * Receive Finished Goods inventory from shop floor execution.
     */
    public function receiveFinishedGoods(
        int $orderId,
        float $quantity,
        string $qualityStatus = 'passed',
        ?string $remarks = null,
        ?int $userId = null
    ): ProductionOrderReceipt {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Receipt quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($orderId, $quantity, $qualityStatus, $remarks, $userId) {
            $order = ProductionOrder::findOrFail($orderId);

            if ($order->isClosed() || $order->isCancelled()) {
                throw new InvalidArgumentException("Cannot receive finished goods on a closed or cancelled order.");
            }

            $receipt = ProductionOrderReceipt::create([
                'tenant_id'           => $order->tenant_id,
                'production_order_id' => $order->id,
                'product_id'          => $order->product_id,
                'quantity_received'   => $quantity,
                'quality_status'      => $qualityStatus,
                'received_by'         => $userId,
                'received_at'         => now(),
                'remarks'             => $remarks,
            ]);

            // Increment quantity_produced
            $order->quantity_produced += $quantity;
            $order->save();

            return $receipt;
        });
    }
}
