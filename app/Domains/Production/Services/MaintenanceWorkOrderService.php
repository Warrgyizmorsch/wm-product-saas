<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\WorkCenter;
use App\Models\User;
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
        private readonly ProductionEventService $eventService,
        private readonly MaintenanceWorkOrderLogService $logService
    ) {}

    private function isBlankAssignment(array $assignment): bool
    {
        $assignmentType = trim((string) ($assignment['assignment_type'] ?? $assignment['type'] ?? ''));
        $technicianId = $assignment['technician_id'] ?? null;
        $technicianName = trim((string) ($assignment['technician_name'] ?? ''));
        $workedHours = $assignment['worked_hours'] ?? null;
        $hourlyRate = $assignment['hourly_rate'] ?? null;
        $notes = trim((string) ($assignment['notes'] ?? ''));

        return $assignmentType === ''
            && (empty($technicianId) || $technicianId === '')
            && $technicianName === ''
            && ($workedHours === null || $workedHours === '')
            && ($hourlyRate === null || $hourlyRate === '')
            && $notes === '';
    }

    private function normalizeAssignmentInput(array $assignment): array
    {
        $type = strtolower((string) ($assignment['assignment_type'] ?? $assignment['type'] ?? ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL));
        if (!in_array($type, [ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL, ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL], true)) {
            throw new InvalidArgumentException('Assignment type must be internal or external.');
        }

        $normalized = [
            'assignment_type' => $type,
            'technician_id' => null,
            'technician_name' => '',
            'expected_work_hours' => isset($assignment['expected_work_hours']) ? (float) $assignment['expected_work_hours'] : (isset($assignment['worked_hours']) ? (float) $assignment['worked_hours'] : 0.0),
            'worked_hours' => 0.0,
            'hourly_rate' => isset($assignment['hourly_rate']) ? (float) $assignment['hourly_rate'] : 0.0,
            'notes' => trim((string) ($assignment['notes'] ?? '')),
            'assigned_at' => $assignment['assigned_at'] ?? now(),
        ];

        if ($type === ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL) {
            $technicianId = isset($assignment['technician_id']) ? (int) $assignment['technician_id'] : null;
            $user = $technicianId ? User::find($technicianId) : null;
            $technicianName = trim((string) ($assignment['technician_name'] ?? ($user?->name ?? '')));

            if (!$technicianId || $technicianName === '') {
                throw new InvalidArgumentException('Internal assignments require a valid technician and a technician name.');
            }

            $normalized['technician_id'] = $technicianId;
            $normalized['technician_name'] = $technicianName;
        } else {
            $technicianName = trim((string) ($assignment['technician_name'] ?? ''));
            if ($technicianName === '') {
                throw new InvalidArgumentException('External mechanic name is required.');
            }

            $normalized['technician_name'] = $technicianName;
        }

        return $normalized;
    }

    private function syncLegacyAssignedTechnician(ProductionMaintenanceWorkOrder $workOrder): void
    {
        $primaryInternal = $workOrder->assignments()
            ->where('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL)
            ->whereNotNull('technician_id')
            ->orderByDesc('assigned_at')
            ->first();

        $workOrder->update([
            'assigned_technician_id' => $primaryInternal?->technician_id,
        ]);
    }

    private function buildAssignmentsFromData(array $data): array
    {
        $assignments = $data['assignments'] ?? [];

        if (!empty($assignments)) {
            return array_values(array_filter($assignments, fn ($assignment) => is_array($assignment) && !$this->isBlankAssignment($assignment)));
        }

        $legacyTechnicianId = $data['assigned_technician_id'] ?? $data['technician_id'] ?? null;
        $legacyTechnicianName = $data['technician_name'] ?? null;
        $legacyAssignmentType = $data['assignment_type'] ?? ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL;

        if ($legacyTechnicianId || $legacyTechnicianName) {
            $technicianId = $legacyTechnicianId ? (int) $legacyTechnicianId : null;
            $user = $technicianId ? User::find($technicianId) : null;
            $technicianName = trim((string) ($legacyTechnicianName ?? ($user?->name ?? '')));

            return [[
                'assignment_type' => $legacyAssignmentType,
                'technician_id' => $technicianId,
                'technician_name' => $technicianName,
                'worked_hours' => $data['worked_hours'] ?? 0,
                'hourly_rate' => $data['hourly_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]];
        }

        if (isset($data['assignment_type']) || isset($data['technician_id']) || isset($data['technician_name'])) {
            return [[
                'assignment_type' => $data['assignment_type'] ?? ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL,
                'technician_id' => $data['technician_id'] ?? null,
                'technician_name' => $data['technician_name'] ?? null,
                'worked_hours' => $data['worked_hours'] ?? 0,
                'hourly_rate' => $data['hourly_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]];
        }

        return [];
    }

    public function hasValidAssignment(ProductionMaintenanceWorkOrder $workOrder): bool
    {
        return $workOrder->assignments()
            ->where(function ($query) {
                $query->where('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL)
                    ->whereNotNull('technician_id')
                    ->where('technician_name', '!=', '')
                    ->orWhere('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL)
                    ->where('technician_name', '!=', '');
            })
            ->exists();
    }

    private function syncAssignmentWorkedHoursForCompletion(ProductionMaintenanceWorkOrder $workOrder): void
    {
        $actualStart = $workOrder->actual_start ? Carbon::parse($workOrder->actual_start) : null;
        $actualEnd = $workOrder->actual_end ? Carbon::parse($workOrder->actual_end) : now();

        $workOrder->assignments()->each(function (ProductionMaintenanceWorkOrderAssignment $assignment) use ($actualStart, $actualEnd) {
            $assignmentAt = $assignment->assigned_at ? Carbon::parse($assignment->assigned_at) : $actualEnd;
            $referenceStart = $actualStart && $assignmentAt->lt($actualStart) ? $actualStart : $assignmentAt;
            $seconds = max(0, $actualEnd->diffInSeconds($referenceStart, false));
            $hours = round($seconds / 3600, 2);

            $assignment->update([
                'worked_hours' => $hours,
            ]);
        });
    }

    public function createAssignmentsForWorkOrder(ProductionMaintenanceWorkOrder $workOrder, array $assignments, ?int $userId = null): void
    {
        if (empty($assignments)) {
            return;
        }

        foreach ($assignments as $assignment) {
            if (!is_array($assignment) || $this->isBlankAssignment($assignment)) {
                continue;
            }

            $normalized = $this->normalizeAssignmentInput($assignment);

            $record = ProductionMaintenanceWorkOrderAssignment::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'technician_id' => $normalized['technician_id'],
                'technician_name' => $normalized['technician_name'],
                'assignment_type' => $normalized['assignment_type'],
                'assigned_at' => $normalized['assigned_at'],
                'expected_work_hours' => $normalized['expected_work_hours'],
                'worked_hours' => $normalized['worked_hours'],
                'hourly_rate' => $normalized['hourly_rate'],
                'notes' => $normalized['notes'],
            ]);

            $this->logService->recordAssignmentCreated($workOrder, [
                'assignment_id' => $record->id,
                'assignment_type' => $record->assignment_type,
                'technician_id' => $record->technician_id,
                'technician_name' => $record->technician_name,
                'worked_hours' => $record->worked_hours,
                'hourly_rate' => $record->hourly_rate,
                'notes' => $record->notes,
            ], $userId);
        }

        $this->syncLegacyAssignedTechnician($workOrder);
    }

    public function addAssignment(int $workOrderId, int $tenantId, array $data, ?int $userId = null): ProductionMaintenanceWorkOrderAssignment
    {
        $workOrder = $this->repository->findWorkOrderForLock($workOrderId, $tenantId);
        if (!$workOrder) {
            throw new InvalidArgumentException("Work Order #{$workOrderId} not found.");
        }

        if (in_array($workOrder->status, [ProductionMaintenanceWorkOrder::STATUS_COMPLETED, ProductionMaintenanceWorkOrder::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('Assignments cannot be added after a work order is completed or cancelled.');
        }

        if ($this->isBlankAssignment($data)) {
            throw new InvalidArgumentException('Assignment payload cannot be empty.');
        }

        $normalized = $this->normalizeAssignmentInput($data);

        $assignment = ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id' => $tenantId,
            'work_order_id' => $workOrder->id,
            'technician_id' => $normalized['technician_id'],
            'technician_name' => $normalized['technician_name'],
            'assignment_type' => $normalized['assignment_type'],
            'assigned_at' => $normalized['assigned_at'],
            'expected_work_hours' => $normalized['expected_work_hours'],
            'worked_hours' => $normalized['worked_hours'],
            'hourly_rate' => $normalized['hourly_rate'],
            'notes' => $normalized['notes'],
        ]);

        $this->logService->recordAssignmentCreated($workOrder, [
            'assignment_id' => $assignment->id,
            'assignment_type' => $assignment->assignment_type,
            'technician_id' => $assignment->technician_id,
            'technician_name' => $assignment->technician_name,
            'worked_hours' => $assignment->worked_hours,
            'hourly_rate' => $assignment->hourly_rate,
            'notes' => $assignment->notes,
        ], $userId);

        $this->syncLegacyAssignedTechnician($workOrder);

        return $assignment;
    }

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

            $this->logService->recordWorkOrderCreated($wo, $userId);

            $assignments = $this->buildAssignmentsFromData($data);

            if (!empty($assignments)) {
                $this->createAssignmentsForWorkOrder($wo, $assignments, $userId);
            }

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Created',
                'title'        => 'Maintenance WO Created',
                'description'  => "Maintenance Work Order [{$wo->work_order_number}] created for machine #{$wo->machine_id}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime', 'assignments.technician']);
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

            if (in_array($wo->status, [ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS, ProductionMaintenanceWorkOrder::STATUS_COMPLETED, ProductionMaintenanceWorkOrder::STATUS_CANCELLED], true)) {
                throw new InvalidArgumentException('Scheduling and rescheduling are not allowed once the work order has started, been completed, or been cancelled.');
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

            $previousStatus = $wo->status;
            $wo->planned_start          = $start;
            $wo->planned_end            = $end;
            $wo->assigned_technician_id = $technicianId ?: $wo->assigned_technician_id;
            $wo->status                 = ProductionMaintenanceWorkOrder::STATUS_SCHEDULED;
            $wo->save();

            if ($technicianId && !$wo->assignments()->where('technician_id', $technicianId)->exists()) {
                $user = User::find($technicianId);
                $this->addAssignment($wo->id, $tenantId, [
                    'assignment_type' => ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL,
                    'technician_id' => $technicianId,
                    'technician_name' => $user?->name ?? '',
                    'assigned_at' => now(),
                ], $userId);
            }

            if ($previousStatus === ProductionMaintenanceWorkOrder::STATUS_SCHEDULED) {
                $this->logService->recordWorkOrderRescheduled($wo, $userId, [
                    'previous_status' => $previousStatus,
                    'planned_start' => $start->toDateTimeString(),
                    'planned_end' => $end->toDateTimeString(),
                ]);
            } else {
                $this->logService->recordWorkOrderScheduled($wo, $userId, [
                    'planned_start' => $start->toDateTimeString(),
                    'planned_end' => $end->toDateTimeString(),
                ]);
            }

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Scheduled',
                'title'        => 'Maintenance WO Scheduled',
                'description'  => "Work Order [{$wo->work_order_number}] scheduled from {$start->toDateTimeString()} to {$end->toDateTimeString()}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            app(ProductionNotificationService::class)->notifyMaintenanceScheduled($wo);

            return $wo->fresh(['machine', 'technician', 'downtime', 'assignments.technician']);
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

            if (!$this->hasValidAssignment($wo)) {
                throw new InvalidArgumentException('At least one valid assignment must exist before the work order can start.');
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

            if ($wo->downtime_id) {
                $this->logService->recordMachineDowntimeStarted($wo, $wo->downtime_id, $userId, [
                    'category' => $category,
                    'machine_name' => $machine->name,
                    'reason' => "Maintenance Work Order In Progress: {$wo->work_order_number}",
                ]);
            }

            $this->logService->recordMaintenanceStarted($wo, $userId, [
                'category' => $category,
                'machine_name' => $machine->name,
            ]);

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

            $downtime = $this->downtimeService->startDowntime(
                $tenantId,
                $machineId,
                'Breakdown',
                $reason,
                $userId
            );

            $machine->update([
                'status'             => Machine::STATUS_INACTIVE,
                'maintenance_status' => 'breakdown',
                'current_state'      => 'Breakdown',
                'current_state_reason' => $reason,
            ]);

            $woNumber = $this->codeService->generateWorkOrderNumber($tenantId);

            $wo = $this->repository->createWorkOrder([
                'tenant_id'           => $tenantId,
                'work_order_number'   => $woNumber,
                'machine_id'          => $machineId,
                'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
                'priority'            => $priority,
                'problem_description' => $reason,
                'downtime_id'         => $downtime->id,
                'status'              => ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                'created_by'          => $userId,
            ]);

            $wo->save();

            $this->logService->recordWorkOrderCreated($wo, $userId);
            $this->logService->recordMachineDowntimeStarted($wo, $downtime->id, $userId, [
                'reason' => $reason,
                'priority' => $priority,
                'category' => 'Breakdown',
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $machineId,
                'event_type'   => 'Machine Breakdown Reported',
                'title'        => 'Machine Breakdown Reported',
                'description'  => "Breakdown reported for machine [{$machine->name}]. Reason: {$reason}. Work order [{$wo->work_order_number}] created in draft state with open downtime #{$downtime->id}.",
                'severity'     => 'danger',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            app(ProductionNotificationService::class)->notifyMachineBreakdown($machine, $reason, $wo);

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
        ?string $mechanicType = null,
        ?float $externalMechanicCost = 0.00,
        ?float $internalMechanicCost = 0.00,
        ?array $checklistJson = null,
        bool $scrapMachine = false,
        float $scrapValue = 0.00,
        ?string $decisionNote = null
    ): ProductionMaintenanceWorkOrder {
        return DB::transaction(function () use ($id, $tenantId, $userId, $workPerformed, $laborHours, $mechanicType, $externalMechanicCost, $internalMechanicCost, $checklistJson, $scrapMachine, $scrapValue, $decisionNote) {
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

            $repairHours = max(0.0, (float) $laborHours);
            $mechanicType = $mechanicType ?: ProductionMaintenanceWorkOrder::MECHANIC_TYPE_INHOUSE;
            $externalRepairCost = (float) ($externalMechanicCost ?? 0.00);
            $internalRepairCost = (float) ($internalMechanicCost ?? 0.00);

            // Sum issued spare parts cost. Inventory consumption is tracked separately; this is the cost that remains in the WO summary.
            $sparesCost = (float) ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $wo->id)
                ->sum('total_cost');

            $repairCost = round($externalRepairCost + $internalRepairCost + $sparesCost, 2);
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
                'actual_end'             => $now,
                'work_performed'         => $workPerformed ?: $wo->work_performed,
                'checklist_json'         => $checklistJson ?: $wo->checklist_json,
                'labor_hours'            => $laborHours,
                'repair_hours'           => $repairHours,
                'labor_cost_rate'        => 0.00,
                'labor_cost'             => 0.00,
                'repair_cost'            => $repairCost,
                'mechanic_type'          => $mechanicType,
                'external_mechanic_cost' => $externalRepairCost,
                'internal_mechanic_cost' => $internalRepairCost,
                'spare_parts_cost'       => $sparesCost,
                'scrap_machine'          => $scrapMachine,
                'scrap_value'            => $scrapMachine ? $scrapValue : 0.00,
                'decision_note'          => $decisionNote ?: ($scrapMachine ? 'Machine scrapped instead of repaired.' : null),
                'total_cost'             => $repairCost,
                'status'                 => ProductionMaintenanceWorkOrder::STATUS_COMPLETED,
                'completed_by'           => $userId,
            ]);

            $this->syncAssignmentWorkedHoursForCompletion($wo);

            $this->logService->recordWorkOrderCompleted($wo, $userId, [
                'repair_hours' => $repairHours,
                'mechanic_type' => $mechanicType,
                'external_mechanic_cost' => $externalRepairCost,
                'internal_mechanic_cost' => $internalRepairCost,
                'spare_parts_cost' => $sparesCost,
                'scrap_machine' => $scrapMachine,
                'scrap_value' => $scrapValue,
                'work_performed' => $workPerformed ?: 'N/A',
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Maintenance Completed',
                'title'        => 'Maintenance Completed',
                'description'  => "Work Order [{$wo->work_order_number}] completed for machine [{$machine->name}]. Repair cost: \${$repairCost}." . ($scrapMachine ? ' Machine marked for scrap.' : ' Machine restored to active.'),
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

            $this->logService->recordWorkOrderCancelled($wo, $userId, [
                'reason' => $reason ?: 'Cancelled by user',
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
