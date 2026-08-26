<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBatchGenealogy;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use Illuminate\Support\Facades\DB;

class BatchProductionService
{
    public function __construct(
        private readonly BatchNumberService $batchNumberService
    ) {
    }

    /**
     * Create a new production batch.
     */
    public function createBatch(
        int $tenantId,
        int $orderId,
        int $productId,
        float $plannedQty,
        string $status = ProductionBatch::STATUS_PLANNED,
        ?string $expiryDate = null,
        ?string $remarks = null
    ): ProductionBatch {
        return DB::transaction(function () use ($tenantId, $orderId, $productId, $plannedQty, $status, $expiryDate, $remarks) {
            $order = \App\Domains\Production\Models\ProductionOrder::find($orderId);
            if ($order && $productId === $order->product_id) {
                $sumActivePlanned = ProductionBatch::where('tenant_id', $tenantId)
                    ->where('production_order_id', $orderId)
                    ->where('product_id', $productId)
                    ->whereNotIn('status', [ProductionBatch::STATUS_CANCELLED])
                    ->sum('planned_quantity');

                $unallocatedOrderQty = max(0.0, (float) $order->quantity_ordered - (float) $sumActivePlanned);
                if ($plannedQty > $unallocatedOrderQty && $unallocatedOrderQty > 0) {
                    throw new \InvalidArgumentException("Planned batch quantity (" . number_format($plannedQty, 2) . ") cannot exceed unallocated order capacity (" . number_format($unallocatedOrderQty, 2) . ").");
                }
            }

            $batchNumber = $this->batchNumberService->generateNextNumber($tenantId, $order ?? $orderId, $productId);

            $batch = ProductionBatch::create([
                'tenant_id' => $tenantId,
                'batch_number' => $batchNumber,
                'production_order_id' => $orderId,
                'product_id' => $productId,
                'planned_quantity' => $plannedQty,
                'actual_quantity' => 0.00,
                'expiry_date' => $expiryDate,
                'status' => $status,
                'remarks' => $remarks,
            ]);

            // Log trace from Order to Batch
            ProductionLotTrace::create([
                'tenant_id' => $tenantId,
                'source_type' => 'order',
                'source_id' => $orderId,
                'target_type' => 'batch',
                'target_id' => $batch->id,
                'quantity' => $plannedQty,
                'remarks' => 'Batch created from order.',
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_batch_id' => $batch->id,
                'event_type' => 'Batch Created',
                'title' => 'Production Batch Created',
                'description' => "Batch {$batch->batch_number} has been created.",
                'severity' => 'info',
                'event_source' => 'BatchProductionService',
            ]);

            return $batch;
        });
    }

    /**
     * Split a batch into multiple child batches.
     */
    public function splitBatch(int $tenantId, int $parentBatchId, array $splits): array
    {
        return DB::transaction(function () use ($tenantId, $parentBatchId, $splits) {
            $parent = ProductionBatch::findOrFail($parentBatchId);
            $children = [];
            $totalSplitQty = 0.0;

            foreach ($splits as $split) {
                $totalSplitQty += (float) $split['planned_quantity'];
            }

            if ($totalSplitQty <= 0) {
                throw new \InvalidArgumentException("Total split quantity must be greater than zero.");
            }

            if ($totalSplitQty > $parent->planned_quantity) {
                throw new \InvalidArgumentException("Total split quantity (" . number_format($totalSplitQty, 2) . ") cannot exceed parent batch planned quantity (" . number_format($parent->planned_quantity, 2) . ").");
            }

            $remainingBalance = max(0.00, (float) $parent->planned_quantity - $totalSplitQty);
            $parent->update([
                'planned_quantity' => $remainingBalance,
                'remarks' => $parent->remarks . " | Split {$totalSplitQty} quantity into children.",
            ]);

            $children = [];
            foreach ($splits as $split) {
                $qty = (float) $split['planned_quantity'];
                $child = $this->createBatch(
                    $tenantId,
                    $parent->production_order_id,
                    $parent->product_id,
                    $qty,
                    ProductionBatch::STATUS_PLANNED,
                    $split['expiry_date'] ?? ($parent->expiry_date ? $parent->expiry_date->toDateString() : null),
                    $split['remarks'] ?? "Split from parent batch #{$parent->batch_number}"
                );
                $child->current_operation_id = $parent->current_operation_id;
                $child->source_operation_id = $parent->source_operation_id;
                $child->save();

                ProductionBatchGenealogy::create([
                    'tenant_id' => $tenantId,
                    'parent_batch_id' => $parent->id,
                    'child_batch_id' => $child->id,
                    'type' => 'split',
                    'quantity' => $qty,
                ]);

                ProductionLotTrace::create([
                    'tenant_id' => $tenantId,
                    'source_type' => 'batch',
                    'source_id' => $parent->id,
                    'target_type' => 'batch',
                    'target_id' => $child->id,
                    'quantity' => $qty,
                    'remarks' => "Split trace from parent batch {$parent->batch_number}.",
                ]);

                $children[] = $child;
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $parent->production_order_id,
                'production_batch_id' => $parent->id,
                'event_type' => 'Batch Split',
                'title' => 'Batch Split Completed',
                'description' => "Batch {$parent->batch_number} has been split into child batches.",
                'severity' => 'info',
                'event_source' => 'BatchProductionService',
            ]);

            return $children;
        });
    }

    /**
     * Merge multiple batches into a single child batch.
     */
    public function mergeBatches(int $tenantId, array $parentBatchIds, float $targetPlannedQty, ?string $remarks = null): ProductionBatch
    {
        return DB::transaction(function () use ($tenantId, $parentBatchIds, $targetPlannedQty, $remarks) {
            if (empty($parentBatchIds)) {
                throw new \InvalidArgumentException("No parent batches specified for merging.");
            }

            $parents = ProductionBatch::whereIn('id', $parentBatchIds)->get();

            // Validate same product & check maximum total quantity
            $productId = $parents->first()->product_id;
            $orderId = $parents->first()->production_order_id;
            $sumPlannedQty = (float) $parents->sum('planned_quantity');

            foreach ($parents as $parent) {
                if ($parent->product_id !== $productId) {
                    throw new \LogicException("Cannot merge batches with different products.");
                }
            }

            if ($targetPlannedQty > $sumPlannedQty) {
                throw new \InvalidArgumentException("Target merged quantity (" . number_format($targetPlannedQty, 2) . ") cannot exceed the combined quantity of selected source batches (" . number_format($sumPlannedQty, 2) . ").");
            }

            // Create target merged batch
            $child = $this->createBatch(
                $tenantId,
                $orderId,
                $productId,
                $targetPlannedQty,
                ProductionBatch::STATUS_PLANNED,
                null,
                $remarks ?? "Merged from batches: " . $parents->pluck('batch_number')->implode(', ')
            );

            // Log each parent link
            foreach ($parents as $parent) {
                // Link genealogy
                ProductionBatchGenealogy::create([
                    'tenant_id' => $tenantId,
                    'parent_batch_id' => $parent->id,
                    'child_batch_id' => $child->id,
                    'type' => 'merge',
                    'quantity' => $parent->planned_quantity,
                ]);

                // Trace log
                ProductionLotTrace::create([
                    'tenant_id' => $tenantId,
                    'source_type' => 'batch',
                    'source_id' => $parent->id,
                    'target_type' => 'batch',
                    'target_id' => $child->id,
                    'quantity' => $parent->planned_quantity,
                    'remarks' => "Merge trace into batch {$child->batch_number}.",
                ]);

                // Consume/Complete parents
                $parent->update([
                    'status' => ProductionBatch::STATUS_CONSUMED,
                ]);
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_batch_id' => $child->id,
                'event_type' => 'Batch Merge',
                'title' => 'Batches Merged',
                'description' => "Multiple batches merged into target batch {$child->batch_number}.",
                'severity' => 'info',
                'event_source' => 'BatchProductionService',
            ]);

            return $child;
        });
    }

    /**
     * Cancel an unprocessed planned batch.
     */
    public function cancelBatch(int $tenantId, int $batchId, ?string $reason = null): ProductionBatch
    {
        return DB::transaction(function () use ($tenantId, $batchId, $reason) {
            $batch = ProductionBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

            if ($batch->status === ProductionBatch::STATUS_CANCELLED) {
                return $batch;
            }

            // Verify batch has not started processing
            $hasProgress = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->exists();

            $hasWipTx = \App\Domains\Production\Models\ProductionWipTransaction::where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->exists();

            if ($hasProgress || $hasWipTx || (float) $batch->actual_quantity > 0) {
                throw new \InvalidArgumentException("Batch #{$batch->batch_number} has already started production or has recorded progress and cannot be cancelled.");
            }

            $batch->update([
                'status' => ProductionBatch::STATUS_CANCELLED,
                'remarks' => trim(($batch->remarks ?? '') . ' | Cancelled: ' . ($reason ?? 'User cancelled unprocessed batch.')),
            ]);

            // Remove associated empty WIP records created for this batch
            \App\Domains\Production\Models\ProductionWip::where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->delete();

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $batch->production_order_id,
                'production_batch_id' => $batch->id,
                'event_type' => 'Batch Cancelled',
                'title' => 'Batch Cancelled',
                'description' => "Batch {$batch->batch_number} of planned qty {$batch->planned_quantity} was cancelled.",
                'severity' => 'warning',
                'event_source' => 'BatchProductionService',
            ]);

            return $batch;
        });
    }

    /**
     * Deterministically resolve or create a Production Batch for operation execution progress.
     */
    public function resolveBatchForProgress(
        \App\Domains\Production\Models\ProductionOrder $order,
        \App\Domains\Production\Models\ProductionOrderOperation $operation,
        ?int $selectedBatchId,
        float $quantityProduced
    ): ProductionBatch {
        return DB::transaction(function () use ($order, $operation, $selectedBatchId, $quantityProduced) {
            $tenantId = $order->tenant_id;

            // 1. Explicit selection provided by user/client request
            if ($selectedBatchId) {
                $batch = ProductionBatch::where('tenant_id', $tenantId)
                    ->where('production_order_id', $order->id)
                    ->lockForUpdate()
                    ->find($selectedBatchId);

                if (!$batch) {
                    throw new \InvalidArgumentException("Specified production batch #{$selectedBatchId} not found for this order.");
                }

                if ($batch->status === ProductionBatch::STATUS_CANCELLED) {
                    throw new \InvalidArgumentException("Cannot log progress against cancelled batch #{$batch->batch_number}.");
                }

                return $batch;
            }

            // 2. Check if this is the initial operation step in sequence
            $isFirstOp = !\App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->where('sequence', '<', $operation->sequence)
                ->exists();

            if ($isFirstOp) {
                // Initial operation: Resolve by batch-specific progress logged at initial op vs planned quantity
                $activeBatches = ProductionBatch::where('tenant_id', $tenantId)
                    ->where('production_order_id', $order->id)
                    ->whereNotIn('status', [ProductionBatch::STATUS_CANCELLED, ProductionBatch::STATUS_CONSUMED])
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($activeBatches as $b) {
                    $loggedAtInit = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                        ->where('production_batch_id', $b->id)
                        ->where('operation_id', $operation->id)
                        ->sum('quantity_produced');

                    $remainingCapacity = max(0.0, (float) $b->planned_quantity - (float) $loggedAtInit);
                    if ($remainingCapacity > 0) {
                        return $b;
                    }
                }

                // If no existing active batch has remaining capacity at initial operation, create a new batch
                $plannedForNewBatch = max($quantityProduced, $order->quantity_ordered);
                if ($activeBatches->isNotEmpty()) {
                    $plannedForNewBatch = $quantityProduced > 0 ? $quantityProduced : $order->quantity_ordered;
                }

                $batchProductId = $operation->source_product_id ?? $order->product_id;
                $newBatch = $this->createBatch(
                    $tenantId,
                    $order->id,
                    $batchProductId,
                    $plannedForNewBatch,
                    ProductionBatch::STATUS_IN_PROGRESS,
                    null,
                    "Batch created for production at operation #{$operation->sequence}"
                );
                $newBatch->current_operation_id = $operation->id;
                $newBatch->source_operation_id = $operation->id;
                $newBatch->save();

                return $newBatch;
            }

            // 3. Successor Operation: Require transferred WIP / predecessor batch eligibility
            $allBatches = ProductionBatch::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->whereNotIn('status', [ProductionBatch::STATUS_CANCELLED, ProductionBatch::STATUS_CONSUMED])
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $eligibleBatches = [];
            foreach ($allBatches as $b) {
                $transferredIn = \App\Domains\Production\Models\ProductionWipTransaction::where('tenant_id', $tenantId)
                    ->where('production_order_id', $order->id)
                    ->where('production_batch_id', $b->id)
                    ->where('to_operation_id', $operation->routing_operation_id)
                    ->where('transaction_type', 'transferred')
                    ->sum('quantity');

                $processedAtCurrentOp = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
                    ->where('production_batch_id', $b->id)
                    ->where('operation_id', $operation->id)
                    ->sum('quantity_produced');

                $eligibleQty = max(0.0, (float) $transferredIn - (float) $processedAtCurrentOp);
                if ($eligibleQty > 0) {
                    $eligibleBatches[] = ['batch' => $b, 'eligible_qty' => $eligibleQty];
                }
            }

            if (count($eligibleBatches) === 1) {
                return $eligibleBatches[0]['batch'];
            }

            if (count($eligibleBatches) > 1) {
                throw new \InvalidArgumentException("Multiple batches have transferred WIP available at operation #{$operation->sequence}. Please explicitly select batch_id.");
            }

            // Fallback: If only 1 batch exists for order, continue with it
            if ($allBatches->count() === 1) {
                return $allBatches->first();
            }

            if ($allBatches->isNotEmpty()) {
                return $allBatches->first();
            }

            throw new \InvalidArgumentException("No transferred WIP available at operation #{$operation->sequence} for any batch.");
        });
    }

    /**
     * Create a linked overflow child batch for excess production beyond batch capacity.
     */
    public function createOverflowBatch(
        ProductionBatch $parentBatch,
        float $overflowQty,
        \App\Domains\Production\Models\ProductionOrderOperation $operation
    ): ProductionBatch {
        return DB::transaction(function () use ($parentBatch, $overflowQty, $operation) {
            $child = $this->createBatch(
                $parentBatch->tenant_id,
                $parentBatch->production_order_id,
                $parentBatch->product_id,
                $overflowQty,
                ProductionBatch::STATUS_IN_PROGRESS,
                $parentBatch->expiry_date ? $parentBatch->expiry_date->toDateString() : null,
                "Overflow batch created from parent batch #{$parentBatch->batch_number} at operation #{$operation->sequence}."
            );

            $child->current_operation_id = $operation->id;
            $child->source_operation_id = $operation->id;
            $child->save();

            // Record genealogy link
            ProductionBatchGenealogy::create([
                'tenant_id' => $parentBatch->tenant_id,
                'parent_batch_id' => $parentBatch->id,
                'child_batch_id' => $child->id,
                'type' => 'split',
                'quantity' => $overflowQty,
            ]);

            ProductionLotTrace::create([
                'tenant_id' => $parentBatch->tenant_id,
                'source_type' => 'batch',
                'source_id' => $parentBatch->id,
                'target_type' => 'batch',
                'target_id' => $child->id,
                'quantity' => $overflowQty,
                'remarks' => "Overflow batch trace from parent batch {$parentBatch->batch_number}.",
            ]);

            return $child;
        });
    }

    /**
     * Get operation-specific, routing-aware batch queue categorized into active, waiting transfer, blocked, completed, and waiting input.
     */
    public function getOperationBatchQueue(
        \App\Domains\Production\Models\ProductionOrderOperation $operation,
        ?int $operatorId = null
    ): array {
        $tenantId = $operation->tenant_id;
        $orderId = $operation->production_order_id;
        $opProductId = $operation->source_product_id ?? $operation->order?->product_id;

        // Determine if initial routing operation for this product routing
        $isFirstOp = !\App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($operation, $opProductId) {
                if ($operation->routing_id) {
                    $q->where('routing_id', $operation->routing_id);
                } else {
                    $q->where('source_product_id', $opProductId);
                }
            })
            ->where('sequence', '<', $operation->sequence)
            ->exists();

        $prevOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($operation, $opProductId) {
                if ($operation->routing_id) {
                    $q->where('routing_id', $operation->routing_id);
                } else {
                    $q->where('source_product_id', $opProductId);
                }
            })
            ->where('sequence', '<', $operation->sequence)
            ->orderBy('sequence', 'desc')
            ->first();

        $nextOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($operation, $opProductId) {
                if ($operation->routing_id) {
                    $q->where('routing_id', $operation->routing_id);
                } else {
                    $q->where('source_product_id', $opProductId);
                }
            })
            ->where('sequence', '>', $operation->sequence)
            ->orderBy('sequence', 'asc')
            ->first();

        $batches = ProductionBatch::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(function ($q) use ($opProductId) {
                if ($opProductId) {
                    $q->where('product_id', $opProductId);
                }
            })
            ->whereNotIn('status', [ProductionBatch::STATUS_CANCELLED, ProductionBatch::STATUS_CONSUMED])
            ->get();

        // Eager load grouped logs, WIP transactions, scraps, reworks to avoid N+1
        $logs = \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->get();

        $wipTxs = \App\Domains\Production\Models\ProductionWipTransaction::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->get();

        $scraps = \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->get();

        $reworks = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->get();

        $queue = [
            'active' => [],
            'waiting_transfer' => [],
            'blocked' => [],
            'completed' => [],
            'waiting_input' => [],
            'meta' => [
                'is_first_op' => $isFirstOp,
                'previous_op' => $prevOp,
                'next_op' => $nextOp,
                'current_op' => $operation,
            ],
        ];

        foreach ($batches as $batch) {
            $batchLogs = $logs->where('production_batch_id', $batch->id);
            $batchWip = $wipTxs->where('production_batch_id', $batch->id);
            $batchScraps = $scraps->where('production_batch_id', $batch->id);
            $batchReworks = $reworks->where('production_batch_id', $batch->id);

            // Operation-level quantities
            $reworkCompletedAtOp = (float) $batchWip->where('from_operation_id', $operation->routing_operation_id)
                ->where('transaction_type', 'rework_completed')
                ->sum('quantity');
            $reworkFailedScrappedAtOp = (float) $batchWip->where('from_operation_id', $operation->routing_operation_id)
                ->where('transaction_type', 'rework_failed_scrapped')
                ->sum('quantity');
            $processedAtOp = (float) $batchLogs->where('operation_id', $operation->id)->sum('quantity_produced') + $reworkCompletedAtOp;

            $logScrapAtOp = (float) $batchLogs->where('operation_id', $operation->id)->sum('quantity_scrapped');
            $disposalScrapAtOp = (float) $batchScraps->where('production_order_operation_id', $operation->id)->sum('quantity');
            $scrapAtOp = max($disposalScrapAtOp, $logScrapAtOp);

            $reworkAtOp = (float) $batchReworks->where('production_order_operation_id', $operation->id)->where('status', 'pending')->sum('quantity');
            $logRejectedAtOp = (float) $batchLogs->where('operation_id', $operation->id)->sum('quantity_rejected');
            $rejectedAtOp = max($reworkAtOp, $logRejectedAtOp);
            $pendingRejectedAtOp = max(0.0, $rejectedAtOp - $reworkCompletedAtOp - $reworkFailedScrappedAtOp);

            // Transferred IN into current operation's routing_operation_id
            $transferredIn = (float) $batchWip->where('to_operation_id', $operation->routing_operation_id)
                ->where('transaction_type', 'transferred')
                ->sum('quantity');

            if ($prevOp && $transferredIn <= 0) {
                // Good output produced at previous operation in same routing for this batch is available input
                $reworkCompletedAtPrev = (float) $batchWip->where('from_operation_id', $prevOp->routing_operation_id)
                    ->where('transaction_type', 'rework_completed')
                    ->sum('quantity');
                $goodAtPrevOp = (float) $batchLogs->where('operation_id', $prevOp->id)->sum('quantity_produced') + $reworkCompletedAtPrev;
                $transferredIn = max($transferredIn, $goodAtPrevOp);
            }

            // Transferred OUT from current operation to successor OR converted to Finished Goods
            $transferredOut = (float) $batchWip->where('from_operation_id', $operation->routing_operation_id)
                ->whereIn('transaction_type', ['transferred', 'converted_to_finished_goods'])
                ->sum('quantity');

            // If final operation, also sum finished goods conversion transactions for this batch
            if (!$nextOp) {
                $fgConvertedOut = (float) \App\Domains\Production\Models\ProductionWipTransaction::where('tenant_id', $batch->tenant_id)
                    ->where('production_order_id', $operation->production_order_id)
                    ->where(function ($q) use ($batch) {
                        $q->where('production_batch_id', $batch->id)
                            ->orWhereHas('wip', fn($w) => $w->where('production_batch_id', $batch->id));
                    })
                    ->where('transaction_type', 'converted_to_finished_goods')
                    ->sum('quantity');

                $transferredOut = max($transferredOut, $fgConvertedOut);
            }

            // Input available & remaining processable quantity
            $totalInputConsumed = $processedAtOp + $pendingRejectedAtOp + $scrapAtOp;

            if ($isFirstOp) {
                $inputAvailable = max(0.0, (float) $batch->planned_quantity - $totalInputConsumed);
                $totalInputReceived = (float) $batch->planned_quantity;
            } else {
                $inputAvailable = max(0.0, $transferredIn - $totalInputConsumed);
                $totalInputReceived = $transferredIn;
            }

            $remainingToProcess = max(0.0, $inputAvailable);
            $goodAtOp = $processedAtOp;

            // Ready to transfer forward
            $readyToTransfer = $nextOp ? max(0.0, $goodAtOp - $transferredOut) : 0.0;

            // Check holds / quality blocking
            $isBlocked = ($batch->status === ProductionBatch::STATUS_BLOCKED || $batch->status === 'quarantine');
            $hasPendingRework = ($batchReworks->whereIn('production_order_operation_id', [$operation->id, $operation->routing_operation_id])->where('status', 'pending')->isNotEmpty()) || ($pendingRejectedAtOp > 0);

            // Determine Display State
            if ($isBlocked) {
                $displayStatus = 'BLOCKED';
            } elseif ($hasPendingRework) {
                $displayStatus = 'REWORK';
            } elseif ($isFirstOp && $totalInputConsumed >= $batch->planned_quantity && $readyToTransfer <= 0 && $pendingRejectedAtOp <= 0) {
                $displayStatus = 'COMPLETED_AT_OPERATION';
            } elseif (!$isFirstOp && $transferredIn > 0 && $remainingToProcess <= 0 && $readyToTransfer <= 0 && $pendingRejectedAtOp <= 0) {
                $displayStatus = 'COMPLETED_AT_OPERATION';
            } elseif (($processedAtOp > 0 || $scrapAtOp > 0 || $rejectedAtOp > 0) && $remainingToProcess <= 0 && $readyToTransfer > 0 && $pendingRejectedAtOp <= 0) {
                $displayStatus = 'WAITING_FOR_TRANSFER';
            } elseif (($processedAtOp > 0 || $scrapAtOp > 0 || $rejectedAtOp > 0) && ($remainingToProcess > 0 || $pendingRejectedAtOp > 0)) {
                $displayStatus = 'PARTIALLY_PROCESSED';
            } elseif ($processedAtOp == 0 && $scrapAtOp == 0 && $rejectedAtOp == 0 && $totalInputReceived > 0 && $remainingToProcess > 0) {
                $displayStatus = 'READY';
            } else {
                $displayStatus = 'WAITING_FOR_INPUT';
            }

            $canLogProgress = ($remainingToProcess > 0 || $pendingRejectedAtOp > 0) && !$isBlocked;
            $canTransfer = ($nextOp !== null) && ($readyToTransfer > 0) && !$isBlocked;
            $canSplit = ($inputAvailable > 0) && !$isBlocked;
            $canPrintLabel = true;

            $item = [
                'batch' => $batch,
                'planned_quantity' => (float) $batch->planned_quantity,
                'input_received' => $totalInputReceived,
                'input_available' => $inputAvailable,
                'processed_at_operation' => $processedAtOp,
                'good_at_operation' => $goodAtOp,
                'scrap_at_operation' => $scrapAtOp,
                'rework_at_operation' => $reworkAtOp,
                'rejected_at_operation' => $rejectedAtOp,
                'rework_completed_at_operation' => $reworkCompletedAtOp,
                'rework_failed_scrapped_at_operation' => $reworkFailedScrappedAtOp,
                'pending_rejected_at_operation' => $pendingRejectedAtOp,
                'transferred_to_next' => $transferredOut,
                'remaining_to_process' => $remainingToProcess,
                'ready_to_transfer' => $readyToTransfer,
                'previous_operation' => $prevOp,
                'next_operation' => $nextOp,
                'display_status' => $displayStatus,
                'can_log_progress' => $canLogProgress,
                'can_transfer' => $canTransfer,
                'can_split' => $canSplit,
                'can_print_label' => $canPrintLabel,
            ];

            // Assign to queue categories
            if ($displayStatus === 'BLOCKED') {
                $queue['blocked'][] = $item;
            } elseif ($displayStatus === 'COMPLETED_AT_OPERATION') {
                $queue['completed'][] = $item;
            } elseif ($displayStatus === 'WAITING_FOR_TRANSFER') {
                $queue['waiting_transfer'][] = $item;
            } elseif ($displayStatus === 'READY' || $displayStatus === 'PARTIALLY_PROCESSED' || $displayStatus === 'REWORK') {
                $queue['active'][] = $item;
            } else {
                $queue['waiting_input'][] = $item;
            }
        }

        return $queue;
    }

    /**
     * Reconcile a batch's actual_quantity to include both direct progress logs and completed rework output.
     */
    public function reconcileBatchActualQuantity(int $batchId): ProductionBatch
    {
        $batch = ProductionBatch::findOrFail($batchId);
        $tenantId = $batch->tenant_id;
        $orderId = $batch->production_order_id;

        $finalOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->orderBy('sequence', 'desc')
            ->first();

        if (!$finalOp) {
            return $batch;
        }

        $directProduced = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('production_batch_id', $batchId)
            ->where('operation_id', $finalOp->id)
            ->sum('quantity_produced');

        $reworkCompleted = (float) \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('production_batch_id', $batchId)
            ->where('status', 'completed')
            ->sum('quantity');

        $reconciledActual = $directProduced + $reworkCompleted;

        if ($reconciledActual > $batch->actual_quantity) {
            $batch->actual_quantity = $reconciledActual;
            if ($batch->actual_quantity >= $batch->planned_quantity && $batch->planned_quantity > 0) {
                $batch->status = ProductionBatch::STATUS_COMPLETED;
            }
            $batch->save();
        }

        return $batch;
    }

    /**
     * Record component consumption genealogy between parent and predecessor batch/operation. (F-03)
     */
    public function recordComponentConsumptionGenealogy(
        ProductionOrder $order,
        ProductionOrderOperation $parentOp,
        ProductionOrderOperation $predOp,
        float $quantity,
        ?int $userId = null,
        ?int $parentBatchId = null,
        ?int $childBatchId = null
    ): void {
        $parentBatch = $parentBatchId
            ? ProductionBatch::where('tenant_id', $order->tenant_id)->find($parentBatchId)
            : ProductionBatch::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->first();

        if (!$parentBatch) {
            $parentBatch = ProductionBatch::create([
                'tenant_id' => $order->tenant_id,
                'production_order_id' => $order->id,
                'batch_number' => 'BAT-' . $order->order_number . '-01',
                'product_id' => $order->product_id,
                'planned_quantity' => $order->quantity_ordered,
                'status' => 'released',
            ]);
        }

        $childBatch = $childBatchId
            ? ProductionBatch::where('tenant_id', $order->tenant_id)->find($childBatchId)
            : ProductionBatch::where('tenant_id', $order->tenant_id)
                ->where('production_order_id', $order->id)
                ->where('product_id', $predOp->source_product_id)
                ->first();

        if ($parentBatch) {
            $effectiveChildBatchId = $childBatch?->id ?? $parentBatch->id;

            // Prevent duplicate genealogy entries for identical consumption event (F-03)
            $exists = ProductionBatchGenealogy::where('tenant_id', $order->tenant_id)
                ->where('parent_batch_id', $parentBatch->id)
                ->where('child_batch_id', $effectiveChildBatchId)
                ->where('type', 'component_consumption')
                ->where('quantity', $quantity)
                ->exists();

            if (!$exists) {
                ProductionBatchGenealogy::create([
                    'tenant_id' => $order->tenant_id,
                    'parent_batch_id' => $parentBatch->id,
                    'child_batch_id' => $effectiveChildBatchId,
                    'component_product_id' => $predOp->source_product_id,
                    'quantity' => $quantity,
                    'type' => 'component_consumption',
                    'recorded_by' => $userId,
                ]);
            }
        }
    }
}
