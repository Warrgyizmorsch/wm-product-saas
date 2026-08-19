<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
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
            ->with(['workCenter', 'machine', 'orderOperation.routingOperation'])
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
            $isExternal = (bool) ($op->orderOperation?->is_external ?? $op->orderOperation?->routingOperation?->is_external ?? false);

            if (!$op->planned_start || !$op->planned_finish) {
                $errors[] = [
                    'code'         => 'MISSING_PLANNED_TIMING',
                    'severity'     => 'error',
                    'message'      => "Operation sequence {$op->sequence} is missing planned start or finish time.",
                    'operation_id' => $op->id,
                ];
            }

            if (!$op->workCenter || !$op->workCenter->isActive()) {
                $errors[] = [
                    'code'         => 'INACTIVE_WORK_CENTER',
                    'severity'     => 'error',
                    'message'      => "Operation sequence {$op->sequence} relies on an inactive or missing Work Center.",
                    'operation_id' => $op->id,
                ];
            }

            // Check if Machine assignment is required for this WorkCenter (only for internal operations)
            if (!$isExternal && !$op->machine_id) {
                $machinesCount = Machine::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('work_center_id', $op->work_center_id)
                    ->where('status', 'active')
                    ->count();

                if ($machinesCount > 0) {
                    $errors[] = [
                        'code'         => 'UNASSIGNED_MACHINE',
                        'severity'     => 'error',
                        'message'      => "Operation sequence {$op->sequence} requires a machine assignment at Work Center [{$op->workCenter->name}].",
                        'operation_id' => $op->id,
                    ];
                }
            }
        }

        // 2. Dependency Integrity Check
        if ($operations->count() > 1) {
            $orderQty = (float) ($schedule->order->quantity_ordered ?? 1);
            for ($i = 0; $i < $operations->count() - 1; $i++) {
                $curr = $operations[$i];
                $next = $operations[$i + 1];

                $earliestNextStart = $curr->planned_finish;
                if ($curr->orderOperation) {
                    $earliestNextStart = $this->schedulingService->calculateTransferReadyAt(
                        $curr->orderOperation,
                        $curr->planned_start,
                        $orderQty
                    );
                }

                if ($next->planned_start->lt($earliestNextStart)) {
                    $errors[] = [
                        'code'         => 'DEPENDENCY_VIOLATION',
                        'severity'     => 'error',
                        'message'      => "Sequence {$next->sequence} starts at {$next->planned_start->toDateTimeString()}, before predecessor transfer-ready time {$earliestNextStart->toDateTimeString()}.",
                        'operation_id' => $next->id,
                    ];
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

                if ($m->work_center_id !== $op->work_center_id) {
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

                // Machine qualification check (Primary or Approved Alternate)
                if ($op->orderOperation && $op->orderOperation->routing_operation_id) {
                    $routingOpId = $op->orderOperation->routing_operation_id;
                    $routingOp   = \App\Domains\Production\Models\RoutingOperation::find($routingOpId);

                    if ($routingOp && $routingOp->machine_id && $routingOp->machine_id !== $m->id) {
                        $isApprovedAlt = RoutingOperationAlternateMachine::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('routing_operation_id', $routingOpId)
                            ->where('machine_id', $m->id)
                            ->exists();

                        if (!$isApprovedAlt) {
                            $errors[] = [
                                'code'         => 'UNQUALIFIED_MACHINE',
                                'severity'     => 'error',
                                'message'      => "Machine [{$m->name}] is not an approved primary or alternate machine for operation sequence {$op->sequence}.",
                                'operation_id' => $op->id,
                            ];
                        }
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
        $detectedConflicts = $this->schedulingService->detectConflicts($tenantId);
        foreach ($detectedConflicts as $conflictMsg) {
            // Check if any operation in this schedule is involved
            foreach ($operations as $op) {
                if (str_contains($conflictMsg, "Schedule Op #{$op->id}")) {
                    $errors[] = [
                        'code'         => 'MACHINE_DOUBLE_BOOKING',
                        'severity'     => 'error',
                        'message'      => $conflictMsg,
                        'operation_id' => $op->id,
                    ];
                }
            }
        }

        // 5. Work Center Capacity Overloads (Warning)
        $detectedOverloads = $this->schedulingService->detectOverloads($tenantId);
        $scheduleWcNames   = $operations->pluck('workCenter.name')->filter()->unique();

        foreach ($detectedOverloads as $overloadMsg) {
            foreach ($scheduleWcNames as $wcName) {
                if (str_contains($overloadMsg, $wcName)) {
                    $warnings[] = [
                        'code'         => 'CAPACITY_OVERLOAD',
                        'severity'     => 'warning',
                        'message'      => $overloadMsg,
                        'operation_id' => null,
                    ];
                }
            }
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

                    $remainingNeed = max(0.0, $required - $issued - $reserved);
                    $available = 0.0;
                    if ($res->warehouse_id) {
                        $available = StockService::getAvailableStock($res->product_id, $res->warehouse_id);
                    }

                    if ($remainingNeed == 0.0 || ($reserved + $available) >= $required) {
                        $readyCount++;
                    } elseif (($reserved + $available) > 0) {
                        $shortageCount++;
                        $warnings[] = [
                            'code'         => 'MATERIAL_PARTIALLY_AVAILABLE',
                            'severity'     => 'warning',
                            'message'      => "Material [{$res->product->name}] is partially available (Available: {$available}, Reserved: {$reserved}, Need: {$required}).",
                            'operation_id' => null,
                        ];
                    } else {
                        $shortageCount++;
                        $warnings[] = [
                            'code'         => 'MATERIAL_SHORTAGE',
                            'severity'     => 'warning',
                            'message'      => "Material shortage for [{$res->product->name}] (Required: {$required}, Available: {$available}).",
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
