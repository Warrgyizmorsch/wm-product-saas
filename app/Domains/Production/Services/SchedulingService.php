<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchedulingService
{
    private array $workCentersCache = [];
    private array $calendarsCache = [];
    private array $shiftsCache = [];

    public function __construct(
        private readonly ProductionScheduleNumberService $numberService
    ) {
    }

    private function getCachedWorkCenter(?int $wcId): ?WorkCenter
    {
        if (!$wcId) {
            return null;
        }
        if (!isset($this->workCentersCache[$wcId])) {
            $this->workCentersCache[$wcId] = WorkCenter::withoutGlobalScopes()->find($wcId);
        }
        return $this->workCentersCache[$wcId];
    }

    private function getCachedCalendar(WorkCenter $wc, int $tenantId): ProductionCalendar
    {
        if (!isset($this->calendarsCache[$wc->id])) {
            $this->calendarsCache[$wc->id] = $this->resolveCalendar($wc, $tenantId);
        }
        return $this->calendarsCache[$wc->id];
    }

    private function getCachedShifts(WorkCenter $wc): \Illuminate\Support\Collection
    {
        if (!isset($this->shiftsCache[$wc->id])) {
            $shifts = $wc->shifts()->where('active', true)->get();
            if ($shifts->isEmpty()) {
                $shifts = collect([
                    new ProductionShift([
                        'name' => 'Standard Shift',
                        'code' => 'STD',
                        'start_time' => '08:00:00',
                        'end_time' => '16:00:00',
                        'break_minutes' => 0,
                    ])
                ]);
            }
            $this->shiftsCache[$wc->id] = $shifts;
        }
        return $this->shiftsCache[$wc->id];
    }

    /**
     * Public gateway for generating a production schedule.
     *
     * Dispatches to the forward or backward scheduling engine based on $type.
     * Throws InvalidArgumentException for unsupported scheduling strategies.
     *
     * @param  ProductionOrder $order  The production order to schedule.
     * @param  Carbon          $date   Start date (forward) or due date (backward).
     * @param  string          $type   Scheduling strategy: 'forward' or 'backward'.
     * @return ProductionSchedule
     *
     * @throws \InvalidArgumentException When $type is not supported.
     */
    public function generateSchedule(
        ProductionOrder $order,
        Carbon $date,
        string $type = ProductionSchedule::TYPE_FORWARD
    ): ProductionSchedule {
        return DB::transaction(function () use ($order, $date, $type) {
            // Lock order for update
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);

            // Fetch existing draft/scheduled schedules for the order
            $existingSchedules = ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [
                    ProductionSchedule::STATUS_DRAFT,
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_RELEASED,
                    ProductionSchedule::STATUS_IN_PROGRESS
                ])
                ->lockForUpdate()
                ->get();

            $hasExecution = false;
            $execSchedule = null;
            foreach ($existingSchedules as $s) {
                if ($this->hasExecutionHistory($s)) {
                    $hasExecution = true;
                    $execSchedule = $s;
                    break;
                }
            }

            if ($hasExecution && $execSchedule) {
                // Safely perform partial reschedule in-place
                return $this->partialReschedule($execSchedule, $date, $type);
            }

            // Otherwise, explicitly delete old schedules (checked)
            foreach ($existingSchedules as $s) {
                ProductionScheduleOperation::where('production_schedule_id', $s->id)->delete();
                $s->delete();
            }

            if ($type === ProductionSchedule::TYPE_FORWARD) {
                return $this->generateForwardSchedule($order, $date);
            }

            if ($type === ProductionSchedule::TYPE_BACKWARD) {
                return $this->generateBackwardSchedule($order, $date);
            }

            throw new \InvalidArgumentException(
                "Scheduling strategy [{$type}] is not supported in this release. Supported: forward, backward."
            );
        });
    }

    /**
     * Generate Forward Schedule.
     *
     * Allocates routing operations sequentially from the given start date.
     * Respects: shift windows, holiday calendars, parallel operations, alternate machines.
     * Deletes any existing draft/scheduled plans for the same order before generating.
     *
     * @param  ProductionOrder $order      The order whose routing operations will be scheduled.
     * @param  Carbon          $startDate  Scheduling starts no earlier than this date.
     * @return ProductionSchedule          The persisted schedule with all operations attached.
     */
    public function generateForwardSchedule(ProductionOrder $order, Carbon $startDate): ProductionSchedule
    {
        return DB::transaction(function () use ($order, $startDate) {
            $rawOperations = $order->operations()->with(['workCenter', 'predecessorDependencies'])->get();
            if ($rawOperations->isEmpty()) {
                throw new \LogicException("Cannot generate schedule: Production Order has no operations configured.");
            }

            $operations = $this->sortOperationsTopologically($rawOperations);

            foreach ($operations as $op) {
                if (!$op->is_external) {
                    if (!$op->workCenter) {
                        throw new \LogicException("Cannot generate schedule: Work Center is missing for operation sequence [{$op->sequence}].");
                    }
                    if (!$op->workCenter->isActive()) {
                        throw new \LogicException("Cannot generate schedule: Work Center [{$op->workCenter->name}] for operation sequence [{$op->sequence}] is inactive.");
                    }
                }
            }

            // Safe checked deletion instead of cascading delete
            $existing = ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [
                    ProductionSchedule::STATUS_DRAFT,
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_RELEASED,
                    ProductionSchedule::STATUS_IN_PROGRESS
                ])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $s) {
                if ($this->hasExecutionHistory($s)) {
                    throw new \LogicException("Cannot replace schedule: Schedule [{$s->schedule_number}] has active MES execution or WIP records.");
                }
                ProductionScheduleOperation::where('production_schedule_id', $s->id)->delete();
                $s->delete();
            }

            $tenantId = $order->tenant_id;

            $schedule = ProductionSchedule::create([
                'tenant_id' => $tenantId,
                'schedule_number' => $this->numberService->generateNextNumber($tenantId),
                'production_order_id' => $order->id,
                'scheduling_type' => ProductionSchedule::TYPE_FORWARD,
                'generated_by' => 'forward',
                'status' => ProductionSchedule::STATUS_SCHEDULED,
                'scheduled_at' => now(),
                'created_by' => auth()->id() ?: 1,
            ]);
            $scheduledData = [];
            // Track created schedule-op IDs per parallel_group for sibling exclusion
            $parallelGroupOpIds = []; // ['GROUP-A' => [scheduleOpId1, ...]]

            foreach ($operations as $op) {
                if ($op->is_external) {
                    $totalDays = (int) ($op->dispatch_buffer_days ?? 0) + (int) ($op->subcontract_lead_time_days ?? 0) + (int) ($op->return_buffer_days ?? 0);
                    $duration = $totalDays * 1440.0;

                    $earliestStart = $this->calculateEarliestStartFromPredecessors(
                        $scheduledData,
                        $op->sequence,
                        $op->parallel_group,
                        (bool) $op->is_parallel,
                        $startDate,
                        (float) $order->quantity_ordered,
                        $op
                    );

                    $plannedStart = $earliestStart->copy();
                    $plannedFinish = $plannedStart->copy()->addMinutes((int) ceil($duration));
                    $warnings = [];
                    $machineId = null;
                    $priority = 1;
                } else {
                    $times = $this->calculateOperationTimes($op, $order->quantity_ordered);
                    $duration = $times['total_minutes'];

                    // Calculate earliest start based on predecessors
                    $earliestStart = $this->calculateEarliestStartFromPredecessors(
                        $scheduledData,
                        $op->sequence,
                        $op->parallel_group,
                        (bool) $op->is_parallel,
                        $startDate,
                        (float) $order->quantity_ordered,
                        $op
                    );

                    // Collect parallel-group sibling schedule-op IDs to exclude from bookings
                    $excludeScheduleOpIds = [];
                    if ($op->is_parallel && $op->parallel_group !== null) {
                        $excludeScheduleOpIds = $parallelGroupOpIds[$op->parallel_group] ?? [];
                    }

                    // Find optimal machine & slot
                    if ($op->routing_operation_id) {
                        $alloc = $this->findNextAvailableMachine($op->routing_operation_id, $earliestStart, $duration, $tenantId, true, $excludeScheduleOpIds);
                    } else {
                        $slot = $this->calculateAvailableSlot($op->work_center_id, $op->machine_id, $earliestStart, $duration, true, $excludeScheduleOpIds);
                        $warnings = $slot['warnings'];
                        if ($op->machine_id) {
                            $m = Machine::withoutGlobalScopes()->find($op->machine_id);
                            if ($m) {
                                $mCheck = $this->validateMachineForScheduling($m, $op->work_center_id, $tenantId);
                                if (!$mCheck['valid'] && !empty($mCheck['warning'])) {
                                    $warnings[] = $mCheck['warning'];
                                }
                            }
                        }
                        $alloc = [
                            'machine_id' => $op->machine_id,
                            'start' => $slot['start'],
                            'finish' => $slot['finish'],
                            'warnings' => $warnings,
                            'priority' => 1,
                        ];
                    }

                    $plannedStart = $alloc['start'] ?? $earliestStart->copy();
                    $plannedFinish = $alloc['finish'] ?? $earliestStart->copy()->addMinutes((int) ceil($duration));
                    $warnings = $alloc['warnings'] ?? [];
                    $machineId = $alloc['machine_id'] ?? null;
                    $priority = $alloc['priority'] ?? 1;
                }

                $lane = $op->work_center_id ? 'WorkCenter_' . $op->work_center_id : 'Vendor_' . ($op->vendor_id ?? 'External');
                $resourceId = $machineId ? 'Machine_' . $machineId : $lane;

                $schedOp = ProductionScheduleOperation::create([
                    'tenant_id' => $tenantId,
                    'production_schedule_id' => $schedule->id,
                    'production_order_id' => $order->id,
                    'production_order_operation_id' => $op->id,
                    'work_center_id' => $op->work_center_id,
                    'machine_id' => $machineId,
                    'sequence' => $op->sequence,
                    'priority' => $priority,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'baseline_start' => $plannedStart,
                    'baseline_finish' => $plannedFinish,
                    'planned_duration_minutes' => $duration,
                    'status' => $op->sequence === $operations->first()->sequence
                        ? ProductionScheduleOperation::STATUS_READY
                        : ProductionScheduleOperation::STATUS_WAITING,
                    'warnings' => $warnings,
                    'locked' => false,
                    'lane' => $lane,
                    'resource_id' => $resourceId,
                ]);

                // Track parallel group schedule-op IDs for sibling exclusion
                if ($op->is_parallel && $op->parallel_group !== null) {
                    $parallelGroupOpIds[$op->parallel_group][] = $schedOp->id;
                }

                $scheduledData[] = [
                    'sequence' => $op->sequence,
                    'parallel_group' => $op->parallel_group,
                    'is_parallel' => $op->is_parallel,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'work_center_id' => $op->work_center_id,
                    'machine_id' => $machineId,
                    'order_op' => $op,
                ];
            }

            // Stash Overall Capacity Utilization
            $schedule->update([
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Schedule Created',
                'title' => 'Production Schedule Created',
                'description' => "Schedule {$schedule->schedule_number} created via forward scheduling.",
                'severity' => 'info',
                'event_source' => 'SchedulingService',
                'triggered_by' => auth()->id(),
            ]);

            return $schedule;
        });
    }

    /**
     * Generate Backward Schedule.
     *
     * Allocates routing operations in reverse-sequence from the given due date, ensuring
     * the last operation finishes on or before $dueDate. Predecessor/successor logic is
     * inverted compared to forward scheduling.
     *
     * @param  ProductionOrder $order    The order whose routing operations will be scheduled.
     * @param  Carbon          $dueDate  All operations must complete by this date.
     * @return ProductionSchedule        The persisted schedule with operations in correct sequence.
     */
    public function generateBackwardSchedule(ProductionOrder $order, Carbon $dueDate): ProductionSchedule
    {
        return DB::transaction(function () use ($order, $dueDate) {
            $rawOperations = $order->operations()->with(['workCenter', 'predecessorDependencies', 'successorDependencies'])->get();
            if ($rawOperations->isEmpty()) {
                throw new \LogicException("Cannot generate schedule: Production Order has no operations configured.");
            }

            $sortedOps = $this->sortOperationsTopologically($rawOperations);

            foreach ($sortedOps as $op) {
                if (!$op->is_external) {
                    if (!$op->workCenter) {
                        throw new \LogicException("Cannot generate schedule: Work Center is missing for operation sequence [{$op->sequence}].");
                    }
                    if (!$op->workCenter->isActive()) {
                        throw new \LogicException("Cannot generate schedule: Work Center [{$op->workCenter->name}] for operation sequence [{$op->sequence}] is inactive.");
                    }
                }
            }

            // Safe checked deletion instead of cascading delete
            $existing = ProductionSchedule::withoutGlobalScopes()
                ->where('production_order_id', $order->id)
                ->whereIn('status', [
                    ProductionSchedule::STATUS_DRAFT,
                    ProductionSchedule::STATUS_SCHEDULED,
                    ProductionSchedule::STATUS_RELEASED,
                    ProductionSchedule::STATUS_IN_PROGRESS
                ])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $s) {
                if ($this->hasExecutionHistory($s)) {
                    throw new \LogicException("Cannot replace schedule: Schedule [{$s->schedule_number}] has active MES execution or WIP records.");
                }
                ProductionScheduleOperation::where('production_schedule_id', $s->id)->delete();
                $s->delete();
            }

            $tenantId = $order->tenant_id;

            $schedule = ProductionSchedule::create([
                'tenant_id' => $tenantId,
                'schedule_number' => $this->numberService->generateNextNumber($tenantId),
                'production_order_id' => $order->id,
                'scheduling_type' => ProductionSchedule::TYPE_BACKWARD,
                'generated_by' => 'backward',
                'status' => ProductionSchedule::STATUS_SCHEDULED,
                'scheduled_at' => now(),
                'created_by' => auth()->id() ?: 1,
            ]);

            // For backward scheduling, schedule operations in reverse topological order (successors first)
            $reversedOps = $sortedOps->reverse();
            $records = [];
            $scheduledData = [];
            // Track created schedule-op IDs per parallel_group for sibling exclusion
            $parallelGroupOpIds = []; // ['GROUP-A' => [scheduleOpId1, ...]]

            foreach ($reversedOps as $op) {
                if ($op->is_external) {
                    $totalDays = (int) ($op->dispatch_buffer_days ?? 0) + (int) ($op->subcontract_lead_time_days ?? 0) + (int) ($op->return_buffer_days ?? 0);
                    $duration = $totalDays * 1440.0;

                    $latestFinish = $this->calculateLatestFinishFromSuccessors(
                        $scheduledData,
                        $op->sequence,
                        $op->parallel_group,
                        (bool) $op->is_parallel,
                        $dueDate,
                        (float) $order->quantity_ordered,
                        $op
                    );

                    $plannedFinish = $latestFinish->copy();
                    $plannedStart = $plannedFinish->copy()->subMinutes((int) ceil($duration));
                    $warnings = [];
                    $machineId = null;
                    $priority = 1;
                } else {
                    $times = $this->calculateOperationTimes($op, $order->quantity_ordered);
                    $duration = $times['total_minutes'];

                    // Calculate latest finish cursor based on successors
                    $latestFinish = $this->calculateLatestFinishFromSuccessors(
                        $scheduledData,
                        $op->sequence,
                        $op->parallel_group,
                        (bool) $op->is_parallel,
                        $dueDate,
                        (float) $order->quantity_ordered,
                        $op
                    );

                    // Collect parallel-group sibling schedule-op IDs to exclude from bookings
                    $excludeScheduleOpIds = [];
                    if ($op->is_parallel && $op->parallel_group !== null) {
                        $excludeScheduleOpIds = $parallelGroupOpIds[$op->parallel_group] ?? [];
                    }

                    // Find slot searching backwards
                    if ($op->routing_operation_id) {
                        $alloc = $this->findNextAvailableMachine($op->routing_operation_id, $latestFinish, $duration, $tenantId, false, $excludeScheduleOpIds);
                    } else {
                        $slot = $this->calculateAvailableSlot($op->work_center_id, $op->machine_id, $latestFinish, $duration, false, $excludeScheduleOpIds);
                        $warnings = $slot['warnings'];
                        if ($op->machine_id) {
                            $m = Machine::withoutGlobalScopes()->find($op->machine_id);
                            if ($m) {
                                $mCheck = $this->validateMachineForScheduling($m, $op->work_center_id, $tenantId);
                                if (!$mCheck['valid'] && !empty($mCheck['warning'])) {
                                    $warnings[] = $mCheck['warning'];
                                }
                            }
                        }
                        $alloc = [
                            'machine_id' => $op->machine_id,
                            'start' => $slot['start'],
                            'finish' => $slot['finish'],
                            'warnings' => $warnings,
                            'priority' => 1,
                        ];
                    }

                    $plannedStart = $alloc['start'] ?? $latestFinish->copy()->subMinutes((int) ceil($duration));
                    $plannedFinish = $alloc['finish'] ?? $latestFinish->copy();
                    $warnings = $alloc['warnings'] ?? [];
                    $machineId = $alloc['machine_id'] ?? null;
                    $priority = $alloc['priority'] ?? 1;
                }

                $lane = $op->work_center_id ? 'WorkCenter_' . $op->work_center_id : 'Vendor_' . ($op->vendor_id ?? 'External');
                $resourceId = $machineId ? 'Machine_' . $machineId : $lane;

                $records[] = [
                    'tenant_id' => $tenantId,
                    'production_schedule_id' => $schedule->id,
                    'production_order_id' => $order->id,
                    'production_order_operation_id' => $op->id,
                    'work_center_id' => $op->work_center_id,
                    'machine_id' => $machineId,
                    'sequence' => $op->sequence,
                    'priority' => $priority,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'baseline_start' => $plannedStart,
                    'baseline_finish' => $plannedFinish,
                    'planned_duration_minutes' => $duration,
                    'status' => ProductionScheduleOperation::STATUS_WAITING,
                    'warnings' => $warnings,
                    'locked' => false,
                    'lane' => $lane,
                    'resource_id' => $resourceId,
                    '_parallel_group' => $op->parallel_group,
                    '_is_parallel' => $op->is_parallel,
                ];

                $scheduledData[] = [
                    'sequence' => $op->sequence,
                    'parallel_group' => $op->parallel_group,
                    'is_parallel' => $op->is_parallel,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'work_center_id' => $op->work_center_id,
                    'machine_id' => $machineId,
                    'order_op' => $op,
                ];
            }

            // Write in correct sequence
            usort($records, fn($a, $b) => $a['sequence'] <=> $b['sequence']);
            if (count($records) > 0) {
                $records[0]['status'] = ProductionScheduleOperation::STATUS_READY;
            }

            foreach ($records as $record) {
                $parallelGroup = $record['_parallel_group'] ?? null;
                $isParallel = $record['_is_parallel'] ?? false;
                unset($record['_parallel_group'], $record['_is_parallel']);
                $schedOp = ProductionScheduleOperation::create($record);
                if ($isParallel && $parallelGroup !== null) {
                    $parallelGroupOpIds[$parallelGroup][] = $schedOp->id;
                }
            }

            $schedule->update([
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);


            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($order->tenant_id, [
                'production_order_id' => $order->id,
                'event_type' => 'Schedule Created',
                'title' => 'Production Schedule Created',
                'description' => "Schedule {$schedule->schedule_number} created via backward scheduling.",
                'severity' => 'info',
                'event_source' => 'SchedulingService',
                'triggered_by' => auth()->id(),
            ]);

            return $schedule;
        });
    }

    /**
     * Reschedule an existing ProductionSchedule.
     *
     * Repositions all unlocked operations from $newStartDate forward, preserving the
     * start times of any operations where `locked = true`. Only 'forward' reschedule
     * is supported in this release.
     *
     * @param  int    $scheduleId   ID of the ProductionSchedule to reschedule.
     * @param  Carbon $newStartDate New earliest start date for unlocked operations.
     * @param  string $type         Reschedule direction; only 'forward' is supported.
     * @return ProductionSchedule   The updated schedule with recalculated operation times.
     *
     * @throws \InvalidArgumentException When $type is not 'forward'.
     */
    public function reschedule(int $scheduleId, Carbon $newStartDate, string $type = 'forward'): ProductionSchedule
    {
        if ($type !== 'forward') {
            throw new \InvalidArgumentException("Rescheduling only supports 'forward' adjustment currently.");
        }

        return DB::transaction(function () use ($scheduleId, $newStartDate) {
            $schedule = ProductionSchedule::findOrFail($scheduleId);
            $ops = $schedule->operations()->orderBy('sequence')->get();
            $scheduledData = [];

            foreach ($ops as $op) {
                // Find order operation to check parallel group info
                $orderOp = ProductionOrderOperation::find($op->production_order_operation_id);
                $isParallel = $orderOp ? $orderOp->is_parallel : false;
                $parallelGroup = $orderOp ? $orderOp->parallel_group : null;

                $isFrozen = $op->locked || $op->isTerminal() || $op->isRunning() || $op->isPaused();

                if ($isFrozen) {
                    $scheduledData[] = [
                        'sequence' => $op->sequence,
                        'parallel_group' => $parallelGroup,
                        'is_parallel' => $isParallel,
                        'planned_start' => $op->planned_start->copy(),
                        'planned_finish' => $op->planned_finish->copy(),
                    ];
                    continue;
                }

                // Calculate earliest start based on predecessors in scheduledData
                $earliestStart = $this->calculateEarliestStartFromPredecessors(
                    $scheduledData,
                    $op->sequence,
                    $parallelGroup,
                    (bool) $isParallel,
                    $newStartDate,
                    (float) ($schedule->order?->quantity_ordered ?? 1)
                );

                $routingOpId = $orderOp ? $orderOp->routing_operation_id : 0;
                $alloc = $this->findNextAvailableMachine($routingOpId, $earliestStart, $op->planned_duration_minutes, $schedule->tenant_id, true);

                $plannedStart = $alloc['start'] ?? $earliestStart->copy();
                $plannedFinish = $alloc['finish'] ?? $earliestStart->copy()->addMinutes((int) ceil($op->planned_duration_minutes));

                $targetMachine = $alloc['machine_id'] ?? $op->machine_id;
                if ($targetMachine) {
                    app(CapacityPlanningService::class)->validateMachineAvailability(
                        $schedule->tenant_id,
                        (int) $targetMachine,
                        $plannedStart,
                        $plannedFinish,
                        $op->id
                    );
                }

                $op->update([
                    'machine_id' => $targetMachine,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'warnings' => $alloc['warnings'] ?? [],
                    'priority' => $alloc['priority'] ?? 1,
                    'resource_id' => $targetMachine ? 'Machine_' . $targetMachine : $op->resource_id,
                ]);

                $scheduledData[] = [
                    'sequence' => $op->sequence,
                    'parallel_group' => $parallelGroup,
                    'is_parallel' => $isParallel,
                    'planned_start' => $plannedStart,
                    'planned_finish' => $plannedFinish,
                    'work_center_id' => $op->work_center_id,
                    'machine_id' => $targetMachine,
                    'order_op' => $orderOp,
                ];
            }

            $schedule->update([
                'generated_by' => 'reschedule',
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($schedule->tenant_id, [
                'production_order_id' => $schedule->production_order_id,
                'event_type' => 'Schedule Rescheduled',
                'title' => 'Production Schedule Rescheduled',
                'description' => "Schedule {$schedule->schedule_number} has been rescheduled.",
                'severity' => 'info',
                'event_source' => 'SchedulingService',
                'triggered_by' => auth()->id(),
            ]);

            return $schedule;
        });
    }

    /**
     * Find the next available time slot for a resource.
     *
     * Searches shift windows, respects holiday calendars, and avoids overlapping
     * with existing bookings on the same machine or work center. Falls back to
     * unlimited capacity scheduling if no finite slot is found within 365 days.
     *
     * @param  int   $workCenterId     Work center to schedule against.
     * @param  int|null $machineId     Specific machine to book; null means work-center-level.
     * @param  Carbon   $from          Earliest acceptable start.
     * @param  float    $durationMinutes Operation duration in minutes.
     * @param  bool     $forward       True = search forward from $from; false = search backward.
     * @return array{start: Carbon, finish: Carbon, warnings: array}
     */
    public function calculateAvailableSlot(
        ?int $workCenterId,
        ?int $machineId,
        Carbon $from,
        float $durationMinutes,
        bool $forward = true,
        array $excludeScheduleOpIds = []
    ): array {
        if (!$workCenterId) {
            return [
                'start' => $forward ? $from->copy() : $from->copy()->subMinutes((int) $durationMinutes),
                'finish' => $forward ? $from->copy()->addMinutes((int) $durationMinutes) : $from->copy(),
                'warnings' => [],
            ];
        }

        $wc = $this->getCachedWorkCenter($workCenterId);
        if (!$wc) {
            return ['start' => $from->copy(), 'finish' => $from->copy()->addMinutes((int) $durationMinutes), 'warnings' => []];
        }

        $tenantId = $wc->tenant_id;
        $warnings = [];

        // 1. Resolve Calendar Fallback Hierarchy
        $calendar = $this->getCachedCalendar($wc, $tenantId);

        // Fetch shifts assigned to work center
        $shifts = $this->getCachedShifts($wc);
        if ($shifts->isEmpty()) {
            // Standard 8-hour shift fallback (480 minutes) if no shifts configured
            $shifts = collect([
                new ProductionShift([
                    'name' => 'Standard Shift',
                    'code' => 'STD',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'break_minutes' => 0,
                ])
            ]);
        }

        // Fetch other active operations on this resource to avoid overlap
        $bookingsQuery = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereNotIn('status', [
                ProductionScheduleOperation::STATUS_COMPLETED,
                ProductionScheduleOperation::STATUS_CANCELLED,
                ProductionScheduleOperation::STATUS_SKIPPED,
            ]);

        if ($machineId) {
            $bookingsQuery->where('machine_id', $machineId);
        } else {
            $bookingsQuery->where('work_center_id', $workCenterId)->whereNull('machine_id');
        }

        $bookings = $bookingsQuery->get();

        if ($machineId) {
            $downtimes = ProductionMachineDowntime::withoutGlobalScopes()
                ->where('machine_id', $machineId)
                ->whereNotIn('status', ['closed', 'resolved', 'cancelled'])
                ->get();

            foreach ($downtimes as $dt) {
                $bookings->push((object) [
                    'planned_start' => Carbon::parse($dt->start_time),
                    'planned_finish' => Carbon::parse($dt->end_time),
                ]);
            }
        }

        // Exclude parallel-group siblings so they may share the same slot
        if (!empty($excludeScheduleOpIds)) {
            $bookings = $bookings->whereNotIn('id', $excludeScheduleOpIds)->values();
        }

        $searchDate = $from->copy();
        $limitDays = 365;

        $remainingMinutes = $durationMinutes;
        $plannedStart = null;
        $plannedFinish = null;

        for ($day = 0; $day < $limitDays; $day++) {
            // Check calendar working days & holidays
            if (!$this->isWorkingDay($calendar, $searchDate, $tenantId)) {
                $warnings[] = [
                    'code' => 'HOLIDAY_SKIPPED',
                    'message' => "Scheduled date {$searchDate->toDateString()} skipped due to holiday/weekend configuration.",
                    'severity' => 'info',
                ];
                if ($forward) {
                    $searchDate->addDay()->startOfDay();
                } else {
                    $searchDate->subDay()->endOfDay();
                }
                continue;
            }

            // Build work windows from shifts
            $windows = [];
            foreach ($shifts as $shift) {
                $startStr = $searchDate->toDateString() . ' ' . $shift->start_time;
                $endStr = $searchDate->toDateString() . ' ' . $shift->end_time;

                $start = Carbon::parse($startStr);
                $end = Carbon::parse($endStr);

                if ($end->lt($start)) {
                    // Overlapping midnight shift
                    $end->addDay();
                }

                // Deduct breaks
                if ($shift->break_minutes > 0) {
                    $end->subMinutes($shift->break_minutes);
                }

                $windows[] = ['start' => $start, 'finish' => $end];
            }

            // Sort windows: if forward, earliest first; if backward, latest first
            if ($forward) {
                usort($windows, fn($a, $b) => $a['start'] <=> $b['start']);
            } else {
                usort($windows, fn($a, $b) => $b['start'] <=> $a['start']);
            }

            // Allocate duration into windows
            foreach ($windows as $window) {
                $searchStart = $forward ? $window['start']->max($from) : $window['start'];
                $searchFinish = $forward ? $window['finish'] : $window['finish']->min($from);

                if ($searchStart->gte($searchFinish)) {
                    continue;
                }

                // Get free slots inside this window
                $freeSlots = $this->getFreeSlots($searchStart, $searchFinish, $bookings);

                if (!$forward) {
                    // If backward, evaluate slots in reverse chronological order
                    $freeSlots = array_reverse($freeSlots);
                }

                $efficiency = $wc->efficiency_percentage ?? 100.0;
                if ($efficiency <= 0) {
                    $efficiency = 100.0;
                }
                $efficiencyFactor = $efficiency / 100.0;

                foreach ($freeSlots as $slot) {
                    $freeStart = $slot['start'];
                    $freeEnd = $slot['finish'];
                    if ($freeStart->gte($freeEnd)) {
                        continue;
                    }

                    $freeDuration = $freeStart->diffInMinutes($freeEnd);
                    $maxPlannedDuration = $freeDuration * $efficiencyFactor;

                    if ($forward) {
                        if (($slot['bounded_at_finish'] ?? false) && $remainingMinutes > $maxPlannedDuration && $durationMinutes > $maxPlannedDuration) {
                            continue;
                        }

                        if ($plannedStart === null) {
                            $plannedStart = $freeStart->copy();
                        }

                        if ($remainingMinutes <= $maxPlannedDuration) {
                            $calendarMinutesNeeded = $remainingMinutes / $efficiencyFactor;
                            $plannedFinish = $freeStart->copy()->addMinutes((int) ceil($calendarMinutesNeeded));
                            $remainingMinutes = 0;
                            break 3; // Finished allocating!
                        } else {
                            $remainingMinutes -= $maxPlannedDuration;
                            $plannedFinish = $freeEnd->copy();
                        }
                    } else {
                        // Backward scheduling
                        if (($slot['bounded_at_start'] ?? false) && $remainingMinutes > $maxPlannedDuration && $durationMinutes > $maxPlannedDuration) {
                            continue;
                        }

                        if ($plannedFinish === null) {
                            $plannedFinish = $freeEnd->copy();
                        }

                        if ($remainingMinutes <= $maxPlannedDuration) {
                            $calendarMinutesNeeded = $remainingMinutes / $efficiencyFactor;
                            $plannedStart = $freeEnd->copy()->subMinutes((int) ceil($calendarMinutesNeeded));
                            $remainingMinutes = 0;
                            break 3; // Finished allocating!
                        } else {
                            $remainingMinutes -= $maxPlannedDuration;
                            $plannedStart = $freeStart->copy();
                        }
                    }
                }
            }

            if ($forward) {
                $searchDate->addDay()->startOfDay();
            } else {
                $searchDate->subDay()->endOfDay();
            }
        }

        if ($remainingMinutes <= 0 && $plannedStart && $plannedFinish) {
            return [
                'start' => $plannedStart,
                'finish' => $plannedFinish,
                'warnings' => $warnings,
            ];
        }

        // Full fallback: Unlimited capacity schedule at start if no slot resolved in 365 days
        return [
            'start' => $forward ? $from->copy() : $from->copy()->subMinutes((int) $durationMinutes),
            'finish' => $forward ? $from->copy()->addMinutes((int) $durationMinutes) : $from->copy(),
            'warnings' => array_merge($warnings, [
                [
                    'code' => 'CAPACITY_OVERLOAD',
                    'message' => 'No finite slot found. Scheduled with standard unlimited capacity.',
                    'severity' => 'warning',
                ]
            ]),
        ];
    }

    private function getFreeSlots(Carbon $wStart, Carbon $wEnd, $bookings): array
    {
        $overlapping = $bookings->filter(
            fn($b) =>
            $b->planned_start->lt($wEnd) && $b->planned_finish->gt($wStart)
        )->sortBy('planned_start');

        $freeSlots = [];
        $currentStart = $wStart->copy();

        foreach ($overlapping as $b) {
            $bStart = $b->planned_start->max($wStart);
            $bEnd = $b->planned_finish->min($wEnd);

            if ($bStart->gt($currentStart)) {
                $freeSlots[] = [
                    'start' => $currentStart->copy(),
                    'finish' => $bStart->copy(),
                    'bounded_at_finish' => true,
                    'bounded_at_start' => ($currentStart->gt($wStart)),
                ];
            }
            $currentStart = $currentStart->max($bEnd);
        }

        if ($currentStart->lt($wEnd)) {
            $freeSlots[] = [
                'start' => $currentStart->copy(),
                'finish' => $wEnd->copy(),
                'bounded_at_finish' => false,
                'bounded_at_start' => ($currentStart->gt($wStart)),
            ];
        }

        return $freeSlots;
    }

    /**
     * Find the optimal machine for a routing operation.
     *
     * Evaluates the primary machine and all configured alternate machines.
     * Returns the candidate with the earliest available slot. If an alternate
     * machine is selected, a ALTERNATE_MACHINE_USED warning is appended.
     *
     * @param  int   $routingOpId     ID of the RoutingOperation that defines the machine constraints.
     * @param  Carbon $from           Earliest acceptable start time.
     * @param  float  $durationMinutes Duration in minutes.
     * @param  int    $tenantId        Tenant scope for machine validation.
     * @param  bool   $forward         Scheduling direction.
     * @return array{machine_id: int|null, start: Carbon, finish: Carbon, warnings: array, priority: int}
     */
    public function findNextAvailableMachine(
        int $routingOpId,
        Carbon $from,
        float $durationMinutes,
        int $tenantId,
        bool $forward = true,
        array $excludeScheduleOpIds = []
    ): array {
        $warnings = [];
        $routingOp = RoutingOperation::withoutGlobalScopes()->find($routingOpId);

        if (!$routingOp) {
            return [
                'machine_id' => null,
                'start' => $from->copy(),
                'finish' => $from->copy()->addMinutes((int) $durationMinutes),
                'warnings' => [],
                'priority' => 1,
            ];
        }

        $candidates = [];

        // 1. Evaluate Primary Machine
        if ($routingOp->machine_id) {
            $machine = Machine::withoutGlobalScopes()->find($routingOp->machine_id);
            if ($machine) {
                $validation = $this->validateMachineForScheduling($machine, $routingOp->work_center_id, $tenantId);
                if ($validation['valid']) {
                    $candidates[] = [
                        'machine' => $machine,
                        'priority' => 0, // Priority 0 is primary
                    ];
                } else {
                    $warnings[] = $validation['warning'];
                }
            }
        }

        // 2. Evaluate Alternates
        $alternates = RoutingOperationAlternateMachine::where('routing_operation_id', $routingOp->id)->get();
        foreach ($alternates as $alt) {
            $altMachine = Machine::withoutGlobalScopes()->find($alt->machine_id);
            if ($altMachine) {
                $validation = $this->validateMachineForScheduling($altMachine, $routingOp->work_center_id, $tenantId);
                if ($validation['valid']) {
                    $candidates[] = [
                        'machine' => $altMachine,
                        'priority' => $alt->priority,
                    ];
                } else {
                    $warnings[] = $validation['warning'];
                }
            }
        }

        if (empty($candidates)) {
            $warnings[] = [
                'code' => 'NO_AVAILABLE_MACHINE',
                'message' => 'No valid active machines configured for this routing operation.',
                'severity' => 'warning',
            ];

            return [
                'machine_id' => $routingOp->machine_id,
                'start' => $from->copy(),
                'finish' => $from->copy()->addMinutes((int) $durationMinutes),
                'warnings' => $warnings,
                'priority' => 1,
            ];
        }

        // Calculate slot for each valid candidate
        $evaluated = [];
        foreach ($candidates as $cand) {
            $slot = $this->calculateAvailableSlot($routingOp->work_center_id, $cand['machine']->id, $from, $durationMinutes, $forward, $excludeScheduleOpIds);
            $evaluated[] = [
                'machine' => $cand['machine'],
                'priority' => $cand['priority'],
                'slot' => $slot,
            ];
        }

        // Sort candidates: if forward: earliest start slot first. If backward: latest start slot first. Priority as tie-breaker.
        usort($evaluated, function ($a, $b) use ($forward) {
            $startA = $a['slot']['start'];
            $startB = $b['slot']['start'];
            if ($startA->eq($startB)) {
                return $a['priority'] <=> $b['priority'];
            }
            return $forward ? ($startA <=> $startB) : ($startB <=> $startA);
        });

        $winner = $evaluated[0];
        $allWarnings = array_merge($warnings, $winner['slot']['warnings']);
        $allWarnings = ProductionScheduleOperation::aggregateWarnings($allWarnings);

        // Warn if an alternate machine was selected over the primary
        if ($winner['priority'] > 0) {
            $allWarnings[] = [
                'code' => 'ALTERNATE_MACHINE_USED',
                'message' => "Primary machine was bypassed. Alternate machine [{$winner['machine']->name}] assigned.",
                'severity' => 'info',
            ];
        }

        $allWarnings = ProductionScheduleOperation::aggregateWarnings($allWarnings);

        return [
            'machine_id' => $winner['machine']->id,
            'start' => $winner['slot']['start'],
            'finish' => $winner['slot']['finish'],
            'warnings' => $allWarnings,
            'priority' => $winner['priority'] === 0 ? 1 : $winner['priority'] + 1,
        ];
    }

    /**
     * Resolve working hours of Work Center in minutes.
     */
    public function calculateCapacity(?int $workCenterId, Carbon $date): float
    {
        if (!$workCenterId) {
            return 0.0;
        }

        $wc = $this->getCachedWorkCenter($workCenterId);
        if (!$wc || !$wc->isActive()) {
            return 0.0;
        }

        $calendar = $this->getCachedCalendar($wc, $wc->tenant_id);
        if (!$this->isWorkingDay($calendar, $date, $wc->tenant_id)) {
            return 0.0;
        }

        $shifts = $this->getCachedShifts($wc);
        if ($shifts->isEmpty()) {
            // Standard shift default (480 minutes)
            $totalMinutes = 480.0;
        } else {
            $totalMinutes = 0.0;
            foreach ($shifts as $shift) {
                $start = Carbon::parse($shift->start_time);
                $end = Carbon::parse($shift->end_time);

                if ($end->lt($start)) {
                    $end->addDay();
                }

                $diff = $start->diffInMinutes($end);
                if ($shift->break_minutes > 0) {
                    $diff -= $shift->break_minutes;
                }

                $totalMinutes += max(0.0, $diff);
            }
        }

        $machineCount = $wc->machines()->where('status', Machine::STATUS_ACTIVE)->count();
        if ($machineCount <= 0) {
            $machineCount = 1;
        }

        return $totalMinutes * $machineCount * (($wc->efficiency_percentage ?? 100.0) / 100.0);
    }

    /**
     * Detect scheduling conflicts for a tenant.
     *
     * Identifies machine-level time overlaps: two operations assigned to the same
     * machine where the earlier operation's planned_finish exceeds the next
     * operation's planned_start.
     *
     * @param  int   $tenantId
     * @return string[]  Human-readable conflict descriptions.
     */
    public function detectConflicts(int $tenantId): array
    {
        $conflicts = [];
        $ops = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId)->whereIn('status', ['scheduled', 'released', 'in_progress']))
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->whereNotNull('machine_id')
            ->with(['machine', 'order', 'orderOperation'])
            ->orderBy('machine_id')
            ->orderBy('planned_start')
            ->get();

        $grouped = $ops->groupBy('machine_id');
        foreach ($grouped as $machineId => $machineOps) {
            $machineOps = $machineOps->values();
            for ($i = 0; $i < $machineOps->count() - 1; $i++) {
                $curr = $machineOps[$i];
                $next = $machineOps[$i + 1];

                if ($curr->planned_finish->gt($next->planned_start)) {
                    $mName = $curr->machine ? "{$curr->machine->name} ({$curr->machine->code})" : "Machine #{$machineId}";
                    $currOpName = $curr->orderOperation ? $curr->orderOperation->name : "Op #{$curr->sequence}";
                    $currOrder = $curr->order ? "Order #{$curr->order->order_number}" : "Schedule Op #{$curr->id}";
                    $nextOpName = $next->orderOperation ? $next->orderOperation->name : "Op #{$next->sequence}";
                    $nextOrder = $next->order ? "Order #{$next->order->order_number}" : "Schedule Op #{$next->id}";

                    $conflicts[] = "Overlap on machine {$mName}: {$currOrder} (Op #{$curr->sequence} - {$currOpName}) overlaps with {$nextOrder} (Op #{$next->sequence} - {$nextOpName}).";
                }
            }
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * Detect capacity overloads for a tenant.
     *
     * Groups active scheduled operations by (work_center_id, date) and compares
     * the total planned minutes against the work center's calculated capacity.
     * Returns a list of human-readable overload messages for affected work centers.
     *
     * NOTE: This method performs in-memory grouping after loading all active ops.
     * For large tenants, consider refactoring to a DB-level aggregation query.
     *
     * @param  int   $tenantId
     * @return string[]  Human-readable overload descriptions.
     */
    public function detectOverloads(int $tenantId): array
    {
        $overloads = [];
        $ops = ProductionScheduleOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['workCenter'])
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId)->whereIn('status', ['scheduled', 'released', 'in_progress']))
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
            ->get();

        if ($ops->isEmpty()) {
            return [];
        }

        $groupedByWc = $ops->groupBy('work_center_id');

        foreach ($groupedByWc as $wcId => $wcOps) {
            $firstOp = $wcOps->first();
            $wc = $firstOp ? $firstOp->workCenter : null;
            if (!$wc) {
                continue;
            }

            $minDate = $wcOps->min('planned_start');
            $maxDate = $wcOps->max('planned_finish');
            if (!$minDate || !$maxDate) {
                continue;
            }

            $date = $minDate->copy()->startOfDay();
            $wcMaxDate = $maxDate->copy()->endOfDay();

            while ($date->lte($wcMaxDate)) {
                $dateStr = $date->toDateString();
                $capacity = $this->calculateCapacity((int) $wcId, $date);

                $scheduledMinutes = 0.0;
                foreach ($wcOps as $op) {
                    if ($op->orderOperation && (bool) $op->orderOperation->is_external) {
                        continue;
                    }
                    $scheduledMinutes += $this->calculateOperationScheduledMinutesOnDate($op, $date);
                }

                if ($scheduledMinutes > $capacity) {
                    $overloads[] = "Work Center [{$wc->name}] overloaded on {$dateStr}: Scheduled " . round($scheduledMinutes, 1) . " minutes, Capacity is " . round($capacity, 1) . " minutes.";
                }

                $date->addDay();
            }
        }

        return array_values(array_unique($overloads));
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function calculateOperationTimes(ProductionOrderOperation $op, float $quantity): array
    {
        $setup = (float) $op->setup_time_planned;
        $proc = (float) $op->processing_time_planned * $quantity;
        return [
            'setup_minutes' => $setup,
            'processing_minutes' => $proc,
            'total_minutes' => $setup + $proc,
        ];
    }

    /**
     * Add working minutes to a timestamp respecting work center shifts, working days, and holidays.
     */
    public function addWorkingMinutes(?int $workCenterId, Carbon $from, float $minutes): Carbon
    {
        if ($minutes <= 0) {
            return $from->copy();
        }

        if (!$workCenterId) {
            return $from->copy()->addMinutes((int) ceil($minutes));
        }

        $wc = $this->getCachedWorkCenter($workCenterId);
        if (!$wc) {
            return $from->copy()->addMinutes((int) ceil($minutes));
        }

        $tenantId = $wc->tenant_id;
        $calendar = $this->getCachedCalendar($wc, $tenantId);
        $shifts = $this->getCachedShifts($wc);
        if ($shifts->isEmpty()) {
            $shifts = collect([
                new ProductionShift([
                    'name' => 'Standard Shift',
                    'code' => 'STD',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'break_minutes' => 0,
                ])
            ]);
        }

        $searchDate = $from->copy();
        $remaining = $minutes;

        for ($day = 0; $day < 365; $day++) {
            if (!$this->isWorkingDay($calendar, $searchDate, $tenantId)) {
                $searchDate->addDay()->startOfDay();
                continue;
            }

            foreach ($shifts as $shift) {
                $startStr = $searchDate->toDateString() . ' ' . $shift->start_time;
                $endStr = $searchDate->toDateString() . ' ' . $shift->end_time;
                $start = Carbon::parse($startStr);
                $end = Carbon::parse($endStr);
                if ($end->lt($start)) {
                    $end->addDay();
                }
                if ($shift->break_minutes > 0) {
                    $end->subMinutes($shift->break_minutes);
                }

                $searchStart = $start->max($searchDate);
                if ($searchStart->gte($end)) {
                    continue;
                }

                $avail = $searchStart->diffInMinutes($end);
                if ($remaining <= $avail) {
                    return $searchStart->copy()->addMinutes((int) ceil($remaining));
                }

                $remaining -= $avail;
                $searchDate = $end->copy();
            }

            $searchDate->addDay()->startOfDay();
        }

        return $from->copy()->addMinutes((int) ceil($minutes));
    }

    /**
     * Subtract working minutes from a timestamp respecting work center shifts, working days, and holidays.
     */
    public function subtractWorkingMinutes(?int $workCenterId, Carbon $from, float $minutes): Carbon
    {
        if ($minutes <= 0) {
            return $from->copy();
        }

        if (!$workCenterId) {
            return $from->copy()->subMinutes((int) ceil($minutes));
        }

        $wc = $this->getCachedWorkCenter($workCenterId);
        if (!$wc) {
            return $from->copy()->subMinutes((int) ceil($minutes));
        }

        $tenantId = $wc->tenant_id;
        $calendar = $this->getCachedCalendar($wc, $tenantId);
        $shifts = $this->getCachedShifts($wc);
        if ($shifts->isEmpty()) {
            $shifts = collect([
                new ProductionShift([
                    'name' => 'Standard Shift',
                    'code' => 'STD',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'break_minutes' => 0,
                ])
            ]);
        }

        $searchDate = $from->copy();
        $remaining = $minutes;

        for ($day = 0; $day < 365; $day++) {
            if (!$this->isWorkingDay($calendar, $searchDate, $tenantId)) {
                $searchDate->subDay()->endOfDay();
                continue;
            }

            $reversedShifts = $shifts->reverse();

            foreach ($reversedShifts as $shift) {
                $startStr = $searchDate->toDateString() . ' ' . $shift->start_time;
                $endStr = $searchDate->toDateString() . ' ' . $shift->end_time;
                $start = Carbon::parse($startStr);
                $end = Carbon::parse($endStr);
                if ($end->lt($start)) {
                    $end->addDay();
                }
                if ($shift->break_minutes > 0) {
                    $end->subMinutes($shift->break_minutes);
                }

                $searchFinish = $end->min($searchDate);
                if ($searchFinish->lte($start)) {
                    continue;
                }

                $avail = $start->diffInMinutes($searchFinish);
                if ($remaining <= $avail) {
                    return $searchFinish->copy()->subMinutes((int) ceil($remaining));
                }

                $remaining -= $avail;
                $searchDate = $start->copy();
            }

            $searchDate->subDay()->endOfDay();
        }

        return $from->copy()->subMinutes((int) ceil($minutes));
    }

    /**
     * Calculate transfer-ready timestamp for an operation given its planned start time.
     */
    public function calculateTransferReadyAt(
        ProductionOrderOperation $orderOp,
        Carbon $plannedStart,
        float $orderQuantity
    ): Carbon {
        if (!(bool) $orderOp->overlap_enabled) {
            $times = $this->calculateOperationTimes($orderOp, $orderQuantity);
            return $this->addWorkingMinutes($orderOp->work_center_id, $plannedStart, $times['total_minutes']);
        }

        $setupMinutes = (float) ($orderOp->setup_time_planned ?? 0);
        $batchQty = (float) ($orderOp->transfer_batch_quantity ?? 0);
        $lagMinutes = (float) ($orderOp->transfer_lag_minutes ?? 0);
        $effectiveTransferQty = min($batchQty, $orderQuantity);

        $firstBatchTimes = $this->calculateOperationTimes($orderOp, $effectiveTransferQty);
        $firstBatchRunDuration = (float) ($firstBatchTimes['processing_minutes'] ?? 0);
        $totalOffsetMinutes = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

        return $this->addWorkingMinutes(
            $orderOp->work_center_id,
            $plannedStart,
            $totalOffsetMinutes
        );
    }

    /**
     * Calculate the latest allowable finish timestamp for an operation based on its scheduled successors.
     * Respects overlap_enabled, transfer_batch_quantity, and transfer_lag_minutes for backward overlapping scheduling.
     */
    public function calculateLatestFinishFromSuccessors(
        array $scheduledData,
        int $opSequence,
        ?string $parallelGroup,
        bool $isParallel,
        Carbon $defaultDueDate,
        float $orderQuantity,
        ?ProductionOrderOperation $currentOp = null
    ): Carbon {
        $successorOpIds = [];
        if ($currentOp) {
            $allOps = ProductionOrderOperation::where('tenant_id', $currentOp->tenant_id)
                ->where('production_order_id', $currentOp->production_order_id)
                ->with('predecessorDependencies')
                ->get();
            foreach ($allOps as $aOp) {
                if ($aOp->previous_operation_id === $currentOp->id || $aOp->predecessorDependencies->contains($currentOp->id)) {
                    $successorOpIds[] = $aOp->id;
                }
            }
        }

        $successors = collect($scheduledData)->filter(function ($succ) use ($opSequence, $parallelGroup, $isParallel, $successorOpIds) {
            if (!empty($successorOpIds)) {
                $succOpId = $succ['order_op']?->id ?? $succ['production_order_operation_id'] ?? null;
                if (!$succOpId || !in_array($succOpId, $successorOpIds)) {
                    return false;
                }
            } else {
                if ($succ['sequence'] <= $opSequence) {
                    return false;
                }
            }
            if ($isParallel && $parallelGroup !== null && ($succ['parallel_group'] ?? null) === $parallelGroup) {
                return false;
            }
            return true;
        });

        if ($successors->isEmpty()) {
            return $defaultDueDate->copy();
        }

        $latestFinishTimestamps = $successors->map(function ($succ) use ($orderQuantity, $currentOp) {
            $succStart = $succ['planned_start']->copy();

            if ($currentOp && (bool) $currentOp->is_external) {
                return $succStart;
            }

            if ($currentOp && (bool) $currentOp->overlap_enabled) {
                // Same machine cannot overlap with itself: enforce Finish-to-Start on same machine
                if ($currentOp->machine_id && ($succ['machine_id'] ?? null) === $currentOp->machine_id) {
                    return $succStart;
                }

                $setupMinutes = (float) ($currentOp->setup_time_planned ?? 0);
                $batchQty = (float) ($currentOp->transfer_batch_quantity ?? 0);
                $lagMinutes = (float) ($currentOp->transfer_lag_minutes ?? 0);
                $effectiveTransferQty = min($batchQty, $orderQuantity);

                $firstBatchTimes = $this->calculateOperationTimes($currentOp, $effectiveTransferQty);
                $firstBatchRunDuration = (float) ($firstBatchTimes['processing_minutes'] ?? 0);
                $totalOffsetMinutes = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

                if ($currentOp->work_center_id) {
                    // Latest allowable start time for currentOp to meet successor start
                    $maxStart = $this->subtractWorkingMinutes(
                        $currentOp->work_center_id,
                        $succStart,
                        $totalOffsetMinutes
                    );

                    // Latest allowable finish time for currentOp
                    $totalTimes = $this->calculateOperationTimes($currentOp, $orderQuantity);
                    return $this->addWorkingMinutes(
                        $currentOp->work_center_id,
                        $maxStart,
                        $totalTimes['total_minutes']
                    );
                }
            }

            // Non-overlapping: currentOp finish must be at or before successor start
            return $succStart;
        });

        return $latestFinishTimestamps->min();
    }

    /**
     * Calculate the earliest available start timestamp for an operation based on its scheduled predecessors.
     * Respects overlap_enabled, transfer_batch_quantity, and transfer_lag_minutes for forward overlapping scheduling.
     */
    public function calculateEarliestStartFromPredecessors(
        array $scheduledData,
        int $opSequence,
        ?string $parallelGroup,
        bool $isParallel,
        Carbon $defaultStart,
        float $orderQuantity,
        ?ProductionOrderOperation $currentOp = null
    ): Carbon {
        $predecessorOpIds = [];
        if ($currentOp) {
            if ($currentOp->previous_operation_id) {
                $predecessorOpIds[] = $currentOp->previous_operation_id;
            }
            if ($currentOp->relationLoaded('predecessorDependencies')) {
                foreach ($currentOp->predecessorDependencies as $pred) {
                    $predecessorOpIds[] = $pred->id;
                }
            }
        }

        $predecessors = collect($scheduledData)->filter(function ($prev) use ($opSequence, $parallelGroup, $isParallel, $predecessorOpIds) {
            if (!empty($predecessorOpIds)) {
                $prevOpId = $prev['order_op']?->id ?? $prev['production_order_operation_id'] ?? null;
                if (!$prevOpId || !in_array($prevOpId, $predecessorOpIds)) {
                    return false;
                }
            } else {
                if ($prev['sequence'] >= $opSequence) {
                    return false;
                }
            }
            if ($isParallel && $parallelGroup !== null && ($prev['parallel_group'] ?? null) === $parallelGroup) {
                return false;
            }
            return true;
        });

        if ($predecessors->isEmpty()) {
            return $defaultStart->copy();
        }

        $earliestStartTimestamps = $predecessors->map(function ($prev) use ($orderQuantity) {
            $prevOrderOp = $prev['order_op'] ?? null;

            if ($prevOrderOp && (bool) $prevOrderOp->is_external) {
                return $prev['planned_finish']->copy();
            }

            // Check if overlap scheduling is enabled on the predecessor operation
            if ($prevOrderOp && (bool) $prevOrderOp->overlap_enabled) {
                $setupMinutes = (float) ($prevOrderOp->setup_time_planned ?? 0);
                $batchQty = (float) ($prevOrderOp->transfer_batch_quantity ?? 0);
                $lagMinutes = (float) ($prevOrderOp->transfer_lag_minutes ?? 0);
                $effectiveTransferQty = min($batchQty, $orderQuantity);

                $firstBatchTimes = $this->calculateOperationTimes($prevOrderOp, $effectiveTransferQty);
                $firstBatchRunDuration = (float) ($firstBatchTimes['processing_minutes'] ?? 0);
                $totalOffsetMinutes = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

                if ($totalOffsetMinutes > 0 && isset($prev['work_center_id']) && isset($prev['planned_start'])) {
                    return $this->addWorkingMinutes(
                        $prev['work_center_id'],
                        $prev['planned_start'],
                        $totalOffsetMinutes
                    );
                }

                return isset($prev['planned_start']) ? $prev['planned_start']->copy() : $prev['planned_finish']->copy();
            }

            // Fallback to standard Finish-to-Start dependency
            return $prev['planned_finish']->copy();
        });

        return $earliestStartTimestamps->max()->copy();
    }

    /**
     * Sort operations topologically (predecessors before successors) considering both
     * intra-routing previous_operation_id and cross-assembly predecessorDependencies.
     */
    public function sortOperationsTopologically(\Illuminate\Support\Collection $operations): \Illuminate\Support\Collection
    {
        $opMap = $operations->keyBy('id');
        $inDegree = [];
        $graph = [];

        foreach ($operations as $op) {
            $id = $op->id;
            if (!isset($inDegree[$id])) {
                $inDegree[$id] = 0;
            }
            if (!isset($graph[$id])) {
                $graph[$id] = [];
            }

            // 1. Intra-routing predecessor
            if ($op->previous_operation_id && $opMap->has($op->previous_operation_id)) {
                $predId = $op->previous_operation_id;
                $graph[$predId][] = $id;
                $inDegree[$id]++;
            }

            // 2. Cross-assembly predecessors
            if ($op->relationLoaded('predecessorDependencies')) {
                foreach ($op->predecessorDependencies as $predOp) {
                    if ($opMap->has($predOp->id)) {
                        $graph[$predOp->id][] = $id;
                        $inDegree[$id]++;
                    }
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $sorted = collect();
        while (!empty($queue)) {
            $currId = array_shift($queue);
            $sorted->push($opMap->get($currId));

            foreach ($graph[$currId] as $succId) {
                $inDegree[$succId]--;
                if ($inDegree[$succId] === 0) {
                    $queue[] = $succId;
                }
            }
        }

        if ($sorted->count() < $operations->count()) {
            throw new \LogicException("Circular dependency detected in Production Order operations graph.");
        }

        return $sorted;
    }

    private function resolveCalendar(WorkCenter $wc, int $tenantId): ProductionCalendar
    {
        if ($wc->production_calendar_id) {
            $cal = ProductionCalendar::withoutGlobalScopes()->find($wc->production_calendar_id);
            if ($cal)
                return $cal;
        }

        $default = ProductionCalendar::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if ($default)
            return $default;

        // Mon-Fri virtual fallback
        return new ProductionCalendar([
            'name' => 'Mon-Fri Fallback Calendar',
            'working_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function isWorkingDay(ProductionCalendar $calendar, Carbon $date, int $tenantId): bool
    {
        $dayOfWeek = $date->dayOfWeek;
        $workingDays = $calendar->working_days ?? [1, 2, 3, 4, 5];

        $dayNamesMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];
        $workingDaysNormalized = array_map(function ($day) use ($dayNamesMap) {
            if (is_numeric($day)) {
                return (int) $day;
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

    private function validateMachineForScheduling(Machine $machine, int $workCenterId, int $tenantId): array
    {
        if ($machine->tenant_id !== $tenantId) {
            return [
                'valid' => false,
                'warning' => [
                    'code' => 'MACHINE_UNAVAILABLE',
                    'message' => "Machine [{$machine->name}] does not belong to this tenant.",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->work_center_id !== $workCenterId) {
            return [
                'valid' => false,
                'warning' => [
                    'code' => 'MACHINE_UNAVAILABLE',
                    'message' => "Machine [{$machine->name}] does not belong to Work Center #{$workCenterId}.",
                    'severity' => 'warning',
                ],
            ];
        }

        if (!$machine->isActive()) {
            return [
                'valid' => false,
                'warning' => [
                    'code' => 'MACHINE_UNAVAILABLE',
                    'message' => "Machine [{$machine->name}] is not active (status: {$machine->status}).",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->isUnderMaintenance()) {
            return [
                'valid' => false,
                'warning' => [
                    'code' => 'MACHINE_UNDER_MAINTENANCE',
                    'message' => "Machine [{$machine->name}] is under maintenance.",
                    'severity' => 'warning',
                ],
            ];
        }

        if ($machine->isDecommissioned() || $machine->isInactive()) {
            return [
                'valid' => false,
                'warning' => [
                    'code' => 'MACHINE_UNAVAILABLE',
                    'message' => "Machine [{$machine->name}] is decommissioned or inactive.",
                    'severity' => 'warning',
                ],
            ];
        }

        return ['valid' => true];
    }

    private function calculateOverallUtilization(ProductionSchedule $schedule): float
    {
        $ops = $schedule->operations;
        if ($ops->isEmpty())
            return 0.00;

        $totalScheduled = $ops->sum('planned_duration_minutes');

        // Sum total daily capacities for the scheduled dates
        $workCenterIds = $ops->pluck('work_center_id')->filter()->unique();
        $minDate = $ops->min('planned_start');
        $maxDate = $ops->max('planned_finish');

        if (!$minDate || !$maxDate)
            return 0.00;

        $totalCapacity = 0.0;
        $date = $minDate->copy()->startOfDay();

        while ($date->lte($maxDate)) {
            foreach ($workCenterIds as $wcId) {
                if ($wcId) {
                    $totalCapacity += $this->calculateCapacity((int) $wcId, $date);
                }
            }
            $date->addDay();
        }

        if ($totalCapacity <= 0.0)
            return 100.00;

        return (float) round(min(100.00, ($totalScheduled / $totalCapacity) * 100.00), 2);
    }

    /**
     * Validate a generated schedule.
     *
     * @throws \LogicException on the first validation failure found.
     */
    public function validateSchedule(ProductionSchedule $schedule): void
    {
        $tenantId = $schedule->order->tenant_id;

        foreach ($schedule->operations()->with(['workCenter', 'machine'])->get() as $op) {
            if (!$op->work_center_id) {
                continue;
            }

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
     * Get capacity utilization details for each work center in the schedule.
     *
     * @param  ProductionSchedule $schedule
     * @return array
     */
    public function getWorkCenterCapacityDetails(ProductionSchedule $schedule, ?string $groupBy = null): array
    {
        $ops = $schedule->operations;
        if ($ops->isEmpty())
            return [];

        $minDate = $ops->min('planned_start');
        $maxDate = $ops->max('planned_finish');
        if (!$minDate || !$maxDate)
            return [];

        $details = [];
        $groupedOps = $ops->groupBy('work_center_id');

        foreach ($groupedOps as $wcId => $wcOps) {
            if (!$wcId) {
                continue;
            }
            $wc = WorkCenter::withoutGlobalScopes()->find($wcId);
            if (!$wc)
                continue;

            $totalScheduled = $wcOps->sum('planned_duration_minutes');
            $totalCapacity = 0.0;

            $date = $minDate->copy()->startOfDay();
            while ($date->lte($maxDate)) {
                $totalCapacity += $this->calculateCapacity($wcId, $date);
                $date->addDay();
            }

            $utilization = $totalCapacity > 0 ? ($totalScheduled / $totalCapacity) * 100 : 100;

            $shiftsList = $wc->shifts()->where('active', true)->pluck('name')->toArray();
            $calendarName = $wc->calendar ? $wc->calendar->name : 'Mon-Fri Fallback';
            $workingDays = $wc->calendar ? $wc->calendar->working_days : [1, 2, 3, 4, 5];

            $daysMap = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
            $workingDaysStr = implode(', ', array_map(fn($d) => $daysMap[$d] ?? $d, $workingDays));

            $activeMachinesCount = $wc->activeMachines()->count();

            $dailyBreakdown = [];
            $date = $minDate->copy()->startOfDay();
            while ($date->lte($maxDate)) {
                $dateStr = $date->toDateString();
                $dayCapacity = $this->calculateCapacity($wcId, $date);

                $dayScheduled = 0.0;
                foreach ($wcOps as $op) {
                    $dayScheduled += $this->calculateOperationScheduledMinutesOnDate($op, $date);
                }

                $dailyBreakdown[] = [
                    'date' => $dateStr,
                    'carbon' => $date->copy(),
                    'day_name' => $date->format('l'),
                    'capacity_minutes' => $dayCapacity,
                    'scheduled_minutes' => $dayScheduled,
                ];
                $date->addDay();
            }

            // Determine grouping type
            $daysCount = $minDate->diffInDays($maxDate);
            $groupType = $groupBy ?: ($daysCount <= 14 ? 'day' : ($daysCount <= 90 ? 'week' : 'month'));

            $finalBreakdown = [];
            if ($groupType === 'week') {
                $weeks = [];
                foreach ($dailyBreakdown as $day) {
                    $monday = $day['carbon']->copy()->startOfWeek()->toDateString();
                    $weeks[$monday][] = $day;
                }

                foreach ($weeks as $monDate => $daysInWeek) {
                    $mon = Carbon::parse($monDate);
                    $sun = $mon->copy()->endOfWeek();

                    $scheduled = array_sum(array_column($daysInWeek, 'scheduled_minutes'));
                    $capacity = array_sum(array_column($daysInWeek, 'capacity_minutes'));

                    $finalBreakdown[] = [
                        'date' => $mon->format('d/m/Y') . ' - ' . $sun->format('d/m/Y'),
                        'day_name' => 'Week ' . $mon->format('W'),
                        'capacity_minutes' => $capacity,
                        'scheduled_minutes' => $scheduled,
                        'utilization' => $capacity > 0 ? min(100.00, ($scheduled / $capacity) * 100) : ($scheduled > 0 ? 100.00 : 0.00),
                    ];
                }
            } elseif ($groupType === 'month') {
                $months = [];
                foreach ($dailyBreakdown as $day) {
                    $monthKey = $day['carbon']->format('Y-m');
                    $months[$monthKey][] = $day;
                }

                foreach ($months as $monthKey => $daysInMonth) {
                    $mStart = Carbon::parse($monthKey . '-01');

                    $scheduled = array_sum(array_column($daysInMonth, 'scheduled_minutes'));
                    $capacity = array_sum(array_column($daysInMonth, 'capacity_minutes'));

                    $finalBreakdown[] = [
                        'date' => $mStart->format('F Y'),
                        'day_name' => 'Month',
                        'capacity_minutes' => $capacity,
                        'scheduled_minutes' => $scheduled,
                        'utilization' => $capacity > 0 ? min(100.00, ($scheduled / $capacity) * 100) : ($scheduled > 0 ? 100.00 : 0.00),
                    ];
                }
            } else {
                // Day breakdown
                foreach ($dailyBreakdown as $day) {
                    $finalBreakdown[] = [
                        'date' => $day['date'],
                        'day_name' => $day['day_name'],
                        'capacity_minutes' => $day['capacity_minutes'],
                        'scheduled_minutes' => $day['scheduled_minutes'],
                        'utilization' => $day['capacity_minutes'] > 0 ? min(100.00, ($day['scheduled_minutes'] / $day['capacity_minutes']) * 100) : ($day['scheduled_minutes'] > 0 ? 100.00 : 0.00),
                    ];
                }
            }

            $details[] = [
                'work_center' => $wc,
                'calendar_name' => $calendarName,
                'working_days' => $workingDaysStr,
                'shifts' => empty($shiftsList) ? 'Standard Shift (Fallback)' : implode(', ', $shiftsList),
                'active_machines' => $activeMachinesCount,
                'scheduled_minutes' => $totalScheduled,
                'capacity_minutes' => $totalCapacity,
                'utilization' => min(100.00, $utilization),
                'daily_breakdown' => $finalBreakdown,
                'group_type' => $groupType,
            ];
        }

        return $details;
    }

    public function calculateOperationScheduledMinutesOnDate($op, Carbon $date): float
    {
        $wc = $this->getCachedWorkCenter($op->work_center_id);
        if (!$wc)
            return 0.0;

        $tenantId = $wc->tenant_id;
        $calendar = $this->getCachedCalendar($wc, $tenantId);

        // If it's a holiday or weekend, no active work can be done
        if (!$this->isWorkingDay($calendar, $date, $tenantId)) {
            return 0.0;
        }

        // Retrieve shift windows
        $shifts = $this->getCachedShifts($wc);
        if ($shifts->isEmpty()) {
            $shifts = collect([
                new ProductionShift([
                    'name' => 'Standard Shift',
                    'code' => 'STD',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'break_minutes' => 0,
                ])
            ]);
        }

        $windows = [];
        foreach ($shifts as $shift) {
            $startStr = $date->toDateString() . ' ' . $shift->start_time;
            $endStr = $date->toDateString() . ' ' . $shift->end_time;

            $start = Carbon::parse($startStr);
            $end = Carbon::parse($endStr);

            if ($end->lt($start)) {
                $end->addDay();
            }

            if ($shift->break_minutes > 0) {
                $end->subMinutes($shift->break_minutes);
            }

            $windows[] = ['start' => $start, 'finish' => $end];
        }

        $totalOverlap = 0.0;
        $opStart = $op->planned_start;
        $opFinish = $op->planned_finish;

        foreach ($windows as $window) {
            $wStart = $window['start'];
            $wEnd = $window['finish'];

            $overlapStart = $opStart->max($wStart);
            $overlapEnd = $opFinish->min($wEnd);

            if ($overlapStart->lt($overlapEnd)) {
                $totalOverlap += $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        // Fallback for standard unlimited capacity scheduling (outside shifts)
        if ($totalOverlap === 0.0) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $overlapStart = $opStart->max($dayStart);
            $overlapEnd = $opFinish->min($dayEnd);

            if ($overlapStart->lt($overlapEnd)) {
                $totalOverlap = $overlapStart->diffInMinutes($overlapEnd);
                // Proportional scale to match planned_duration_minutes if it spans multiple days
                $totalOpDuration = max(1.0, $opStart->diffInMinutes($opFinish));
                $ratio = min(1.0, $op->planned_duration_minutes / $totalOpDuration);
                return $totalOverlap * $ratio;
            }
            return 0.0;
        }

        $efficiency = $wc->efficiency_percentage ?? 100.0;
        if ($efficiency <= 0) {
            $efficiency = 100.0;
        }
        $efficiencyFactor = $efficiency / 100.0;

        return $totalOverlap * $efficiencyFactor;
    }

    /**
     * Check if a schedule has any MES or WIP execution history.
     */
    public function hasExecutionHistory(ProductionSchedule $schedule): bool
    {
        // 1. Started, paused or completed operations
        $hasExecutionStatus = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)
            ->whereIn('status', [
                ProductionScheduleOperation::STATUS_RUNNING,
                ProductionScheduleOperation::STATUS_PAUSED,
                ProductionScheduleOperation::STATUS_COMPLETED
            ])
            ->exists();
        if ($hasExecutionStatus) {
            return true;
        }

        // 2. Actual start/finish times on schedule operations
        $hasActualTimes = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)
            ->where(fn($q) => $q->whereNotNull('actual_start')->orWhereNotNull('actual_finish'))
            ->exists();
        if ($hasActualTimes) {
            return true;
        }

        // 3. Lookups on related progress logs, WIP records and quantities
        $schedOpIds = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)->pluck('id')->toArray();
        if (!empty($schedOpIds)) {
            if (\App\Domains\Production\Models\ProductionWip::where('tenant_id', $schedule->tenant_id)->whereIn('current_schedule_operation_id', $schedOpIds)->exists()) {
                return true;
            }
        }

        $opIds = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)
            ->pluck('production_order_operation_id')
            ->filter()
            ->toArray();

        if (!empty($opIds)) {
            if (\App\Domains\Production\Models\ProductionOrderProgressLog::where('tenant_id', $schedule->tenant_id)->whereIn('operation_id', $opIds)->exists()) {
                return true;
            }
            $hasQuantity = ProductionOrderOperation::whereIn('id', $opIds)
                ->where(fn($q) => $q->where('quantity_produced', '>', 0)
                    ->orWhere('quantity_rejected', '>', 0)
                    ->orWhere('quantity_scrapped', '>', 0))
                ->exists();
            if ($hasQuantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform an in-place reschedule of waiting/ready operations on a schedule.
     */
    public function partialReschedule(ProductionSchedule $schedule, Carbon $startDate, string $type = ProductionSchedule::TYPE_FORWARD): ProductionSchedule
    {
        return DB::transaction(function () use ($schedule, $startDate, $type) {
            // Lock schedule for update
            $schedule = ProductionSchedule::lockForUpdate()->findOrFail($schedule->id);
            $order = $schedule->order;
            if (!$order) {
                throw new \LogicException("Cannot reschedule: Production Order is missing for Schedule [{$schedule->schedule_number}].");
            }

            $operations = $schedule->operations()->orderBy('sequence')->get();
            if ($operations->isEmpty()) {
                return $schedule;
            }

            $tenantId = $schedule->tenant_id;
            $scheduledData = [];

            // 1. Identify which operations are frozen
            $frozenOpIds = [];
            foreach ($operations as $op) {
                $isFrozen = in_array($op->status, [
                    ProductionScheduleOperation::STATUS_RUNNING,
                    ProductionScheduleOperation::STATUS_PAUSED,
                    ProductionScheduleOperation::STATUS_COMPLETED
                ]) || !is_null($op->actual_start) || !is_null($op->actual_finish) || (bool) $op->locked;

                if ($isFrozen) {
                    $frozenOpIds[] = $op->id;
                    $scheduledData[$op->sequence] = [
                        'sequence' => $op->sequence,
                        'parallel_group' => $op->orderOperation?->parallel_group,
                        'is_parallel' => $op->orderOperation?->is_parallel,
                        'planned_start' => $op->planned_start,
                        'planned_finish' => $op->planned_finish,
                    ];
                }
            }

            // 2. Reschedule only non-frozen (eligible pending) operations
            if ($type === ProductionSchedule::TYPE_FORWARD) {
                $isFirstPending = true;
                foreach ($operations as $op) {
                    if (in_array($op->id, $frozenOpIds)) {
                        continue;
                    }

                    // Calculate earliest start based on predecessors
                    $earliestStart = $this->calculateEarliestStartFromPredecessors(
                        $scheduledData,
                        $op->sequence,
                        $op->orderOperation?->parallel_group,
                        (bool) $op->orderOperation?->is_parallel,
                        $startDate,
                        (float) ($order->quantity_ordered ?? 1)
                    );

                    $times = $this->calculateOperationTimes($op->orderOperation, $order->quantity_ordered);
                    $duration = $times['total_minutes'];

                    // Find slot
                    if ($op->orderOperation?->routing_operation_id) {
                        $alloc = $this->findNextAvailableMachine($op->orderOperation->routing_operation_id, $earliestStart, $duration, $tenantId, true);
                    } else {
                        $slot = $this->calculateAvailableSlot($op->work_center_id, null, $earliestStart, $duration, true);
                        $alloc = [
                            'machine_id' => null,
                            'start' => $slot['start'],
                            'finish' => $slot['finish'],
                            'warnings' => $slot['warnings'],
                            'priority' => 1,
                        ];
                    }

                    $plannedStart = $alloc['start'] ?? $earliestStart->copy();
                    $plannedFinish = $alloc['finish'] ?? $earliestStart->copy()->addMinutes((int) ceil($duration));
                    $warnings = $alloc['warnings'] ?? [];
                    $machineId = $alloc['machine_id'] ?? null;
                    $priority = $alloc['priority'] ?? 1;

                    // Capture old values for audit logging
                    $oldStart = $op->planned_start;
                    $oldFinish = $op->planned_finish;
                    $oldMachineId = $op->machine_id;

                    $op->update([
                        'planned_start' => $plannedStart,
                        'planned_finish' => $plannedFinish,
                        'machine_id' => $machineId,
                        'priority' => $priority,
                        'warnings' => $warnings,
                        'resource_id' => $machineId ? 'Machine_' . $machineId : 'WorkCenter_' . $op->work_center_id,
                        'status' => $isFirstPending
                            ? ProductionScheduleOperation::STATUS_READY
                            : ProductionScheduleOperation::STATUS_WAITING,
                    ]);

                    $isFirstPending = false;

                    $scheduledData[$op->sequence] = [
                        'sequence' => $op->sequence,
                        'parallel_group' => $op->orderOperation?->parallel_group,
                        'is_parallel' => $op->orderOperation?->is_parallel,
                        'planned_start' => $plannedStart,
                        'planned_finish' => $plannedFinish,
                        'work_center_id' => $op->work_center_id,
                        'machine_id' => $machineId,
                        'order_op' => $op->orderOperation,
                    ];

                    // Write Event timeline entry
                    app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                        'production_order_id' => $order->id,
                        'production_order_operation_id' => $op->production_order_operation_id,
                        'machine_id' => $machineId,
                        'event_type' => 'Schedule Rescheduled',
                        'title' => 'Operation Rescheduled',
                        'description' => "Operation Seq #{$op->sequence} Rescheduled: Start changed from {$oldStart?->format('Y-m-d H:i')} to {$plannedStart->format('Y-m-d H:i')}.",
                        'severity' => 'warning',
                        'event_source' => 'SchedulingService',
                        'metadata' => [
                            'old_start' => $oldStart?->toDateTimeString(),
                            'new_start' => $plannedStart->toDateTimeString(),
                            'old_finish' => $oldFinish?->toDateTimeString(),
                            'new_finish' => $plannedFinish->toDateTimeString(),
                            'old_machine_id' => $oldMachineId,
                            'new_machine_id' => $machineId,
                        ],
                    ]);
                }
            } else {
                // BACKWARD scheduling
                $reversedOps = $operations->reverse();
                foreach ($reversedOps as $op) {
                    if (in_array($op->id, $frozenOpIds)) {
                        continue;
                    }

                    $latestFinish = $this->calculateLatestFinishFromSuccessors(
                        $scheduledData,
                        $op->sequence,
                        $op->orderOperation?->parallel_group,
                        (bool) $op->orderOperation?->is_parallel,
                        $startDate,
                        (float) ($order->quantity_ordered ?? 1),
                        $op->orderOperation
                    );

                    $times = $this->calculateOperationTimes($op->orderOperation, $order->quantity_ordered);
                    $duration = $times['total_minutes'];

                    if ($op->orderOperation?->routing_operation_id) {
                        $alloc = $this->findNextAvailableMachine($op->orderOperation->routing_operation_id, $latestFinish, $duration, $tenantId, false);
                    } else {
                        $slot = $this->calculateAvailableSlot($op->work_center_id, null, $latestFinish, $duration, false);
                        $alloc = [
                            'machine_id' => null,
                            'start' => $slot['start'],
                            'finish' => $slot['finish'],
                            'warnings' => $slot['warnings'],
                            'priority' => 1,
                        ];
                    }

                    $plannedStart = $alloc['start'] ?? $latestFinish->copy()->subMinutes((int) ceil($duration));
                    $plannedFinish = $alloc['finish'] ?? $latestFinish->copy();
                    $warnings = $alloc['warnings'] ?? [];
                    $machineId = $alloc['machine_id'] ?? null;
                    $priority = $alloc['priority'] ?? 1;

                    $oldStart = $op->planned_start;
                    $oldFinish = $op->planned_finish;
                    $oldMachineId = $op->machine_id;

                    $op->update([
                        'planned_start' => $plannedStart,
                        'planned_finish' => $plannedFinish,
                        'machine_id' => $machineId,
                        'priority' => $priority,
                        'warnings' => $warnings,
                        'resource_id' => $machineId ? 'Machine_' . $machineId : 'WorkCenter_' . $op->work_center_id,
                        'status' => ProductionScheduleOperation::STATUS_WAITING,
                    ]);

                    $scheduledData[$op->sequence] = [
                        'sequence' => $op->sequence,
                        'parallel_group' => $op->orderOperation?->parallel_group,
                        'is_parallel' => $op->orderOperation?->is_parallel,
                        'planned_start' => $plannedStart,
                        'planned_finish' => $plannedFinish,
                    ];

                    app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                        'production_order_id' => $order->id,
                        'production_order_operation_id' => $op->production_order_operation_id,
                        'machine_id' => $machineId,
                        'event_type' => 'Schedule Rescheduled',
                        'title' => 'Operation Rescheduled',
                        'description' => "Operation Seq #{$op->sequence} Rescheduled: Start changed from {$oldStart?->format('Y-m-d H:i')} to {$plannedStart->format('Y-m-d H:i')}.",
                        'severity' => 'warning',
                        'event_source' => 'SchedulingService',
                        'metadata' => [
                            'old_start' => $oldStart?->toDateTimeString(),
                            'new_start' => $plannedStart->toDateTimeString(),
                            'old_finish' => $oldFinish?->toDateTimeString(),
                            'new_finish' => $plannedFinish->toDateTimeString(),
                            'old_machine_id' => $oldMachineId,
                            'new_machine_id' => $machineId,
                        ],
                    ]);
                }
            }

            // Update Capacity Utilization
            $schedule->update([
                'capacity_utilization' => $this->calculateOverallUtilization($schedule),
            ]);

            return $schedule;
        });
    }
}
