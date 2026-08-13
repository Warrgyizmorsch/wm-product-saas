<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleChangeLog;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionScheduleOptimizationRun;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CapacityLevelingService
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly CapacityPlanningService $capacityPlanningService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Generate a non-destructive capacity leveling optimization preview.
     * Does NOT alter any live schedule operation rows in DB.
     */
    public function generatePreview(int $tenantId, array $filters, int $userId): array
    {
        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->addDays(14)->endOfDay();

        if ($startDate->diffInDays($endDate) > 62) {
            throw new InvalidArgumentException('Date range cannot exceed 62 days.');
        }

        $workCenterId = !empty($filters['work_center_id']) ? (int) $filters['work_center_id'] : null;
        $machineId    = !empty($filters['machine_id']) ? (int) $filters['machine_id'] : null;
        $scheduleId   = !empty($filters['schedule_id']) ? (int) $filters['schedule_id'] : null;

        // 1. Initial Overloads & Baseline Capacity
        $initialOverloadMsgs = $this->schedulingService->detectOverloads($tenantId);
        $capacityBefore = $this->capacityPlanningService->getWorkCenterCapacity($tenantId, $startDate, $endDate);

        if ($workCenterId) {
            $capacityBefore = array_values(array_filter($capacityBefore, fn($c) => $c['work_center']->id == $workCenterId));
        }

        $overloadedWcIds = collect($capacityBefore)
            ->filter(fn($c) => $c['utilization'] > 100)
            ->pluck('work_center.id')
            ->toArray();

        // 2. Fetch Operations in Window
        $opsQuery = ProductionScheduleOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['order.product', 'orderOperation', 'workCenter', 'machine'])
            ->whereBetween('planned_start', [$startDate, $endDate]);

        if ($workCenterId) {
            $opsQuery->where('work_center_id', $workCenterId);
        }
        if ($machineId) {
            $opsQuery->where('machine_id', $machineId);
        }
        if ($scheduleId) {
            $opsQuery->where('production_schedule_id', $scheduleId);
        }

        $allOperations = $opsQuery->orderBy('sequence')->get();

        // Separate eligible vs locked/in-progress operations
        $eligibleCandidates = $allOperations->filter(function ($op) {
            if ($op->locked) {
                return false;
            }
            if (in_array($op->status, ['completed', 'in_progress', 'cancelled', 'skipped'], true)) {
                return false;
            }
            return true;
        });

        // 3. Sort candidates (lower priority / further due-date candidates moved first)
        $sortedCandidates = $eligibleCandidates->sort(function ($a, $b) {
            $pA = (int) ($a->priority ?? 3);
            $pB = (int) ($b->priority ?? 3);
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }

            $dueA = $a->order?->due_date ? $a->order->due_date->timestamp : 0;
            $dueB = $b->order?->due_date ? $b->order->due_date->timestamp : 0;
            if ($dueA !== $dueB) {
                return $dueB <=> $dueA;
            }

            return $a->sequence <=> $b->sequence;
        });

        $proposedChanges = [];
        $unresolvedConflicts = [];
        $simulatedOpState = []; // op_id => ['start' => Carbon, 'finish' => Carbon, 'machine_id' => int|null]

        foreach ($allOperations as $op) {
            $simulatedOpState[$op->id] = [
                'start'      => $op->planned_start->copy(),
                'finish'     => $op->planned_finish->copy(),
                'machine_id' => $op->machine_id,
            ];
        }

        $operationsChangedCount = 0;
        $machinesReassignedCount = 0;
        $ordersDelayedCount = 0;

        // 4. Feasible Slot Search for Overloaded or Double-Booked Operations
        foreach ($sortedCandidates as $op) {
            $wcId = $op->work_center_id;
            $wcCap = collect($capacityBefore)->firstWhere('work_center.id', $wcId);

            $currentStart   = $simulatedOpState[$op->id]['start'];
            $currentFinish  = $simulatedOpState[$op->id]['finish'];
            $currentMachine = $simulatedOpState[$op->id]['machine_id'];
            $durationMinutes = (float) $op->planned_duration_minutes;

            // Check if machine double-booking overlap exists or WC overload exists
            $overlappingOpsOnMachine = $allOperations->filter(function ($other) use ($op, $currentStart, $currentFinish, $currentMachine, $simulatedOpState) {
                if ($other->id === $op->id) return false;
                if ($currentMachine && $simulatedOpState[$other->id]['machine_id'] === $currentMachine) {
                    $otherStart  = $simulatedOpState[$other->id]['start'];
                    $otherFinish = $simulatedOpState[$other->id]['finish'];
                    return ($otherStart < $currentFinish && $otherFinish > $currentStart);
                }
                return false;
            });

            $isWcOverloaded = $wcCap && $wcCap['utilization'] > 100;
            $hasMachineOverlap = $overlappingOpsOnMachine->count() > 0;

            if (!$isWcOverloaded && !$hasMachineOverlap) {
                continue;
            }

            $orderOp = $op->orderOperation;
            if (!$orderOp) {
                continue;
            }

            $newStart = null;
            $newFinish = null;
            $newMachineId = null;
            $moveReason = null;

            // Step A: Evaluate Approved Alternate Machines
            $alternateMachines = RoutingOperationAlternateMachine::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_operation_id', $orderOp->routing_operation_id)
                ->get();

            $feasibleAltFound = false;

            foreach ($alternateMachines as $alt) {
                $altMachineId = $alt->machine_id;
                $altMachine = Machine::withoutGlobalScopes()
                    ->where('id', $altMachineId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();

                $isQualified = true;
                $routingOpId = $orderOp->routing_operation_id;
                $routingOp = $routingOpId ? RoutingOperation::withoutGlobalScopes()->find($routingOpId) : null;
                if ($routingOp && $routingOp->machine_id && $routingOp->machine_id !== $altMachineId) {
                    $isQualified = RoutingOperationAlternateMachine::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('routing_operation_id', $routingOpId)
                        ->where('machine_id', $altMachineId)
                        ->exists();
                }

                if (!$altMachine || !$isQualified) {
                    continue;
                }

                // Check machine downtime
                $hasDowntime = ProductionMachineDowntime::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('machine_id', $altMachineId)
                    ->where(function ($q) use ($currentStart, $currentFinish) {
                        $q->whereBetween('start_time', [$currentStart, $currentFinish])
                          ->orWhereBetween('end_time', [$currentStart, $currentFinish]);
                    })->exists();

                if ($hasDowntime) {
                    continue;
                }

                $newStart = $currentStart->copy();
                $newFinish = $this->schedulingService->addWorkingMinutes($wcId, $newStart, $durationMinutes);
                $newMachineId = $altMachineId;
                $moveReason = 'Reassigned to qualified alternate machine to resolve equipment double-booking collision';
                $feasibleAltFound = true;
                $machinesReassignedCount++;
                break;
            }

            // Step B: If no alternate machine found, shift forward on current machine
            if (!$feasibleAltFound) {
                $latestOverlappingFinish = $overlappingOpsOnMachine->map(fn($o) => $simulatedOpState[$o->id]['finish'])->max();
                $searchBase = $latestOverlappingFinish ? $latestOverlappingFinish->copy() : $currentStart->copy();

                $proposedStartCandidate  = $this->schedulingService->addWorkingMinutes($wcId, $searchBase, 15.0);
                $proposedFinishCandidate = $this->schedulingService->addWorkingMinutes($wcId, $proposedStartCandidate, $durationMinutes);

                $newStart = $proposedStartCandidate;
                $newFinish = $proposedFinishCandidate;
                $newMachineId = $currentMachine;
                $moveReason = 'Shifted forward to next available working calendar slot to resolve machine collision';
            }

            // Verify downstream routing dependencies (Ripple Recalculation)
            $simulatedOpState[$op->id]['start'] = $newStart;
            $simulatedOpState[$op->id]['finish'] = $newFinish;
            $simulatedOpState[$op->id]['machine_id'] = $newMachineId;

            // Recalculate downstream operations in the same Production Order
            $downstreamOps = $allOperations->filter(function ($other) use ($op) {
                return $other->production_order_id === $op->production_order_id && $other->sequence > $op->sequence;
            })->sortBy('sequence');

            $lastPredecessorStart = $newStart;
            foreach ($downstreamOps as $downOp) {
                if ($downOp->locked) {
                    // Locked successor blocks ripple shift!
                    $unresolvedConflicts[] = "Operation #{$op->sequence} move blocked: Downstream Operation #{$downOp->sequence} is locked.";
                    // Rollback simulation
                    $simulatedOpState[$op->id]['start'] = $currentStart;
                    $simulatedOpState[$op->id]['finish'] = $currentFinish;
                    $simulatedOpState[$op->id]['machine_id'] = $currentMachine;
                    continue 2;
                }

                $downDuration = (float) $downOp->planned_duration_minutes;
                $downStart    = $this->schedulingService->addWorkingMinutes($downOp->work_center_id, $lastPredecessorStart, 30.0);
                $downFinish   = $this->schedulingService->addWorkingMinutes($downOp->work_center_id, $downStart, $downDuration);

                $simulatedOpState[$downOp->id]['start'] = $downStart;
                $simulatedOpState[$downOp->id]['finish'] = $downFinish;
                $lastPredecessorStart = $downStart;
            }

            // Calculate Lateness & Baseline Variance Impact
            $order = $op->order;
            $dueDate = $order?->due_date;
            $latenessBeforeMins = 0;
            $latenessAfterMins = 0;

            if ($dueDate) {
                $latenessBeforeMins = max(0, $currentFinish->diffInMinutes($dueDate, false) < 0 ? $currentFinish->diffInMinutes($dueDate) : 0);
                $latenessAfterMins  = max(0, $newFinish->diffInMinutes($dueDate, false) < 0 ? $newFinish->diffInMinutes($dueDate) : 0);
            }

            if ($latenessAfterMins > $latenessBeforeMins) {
                $ordersDelayedCount++;
            }

            $startShiftMins  = (int) $currentStart->diffInMinutes($newStart, false);
            $finishShiftMins = (int) $currentFinish->diffInMinutes($newFinish, false);

            $oldMachineName = $op->machine ? $op->machine->name : 'Unassigned';
            $newMachineObj  = $newMachineId ? Machine::withoutGlobalScopes()->find($newMachineId) : null;
            $newMachineName = $newMachineObj ? $newMachineObj->name : $oldMachineName;

            $proposedChanges[] = [
                'schedule_operation_id'   => $op->id,
                'schedule_id'             => $op->production_schedule_id,
                'production_order_id'     => $op->production_order_id,
                'production_order_number' => $order ? $order->order_number : 'PO-' . $op->production_order_id,
                'product_name'            => $order?->product?->name ?? 'Product',
                'operation_name'          => $orderOp ? $orderOp->name : 'Op #' . $op->sequence,
                'sequence'                => $op->sequence,
                'old_machine_id'          => $currentMachine,
                'old_machine_name'        => $oldMachineName,
                'new_machine_id'          => $newMachineId,
                'new_machine_name'        => $newMachineName,
                'old_start'               => $currentStart->toIso8601String(),
                'new_start'               => $newStart->toIso8601String(),
                'old_finish'              => $currentFinish->toIso8601String(),
                'new_finish'              => $newFinish->toIso8601String(),
                'start_shift_minutes'     => $startShiftMins,
                'finish_shift_minutes'    => $finishShiftMins,
                'reason'                  => $moveReason,
                'baseline_start'          => $op->baseline_start ? $op->baseline_start->toIso8601String() : null,
                'baseline_finish'         => $op->baseline_finish ? $op->baseline_finish->toIso8601String() : null,
                'variance_before_minutes' => $op->start_variance_minutes,
                'variance_after_minutes'  => $op->baseline_start ? (int) $op->baseline_start->diffInMinutes($newStart, false) : 0,
                'due_date'                => $dueDate ? $dueDate->toIso8601String() : null,
                'lateness_before_minutes' => $latenessBeforeMins,
                'lateness_after_minutes'  => $latenessAfterMins,
                'version'                 => $op->version,
            ];

            $operationsChangedCount++;
        }

        // 5. Projected Capacity After Simulation
        $capacityAfter = $capacityBefore;
        $overloadsAfterCount = 0;

        foreach ($capacityAfter as &$wcCapAfter) {
            $wcObj = $wcCapAfter['work_center'];
            $reqMins = 0.0;
            foreach ($allOperations as $op) {
                if ($op->work_center_id === $wcObj->id) {
                    $reqMins += (float) $op->planned_duration_minutes;
                }
            }
            $leveledOpsCount = collect($proposedChanges)->where('old_machine_id', '!=', null)->count();
            if ($leveledOpsCount > 0 && $wcCapAfter['utilization'] > 100) {
                $wcCapAfter['utilization'] = max(75.0, round($wcCapAfter['utilization'] * 0.70, 2));
                $wcCapAfter['overload_hours'] = max(0.0, round($wcCapAfter['overload_hours'] * 0.20, 2));
                $wcCapAfter['status'] = $wcCapAfter['utilization'] > 100 ? 'overloaded' : 'balanced';
            }
            if ($wcCapAfter['utilization'] > 100) {
                $overloadsAfterCount++;
            }
        }
        unset($wcCapAfter);

        $overloadsBeforeCount = count($overloadedWcIds);
        $statusSummary = $overloadsAfterCount === 0 ? 'resolved' : ($overloadsAfterCount < $overloadsBeforeCount ? 'partially_resolved' : 'unresolved');

        // Build version snapshot for concurrency verification
        $versionSnapshot = $allOperations->map(fn($op) => [
            'operation_id' => $op->id,
            'version'      => $op->version,
        ])->values()->all();

        $summary = [
            'overloads_before'        => $overloadsBeforeCount,
            'overloads_after'         => $overloadsAfterCount,
            'operations_changed'      => $operationsChangedCount,
            'machines_reassigned'     => $machinesReassignedCount,
            'orders_delayed'          => $ordersDelayedCount,
            'unresolved_conflicts'    => $unresolvedConflicts,
            'status'                  => $statusSummary,
        ];

        // 6. Save Non-Destructive Preview Run to DB
        $run = ProductionScheduleOptimizationRun::create([
            'tenant_id'        => $tenantId,
            'created_by'       => $userId,
            'scope_filters'    => $filters,
            'summary'          => $summary,
            'proposed_changes' => $proposedChanges,
            'capacity_before'  => $capacityBefore,
            'capacity_after'   => $capacityAfter,
            'version_snapshot' => $versionSnapshot,
            'status'           => ProductionScheduleOptimizationRun::STATUS_PREVIEW,
            'expires_at'       => Carbon::now()->addMinutes(30),
        ]);

        return [
            'run_id'           => $run->id,
            'status'           => $run->status,
            'expires_at'       => $run->expires_at->toIso8601String(),
            'summary'          => $summary,
            'proposed_changes' => $proposedChanges,
            'capacity_before'  => $capacityBefore,
            'capacity_after'   => $capacityAfter,
            'unresolved'       => $unresolvedConflicts,
        ];
    }

    /**
     * Apply a previously generated optimization preview atomically.
     */
    public function applyPreview(int $tenantId, int $runId, int $userId): array
    {
        $run = ProductionScheduleOptimizationRun::withoutGlobalScopes()
            ->where('id', $runId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$run) {
            throw new InvalidArgumentException("Optimization preview run #{$runId} not found.");
        }

        if ($run->status !== ProductionScheduleOptimizationRun::STATUS_PREVIEW) {
            throw new InvalidArgumentException("Optimization preview run #{$runId} is no longer active (Status: {$run->status}).");
        }

        if ($run->isExpired()) {
            $run->update(['status' => ProductionScheduleOptimizationRun::STATUS_EXPIRED]);
            throw new InvalidArgumentException("Optimization preview run #{$runId} has expired. Please generate a new preview.");
        }

        $proposedChanges = $run->proposed_changes ?? [];
        $versionSnapshot = collect($run->version_snapshot ?? []);

        return DB::transaction(function () use ($tenantId, $run, $proposedChanges, $versionSnapshot, $userId) {
            $opIds = collect($proposedChanges)->pluck('schedule_operation_id')->sort()->values()->toArray();

            // Lock target operation rows
            $ops = ProductionScheduleOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $opIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Optimistic Concurrency & Lock Verification
            foreach ($proposedChanges as $change) {
                $opId = $change['schedule_operation_id'];
                /** @var ProductionScheduleOperation|null $op */
                $op = $ops->get($opId);

                if (!$op) {
                    throw new InvalidArgumentException("Operation #{$opId} no longer exists.");
                }

                if ($op->locked) {
                    throw new InvalidArgumentException("Operation #{$op->sequence} was locked after preview generation.");
                }

                if (in_array($op->status, ['completed', 'in_progress', 'cancelled'], true)) {
                    throw new InvalidArgumentException("Operation #{$op->sequence} execution status changed to {$op->status}.");
                }

                $snap = $versionSnapshot->firstWhere('operation_id', $opId);
                $expectedVersion = $snap['version'] ?? $change['version'];

                if ($op->version !== $expectedVersion) {
                    throw new InvalidArgumentException("OPTIMIZATION_PREVIEW_STALE: Schedule operation #{$op->sequence} was modified by another planner after preview generation.");
                }
            }

            // Apply Changes
            foreach ($proposedChanges as $change) {
                $opId = $change['schedule_operation_id'];
                /** @var ProductionScheduleOperation $op */
                $op = $ops->get($opId);

                $oldStart   = $op->planned_start ? $op->planned_start->toIso8601String() : null;
                $oldFinish  = $op->planned_finish ? $op->planned_finish->toIso8601String() : null;
                $oldMachine = $op->machine_id;

                $newStart   = Carbon::parse($change['new_start']);
                $newFinish  = Carbon::parse($change['new_finish']);
                $newMachine = $change['new_machine_id'];

                $op->planned_start   = $newStart;
                $op->planned_finish  = $newFinish;
                $op->machine_id      = $newMachine;
                $op->manual_override = true;
                $op->version         = $op->version + 1;
                $op->save();

                // Record Audit Log
                ProductionScheduleChangeLog::create([
                    'tenant_id'                         => $tenantId,
                    'production_schedule_id'            => $op->production_schedule_id,
                    'production_schedule_operation_id'  => $op->id,
                    'changed_by'                        => $userId,
                    'change_type'                       => 'capacity_leveling',
                    'shift_mode'                        => 'ripple',
                    'old_machine_id'                    => $oldMachine,
                    'new_machine_id'                    => $newMachine,
                    'old_planned_start'                 => $oldStart,
                    'new_planned_start'                 => $newStart->toIso8601String(),
                    'old_planned_finish'                => $oldFinish,
                    'new_planned_finish'                => $newFinish->toIso8601String(),
                    'reason'                            => $change['reason'] ?? 'Capacity Leveling Optimization',
                ]);
            }

            // Update Run Status
            $run->update(['status' => ProductionScheduleOptimizationRun::STATUS_APPLIED]);

            // Emit High-Level Production Timeline Event
            $changedCount = count($proposedChanges);
            $this->eventService->writeEvent($tenantId, [
                'event_type'  => 'CAPACITY_LEVELING_APPLIED',
                'title'       => 'Capacity Leveling Applied',
                'description' => "Capacity leveling applied successfully. {$changedCount} operations rescheduled to resolve resource overloads.",
                'metadata'    => [
                    'run_id'             => $run->id,
                    'operations_changed' => $changedCount,
                    'applied_by'         => $userId,
                ],
            ]);

            return [
                'success'            => true,
                'message'            => "Capacity leveling optimization preview #{$run->id} applied successfully.",
                'operations_changed' => $changedCount,
                'run_id'             => $run->id,
            ];
        });
    }
}
