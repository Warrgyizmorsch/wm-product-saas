<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceWorkOrderService
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly DowntimeService $downtimeService,
        private readonly MachineStateService $stateService,
        private readonly PmScheduleService $pmScheduleService,
        private readonly MaintenanceCodeService $codeService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a Maintenance Work Order (Draft state).
     */
    public function createWorkOrder(int $tenantId, array $data, ?int $userId = null): ProductionMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $data['tenant_id']  = $tenantId;
            $data['created_by'] = $userId;

            if (empty($data['work_order_number'])) {
                $data['work_order_number'] = $this->codeService->generateWorkOrderNumber($tenantId);
            }

            if (empty($data['status'])) {
                $data['status'] = ProductionMaintenanceWorkOrder::STATUS_DRAFT;
            }

            $wo = $this->repository->createWorkOrder($data);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Created',
                'title'        => 'Maintenance WO Created',
                'description'  => "Maintenance Work Order [{$wo->work_order_number}] created for machine #{$wo->machine_id}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo;
        });
    }

    /**
     * Schedule a Maintenance Work Order & reserve a Downtime slot.
     */
    public function scheduleWorkOrder(
        int $id,
        int $tenantId,
        string $plannedStart,
        string $plannedEnd,
        ?int $technicianId = null,
        ?int $userId = null
    ): ProductionMaintenanceWorkOrder {
        return DB::transaction(function () use ($id, $tenantId, $plannedStart, $plannedEnd, $technicianId, $userId) {
            $wo = $this->repository->findWorkOrderForLock($id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$id} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot schedule a completed or cancelled Work Order.");
            }

            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($wo->machine_id);

            $start = Carbon::parse($plannedStart);
            $end   = Carbon::parse($plannedEnd);

            if ($end->isBefore($start)) {
                throw new InvalidArgumentException("Planned end date must be after planned start date.");
            }

            // Create or update associated scheduled Downtime record for forward scheduling / pre-release validation
            if ($wo->downtime_id) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)->find($wo->downtime_id);
                if ($downtime) {
                    $downtime->update([
                        'start_time' => $start,
                        'end_time'   => $end,
                        'status'     => ProductionMachineDowntime::STATUS_OPEN,
                    ]);
                }
            } else {
                $category = ($wo->type === ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN)
                    ? 'Breakdown'
                    : (($wo->type === ProductionMaintenanceWorkOrder::TYPE_CALIBRATION) ? 'Calibration' : 'Preventive Maintenance');

                $downtime = ProductionMachineDowntime::create([
                    'tenant_id'        => $tenantId,
                    'machine_id'       => $wo->machine_id,
                    'work_center_id'   => $machine->work_center_id,
                    'reason'           => "Scheduled Maintenance: {$wo->work_order_number}",
                    'category'         => $category,
                    'start_time'       => $start,
                    'end_time'         => $end,
                    'duration_minutes' => round($start->diffInMinutes($end), 2),
                    'created_by'       => $userId,
                    'status'           => ProductionMachineDowntime::STATUS_OPEN,
                ]);

                $wo->downtime_id = $downtime->id;
            }

            $wo->planned_start          = $start;
            $wo->planned_end            = $end;
            $wo->assigned_technician_id = $technicianId ?: $wo->assigned_technician_id;
            $wo->status                 = ProductionMaintenanceWorkOrder::STATUS_SCHEDULED;
            $wo->save();

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Scheduled',
                'title'        => 'Maintenance WO Scheduled',
                'description'  => "Work Order [{$wo->work_order_number}] scheduled from {$start->toDateTimeString()} to {$end->toDateTimeString()}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime']);
        });
    }

    /**
     * Start a Maintenance Work Order (In Progress).
     *
     * Transitions Machine to 'under_maintenance', state to 'Maintenance',
     * opens Downtime, and blocks MES operation execution.
     */
    public function startWorkOrder(int $id, int $tenantId, ?int $userId = null): ProductionMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($id, $tenantId, $userId) {
            $wo = $this->repository->findWorkOrderForLock($id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$id} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS) {
                return $wo; // Idempotent
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot start a completed or cancelled Work Order.");
            }

            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($wo->machine_id);

            $category = match ($wo->type) {
                ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN   => 'Breakdown',
                ProductionMaintenanceWorkOrder::TYPE_CALIBRATION => 'Calibration',
                default                                          => 'Preventive Maintenance',
            };

            // Start downtime if not already open
            if (!$wo->downtime_id || !ProductionMachineDowntime::where('tenant_id', $tenantId)->where('id', $wo->downtime_id)->where('status', ProductionMachineDowntime::STATUS_OPEN)->exists()) {
                $downtime = $this->downtimeService->startDowntime(
                    $tenantId,
                    $wo->machine_id,
                    $category,
                    "Maintenance Work Order In Progress: {$wo->work_order_number}",
                    $userId
                );
                $wo->downtime_id = $downtime->id;
            }

            // Ensure machine status is under_maintenance
            $machine->update([
                'status'             => Machine::STATUS_UNDER_MAINTENANCE,
                'maintenance_status' => 'in_progress',
            ]);

            $wo->actual_start = now();
            $wo->status       = ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS;
            $wo->save();

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Maintenance Started',
                'title'        => 'Maintenance Started',
                'description'  => "Technician started Work Order [{$wo->work_order_number}] on machine [{$machine->name}]. Machine is now under maintenance.",
                'severity'     => 'warning',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime']);
        });
    }

    /**
     * Emergency Breakdown Reporting Flow.
     *
     * Immediately transitions machine to 'under_maintenance', state to 'Breakdown',
     * creates open downtime, and creates a Breakdown Work Order.
     */
    public function reportBreakdown(
        int $tenantId,
        int $machineId,
        string $reason,
        ?int $userId = null,
        string $priority = ProductionMaintenanceWorkOrder::PRIORITY_HIGH
    ): ProductionMaintenanceWorkOrder {
        return DB::transaction(function () use ($tenantId, $machineId, $reason, $userId, $priority) {
            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($machineId);

            // 1. Start Breakdown Downtime via DowntimeService
            $downtime = $this->downtimeService->startDowntime(
                $tenantId,
                $machineId,
                'Breakdown',
                $reason,
                $userId
            );

            // 2. Update Machine status to under_maintenance
            $machine->update([
                'status'             => Machine::STATUS_UNDER_MAINTENANCE,
                'maintenance_status' => 'breakdown',
            ]);

            // 3. Create Breakdown Work Order
            $woNumber = $this->codeService->generateWorkOrderNumber($tenantId);

            $wo = $this->repository->createWorkOrder([
                'tenant_id'           => $tenantId,
                'work_order_number'   => $woNumber,
                'machine_id'          => $machineId,
                'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
                'priority'            => $priority,
                'actual_start'        => now(),
                'problem_description' => $reason,
                'downtime_id'         => $downtime->id,
                'status'              => ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
                'created_by'          => $userId,
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $machineId,
                'event_type'   => 'Machine Breakdown Reported',
                'title'        => 'Machine Breakdown Reported',
                'description'  => "Breakdown reported for machine [{$machine->name}]. Reason: {$reason}. Work Order [{$wo->work_order_number}] created.",
                'severity'     => 'danger',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'downtime']);
        });
    }

    /**
     * Complete a Maintenance Work Order.
     *
     * Calculates costs, ends downtime, restores machine status to active,
     * updates PM schedule next due date if PM WO.
     */
    public function completeWorkOrder(
        int $id,
        int $tenantId,
        ?int $userId = null,
        ?string $workPerformed = null,
        float $laborHours = 0.00,
        ?array $checklistJson = null
    ): ProductionMaintenanceWorkOrder {
        return DB::transaction(function () use ($id, $tenantId, $userId, $workPerformed, $laborHours, $checklistJson) {
            $wo = $this->repository->findWorkOrderForLock($id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$id} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED) {
                return $wo; // Idempotent
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot complete a cancelled Work Order.");
            }

            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($wo->machine_id);

            // Determine labor cost rate from WorkCenter or default
            $workCenter = WorkCenter::find($machine->work_center_id);
            $laborRate  = $workCenter ? (float) $workCenter->cost_per_hour : 0.00;

            $calculatedLaborCost = round($laborHours * $laborRate, 2);

            // Sum issued spare parts cost
            $sparesCost = (float) ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $wo->id)
                ->sum('total_cost');

            $totalCost = round($calculatedLaborCost + $sparesCost, 2);

            $now = now();

            // End associated downtime
            if ($wo->downtime_id) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)->find($wo->downtime_id);
                if ($downtime && $downtime->status !== ProductionMachineDowntime::STATUS_CLOSED) {
                    $this->downtimeService->endDowntime(
                        $tenantId,
                        $downtime->id,
                        $userId,
                        $workPerformed ?: 'Maintenance Completed',
                        'Idle'
                    );
                }
            } else {
                $this->stateService->transitionState($tenantId, $machine->id, 'Idle', 'Maintenance Completed', $userId, $workPerformed);
            }

            // Restore machine status to Active
            $machine->update([
                'status'                    => Machine::STATUS_ACTIVE,
                'maintenance_status'        => 'none',
                'last_maintenance_date'     => $now->toDateString(),
            ]);

            // Update PM Schedule next due date if this was a PM Work Order
            if ($wo->pm_schedule_id) {
                $pmSchedule = $this->repository->findPmSchedule($wo->pm_schedule_id, $tenantId);
                if ($pmSchedule) {
                    $nextDue = $this->pmScheduleService->computeNextDueDate(
                        $now,
                        $pmSchedule->frequency_type,
                        $pmSchedule->frequency_value
                    );

                    $pmSchedule->update([
                        'last_completed_date' => $now->toDateString(),
                        'next_due_date'       => $nextDue->toDateString(),
                    ]);

                    $machine->update([
                        'next_maintenance_due_date' => $nextDue->toDateString(),
                    ]);
                }
            }

            // Update Work Order record
            $wo->update([
                'actual_end'       => $now,
                'work_performed'   => $workPerformed ?: $wo->work_performed,
                'checklist_json'   => $checklistJson ?: $wo->checklist_json,
                'labor_hours'      => $laborHours,
                'labor_cost_rate'  => $laborRate,
                'labor_cost'       => $calculatedLaborCost,
                'spare_parts_cost' => $sparesCost,
                'total_cost'       => $totalCost,
                'status'           => ProductionMaintenanceWorkOrder::STATUS_COMPLETED,
                'completed_by'     => $userId,
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Maintenance Completed',
                'title'        => 'Maintenance Completed',
                'description'  => "Work Order [{$wo->work_order_number}] completed for machine [{$machine->name}]. Total Cost: \${$totalCost}. Machine restored to active.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime', 'spares.product']);
        });
    }

    /**
     * Cancel a Maintenance Work Order.
     */
    public function cancelWorkOrder(int $id, int $tenantId, ?int $userId = null, ?string $reason = null): ProductionMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($id, $tenantId, $userId, $reason) {
            $wo = $this->repository->findWorkOrderForLock($id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$id} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                return $wo;
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED) {
                throw new InvalidArgumentException("Cannot cancel a completed Work Order.");
            }

            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($wo->machine_id);

            // If machine is under maintenance for this WO, restore to active
            if ($machine->status === Machine::STATUS_UNDER_MAINTENANCE) {
                $machine->update([
                    'status'             => Machine::STATUS_ACTIVE,
                    'maintenance_status' => 'none',
                ]);
                $this->stateService->transitionState($tenantId, $machine->id, 'Idle', $reason ?: 'Work Order Cancelled', $userId);
            }

            // Close downtime if open
            if ($wo->downtime_id) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)->find($wo->downtime_id);
                if ($downtime && $downtime->status !== ProductionMachineDowntime::STATUS_CLOSED) {
                    $downtime->update([
                        'end_time' => now(),
                        'status'   => ProductionMachineDowntime::STATUS_CLOSED,
                        'remarks'  => $reason ?: 'Work Order Cancelled',
                    ]);
                }
            }

            $wo->update([
                'status'  => ProductionMaintenanceWorkOrder::STATUS_CANCELLED,
                'work_performed' => $wo->work_performed ? $wo->work_performed . " [Cancelled: {$reason}]" : "Cancelled: {$reason}",
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Cancelled',
                'title'        => 'Maintenance WO Cancelled',
                'description'  => "Work Order [{$wo->work_order_number}] cancelled. Reason: {$reason}",
                'severity'     => 'warning',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime']);
        });
    }
}
