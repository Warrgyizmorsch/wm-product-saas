<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use Illuminate\Support\Carbon;

class SchedulingCalendarService
{
    public function __construct(
        private readonly SchedulingService $schedulingService
    ) {}

    /**
     * Build the data required for calendar views.
     */
    public function buildCalendarData(int $tenantId, array $filters): array
    {
        $view = $filters['view'] ?? 'week';
        $layout = $filters['layout'] ?? 'gantt';

        $startDate = isset($filters['start'])
            ? Carbon::parse($filters['start'])->startOfDay()
            : now()->startOfWeek();

        $endDate = match ($view) {
            'day'   => $startDate->copy()->endOfDay(),
            'month' => $startDate->copy()->endOfMonth(),
            default => $startDate->copy()->endOfWeek(),
        };

        // 1. Date overlap bounds query (clipping edges)
        $opsQuery = ProductionScheduleOperation::with([
            'schedule',
            'order.product',
            'orderOperation.routingOperation',
            'orderOperation.operatorAssignments.user',
            'workCenter.activeMachines',
            'machine',
        ])
        ->where('tenant_id', $tenantId)
        ->where('planned_start', '<', $endDate)
        ->where('planned_finish', '>', $startDate);

        // Filters application
        if (isset($filters['status'])) {
            $opsQuery->whereHas('schedule', fn($q) => $q->where('status', $filters['status']));
        } else {
            // Default active schedule statuses
            $opsQuery->whereHas('schedule', fn($q) => $q->whereIn('status', [
                ProductionSchedule::STATUS_SCHEDULED,
                ProductionSchedule::STATUS_RELEASED,
                ProductionSchedule::STATUS_IN_PROGRESS
            ]));
        }

        if (isset($filters['operation_status'])) {
            $opsQuery->where('status', $filters['operation_status']);
        }

        if (isset($filters['work_center_id'])) {
            $opsQuery->where('work_center_id', $filters['work_center_id']);
        }

        if (isset($filters['machine_id'])) {
            $opsQuery->where('machine_id', $filters['machine_id']);
        }

        if (isset($filters['production_order_id'])) {
            $opsQuery->where('production_order_id', $filters['production_order_id']);
        }

        $operations = $opsQuery->orderBy('planned_start')->orderBy('id')->get();

        // 2. Batch-Load conflict validation helpers to prevent N+1 queries
        $downtimes = ProductionMachineDowntime::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('start_time', '<', $endDate)
            ->where(fn($q) => $q->whereNull('end_time')->orWhere('end_time', '>', $startDate))
            ->get()
            ->groupBy('machine_id');

        $holidays = ProductionCalendarHoliday::where('tenant_id', $tenantId)
            ->whereBetween('holiday_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get()
            ->groupBy('production_calendar_id');

        $operatorSkills = ProductionOperatorSkill::where('tenant_id', $tenantId)
            ->where('active', true)
            ->get()
            ->groupBy('user_id');

        // All order ops for sequence/dependency violations
        $orderIds = $operations->pluck('production_order_id')->unique()->toArray();
        $allOrderOps = ProductionScheduleOperation::whereIn('production_order_id', $orderIds)
            ->orderBy('sequence')
            ->get()
            ->groupBy('production_order_id');

        // 3. Resolve Work Centers and Machines
        $wcQuery = WorkCenter::where('tenant_id', $tenantId)->where('status', 'active');
        if (isset($filters['work_center_id'])) {
            $wcQuery->where('id', $filters['work_center_id']);
        }
        $workCenters = $wcQuery->with(['machines'])->get();

        // Calculate columns for UI rendering
        $columns = [];
        if ($view === 'day') {
            for ($i = 0; $i < 24; $i++) {
                $columns[] = [
                    'label' => sprintf('%02d:00', $i),
                    'start' => $startDate->copy()->startOfDay()->addHours($i),
                    'end' => $startDate->copy()->startOfDay()->addHours($i + 1),
                ];
            }
        } elseif ($view === 'month') {
            $daysInMonth = $startDate->daysInMonth;
            for ($i = 0; $i < $daysInMonth; $i++) {
                $day = $startDate->copy()->startOfMonth()->addDays($i);
                $columns[] = [
                    'label' => $day->format('d'),
                    'sublabel' => $day->format('D'),
                    'start' => $day->copy()->startOfDay(),
                    'end' => $day->copy()->endOfDay(),
                ];
            }
        } else {
            for ($i = 0; $i < 7; $i++) {
                $day = $startDate->copy()->startOfWeek()->addDays($i);
                $columns[] = [
                    'label' => $day->format('D'),
                    'sublabel' => $day->format('d M'),
                    'start' => $day->copy()->startOfDay(),
                    'end' => $day->copy()->endOfDay(),
                ];
            }
        }

        $timelineStart = $columns[0]['start'];
        $timelineEnd = end($columns)['end'];
        $totalSeconds = max(1, $timelineStart->diffInSeconds($timelineEnd));

        // 4. Calculate daily capacities and overloaded days to avoid loop database hits
        $capacityGrid = []; // [wcId][dateStr] = structured capacity
        foreach ($workCenters as $wc) {
            $date = $startDate->copy()->startOfDay();
            while ($date->lte($endDate)) {
                $dateStr = $date->toDateString();
                $availMinutes = $this->schedulingService->calculateCapacity($wc->id, $date);

                // Filter operations for this work center on this day in memory
                $dayOps = $operations->filter(function($op) use ($wc, $date) {
                    if (!$op->planned_start || !$op->planned_finish) return false;
                    if ($op->work_center_id !== $wc->id) return false;
                    $overlapStart = $op->planned_start->max($date->copy()->startOfDay());
                    $overlapFinish = $op->planned_finish->min($date->copy()->endOfDay());
                    return $overlapStart->lt($overlapFinish);
                });

                $activeMachineCount = $wc->machines->where('status', Machine::STATUS_ACTIVE)->count() ?: 1;
                $perMachineShiftCap = $availMinutes > 0 ? ($availMinutes / $activeMachineCount) : 450.0;

                $allocatedMinutes = $dayOps->sum(function($op) use ($date, $perMachineShiftCap) {
                    $overlapStart = $op->planned_start->max($date->copy()->startOfDay());
                    $overlapFinish = $op->planned_finish->min($date->copy()->endOfDay());
                    $rawMinutes = max(0, $overlapStart->diffInMinutes($overlapFinish));
                    return min($perMachineShiftCap, $rawMinutes);
                });

                $isOverCapacity = $allocatedMinutes > $availMinutes;
                $capacityGrid[$wc->id][$dateStr] = [
                    'available_minutes' => $availMinutes,
                    'allocated_minutes' => $allocatedMinutes,
                    'remaining_minutes' => max(0, $availMinutes - $allocatedMinutes),
                    'utilization_percent' => $availMinutes > 0 ? ($allocatedMinutes / $availMinutes) * 100 : ($allocatedMinutes > 0 ? 100.0 : 0.0),
                    'is_over_capacity' => $isOverCapacity,
                ];

                $date->addDay();
            }
        }

        // 5. Build hierarchy of Work Centers, Machines, Visual Lanes, and Operations
        $wcData = [];
        $unassignedOps = $operations->whereNull('machine_id');

        foreach ($workCenters as $wc) {
            $wcOps = $operations->where('work_center_id', $wc->id);

            // Determine classification
            $classification = 'fallback';
            $hasExternalOps = $wcOps->contains(fn($op) => (bool)($op->orderOperation?->routingOperation?->is_external ?? false));

            if ($hasExternalOps) {
                $classification = 'external';
            } elseif ($wc->type === 'machine_group' || $wc->machines->isNotEmpty()) {
                $hasUnassigned = $wcOps->contains(fn($op) => is_null($op->machine_id));
                $classification = $hasUnassigned ? 'machine_optional' : 'machine_based';
            } elseif ($wc->machines->isEmpty()) {
                $classification = 'manual';
            }

            // Inactive Machines Filter / Visibility
            // Keep inactive machines only if they have operations scheduled in the date range.
            $wcMachines = $wc->machines->filter(function($machine) use ($wcOps) {
                if ($machine->isActive()) return true;
                return $wcOps->where('machine_id', $machine->id)->isNotEmpty();
            });

            $machineRows = [];

            // Add active and eligible inactive machine lanes
            foreach ($wcMachines as $machine) {
                $machineOps = $wcOps->where('machine_id', $machine->id);
                $machineRows[] = [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'code' => $machine->code,
                    'is_active' => $machine->isActive(),
                    'badge' => $machine->isActive() ? '' : 'Inactive',
                    'lanes' => $this->packOperationsIntoLanes($machineOps, $timelineStart, $timelineEnd, $totalSeconds, $holidays, $downtimes, $operatorSkills, $allOrderOps, $capacityGrid[$wc->id] ?? []),
                ];
            }

            // Fallback general lanes for manual, external, machine_optional, or fallback configurations
            if ($classification === 'manual' || $classification === 'fallback') {
                $machineRows[] = [
                    'id' => null,
                    'name' => $classification === 'manual' ? 'Manual Operations' : 'Work Centre Fallback',
                    'code' => '',
                    'is_active' => true,
                    'badge' => $classification === 'fallback' ? 'Config Warning' : '',
                    'lanes' => $this->packOperationsIntoLanes($wcOps, $timelineStart, $timelineEnd, $totalSeconds, $holidays, $downtimes, $operatorSkills, $allOrderOps, $capacityGrid[$wc->id] ?? []),
                ];
            } elseif ($classification === 'external') {
                $machineRows[] = [
                    'id' => null,
                    'name' => 'External Processing',
                    'code' => 'SUB',
                    'is_active' => true,
                    'badge' => '',
                    'lanes' => $this->packOperationsIntoLanes($wcOps, $timelineStart, $timelineEnd, $totalSeconds, $holidays, $downtimes, $operatorSkills, $allOrderOps, $capacityGrid[$wc->id] ?? []),
                ];
            } elseif ($classification === 'machine_optional') {
                // Renders machines lanes + an unassigned fallback lane
                $fallbackOps = $wcOps->whereNull('machine_id');
                if ($fallbackOps->isNotEmpty()) {
                    $machineRows[] = [
                        'id' => null,
                        'name' => 'General / Manual lane',
                        'code' => 'WC',
                        'is_active' => true,
                        'badge' => '',
                        'lanes' => $this->packOperationsIntoLanes($fallbackOps, $timelineStart, $timelineEnd, $totalSeconds, $holidays, $downtimes, $operatorSkills, $allOrderOps, $capacityGrid[$wc->id] ?? []),
                    ];
                }
            }

            // Flag Work Center warning counts
            $conflictsCount = 0;
            foreach ($machineRows as $mr) {
                foreach ($mr['lanes'] as $lane) {
                    foreach ($lane['operations'] as $opPayload) {
                        if ($opPayload['conflict_data']['has_conflict']) {
                            $conflictsCount += $opPayload['conflict_data']['conflict_count'];
                        }
                    }
                }
            }

            $wcData[] = [
                'id' => $wc->id,
                'name' => $wc->name,
                'code' => $wc->code,
                'classification' => $classification,
                'machines' => $machineRows,
                'conflicts_count' => $conflictsCount,
            ];
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'view' => $view,
            'layout' => $layout,
            'columns' => $columns,
            'totalSeconds' => $totalSeconds,
            'workCentersData' => $wcData,
            'operations' => $operations,
        ];
    }

    /**
     * Greedy interval partitioning lane packing + rendering percents + conflict aggregation.
     */
    private function packOperationsIntoLanes($ops, $timelineStart, $timelineEnd, $totalSeconds, $holidays, $downtimes, $operatorSkills, $allOrderOps, $wcCapacity): array
    {
        if ($ops->isEmpty()) return [];

        // Sort by planned_start ascending, then by id ascending
        $sortedOps = $ops->sortBy(function($op) {
            return $op->planned_start ? $op->planned_start->timestamp : 0;
        })->values();

        $lanes = [];

        foreach ($sortedOps as $op) {
            // 1. Position Percent Calculations
            $leftPercent = 0.0;
            $widthPercent = 0.0;

            if ($op->planned_start && $op->planned_finish) {
                $opStart = $op->planned_start;
                $opFinish = $op->planned_finish;

                // Clamp boundaries
                if ($opStart->lt($timelineStart)) $opStart = $timelineStart;
                if ($opFinish->gt($timelineEnd)) $opFinish = $timelineEnd;

                if ($opStart->lt($opFinish)) {
                    $leftPercent = ($timelineStart->diffInSeconds($opStart) / $totalSeconds) * 100;
                    $widthPercent = ($opStart->diffInSeconds($opFinish) / $totalSeconds) * 100;
                    $widthPercent = max(2.5, $widthPercent); // Minimum visual width
                }
            }

            // 2. Conflict Aggregation
            $conflictPayload = $this->evaluateConflicts($op, $holidays, $downtimes, $operatorSkills, $allOrderOps, $wcCapacity);

            // 3. Lane Packing Algorithm
            $assignedLaneIndex = -1;
            for ($i = 0; $i < count($lanes); $i++) {
                // If this operation starts after the last operation in lane $i ends:
                $lastOpInLane = end($lanes[$i]);
                if ($op->planned_start && $lastOpInLane->planned_finish && $op->planned_start->gte($lastOpInLane->planned_finish)) {
                    $assignedLaneIndex = $i;
                    break;
                }
            }

            if ($assignedLaneIndex === -1) {
                // Create a new lane
                $lanes[] = [$op];
                $assignedLaneIndex = count($lanes) - 1;
            } else {
                $lanes[$assignedLaneIndex][] = $op;
            }

            // Save layout variables on the model/payload
            $op->visual_lane = $assignedLaneIndex;
            $op->left_percent = $leftPercent;
            $op->width_percent = $widthPercent;
            $op->conflict_data = $conflictPayload;
        }

        // Convert lanes to view array structure
        $lanesArray = [];
        foreach ($lanes as $index => $laneOps) {
            $opsPayload = [];
            foreach ($laneOps as $op) {
                $opsPayload[] = [
                    'id' => $op->id,
                    'schedule_number' => $op->schedule->schedule_number ?? '',
                    'order_number' => $op->order->order_number ?? '',
                    'product_name' => $op->order->product->name ?? '',
                    'name' => $op->orderOperation->name ?? 'OP',
                    'operation_number' => $op->orderOperation->operation_number ?? '',
                    'status' => $op->status,
                    'planned_start' => $op->planned_start,
                    'planned_finish' => $op->planned_finish,
                    'left_percent' => $op->left_percent,
                    'width_percent' => $op->width_percent,
                    'visual_lane' => $op->visual_lane,
                    'conflict_data' => $op->conflict_data,
                ];
            }
            $lanesArray[] = [
                'lane_index' => $index,
                'operations' => $opsPayload,
            ];
        }

        return $lanesArray;
    }

    /**
     * Compute multi-conflicts array for a single operation.
     */
    private function evaluateConflicts($op, $holidays, $downtimes, $operatorSkills, $allOrderOps, $wcCapacity): array
    {
        $conflicts = [];
        $highestSeverity = 'warning';

        // 1. Missing dates / Invalid range handling
        if (is_null($op->planned_start) || is_null($op->planned_finish)) {
            $conflicts[] = [
                'type' => 'missing_schedule_time',
                'message' => 'Missing planned start or finish time.',
                'severity' => 'danger',
                'related_ids' => [],
            ];
            $highestSeverity = 'danger';
            return [
                'has_conflict' => true,
                'highest_severity' => $highestSeverity,
                'conflict_count' => count($conflicts),
                'conflicts' => $conflicts,
            ];
        }

        if ($op->planned_finish->lt($op->planned_start)) {
            $conflicts[] = [
                'type' => 'invalid_time_range',
                'message' => 'Planned finish is earlier than planned start.',
                'severity' => 'danger',
                'related_ids' => [],
            ];
            $highestSeverity = 'danger';
        }

        if ($op->planned_start->equalTo($op->planned_finish)) {
            $conflicts[] = [
                'type' => 'zero_duration',
                'message' => 'Operation has zero duration.',
                'severity' => 'warning',
                'related_ids' => [],
            ];
        }

        // 2. Machine Downtime Overlap Check
        if ($op->machine_id) {
            $machineDowntimes = $downtimes->get($op->machine_id, collect());
            foreach ($machineDowntimes as $dt) {
                // Check time overlap between downtime interval and operation interval
                $dtEnd = $dt->end_time ?? Carbon::now()->addYears(10);
                if ($op->planned_start->lt($dtEnd) && $op->planned_finish->gt($dt->start_time)) {
                    $conflicts[] = [
                        'type' => 'machine_downtime',
                        'message' => "Machine [{$op->machine->name}] has active downtime: {$dt->reason}.",
                        'severity' => 'danger',
                        'related_ids' => [],
                    ];
                    $highestSeverity = 'danger';
                }
            }
        }

        // 3. Holiday / Calendar Conflicts
        $wcCalendarId = $op->workCenter->production_calendar_id;
        if ($wcCalendarId) {
            $calHolidays = $holidays->get($wcCalendarId, collect());
            foreach ($calHolidays as $h) {
                $hDate = Carbon::parse($h->holiday_date);
                if ($op->planned_start->toDateString() === $hDate->toDateString() || $op->planned_finish->toDateString() === $hDate->toDateString()) {
                    $conflicts[] = [
                        'type' => 'holiday_conflict',
                        'message' => "Scheduled on holiday: [{$h->name}].",
                        'severity' => 'warning',
                        'related_ids' => [],
                    ];
                }
            }
        }

        // 4. Operator Skills Qualification Check
        $activeAssignment = $op->orderOperation->operatorAssignments
            ->whereIn('status', [ProductionOperatorAssignment::STATUS_ASSIGNED, ProductionOperatorAssignment::STATUS_ACCEPTED])
            ->first();

        if ($activeAssignment) {
            $operatorId = $activeAssignment->user_id;
            $user = $activeAssignment->user ?? \App\Models\User::find($operatorId);

            if ($user && (in_array(strtolower($user->role ?? ''), ['super_admin', 'superadmin', 'admin', 'administrator']) || $user->email === 'admin@example.com')) {
                // Admin / Super Admin is exempt from skill restrictions
            } else {
                $skills = $operatorSkills->get($operatorId, collect());

                if ($skills->isEmpty()) {
                    $conflicts[] = [
                        'type' => 'skill_missing',
                        'message' => "Assigned operator [{$user->name}] lacks qualified skills.",
                        'severity' => 'danger',
                        'related_ids' => [],
                    ];
                    $highestSeverity = 'danger';
                } else {
                    $qualified = false;
                    $unrestrictedKeywords = ['UNRESTRICTED', 'ALL', 'GENERAL', 'SKL-ALL', 'SKILL-ALL', 'MASTER', 'ADMIN', 'FULL', 'GLOBAL', 'ANY', 'SUPER', ''];

                    foreach ($skills as $skill) {
                        $codeUpper = strtoupper(trim($skill->skill_code ?? ''));

                        if (
                            in_array($codeUpper, $unrestrictedKeywords) ||
                            str_contains($codeUpper, 'UNRESTRICTED') ||
                            str_contains($codeUpper, 'GENERAL') ||
                            str_contains($codeUpper, 'MASTER') ||
                            str_contains($codeUpper, 'ADMIN') ||
                            str_contains($codeUpper, 'FULL') ||
                            str_contains($codeUpper, 'GLOBAL') ||
                            str_contains($codeUpper, 'ALL')
                        ) {
                            $qualified = true;
                            break;
                        }

                        if ($skill->machine_id !== null && $op->machine_id !== null && $skill->machine_id === $op->machine_id) {
                            $qualified = true;
                            break;
                        }

                        if ($skill->work_center_id !== null && $skill->work_center_id === $op->work_center_id) {
                            $qualified = true;
                            break;
                        }

                        if (!empty($skill->skill_code)) {
                            $code = strtolower($skill->skill_code);
                            $opName = strtolower($op->orderOperation->name ?? '');
                            $opType = strtolower($op->orderOperation->operation_type ?? '');
                            if (str_contains($opName, $code) || str_contains($code, $opName) || ($opType && str_contains($opType, $code))) {
                                $qualified = true;
                                break;
                            }
                        }
                    }
                    if (!$qualified) {
                        $conflicts[] = [
                            'type' => 'skill_missing',
                            'message' => "Operator lacks required skills/qualification for [{$op->orderOperation->name}].",
                            'severity' => 'danger',
                            'related_ids' => [],
                        ];
                        $highestSeverity = 'danger';
                    }
                }
            }
        }

        // 5. Dependency / Sequence Violation
        $orderOps = $allOrderOps->get($op->production_order_id, collect());
        foreach ($orderOps as $otherOp) {
            if ($otherOp->sequence < $op->sequence) {
                $predOrderOp = $otherOp->orderOperation;
                if ($predOrderOp && (bool) $predOrderOp->overlap_enabled) {
                    $setupMinutes = (float) ($predOrderOp->setup_time_planned ?? 0);
                    $batchQty = (float) ($predOrderOp->transfer_batch_quantity ?? 0);
                    $lagMinutes = (float) ($predOrderOp->transfer_lag_minutes ?? 0);
                    $orderQty = (float) ($op->order?->quantity_ordered ?? 1);
                    $effectiveBatchQty = min($batchQty, $orderQty);
                    $firstBatchRunDuration = (float) $predOrderOp->processing_time_planned * $effectiveBatchQty;
                    $totalOffsetMinutes = $setupMinutes + $firstBatchRunDuration + $lagMinutes;

                    $earliestAllowedStart = $otherOp->planned_start
                        ? app(SchedulingService::class)->addWorkingMinutes(
                            $otherOp->work_center_id,
                            $otherOp->planned_start,
                            $totalOffsetMinutes
                        )
                        : null;

                    if ($earliestAllowedStart && $op->planned_start && $op->planned_start->lt($earliestAllowedStart)) {
                        $conflicts[] = [
                            'type' => 'dependency_violation',
                            'message' => "Sequence conflict: starts before predecessor (Seq #{$otherOp->sequence}) transfer-ready time.",
                            'severity' => 'warning',
                            'related_ids' => [$otherOp->id],
                        ];
                    }
                } else {
                    // Prior sequence must complete before this start
                    if ($otherOp->planned_finish && $op->planned_start && $otherOp->planned_finish->gt($op->planned_start)) {
                        $conflicts[] = [
                            'type' => 'dependency_violation',
                            'message' => "Sequence conflict: starts before predecessor (Seq #{$otherOp->sequence}) finishes.",
                            'severity' => 'warning',
                            'related_ids' => [$otherOp->id],
                        ];
                    }
                }
            }
        }

        // 6. Capacity Overload Violation
        $opStartStr = $op->planned_start->toDateString();
        $dayCapacity = $wcCapacity[$opStartStr] ?? null;
        if ($dayCapacity && $dayCapacity['is_over_capacity']) {
            $conflicts[] = [
                'type' => 'capacity_exceeded',
                'message' => "Work Center capacity exceeded on date [{$opStartStr}].",
                'severity' => 'warning',
                'related_ids' => [],
            ];
        }

        return [
            'has_conflict' => count($conflicts) > 0,
            'highest_severity' => $highestSeverity,
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Helper method to retrieve conflicts for a specific scheduled operation.
     */
    public function getOperationConflicts(int $schedOpId): array
    {
        $op = ProductionScheduleOperation::with(['orderOperation', 'workCenter', 'machine', 'order'])->find($schedOpId);
        if (!$op) {
            return ['has_conflict' => false, 'conflicts' => []];
        }

        $allOrderOps = collect([
            $op->production_order_id => ProductionScheduleOperation::where('production_order_id', $op->production_order_id)
                ->with(['orderOperation', 'order'])
                ->get()
        ]);

        return $this->evaluateConflicts($op, collect(), collect(), collect(), $allOrderOps, []);
    }
}
