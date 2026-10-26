<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterActivityLog;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use Illuminate\Support\Facades\Auth;

class WorkCenterActivityLogger
{
    /**
     * Log a Work Center or Section activity event.
     */
    public function log(
        int $tenantId,
        int $workCenterId,
        ?int $sectionId,
        ?int $userId,
        string $eventType,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $properties = null
    ): WorkCenterActivityLog {
        return WorkCenterActivityLog::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $workCenterId,
            'section_id'     => $sectionId,
            'user_id'        => $userId ?: (Auth::check() ? Auth::id() : null),
            'event_type'     => $eventType,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'description'    => $description,
            'properties'     => $properties,
        ]);
    }

    /**
     * Log Section calculation completion snapshot.
     */
    public function logSectionCalculationCompleted(
        WorkCenterSectionCalculation $calculation,
        ?int $userId = null
    ): WorkCenterActivityLog {
        $sectionName = $calculation->section ? $calculation->section->name : "Section #{$calculation->section_id}";
        $description = sprintf(
            'Section calculation completed for %s: Daily Cap: %s, Daily Cost: $%s (%s)',
            $sectionName,
            number_format($calculation->daily_capacity, 2),
            number_format($calculation->total_daily_operating_cost, 2),
            $calculation->trigger_type
        );

        return $this->log(
            tenantId: (int) $calculation->tenant_id,
            workCenterId: (int) $calculation->work_center_id,
            sectionId: (int) $calculation->section_id,
            userId: $userId,
            eventType: 'section_calculation_completed',
            entityType: 'WorkCenterSectionCalculation',
            entityId: (int) $calculation->id,
            description: $description,
            properties: [
                'batch_id'                      => $calculation->batch_id,
                'trigger_type'                  => $calculation->trigger_type,
                'capacity_per_hour'             => $calculation->capacity_per_hour,
                'daily_capacity'                => $calculation->daily_capacity,
                'labor_cost_per_hour'           => $calculation->labor_cost_per_hour,
                'total_daily_labor_cost'        => $calculation->total_daily_labor_cost,
                'machine_cost_per_hour'         => $calculation->machine_cost_per_hour,
                'total_daily_machine_cost'      => $calculation->total_daily_machine_cost,
                'total_daily_operating_cost'    => $calculation->total_daily_operating_cost,
                'total_operating_cost_per_hour' => $calculation->total_operating_cost_per_hour,
                'unique_operating_hours'        => $calculation->unique_operating_hours,
            ]
        );
    }

    /**
     * Log Work Center calculation completion snapshot.
     */
    public function logWorkCenterCalculationCompleted(
        WorkCenterCalculation $calculation,
        ?int $userId = null
    ): WorkCenterActivityLog {
        $wcName = $calculation->workCenter ? $calculation->workCenter->name : "Work Center #{$calculation->work_center_id}";
        $description = sprintf(
            'Work Center rollup calculation completed for %s: Daily Cap: %s, Total Daily Cost: $%s (%s)',
            $wcName,
            number_format($calculation->daily_capacity, 2),
            number_format($calculation->total_daily_cost, 2),
            $calculation->trigger_type
        );

        return $this->log(
            tenantId: (int) $calculation->tenant_id,
            workCenterId: (int) $calculation->work_center_id,
            sectionId: null,
            userId: $userId,
            eventType: 'work_center_calculation_completed',
            entityType: 'WorkCenterCalculation',
            entityId: (int) $calculation->id,
            description: $description,
            properties: [
                'batch_id'                 => $calculation->batch_id,
                'trigger_type'             => $calculation->trigger_type,
                'impacted_section_ids'     => $calculation->impacted_section_ids,
                'capacity_per_hour'        => $calculation->capacity_per_hour,
                'daily_capacity'           => $calculation->daily_capacity,
                'labor_cost_per_hour'      => $calculation->labor_cost_per_hour,
                'total_daily_labor_cost'   => $calculation->total_daily_labor_cost,
                'machine_cost_per_hour'    => $calculation->machine_cost_per_hour,
                'total_daily_machine_cost' => $calculation->total_daily_machine_cost,
                'overhead_cost'            => $calculation->overhead_cost,
                'total_daily_cost'         => $calculation->total_daily_cost,
                'total_cost_per_hour'      => $calculation->total_cost_per_hour,
                'unique_operating_hours'   => $calculation->unique_operating_hours,
            ]
        );
    }

    /**
     * Log resource mutation (e.g. employee assigned, machine mapped, shift altered).
     */
    public function logResourceChange(
        int $tenantId,
        int $workCenterId,
        ?int $sectionId,
        string $eventType,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $properties = null,
        ?int $userId = null
    ): WorkCenterActivityLog {
        return $this->log(
            tenantId: $tenantId,
            workCenterId: $workCenterId,
            sectionId: $sectionId,
            userId: $userId,
            eventType: $eventType,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            properties: $properties
        );
    }
}
