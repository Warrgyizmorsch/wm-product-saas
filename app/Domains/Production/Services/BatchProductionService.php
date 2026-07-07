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
    ) {}

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
            $batchNumber = $this->batchNumberService->generateNextNumber($tenantId);

            $batch = ProductionBatch::create([
                'tenant_id'           => $tenantId,
                'batch_number'        => $batchNumber,
                'production_order_id' => $orderId,
                'product_id'          => $productId,
                'planned_quantity'    => $plannedQty,
                'actual_quantity'     => 0.0000,
                'expiry_date'         => $expiryDate,
                'status'              => $status,
                'remarks'             => $remarks,
            ]);

            // Log trace from Order to Batch
            ProductionLotTrace::create([
                'tenant_id'   => $tenantId,
                'source_type' => 'order',
                'source_id'   => $orderId,
                'target_type' => 'batch',
                'target_id'   => $batch->id,
                'quantity'    => $plannedQty,
                'remarks'     => 'Batch created from order.',
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_batch_id' => $batch->id,
                'event_type'          => 'Batch Created',
                'title'               => 'Production Batch Created',
                'description'         => "Batch {$batch->batch_number} has been created.",
                'severity'            => 'info',
                'event_source'        => 'BatchProductionService',
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
                $qty = (float) $split['planned_quantity'];
                $totalSplitQty += $qty;

                // Create child batch
                $child = $this->createBatch(
                    $tenantId,
                    $parent->production_order_id,
                    $parent->product_id,
                    $qty,
                    ProductionBatch::STATUS_PLANNED,
                    $split['expiry_date'] ?? ($parent->expiry_date ? $parent->expiry_date->toDateString() : null),
                    $split['remarks'] ?? "Split from parent batch #{$parent->batch_number}"
                );

                // Save parent-child genealogy relation
                ProductionBatchGenealogy::create([
                    'tenant_id'       => $tenantId,
                    'parent_batch_id' => $parent->id,
                    'child_batch_id'  => $child->id,
                    'type'            => 'split',
                    'quantity'        => $qty,
                ]);

                // Create lot trace record
                ProductionLotTrace::create([
                    'tenant_id'   => $tenantId,
                    'source_type' => 'batch',
                    'source_id'   => $parent->id,
                    'target_type' => 'batch',
                    'target_id'   => $child->id,
                    'quantity'    => $qty,
                    'remarks'     => "Split trace from parent batch {$parent->batch_number}.",
                ]);

                $children[] = $child;
            }

            // Deduct split qty from parent planned/actual quantity
            $newPlanned = max(0.0000, $parent->planned_quantity - $totalSplitQty);
            $parent->update([
                'planned_quantity' => $newPlanned,
                'remarks'          => $parent->remarks . " | Split {$totalSplitQty} quantity into children.",
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $parent->production_order_id,
                'production_batch_id' => $parent->id,
                'event_type'          => 'Batch Split',
                'title'               => 'Batch Split Completed',
                'description'         => "Batch {$parent->batch_number} has been split into child batches.",
                'severity'            => 'info',
                'event_source'        => 'BatchProductionService',
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

            // Validate same product
            $productId = $parents->first()->product_id;
            $orderId   = $parents->first()->production_order_id;
            foreach ($parents as $parent) {
                if ($parent->product_id !== $productId) {
                    throw new \LogicException("Cannot merge batches with different products.");
                }
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
                    'tenant_id'       => $tenantId,
                    'parent_batch_id' => $parent->id,
                    'child_batch_id'  => $child->id,
                    'type'            => 'merge',
                    'quantity'        => $parent->planned_quantity,
                ]);

                // Trace log
                ProductionLotTrace::create([
                    'tenant_id'   => $tenantId,
                    'source_type' => 'batch',
                    'source_id'   => $parent->id,
                    'target_type' => 'batch',
                    'target_id'   => $child->id,
                    'quantity'    => $parent->planned_quantity,
                    'remarks'     => "Merge trace into batch {$child->batch_number}.",
                ]);

                // Consume/Complete parents
                $parent->update([
                    'status' => ProductionBatch::STATUS_CONSUMED,
                ]);
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'production_batch_id' => $child->id,
                'event_type'          => 'Batch Merge',
                'title'               => 'Batches Merged',
                'description'         => "Multiple batches merged into target batch {$child->batch_number}.",
                'severity'            => 'info',
                'event_source'        => 'BatchProductionService',
            ]);

            return $child;
        });
    }
}
