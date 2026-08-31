<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionPmSchedule;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PmScheduleService
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly MaintenanceCodeService $codeService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a new PM Schedule.
     */
    public function createSchedule(int $tenantId, array $data): ProductionPmSchedule
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $data['tenant_id'] = $tenantId;
            $data['is_active'] = $data['is_active'] ?? true;

            if (empty($data['code'])) {
                $data['code'] = $this->codeService->generatePmScheduleCode($tenantId);
            }

            if (empty($data['next_due_date'])) {
                $baseDate = !empty($data['last_completed_date'])
                    ? Carbon::parse($data['last_completed_date'])
                    : Carbon::today();
                $data['next_due_date'] = $this->computeNextDueDate(
                    $baseDate,
                    $data['frequency_type'] ?? ProductionPmSchedule::FREQ_DAYS,
                    (int) ($data['frequency_value'] ?? 30)
                )->toDateString();
            }

            $schedule = $this->repository->createPmSchedule($data);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $schedule->machine_id,
                'event_type'   => 'PM Schedule Created',
                'title'        => 'PM Schedule Created',
                'description'  => "PM Schedule [{$schedule->code} - {$schedule->name}] created for machine #{$schedule->machine_id}.",
                'severity'     => 'info',
                'event_source' => 'PmScheduleService',
            ]);

            return $schedule;
        });
    }

    /**
     * Update an existing PM Schedule.
     */
    public function updateSchedule(int $id, int $tenantId, array $data): ProductionPmSchedule
    {
        return DB::transaction(function () use ($id, $tenantId, $data) {
            $schedule = $this->repository->findPmSchedule($id, $tenantId);
            if (!$schedule) {
                throw new InvalidArgumentException("PM Schedule #{$id} not found.");
            }

            return $this->repository->updatePmSchedule($id, $tenantId, $data);
        });
    }

    /**
     * Calculate next due date given a base date, frequency type, and frequency value.
     */
    public function computeNextDueDate(Carbon $baseDate, string $frequencyType, int $frequencyValue): Carbon
    {
        $date = $baseDate->copy();

        match ($frequencyType) {
            ProductionPmSchedule::FREQ_WEEKS  => $date->addWeeks($frequencyValue),
            ProductionPmSchedule::FREQ_MONTHS => $date->addMonths($frequencyValue),
            default                           => $date->addDays($frequencyValue),
        };

        return $date;
    }

    /**
     * Idempotently generate Maintenance Work Orders for due PM schedules.
     *
     * Prevents duplicate work orders for the same schedule and due date.
     */
    public function generateDueWorkOrders(int $tenantId, ?string $asOfDate = null): array
    {
        $targetDate = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : Carbon::today()->toDateString();
        $dueSchedules = $this->repository->getDuePmSchedules($tenantId, $targetDate);

        $generated = [];

        foreach ($dueSchedules as $schedule) {
            DB::transaction(function () use ($tenantId, $schedule, &$generated) {
                // Idempotency check: Check if an active/open WO already exists for this PM schedule
                $existingOpenWo = ProductionMaintenanceWorkOrder::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('pm_schedule_id', $schedule->id)
                    ->whereIn('status', [
                        ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                        ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
                        ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
                    ])
                    ->first();

                if ($existingOpenWo) {
                    return; // Skip: WO already exists and is active
                }

                // Generate unique WO number
                $woNumber = $this->codeService->generateWorkOrderNumber($tenantId);

                $workOrder = $this->repository->createWorkOrder([
                    'tenant_id'           => $tenantId,
                    'work_order_number'   => $woNumber,
                    'machine_id'          => $schedule->machine_id,
                    'pm_schedule_id'      => $schedule->id,
                    'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
                    'priority'            => $schedule->priority,
                    'planned_start'       => $schedule->next_due_date->startOfDay(),
                    'planned_end'         => $schedule->next_due_date->copy()->addHours((float) $schedule->estimated_duration_hours),
                    'problem_description' => "Scheduled Preventive Maintenance: {$schedule->name} ({$schedule->code})",
                    'checklist_json'      => $schedule->checklist_json,
                    'status'              => ProductionMaintenanceWorkOrder::STATUS_DRAFT,
                ]);

                $generated[] = $workOrder;

                $this->eventService->writeEvent($tenantId, [
                    'machine_id'   => $schedule->machine_id,
                    'event_type'   => 'PM Work Order Generated',
                    'title'        => 'PM Work Order Generated',
                    'description'  => "Generated PM Work Order [{$workOrder->work_order_number}] for schedule [{$schedule->code}].",
                    'severity'     => 'info',
                    'event_source' => 'PmScheduleService',
                ]);
            });
        }

        return $generated;
    }
}
