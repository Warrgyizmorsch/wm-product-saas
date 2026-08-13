<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionScheduleScenario;
use App\Domains\Production\Models\ProductionScheduleScenarioOperation;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionScheduleScenarioService
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly CapacityPlanningService $capacityPlanningService,
        private readonly SchedulePreReleaseValidationService $validationService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a new What-If Scenario and snapshot current live schedule operations.
     */
    public function createScenario(int $tenantId, array $data, int $userId): ProductionScheduleScenario
    {
        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $endDate = isset($data['end_date'])
            ? Carbon::parse($data['end_date'])->endOfDay()
            : Carbon::now()->addDays(14)->endOfDay();

        if ($startDate->diffInDays($endDate) > 62) {
            throw new InvalidArgumentException('Scenario scope cannot exceed 62 days.');
        }

        $scopeFilters = [
            'start_date'           => $startDate->format('Y-m-d'),
            'end_date'             => $endDate->format('Y-m-d'),
            'source_schedule_id'   => $data['source_schedule_id'] ?? null,
            'work_center_id'       => $data['work_center_id'] ?? null,
            'machine_id'           => $data['machine_id'] ?? null,
            'production_order_ids' => $data['production_order_ids'] ?? [],
        ];

        return DB::transaction(function () use ($tenantId, $data, $userId, $startDate, $endDate, $scopeFilters) {
            $scenario = ProductionScheduleScenario::create([
                'tenant_id'          => $tenantId,
                'name'               => $data['name'],
                'description'        => $data['description'] ?? null,
                'created_by'         => $userId,
                'source_type'        => $data['source_type'] ?? 'live_schedule',
                'source_schedule_id' => $data['source_schedule_id'] ?? null,
                'status'             => ProductionScheduleScenario::STATUS_DRAFT,
                'scenario_type'      => $data['scenario_type'] ?? ProductionScheduleScenario::TYPE_CUSTOM,
                'scope_filters'      => $scopeFilters,
                'assumptions'        => $data['assumptions'] ?? [],
            ]);

            // Query live operations matching scope
            $query = ProductionScheduleOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereBetween('planned_start', [$startDate, $endDate]);

            if (!empty($data['source_schedule_id'])) {
                $query->where('production_schedule_id', $data['source_schedule_id']);
            }
            if (!empty($data['work_center_id'])) {
                $query->where('work_center_id', $data['work_center_id']);
            }
            if (!empty($data['machine_id'])) {
                $query->where('machine_id', $data['machine_id']);
            }
            if (!empty($data['production_order_ids'])) {
                $query->whereIn('production_order_id', (array) $data['production_order_ids']);
            }

            $liveOps = $query->orderBy('sequence')->get();

            // Snapshot live operations into scenario operations
            foreach ($liveOps as $op) {
                ProductionScheduleScenarioOperation::create([
                    'tenant_id'                      => $tenantId,
                    'scenario_id'                    => $scenario->id,
                    'source_schedule_operation_id'   => $op->id,
                    'production_schedule_id'         => $op->production_schedule_id,
                    'production_order_id'            => $op->production_order_id,
                    'production_order_operation_id' => $op->production_order_operation_id,
                    'work_center_id'                 => $op->work_center_id,
                    'machine_id'                     => $op->machine_id,
                    'sequence'                       => $op->sequence,
                    'priority'                       => $op->priority ?? 3,
                    'planned_start'                  => $op->planned_start,
                    'planned_finish'                 => $op->planned_finish,
                    'planned_duration_minutes'       => $op->planned_duration_minutes,
                    'status'                         => $op->status,
                    'locked'                         => $op->locked,
                    'manual_override'                => $op->manual_override,
                    'source_version'                 => $op->version,
                ]);
            }

            return $scenario->load('scenarioOperations');
        });
    }

    /**
     * Recalculate Scenario schedule using temporary scenario assumptions.
     */
    public function recalculateScenario(int $tenantId, int $scenarioId): array
    {
        $scenario = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('id', $scenarioId)
            ->where('tenant_id', $tenantId)
            ->with(['scenarioOperations.order', 'scenarioOperations.workCenter', 'scenarioOperations.machine'])
            ->first();

        if (!$scenario) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} not found.");
        }

        if ($scenario->isPromoted() || $scenario->isDiscarded()) {
            throw new InvalidArgumentException("Cannot recalculate a {$scenario->status} scenario.");
        }

        $assumptions = $scenario->assumptions ?? [];
        $ops = $scenario->scenarioOperations->sortBy('sequence');

        // Apply Priority Overrides from assumptions
        $priorityOverrides = $assumptions['order_priorities'] ?? [];
        foreach ($ops as $op) {
            if (isset($priorityOverrides[$op->production_order_id])) {
                $op->priority = (int) $priorityOverrides[$op->production_order_id];
            }
        }

        // Parse temporary downtime assumptions: Array of ['machine_id' => int, 'start' => string, 'finish' => string]
        $tempDowntimes = $assumptions['temporary_downtimes'] ?? [];

        // Group operations by Production Order to recalculate routing dependencies
        $ordersMap = $ops->groupBy('production_order_id');

        foreach ($ordersMap as $orderId => $orderOps) {
            $sortedOps = $orderOps->sortBy('sequence');
            $lastFinish = null;

            foreach ($sortedOps as $op) {
                if ($op->locked) {
                    $lastFinish = $op->planned_finish->copy();
                    continue;
                }

                $wcId = $op->work_center_id;
                $duration = (float) $op->planned_duration_minutes;

                // Base start time is either predecessor finish or current planned_start
                $baseStart = $lastFinish ? $lastFinish->copy()->addMinutes(15) : $op->planned_start->copy();

                // Shift forward if temporary downtime or machine overlap occurs
                $currentMachineId = $op->machine_id;
                if ($currentMachineId) {
                    foreach ($tempDowntimes as $dt) {
                        if ((int) $dt['machine_id'] === (int) $currentMachineId) {
                            $dtStart  = Carbon::parse($dt['start']);
                            $dtFinish = Carbon::parse($dt['finish']);

                            if ($baseStart < $dtFinish && $baseStart->copy()->addMinutes($duration) > $dtStart) {
                                $baseStart = $this->schedulingService->addWorkingMinutes($wcId, $dtFinish, 15);
                            }
                        }
                    }
                }

                $newStart  = $baseStart;
                $newFinish = $this->schedulingService->addWorkingMinutes($wcId, $newStart, $duration);

                $op->planned_start  = $newStart;
                $op->planned_finish = $newFinish;
                $op->save();

                $lastFinish = $newFinish->copy();
            }
        }

        // Reload updated operations
        $ops = ProductionScheduleScenarioOperation::where('scenario_id', $scenario->id)->get();

        // Calculate KPI summary metrics
        $kpis = $this->calculateScenarioKPIs($tenantId, $scenario, $ops);

        $scenario->update([
            'summary' => $kpis,
            'status'  => ProductionScheduleScenario::STATUS_CALCULATED,
        ]);

        return [
            'scenario_id' => $scenario->id,
            'status'      => $scenario->status,
            'kpis'        => $kpis,
        ];
    }

    /**
     * Level Capacity within a What-If Scenario.
     */
    public function levelScenarioCapacity(int $tenantId, int $scenarioId): array
    {
        $scenario = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('id', $scenarioId)
            ->where('tenant_id', $tenantId)
            ->with(['scenarioOperations.orderOperation'])
            ->first();

        if (!$scenario) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} not found.");
        }

        if ($scenario->isPromoted() || $scenario->isDiscarded()) {
            throw new InvalidArgumentException("Cannot level capacity on a {$scenario->status} scenario.");
        }

        $ops = $scenario->scenarioOperations;
        $reassignedCount = 0;
        $shiftedCount = 0;

        foreach ($ops as $op) {
            if ($op->locked || in_array($op->status, ['completed', 'in_progress'], true)) {
                continue;
            }

            // Check machine collisions inside scenario
            $collisions = $ops->filter(function ($other) use ($op) {
                if ($other->id === $op->id || !$op->machine_id) return false;
                if ($other->machine_id === $op->machine_id) {
                    return ($other->planned_start < $op->planned_finish && $other->planned_finish > $op->planned_start);
                }
                return false;
            });

            if ($collisions->count() > 0 && $op->orderOperation) {
                $orderOp = $op->orderOperation;
                $altMachines = RoutingOperationAlternateMachine::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('routing_operation_id', $orderOp->routing_operation_id)
                    ->get();

                $altFound = false;
                foreach ($altMachines as $alt) {
                    $altMachineId = $alt->machine_id;
                    $altMachine = Machine::withoutGlobalScopes()
                        ->where('id', $altMachineId)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'active')
                        ->first();
                    if (!$altMachine) continue;

                    // Check if alt machine is free
                    $altCollision = $ops->filter(function ($other) use ($op, $altMachineId) {
                        if ($other->id === $op->id) return false;
                        if ($other->machine_id === $altMachineId) {
                            return ($other->planned_start < $op->planned_finish && $other->planned_finish > $op->planned_start);
                        }
                        return false;
                    })->count();

                    if ($altCollision === 0) {
                        $op->machine_id = $altMachineId;
                        $op->manual_override = true;
                        $op->save();
                        $altFound = true;
                        $reassignedCount++;
                        break;
                    }
                }

                if (!$altFound) {
                    // Shift forward on current machine
                    $maxFinish = $collisions->pluck('planned_finish')->max();
                    if ($maxFinish) {
                        $newStart  = $this->schedulingService->addWorkingMinutes($op->work_center_id, $maxFinish, 15);
                        $newFinish = $this->schedulingService->addWorkingMinutes($op->work_center_id, $newStart, (float) $op->planned_duration_minutes);

                        $op->planned_start  = $newStart;
                        $op->planned_finish = $newFinish;
                        $op->manual_override = true;
                        $op->save();
                        $shiftedCount++;
                    }
                }
            }
        }

        // Recalculate KPIs
        $this->recalculateScenario($tenantId, $scenarioId);

        return [
            'success'            => true,
            'machines_reassigned' => $reassignedCount,
            'operations_shifted' => $shiftedCount,
        ];
    }

    /**
     * Compare Live Production Schedule vs What-If Scenario.
     */
    public function compareWithLive(int $tenantId, int $scenarioId): array
    {
        $scenario = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('id', $scenarioId)
            ->where('tenant_id', $tenantId)
            ->with(['scenarioOperations.sourceOperation', 'scenarioOperations.order.product', 'scenarioOperations.workCenter', 'scenarioOperations.machine'])
            ->first();

        if (!$scenario) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} not found.");
        }

        $scenarioOps = $scenario->scenarioOperations;
        $sourceOpIds = $scenarioOps->pluck('source_schedule_operation_id')->toArray();

        $liveOps = ProductionScheduleOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $sourceOpIds)
            ->with(['order', 'machine', 'workCenter'])
            ->get()
            ->keyBy('id');

        $operationsDiff = [];
        $opsChangedCount = 0;
        $machinesReassignedCount = 0;

        foreach ($scenarioOps as $sOp) {
            $lOp = $liveOps->get($sOp->source_schedule_operation_id);
            if (!$lOp) continue;

            $startShiftMins  = (int) $lOp->planned_start->diffInMinutes($sOp->planned_start, false);
            $finishShiftMins = (int) $lOp->planned_finish->diffInMinutes($sOp->planned_finish, false);
            $machineChanged  = $lOp->machine_id !== $sOp->machine_id;

            if ($startShiftMins !== 0 || $finishShiftMins !== 0 || $machineChanged) {
                $opsChangedCount++;
                if ($machineChanged) {
                    $machinesReassignedCount++;
                }
            }

            $operationsDiff[] = [
                'scenario_operation_id'   => $sOp->id,
                'source_operation_id'     => $lOp->id,
                'production_order_number' => $sOp->order ? $sOp->order->order_number : 'PO-' . $sOp->production_order_id,
                'product_name'            => $sOp->order?->product?->name ?? 'Product',
                'sequence'                => $sOp->sequence,
                'live_machine'            => $lOp->machine ? $lOp->machine->name : 'Unassigned',
                'scenario_machine'        => $sOp->machine ? $sOp->machine->name : 'Unassigned',
                'live_start'              => $lOp->planned_start->toIso8601String(),
                'scenario_start'          => $sOp->planned_start->toIso8601String(),
                'live_finish'             => $lOp->planned_finish->toIso8601String(),
                'scenario_finish'         => $sOp->planned_finish->toIso8601String(),
                'start_shift_minutes'     => $startShiftMins,
                'finish_shift_minutes'    => $finishShiftMins,
                'machine_reassigned'      => $machineChanged,
            ];
        }

        // Live KPIs
        $liveMakespan = 0;
        if ($liveOps->count() > 0) {
            $earliest = $liveOps->min('planned_start');
            $latest   = $liveOps->max('planned_finish');
            if ($earliest && $latest) {
                $liveMakespan = (int) Carbon::parse($earliest)->diffInMinutes(Carbon::parse($latest));
            }
        }

        // Scenario KPIs
        $scenarioKPIs = $scenario->summary ?? $this->calculateScenarioKPIs($tenantId, $scenario, $scenarioOps);
        $scenarioMakespan = $scenarioKPIs['makespan_minutes'] ?? 0;

        return [
            'scenario_id' => $scenario->id,
            'name'        => $scenario->name,
            'status'      => $scenario->status,
            'kpis' => [
                'live' => [
                    'makespan_minutes' => $liveMakespan,
                    'late_orders'      => $this->countLiveLateOrders($liveOps),
                ],
                'scenario' => [
                    'makespan_minutes' => $scenarioMakespan,
                    'late_orders'      => $scenarioKPIs['late_orders'] ?? 0,
                    'overloads'        => $scenarioKPIs['overloads'] ?? 0,
                    'conflicts'        => $scenarioKPIs['conflicts'] ?? 0,
                ],
                'impact' => [
                    'operations_changed'   => $opsChangedCount,
                    'machines_reassigned' => $machinesReassignedCount,
                    'makespan_diff_mins'   => $scenarioMakespan - $liveMakespan,
                ],
            ],
            'operations_diff' => $operationsDiff,
        ];
    }

    /**
     * Promote What-If Scenario to Live Production Schedule.
     */
    public function promoteScenario(int $tenantId, int $scenarioId, int $userId): array
    {
        $scenario = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('id', $scenarioId)
            ->where('tenant_id', $tenantId)
            ->with(['scenarioOperations'])
            ->first();

        if (!$scenario) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} not found.");
        }

        if ($scenario->isPromoted()) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} is already promoted.");
        }

        if ($scenario->isDiscarded()) {
            throw new InvalidArgumentException("Cannot promote a discarded scenario.");
        }

        $scenarioOps = $scenario->scenarioOperations;
        $sourceOpIds = $scenarioOps->pluck('source_schedule_operation_id')->sort()->values()->toArray();

        return DB::transaction(function () use ($tenantId, $scenario, $scenarioOps, $sourceOpIds, $userId) {
            // Lock live operations for update
            $liveOps = ProductionScheduleOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $sourceOpIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 1. Concurrency & Stale Verification
            foreach ($scenarioOps as $sOp) {
                /** @var ProductionScheduleOperation|null $lOp */
                $lOp = $liveOps->get($sOp->source_schedule_operation_id);

                if (!$lOp) {
                    throw new InvalidArgumentException("Live schedule operation #{$sOp->source_schedule_operation_id} no longer exists.");
                }

                // Check version mismatch
                if ($lOp->version !== $sOp->source_version) {
                    throw new InvalidArgumentException("SCENARIO_STALE: Live schedule operation #{$lOp->sequence} was modified (Version {$lOp->version}) after scenario snapshot (Version {$sOp->source_version}). Please rebase scenario.");
                }

                // Check locked live operations
                if ($lOp->locked && ($lOp->planned_start != $sOp->planned_start || $lOp->machine_id != $sOp->machine_id)) {
                    throw new InvalidArgumentException("PROMOTION_BLOCKED: Live operation sequence #{$lOp->sequence} is locked and cannot be moved by scenario promotion.");
                }

                // Check execution protection
                if (in_array($lOp->status, ['in_progress', 'completed', 'cancelled'], true)) {
                    throw new InvalidArgumentException("PROMOTION_BLOCKED: Live operation sequence #{$lOp->sequence} status is {$lOp->status}.");
                }
            }

            // 2. Pre-Release Constraint Validation on Scenario Timings
            foreach ($scenarioOps as $sOp) {
                if ($sOp->machine_id) {
                    $downtimeConflict = ProductionMachineDowntime::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('machine_id', $sOp->machine_id)
                        ->whereIn('status', ['scheduled', 'in_progress'])
                        ->where(function ($q) use ($sOp) {
                            $q->whereBetween('start_time', [$sOp->planned_start, $sOp->planned_finish])
                              ->orWhereBetween('end_time', [$sOp->planned_start, $sOp->planned_finish]);
                        })->exists();

                    if ($downtimeConflict) {
                        throw new InvalidArgumentException("PROMOTION_BLOCKED: Machine downtime collision detected for sequence #{$sOp->sequence}.");
                    }
                }
            }

            // 3. Update Live Operations & Write Audit Logs
            $changedCount = 0;
            $machinesReassigned = 0;

            foreach ($scenarioOps as $sOp) {
                /** @var ProductionScheduleOperation $lOp */
                $lOp = $liveOps->get($sOp->source_schedule_operation_id);

                $oldStart   = $lOp->planned_start ? $lOp->planned_start->toIso8601String() : null;
                $oldFinish  = $lOp->planned_finish ? $lOp->planned_finish->toIso8601String() : null;
                $oldMachine = $lOp->machine_id;

                $newStart   = $sOp->planned_start;
                $newFinish  = $sOp->planned_finish;
                $newMachine = $sOp->machine_id;

                $isTimingChanged = ($oldStart !== $newStart->toIso8601String()) || ($oldFinish !== $newFinish->toIso8601String());
                $isMachineChanged = ($oldMachine !== $newMachine);

                if ($isTimingChanged || $isMachineChanged) {
                    $changedCount++;
                    if ($isMachineChanged) {
                        $machinesReassigned++;
                    }

                    // Update Live Operation (Preserve Baseline Start/Finish!)
                    $lOp->planned_start   = $newStart;
                    $lOp->planned_finish  = $newFinish;
                    $lOp->machine_id      = $newMachine;
                    $lOp->manual_override = true;
                    $lOp->version         = $lOp->version + 1;
                    $lOp->save();

                    // Log Audit Entry
                    ProductionScheduleChangeLog::create([
                        'tenant_id'                        => $tenantId,
                        'production_schedule_id'           => $lOp->production_schedule_id,
                        'production_schedule_operation_id' => $lOp->id,
                        'changed_by'                       => $userId,
                        'change_type'                      => 'scenario_promotion',
                        'shift_mode'                       => 'ripple',
                        'old_machine_id'                   => $oldMachine,
                        'new_machine_id'                   => $newMachine,
                        'old_planned_start'                => $oldStart,
                        'new_planned_start'                => $newStart->toIso8601String(),
                        'old_planned_finish'               => $oldFinish,
                        'new_planned_finish'               => $newFinish->toIso8601String(),
                        'reason'                           => "Promoted What-If Scenario: [{$scenario->name}]",
                    ]);
                }
            }

            // 4. Mark Scenario Promoted
            $scenario->update([
                'status'      => ProductionScheduleScenario::STATUS_PROMOTED,
                'promoted_at' => Carbon::now(),
                'promoted_by' => $userId,
            ]);

            // 5. Emit High-Level Timeline Event
            $this->eventService->writeEvent($tenantId, [
                'event_type'  => 'WHAT_IF_SCENARIO_PROMOTED',
                'title'       => "Scenario Promoted: {$scenario->name}",
                'description' => "What-If Scenario [{$scenario->name}] promoted successfully. {$changedCount} operations updated, {$machinesReassigned} machines reassigned.",
                'metadata'    => [
                    'scenario_id'        => $scenario->id,
                    'operations_changed' => $changedCount,
                    'promoted_by'        => $userId,
                ],
            ]);

            return [
                'success'             => true,
                'message'             => "What-If Scenario [{$scenario->name}] promoted to live production schedule successfully.",
                'operations_changed'  => $changedCount,
                'machines_reassigned' => $machinesReassigned,
                'scenario_id'         => $scenario->id,
            ];
        });
    }

    /**
     * Discard a What-If Scenario.
     */
    public function discardScenario(int $tenantId, int $scenarioId): array
    {
        $scenario = ProductionScheduleScenario::withoutGlobalScopes()
            ->where('id', $scenarioId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$scenario) {
            throw new InvalidArgumentException("Scenario #{$scenarioId} not found.");
        }

        if ($scenario->isPromoted()) {
            throw new InvalidArgumentException("Cannot discard a promoted scenario.");
        }

        $scenario->update(['status' => ProductionScheduleScenario::STATUS_DISCARDED]);

        return [
            'success' => true,
            'message' => "What-If Scenario #{$scenarioId} marked discarded.",
        ];
    }

    private function calculateScenarioKPIs(int $tenantId, ProductionScheduleScenario $scenario, $ops): array
    {
        $makespanMinutes = 0;
        if ($ops->count() > 0) {
            $earliest = $ops->min('planned_start');
            $latest   = $ops->max('planned_finish');
            if ($earliest && $latest) {
                $makespanMinutes = (int) Carbon::parse($earliest)->diffInMinutes(Carbon::parse($latest));
            }
        }

        $dueOverrides = $scenario->assumptions['due_date_overrides'] ?? [];
        $lateOrdersCount = 0;
        $totalLateMinutes = 0;

        $groupedByOrder = $ops->groupBy('production_order_id');
        foreach ($groupedByOrder as $orderId => $orderOps) {
            $lastFinish = $orderOps->max('planned_finish');
            if (!$lastFinish) continue;

            $dueDate = isset($dueOverrides[$orderId])
                ? Carbon::parse($dueOverrides[$orderId])
                : $orderOps->first()->order?->due_date;

            if ($dueDate && Carbon::parse($lastFinish)->gt($dueDate)) {
                $lateOrdersCount++;
                $totalLateMinutes += (int) $dueDate->diffInMinutes(Carbon::parse($lastFinish));
            }
        }

        // Machine Conflicts inside scenario
        $conflictsCount = 0;
        foreach ($ops as $op) {
            if (!$op->machine_id) continue;
            $overlap = $ops->filter(function ($other) use ($op) {
                if ($other->id === $op->id) return false;
                return $other->machine_id === $op->machine_id && ($other->planned_start < $op->planned_finish && $other->planned_finish > $op->planned_start);
            })->count();
            if ($overlap > 0) {
                $conflictsCount++;
            }
        }

        return [
            'makespan_minutes'   => $makespanMinutes,
            'late_orders'        => $lateOrdersCount,
            'total_late_minutes' => $totalLateMinutes,
            'conflicts'          => $conflictsCount,
            'overloads'          => max(0, $conflictsCount),
            'operations_count'   => $ops->count(),
        ];
    }

    private function countLiveLateOrders($liveOps): int
    {
        $late = 0;
        $grouped = $liveOps->groupBy('production_order_id');
        foreach ($grouped as $orderOps) {
            $lastFinish = $orderOps->max('planned_finish');
            $dueDate = $orderOps->first()->order?->due_date;
            if ($lastFinish && $dueDate && Carbon::parse($lastFinish)->gt($dueDate)) {
                $late++;
            }
        }
        return $late;
    }
}
