<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBatchGenealogy;
use App\Domains\Production\Models\ProductionLotTrace;
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
            if ($order) {
                $sumActivePlanned = ProductionBatch::where('tenant_id', $tenantId)
                    ->where('production_order_id', $orderId)
                    ->whereNotIn('status', [ProductionBatch::STATUS_CANCELLED])
                    ->sum('planned_quantity');

                $unallocatedOrderQty = max(0.0, (float) $order->quantity_ordered - (float) $sumActivePlanned);
                if ($plannedQty > $unallocatedOrderQty && $unallocatedOrderQty > 0) {
                    throw new \InvalidArgumentException("Planned batch quantity (" . number_format($plannedQty, 2) . ") cannot exceed unallocated order capacity (" . number_format($unallocatedOrderQty, 2) . ").");
                }
            }

            $batchNumber = $this->batchNumberService->generateNextNumber($tenantId);

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

            // Case 1: Full split (total split == parent planned quantity)
            if (abs($totalSplitQty - (float) $parent->planned_quantity) < 0.0001) {
                $firstChunk = array_shift($splits);
                $firstQty = (float) $firstChunk['planned_quantity'];

                // Update parent batch to the first split chunk quantity
                $parent->update([
                    'planned_quantity' => $firstQty,
                    'remarks' => ($firstChunk['remarks'] ?? $parent->remarks) . " | Split parent batch.",
                ]);

                // Remaining split chunks create new child batches
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
            } else {
                // Case 2: Partial split (total split < parent planned quantity)
                $remainingBalance = max(0.00, (float) $parent->planned_quantity - $totalSplitQty);
                $parent->update([
                    'planned_quantity' => $remainingBalance,
                    'remarks' => $parent->remarks . " | Partial split of {$totalSplitQty} quantity into children.",
                ]);

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

            if ($hasProgress || $hasWipTx || (float)$batch->actual_quantity > 0) {
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

                $newBatch = $this->createBatch(
                    $tenantId,
                    $order->id,
                    $order->product_id,
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

        // Determine if initial routing operation
        $isFirstOp = !\App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('sequence', '<', $operation->sequence)
            ->exists();

        $prevOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('sequence', '<', $operation->sequence)
            ->orderBy('sequence', 'desc')
            ->first();

        $nextOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('sequence', '>', $operation->sequence)
            ->orderBy('sequence', 'asc')
            ->first();

        $batches = ProductionBatch::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
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
            $processedAtOp = (float) $batchLogs->where('operation_id', $operation->id)->sum('quantity_produced') + $reworkCompletedAtOp;
            $scrapAtOp = (float) $batchScraps->where('operation_id', $operation->id)->sum('quantity');
            $reworkAtOp = (float) $batchReworks->where('operation_id', $operation->id)->sum('rework_quantity');

            // Transferred IN into current operation's routing_operation_id
            $transferredIn = (float) $batchWip->where('to_operation_id', $operation->routing_operation_id)
                ->where('transaction_type', 'transferred')
                ->sum('quantity');

            // Transferred OUT from current operation to successor
            $transferredOut = (float) $batchWip->where('from_operation_id', $operation->routing_operation_id)
                ->where('transaction_type', 'transferred')
                ->sum('quantity');

            // Input available & remaining processable quantity
            if ($isFirstOp) {
                $inputAvailable = max(0.0, (float) $batch->planned_quantity - $processedAtOp);
                $totalInputReceived = (float) $batch->planned_quantity;
            } else {
                $inputAvailable = max(0.0, $transferredIn - $processedAtOp);
                $totalInputReceived = $transferredIn;
            }

            $remainingToProcess = max(0.0, $inputAvailable);
            $goodAtOp = max(0.0, $processedAtOp - $scrapAtOp);

            // Ready to transfer forward
            $readyToTransfer = $nextOp ? max(0.0, $goodAtOp - $transferredOut) : 0.0;

            // Check holds / quality blocking
            $isBlocked = ($batch->status === ProductionBatch::STATUS_BLOCKED || $batch->status === 'quarantine');
            $hasPendingRework = $batchReworks->where('operation_id', $operation->id)->where('status', 'pending')->isNotEmpty();

            // Determine Display State
            if ($isBlocked) {
                $displayStatus = 'BLOCKED';
            } elseif ($hasPendingRework) {
                $displayStatus = 'REWORK';
            } elseif ($isFirstOp && $processedAtOp >= $batch->planned_quantity && $readyToTransfer <= 0) {
                $displayStatus = 'COMPLETED_AT_OPERATION';
            } elseif (!$isFirstOp && $transferredIn > 0 && $remainingToProcess <= 0 && $readyToTransfer <= 0) {
                $displayStatus = 'COMPLETED_AT_OPERATION';
            } elseif ($processedAtOp > 0 && $remainingToProcess <= 0 && $readyToTransfer > 0) {
                $displayStatus = 'WAITING_FOR_TRANSFER';
            } elseif ($processedAtOp > 0 && $remainingToProcess > 0) {
                $displayStatus = 'PARTIALLY_PROCESSED';
            } elseif ($processedAtOp == 0 && $totalInputReceived > 0 && $remainingToProcess > 0) {
                $displayStatus = 'READY';
            } else {
                $displayStatus = 'WAITING_FOR_INPUT';
            }

            $canLogProgress = ($remainingToProcess > 0) && !$isBlocked && !$hasPendingRework;
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
            if ($displayStatus === 'BLOCKED' || $displayStatus === 'REWORK') {
                $queue['blocked'][] = $item;
            } elseif ($displayStatus === 'COMPLETED_AT_OPERATION') {
                $queue['completed'][] = $item;
            } elseif ($displayStatus === 'WAITING_FOR_TRANSFER') {
                $queue['waiting_transfer'][] = $item;
            } elseif ($displayStatus === 'READY' || $displayStatus === 'PARTIALLY_PROCESSED') {
                $queue['active'][] = $item;
            } else {
                $queue['waiting_input'][] = $item;
            }
        }

        return $queue;
    }
}
