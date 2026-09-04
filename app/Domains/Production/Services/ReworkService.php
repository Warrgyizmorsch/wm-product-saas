<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReworkService
{
    public function __construct(
        private readonly ProductionEventService $eventService
    ) {
    }

    /**
     * Create a Rework Order and operations.
     */
    public function createReworkOrder(int $tenantId, int $ncrId, array $data): ProductionReworkOrder
    {
        return DB::transaction(function () use ($tenantId, $ncrId, $data) {
            $rework = ProductionReworkOrder::create([
                'tenant_id' => $tenantId,
                'rework_number' => 'RWK-' . strtoupper(uniqid()),
                'ncr_id' => $ncrId,
                'original_production_order_id' => $data['original_production_order_id'],
                'status' => 'draft',
                'cost_estimate' => $data['cost_estimate'] ?? 150.00,
            ]);

            $wcId = $data['work_center_id'] ?? \App\Domains\Production\Models\WorkCenter::where('tenant_id', $tenantId)->value('id') ?? 1;

            // Add standard default rework operation if operations list empty
            $operations = $data['operations'] ?? [
                ['sequence' => 10, 'name' => 'Disassemble and Inspect Defect', 'work_center_id' => $wcId],
                ['sequence' => 20, 'name' => 'Refabricate Defective Section', 'work_center_id' => $wcId],
            ];

            foreach ($operations as $op) {
                ProductionReworkOperation::create([
                    'tenant_id' => $tenantId,
                    'rework_order_id' => $rework->id,
                    'sequence' => $op['sequence'],
                    'name' => $op['name'],
                    'work_center_id' => $op['work_center_id'],
                    'machine_id' => $op['machine_id'] ?? null,
                    'status' => 'waiting',
                ]);
            }

            $this->eventService->writeEvent($tenantId, [
                'production_order_id' => $rework->original_production_order_id,
                'event_type' => 'REWORK_CREATED',
                'title' => 'Rework Order Created',
                'description' => "Rework Order {$rework->rework_number} created for NCR #{$ncrId}.",
                'severity' => 'warning',
                'event_source' => 'ReworkService',
            ]);

            return $rework;
        });
    }

    /**
     * Start rework operation.
     */
    public function startOperation(int $reworkOpId, ?int $tenantId = null): void
    {
        $op = ProductionReworkOperation::query()
            ->when($tenantId !== null, fn($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($reworkOpId);

        if ($op->status === 'completed') {
            throw new \InvalidArgumentException('Completed rework operations cannot be restarted.');
        }

        if ($op->status === 'running') {
            return;
        }

        $op->update([
            'status' => 'running',
            'actual_start' => Carbon::now(),
        ]);

        $rework = $op->reworkOrder;
        if ($rework->status === 'draft') {
            $rework->update(['status' => 'running']);
        }

        $this->eventService->writeEvent($op->tenant_id, [
            'production_order_id' => $rework->original_production_order_id,
            'machine_id' => $op->machine_id,
            'event_type' => 'Rework Started',
            'title' => 'Rework Execution Triggered',
            'description' => "Rework operation {$op->name} has started on order #{$rework->original_production_order_id}.",
            'severity' => 'info',
            'event_source' => 'ReworkService',
        ]);
    }

    /**
     * Complete rework operation and update actual accumulated cost.
     */
    public function completeOperation(int $reworkOpId, array $data, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($reworkOpId, $data, $tenantId) {
            $op = ProductionReworkOperation::query()
                ->when($tenantId !== null, fn($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($reworkOpId);

            if ($op->status === 'completed') {
                return;
            }

            if ($op->status !== 'running') {
                throw new \InvalidArgumentException('Only running rework operations can be completed.');
            }

            $start = $op->actual_start ?? Carbon::now()->subMinutes(30);
            $end = Carbon::now();

            $actualMinutes = (float) $start->diffInMinutes($end);
            $hours = $actualMinutes / 60.0;

            $op->update([
                'status' => 'completed',
                'actual_end' => $end,
                'setup_time_actual' => $data['setup_time_actual'] ?? 0.00,
                'processing_time_actual' => $hours,
            ]);

            // Calculate cost increment: labor ($35/hr) + machine ($50/hr)
            $laborRate = 35.00;
            $machineRate = 50.00;

            $addedCost = ($hours * $laborRate) + ($hours * $machineRate);

            $rework = $op->reworkOrder;
            $rework->update([
                'actual_cost' => $rework->actual_cost + $addedCost,
                'labor_hours_actual' => $rework->labor_hours_actual + $hours,
                'machine_hours_actual' => $rework->machine_hours_actual + ($op->machine_id ? $hours : 0.00),
            ]);

            // If all operations are complete, mark rework order as completed
            $incomplete = ProductionReworkOperation::where('rework_order_id', $rework->id)
                ->where('status', '!=', 'completed')
                ->exists();

            if (!$incomplete) {
                $rework->update(['status' => 'completed']);

                // Auto-resolve NCR
                $ncr = $rework->ncr;
                if ($ncr && $ncr->status !== 'closed') {
                    $ncr->update([
                        'status' => 'closed',
                        'closed_by' => auth()->id(),
                        'closed_at' => Carbon::now(),
                        'esignature_closed' => hash('sha256', (auth()->id() ?? 'system') . $ncr->id . 'closed' . now()->timestamp),
                    ]);

                    $this->eventService->writeEvent($ncr->tenant_id, [
                        'production_order_id' => $ncr->production_order_id,
                        'event_type' => 'NCR Closed',
                        'title' => 'Non-Conformance Resolved',
                        'description' => "NCR {$ncr->ncr_number} automatically closed upon Rework completion.",
                        'severity' => 'success',
                        'event_source' => 'ReworkService',
                    ]);
                }

                // Update original ProductionOrderRework status
                $ncrBatchId = $ncr?->batch_id ?? $ncr?->production_batch_id;
                $ncrOpId = $ncr?->production_order_operation_id;

                $orderRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                    ->where('production_order_id', $rework->original_production_order_id)
                    ->when($ncrBatchId, fn($q) => $q->where('production_batch_id', $ncrBatchId))
                    ->when($ncrOpId, fn($q) => $q->where('production_order_operation_id', $ncrOpId))
                    ->where('status', '!=', 'completed')
                    ->first();

                if (!$orderRework && $ncrOpId) {
                    $orderRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                        ->where('production_order_id', $rework->original_production_order_id)
                        ->where('production_order_operation_id', $ncrOpId)
                        ->where('status', '!=', 'completed')
                        ->first();
                }

                if (!$orderRework) {
                    $orderRework = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                        ->where('production_order_id', $rework->original_production_order_id)
                        ->where('status', '!=', 'completed')
                        ->first();
                }

                if ($orderRework) {
                    $orderRework->update(['status' => 'completed']);
                    $reworkQty = $orderRework->quantity;

                    // Update operation-level counts
                    $originalOpId = $orderRework->production_order_operation_id ?? $ncrOpId;
                    $originalOp = $originalOpId ? \App\Domains\Production\Models\ProductionOrderOperation::find($originalOpId) : null;

                    $isQcRequired = (bool) ($originalOp && ($originalOp->quality_required || ($originalOp->routingOperation?->quality_required ?? false) || ($originalOp->routingOperation?->operation_type === 'inspection')));

                    if ($originalOp) {
                        $originalOp->quantity_rejected = max(0.0000, $originalOp->quantity_rejected - $reworkQty);
                        if (!$isQcRequired) {
                            $originalOp->quantity_produced += $reworkQty;
                        }
                        $originalOp->save();
                    }

                    // Update WIP counts
                    $targetBatchId = $orderRework->production_batch_id;
                    $wip = ($targetBatchId && \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $rework->tenant_id)->where('id', $targetBatchId)->exists())
                        ? \App\Domains\Production\Models\ProductionWip::where('production_order_id', $rework->original_production_order_id)->where('production_batch_id', $targetBatchId)->first()
                        : null;

                    if (!$wip) {
                        $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $rework->original_production_order_id)->first();
                    }

                    if ($wip) {
                        $nextOpExists = \App\Domains\Production\Models\ProductionOrderOperation::where('production_order_id', $wip->production_order_id)
                            ->where('sequence', '>', $originalOp->sequence)
                            ->exists();

                        $wip->rejected_quantity = max(0.0000, $wip->rejected_quantity - $reworkQty);
                        $wip->save();

                        $batchIdCandidate = $orderRework->production_batch_id ?? $wip->production_batch_id;
                        $validBatchId = ($batchIdCandidate && \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $wip->tenant_id)->where('id', $batchIdCandidate)->exists())
                            ? (int) $batchIdCandidate
                            : null;

                        if ($isQcRequired) {
                            // Operation requires QC -> Log progress log so getPendingQcQuantity() routes reworked units to Pending QC
                            \App\Domains\Production\Models\ProductionOrderProgressLog::create([
                                'tenant_id' => $rework->tenant_id,
                                'production_order_id' => $rework->original_production_order_id,
                                'operation_id' => $originalOp->id,
                                'production_batch_id' => $validBatchId,
                                'quantity_produced' => $reworkQty,
                                'quantity_rejected' => 0,
                                'remarks' => "Rework Order {$rework->rework_number} completed. Sent for Quality Re-Inspection.",
                                'recorded_by' => auth()->id() ?? $rework->created_by ?? 1,
                                'recorded_at' => now(),
                            ]);

                            \App\Domains\Production\Models\ProductionWipTransaction::create([
                                'tenant_id' => $wip->tenant_id,
                                'wip_id' => $wip->id,
                                'production_order_id' => $wip->production_order_id,
                                'production_batch_id' => $validBatchId,
                                'from_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                                'to_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                                'from_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                                'to_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                                'transaction_type' => 'rework_pending_qc',
                                'quantity' => $reworkQty,
                                'good_quantity' => 0,
                                'rework_quantity' => -$reworkQty,
                                'remarks' => "Rework completed for {$reworkQty} units. Routed to Quality Re-Inspection (Pending QC).",
                                'transaction_at' => now(),
                            ]);
                        } else {
                            // Operation does NOT require QC -> Directly accept into WIP and unlock successor operations
                            if (!$nextOpExists) {
                                $wip->completed_quantity += $reworkQty;
                            }
                            $wip->available_quantity += $reworkQty;
                            $wip->save();

                            if ($validBatchId) {
                                app(\App\Domains\Production\Services\BatchProductionService::class)->reconcileBatchActualQuantity($validBatchId);
                            }

                            \App\Domains\Production\Models\ProductionWipTransaction::create([
                                'tenant_id' => $wip->tenant_id,
                                'wip_id' => $wip->id,
                                'production_order_id' => $wip->production_order_id,
                                'production_batch_id' => $validBatchId,
                                'from_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                                'to_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                                'from_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                                'to_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                                'transaction_type' => 'rework_completed',
                                'quantity' => $reworkQty,
                                'good_quantity' => $reworkQty,
                                'rework_quantity' => -$reworkQty,
                                'remarks' => "Rework completed: {$reworkQty} units restored to available WIP.",
                                'transaction_at' => now(),
                            ]);

                            app(\App\Domains\Production\Services\ProductionWipService::class)->evaluateAndExecuteWipTransfers($originalOp->id, auth()->id() ?? $wip->created_by);
                        }
                    }

                    // Update original production order's quantity_rejected
                    $originalOrder = $rework->originalOrder;
                    if ($originalOrder) {
                        $originalOrder->quantity_rejected = max(0.0000, $originalOrder->quantity_rejected - $reworkQty);
                        $originalOrder->save();
                    }
                }

                $this->eventService->writeEvent($op->tenant_id, [
                    'production_order_id' => $rework->original_production_order_id,
                    'event_type' => 'Rework Completed',
                    'title' => 'Rework Order Finalized',
                    'description' => "Rework order {$rework->rework_number} completed. Actual Rework Cost: \${$rework->actual_cost}.",
                    'severity' => 'success',
                    'event_source' => 'ReworkService',
                ]);
            }
        });
    }

    /**
     * Mark a Rework Order as failed, converting rejected units permanently to Scrap.
     */
    public function failRework(int $reworkId, array $data = [], ?int $tenantId = null): ProductionReworkOrder
    {
        return DB::transaction(function () use ($reworkId, $data, $tenantId) {
            /** @var ProductionReworkOrder $rework */
            $rework = ProductionReworkOrder::query()
                ->when($tenantId !== null, fn($q) => $q->where('tenant_id', $tenantId))
                ->lockForUpdate()
                ->findOrFail($reworkId);

            // Idempotency check: if already failed, return gracefully
            if ($rework->status === 'failed') {
                return $rework;
            }

            if (in_array($rework->status, ['completed', 'cancelled'], true)) {
                throw new \InvalidArgumentException("Rework order {$rework->rework_number} is already {$rework->status} and cannot be marked as failed.");
            }

            $userId = auth()->id() ?? $data['user_id'] ?? null;
            $failureReason = $data['reason'] ?? $data['remarks'] ?? 'Rework attempt failed; converted to scrap.';

            // 1. Mark Rework Order status as failed
            $rework->update([
                'status' => 'failed',
            ]);

            // Mark any pending operations as cancelled
            ProductionReworkOperation::where('rework_order_id', $rework->id)
                ->where('status', '!=', 'completed')
                ->update(['status' => 'cancelled']);

            // 2. Identify linked shop-floor ProductionOrderRework record(s)
            $ncr = $rework->ncr;
            $ncrBatchId = $ncr?->batch_id ?? $ncr?->production_batch_id;
            $ncrOpId = $ncr?->production_order_operation_id;

            $orderReworks = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                ->where('production_order_id', $rework->original_production_order_id)
                ->when($ncrBatchId, fn($q) => $q->where('production_batch_id', $ncrBatchId))
                ->when($ncrOpId, fn($q) => $q->where('production_order_operation_id', $ncrOpId))
                ->whereIn('status', ['pending', 'in_progress', 'draft'])
                ->lockForUpdate()
                ->get();

            if ($orderReworks->isEmpty() && $ncrOpId) {
                $orderReworks = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                    ->where('production_order_id', $rework->original_production_order_id)
                    ->where('production_order_operation_id', $ncrOpId)
                    ->whereIn('status', ['pending', 'in_progress', 'draft'])
                    ->lockForUpdate()
                    ->get();
            }

            if ($orderReworks->isEmpty()) {
                $orderReworks = \App\Domains\Production\Models\ProductionOrderRework::where('tenant_id', $rework->tenant_id)
                    ->where('production_order_id', $rework->original_production_order_id)
                    ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                    ->lockForUpdate()
                    ->get();
            }

            $failedQty = 0.0;
            foreach ($orderReworks as $orw) {
                $orw->update(['status' => 'failed']);
                $failedQty += (float) $orw->quantity;
            }

            if ($failedQty <= 0) {
                $failedQty = (float) ($ncr?->quantity ?? 1.0);
            }

            // 3. Update Operation metrics (quantity_rejected -= failedQty, quantity_scrapped += failedQty)
            $originalOpId = $ncr?->production_order_operation_id ?? $orderReworks->first()?->production_order_operation_id;
            $originalOp = $originalOpId ? \App\Domains\Production\Models\ProductionOrderOperation::lockForUpdate()->find($originalOpId) : null;

            if ($originalOp) {
                $originalOp->quantity_rejected = max(0.0000, round((float) $originalOp->quantity_rejected - $failedQty, 4));
                $originalOp->quantity_scrapped = round((float) $originalOp->quantity_scrapped + $failedQty, 4);
                $originalOp->save();
            }

            // 4. Update Production Order metrics (quantity_rejected -= failedQty, quantity_scrapped += failedQty)
            $originalOrder = $rework->originalOrder;
            if ($originalOrder) {
                $originalOrder->quantity_rejected = max(0.0000, round((float) $originalOrder->quantity_rejected - $failedQty, 4));
                $originalOrder->quantity_scrapped = round((float) $originalOrder->quantity_scrapped + $failedQty, 4);
                $originalOrder->save();
            }

            // 5. Create ProductionOrderScrap record to ensure traceability
            $batchIdCandidate = $orderReworks->first()?->production_batch_id ?? $ncrBatchId;
            $validBatchId = ($batchIdCandidate && \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $rework->tenant_id)->where('id', $batchIdCandidate)->exists())
                ? (int) $batchIdCandidate
                : null;

            \App\Domains\Production\Models\ProductionOrderScrap::create([
                'tenant_id' => $rework->tenant_id,
                'production_order_id' => $rework->original_production_order_id,
                'production_order_operation_id' => $originalOp?->id,
                'production_batch_id' => $validBatchId,
                'product_id' => $originalOrder?->product_id,
                'quantity' => $failedQty,
                'reason' => "Failed Rework scrap: " . $failureReason,
                'recorded_by' => $userId,
                'recorded_at' => now(),
                'stock_transaction_id' => null,
            ]);

            // 6. Update WIP card metrics (rejected_quantity -= failedQty, scrap_quantity += failedQty, available_quantity unchanged)
            $wip = ($validBatchId)
                ? \App\Domains\Production\Models\ProductionWip::where('production_order_id', $rework->original_production_order_id)->where('production_batch_id', $validBatchId)->lockForUpdate()->first()
                : null;

            if (!$wip) {
                $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $rework->original_production_order_id)->lockForUpdate()->first();
            }

            if ($wip) {
                $wip->rejected_quantity = max(0.0000, round((float) $wip->rejected_quantity - $failedQty, 4));
                $wip->scrap_quantity = round((float) $wip->scrap_quantity + $failedQty, 4);
                $wip->save();

                // Log WIP Transaction Ledger entry
                \App\Domains\Production\Models\ProductionWipTransaction::create([
                    'tenant_id' => $wip->tenant_id,
                    'wip_id' => $wip->id,
                    'production_order_id' => $wip->production_order_id,
                    'production_batch_id' => $validBatchId,
                    'from_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                    'to_operation_id' => $originalOp ? $originalOp->routing_operation_id : null,
                    'from_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                    'to_work_center_id' => $originalOp ? $originalOp->work_center_id : null,
                    'transaction_type' => 'rework_failed_scrapped',
                    'quantity' => $failedQty,
                    'good_quantity' => 0.00,
                    'rework_quantity' => -$failedQty,
                    'scrap_quantity' => $failedQty,
                    'remarks' => "Rework failed for {$failedQty} units; converted to scrap.",
                    'transaction_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            // 7. Register NCR Scrap Disposal via ScrapService
            app(\App\Domains\Production\Services\ScrapService::class)->createScrapDisposal($rework->tenant_id, [
                'ncr_id' => $ncr?->id,
                'category' => 'finished_good',
                'reason_code' => 'rework_failed',
                'quantity' => $failedQty,
                'cost' => $failedQty * ($originalOrder?->product?->unit_cost ?? 1.00),
                'status' => 'approved',
            ]);

            // 8. Close linked NCR with disposition_type = 'scrap'
            if ($ncr && $ncr->status !== 'closed') {
                $ncr->update([
                    'disposition_type' => 'scrap',
                    'status' => 'closed',
                    'closed_by' => $userId,
                    'closed_at' => Carbon::now(),
                    'esignature_closed' => hash('sha256', ($userId ?? 'system') . $ncr->id . 'closed' . now()->timestamp),
                ]);

                $this->eventService->writeEvent($ncr->tenant_id, [
                    'production_order_id' => $ncr->production_order_id,
                    'event_type' => 'NCR Closed',
                    'title' => 'Non-Conformance Resolved (Scrapped)',
                    'description' => "NCR {$ncr->ncr_number} closed with Scrap disposition following Rework Failure.",
                    'severity' => 'warning',
                    'event_source' => 'ReworkService',
                    'triggered_by' => $userId,
                ]);
            }

            // 9. Write timeline event
            $this->eventService->writeEvent($rework->tenant_id, [
                'production_order_id' => $rework->original_production_order_id,
                'event_type' => 'Rework Failed',
                'title' => 'Rework Failed - Converted to Scrap',
                'description' => "Rework Order {$rework->rework_number} failed. {$failedQty} units converted to scrap.",
                'severity' => 'danger',
                'event_source' => 'ReworkService',
                'triggered_by' => $userId,
            ]);

            // 10. Re-evaluate holds, batch reconciliation, and WIP transfers
            if ($validBatchId) {
                app(\App\Domains\Production\Services\BatchProductionService::class)->reconcileBatchActualQuantity($validBatchId);
            }

            if ($originalOp) {
                app(\App\Domains\Production\Services\ProductionWipService::class)->evaluateAndExecuteWipTransfers($originalOp->id, $userId);
            }

            return $rework;
        });
    }
}
