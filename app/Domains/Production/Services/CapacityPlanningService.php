<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Services\ProductionEventService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CapacityPlanningService
{
    public function __construct(
        private readonly SchedulingService $schedulingService
    ) {}

    /**
     * Get bounded, tenant-scoped data payload for the Interactive Dispatch Board.
     */
    public function getDispatchBoardData(int $tenantId, Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        if ($startDate->diffInDays($endDate) > 62) {
            throw new \InvalidArgumentException('Date range cannot exceed 62 days.');
        }

        $startWindow = $startDate->copy()->startOfDay();
        $endWindow   = $endDate->copy()->endOfDay();

        // 1. Resources (Work Centers and Child Machines)
        $wcQuery = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with(['machines' => fn ($q) => $q->where('tenant_id', $tenantId)]);

        if (!empty($filters['work_center_id'])) {
            $wcQuery->where('id', $filters['work_center_id']);
        }

        $workCenters = $wcQuery->get();
        $resources = $workCenters->map(function ($wc) {
            return [
                'id'                    => $wc->id,
                'code'                  => $wc->code,
                'name'                  => $wc->name,
                'type'                  => 'work_center',
                'efficiency_percentage' => (float) $wc->efficiency_percentage,
                'machines'              => $wc->machines->map(fn ($m) => [
                    'id'             => $m->id,
                    'code'           => $m->code,
                    'name'           => $m->name,
                    'type'           => 'machine',
                    'work_center_id' => $m->work_center_id,
                    'status'         => $m->status,
                ])->values()->all(),
            ];
        })->values()->all();

        // 2. Scheduled Operations
        $opsQuery = ProductionScheduleOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with([
                'schedule',
                'order.product',
                'orderOperation',
                'workCenter',
                'machine',
            ])
            ->whereBetween('planned_start', [$startWindow, $endWindow]);

        if (!empty($filters['work_center_id'])) {
            $opsQuery->where('work_center_id', $filters['work_center_id']);
        }
        if (!empty($filters['machine_id'])) {
            $opsQuery->where('machine_id', $filters['machine_id']);
        }
        if (!empty($filters['production_order_id'])) {
            $opsQuery->where('production_order_id', $filters['production_order_id']);
        }
        if (!empty($filters['schedule_id'])) {
            $opsQuery->where('production_schedule_id', $filters['schedule_id']);
        }
        if (!empty($filters['status'])) {
            $opsQuery->where('status', $filters['status']);
        }

        $operations = $opsQuery->orderBy('sequence')->get()->map(function ($op) {
            $orderOp = $op->orderOperation;
            return [
                'schedule_operation_id'   => $op->id,
                'schedule_id'             => $op->production_schedule_id,
                'production_order_id'     => $op->production_order_id,
                'production_order_number' => $op->order ? $op->order->order_number : null,
                'product_name'            => $op->order && $op->order->product ? $op->order->product->name : null,
                'production_order_operation_id' => $op->production_order_operation_id,
                'operation_name'          => $orderOp ? $orderOp->name : 'Operation #' . $op->sequence,
                'operation_number'        => $orderOp ? $orderOp->operation_number : 'OP' . $op->sequence,
                'sequence'                => $op->sequence,
                'work_center_id'          => $op->work_center_id,
                'machine_id'              => $op->machine_id,
                'planned_start'           => $op->planned_start ? $op->planned_start->toIso8601String() : null,
                'planned_finish'          => $op->planned_finish ? $op->planned_finish->toIso8601String() : null,
                'baseline_start'          => $op->baseline_start ? $op->baseline_start->toIso8601String() : null,
                'baseline_finish'         => $op->baseline_finish ? $op->baseline_finish->toIso8601String() : null,
                'start_variance_minutes'  => $op->start_variance_minutes,
                'finish_variance_minutes' => $op->finish_variance_minutes,
                'planned_duration_minutes'=> (float) $op->planned_duration_minutes,
                'status'                  => $op->status,
                'locked'                  => (bool) $op->locked,
                'manual_override'         => (bool) $op->manual_override,
                'version'                 => (int) $op->version,
                'priority'                => (int) $op->priority,
                'overlap_enabled'         => $orderOp ? (bool) $orderOp->overlap_enabled : false,
                'transfer_batch_quantity' => $orderOp ? (float) $orderOp->transfer_batch_quantity : 0.0,
                'transfer_lag_minutes'    => $orderOp ? (float) $orderOp->transfer_lag_minutes : 0.0,
            ];
        })->values()->all();

        // 3. Machine Downtimes
        $downtimes = ProductionMachineDowntime::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$startWindow, $endWindow])
            ->get()
            ->map(fn ($d) => [
                'id'         => $d->id,
                'machine_id' => $d->machine_id,
                'start_time' => $d->start_time ? $d->start_time->toIso8601String() : null,
                'end_time'   => $d->end_time ? $d->end_time->toIso8601String() : null,
                'reason'     => $d->reason,
                'status'     => $d->status,
            ])->values()->all();

        // 4. Capacity Load Metrics
        $capacity = $this->getDailyLoad($tenantId, $startDate, $endDate);

        // 5. Active Schedule Warnings & Conflicts
        $warnings = [];
        $detectedConflicts = $this->schedulingService->detectConflicts($tenantId);
        foreach ($detectedConflicts as $msg) {
            $warnings[] = [
                'type'     => 'MACHINE_CONFLICT',
                'severity' => 'error',
                'message'  => $msg,
            ];
        }

        $detectedOverloads = $this->schedulingService->detectOverloads($tenantId);
        foreach ($detectedOverloads as $msg) {
            $warnings[] = [
                'type'     => 'CAPACITY_OVERLOAD',
                'severity' => 'warning',
                'message'  => $msg,
            ];
        }

        return [
            'range' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'resources'  => $resources,
            'operations' => $operations,
            'downtimes'  => $downtimes,
            'capacity'   => $capacity,
            'warnings'   => $warnings,
            'meta'       => [
                'total_operations' => count($operations),
                'total_resources'  => count($resources),
                'filters_applied'  => array_filter($filters),
            ],
        ];
    }

    /**
     * Get capacity planning details for work centers.
     */
    public function getWorkCenterCapacity(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $details = [];

        $allConflicts = $this->schedulingService->detectConflicts($tenantId);

        foreach ($workCenters as $wc) {
            $totalCapacity = 0.0;
            $date = $startDate->copy()->startOfDay();
            while ($date->lte($endDate)) {
                $totalCapacity += $this->schedulingService->calculateCapacity($wc->id, $date);
                $date->addDay();
            }

            $ops = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
                ->with('orderOperation')
                ->get();

            $required = (float) $ops->sum('planned_duration_minutes');
            $setup = (float) $ops->sum(fn($o) => (float) ($o->orderOperation?->setup_time_planned ?? 0.0));
            $run = max(0.0, $required - $setup);

            $downtime = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('work_center_id', $wc->id)
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('duration_minutes');

            $available = max(0.0, $totalCapacity - $downtime);
            $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);
            $overload = max(0.0, $required - $available);
            $free = max(0.0, $available - $required);

            $status = 'available';
            if ($available <= 0) {
                $status = 'unavailable';
            } elseif ($utilization > 100) {
                $status = 'overloaded';
            } elseif ($utilization > 85) {
                $status = 'near_capacity';
            } elseif ($utilization > 0) {
                $status = 'balanced';
            }

            $wcConflicts = collect($allConflicts)->filter(function($c) use ($wc) {
                return str_contains(strtolower($c), strtolower($wc->name)) || str_contains($c, "Work Center #{$wc->id}");
            })->count();

            $details[] = [
                'work_center' => $wc,
                'available_hours' => round($available / 60.0, 2),
                'setup_hours' => round($setup / 60.0, 2),
                'run_hours' => round($run / 60.0, 2),
                'required_hours' => round($required / 60.0, 2),
                'utilization' => round($utilization, 2),
                'overload_hours' => round($overload / 60.0, 2),
                'free_hours' => round($free / 60.0, 2),
                'ops_count' => $ops->count(),
                'conflicts_count' => $wcConflicts,
                'status' => $status,
            ];
        }

        return $details;
    }

    /**
     * Get capacity planning details for machines.
     */
    public function getMachineCapacity(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $machines = Machine::where('tenant_id', $tenantId)->where('status', 'active')->with('workCenter')->get();
        $details = [];

        foreach ($machines as $m) {
            $wc = $m->workCenter;
            if (!$wc) continue;

            $totalCapacity = 0.0;
            $date = $startDate->copy()->startOfDay();
            while ($date->lte($endDate)) {
                $totalCapacity += $this->schedulingService->calculateCapacity($wc->id, $date);
                $date->addDay();
            }

            $ops = ProductionScheduleOperation::where('tenant_id', $tenantId)
                ->where('machine_id', $m->id)
                ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
                ->get();

            $required = (float) $ops->sum('planned_duration_minutes');

            $downtime = (float) ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('machine_id', $m->id)
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('duration_minutes');

            $available = max(0.0, $totalCapacity - $downtime);
            $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);
            $overload = max(0.0, $required - $available);

            $status = 'available';
            if ($m->isUnderMaintenance()) {
                $status = 'downtime';
            } elseif ($available <= 0) {
                $status = 'unavailable';
            } elseif ($utilization > 100) {
                $status = 'overloaded';
            } elseif ($utilization > 85) {
                $status = 'near_capacity';
            } elseif ($utilization > 0) {
                $status = 'balanced';
            }

            $details[] = [
                'machine' => $m,
                'available_hours' => round($available / 60.0, 2),
                'required_hours' => round($required / 60.0, 2),
                'utilization' => round($utilization, 2),
                'downtime_hours' => round($downtime / 60.0, 2),
                'overload_hours' => round($overload / 60.0, 2),
                'ops_count' => $ops->count(),
                'status' => $status,
            ];
        }

        return $details;
    }

    /**
     * Get capacity planner daily load grid data.
     */
    public function getDailyLoad(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $days = [];

        // Pre-fetch all operations for date range in a single query
        $allOps = ProductionScheduleOperation::where('tenant_id', $tenantId)
            ->whereBetween('planned_start', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->get();

        // Pre-fetch all machine downtimes in a single query
        $allDowntimes = ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $date = $startDate->copy()->startOfDay();
        while ($date->lte($endDate)) {
            $currentDateStr = $date->toDateString();
            foreach ($workCenters as $wc) {
                $capacity = $this->schedulingService->calculateCapacity($wc->id, $date);

                // Filter operations in-memory
                $ops = $allOps->filter(function ($op) use ($wc, $currentDateStr) {
                    return $op->work_center_id === $wc->id 
                        && $op->planned_start->toDateString() === $currentDateStr;
                });

                $required = (float) $ops->sum('planned_duration_minutes');

                // Filter downtimes in-memory
                $downtime = (float) $allDowntimes->filter(function ($dt) use ($wc, $currentDateStr) {
                    return $dt->work_center_id === $wc->id 
                        && $dt->start_time->toDateString() === $currentDateStr;
                })->sum('duration_minutes');

                $available = max(0.0, $capacity - $downtime);
                $utilization = $available > 0 ? ($required / $available) * 100 : ($required > 0 ? 100.0 : 0.0);

                $days[] = [
                    'date' => $currentDateStr,
                    'work_center' => $wc,
                    'available_hours' => round($available / 60.0, 2),
                    'used_hours' => round($required / 60.0, 2),
                    'remaining_hours' => round(max(0.0, $available - $required) / 60.0, 2),
                    'utilization' => round($utilization, 2),
                    'overloaded' => $required > $available,
                ];
            }
            $date->addDay();
        }

        return $days;
    }

    /**
     * Reschedule a single schedule operation step safely (delegates to rescheduleOperationWithMode).
     */
    public function rescheduleOperation(
        int     $schedOpId,
        Carbon  $newStart,
        ?int    $newMachineId = null,
        ?string $reason       = null,
        ?int    $userId       = null
    ): void {
        $this->rescheduleOperationWithMode(
            $schedOpId,
            $newStart,
            $newMachineId,
            ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
            $reason,
            $userId
        );
    }

    /**
     * Validate if a machine is qualified for a schedule operation.
     * Must be active, belong to tenant & work center, and be primary or approved alternate machine.
     */
    public function validateMachineQualification(ProductionScheduleOperation $schedOp, int $machineId, int $tenantId): Machine
    {
        $machine = Machine::where('tenant_id', $tenantId)->findOrFail($machineId);

        if (!$machine->isActive()) {
            throw new InvalidArgumentException("Selected machine [{$machine->name}] is inactive.");
        }

        if ($machine->work_center_id !== $schedOp->work_center_id) {
            throw new InvalidArgumentException("Selected machine [{$machine->name}] does not belong to Work Center [#{$schedOp->work_center_id}].");
        }

        $orderOp = $schedOp->orderOperation;
        if ($orderOp && $orderOp->routing_operation_id) {
            $routingOpId      = $orderOp->routing_operation_id;
            $primaryMachineId = $orderOp->machine_id;

            if ($primaryMachineId && (int) $primaryMachineId === (int) $machineId) {
                return $machine;
            }

            $isAlternate = RoutingOperationAlternateMachine::where('tenant_id', $tenantId)
                ->where('routing_operation_id', $routingOpId)
                ->where('machine_id', $machineId)
                ->exists();

            if (!$isAlternate && $primaryMachineId) {
                throw new InvalidArgumentException("Machine [{$machine->name}] is not an approved primary or alternate machine for operation [{$orderOp->name}].");
            }
        }

        return $machine;
    }

    /**
     * Toggle lock state on a schedule operation.
     */
    public function toggleOperationLock(int $schedOpId, ?int $userId = null): ProductionScheduleOperation
    {
        return DB::transaction(function () use ($schedOpId, $userId) {
            $tenantId = require_tenant_id();
            $schedOp  = ProductionScheduleOperation::lockForUpdate()->findOrFail($schedOpId);

            if ($schedOp->tenant_id !== $tenantId) {
                throw new InvalidArgumentException("Operation does not belong to your tenant context.");
            }

            $oldLocked       = (bool) $schedOp->locked;
            $schedOp->locked = !$oldLocked;
            $schedOp->version = (int) $schedOp->version + 1;
            $schedOp->save();

            ProductionScheduleChangeLog::create([
                'tenant_id'                        => $tenantId,
                'production_schedule_id'           => $schedOp->production_schedule_id,
                'production_schedule_operation_id' => $schedOp->id,
                'change_type'                      => ProductionScheduleChangeLog::CHANGE_TYPE_LOCK_TOGGLE,
                'old_planned_start'                => $schedOp->planned_start,
                'new_planned_start'                => $schedOp->planned_start,
                'old_planned_finish'               => $schedOp->planned_finish,
                'new_planned_finish'               => $schedOp->planned_finish,
                'reason'                           => $schedOp->locked ? 'Operation locked by planner' : 'Operation unlocked by planner',
                'changed_by'                       => $userId ?: (auth()->id() ?: 1),
            ]);

            app(ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $schedOp->production_order_id,
                'event_type'          => 'Operation Lock Toggled',
                'title'               => $schedOp->locked ? 'Operation Locked' : 'Operation Unlocked',
                'description'         => "Operation [{$schedOp->orderOperation?->name}] lock status set to " . ($schedOp->locked ? 'LOCKED' : 'UNLOCKED'),
                'severity'            => 'info',
                'event_source'        => 'CapacityPlanningService',
                'triggered_by'        => $userId ?: (auth()->id() ?: 1),
            ]);

            return $schedOp;
        });
    }

    /**
     * Reschedule an operation supporting Isolated or Ripple shift modes.
     */
    public function rescheduleOperationWithMode(
        int     $schedOpId,
        Carbon  $newStart,
        ?int    $newMachineId    = null,
        string  $shiftMode       = ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED,
        ?string $reason          = null,
        ?int    $userId          = null,
        ?int    $expectedVersion = null
    ): array {
        return DB::transaction(function () use ($schedOpId, $newStart, $newMachineId, $shiftMode, $reason, $userId, $expectedVersion) {
            $tenantId = require_tenant_id();
            $schedOp  = ProductionScheduleOperation::lockForUpdate()->findOrFail($schedOpId);

            if ($schedOp->tenant_id !== $tenantId) {
                throw new InvalidArgumentException("Operation does not belong to your tenant context.");
            }

            if ($expectedVersion !== null && (int) $schedOp->version !== (int) $expectedVersion) {
                throw new InvalidArgumentException("CONCURRENCY_CONFLICT: Operation schedule was modified by another user (Expected version {$expectedVersion}, actual {$schedOp->version}).");
            }

            if ($schedOp->locked) {
                throw new InvalidArgumentException("Operation [{$schedOp->orderOperation?->name}] is locked and cannot be moved.");
            }

            if ($schedOp->isTerminal() || $schedOp->isRunning() || $schedOp->isPaused()) {
                throw new InvalidArgumentException("Operation is in execution state ({$schedOp->status}) and cannot be rescheduled.");
            }

            $order = $schedOp->order;
            if ($order && ($order->isClosed() || $order->isCancelled())) {
                throw new InvalidArgumentException("Operation cannot be rescheduled: Parent production order is closed or cancelled.");
            }

            $targetMachineId = $newMachineId ?: $schedOp->machine_id;
            if ($targetMachineId) {
                $this->validateMachineQualification($schedOp, $targetMachineId, $tenantId);
            }

            $wc       = $schedOp->workCenter;
            $calendar = $this->resolveCalendarForWorkCenter($wc, $tenantId);
            if (!$this->isWorkingDay($calendar, $newStart, $tenantId)) {
                throw new InvalidArgumentException("Reschedule failed: Target date is not a valid working day on calendar.");
            }

            $durationMinutes = (float) $schedOp->planned_duration_minutes;
            $newFinish       = $this->schedulingService->addWorkingMinutes($schedOp->work_center_id, $newStart, $durationMinutes);

            if ($targetMachineId) {
                $this->validateMachineAvailability($tenantId, $targetMachineId, $newStart, $newFinish, $schedOp->id);
            }

            // Predecessor Check
            $predecessor = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                ->where('sequence', '<', $schedOp->sequence)
                ->whereNotIn('status', ['cancelled', 'skipped'])
                ->orderBy('sequence', 'desc')
                ->first();

            if ($predecessor) {
                $predOrderOp = $predecessor->orderOperation;
                if ($predOrderOp && (bool) $predOrderOp->overlap_enabled) {
                    $setupMinutes          = (float) ($predOrderOp->setup_time_planned ?? 0);
                    $batchQty              = (float) ($predOrderOp->transfer_batch_quantity ?? 0);
                    $lagMinutes            = (float) ($predOrderOp->transfer_lag_minutes ?? 0);
                    $orderQty              = (float) ($order->quantity_ordered ?? 1);
                    $effectiveBatchQty     = min($batchQty, $orderQty);
                    $firstBatchRunDuration = (float) $predOrderOp->processing_time_planned * $effectiveBatchQty;
                    $totalOffsetMinutes    = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

                    $earliestAllowedStart = $this->schedulingService->addWorkingMinutes(
                        $predecessor->work_center_id,
                        $predecessor->planned_start,
                        $totalOffsetMinutes
                    );

                    if ($newStart->lt($earliestAllowedStart)) {
                        throw new InvalidArgumentException("Reschedule conflict: Starts before predecessor transfer-ready time ({$earliestAllowedStart->toDateTimeString()}).");
                    }
                } else {
                    if ($newStart->lt($predecessor->planned_finish)) {
                        throw new InvalidArgumentException("Reschedule conflict: Starts before predecessor finishes ({$predecessor->planned_finish->toDateTimeString()}).");
                    }
                }
            }

            if ($shiftMode === ProductionScheduleChangeLog::SHIFT_MODE_ISOLATED) {
                // Successor Check for Isolated shift
                $successor = ProductionScheduleOperation::where('production_schedule_id', $schedOp->production_schedule_id)
                    ->where('sequence', '>', $schedOp->sequence)
                    ->whereNotIn('status', ['cancelled', 'skipped'])
                    ->orderBy('sequence', 'asc')
                    ->first();

                if ($successor) {
                    $orderOp = $schedOp->orderOperation;
                    if ($orderOp && (bool) $orderOp->overlap_enabled) {
                        $setupMinutes          = (float) ($orderOp->setup_time_planned ?? 0);
                        $batchQty              = (float) ($orderOp->transfer_batch_quantity ?? 0);
                        $lagMinutes            = (float) ($orderOp->transfer_lag_minutes ?? 0);
                        $orderQty              = (float) ($order->quantity_ordered ?? 1);
                        $effectiveBatchQty     = min($batchQty, $orderQty);
                        $firstBatchRunDuration = (float) $orderOp->processing_time_planned * $effectiveBatchQty;
                        $totalOffsetMinutes    = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

                        $earliestSuccessorStart = $this->schedulingService->addWorkingMinutes(
                            $schedOp->work_center_id,
                            $newStart,
                            $totalOffsetMinutes
                        );

                        if ($successor->planned_start->lt($earliestSuccessorStart)) {
                            throw new InvalidArgumentException("Isolated move conflict: Moving this operation delays transfer-ready time beyond successor start ({$successor->planned_start->toDateTimeString()}).");
                        }
                    } else {
                        if ($newFinish->gt($successor->planned_start)) {
                            throw new InvalidArgumentException("Isolated move conflict: Operation finishes after successor starts ({$successor->planned_start->toDateTimeString()}).");
                        }
                    }
                }
            }

            // Save Target Operation
            $oldStart     = $schedOp->planned_start;
            $oldFinish    = $schedOp->planned_finish;
            $oldMachineId = $schedOp->machine_id;

            $schedOp->planned_start    = $newStart;
            $schedOp->planned_finish   = $newFinish;
            $schedOp->machine_id       = $targetMachineId;
            $schedOp->manual_override  = true;
            $schedOp->last_adjusted_at = now();
            $schedOp->last_adjusted_by = $userId ?: (auth()->id() ?: 1);
            $schedOp->version           = (int) $schedOp->version + 1;
            $schedOp->save();

            ProductionScheduleChangeLog::create([
                'tenant_id'                        => $tenantId,
                'production_schedule_id'           => $schedOp->production_schedule_id,
                'production_schedule_operation_id' => $schedOp->id,
                'change_type'                      => $newMachineId && $newMachineId !== $oldMachineId
                    ? ProductionScheduleChangeLog::CHANGE_TYPE_MACHINE_REASSIGN
                    : ProductionScheduleChangeLog::CHANGE_TYPE_MANUAL_SHIFT,
                'shift_mode'                       => $shiftMode,
                'old_machine_id'                   => $oldMachineId,
                'new_machine_id'                   => $targetMachineId,
                'old_planned_start'                => $oldStart,
                'new_planned_start'                => $newStart,
                'old_planned_finish'               => $oldFinish,
                'new_planned_finish'               => $newFinish,
                'reason'                           => $reason ?: 'Manual planner shift',
                'changed_by'                       => $userId ?: (auth()->id() ?: 1),
            ]);

            $adjustedCount = 1;

            if ($shiftMode === ProductionScheduleChangeLog::SHIFT_MODE_RIPPLE) {
                // Downstream Ripple recalculation
                $successors = ProductionScheduleOperation::lockForUpdate()
                    ->where('production_schedule_id', $schedOp->production_schedule_id)
                    ->where('sequence', '>', $schedOp->sequence)
                    ->whereNotIn('status', ['cancelled', 'skipped'])
                    ->orderBy('sequence', 'asc')
                    ->get();

                $scheduledMap = [
                    $schedOp->sequence => [
                        'sequence'       => $schedOp->sequence,
                        'parallel_group' => $schedOp->orderOperation?->parallel_group,
                        'is_parallel'    => $schedOp->orderOperation?->is_parallel,
                        'planned_start'  => $schedOp->planned_start->copy(),
                        'planned_finish' => $schedOp->planned_finish->copy(),
                        'work_center_id' => $schedOp->work_center_id,
                        'order_op'       => $schedOp->orderOperation,
                    ],
                ];

                foreach ($successors as $succOp) {
                    $isSuccFrozen = $succOp->locked || $succOp->isTerminal() || $succOp->isRunning() || $succOp->isPaused();

                    $earliestStart = $this->schedulingService->calculateEarliestStartFromPredecessors(
                        $scheduledMap,
                        $succOp->sequence,
                        $succOp->orderOperation?->parallel_group,
                        (bool) $succOp->orderOperation?->is_parallel,
                        $newStart,
                        (float) ($order->quantity_ordered ?? 1)
                    );

                    if ($isSuccFrozen) {
                        if ($succOp->planned_start->lt($earliestStart)) {
                            throw new \LogicException(json_encode([
                                'success'               => false,
                                'code'                  => 'LOCKED_OPERATION_CONFLICT',
                                'blocking_operation_id' => $succOp->id,
                                'message'               => "LOCKED_OPERATION_CONFLICT: Operation sequence [{$succOp->sequence}] ({$succOp->orderOperation?->name}) is locked/running and prevents downstream ripple propagation (Requires start at {$earliestStart->toDateTimeString()}, locked at {$succOp->planned_start->toDateTimeString()}).",
                            ]));
                        }
                        $scheduledMap[$succOp->sequence] = [
                            'sequence'       => $succOp->sequence,
                            'parallel_group' => $succOp->orderOperation?->parallel_group,
                            'is_parallel'    => $succOp->orderOperation?->is_parallel,
                            'planned_start'  => $succOp->planned_start->copy(),
                            'planned_finish' => $succOp->planned_finish->copy(),
                            'work_center_id' => $succOp->work_center_id,
                            'order_op'       => $succOp->orderOperation,
                        ];
                        continue;
                    }

                    if ($succOp->planned_start->lt($earliestStart)) {
                        $succDuration = (float) $succOp->planned_duration_minutes;
                        $succFinish   = $this->schedulingService->addWorkingMinutes($succOp->work_center_id, $earliestStart, $succDuration);

                        if ($succOp->machine_id) {
                            $this->validateMachineAvailability($tenantId, $succOp->machine_id, $earliestStart, $succFinish, $succOp->id);
                        }

                        $succOldStart  = $succOp->planned_start;
                        $succOldFinish = $succOp->planned_finish;

                        $succOp->planned_start    = $earliestStart;
                        $succOp->planned_finish   = $succFinish;
                        $succOp->last_adjusted_at = now();
                        $succOp->last_adjusted_by = $userId ?: (auth()->id() ?: 1);
                        $succOp->version           = (int) $succOp->version + 1;
                        $succOp->save();

                        ProductionScheduleChangeLog::create([
                            'tenant_id'                        => $tenantId,
                            'production_schedule_id'           => $succOp->production_schedule_id,
                            'production_schedule_operation_id' => $succOp->id,
                            'change_type'                      => ProductionScheduleChangeLog::CHANGE_TYPE_RIPPLE_SHIFT,
                            'shift_mode'                       => ProductionScheduleChangeLog::SHIFT_MODE_RIPPLE,
                            'old_machine_id'                   => $succOp->machine_id,
                            'new_machine_id'                   => $succOp->machine_id,
                            'old_planned_start'                => $succOldStart,
                            'new_planned_start'                => $earliestStart,
                            'old_planned_finish'               => $succOldFinish,
                            'new_planned_finish'               => $succFinish,
                            'reason'                           => "Ripple shift from operation sequence [{$schedOp->sequence}]",
                            'changed_by'                       => $userId ?: (auth()->id() ?: 1),
                        ]);

                        $adjustedCount++;
                    }

                    $scheduledMap[$succOp->sequence] = [
                        'sequence'       => $succOp->sequence,
                        'parallel_group' => $succOp->orderOperation?->parallel_group,
                        'is_parallel'    => $succOp->orderOperation?->is_parallel,
                        'planned_start'  => $succOp->planned_start->copy(),
                        'planned_finish' => $succOp->planned_finish->copy(),
                        'work_center_id' => $succOp->work_center_id,
                        'order_op'       => $succOp->orderOperation,
                    ];
                }
            }

            app(ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $schedOp->production_order_id,
                'event_type'          => 'Schedule Operation Adjusted',
                'title'               => 'Schedule Adjusted (' . strtoupper($shiftMode) . ')',
                'description'         => "Schedule operation sequence [{$schedOp->sequence}] rescheduled. Total operations updated: {$adjustedCount}.",
                'severity'            => 'info',
                'event_source'        => 'CapacityPlanningService',
                'triggered_by'        => $userId ?: (auth()->id() ?: 1),
            ]);

            return [
                'success'                   => true,
                'message'                   => "Operation sequence [{$schedOp->sequence}] rescheduled successfully ({$shiftMode} mode).",
                'adjusted_operations_count' => $adjustedCount,
                'schedule_id'               => $schedOp->production_schedule_id,
            ];
        });
    }

    /**
     * Get rule-based load balancing suggestions for an overloaded operation.
     */
    public function getLoadBalanceSuggestions(int $schedOpId): array
    {
        $schedOp = ProductionScheduleOperation::findOrFail($schedOpId);
        $tenantId = require_tenant_id();

        // Get alternate machines in the same work center
        $machines = Machine::where('work_center_id', $schedOp->work_center_id)
            ->where('status', 'active')
            ->where('id', '!=', $schedOp->machine_id)
            ->get();

        $suggestions = [];

        foreach ($machines as $m) {
            // Check original slot overlap
            $hasOverlap = ProductionScheduleOperation::where('machine_id', $m->id)
                ->where('planned_start', '<', $schedOp->planned_finish)
                ->where('planned_finish', '>', $schedOp->planned_start)
                ->whereNotIn('status', ['cancelled', 'skipped'])
                ->exists();

            if (!$hasOverlap) {
                $suggestions[] = [
                    'machine' => $m,
                    'suggested_start' => $schedOp->planned_start,
                    'suggested_finish' => $schedOp->planned_finish,
                    'conflict_resolved' => true,
                    'warning' => null,
                ];
            } else {
                // Find next free slot on this machine starting from now
                $searchStart = now();
                $duration = (int) $schedOp->planned_duration_minutes;
                
                $bookings = ProductionScheduleOperation::where('machine_id', $m->id)
                    ->where('planned_finish', '>', $searchStart)
                    ->whereNotIn('status', ['cancelled', 'skipped'])
                    ->orderBy('planned_start')
                    ->get();

                $slotStart = $searchStart->copy();
                $foundSlot = false;

                foreach ($bookings as $b) {
                    if ($slotStart->copy()->addMinutes($duration)->lte($b->planned_start)) {
                        $foundSlot = true;
                        break;
                    }
                    $slotStart = $b->planned_finish->copy();
                }

                if (!$foundSlot) {
                    $slotStart = $bookings->isEmpty() ? $searchStart : $bookings->last()->planned_finish->copy();
                }

                $suggestions[] = [
                    'machine' => $m,
                    'suggested_start' => $slotStart,
                    'suggested_finish' => $slotStart->copy()->addMinutes($duration),
                    'conflict_resolved' => false,
                    'warning' => 'Machine has existing bookings. Placed in next available slot.',
                ];
            }
        }

        return $suggestions;
    }

    private function resolveCalendarForWorkCenter($wc, $tenantId)
    {
        if ($wc->production_calendar_id) {
            $cal = ProductionCalendar::withoutGlobalScopes()->find($wc->production_calendar_id);
            if ($cal) return $cal;
        }
        $default = ProductionCalendar::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
        if ($default) return $default;

        return new ProductionCalendar([
            'name'         => 'Mon-Fri Fallback Calendar',
            'working_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function isWorkingDay($calendar, $date, $tenantId): bool
    {
        $dayOfWeek = $date->dayOfWeek;
        $workingDays = $calendar->working_days ?? [1, 2, 3, 4, 5];
        
        $dayNamesMap = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];
        $workingDaysNormalized = array_map(function($day) use ($dayNamesMap) {
            if (is_numeric($day)) {
                return (int)$day;
            }
            return $dayNamesMap[strtolower($day)] ?? $day;
        }, $workingDays);

        if (!in_array($dayOfWeek, $workingDaysNormalized)) {
            return false;
        }
        if ($calendar->id) {
            $isHoliday = ProductionCalendarHoliday::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_calendar_id', $calendar->id)
                ->whereDate('holiday_date', $date)
                ->where('active', true)
                ->exists();
            if ($isHoliday) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate that a target machine is active, available, and free of open downtime conflicts.
     */
    public function validateMachineAvailability(
        int $tenantId,
        int $machineId,
        Carbon $start,
        Carbon $finish,
        ?int $excludeScheduleOpId = null
    ): void {
        $machine = Machine::where('tenant_id', $tenantId)->find($machineId);
        if (!$machine) {
            throw new InvalidArgumentException("Reschedule failed: Selected machine #{$machineId} does not exist.");
        }

        if (!$machine->isActive() || $machine->isUnderMaintenance()) {
            throw new InvalidArgumentException("Reschedule failed: Selected machine '{$machine->name}' is inactive or under maintenance.");
        }

        // Check overlapping machine downtime (planned maintenance or active breakdown)
        $hasDowntimeConflict = ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('machine_id', $machineId)
            ->where('status', ProductionMachineDowntime::STATUS_OPEN)
            ->where('start_time', '<', $finish)
            ->where(function ($query) use ($start) {
                $query->whereNull('end_time')
                    ->orWhere('end_time', '>', $start);
            })
            ->exists();

        if ($hasDowntimeConflict) {
            throw new InvalidArgumentException(
                "Reschedule conflict: Target machine '{$machine->name}' has active downtime or maintenance during requested slot ({$start->format('Y-m-d H:i')} to {$finish->format('Y-m-d H:i')})."
            );
        }
    }
}
