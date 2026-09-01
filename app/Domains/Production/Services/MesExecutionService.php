<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MesExecutionService
{
    /**
     * Start a scheduled operation.
     *
     * Validates:
     * - Operation must be in 'ready' status.
     * - All predecessor operations (lower sequence) must be completed or skipped.
     * - Machine (if specified) must be active, in same tenant, and not currently running another operation.
     *
     * Side effects:
     * - Sets actual_start on ProductionScheduleOperation.
     * - Updates ProductionOrderOperation status to running.
     * - Transitions machine state to 'Running' and logs a production event.
     *
     * @param  int  $scheduleOpId  ID of the ProductionScheduleOperation to start.
     * @param  int|null  $machineId  Override machine (must match tenant & work center).
     * @param  int|null  $operatorId  Operator who started the operation.
     *
     * @throws InvalidArgumentException When state transition is not allowed.
     */
    public function startOperation(int $scheduleOpId, ?int $machineId, ?int $operatorId): void
    {
        DB::transaction(function () use ($scheduleOpId, $machineId, $operatorId) {
            $schedOp = ProductionScheduleOperation::with(['schedule', 'order'])
                ->lockForUpdate()
                ->findOrFail($scheduleOpId);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);

            if ($orderOp && $orderOp->is_external) {
                throw new InvalidArgumentException("Operation {$orderOp->operation_number} is an external subcontract operation. Internal machine execution is disabled.");
            }

            // Auto-heal schedule operation status if underlying order operation is ready or has transferred WIP
            if ($schedOp->isWaiting() && $orderOp) {
                if ($orderOp->status === ProductionOrderOperation::STATUS_READY || (float) $orderOp->quantity_transferred_in > 0) {
                    $schedOp->status = ProductionScheduleOperation::STATUS_READY;
                    $schedOp->save();
                }
            }

            if (!$schedOp->canStart()) {
                throw new InvalidArgumentException(
                    "Operation cannot be started. Current status: [{$schedOp->status}]. Only 'ready' operations can be started."
                );
            }

            // Sync with underlying ProductionOrderOperation and check skills qualification
            // Skills validation is opt-in: only enforced if tenant has skills configured
            if ($operatorId && $orderOp) {
                $tenantId = $schedOp->schedule->order->tenant_id;
                $tenantHasSkills = ProductionOperatorSkill::where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->exists();

                if ($tenantHasSkills) {
                    try {
                        app(OperatorAssignmentService::class)
                            ->validateOperatorQualification($operatorId, $orderOp, $tenantId);
                    } catch (\LogicException $e) {
                        throw new InvalidArgumentException($e->getMessage());
                    }
                }
            }

            // Validate predecessor operations are complete or have transferred WIP via overlap
            $predOrderOpIds = [];
            if ($orderOp) {
                if ($orderOp->previous_operation_id) {
                    $predOrderOpIds[] = (int) $orderOp->previous_operation_id;
                }
                $orderOp->loadMissing('predecessorDependencies');
                foreach ($orderOp->predecessorDependencies as $predDep) {
                    $predOrderOpIds[] = (int) $predDep->id;
                }
            }

            // Single-routing or fallback sequence check if no explicit dependencies exist
            if (empty($predOrderOpIds) && $orderOp && $schedOp->sequence > 10) {
                $prevSched = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                    ->where('sequence', '<', $schedOp->sequence)
                    ->orderBy('sequence', 'desc')
                    ->first();
                if ($prevSched && $prevSched->production_order_operation_id) {
                    $predOrderOpIds[] = (int) $prevSched->production_order_operation_id;
                }
            }

            if (!empty($predOrderOpIds)) {
                $incompletePredecessors = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                    ->whereIn('production_order_operation_id', $predOrderOpIds)
                    ->whereNotIn('status', [
                        ProductionScheduleOperation::STATUS_COMPLETED,
                        ProductionScheduleOperation::STATUS_SKIPPED,
                        ProductionScheduleOperation::STATUS_CANCELLED,
                    ])
                    ->get();

                $blockingPredecessors = $incompletePredecessors->filter(function (ProductionScheduleOperation $predOp) use ($orderOp, $schedOp): bool {
                    $predOrderOp = $predOp->orderOperation;
                    if ($predOrderOp) {
                        // If underlying order operation is completed or target quantity is produced, not blocking!
                        if (
                            $predOrderOp->status === ProductionOrderOperation::STATUS_COMPLETED ||
                            ($predOrderOp->target_produced_qty > 0 && (float) $predOrderOp->quantity_produced >= (float) $predOrderOp->target_produced_qty)
                        ) {
                            // Auto-heal schedule operation status
                            $predOp->update([
                                'status' => ProductionScheduleOperation::STATUS_COMPLETED,
                                'actual_finish' => $predOp->actual_finish ?? now(),
                            ]);
                            return false;
                        }

                        if ($predOrderOp->queue_threshold_enabled ?? $predOrderOp->overlap_enabled) {
                            if (($orderOp && (float) $orderOp->quantity_transferred_in > 0) || ((float) $predOrderOp->quantity_transferred_out > 0) || ($schedOp->status === ProductionScheduleOperation::STATUS_READY)) {
                                return false;
                            }
                        }
                    }
                    return true;
                });

                if ($blockingPredecessors->isNotEmpty()) {
                    throw new InvalidArgumentException(
                        "Cannot start operation #{$schedOp->sequence}. All predecessor operations must be completed or skipped first."
                    );
                }
            }

            $blockedByQuality = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '<', $schedOp->sequence)
                ->where('status', ProductionScheduleOperation::STATUS_COMPLETED)
                ->get()
                ->contains(function (ProductionScheduleOperation $predecessor): bool {
                    $orderOp = $predecessor->orderOperation;

                    return $orderOp instanceof ProductionOrderOperation
                        && $this->qualityGateIsPendingOrFailed($orderOp);
                });

            if ($blockedByQuality) {
                throw new InvalidArgumentException('Cannot start next operation until predecessor quality gates have passed.');
            }

            // Validate machine if provided
            $resolvedMachineId = $machineId ?? $schedOp->machine_id;
            if ($resolvedMachineId) {
                // Lock machine row to prevent concurrent assignment
                Machine::withoutGlobalScopes()
                    ->where('tenant_id', $schedOp->schedule->order->tenant_id)
                    ->lockForUpdate()
                    ->findOrFail($resolvedMachineId);

                $this->validateMachineForExecution($resolvedMachineId, $schedOp->schedule->order->tenant_id);

                // Check machine is not double-booked (another running op on same machine)
                $conflict = ProductionScheduleOperation::withoutGlobalScopes()
                    ->where('tenant_id', $schedOp->schedule->order->tenant_id)
                    ->where('machine_id', $resolvedMachineId)
                    ->where('status', ProductionScheduleOperation::STATUS_RUNNING)
                    ->where('id', '!=', $scheduleOpId)
                    ->exists();

                if ($conflict) {
                    throw new InvalidArgumentException(
                        'Machine is already running another operation. Cannot start a second operation on the same machine simultaneously.'
                    );
                }
            }

            // Update schedule operation
            $schedOp->update([
                'status' => ProductionScheduleOperation::STATUS_RUNNING,
                'actual_start' => now(),
                'machine_id' => $resolvedMachineId ?? $schedOp->machine_id,
                'actual_machine_id' => $resolvedMachineId ?? $schedOp->machine_id,
            ]);

            // Sync the underlying ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_RUNNING;
                $orderOp->actual_start_time = $orderOp->actual_start_time ?? now();
                $orderOp->operator_id = $operatorId;
                $orderOp->machine_used_id = $resolvedMachineId ?? $orderOp->machine_id;
                $orderOp->save();
            }

            // Sync with WIP tracking
            $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $schedOp->production_order_id)->first();
            if ($wip) {
                app(ProductionWipService::class)->startWipOperation($wip->id, $schedOp->id, $operatorId);
            }

            // Transition ProductionSchedule to in_progress if needed
            $schedule = $schedOp->schedule;
            if ($schedule && in_array($schedule->status, [ProductionSchedule::STATUS_SCHEDULED, ProductionSchedule::STATUS_RELEASED])) {
                $schedule->update([
                    'status' => ProductionSchedule::STATUS_IN_PROGRESS,
                ]);
            }

            // Transition parent order to in_progress if needed
            $order = $schedOp->order;
            if ($order && $order->isReleased()) {
                $order->status = ProductionOrder::STATUS_IN_PROGRESS;
                $order->actual_start_date = now();
                $order->save();
            }

            // Transition machine state if machine is used
            $resolvedMachineId = $machineId ?? $schedOp->machine_id;
            if ($resolvedMachineId) {
                app(MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $resolvedMachineId,
                    'Running',
                    'Production Started',
                    $operatorId
                );
            }

            app(ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id' => $schedOp->production_order_id,
                'production_order_operation_id' => $schedOp->production_order_operation_id,
                'machine_id' => $resolvedMachineId,
                'operator_id' => $operatorId,
                'event_type' => 'Operation Started',
                'title' => 'Operation Started',
                'description' => "Operation {$schedOp->orderOperation->name} has started on the shop floor.",
                'severity' => 'info',
                'event_source' => 'MesExecutionService',
                'triggered_by' => $operatorId,
            ]);
        });
    }

    /**
     * Pause a running operation.
     *
     * Intelligently transitions the assigned machine to a contextual 'waiting' state
     * based on pause remarks (e.g., 'material' → Waiting Material, 'breakdown' → Breakdown).
     *
     * @param  int  $scheduleOpId  ID of the running ProductionScheduleOperation.
     * @param  string|null  $remarks  Optional reason for pause (used to determine machine state).
     *
     * @throws InvalidArgumentException When operation is not in 'running' status.
     */
    public function pauseOperation(int $scheduleOpId, ?string $remarks = null, ?int $operatorId = null): void
    {
        DB::transaction(function () use ($scheduleOpId, $remarks, $operatorId) {
            $schedOp = ProductionScheduleOperation::lockForUpdate()->findOrFail($scheduleOpId);

            if (!$schedOp->isRunning()) {
                throw new InvalidArgumentException(
                    "Only running operations can be paused. Current status: [{$schedOp->status}]."
                );
            }

            $schedOp->update([
                'status' => ProductionScheduleOperation::STATUS_PAUSED,
                'last_paused_at' => now(),
            ]);

            // Sync ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_PAUSED;
                $orderOp->save();
            }

            $machineId = $schedOp->machine_id;
            $userId = $operatorId ?? auth()->id() ?? null;
            if ($machineId) {
                $tenantId = $schedOp->schedule->order->tenant_id;
                $hasOpenDowntime = ProductionMachineDowntime::where('tenant_id', $tenantId)
                    ->where('machine_id', $machineId)
                    ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                    ->exists();

                if (!$hasOpenDowntime) {
                    $reason = $remarks ?? 'Operation Paused';
                    $category = 'Operator Pause';
                    if (str_contains(strtolower($reason), 'material')) {
                        $category = 'Material Shortage';
                    } elseif (str_contains(strtolower($reason), 'breakdown') || str_contains(strtolower($reason), 'failure')) {
                        $category = 'Breakdown';
                    } elseif (str_contains(strtolower($reason), 'shortage')) {
                        $category = 'Operator Shortage';
                    }

                    // startDowntime will automatically transition machine state and write events
                    app(DowntimeService::class)->startDowntime(
                        $tenantId,
                        $machineId,
                        $category,
                        $reason,
                        $userId,
                        [
                            'production_order_id' => $schedOp->production_order_id,
                            'production_order_operation_id' => $schedOp->production_order_operation_id,
                            'remarks' => $remarks,
                        ]
                    );
                }
            }

            app(ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id' => $schedOp->production_order_id,
                'production_order_operation_id' => $schedOp->production_order_operation_id,
                'machine_id' => $machineId,
                'operator_id' => $userId,
                'event_type' => 'Operation Paused',
                'title' => 'Operation Paused',
                'description' => "Operation paused. Reason: {$remarks}",
                'severity' => 'warning',
                'event_source' => 'MesExecutionService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Resume a paused operation.
     *
     * Restores the operation to 'running' status and transitions
     * the assigned machine back to 'Running' state.
     *
     * @param  int  $scheduleOpId  ID of the paused ProductionScheduleOperation.
     * @param  int|null  $operatorId  Operator who resumed the operation.
     *
     * @throws InvalidArgumentException When operation is not in 'paused' status.
     */
    public function resumeOperation(int $scheduleOpId, ?int $operatorId = null): void
    {
        DB::transaction(function () use ($scheduleOpId, $operatorId) {
            $schedOp = ProductionScheduleOperation::lockForUpdate()->findOrFail($scheduleOpId);

            if (!$schedOp->isPaused()) {
                throw new InvalidArgumentException(
                    "Only paused operations can be resumed. Current status: [{$schedOp->status}]."
                );
            }

            $now = now();
            $pausedSeconds = 0;
            if ($schedOp->last_paused_at) {
                $pausedSeconds = max(0, $now->timestamp - $schedOp->last_paused_at->timestamp);
            }

            $schedOp->update([
                'status' => ProductionScheduleOperation::STATUS_RUNNING,
                'accumulated_paused_seconds' => ($schedOp->accumulated_paused_seconds ?? 0) + $pausedSeconds,
                'last_paused_at' => null,
            ]);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_RUNNING;
                $orderOp->save();
            }

            $machineId = $schedOp->machine_id;
            $userId = $operatorId ?? auth()->id() ?? null;
            if ($machineId) {
                // Find and close open downtime
                $activeDowntime = ProductionMachineDowntime::where('tenant_id', $schedOp->schedule->order->tenant_id)
                    ->where('machine_id', $machineId)
                    ->where('production_order_operation_id', $schedOp->production_order_operation_id)
                    ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                    ->first();
                if ($activeDowntime) {
                    app(DowntimeService::class)->endDowntime(
                        $schedOp->schedule->order->tenant_id,
                        $activeDowntime->id,
                        $userId,
                        'Operation Resumed',
                        'Running'
                    );
                } else {
                    app(MachineStateService::class)->transitionState(
                        $schedOp->schedule->order->tenant_id,
                        $machineId,
                        'Running',
                        'Operation Resumed',
                        $userId
                    );
                }
            }

            app(ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id' => $schedOp->production_order_id,
                'production_order_operation_id' => $schedOp->production_order_operation_id,
                'machine_id' => $machineId,
                'operator_id' => $userId,
                'event_type' => 'Operation Resumed',
                'title' => 'Operation Resumed',
                'description' => 'Operation has been resumed.',
                'severity' => 'info',
                'event_source' => 'MesExecutionService',
                'triggered_by' => $userId,
            ]);
        });
    }

    /**
     * Complete an operation, log progress, and advance the routing sequence.
     *
     * Side effects:
     * - Populates actual_finish on ProductionScheduleOperation.
     * - Creates ProductionOrderProgressLog entry with qty_good, qty_scrap, qty_rework.
     * - Marks the next sequential operation as 'ready' (if exists and not yet ready).
     * - Auto-completes the ProductionSchedule if this was the last operation.
     * - Auto-completes the ProductionOrder if all operations are done.
     *
     * @param  int  $scheduleOpId  ID of the running/paused operation to complete.
     * @param  array  $data  Completion data: qty_good, qty_scrap, qty_rework, remarks.
     * @param  int|null  $operatorId  Operator who completed the operation.
     *
     * @throws InvalidArgumentException When operation is not in 'running' or 'paused' status.
     */
    public function completeOperation(int $scheduleOpId, array $data, ?int $operatorId): void
    {
        DB::transaction(function () use ($scheduleOpId, $data, $operatorId) {
            $schedOp = ProductionScheduleOperation::with(['schedule.order.tenant', 'order'])
                ->lockForUpdate()
                ->findOrFail($scheduleOpId);

            $isExtOp = (bool) ($schedOp->orderOperation?->is_external ?? false);
            if (!$schedOp->isRunning() && !$schedOp->isPaused() && !($isExtOp && in_array($schedOp->status, [ProductionScheduleOperation::STATUS_READY, ProductionScheduleOperation::STATUS_RUNNING]))) {
                throw new InvalidArgumentException(
                    "Only running or paused operations can be completed. Current status: [{$schedOp->status}]."
                );
            }

            $now = now();
            $pausedSeconds = 0;
            if ($schedOp->isPaused() && $schedOp->last_paused_at) {
                $pausedSeconds = max(0, $now->timestamp - $schedOp->last_paused_at->timestamp);
            }

            // Update schedule operation timing
            $schedOp->update([
                'status' => ProductionScheduleOperation::STATUS_COMPLETED,
                'actual_finish' => $now,
                'accumulated_paused_seconds' => ($schedOp->accumulated_paused_seconds ?? 0) + $pausedSeconds,
                'last_paused_at' => null,
            ]);

            // Sync ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $produced = (float) ($data['quantity_produced'] ?? 0);
                $rejected = (float) ($data['quantity_rejected'] ?? 0);
                $scrapped = (float) ($data['quantity_scrapped'] ?? 0);
                $setupMinutes = (float) ($data['setup_minutes'] ?? 0);
                $runMinutes = (float) ($data['run_minutes'] ?? 0);

                if ($runMinutes === 0.0 && $schedOp->actual_start) {
                    $pausedSec = $schedOp->accumulated_paused_seconds ?? 0;
                    $diffSeconds = $now->timestamp - $schedOp->actual_start->timestamp - $pausedSec;
                    $totalElapsedMinutes = max(0.0, round($diffSeconds / 60, 2));

                    // Subtract previously logged minutes to get the increment
                    $previouslyLogged = \App\Domains\Production\Models\ProductionOrderProgressLog::where('operation_id', $orderOp->id)
                        ->sum('run_minutes_logged');
                    $runMinutes = max(0.0, $totalElapsedMinutes - $previouslyLogged);
                }

                if ($produced < 0 || $rejected < 0 || $scrapped < 0) {
                    throw new InvalidArgumentException('Quantities cannot be negative.');
                }

                // Overproduction Limit Check
                // Scrapped quantities represent discarded material and should not count toward the overproduction limit.
                $plannedQty = (float) ($orderOp->target_produced_qty > 0 ? $orderOp->target_produced_qty : ($schedOp->schedule->order->quantity_ordered ?? 1.0));
                $totalProcessedSoFar = $orderOp->quantity_produced;
                $currentProcessed = $produced;
                $totalProcessed = $totalProcessedSoFar + $currentProcessed;

                $tenant = $schedOp->schedule->order->tenant;
                $limitPercent = (float) ($tenant->settings['overproduction_limit_percentage'] ?? 20.0);
                $maxAllowed = $plannedQty * (1 + $limitPercent / 100);

                if ($totalProcessed > $maxAllowed) {
                    throw new InvalidArgumentException("Quantity exceeds the allowed overproduction limit of {$limitPercent}% (Max allowed: {$maxAllowed} units).");
                }

                // Prevent processing beyond available transferred input WIP
                $isFirstOp = !ProductionOrderOperation::where('tenant_id', $orderOp->tenant_id)
                    ->where('production_order_id', $orderOp->production_order_id)
                    ->where('sequence', '<', $orderOp->sequence)
                    ->exists();

                $batchId = !empty($data['production_batch_id']) ? (int) $data['production_batch_id'] : (!empty($data['batch_id']) ? (int) $data['batch_id'] : null);

                $availableWip = app(ProductionWipService::class)->getAvailableInputWip($orderOp, $batchId);
                $newConsumed = $isFirstOp ? (float) ($produced + $rejected) : (float) ($produced + $rejected + $scrapped);

                if ($newConsumed > $availableWip) {
                    throw new InvalidArgumentException("Cannot process {$newConsumed} units: Exceeds available transferred input WIP of {$availableWip} units.");
                }

                if ($this->qualityGateIsPendingOrFailed($orderOp)) {
                    throw new InvalidArgumentException('This operation requires an approved passed quality inspection before completion.');
                }

                // Create progress log
                ProductionOrderProgressLog::create([
                    'tenant_id' => $schedOp->order->tenant_id,
                    'production_order_id' => $schedOp->production_order_id,
                    'production_batch_id' => $batchId,
                    'operation_id' => $orderOp->id,
                    'quantity_produced' => $produced,
                    'quantity_rejected' => $rejected,
                    'quantity_scrapped' => $scrapped,
                    'setup_minutes_logged' => $setupMinutes,
                    'run_minutes_logged' => $runMinutes,
                    'recorded_by' => $operatorId,
                    'recorded_at' => $now,
                    'machine_id' => $schedOp->machine_id,
                    'start_time' => $schedOp->actual_start,
                    'stop_time' => $now,
                    'remarks' => $data['remarks'] ?? null,
                ]);

                // Determine if required target quantity has been reached
                $targetQty = (float) ($orderOp->target_produced_qty > 0 ? $orderOp->target_produced_qty : ($schedOp->order->quantity_ordered ?? 0));
                $newTotalOutput = (float) $orderOp->quantity_produced + (float) $orderOp->quantity_scrapped + (float) $orderOp->quantity_rejected + $produced + $scrapped + $rejected;
                $isTargetReached = ($targetQty <= 0) || ($newTotalOutput >= $targetQty) || !empty($data['force_complete']);

                // Update order operation & parent production order metrics
                if ($isTargetReached) {
                    $orderOp->status = ProductionOrderOperation::STATUS_COMPLETED;
                    $orderOp->actual_end_time = $now;
                } else {
                    $orderOp->status = ProductionOrderOperation::STATUS_RUNNING;
                }
                $orderOp->setup_time_actual += $setupMinutes;
                $orderOp->processing_time_actual += $runMinutes;
                $orderOp->quantity_produced += $produced;
                $orderOp->quantity_rejected += $rejected;
                $orderOp->quantity_scrapped += $scrapped;
                $orderOp->save();

                $order = $schedOp->order ?? $schedOp->schedule->order ?? null;
                if ($order) {
                    $isFinalFgOp = !ProductionOrderOperation::where('tenant_id', $orderOp->tenant_id)
                        ->where('production_order_id', $orderOp->production_order_id)
                        ->where('sequence', '>', $orderOp->sequence)
                        ->exists() && (!$orderOp->is_intermediate && ($orderOp->source_product_id === null || (int) $orderOp->source_product_id === (int) $order->product_id));

                    if ($isFinalFgOp) {
                        $order->quantity_produced += $produced;
                    }
                    $order->quantity_rejected += $rejected;
                    $order->quantity_scrapped += $scrapped;
                    $order->save();
                }

                // Handle automatic Rework Order & NCR creation when rejected quantity is logged from shopfloor
                if ($rejected > 0 && $order) {
                    app(ProductionExecutionService::class)->logRework(
                        $schedOp->production_order_id,
                        $orderOp->id,
                        $rejected,
                        $data['remarks'] ?? 'Automatic log rework from MES shopfloor operation execution.',
                        $operatorId,
                        $batchId
                    );

                    $ncr = \App\Domains\Production\Models\ProductionNcr::create([
                        'tenant_id' => $order->tenant_id,
                        'ncr_number' => 'NCR-AUTO-' . strtoupper(uniqid()),
                        'category' => 'process',
                        'status' => 'open',
                        'disposition_type' => 'rework',
                        'production_order_id' => $schedOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'batch_id' => $batchId,
                        'machine_id' => $schedOp->machine_id,
                        'operator_id' => $operatorId,
                        'description' => "Automatic NCR generated due to rejected quantity logged during MES shopfloor operation execution.",
                    ]);

                    app(\App\Domains\Production\Services\ReworkService::class)->createReworkOrder($order->tenant_id, $ncr->id, [
                        'original_production_order_id' => $order->id,
                        'work_center_id' => $orderOp->work_center_id,
                        'cost_estimate' => 50.00,
                    ]);
                }

                // Handle automatic Scrap Disposal & NCR creation when scrapped quantity is logged from shopfloor
                if ($scrapped > 0 && $order) {
                    \App\Domains\Production\Models\ProductionOrderScrap::create([
                        'tenant_id' => $order->tenant_id,
                        'production_order_id' => $schedOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'production_batch_id' => $batchId,
                        'product_id' => $order->product_id,
                        'quantity' => $scrapped,
                        'reason' => $data['remarks'] ?? 'Automatic log scrap from MES shopfloor operation execution.',
                        'recorded_by' => $operatorId,
                        'recorded_at' => $now,
                        'stock_transaction_id' => null,
                    ]);

                    $ncr = \App\Domains\Production\Models\ProductionNcr::create([
                        'tenant_id' => $order->tenant_id,
                        'ncr_number' => 'NCR-AUTO-' . strtoupper(uniqid()),
                        'category' => 'process',
                        'status' => 'open',
                        'disposition_type' => 'scrap',
                        'production_order_id' => $schedOp->production_order_id,
                        'production_order_operation_id' => $orderOp->id,
                        'batch_id' => $batchId,
                        'machine_id' => $schedOp->machine_id,
                        'operator_id' => $operatorId,
                        'description' => "Automatic NCR generated due to scrapped quantity logged during MES shopfloor operation execution.",
                    ]);

                    app(ScrapService::class)->createScrapDisposal($order->tenant_id, [
                        'ncr_id' => $ncr->id,
                        'category' => 'finished_good',
                        'reason_code' => 'defect',
                        'quantity' => $scrapped,
                        'cost' => $scrapped * ($order->product->unit_cost ?? 1.00),
                    ]);
                }

                // Update schedule operation status
                if ($isTargetReached) {
                    $schedOp->update([
                        'status' => ProductionScheduleOperation::STATUS_COMPLETED,
                        'actual_finish' => $now,
                        'accumulated_paused_seconds' => ($schedOp->accumulated_paused_seconds ?? 0) + $pausedSeconds,
                        'last_paused_at' => null,
                    ]);
                } else {
                    $schedOp->update([
                        'status' => ProductionScheduleOperation::STATUS_RUNNING,
                        'accumulated_paused_seconds' => ($schedOp->accumulated_paused_seconds ?? 0) + $pausedSeconds,
                        'last_paused_at' => null,
                    ]);
                }

                // Sync WIP tracking on operation progress
                $batchId = $data['production_batch_id'] ?? \App\Domains\Production\Models\ProductionBatch::where('tenant_id', $schedOp->order->tenant_id)->where('production_order_id', $schedOp->production_order_id)->value('id');
                $wip = $batchId ? app(ProductionWipService::class)->getOrCreateWipForBatchOperation(
                    $schedOp->production_order_id,
                    $batchId,
                    $orderOp->routing_operation_id,
                    $operatorId
                ) : \App\Domains\Production\Models\ProductionWip::where('production_order_id', $schedOp->production_order_id)->first();
                if ($wip) {
                    app(ProductionWipService::class)->completeWipOperation(
                        $wip->id,
                        $orderOp->id,
                        $produced,
                        $rejected,
                        $scrapped,
                        $setupMinutes,
                        $runMinutes,
                        $data['remarks'] ?? null,
                        $operatorId
                    );
                }

                // Trigger sub-batch WIP transfers to downstream operation if applicable
                app(ProductionWipService::class)->evaluateAndExecuteWipTransfers($orderOp->id, $operatorId);
            }

            // Advance successor operations to READY immediately via readiness reconciliation
            if ($schedOp->production_order_id) {
                app(\App\Domains\Production\Services\ProductionOrderService::class)->reconcileOperationReadiness($schedOp->production_order_id);
            }

            // Check if all schedule operations are terminal — if so, auto-complete schedule
            $allDone = !ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->whereNotIn('status', [
                    ProductionScheduleOperation::STATUS_COMPLETED,
                    ProductionScheduleOperation::STATUS_SKIPPED,
                    ProductionScheduleOperation::STATUS_CANCELLED,
                ])
                ->exists();

            if ($allDone) {
                $this->completeSchedule($schedOp->schedule, $operatorId);
            }

            $machineId = $schedOp->machine_id;
            if ($machineId) {
                // If operation was paused, close open downtime first
                $activeDowntime = ProductionMachineDowntime::where('tenant_id', $schedOp->schedule->order->tenant_id)
                    ->where('machine_id', $machineId)
                    ->where('production_order_operation_id', $schedOp->production_order_operation_id)
                    ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                    ->first();
                if ($activeDowntime) {
                    app(DowntimeService::class)->endDowntime(
                        $schedOp->schedule->order->tenant_id,
                        $activeDowntime->id,
                        $operatorId ?? 0,
                        'Operation Completed'
                    );
                } else {
                    // Transition machine state back to Idle
                    app(MachineStateService::class)->transitionState(
                        $schedOp->schedule->order->tenant_id,
                        $machineId,
                        'Idle',
                        'Operation Completed',
                        $operatorId
                    );
                }
            }

            app(ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id' => $schedOp->production_order_id,
                'production_order_operation_id' => $schedOp->production_order_operation_id,
                'machine_id' => $machineId,
                'event_type' => 'Operation Completed',
                'title' => 'Operation Completed',
                'description' => 'Operation has been completed successfully.',
                'severity' => 'success',
                'event_source' => 'MesExecutionService',
                'triggered_by' => $operatorId,
            ]);
        });
    }

    /**
     * Log partial progress on a running or paused operation from the shopfloor.
     */
    public function logPartialProgress(int $scheduleOpId, array $data, ?int $operatorId): void
    {
        DB::transaction(function () use ($scheduleOpId, $data, $operatorId) {
            $schedOp = ProductionScheduleOperation::with(['schedule.order.tenant', 'order'])
                ->lockForUpdate()
                ->findOrFail($scheduleOpId);

            if (!$schedOp->isRunning() && !$schedOp->isPaused()) {
                throw new InvalidArgumentException(
                    "Only running or paused operations can log progress. Current status: [{$schedOp->status}]."
                );
            }

            $now = now();

            // Sync with underlying ProductionOrderOperation
            $orderOp = ProductionOrderOperation::findOrFail($schedOp->production_order_operation_id);

            // Calculate setup/run minutes automatically from elapsed time if not provided
            $setupMinutes = (float) ($data['setup_minutes'] ?? 0);
            $runMinutes = (float) ($data['run_minutes'] ?? 0);

            if ($runMinutes === 0.0 && $schedOp->actual_start) {
                $endDt = $schedOp->isPaused() && $schedOp->last_paused_at ? $schedOp->last_paused_at : $now;
                $pausedSec = $schedOp->accumulated_paused_seconds ?? 0;
                $diffSeconds = $endDt->timestamp - $schedOp->actual_start->timestamp - $pausedSec;
                $totalElapsedMinutes = max(0.0, round($diffSeconds / 60, 2));

                // Subtract previously logged minutes to get the increment
                $previouslyLogged = \App\Domains\Production\Models\ProductionOrderProgressLog::where('operation_id', $orderOp->id)
                    ->sum('run_minutes_logged');
                $runMinutes = max(0.0, $totalElapsedMinutes - $previouslyLogged);
            }

            $produced = (float) ($data['quantity_produced'] ?? 0);
            $rejected = (float) ($data['quantity_rejected'] ?? 0);
            $scrapped = (float) ($data['quantity_scrapped'] ?? 0);

            if ($produced < 0 || $rejected < 0 || $scrapped < 0) {
                throw new InvalidArgumentException('Quantities cannot be negative.');
            }

            $batchId = !empty($data['production_batch_id']) ? (int) $data['production_batch_id'] : (!empty($data['batch_id']) ? (int) $data['batch_id'] : null);

            // Log progress via main ProductionExecutionService
            app(ProductionExecutionService::class)->logProgress(
                $orderOp->id,
                $produced,
                $rejected,
                $scrapped,
                $setupMinutes,
                $runMinutes,
                $data['remarks'] ?? null,
                $schedOp->machine_id,
                $operatorId,
                false, // Do NOT complete operation
                $data['idempotency_key'] ?? null,
                $batchId
            );

            app(ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id' => $schedOp->production_order_id,
                'production_order_operation_id' => $schedOp->production_order_operation_id,
                'machine_id' => $schedOp->machine_id,
                'operator_id' => $operatorId,
                'event_type' => 'Progress Logged',
                'title' => 'Shift/Daily Progress Logged',
                'description' => "Logged partial progress: {$produced} produced, {$rejected} rejected, {$scrapped} scrapped.",
                'severity' => 'info',
                'event_source' => 'MesExecutionService',
                'triggered_by' => $operatorId,
            ]);
        });
    }

    /**
     * Place an operation on hold.
     */
    public function holdOperation(int $scheduleOpId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($scheduleOpId) {
            $schedOp = ProductionScheduleOperation::lockForUpdate()->findOrFail($scheduleOpId);

            $schedOp->update(['status' => ProductionScheduleOperation::STATUS_PAUSED]);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_PAUSED;
                $orderOp->save();
            }
        });
    }

    /**
     * Cancel an operation.
     */
    public function cancelOperation(int $scheduleOpId): void
    {
        DB::transaction(function () use ($scheduleOpId) {
            $schedOp = ProductionScheduleOperation::lockForUpdate()->findOrFail($scheduleOpId);

            if ($schedOp->isCompleted()) {
                throw new InvalidArgumentException('Completed operations cannot be cancelled.');
            }

            $machineId = $schedOp->machine_id;
            if ($machineId && ($schedOp->isRunning() || $schedOp->isPaused())) {
                // If operation was paused, close open downtime first
                $activeDowntime = ProductionMachineDowntime::where('tenant_id', $schedOp->schedule->order->tenant_id)
                    ->where('machine_id', $machineId)
                    ->where('production_order_operation_id', $schedOp->production_order_operation_id)
                    ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                    ->first();
                if ($activeDowntime) {
                    app(DowntimeService::class)->endDowntime(
                        $schedOp->schedule->order->tenant_id,
                        $activeDowntime->id,
                        auth()->id() ?? $schedOp->tenant_id,
                        'Operation Cancelled'
                    );
                } else {
                    // Transition machine state back to Idle
                    app(MachineStateService::class)->transitionState(
                        $schedOp->schedule->order->tenant_id,
                        $machineId,
                        'Idle',
                        'Operation Cancelled'
                    );
                }
            }

            $schedOp->update(['status' => ProductionScheduleOperation::STATUS_CANCELLED]);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp && !$orderOp->isCompleted()) {
                $orderOp->status = ProductionOrderOperation::STATUS_CANCELLED;
                $orderOp->save();
            }
        });
    }

    /**
     * Report an MES Operator Andon Alert / Machine Breakdown.
     */
    public function reportAndonAlert(
        int $schedOpId,
        string $category,
        string $severity,
        string $reason,
        ?string $remarks = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($schedOpId, $category, $severity, $reason, $remarks, $userId) {
            $schedOp = ProductionScheduleOperation::with(['schedule.order', 'workCenter', 'machine'])->findOrFail($schedOpId);
            $tenantId = $schedOp->schedule->tenant_id;

            if ($schedOp->status === ProductionScheduleOperation::STATUS_COMPLETED || $schedOp->status === ProductionScheduleOperation::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot report Andon alert: Operation is completed or cancelled.");
            }

            $order = $schedOp->schedule->order;
            $machineId = $schedOp->machine_id;

            $downtime = null;
            if ($machineId && in_array($category, ['Breakdown', 'Maintenance', 'Equipment Failure', 'Machine Breakdown'], true)) {
                $downtimeCategory = match ($category) {
                    'Maintenance' => 'Preventive Maintenance',
                    default => 'Breakdown',
                };

                $downtimeService = app(DowntimeService::class);
                $downtime = $downtimeService->startDowntime($tenantId, $machineId, $downtimeCategory, $reason, $userId, [
                    'production_order_id' => $order->id,
                    'production_order_operation_id' => $schedOp->production_order_operation_id,
                    'remarks' => $remarks,
                ]);

                if ($schedOp->status === ProductionScheduleOperation::STATUS_RUNNING) {
                    $schedOp->update([
                        'status' => ProductionScheduleOperation::STATUS_PAUSED,
                        'last_paused_at' => now(),
                    ]);

                    $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
                    if ($orderOp) {
                        $orderOp->status = ProductionOrderOperation::STATUS_PAUSED;
                        $orderOp->save();
                    }
                }
            } else {
                app(ProductionEventService::class)->writeEvent($tenantId, [
                    'production_order_id' => $order->id,
                    'machine_id' => $machineId,
                    'event_type' => 'Andon Alert Fired',
                    'title' => "Operator Andon Alert: {$category}",
                    'description' => "Alert raised by operator for sequence #{$schedOp->sequence}: {$reason}. Remarks: {$remarks}",
                    'severity' => $severity,
                    'event_source' => 'MES Execution',
                ]);

                if ($schedOp->status === ProductionScheduleOperation::STATUS_RUNNING && in_array($severity, ['critical', 'warning'], true)) {
                    $this->pauseOperation($schedOpId, "Andon Alert ({$category}): {$reason}", $userId);
                }
            }

            return [
                'operation_id' => $schedOp->id,
                'category' => $category,
                'severity' => $severity,
                'downtime_id' => $downtime?->id,
            ];
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Complete a schedule and sync with the parent Production Order.
     */
    private function completeSchedule(ProductionSchedule $schedule, ?int $operatorId): void
    {
        $schedule->update([
            'status' => ProductionSchedule::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $operatorId,
        ]);

        // Auto-complete parent Production Order if in_progress
        $order = $schedule->order;
        if ($order && $order->isInProgress()) {
            $order->status = ProductionOrder::STATUS_COMPLETED;
            $order->actual_end_date = now();
            $order->completed_by = $operatorId;
            $order->completed_at = now();
            $order->save();
        }
    }

    /**
     * Validate machine is available for MES execution.
     *
     * @throws InvalidArgumentException on any validation failure.
     */
    private function validateMachineForExecution(int $machineId, int $tenantId): void
    {
        $machine = Machine::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($machineId);

        if (!$machine) {
            throw new InvalidArgumentException("Machine #{$machineId} not found.");
        }

        if (!$machine->isActive()) {
            throw new InvalidArgumentException(
                "Machine [{$machine->name}] is not available for production (status: {$machine->status})."
            );
        }
    }

    private function qualityGateIsPendingOrFailed(ProductionOrderOperation $operation): bool
    {
        if (!$operation->routingOperation?->quality_required) {
            return false;
        }

        return !ProductionQualityInspection::where('tenant_id', $operation->tenant_id)
            ->where('production_order_id', $operation->production_order_id)
            ->where(function ($q) use ($operation) {
                $q->where('production_order_operation_id', $operation->id)
                    ->orWhereNull('production_order_operation_id');
            })
            ->where('status', 'approved')
            ->where('result', 'passed')
            ->exists();
    }

    /**
     * Calculate operation readiness and executable parent quantity. (Rule 5 & Rule 11)
     *
     * @return array{is_ready: bool, executable_qty: float, remaining_executable_qty: float, claimed_qty: float, blockers: array}
     */
    public function calculateOperationReadiness(ProductionOrderOperation $op): array
    {
        $op->loadMissing(['previousOperation', 'predecessorDependencies']);
        $order = $op->order;
        $tenantId = $op->tenant_id;
        $blockers = [];
        $warnings = [];

        // 1. Intra-routing predecessor check
        $intraAvailableQty = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 1));
        if ($op->previousOperation) {
            $intraAvailableQty = (float) $op->previousOperation->quantity_produced;
            if ($op->previousOperation->status !== ProductionOrderOperation::STATUS_COMPLETED && $intraAvailableQty <= 0) {
                $blockers[] = "Intra-routing predecessor operation {$op->previousOperation->operation_number} is not completed.";
            }
        }

        // 2. Cross-assembly predecessor check (Executable parent quantity formula - Rule 5 & Rule 11)
        $crossExecutableLimits = [];
        $crossPreds = $op->predecessorDependencies()->wherePivot('dependency_type', 'cross_assembly')->get();
        foreach ($crossPreds as $predOp) {
            $childProductId = $predOp->source_product_id;
            if (!$childProductId) {
                continue;
            }

            // BOM ratio: how many units of SFG per 1 unit of parent
            $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $tenantId)
                ->where('bom_id', $op->source_bom_id ?? $order->bom_id)
                ->where('material_id', $childProductId)
                ->first();
            $bomRatio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;

            // Issued material stock directly for this order
            $issuedToOrder = (float) \App\Domains\Production\Models\ProductionOrderReservation::where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->where('product_id', $childProductId)
                ->sum('quantity_issued');

            // Warehouse Reserved stock & Available stock for order (Rule 2)
            $reservedWarehouseStock = (float) \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('product_id', $childProductId)
                ->sum('reserved_qty');
            $availableWarehouseStock = (float) \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('product_id', $childProductId)
                ->sum('quantity');

            $warehouseStock = max($reservedWarehouseStock, $availableWarehouseStock);

            // Usable Intermediate Manufactured SFG = Produced - (QC Hold + Rework) - Consumed (Rule 11)
            $predOpFresh = ProductionOrderOperation::find($predOp->id) ?? $predOp;
            $produced = (float) $predOpFresh->quantity_produced;
            $qcHold = (float) ($predOpFresh->active_qc_hold ?? 0.0);
            $rework = (float) ($predOpFresh->active_rework ?? 0.0);
            $consumed = (float) ($predOpFresh->quantity_consumed ?? 0.0);

            $usableIntermediateSfg = max(0.0, $produced - ($qcHold + $rework) - $consumed);
            $totalUsableSfg = $issuedToOrder + $warehouseStock + $usableIntermediateSfg;

            if ($issuedToOrder <= 0 && $warehouseStock > 0 && $usableIntermediateSfg <= 0) {
                $childProduct = \App\Domains\Inventory\Models\Product::find($childProductId);
                $childName = $childProduct->name ?? "Product #{$childProductId}";
                $warnings[] = "Stock available in Warehouse ({$warehouseStock} units of {$childName}), but Material Issue to Order from Store is pending (0.00 issued).";
            }

            $maxExecutableParentQtyForThisComponent = $totalUsableSfg / $bomRatio;
            $crossExecutableLimits[] = $maxExecutableParentQtyForThisComponent;

            if ($totalUsableSfg <= 0 && $predOp->status !== ProductionOrderOperation::STATUS_COMPLETED) {
                $blockers[] = "Cross-assembly predecessor operation {$predOp->operation_number} for product ID {$childProductId} has insufficient usable SFG (Available: {$totalUsableSfg}, Required BOM Ratio: {$bomRatio}).";
            }
        }

        $minCrossExecutable = !empty($crossExecutableLimits) ? min($crossExecutableLimits) : (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 1));
        $executableQty = min($intraAvailableQty, $minCrossExecutable);

        // Subtract already claimed quantity
        $claimedQty = (float) ($op->quantity_claimed ?? 0.0);
        $remainingExecutableQty = max(0.0, $executableQty - $claimedQty);

        $isReady = empty($blockers) && $remainingExecutableQty > 0;

        return [
            'is_ready' => $isReady,
            'executable_qty' => $executableQty,
            'remaining_executable_qty' => $remainingExecutableQty,
            'claimed_qty' => $claimedQty,
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /**
     * Atomically claim a batch quantity to execute on an operation with lockForUpdate and idempotency key check. (F-02)
     */
    public function claimBatchToExecute(
        int $operationId,
        float $requestedQty,
        ?string $idempotencyKey = null
    ): float {
        return DB::transaction(function () use ($operationId, $requestedQty, $idempotencyKey) {
            $op = ProductionOrderOperation::lockForUpdate()->findOrFail($operationId);

            // Idempotency check using ProductionWipTransaction batch_claimed audit record (F-02)
            if (!empty($idempotencyKey)) {
                $existingClaimTx = ProductionWipTransaction::where('tenant_id', $op->tenant_id)
                    ->where('production_order_id', $op->production_order_id)
                    ->where('transaction_type', 'batch_claimed')
                    ->where('remarks', 'LIKE', "%IDEMPOTENCY_KEY:{$idempotencyKey}%")
                    ->first();
                if ($existingClaimTx) {
                    return (float) $op->quantity_claimed;
                }
            }

            $readiness = $this->calculateOperationReadiness($op);
            if ($requestedQty > $readiness['remaining_executable_qty']) {
                throw new InvalidArgumentException(
                    "Cannot claim {$requestedQty} units for operation {$op->operation_number}. Remaining executable quantity is {$readiness['remaining_executable_qty']} (Max Executable: {$readiness['executable_qty']}, Already Claimed: {$readiness['claimed_qty']})."
                );
            }

            $op->increment('quantity_claimed', $requestedQty);

            // Record claim audit transaction for idempotency tracking (F-02)
            $opWip = ProductionWip::where('tenant_id', $op->tenant_id)
                ->where('production_order_id', $op->production_order_id)
                ->first();
            if (!$opWip) {
                $opWip = app(ProductionWipService::class)->initializeWip($op->production_order_id, null, null);
            }

            ProductionWipTransaction::create([
                'tenant_id' => $op->tenant_id,
                'wip_id' => $opWip->id,
                'production_order_id' => $op->production_order_id,
                'from_operation_id' => $op->routing_operation_id,
                'to_operation_id' => $op->routing_operation_id,
                'from_work_center_id' => $op->work_center_id,
                'to_work_center_id' => $op->work_center_id,
                'transaction_type' => 'batch_claimed',
                'quantity' => $requestedQty,
                'cost_before' => 0.0000,
                'cost_added' => 0.0000,
                'cost_after' => 0.0000,
                'remarks' => "Batch claimed: {$requestedQty} units for Op {$op->operation_number}." . (!empty($idempotencyKey) ? " IDEMPOTENCY_KEY:{$idempotencyKey}" : ""),
                'transaction_at' => now(),
            ]);

            return (float) $op->fresh()->quantity_claimed;
        });
    }
}
