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

    private function syncAssignmentWorkedHoursForCompletion(ProductionMaintenanceWorkOrder $workOrder, ?Carbon $completionTime = null): void
    {
        $maintStart = $workOrder->actual_start
            ? Carbon::parse($workOrder->actual_start)
            : ($workOrder->planned_start ? Carbon::parse($workOrder->planned_start) : Carbon::parse($workOrder->created_at));

        $endTime = $completionTime ?: ($workOrder->actual_end ? Carbon::parse($workOrder->actual_end) : now());

        $workOrder->assignments()->each(function (ProductionMaintenanceWorkOrderAssignment $assignment) use ($maintStart, $endTime) {
            $assignmentAt = $assignment->assigned_at ? Carbon::parse($assignment->assigned_at) : $maintStart;
            $seconds = max(0, $referenceStart->diffInSeconds($endTime, false));
            $totalMinutes = (int) round($seconds / 60);
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            $hours = (float) sprintf('%d.%02d', $h, $m);

            if (empty($assignment->worked_hours) || (float) $assignment->worked_hours <= 0.0) {
                $assignment->update([
                    'worked_hours' => $hours,
                ]);
            }
        });
    }

    public function createAssignmentsForWorkOrder(ProductionMaintenanceWorkOrder $workOrder, array $assignments, ?int $userId = null): void
    {
        if (empty($assignments)) {
            return;
        }

        $seenInternal = [];
        $seenExternal = [];

        foreach ($assignments as $assignment) {
            if (!is_array($assignment) || $this->isBlankAssignment($assignment)) {
                continue;
            }

            $normalized = $this->normalizeAssignmentInput($assignment);

            // Deduplicate internal technicians within batch
            if ($normalized['assignment_type'] === ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL && !empty($normalized['technician_id'])) {
                if (in_array($normalized['technician_id'], $seenInternal, true)) {
                    continue;
                }
                $seenInternal[] = $normalized['technician_id'];
            } elseif ($normalized['assignment_type'] === ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL && !empty($normalized['technician_name'])) {
                $extKey = strtolower(trim($normalized['technician_name']));
                if (in_array($extKey, $seenExternal, true)) {
                    continue;
                }
                $seenExternal[] = $extKey;
            }

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
                'type' => $record->assignment_type,
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

        // Enforce uniqueness for both internal and external technicians
        if ($normalized['assignment_type'] === ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL && !empty($normalized['technician_id'])) {
            $alreadyAssigned = ProductionMaintenanceWorkOrderAssignment::where('work_order_id', $workOrder->id)
                ->where('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_INTERNAL)
                ->where('technician_id', $normalized['technician_id'])
                ->exists();

            if ($alreadyAssigned) {
                $techLabel = $normalized['technician_name'] ?: "ID #{$normalized['technician_id']}";
                throw new InvalidArgumentException("Technician '{$techLabel}' is already assigned to this work order.");
            }
        } elseif ($normalized['assignment_type'] === ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL && !empty($normalized['technician_name'])) {
            $alreadyAssigned = ProductionMaintenanceWorkOrderAssignment::where('work_order_id', $workOrder->id)
                ->where('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL)
                ->whereRaw('LOWER(TRIM(technician_name)) = ?', [strtolower(trim($normalized['technician_name']))])
                ->exists();

            if ($alreadyAssigned) {
                throw new InvalidArgumentException("External technician '{$normalized['technician_name']}' is already assigned to this work order.");
            }
        }

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
            'type' => $assignment->assignment_type,
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
            $branchId = $data['branch_id'] ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $data['company_id'] ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            $data['tenant_id']  = $tenantId;
            $data['company_id'] = $companyId;
            $data['branch_id']  = $branchId;
            $data['created_by'] = $userId;

            if (empty($data['work_order_number'])) {
                $data['work_order_number'] = $this->codeService->generateWorkOrderNumber($tenantId, $branchId);
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

            $downtimeNewlyStarted = false;
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
                $downtimeNewlyStarted = true;
            }

            // Ensure machine status is under_maintenance
            $machine->update([
                'status'             => Machine::STATUS_UNDER_MAINTENANCE,
                'maintenance_status' => 'in_progress',
            ]);

            $wo->actual_start = now();
            $wo->status       = ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS;
            $wo->save();

            if ($downtimeNewlyStarted && $wo->downtime_id) {
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

            // 3. Create Breakdown Work Order
            $branchId = $machine->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $machine->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();
            $woNumber = $this->codeService->generateWorkOrderNumber($tenantId, $branchId);

            $wo = $this->repository->createWorkOrder([
                'tenant_id'           => $tenantId,
                'company_id'          => $companyId,
                'branch_id'           => $branchId,
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
        ?string $decisionNote = null,
        mixed $completedAt = null,
        float $additionalCost = 0.00,
        bool $externalPartsPurchased = false,
        ?array $assignmentsData = null
    ): ProductionMaintenanceWorkOrder {
        return DB::transaction(function () use (
            $id,
            $tenantId,
            $userId,
            $workPerformed,
            $laborHours,
            $mechanicType,
            $externalMechanicCost,
            $internalMechanicCost,
            $checklistJson,
            $scrapMachine,
            $scrapValue,
            $decisionNote,
            $completedAt,
            $additionalCost,
            $externalPartsPurchased,
            $assignmentsData
        ) {
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

            // Completion timestamp: use captured timestamp if provided, otherwise now()
            if ($completedAt) {
                $completionTime = Carbon::parse($completedAt);
                // Ensure timezone is application timezone
                $completionTime->setTimezone(config('app.timezone', 'Asia/Kolkata'));
            } else {
                $completionTime = now();
            }

            // Guard: completion time must never be earlier than work order start
            if ($wo->actual_start) {
                $startDt = Carbon::parse($wo->actual_start);
                if ($completionTime->lessThan($startDt)) {
                    $completionTime = now()->greaterThan($startDt) ? now() : $startDt->copy();
                }
            }

            // 1. Process assignments worked hours & hourly rates if provided
            if (!empty($assignmentsData) && is_array($assignmentsData)) {
                foreach ($assignmentsData as $asnInput) {
                    if (!empty($asnInput['id'])) {
                        $assignment = ProductionMaintenanceWorkOrderAssignment::where('tenant_id', $tenantId)
                            ->where('work_order_id', $wo->id)
                            ->find($asnInput['id']);
                        if ($assignment) {
                            $assignmentUpdate = [];
                            if (isset($asnInput['worked_hours'])) {
                                $assignmentUpdate['worked_hours'] = max(0.0, (float) $asnInput['worked_hours']);
                            }
                            if (isset($asnInput['hourly_rate'])) {
                                $assignmentUpdate['hourly_rate'] = max(0.0, (float) $asnInput['hourly_rate']);
                            }
                            if (!empty($assignmentUpdate)) {
                                $assignment->update($assignmentUpdate);
                            }
                        }
                    }
                }
            } else {
                $this->syncAssignmentWorkedHoursForCompletion($wo, $completionTime);
            }

            // 2. Compute mechanic costs from assignments: sum of hourly_rate * worked_hours (using hours_to_decimal)
            $assignments = $wo->assignments()->get();
            if ($assignments->isNotEmpty()) {
                $calcInternalCost = 0.0;
                $calcExternalCost = 0.0;
                $totalAssignedWorkedHours = 0.0;

                foreach ($assignments as $asn) {
                    $cost = round((float) $asn->hourly_rate * hours_to_decimal($asn->worked_hours), 2);
                    $totalAssignedWorkedHours += (float) $asn->worked_hours;
                    if ($asn->assignment_type === ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL) {
                        $calcExternalCost += $cost;
                    } else {
                        $calcInternalCost += $cost;
                    }
                }

                $sumCost = round($calcInternalCost + $calcExternalCost, 2);
                if ($sumCost > 0.0) {
                    $mechanicCost = $sumCost;
                } else {
                    $mechanicCost = round((float) ($externalMechanicCost ?? 0.00) + (float) ($internalMechanicCost ?? 0.00), 2);
                }
            } else {
                $externalRepairCost = (float) ($externalMechanicCost ?? 0.00);
                $internalRepairCost = (float) ($internalMechanicCost ?? 0.00);
                $mechanicCost = round($externalRepairCost + $internalRepairCost, 2);
            }

            // 3. Sum issued spare parts cost (keep existing logic)
            $sparesCost = (float) ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $wo->id)
                ->sum('total_cost');

            // 4. Compute total cost: mechanic_cost + spare_parts_cost + additional_cost
            $additionalExpense = max(0.0, (float) $additionalCost);
            $totalCost = round($mechanicCost + $sparesCost + $additionalExpense, 2);
            $repairCost = $totalCost;

            // 5. End associated downtime
            if ($wo->downtime_id) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)->find($wo->downtime_id);
                if ($downtime && $downtime->status !== ProductionMachineDowntime::STATUS_CLOSED) {
                    $this->downtimeService->endDowntime(
                        $tenantId,
                        $downtime->id,
                        $userId,
                        $workPerformed ?: ($scrapMachine ? 'Maintenance Completed (Machine Scrapped)' : 'Maintenance Completed'),
                        $scrapMachine ? 'Decommissioned' : 'Idle'
                    );
                }
            } else {
                $this->stateService->transitionState(
                    $tenantId,
                    $machine->id,
                    $scrapMachine ? 'Decommissioned' : 'Idle',
                    $scrapMachine ? 'Machine Scrapped during Maintenance' : 'Maintenance Completed',
                    $userId,
                    $workPerformed
                );
            }

            // 6. Machine status: Decommissioned if scrapped, Active if restored
            if ($scrapMachine) {
                $machine->update([
                    'status'                    => Machine::STATUS_DECOMMISSIONED,
                    'maintenance_status'        => 'none',
                    'last_maintenance_date'     => $completionTime->toDateString(),
                    'current_state'             => 'Decommissioned',
                    'current_state_reason'      => 'Machine Scrapped during Maintenance',
                ]);
            } else {
                $machine->update([
                    'status'                    => Machine::STATUS_ACTIVE,
                    'maintenance_status'        => 'none',
                    'last_maintenance_date'     => $completionTime->toDateString(),
                ]);

                // Update PM Schedule next due date if PM Work Order and machine is restored
                if ($wo->pm_schedule_id) {
                    $pmSchedule = $this->repository->findPmSchedule($wo->pm_schedule_id, $tenantId);
                    if ($pmSchedule) {
                        $nextDue = $this->pmScheduleService->computeNextDueDate(
                            $completionTime,
                            $pmSchedule->frequency_type,
                            $pmSchedule->frequency_value
                        );

                        $pmSchedule->update([
                            'last_completed_date' => $completionTime->toDateString(),
                            'next_due_date'       => $nextDue->toDateString(),
                        ]);

                        $machine->update([
                            'next_maintenance_due_date' => $nextDue->toDateString(),
                        ]);
                    }
                }
            }

            // 7. Update Work Order record
            $wo->update([
                'actual_end'               => $completionTime,
                'work_performed'           => $workPerformed ?: $wo->work_performed,
                'checklist_json'           => $checklistJson ?: $wo->checklist_json,
                'mechanic_cost'            => $mechanicCost,
                'spare_parts_cost'         => $sparesCost,
                'additional_cost'          => $additionalExpense,
                'external_parts_purchased' => $externalPartsPurchased,
                'was_machine_scraped'      => $scrapMachine,
                'decision_note'            => $decisionNote ?: ($scrapMachine ? 'Machine scrapped instead of repaired.' : null),
                'total_cost'               => $totalCost,
                'status'                   => ProductionMaintenanceWorkOrder::STATUS_COMPLETED,
                'completed_by'             => $userId,
            ]);

            // 8. Record logs & events
            $this->logService->recordWorkOrderCompleted($wo, $userId, [
                'mechanic_cost'            => $mechanicCost,
                'spare_parts_cost'         => $sparesCost,
                'additional_cost'          => $additionalExpense,
                'external_parts_purchased' => $externalPartsPurchased,
                'was_machine_scraped'      => $scrapMachine,
                'work_performed'           => $workPerformed ?: 'N/A',
                'completed_at'             => $completionTime->toIso8601String(),
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Maintenance Completed',
                'title'        => 'Maintenance Completed',
                'description'  => "Work Order [{$wo->work_order_number}] completed for machine [{$machine->name}]. Total cost: " . format_currency((float) $totalCost) . "." . ($scrapMachine ? ' Machine marked for scrap (Decommissioned).' : ' Machine restored to active.'),
                'severity'     => 'info',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime', 'spares.product', 'assignments']);
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

            // Determine if this work order was associated with a machine breakdown
            $downtime = null;
            if ($wo->downtime_id) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)->find($wo->downtime_id);
            }
            if (!$downtime && ($wo->type === ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN || $machine->maintenance_status === 'breakdown')) {
                $downtime = ProductionMachineDowntime::where('tenant_id', $tenantId)
                    ->where('machine_id', $wo->machine_id)
                    ->where('status', ProductionMachineDowntime::STATUS_OPEN)
                    ->latest('id')
                    ->first();
            }

            $isBreakdown = $wo->type === ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN
                || ($machine->maintenance_status === 'breakdown')
                || ($machine->current_state === 'Breakdown')
                || ($downtime && $downtime->category === 'Breakdown');

            $effectiveReason = trim((string) $reason);
            if ($isBreakdown && $effectiveReason === '') {
                throw new InvalidArgumentException("A cancellation reason is required for breakdown work orders.");
            }

            // Close downtime if open
            if ($downtime && $downtime->status !== ProductionMachineDowntime::STATUS_CLOSED) {
                $endTime = now();
                $start = $downtime->start_time ? Carbon::parse($downtime->start_time) : $endTime;
                $durationMinutes = max(0.00, round($start->diffInSeconds($endTime) / 60.0, 2));

                $downtime->update([
                    'end_time'         => $endTime,
                    'duration_minutes' => $durationMinutes,
                    'status'           => ProductionMachineDowntime::STATUS_CLOSED,
                    'remarks'          => $effectiveReason !== '' ? "Cancelled: {$effectiveReason}" : 'Work Order Cancelled',
                    'approved_by'      => $userId,
                ]);

                if (!$wo->downtime_id) {
                    $wo->downtime_id = $downtime->id;
                }
            }

            // Update work order to cancelled status and persist decision_note / work_performed
            $wo->status = ProductionMaintenanceWorkOrder::STATUS_CANCELLED;
            if ($effectiveReason !== '') {
                $wo->decision_note = $effectiveReason;
                $wo->work_performed = $wo->work_performed
                    ? $wo->work_performed . " [Cancelled: {$effectiveReason}]"
                    : "Cancelled: {$effectiveReason}";
            } else {
                $wo->work_performed = $wo->work_performed ?: 'Work Order Cancelled';
            }
            $wo->save();

            // If machine was under maintenance, inactive, or broken down for this WO, restore to active
            if (
                $machine->status === Machine::STATUS_UNDER_MAINTENANCE
                || $machine->status === Machine::STATUS_INACTIVE
                || $machine->maintenance_status === 'breakdown'
                || $isBreakdown
            ) {
                $hasOtherActiveMwo = ProductionMaintenanceWorkOrder::where('tenant_id', $tenantId)
                    ->where('machine_id', $machine->id)
                    ->where('id', '!=', $wo->id)
                    ->whereIn('status', [
                        ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                        ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
                        ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
                    ])
                    ->exists();

                if (!$hasOtherActiveMwo) {
                    $machine->update([
                        'status'               => Machine::STATUS_ACTIVE,
                        'maintenance_status'   => 'none',
                        'current_state'        => 'Idle',
                        'current_state_reason' => $effectiveReason !== '' ? "Work Order Cancelled: {$effectiveReason}" : 'Work Order Cancelled',
                    ]);
                }

                $this->stateService->transitionState(
                    $tenantId,
                    $machine->id,
                    'Idle',
                    $effectiveReason !== '' ? "Work Order Cancelled: {$effectiveReason}" : 'Work Order Cancelled',
                    $userId
                );
            }

            $this->logService->recordWorkOrderCancelled($wo, $userId, [
                'reason' => $effectiveReason ?: 'Cancelled by user',
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Work Order Cancelled',
                'title'        => 'Maintenance WO Cancelled',
                'description'  => "Work Order [{$wo->work_order_number}] cancelled." . ($effectiveReason !== '' ? " Reason: {$effectiveReason}" : ''),
                'severity'     => 'warning',
                'event_source' => 'MaintenanceWorkOrderService',
            ]);

            return $wo->fresh(['machine', 'technician', 'downtime']);
        });
    }

    /**
     * Calculate an employee technician's hourly rate derived from their HRMS salary structure,
     * returned denominated in the active or requested target currency.
     *
     * @param User|int|null $user
     * @param string|null $targetCurrency Optional target currency code (defaults to active_currency())
     * @return float Hourly rate rounded to 2 decimal places, or 0.00 if unresolvable
     */
    public function calculateTechnicianHourlyRate(User|int|null $user, ?string $targetCurrency = null): float
    {
        if (empty($user)) {
            return 0.00;
        }

        if (is_numeric($user)) {
            $user = User::find($user);
        }

        if (!$user instanceof User) {
            return 0.00;
        }

        /** @var \App\Domains\HRMS\Models\Employee|null $employee */
        $employee = $user->relationLoaded('employee') && $user->employee
            ? $user->employee
            : (class_exists(\App\Domains\HRMS\Models\Employee::class)
                ? (\App\Domains\HRMS\Models\Employee::withoutGlobalScopes()->where('user_id', $user->id)->first()
                    ?: \App\Domains\HRMS\Models\Employee::resolveForUser($user))
                : null);

        if (!$employee || empty($employee->current_salary) || (float) $employee->current_salary <= 0) {
            return 0.00;
        }

        // 1. Resolve salary structure: pay group match preferred, then direct assignment
        $structure = null;
        if ($employee->pay_group_id && class_exists(\App\Domains\HRMS\Models\SalaryStructure::class)) {
            $structure = \App\Domains\HRMS\Models\SalaryStructure::where('pay_group_id', $employee->pay_group_id)
                ->where('min_ctc', '<=', $employee->current_salary)
                ->where('max_ctc', '>=', $employee->current_salary)
                ->where('status', true)
                ->first();
        }

        if (!$structure) {
            $structure = $employee->salaryStructure;
        }

        if (!$structure || !$structure->relationLoaded('items') && !method_exists($structure, 'items')) {
            return 0.00;
        }

        // 2. Identify the BASIC salary component item
        $items = $structure->items()->with('component')->get();
        /** @var \App\Domains\HRMS\Models\SalaryStructureItem|null $basicItem */
        $basicItem = $items->first(function ($item) {
            return $item->component && (
                strtolower($item->component->code ?? '') === 'basic' ||
                ($item->component->type === 'earning' && in_array($item->calculation_type, ['fixed', 'percentage_of_ctc']))
            );
        });

        if (!$basicItem || $basicItem->value === null || (float) $basicItem->value <= 0) {
            return 0.00;
        }

        // 3. Calculate monthly basic salary based on calculation_type
        $monthlyBasic = 0.00;
        if ($basicItem->calculation_type === 'percentage_of_ctc') {
            // value represents a percentage of annual CTC (e.g. 50.00 = 50%)
            $annualBasic = ((float) $employee->current_salary * (float) $basicItem->value) / 100.0;
            $monthlyBasic = $annualBasic / 12.0;
        } elseif ($basicItem->calculation_type === 'fixed') {
            // Per HRMS PayrollCalculationService convention, fixed components are stored as annual amounts
            $monthlyBasic = (float) $basicItem->value / 12.0;
        } else {
            // Unknown or unsupported calculation type; do not guess
            return 0.00;
        }

        if ($monthlyBasic <= 0) {
            return 0.00;
        }

        // 4. Derive hourly rate using configurable standard working-hours basis
        $workingHours = max(1.0, (float) config('production.maintenance_working_hours_per_month', 208.0));
        $hourlyRateInEmpCurrency = $monthlyBasic / $workingHours;

        // 5. Currency normalization:
        // Employee salary is denominated in the company's local payroll currency (default INR)
        $employeeCurrency = $employee->company?->currency ?? 'INR';
        $baseCurrency = config('currency.base', 'USD');

        if ($employeeCurrency === $baseCurrency) {
            $rateInBase = $hourlyRateInEmpCurrency;
        } else {
            $empFxRate = (float) (config("currency.currencies.{$employeeCurrency}.rate") ?? 1.0);
            $rateInBase = $empFxRate > 0 ? ($hourlyRateInEmpCurrency / $empFxRate) : $hourlyRateInEmpCurrency;
        }

        // 6. Convert to target/active display currency
        if ($targetCurrency !== null) {
            $targetFxRate = (float) (config("currency.currencies.{$targetCurrency}.rate") ?? 1.0);
            $finalRate = $rateInBase * $targetFxRate;
        } else {
            $finalRate = function_exists('convert_from_base')
                ? convert_from_base($rateInBase)
                : $rateInBase;
        }

        return round($finalRate, 2);
    }
}

