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
                'Operator Shortage'                        => 'Waiting Operator',
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
        ?string $remarks = null
    ): ProductionMachineDowntime {
        return DB::transaction(function () use ($tenantId, $downtimeId, $userId, $remarks) {
            $downtime = ProductionMachineDowntime::findOrFail($downtimeId);

            if ($downtime->status === ProductionMachineDowntime::STATUS_CLOSED) {
                throw new InvalidArgumentException("Downtime event is already closed.");
            }

            $endTime = now();
            $durationMinutes = max(0.00, round($endTime->diffInSeconds($downtime->start_time) / 60.0, 2));

            $downtime->update([
                'end_time'         => $endTime,
                'duration_minutes' => $durationMinutes,
                'approved_by'      => $userId,
                'remarks'          => $remarks ?? $downtime->remarks,
                'status'           => ProductionMachineDowntime::STATUS_CLOSED,
            ]);

            // Transition machine state back to Idle
            $this->stateService->transitionState($tenantId, $downtime->machine_id, 'Idle', 'Downtime Ended', $userId, $remarks);

            $machineName = $downtime->machine ? $downtime->machine->name : 'Machine';

            $this->eventService->writeEvent($tenantId, [
                'production_order_id'            => $downtime->production_order_id,
                'production_order_operation_id'  => $downtime->production_order_operation_id,
                'machine_id'                     => $downtime->machine_id,
                'operator_id'                    => $userId,
                'event_type'                     => 'Downtime Ended',
                'title'                          => 'Downtime Ended',
                'description'                    => "Downtime on machine [{$machineName}] has ended.",
                'severity'                       => 'info',
                'event_source'                   => 'DowntimeService',
                'triggered_by'                   => $userId,
            ]);

            return $downtime;
        });
    }
}
