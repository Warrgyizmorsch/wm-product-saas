<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
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
     */
    public function startOperation(int $scheduleOpId, ?int $machineId, ?int $operatorId): void
    {
        DB::transaction(function () use ($scheduleOpId, $machineId, $operatorId) {
            $schedOp = ProductionScheduleOperation::with(['schedule', 'order'])
                ->findOrFail($scheduleOpId);

            if (!$schedOp->canStart()) {
                throw new InvalidArgumentException(
                    "Operation cannot be started. Current status: [{$schedOp->status}]. Only 'ready' operations can be started."
                );
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

            // Validate machine if provided
            $resolvedMachineId = $machineId ?? $schedOp->machine_id;
            if ($resolvedMachineId) {
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
                        "Machine is already running another operation. Cannot start a second operation on the same machine simultaneously."
                    );
                }
            }

            // Update schedule operation
            $schedOp->update([
                'status'            => ProductionScheduleOperation::STATUS_RUNNING,
                'actual_start'      => now(),
                'machine_id'        => $resolvedMachineId ?? $schedOp->machine_id,
                'actual_machine_id' => $resolvedMachineId ?? $schedOp->machine_id,
            ]);

            // Sync the underlying ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status           = ProductionOrderOperation::STATUS_RUNNING;
                $orderOp->actual_start_time = $orderOp->actual_start_time ?? now();
                $orderOp->operator_id       = $operatorId;
                $orderOp->machine_used_id   = $resolvedMachineId ?? $orderOp->machine_id;
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
                $order->status            = ProductionOrder::STATUS_IN_PROGRESS;
                $order->actual_start_date = now();
                $order->save();
            }

            // Transition machine state if machine is used
            $resolvedMachineId = $machineId ?? $schedOp->machine_id;
            if ($resolvedMachineId) {
                app(\App\Domains\Production\Services\MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $resolvedMachineId,
                    'Running',
                    'Production Started',
                    $operatorId
                );
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id'            => $schedOp->production_order_id,
                'production_order_operation_id'  => $schedOp->production_order_operation_id,
                'machine_id'                     => $resolvedMachineId,
                'operator_id'                    => $operatorId,
                'event_type'                     => 'Operation Started',
                'title'                          => 'Operation Started',
                'description'                    => "Operation {$schedOp->orderOperation->name} has started on the shop floor.",
                'severity'                       => 'info',
                'event_source'                   => 'MesExecutionService',
                'triggered_by'                   => $operatorId,
            ]);
        });
    }

    /**
     * Pause a running operation.
     */
    public function pauseOperation(int $scheduleOpId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($scheduleOpId, $remarks) {
            $schedOp = ProductionScheduleOperation::findOrFail($scheduleOpId);

            if (!$schedOp->isRunning()) {
                throw new InvalidArgumentException(
                    "Only running operations can be paused. Current status: [{$schedOp->status}]."
                );
            }

            $schedOp->update(['status' => ProductionScheduleOperation::STATUS_PAUSED]);

            // Sync ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_PAUSED;
                $orderOp->save();
            }

            $machineId = $schedOp->machine_id;
            if ($machineId) {
                $reason = $remarks ?? 'Operation Paused';
                $state = 'Waiting Operator';
                if (str_contains(strtolower($reason), 'material')) {
                    $state = 'Waiting Material';
                } elseif (str_contains(strtolower($reason), 'breakdown') || str_contains(strtolower($reason), 'failure')) {
                    $state = 'Breakdown';
                }
                app(\App\Domains\Production\Services\MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $machineId,
                    $state,
                    $reason
                );
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id'            => $schedOp->production_order_id,
                'production_order_operation_id'  => $schedOp->production_order_operation_id,
                'machine_id'                     => $machineId,
                'event_type'                     => 'Operation Paused',
                'title'                          => 'Operation Paused',
                'description'                    => "Operation paused. Reason: {$remarks}",
                'severity'                       => 'warning',
                'event_source'                   => 'MesExecutionService',
            ]);
        });
    }

    /**
     * Resume a paused operation.
     */
    public function resumeOperation(int $scheduleOpId): void
    {
        DB::transaction(function () use ($scheduleOpId) {
            $schedOp = ProductionScheduleOperation::findOrFail($scheduleOpId);

            if (!$schedOp->isPaused()) {
                throw new InvalidArgumentException(
                    "Only paused operations can be resumed. Current status: [{$schedOp->status}]."
                );
            }

            $schedOp->update(['status' => ProductionScheduleOperation::STATUS_RUNNING]);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $orderOp->status = ProductionOrderOperation::STATUS_RUNNING;
                $orderOp->save();
            }

            $machineId = $schedOp->machine_id;
            if ($machineId) {
                app(\App\Domains\Production\Services\MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $machineId,
                    'Running',
                    'Operation Resumed'
                );
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id'            => $schedOp->production_order_id,
                'production_order_operation_id'  => $schedOp->production_order_operation_id,
                'machine_id'                     => $machineId,
                'event_type'                     => 'Operation Resumed',
                'title'                          => 'Operation Resumed',
                'description'                    => "Operation has been resumed.",
                'severity'                       => 'info',
                'event_source'                   => 'MesExecutionService',
            ]);
        });
    }

    /**
     * Complete an operation, log progress, and advance the routing sequence.
     *
     * Side effects:
     * - Populates actual_finish on ProductionScheduleOperation.
     * - Creates ProductionOrderProgressLog.
     * - Marks next operation as ready (if exists).
     * - Auto-completes schedule and order if this was the last operation.
     */
    public function completeOperation(int $scheduleOpId, array $data, ?int $operatorId): void
    {
        DB::transaction(function () use ($scheduleOpId, $data, $operatorId) {
            $schedOp = ProductionScheduleOperation::with(['schedule', 'order'])->findOrFail($scheduleOpId);

            if (!$schedOp->isRunning() && !$schedOp->isPaused()) {
                throw new InvalidArgumentException(
                    "Only running or paused operations can be completed. Current status: [{$schedOp->status}]."
                );
            }

            $now = now();

            // Update schedule operation timing
            $schedOp->update([
                'status'        => ProductionScheduleOperation::STATUS_COMPLETED,
                'actual_finish' => $now,
            ]);

            // Sync ProductionOrderOperation
            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp) {
                $produced      = (float) ($data['quantity_produced'] ?? 0);
                $rejected      = (float) ($data['quantity_rejected'] ?? 0);
                $scrapped      = (float) ($data['quantity_scrapped'] ?? 0);
                $setupMinutes  = (float) ($data['setup_minutes'] ?? 0);
                $runMinutes    = (float) ($data['run_minutes'] ?? 0);

                // Create progress log
                ProductionOrderProgressLog::create([
                    'tenant_id'            => $schedOp->order->tenant_id,
                    'production_order_id'  => $schedOp->production_order_id,
                    'operation_id'         => $orderOp->id,
                    'quantity_produced'    => $produced,
                    'quantity_rejected'    => $rejected,
                    'quantity_scrapped'    => $scrapped,
                    'setup_minutes_logged' => $setupMinutes,
                    'run_minutes_logged'   => $runMinutes,
                    'recorded_by'          => $operatorId,
                    'recorded_at'          => $now,
                    'machine_id'           => $schedOp->machine_id,
                    'start_time'           => $schedOp->actual_start,
                    'stop_time'            => $now,
                    'remarks'              => $data['remarks'] ?? null,
                ]);

                // Update order operation
                $orderOp->status                  = ProductionOrderOperation::STATUS_COMPLETED;
                $orderOp->actual_end_time         = $now;
                $orderOp->setup_time_actual       += $setupMinutes;
                $orderOp->processing_time_actual  += $runMinutes;
                $orderOp->quantity_produced       += $produced;
                $orderOp->quantity_rejected       += $rejected;
                $orderOp->quantity_scrapped       += $scrapped;
                $orderOp->save();
            }

            // Advance next schedule operation to ready
            $nextSchedOp = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '>', $schedOp->sequence)
                ->orderBy('sequence')
                ->first();

            if ($nextSchedOp && $nextSchedOp->isWaiting()) {
                $nextSchedOp->update(['status' => ProductionScheduleOperation::STATUS_READY]);

                // Sync next order operation too
                $nextOrderOp = ProductionOrderOperation::find($nextSchedOp->production_order_operation_id);
                if ($nextOrderOp && $nextOrderOp->status === ProductionOrderOperation::STATUS_WAITING) {
                    $nextOrderOp->status = ProductionOrderOperation::STATUS_READY;
                    $nextOrderOp->save();
                }
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
                app(\App\Domains\Production\Services\MachineStateService::class)->transitionState(
                    $schedOp->schedule->order->tenant_id,
                    $machineId,
                    'Idle',
                    'Operation Completed',
                    $operatorId
                );
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedOp->schedule->order->tenant_id, [
                'production_order_id'            => $schedOp->production_order_id,
                'production_order_operation_id'  => $schedOp->production_order_operation_id,
                'machine_id'                     => $machineId,
                'event_type'                     => 'Operation Completed',
                'title'                          => 'Operation Completed',
                'description'                    => "Operation has been completed successfully.",
                'severity'                       => 'success',
                'event_source'                   => 'MesExecutionService',
                'triggered_by'                   => $operatorId,
            ]);
        });
    }

    /**
     * Place an operation on hold.
     */
    public function holdOperation(int $scheduleOpId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($scheduleOpId, $remarks) {
            $schedOp = ProductionScheduleOperation::findOrFail($scheduleOpId);

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
            $schedOp = ProductionScheduleOperation::findOrFail($scheduleOpId);

            if ($schedOp->isCompleted()) {
                throw new InvalidArgumentException("Completed operations cannot be cancelled.");
            }

            $schedOp->update(['status' => ProductionScheduleOperation::STATUS_CANCELLED]);

            $orderOp = ProductionOrderOperation::find($schedOp->production_order_operation_id);
            if ($orderOp && !$orderOp->isCompleted()) {
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
            'status'       => ProductionSchedule::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $operatorId,
        ]);

        // Auto-complete parent Production Order if in_progress
        $order = $schedule->order;
        if ($order && $order->isInProgress()) {
            $order->status           = ProductionOrder::STATUS_COMPLETED;
            $order->actual_end_date  = now();
            $order->completed_by     = $operatorId;
            $order->completed_at     = now();
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
        $machine = Machine::withoutGlobalScopes()->find($machineId);

        if (!$machine) {
            throw new InvalidArgumentException("Machine #{$machineId} not found.");
        }

        if ($machine->tenant_id !== $tenantId) {
            throw new InvalidArgumentException("Machine does not belong to this tenant.");
        }

        if (!$machine->isActive()) {
            throw new InvalidArgumentException(
                "Machine [{$machine->name}] is not available for production (status: {$machine->status})."
            );
        }
    }
}
