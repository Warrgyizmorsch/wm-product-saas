<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\WorkCenter;
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
            $wcId = $data['work_center_id'] ?? WorkCenter::where('tenant_id', $tenantId)->value('id');

            $ncr = \App\Domains\Production\Models\ProductionNcr::with('operation')->find($ncrId);
            $defaultMachineId = $data['machine_id'] ?? $ncr?->machine_id ?? $ncr?->operation?->machine_id;
            if (!$defaultMachineId && $wcId) {
                $defaultMachineId = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)->where('work_center_id', $wcId)->value('id');
            }

            // Default single direct rework operation
            $defaultOpName = !empty($data['operation_name'])
                ? $data['operation_name']
                : ($ncr?->operation ? "Rework: {$ncr->operation->name}" : 'Rework Execution');

            $operations = $data['operations'] ?? [
                ['sequence' => 10, 'name' => $defaultOpName, 'work_center_id' => $wcId, 'machine_id' => $defaultMachineId],
            ];

            $costEstimate = isset($data['cost_estimate']) && $data['cost_estimate'] !== null
                ? (float) $data['cost_estimate']
                : $this->calculateDynamicCostEstimate($tenantId, $data, $operations);

            $rework = ProductionReworkOrder::create([
                'tenant_id' => $tenantId,
                'rework_number' => 'RWK-' . strtoupper(uniqid()),
                'ncr_id' => $ncrId,
                'original_production_order_id' => $data['original_production_order_id'],
                'status' => 'draft',
                'cost_estimate' => $costEstimate,
            ]);

            foreach ($operations as $op) {
                $opWcId = $op['work_center_id'] ?? $wcId;
                $opMachineId = $op['machine_id'] ?? $defaultMachineId;
                if ($opMachineId && $opWcId) {
                    $machWc = \App\Domains\Production\Models\Machine::where('id', $opMachineId)->value('work_center_id');
                    if ($machWc && $machWc !== $opWcId) {
                        $opMachineId = \App\Domains\Production\Models\Machine::where('work_center_id', $opWcId)->value('id');
                    }
                }

                ProductionReworkOperation::create([
                    'tenant_id' => $tenantId,
                    'rework_order_id' => $rework->id,
                    'sequence' => $op['sequence'],
                    'name' => $op['name'],
                    'work_center_id' => $opWcId,
                    'machine_id' => $opMachineId,
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
     * Calculate dynamic cost estimate based on planned operations and Work Center / Machine rates.
     */
    public function calculateDynamicCostEstimate(int $tenantId, array $data, array $operations): float
    {
        $totalEstimate = 0.0;
        $defaultOpMinutes = 30.0; // standard default estimated duration per planned rework stage

        foreach ($operations as $op) {
            $wcId = $op['work_center_id'] ?? ($data['work_center_id'] ?? null);
            $wc = $wcId ? WorkCenter::where('tenant_id', $tenantId)->find($wcId) : null;

            $laborRatePerMinute = $wc ? ((float) $wc->cost_per_hour / 60.0) : 0.0;
            $overheadRatePerMinute = $wc ? ((float) $wc->overhead_rate / 60.0) : 0.0;

            $machineRatePerMinute = 0.0;
            if (!empty($op['machine_id'])) {
                $prodOrderId = $data['original_production_order_id'] ?? null;
                if ($prodOrderId) {
                    $orderOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
                        ->where('production_order_id', $prodOrderId)
                        ->where(function ($q) use ($wcId, $op) {
                            if ($wcId) {
                                $q->where('work_center_id', $wcId);
                            }
                            $q->orWhere('machine_id', $op['machine_id']);
                        })
                        ->with('routingOperation')
                        ->first();
                    $machineRatePerMinute = (float) ($orderOp?->routingOperation?->machine_cost_rate ?? 0.0);
                }
            }

            $opMinutes = (float) ($op['planned_minutes'] ?? $defaultOpMinutes);
            $totalEstimate += $opMinutes * ($laborRatePerMinute + $overheadRatePerMinute + $machineRatePerMinute);
        }

        return round($totalEstimate, 2);
    }

    /**
     * Start rework operation.
     */
    public function startOperation(int $reworkOpId, ?int $tenantId = null, ?int $machineId = null): void
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

        $updateData = [
            'status' => 'running',
            'actual_start' => Carbon::now(),
        ];
        if ($machineId) {
            $updateData['machine_id'] = $machineId;
        }

        $op->update($updateData);

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

            $actualElapsedMinutes = (float) $start->diffInMinutes($end);
            $runMinutes = isset($data['processing_time_actual']) && (float) $data['processing_time_actual'] > 0
                ? (float) $data['processing_time_actual']
                : $actualElapsedMinutes;

            $setupMinutes = isset($data['setup_time_actual']) ? (float) $data['setup_time_actual'] : (float) ($op->setup_time_actual ?? 0.0);
            $totalMinutes = $setupMinutes + $runMinutes;
            $hours = $totalMinutes / 60.0;

            $op->loadMissing(['workCenter', 'machine', 'reworkOrder.ncr.operation.routingOperation']);

            $wc = $op->workCenter;
            $wcLaborRate = $wc ? ((float) $wc->cost_per_hour / 60.0) : 0.0;
            $wcOverheadRate = $wc ? ((float) $wc->overhead_rate / 60.0) : 0.0;

            // Resolve machine rate dynamically if machine assigned
            $machineRatePerMinute = 0.0;
            if ($op->machine_id) {
                $routingMachineRate = (float) ($op->reworkOrder?->ncr?->operation?->routingOperation?->machine_cost_rate ?? 0.0);
                $machineRatePerMinute = $routingMachineRate;
            }

            $laborCost = round($totalMinutes * $wcLaborRate, 2);
            $machineCost = round($totalMinutes * $machineRatePerMinute, 2);
            $overheadCost = round($totalMinutes * $wcOverheadRate, 2);
            $addedNonMaterialCost = round($laborCost + $machineCost + $overheadCost, 2);

            $op->update([
                'status' => 'completed',
                'actual_end' => $end,
                'setup_time_actual' => $setupMinutes,
                'processing_time_actual' => $runMinutes / 60.0,
            ]);

            $rework = $op->reworkOrder;
            $rework->update([
                'actual_cost' => $rework->actual_cost + $addedNonMaterialCost,
                'labor_hours_actual' => $rework->labor_hours_actual + $hours,
                'machine_hours_actual' => $rework->machine_hours_actual + ($op->machine_id ? $hours : 0.00),
            ]);

            // If all operations are complete, mark rework order as completed
            $incomplete = ProductionReworkOperation::where('rework_order_id', $rework->id)
                ->where('status', '!=', 'completed')
                ->exists();

            if (!$incomplete) {
                $rework->update(['status' => 'completed']);

                // Record non-material Rework Expense in ProductionCostAdjustment on parent production order.
                // CRITICAL ERP RULE: Material cost is explicitly excluded here to prevent double-counting,
                // as rework material was already captured via ProductionOrderIssue and StockService outflow.
                $totalReworkLabor = 0.0;
                $totalReworkMachine = 0.0;
                $totalReworkOverhead = 0.0;

                if ($rework->original_production_order_id) {
                    $parentOrder = ProductionOrder::find($rework->original_production_order_id);
                    if ($parentOrder) {
                        $rework->loadMissing(['operations.workCenter', 'ncr.operation.routingOperation']);

                        foreach ($rework->operations as $cOp) {
                            $cMins = round((float) ($cOp->setup_time_actual ?? 0.0) + ((float) ($cOp->processing_time_actual ?? 0.0) * 60.0), 2);
                            $cWc = $cOp->workCenter;
                            $cLaborRate = $cWc ? ((float) $cWc->cost_per_hour / 60.0) : 0.0;
                            $cOverheadRate = $cWc ? ((float) $cWc->overhead_rate / 60.0) : 0.0;
                            $cMachineRate = 0.0;
                            if ($cOp->machine_id) {
                                $cMachineRate = (float) ($rework->ncr?->operation?->routingOperation?->machine_cost_rate ?? 0.0);
                            }

                            $totalReworkLabor += $cMins * $cLaborRate;
                            $totalReworkMachine += $cMins * $cMachineRate;
                            $totalReworkOverhead += $cMins * $cOverheadRate;
                        }

                        $totalReworkLabor = round($totalReworkLabor, 2);
                        $totalReworkMachine = round($totalReworkMachine, 2);
                        $totalReworkOverhead = round($totalReworkOverhead, 2);

                        if ($totalReworkLabor > 0.0) {
                            ProductionCostAdjustment::create([
                                'tenant_id'           => $parentOrder->tenant_id,
                                'production_order_id' => $parentOrder->id,
                                'adjustment_date'     => now()->toDateString(),
                                'cost_component'      => ProductionCostAdjustment::COMPONENT_LABOR,
                                'category'            => ProductionCostAdjustment::CATEGORY_REWORK_EXPENSE,
                                'description'         => "Rework Labor Expense for Rework Order {$rework->rework_number}",
                                'amount'              => $totalReworkLabor,
                                'status'              => 'recorded',
                                'notes'               => "Generated upon completion of Rework #{$rework->id}",
                                'created_by'          => auth()->id() ?? $rework->created_by,
                                'updated_by'          => auth()->id() ?? $rework->created_by,
                            ]);
                        }

                        if ($totalReworkMachine > 0.0) {
                            ProductionCostAdjustment::create([
                                'tenant_id'           => $parentOrder->tenant_id,
                                'production_order_id' => $parentOrder->id,
                                'adjustment_date'     => now()->toDateString(),
                                'cost_component'      => ProductionCostAdjustment::COMPONENT_MACHINE,
                                'category'            => ProductionCostAdjustment::CATEGORY_REWORK_EXPENSE,
                                'description'         => "Rework Machine Expense for Rework Order {$rework->rework_number}",
                                'amount'              => $totalReworkMachine,
                                'status'              => 'recorded',
                                'notes'               => "Generated upon completion of Rework #{$rework->id}",
                                'created_by'          => auth()->id() ?? $rework->created_by,
                                'updated_by'          => auth()->id() ?? $rework->created_by,
                            ]);
                        }

                        if ($totalReworkOverhead > 0.0) {
                            ProductionCostAdjustment::create([
                                'tenant_id'           => $parentOrder->tenant_id,
                                'production_order_id' => $parentOrder->id,
                                'adjustment_date'     => now()->toDateString(),
                                'cost_component'      => ProductionCostAdjustment::COMPONENT_OVERHEAD,
                                'category'            => ProductionCostAdjustment::CATEGORY_REWORK_EXPENSE,
                                'description'         => "Rework Overhead Expense for Rework Order {$rework->rework_number}",
                                'amount'              => $totalReworkOverhead,
                                'status'              => 'recorded',
                                'notes'               => "Generated upon completion of Rework #{$rework->id}",
                                'created_by'          => auth()->id() ?? $rework->created_by,
                                'updated_by'          => auth()->id() ?? $rework->created_by,
                            ]);
                        }
                    }
                }

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
                            ->where(function ($q) use ($originalOp) {
                                $q->where('previous_operation_id', $originalOp->id)
                                  ->orWhereHas('predecessorDependencies', fn($d) => $d->where('predecessor_operation_id', $originalOp->id))
                                  ->orWhere(function ($sub) use ($originalOp) {
                                      if ($originalOp->source_product_id) {
                                          $sub->where('source_product_id', $originalOp->source_product_id);
                                      } else {
                                          $sub->whereNull('source_product_id');
                                      }
                                      $sub->where('sequence', '>', $originalOp->sequence);
                                  });
                            })
                            ->exists();

                        $wip->rejected_quantity = max(0.0000, $wip->rejected_quantity - $reworkQty);
                        if (isset($totalReworkLabor) && ($totalReworkLabor > 0 || $totalReworkMachine > 0 || $totalReworkOverhead > 0)) {
                            $wip->labor_cost += $totalReworkLabor;
                            $wip->machine_cost += $totalReworkMachine;
                            $wip->overhead_cost += $totalReworkOverhead;
                            $wip->total_value += ($totalReworkLabor + $totalReworkMachine + $totalReworkOverhead);
                        }
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
                    'description' => "Rework order {$rework->rework_number} completed. Actual Rework Cost: " . format_currency((float) $rework->actual_cost) . ".",
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
                'company_id' => $originalOrder?->company_id ?? $rework->company_id ?? company_id(),
                'branch_id' => $originalOrder?->branch_id ?? $rework->branch_id ?? branch_id(),
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

    /**
     * Request additional raw material from store for a Rework Order.
     * Generates or appends to a Store Requisition Slip (MR-YYYY-XXXXXX).
     * Warehouse selection is deferred to the Storekeeper in the Store.
     */
    public function requestMaterial(
        int $reworkId,
        int $tenantId,
        int $productId,
        float $quantity,
        ?int $reworkOpId = null,
        ?string $reason = null,
        ?int $userId = null
    ): \App\Domains\Production\Models\ProductionRequisitionSlip {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Requested quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($reworkId, $tenantId, $productId, $quantity, $reworkOpId, $reason, $userId) {
            $rework = ProductionReworkOrder::with('originalOrder')->findOrFail($reworkId);
            $parentOrder = $rework->originalOrder;

            $branchId = $parentOrder?->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $parentOrder?->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            // 1. Find existing pending slip for this rework order, or create new one
            $slip = \App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('rework_order_id', $reworkId)
                ->whereIn('status', ['pending', 'partial', 'Pending', 'Partially Issued'])
                ->latest('id')
                ->first();

            if (!$slip) {
                $year = now()->format('Y');
                $prefix = "MR-{$year}-";
                $lastSlip = \App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                    ->where('requisition_number', 'like', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNum = 1;
                if ($lastSlip) {
                    $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
                    $nextNum = ((int) $lastNumStr) + 1;
                }
                $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                while (\App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                    ->where('requisition_number', $reqNumber)
                    ->exists()) {
                    $nextNum++;
                    $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                }

                $slipNotes = "Material requisition for Rework Order {$rework->rework_number}";
                if ($reason) {
                    $slipNotes .= " — {$reason}";
                }

                $slip = \App\Domains\Production\Models\ProductionRequisitionSlip::create([
                    'tenant_id'                 => $tenantId,
                    'company_id'                => $companyId,
                    'branch_id'                 => $branchId,
                    'production_order_id'       => $rework->original_production_order_id,
                    'maintenance_work_order_id' => null,
                    'rework_order_id'           => $rework->id,
                    'source_type'               => \App\Domains\Production\Models\ProductionRequisitionSlip::SOURCE_TYPE_REWORK_ORDER,
                    'requisition_number'        => $reqNumber,
                    'status'                    => 'pending',
                    'requested_by'              => $userId ?? auth()->id(),
                    'requisition_date'          => now()->toDateString(),
                    'notes'                     => $slipNotes,
                ]);
            }

            $product = \App\Domains\Inventory\Models\Product::findOrFail($productId);
            $uomId = $product->uom_id ?? 1;

            \App\Domains\Production\Models\ProductionRequisitionSlipItem::create([
                'tenant_id'                      => $tenantId,
                'production_requisition_slip_id' => $slip->id,
                'product_id'                     => $productId,
                'warehouse_id'                   => null, // Storekeeper determines warehouse upon fulfillment!
                'rework_operation_id'            => $reworkOpId,
                'quantity_planned'               => $quantity,
                'quantity_reserved'              => 0.0,
                'quantity_issued'                => 0.0,
                'uom_id'                         => $uomId,
            ]);

            // Sync reservation on parent order so Material Status reflects it
            if ($parentOrder) {
                $res = \App\Domains\Production\Models\ProductionOrderReservation::where('tenant_id', $tenantId)
                    ->where('production_order_id', $parentOrder->id)
                    ->where('product_id', $productId)
                    ->first();

                if (!$res) {
                    \App\Domains\Production\Models\ProductionOrderReservation::create([
                        'tenant_id'                     => $tenantId,
                        'production_order_id'           => $parentOrder->id,
                        'product_id'                    => $productId,
                        'uom_id'                        => $uomId,
                        'quantity_planned'              => 0.0,
                        'quantity_additional_requested' => $quantity,
                        'quantity_reserved'             => 0.0,
                        'quantity_issued'               => 0.0,
                    ]);
                } else {
                    $res->quantity_additional_requested += $quantity;
                    $res->save();
                }
            }

            $this->eventService->writeEvent($tenantId, [
                'production_order_id' => $rework->original_production_order_id,
                'event_type'          => 'REWORK_MATERIAL_REQUESTED',
                'title'               => 'Rework Material Requested from Store',
                'description'         => "Requested {$quantity} of {$product->name} for Rework #{$rework->rework_number} on MR #{$slip->requisition_number}.",
                'severity'            => 'info',
                'event_source'        => 'ReworkService',
            ]);

            return $slip;
        });
    }
}
