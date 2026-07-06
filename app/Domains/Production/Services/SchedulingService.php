<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchedulingService
{
    public function __construct(
        private readonly ProductionScheduleNumberService $numberService
    ) {}

    /**
     * Generate a complete schedule for the given Production Order.
     *
     * Currently only Forward Scheduling is implemented.
     * Backward and Manual scheduling will be plugged in without changing this signature.
     *
     * @throws \LogicException if an unsupported scheduling type is requested.
     */
    public function generateSchedule(
        ProductionOrder $order,
        Carbon $startDate,
        string $type = ProductionSchedule::TYPE_FORWARD
    ): ProductionSchedule {
        if ($type !== ProductionSchedule::TYPE_FORWARD) {
            throw new \LogicException(
                "Scheduling type [{$type}] is not yet implemented. Only 'forward' scheduling is currently supported."
            );
        }

        return DB::transaction(function () use ($order, $startDate, $type) {
            // Remove any existing draft schedule for this order
            ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [ProductionSchedule::STATUS_DRAFT, ProductionSchedule::STATUS_SCHEDULED])
                ->delete();

            $tenantId = $order->tenant_id;

            $schedule = ProductionSchedule::create([
                'tenant_id'           => $tenantId,
                'schedule_number'     => $this->numberService->generateNextNumber($tenantId),
                'production_order_id' => $order->id,
                'scheduling_type'     => $type,
                'status'              => ProductionSchedule::STATUS_SCHEDULED,
                'scheduled_at'        => now(),
                'created_by'          => auth()->id() ?: 1,
            ]);

            $operations = $order->operations()->orderBy('sequence')->get();
            $cursor     = $startDate->copy();

            foreach ($operations as $op) {
                $times      = $this->calculateOperationTimes($op, $order->quantity_ordered);
                $workCenter = $this->allocateWorkCenter($op);
                $machine    = $this->allocateMachine($op, $workCenter, $tenantId);

                $durationMinutes = $times['setup_minutes'] + $times['processing_minutes'];
                $plannedFinish   = $cursor->copy()->addMinutes((int) ceil($durationMinutes));

                ProductionScheduleOperation::create([
                    'tenant_id'                     => $tenantId,
                    'production_schedule_id'        => $schedule->id,
                    'production_order_id'           => $order->id,
                    'production_order_operation_id' => $op->id,
                    'work_center_id'                => $workCenter->id,
                    'machine_id'                    => $machine?->id,
                    'sequence'                      => $op->sequence,
                    'priority'                      => 1,
                    'planned_start'                 => $cursor->copy(),
                    'planned_finish'                => $plannedFinish,
                    'planned_duration_minutes'      => $durationMinutes,
                    // First operation is ready; rest wait for predecessor
                    'status' => $op->sequence === $operations->first()->sequence
                        ? ProductionScheduleOperation::STATUS_READY
                        : ProductionScheduleOperation::STATUS_WAITING,
                ]);

                // Forward scheduling: next op starts when this one finishes
                $cursor = $plannedFinish->copy();
            }

            return $schedule;
        });
    }

    /**
     * Calculate planned setup and processing minutes for a single operation.
     */
    public function calculateOperationTimes(ProductionOrderOperation $op, float $quantity): array
    {
        $setupMinutes      = (float) $op->setup_time_planned;
        $processingPerUnit = (float) $op->processing_time_planned;
        $processingMinutes = $processingPerUnit * $quantity;

        return [
            'setup_minutes'      => $setupMinutes,
            'processing_minutes' => $processingMinutes,
            'total_minutes'      => $setupMinutes + $processingMinutes,
        ];
    }

    /**
     * Resolve the Work Center for the operation.
     *
     * @throws \LogicException if work center is not found or inactive.
     */
    public function allocateWorkCenter(ProductionOrderOperation $op): WorkCenter
    {
        $wc = WorkCenter::withoutGlobalScopes()->find($op->work_center_id);

        if (!$wc) {
            throw new \LogicException(
                "Work Center #{$op->work_center_id} not found for operation [{$op->operation_number}]."
            );
        }

        if (!$wc->isActive()) {
            throw new \LogicException(
                "Work Center [{$wc->name}] is inactive and cannot be scheduled."
            );
        }

        return $wc;
    }

    /**
     * Resolve and validate the Machine for the operation (if any).
     * Validates: active status, same tenant, same work center.
     *
     * @throws \LogicException if the machine fails validation.
     */
    public function allocateMachine(
        ProductionOrderOperation $op,
        WorkCenter $workCenter,
        int $tenantId
    ): ?Machine {
        if (!$op->machine_id) {
            return null;
        }

        $machine = Machine::withoutGlobalScopes()->find($op->machine_id);

        if (!$machine) {
            throw new \LogicException(
                "Machine #{$op->machine_id} not found for operation [{$op->operation_number}]."
            );
        }

        if ($machine->tenant_id !== $tenantId) {
            throw new \LogicException(
                "Machine [{$machine->name}] does not belong to this tenant."
            );
        }

        if ($machine->work_center_id !== $workCenter->id) {
            throw new \LogicException(
                "Machine [{$machine->name}] is not assigned to Work Center [{$workCenter->name}]."
            );
        }

        if (!$machine->isActive()) {
            throw new \LogicException(
                "Machine [{$machine->name}] is not active (status: {$machine->status}). Cannot schedule on unavailable machine."
            );
        }

        return $machine;
    }

    /**
     * Calculate effective capacity for a work center on a given date.
     *
     * This is a placeholder for future Shift Planning integration.
     * Currently assumes 8-hour shifts based on work center efficiency.
     *
     * Future: query shift tables, holiday calendar, overtime rules.
     */
    public function calculateCapacity(int $workCenterId, Carbon $date): float
    {
        $wc = WorkCenter::withoutGlobalScopes()->find($workCenterId);

        if (!$wc || !$wc->isActive()) {
            return 0.0;
        }

        $hoursPerShift   = 8.0;
        $efficiency      = max(0.01, ($wc->efficiency_percentage ?? 100) / 100.0);
        $capacityPerHour = $wc->capacity_per_hour ?? 1.0;

        return $hoursPerShift * $efficiency * $capacityPerHour * 60.0; // in minutes
    }

    /**
     * Validate a generated schedule:
     * - All work centers active.
     * - All machines active, same tenant, same work center.
     *
     * @throws \LogicException on the first validation failure found.
     */
    public function validateSchedule(ProductionSchedule $schedule): void
    {
        $tenantId = $schedule->order->tenant_id;

        foreach ($schedule->operations()->with(['workCenter', 'machine'])->get() as $op) {
            if (!$op->workCenter || !$op->workCenter->isActive()) {
                throw new \LogicException(
                    "Work Center for operation [{$op->sequence}] is inactive or missing."
                );
            }

            if ($op->machine_id && $op->machine) {
                if ($op->machine->tenant_id !== $tenantId) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] does not belong to this tenant."
                    );
                }
                if ($op->machine->work_center_id !== $op->work_center_id) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] does not belong to Work Center [{$op->workCenter->name}]."
                    );
                }
                if (!$op->machine->isActive()) {
                    throw new \LogicException(
                        "Machine [{$op->machine->name}] is not active (status: {$op->machine->status})."
                    );
                }
            }
        }
    }

    /**
     * Detect scheduling conflicts (machine time overlaps) across all active schedules for the tenant.
     *
     * Returns an array of warning messages. Empty array = no conflicts.
     */
    public function detectOverloads(int $tenantId): array
    {
        $warnings = [];

        // Find machines with multiple running/ready operations overlapping in time
        $ops = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereHas('schedule', function ($q) use ($tenantId) {
                $q->withoutGlobalScopes()
                  ->where('tenant_id', $tenantId)
                  ->whereIn('status', [
                      ProductionSchedule::STATUS_SCHEDULED,
                      ProductionSchedule::STATUS_RELEASED,
                  ]);
            })
            ->whereNotNull('machine_id')
            ->whereNotIn('status', [
                ProductionScheduleOperation::STATUS_COMPLETED,
                ProductionScheduleOperation::STATUS_CANCELLED,
                ProductionScheduleOperation::STATUS_SKIPPED,
            ])
            ->with(['machine', 'schedule', 'order'])
            ->orderBy('machine_id')
            ->orderBy('planned_start')
            ->get();

        // Group by machine and find overlapping time slots
        $grouped = $ops->groupBy('machine_id');

        foreach ($grouped as $machineId => $machineOps) {
            $machineOps = $machineOps->sortBy('planned_start')->values();

            for ($i = 0; $i < $machineOps->count() - 1; $i++) {
                $current = $machineOps[$i];
                $next    = $machineOps[$i + 1];

                if ($current->planned_finish > $next->planned_start) {
                    $machineName = $current->machine?->name ?? "Machine #{$machineId}";
                    $warnings[]  = "Conflict: [{$machineName}] has overlapping operations — "
                        . "Schedule [{$current->schedule->schedule_number}] operation #{$current->sequence} "
                        . "finishes at {$current->planned_finish->format('d/m/Y H:i')} "
                        . "but next operation starts at {$next->planned_start->format('d/m/Y H:i')}.";
                }
            }
        }

        return $warnings;
    }
}
