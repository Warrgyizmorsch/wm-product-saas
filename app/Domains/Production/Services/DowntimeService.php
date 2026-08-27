<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DowntimeService
{
    public function __construct(
        private readonly MachineStateService $stateService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Start a machine downtime event.
     */
    public function startDowntime(
        int $tenantId,
        int $machineId,
        string $category,
        string $reason,
        ?int $userId = null,
        array $params = []
    ): ProductionMachineDowntime {
        return DB::transaction(function () use ($tenantId, $machineId, $category, $reason, $userId, $params) {
            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($machineId);

            // 1. Conflict Prevention: Check for existing open downtime
            $activeDowntime = ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('machine_id', $machineId)
                ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                ->exists();

            if ($activeDowntime) {
                throw new InvalidArgumentException("Cannot start downtime: Machine already has an active open downtime event.");
            }

            // 2. Create the downtime record
            $downtime = ProductionMachineDowntime::create([
                'tenant_id'                      => $tenantId,
                'machine_id'                     => $machineId,
                'work_center_id'                 => $machine->work_center_id,
                'production_order_id'            => $params['production_order_id'] ?? null,
                'production_order_operation_id'  => $params['production_order_operation_id'] ?? null,
                'reason'                         => $reason,
                'category'                       => $category,
                'start_time'                     => now(),
                'end_time'                       => null,
                'duration_minutes'               => null,
                'created_by'                     => $userId,
                'approved_by'                    => null,
                'remarks'                        => $params['remarks'] ?? null,
                'status'                         => ProductionMachineDowntime::STATUS_OPEN,
            ]);

            // 3. Map category to appropriate machine state transition
            $newState = match ($category) {
                'Breakdown'                                => 'Breakdown',
                'Preventive Maintenance', 'Calibration'    => 'Maintenance',
                'Setup', 'Tool Change'                     => 'Setup',
                'Material Shortage'                        => 'Waiting Material',
                'Operator Shortage', 'Operator Pause'      => 'Waiting Operator',
                default                                    => 'Offline',
            };

            $this->stateService->transitionState($tenantId, $machineId, $newState, $reason, $userId, $params['remarks'] ?? null);

            $this->eventService->writeEvent($tenantId, [
                'production_order_id'            => $downtime->production_order_id,
                'production_order_operation_id'  => $downtime->production_order_operation_id,
                'machine_id'                     => $machineId,
                'operator_id'                    => $userId,
                'event_type'                     => 'Downtime Started',
                'title'                          => 'Downtime Started',
                'description'                    => "Machine [{$machine->name}] has entered downtime ({$category}). Reason: {$reason}",
                'severity'                       => 'warning',
                'event_source'                   => 'DowntimeService',
                'triggered_by'                   => $userId,
            ]);

            return $downtime;
        });
    }

    /**
     * End a machine downtime event.
     */
    public function endDowntime(
        int $tenantId,
        int $downtimeId,
        ?int $userId = null,
        ?string $remarks = null,
        ?string $targetState = 'Idle'
    ): ProductionMachineDowntime {
        return DB::transaction(function () use ($tenantId, $downtimeId, $userId, $remarks, $targetState) {
            $downtime = ProductionMachineDowntime::findOrFail($downtimeId);

            if ($downtime->status === ProductionMachineDowntime::STATUS_CLOSED) {
                throw new InvalidArgumentException("Downtime event is already closed.");
            }

            $endTime = now();
            $start = \Illuminate\Support\Carbon::parse($downtime->start_time);
            $durationMinutes = max(0.00, round($start->diffInSeconds($endTime) / 60.0, 2));

            $downtime->update([
                'end_time'         => $endTime,
                'duration_minutes' => $durationMinutes,
                'approved_by'      => $userId,
                'remarks'          => $remarks ?? $downtime->remarks,
                'status'           => ProductionMachineDowntime::STATUS_CLOSED,
            ]);

            // If there is an associated active Maintenance Work Order, complete it as well
            $wo = \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
                ->where(function ($q) use ($downtime) {
                    $q->where('downtime_id', $downtime->id)
                      ->orWhere(function ($sub) use ($downtime) {
                          $sub->where('machine_id', $downtime->machine_id)
                              ->whereIn('status', [
                                  \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                                  \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
                                  \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
                              ]);
                      });
                })
                ->first();

            if ($wo) {
                $workCenter = \App\Domains\Production\Models\WorkCenter::find($downtime->work_center_id);
                $laborRate  = $workCenter ? (float) $workCenter->cost_per_hour : 0.00;

                $laborHours = max(0.1, round($durationMinutes / 60.0, 2));
                $calculatedLaborCost = round($laborHours * $laborRate, 2);

                $sparesCost = (float) \App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                    ->where('maintenance_work_order_id', $wo->id)
                    ->sum('total_cost');

                $totalCost = round($calculatedLaborCost + $sparesCost, 2);

                $wo->update([
                    'actual_end'       => $endTime,
                    'work_performed'   => $remarks ?: ($wo->work_performed ?: 'Downtime Resolved & Maintenance Completed'),
                    'labor_hours'      => $laborHours,
                    'labor_cost_rate'  => $laborRate,
                    'labor_cost'       => $calculatedLaborCost,
                    'spare_parts_cost' => $sparesCost,
                    'total_cost'       => $totalCost,
                    'status'           => \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_COMPLETED,
                    'completed_by'     => $userId,
                ]);

                if ($wo->pm_schedule_id) {
                    $pmSchedule = \App\Domains\Production\Models\ProductionPmSchedule::where('tenant_id', $tenantId)->find($wo->pm_schedule_id);
                    if ($pmSchedule) {
                        $pmService = app(PmScheduleService::class);
                        $nextDue = $pmService->computeNextDueDate(
                            $endTime,
                            $pmSchedule->frequency_type,
                            $pmSchedule->frequency_value
                        );

                        $pmSchedule->update([
                            'last_completed_date' => $endTime->toDateString(),
                            'next_due_date'       => $nextDue->toDateString(),
                        ]);
                    }
                }
            }

            // Restore machine status to Active and maintenance_status to none
            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($downtime->machine_id);
            if ($machine) {
                $machine->update([
                    'status'             => Machine::STATUS_ACTIVE,
                    'maintenance_status' => 'none',
                    'last_maintenance_date' => $endTime->toDateString(),
                ]);
            }

            // Transition machine state to targetState (defaults to 'Idle')
            $this->stateService->transitionState($tenantId, $downtime->machine_id, $targetState ?: 'Idle', $remarks ?: 'Downtime Ended', $userId, $remarks);

            $machineName = $machine ? $machine->name : 'Machine';

            $this->eventService->writeEvent($tenantId, [
                'production_order_id'            => $downtime->production_order_id,
                'production_order_operation_id'  => $downtime->production_order_operation_id,
                'machine_id'                     => $downtime->machine_id,
                'operator_id'                    => $userId,
                'event_type'                     => 'Downtime Ended',
                'title'                          => 'Downtime Ended',
                'description'                    => "Downtime on machine [{$machineName}] has ended and maintenance work order resolved.",
                'severity'                       => 'info',
                'event_source'                   => 'DowntimeService',
                'triggered_by'                   => $userId,
            ]);

            return $downtime;
        });
    }
}
