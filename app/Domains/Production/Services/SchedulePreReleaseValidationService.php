<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;

class SchedulePreReleaseValidationService
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly CapacityPlanningService $capacityService
    ) {}

    /**
     * Perform comprehensive pre-release validation for a Production Schedule.
     *
     * @param  ProductionSchedule $schedule
     * @return array
     */
    public function validate(ProductionSchedule $schedule): array
    {
        $tenantId = $schedule->tenant_id ?? require_tenant_id();

        $errors   = [];
        $warnings = [];
        $info     = [];

        // 1. Schedule Completeness & Operations Check
        $operations = ProductionScheduleOperation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_schedule_id', $schedule->id)
            ->with(['workCenter', 'section', 'machine.section', 'manualProduction', 'orderOperation.routingOperation', 'orderOperation.predecessorDependencies'])
            ->orderBy('sequence')
            ->get();

        if ($operations->isEmpty()) {
            $errors[] = [
                'code'         => 'EMPTY_SCHEDULE',
                'severity'     => 'error',
                'message'      => "Schedule [{$schedule->schedule_number}] has no operations assigned.",
                'operation_id' => null,
            ];
        }

        foreach ($operations as $op) {
            $isExternal = (bool) ($op->orderOperation?->is_external ?? $op->orderOperation?->routingOperation?->is_external ?? ($op->work_center_id === null));

            if (!$op->planned_start || !$op->planned_finish) {
                $errors[] = [
                    'code'         => 'MISSING_PLANNED_TIMING',
                    'severity'     => 'error',
                    'message'      => "Operation sequence {$op->sequence} is missing planned start or finish time.",
                    'operation_id' => $op->id,
                ];
            }

            if (!$isExternal && (!$op->workCenter || !$op->workCenter->isActive())) {
                $errors[] = [
                    'code'         => 'INACTIVE_WORK_CENTER',
                    'severity'     => 'error',
                    'message'      => "Operation sequence {$op->sequence} relies on an inactive or missing Work Center.",
                    'operation_id' => $op->id,
                ];
            }

            // Section architecture: an internal operation runs on a Machine OR a Manual Batch inside a Section.
            if (!$isExternal && $op->work_center_id) {
                $opLabel = "Operation sequence {$op->sequence}";

                if (!$op->machine_id && !$op->section_manual_production_id) {
                    $errors[] = [
                        'code'         => 'NO_RESOURCE_ASSIGNED',
                        'severity'     => 'error',
                        'message'      => "{$opLabel} has no machine or manual batch assigned at Work Center [" . ($op->workCenter?->name ?? 'N/A') . "].",
                        'operation_id' => $op->id,
                    ];
                }

                if ($op->section_id) {
                    $section = $op->section;
                    if (!$section || !$section->isActive()) {
                        $errors[] = [
                            'code'         => 'INACTIVE_SECTION',
                            'severity'     => 'error',
                            'message'      => "{$opLabel} relies on an inactive or missing Section" . ($section ? " [{$section->name}]" : '') . '.',
                            'operation_id' => $op->id,
                        ];
                    } elseif ((int) $section->work_center_id !== (int) $op->work_center_id) {
                        $errors[] = [
                            'code'         => 'SECTION_WORK_CENTER_MISMATCH',
                            'severity'     => 'error',
                            'message'      => "{$opLabel}: Section [{$section->name}] does not belong to Work Center [" . ($op->workCenter?->name ?? 'N/A') . "].",
                            'operation_id' => $op->id,
                        ];
                    }
                }

                if ($op->section_manual_production_id) {
                    $batch = $op->manualProduction;
                    if (!$batch || !$batch->active) {
                        $errors[] = [
                            'code'         => 'MANUAL_BATCH_INACTIVE',
                            'severity'     => 'error',
                            'message'      => "{$opLabel} relies on an inactive or missing manual batch" . ($batch ? " [{$batch->batch_name}]" : '') . '.',
                            'operation_id' => $op->id,
                        ];
                    } elseif ($op->section_id && (int) $batch->section_id !== (int) $op->section_id) {
                        $errors[] = [
                            'code'         => 'MANUAL_BATCH_SECTION_MISMATCH',
                            'severity'     => 'error',
                            'message'      => "{$opLabel}: manual batch [{$batch->batch_name}] does not belong to the operation's Section.",
                            'operation_id' => $op->id,
                        ];
                    }
                }

                // Slot could not be placed inside shifts (generation fell back to a raw start time)
                foreach ((array) ($op->warnings ?? []) as $w) {
                    if (($w['code'] ?? null) === 'NO_SHIFTS_CONFIGURED') {
                        $errors[] = [
                            'code'         => 'NO_SHIFTS_CONFIGURED',
                            'severity'     => 'error',
                            'message'      => "{$opLabel}: " . ($w['message'] ?? 'no active shifts configured.'),
                            'operation_id' => $op->id,
                        ];
                    }
                }
            }
        }

        // 2. Dependency Integrity Check
        if ($operations->count() > 1) {
            $orderQty = (float) ($schedule->order->quantity_ordered ?? 1);

            $opByOrderOpId = [];
            foreach ($operations as $op) {
                if ($op->production_order_operation_id) {
                    $opByOrderOpId[$op->production_order_operation_id] = $op;
                }
            }

            foreach ($operations as $op) {
                $orderOp = $op->orderOperation;
                if (!$orderOp) {
                    continue;
                }

                $predecessorOrderOpIds = [];

                if ($orderOp->previous_operation_id) {
                    $predecessorOrderOpIds[] = $orderOp->previous_operation_id;
                }

                if ($orderOp->relationLoaded('predecessorDependencies')) {
                    foreach ($orderOp->predecessorDependencies as $pred) {
                        $predecessorOrderOpIds[] = $pred->id;
                    }
                } else {
                    $predIds = \App\Domains\Production\Models\ProductionOrderOperationDependency::where('tenant_id', $tenantId)
                        ->where('operation_id', $orderOp->id)
                        ->pluck('predecessor_operation_id')
                        ->toArray();
                    $predecessorOrderOpIds = array_merge($predecessorOrderOpIds, $predIds);
                }

                $predecessorOrderOpIds = array_unique($predecessorOrderOpIds);

                // Fallback for single-routing legacy orders where previous_operation_id is not set
                if (empty($predecessorOrderOpIds)) {
                    $sameRoutingPrev = $operations->filter(function ($prevOp) use ($op, $orderOp) {
                        $prevOrderOp = $prevOp->orderOperation;
                        if (!$prevOrderOp) return false;

                        $sameSource = ($orderOp->source_routing_id && $prevOrderOp->source_routing_id)
                            ? ($orderOp->source_routing_id === $prevOrderOp->source_routing_id)
                            : ($orderOp->production_order_id === $prevOrderOp->production_order_id);

                        return $sameSource && $prevOp->sequence < $op->sequence;
                    })->sortByDesc('sequence')->first();

                    if ($sameRoutingPrev && $sameRoutingPrev->production_order_operation_id) {
                        $predecessorOrderOpIds[] = $sameRoutingPrev->production_order_operation_id;
                    }
                }

                foreach ($predecessorOrderOpIds as $predOpId) {
                    if (!isset($opByOrderOpId[$predOpId])) {
                        continue;
                    }

                    $predSchedOp = $opByOrderOpId[$predOpId];
                    $predOrderOp = $predSchedOp->orderOperation;

                    $earliestNextStart = $predSchedOp->planned_finish;
                    if ($predOrderOp && (bool) $predOrderOp->overlap_enabled) {
                        $predQty = (float) ($predOrderOp->target_produced_qty > 0 ? $predOrderOp->target_produced_qty : $orderQty);
                        $earliestNextStart = $this->schedulingService->calculateTransferReadyAt(
                            $predOrderOp,
                            $predSchedOp->planned_start,
                            $predQty
                        );
                    }

                    if ($op->planned_start && $op->planned_start->lt($earliestNextStart->copy()->subSeconds(10))) {
                        $opName = $op->operation_name ?? $orderOp->name ?? "Sequence {$op->sequence}";
                        $predName = $predSchedOp->operation_name ?? $predOrderOp?->name ?? "Sequence {$predSchedOp->sequence}";
                        $errors[] = [
                            'code'         => 'DEPENDENCY_VIOLATION',
                            'severity'     => 'error',
                            'message'      => "Operation '{$opName}' (Seq {$op->sequence}) starts at {$op->planned_start->toDateTimeString()}, before predecessor '{$predName}' (Seq {$predSchedOp->sequence}) transfer-ready time {$earliestNextStart->toDateTimeString()}.",
                            'operation_id' => $op->id,
                        ];
                    }
                }
            }
        }

        // 3. Machine Readiness & Downtime Checks
        foreach ($operations as $op) {
            if ($op->machine_id && $op->machine) {
                $m = $op->machine;
                if ($m->tenant_id !== $tenantId) {
                    $errors[] = [
                        'code'         => 'CROSS_TENANT_MACHINE',
                        'severity'     => 'error',
                        'message'      => "Machine [{$m->name}] does not belong to tenant.",
                        'operation_id' => $op->id,
                    ];
                }

                // Machines belong to a Section; the Work Center is derived through it.
                if ((int) ($m->section?->work_center_id ?? 0) !== (int) $op->work_center_id
                    || ($op->section_id && (int) $m->section_id !== (int) $op->section_id)) {
                    $errors[] = [
                        'code'         => 'MACHINE_WORK_CENTER_MISMATCH',
                        'severity'     => 'error',
                        'message'      => "Machine [{$m->name}] does not belong to Work Center sequence {$op->sequence}.",
                        'operation_id' => $op->id,
                    ];
                }

                if (!$m->isActive()) {
                    $errors[] = [
                        'code'         => 'MACHINE_INACTIVE',
                        'severity'     => 'error',
                        'message'      => "Machine [{$m->name}] is not active (status: {$m->status}).",
                        'operation_id' => $op->id,
                    ];
                }

                // Machine qualification: no alternate machines for now — the alternative operation is chosen at
                // Production Order creation, so the schedule must use the machine planned on the order operation.
                if ($op->orderOperation) {
                    $plannedMachineId = $op->orderOperation->machine_id ?: $op->orderOperation->routingOperation?->machine_id;

                    if ($plannedMachineId && (int) $plannedMachineId !== (int) $m->id) {
                        $errors[] = [
                            'code'         => 'UNQUALIFIED_MACHINE',
                            'severity'     => 'error',
                            'message'      => "Machine [{$m->name}] is not the machine planned for operation sequence {$op->sequence}.",
                            'operation_id' => $op->id,
                        ];
                    }
                }

                // Downtime Collision Check
                $downtimeConflict = ProductionMachineDowntime::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('machine_id', $m->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->where(function ($q) use ($op) {
                        $q->whereBetween('start_time', [$op->planned_start, $op->planned_finish])
                          ->orWhereBetween('end_time', [$op->planned_start, $op->planned_finish])
                          ->orWhere(function ($sub) use ($op) {
                              $sub->where('start_time', '<=', $op->planned_start)
                                  ->where('end_time', '>=', $op->planned_finish);
                          });
                    })
                    ->first();

                if ($downtimeConflict) {
                    $errors[] = [
                        'code'         => 'MACHINE_DOWNTIME_COLLISION',
                        'severity'     => 'error',
                        'message'      => "Machine [{$m->name}] has scheduled maintenance downtime during operation sequence {$op->sequence}.",
                        'operation_id' => $op->id,
                    ];
                }
            }
        }

        // 4. Schedule Conflicts (Double Booking)
        $detectedConflicts = $this->schedulingService->detectConflictsDetailed($tenantId);
        $scheduleOpIds = $operations->pluck('id')->all();
        foreach ($detectedConflicts as $conflict) {
            if (in_array($conflict['curr_op_id'], $scheduleOpIds, true) || in_array($conflict['next_op_id'], $scheduleOpIds, true)) {
                $matchedOpId = in_array($conflict['curr_op_id'], $scheduleOpIds, true) ? $conflict['curr_op_id'] : $conflict['next_op_id'];
                $errors[] = [
                    'code'         => 'MACHINE_DOUBLE_BOOKING',
                    'severity'     => 'error',
                    'message'      => $conflict['message'],
                    'operation_id' => $matchedOpId,
                ];
            }
        }

        // 5. Work Center Capacity Overloads (Warning)
        $scheduleOverloads = $this->schedulingService->detectOverloadsForSchedule($schedule);
        foreach ($scheduleOverloads as $overloadMsg) {
            $warnings[] = [
                'code'         => 'CAPACITY_OVERLOAD',
                'severity'     => 'warning',
                'message'      => $overloadMsg,
                'operation_id' => null,
            ];
        }

        // 6. Material Readiness Checks (Warnings for Shortages)
        if ($schedule->production_order_id) {
            $reservations = ProductionOrderReservation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_order_id', $schedule->production_order_id)
                ->with(['product'])
                ->get();

            if ($reservations->isEmpty()) {
                $info[] = [
                    'code'         => 'NO_MATERIAL_RESERVATIONS',
                    'severity'     => 'info',
                    'message'      => 'No explicit material BOM reservations found for this Production Order.',
                    'operation_id' => null,
                ];
            } else {
                $totalItems = $reservations->count();
                $readyCount = 0;
                $shortageCount = 0;

                foreach ($reservations as $res) {
                    $required = (float) $res->quantity_planned;
                    $issued   = (float) $res->quantity_issued;
                    $reserved = (float) $res->quantity_reserved;

                    if ($issued >= $required && $required > 0) {
                        $readyCount++;
                        continue;
                    }

                    $available = 0.0;
                    if ($res->warehouse_id) {
                        $available = (float) StockService::getAvailableStock($res->product_id, $res->warehouse_id);
                    }
                    if ($available <= 0) {
                        $available = (float) \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                            ->where('product_id', $res->product_id)
                            ->sum('quantity');
                    }

                    $totalAvailable = $issued + $reserved + $available;

                    if ($totalAvailable >= $required || ($issued + $reserved) >= $required) {
                        $readyCount++;
                    } elseif ($totalAvailable > 0) {
                        $shortageCount++;
                        $warnings[] = [
                            'code'         => 'MATERIAL_PARTIALLY_AVAILABLE',
                            'severity'     => 'warning',
                            'message'      => "Material [{$res->product->name}] is partially available (Issued: {$issued}, Warehouse Stock: {$available}, Required: {$required}).",
                            'operation_id' => null,
                        ];
                    } else {
                        $shortageCount++;
                        $warnings[] = [
                            'code'         => 'MATERIAL_SHORTAGE',
                            'severity'     => 'warning',
                            'message'      => "Material shortage for [{$res->product->name}] (Required: {$required}, Issued: {$issued}, Warehouse Stock: {$available}).",
                            'operation_id' => null,
                        ];
                    }
                }

                $info[] = [
                    'code'         => 'MATERIAL_READINESS_SUMMARY',
                    'severity'     => 'info',
                    'message'      => "Material readiness: {$readyCount}/{$totalItems} component items ready.",
                    'operation_id' => null,
                ];
            }
        }

        $canRelease = empty($errors);
        $hasWarnings = !empty($warnings);

        return [
            'can_release'  => $canRelease,
            'has_warnings' => $hasWarnings,
            'errors'       => array_values($errors),
            'warnings'     => array_values($warnings),
            'info'         => array_values($info),
            'summary'      => [
                'total_operations' => $operations->count(),
                'error_count'      => count($errors),
                'warning_count'    => count($warnings),
                'info_count'       => count($info),
            ],
        ];
    }
}
