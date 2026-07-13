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

            if (! $schedOp->canStart()) {
                throw new InvalidArgumentException(
                    "Operation cannot be started. Current status: [{$schedOp->status}]. Only 'ready' operations can be started."
                );
            }

            // Sync with underlying ProductionOrderOperation and check skills qualification
            // Skills validation is opt-in: only enforced if tenant has skills configured
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
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

            // Validate predecessor operations are complete
            $predecessors = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '<', $schedOp->sequence)
                ->whereNotIn('status', [
                    ProductionScheduleOperation::STATUS_COMPLETED,
                    ProductionScheduleOperation::STATUS_SKIPPED,
                    ProductionScheduleOperation::STATUS_CANCELLED,
                ])
                ->exists();

            if ($predecessors) {
                throw new InvalidArgumentException(
                    "Cannot start operation #{$schedOp->sequence}. All predecessor operations must be completed or skipped first."
                );
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

            if (! $schedOp->isRunning()) {
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
                $reason = $remarks ?? 'Operation Paused';
                $category = 'Operator Shortage';
                if (str_contains(strtolower($reason), 'material')) {
                    $category = 'Material Shortage';
                } elseif (str_contains(strtolower($reason), 'breakdown') || str_contains(strtolower($reason), 'failure')) {
                    $category = 'Breakdown';
                }

                // startDowntime will automatically transition machine state and write events
                app(DowntimeService::class)->startDowntime(
                    $schedOp->schedule->order->tenant_id,
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

            if (! $schedOp->isPaused()) {
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
                'accumulated_paused_seconds' => $schedOp->accumulated_paused_seconds + $pausedSeconds,
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
                        'Operation Resumed'
                    );
                }

                // Always transition machine state back to Running on resume
                app(MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $machineId,
                    'Running',
                    'Operation Resumed',
                    $userId
                );
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

            if (! $schedOp->isRunning() && ! $schedOp->isPaused()) {
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
                'accumulated_paused_seconds' => $schedOp->accumulated_paused_seconds + $pausedSeconds,
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

                if ($produced < 0 || $rejected < 0 || $scrapped < 0) {
                    throw new InvalidArgumentException('Quantities cannot be negative.');
                }

                // Overproduction Limit Check
                $plannedQty = (float) $schedOp->schedule->order->quantity_ordered;
                $totalProcessedSoFar = $orderOp->quantity_produced + $orderOp->quantity_scrapped;
                $currentProcessed = $produced + $scrapped;
                $totalProcessed = $totalProcessedSoFar + $currentProcessed;

                $tenant = $schedOp->schedule->order->tenant;
                $limitPercent = (float) ($tenant->settings['overproduction_limit_percentage'] ?? 20.0);
                $maxAllowed = $plannedQty * (1 + $limitPercent / 100);

                if ($totalProcessed > $maxAllowed) {
                    throw new InvalidArgumentException("Quantity exceeds the allowed overproduction limit of {$limitPercent}% (Max allowed: {$maxAllowed} units).");
                }

                if ($this->qualityGateIsPendingOrFailed($orderOp)) {
                    throw new InvalidArgumentException('This operation requires an approved passed quality inspection before completion.');
                }

                // Create progress log
                ProductionOrderProgressLog::create([
                    'tenant_id' => $schedOp->order->tenant_id,
                    'production_order_id' => $schedOp->production_order_id,
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

                // Update order operation
                $orderOp->status = ProductionOrderOperation::STATUS_COMPLETED;
                $orderOp->actual_end_time = $now;
                $orderOp->setup_time_actual += $setupMinutes;
                $orderOp->processing_time_actual += $runMinutes;
                $orderOp->quantity_produced += $produced;
                $orderOp->quantity_rejected += $rejected;
                $orderOp->quantity_scrapped += $scrapped;
                $orderOp->save();
            }

            // Advance next schedule operations to ready (including parallel operations sharing the same next sequence)
            $nextSequence = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '>', $schedOp->sequence)
                ->min('sequence');

            if ($nextSequence) {
                $nextSchedOps = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                    ->where('sequence', $nextSequence)
                    ->get();

                foreach ($nextSchedOps as $nsOp) {
                    if ($nsOp->isWaiting()) {
                        $nsOp->update(['status' => ProductionScheduleOperation::STATUS_READY]);

                        // Sync next order operation too
                        $nextOrderOp = ProductionOrderOperation::find($nsOp->production_order_operation_id);
                        if ($nextOrderOp && $nextOrderOp->status === ProductionOrderOperation::STATUS_WAITING) {
                            $nextOrderOp->status = ProductionOrderOperation::STATUS_READY;
                            $nextOrderOp->save();
                        }
                    }
                }
            }

            // Check if all schedule operations are terminal — if so, auto-complete schedule
            $allDone = ! ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
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
            if ($orderOp && ! $orderOp->isCompleted()) {
                $orderOp->status = ProductionOrderOperation::STATUS_CANCELLED;
                $orderOp->save();
            }
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

        if (! $machine) {
            throw new InvalidArgumentException("Machine #{$machineId} not found.");
        }

        if (! $machine->isActive()) {
            throw new InvalidArgumentException(
                "Machine [{$machine->name}] is not available for production (status: {$machine->status})."
            );
        }
    }

    private function qualityGateIsPendingOrFailed(ProductionOrderOperation $operation): bool
    {
        if (! $operation->routingOperation?->quality_required) {
            return false;
        }

        return ! ProductionQualityInspection::where('tenant_id', $operation->tenant_id)
            ->where('production_order_operation_id', $operation->id)
            ->where('status', 'approved')
            ->where('result', 'passed')
            ->exists();
    }
}
