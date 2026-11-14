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
     * Log a Work Center or Section calculation snapshot event.
     */
    public function log(
        int $tenantId,
        int $workCenterId,
        ?int $sectionId,
        ?int $userId,
        string $calculationType,
        ?int $snapshotId,
        ?string $batchId,
        string $description,
        ?int $snapshotNumber = null
    ): WorkCenterActivityLog {
        return WorkCenterActivityLog::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $workCenterId,
            'section_id'       => $sectionId,
            'user_id'          => $userId ?: (Auth::check() ? Auth::id() : null),
            'calculation_type' => $calculationType,
            'snapshot_id'      => $snapshotId,
            'snapshot_number'  => $snapshotNumber,
            'batch_id'         => $batchId,
            'description'      => $description,
        ]);
    }

    /**
     * Log Section calculation completion snapshot.
     */
    public function logSectionCalculationCompleted(
        WorkCenterSectionCalculation $calculation,
        ?int $userId = null
    ): WorkCenterActivityLog {
        $section = $calculation->section;
        $sectionName = $section?->name ?? "Section #{$calculation->section_id}";
        $sectionCode = $section?->code ?? "SEC-{$calculation->section_id}";
        $snapNum = (int) ($calculation->snapshot_number ?: $calculation->id);

        $description = sprintf(
            '%s [%s] snapshot #%d calculated',
            $sectionName,
            $sectionCode,
            $snapNum
        );

        return $this->log(
            tenantId: (int) $calculation->tenant_id,
            workCenterId: (int) ($calculation->section?->work_center_id ?? $calculation->workCenterCalculation?->work_center_id ?? 0),
            sectionId: (int) $calculation->section_id,
            userId: $userId,
            calculationType: 'section',
            snapshotId: (int) $calculation->id,
            batchId: $calculation->batch_id,
            description: $description,
            snapshotNumber: $snapNum
        );
    }

    /**
     * Log Work Center calculation completion snapshot.
     */
    public function logWorkCenterCalculationCompleted(
        WorkCenterCalculation $calculation,
        ?int $userId = null
    ): WorkCenterActivityLog {
        $workCenter = $calculation->workCenter;
        $wcName = $workCenter?->name ?? "Work Center #{$calculation->work_center_id}";
        $wcCode = $workCenter?->code ?? "WC-{$calculation->work_center_id}";
        $snapNum = (int) ($calculation->snapshot_number ?: $calculation->id);

        $description = sprintf(
            '%s [%s] snapshot #%d calculated',
            $wcName,
            $wcCode,
            $snapNum
        );

        return $this->log(
            tenantId: (int) $calculation->tenant_id,
            workCenterId: (int) $calculation->work_center_id,
            sectionId: null,
            userId: $userId,
            calculationType: 'work_center',
            snapshotId: (int) $calculation->id,
            batchId: $calculation->batch_id,
            description: $description,
            snapshotNumber: $snapNum
        );
    }

    /**
     * Log generic activity or resource change.
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
            calculationType: str_contains(strtolower($eventType), 'section') ? 'section' : 'work_center',
            snapshotId: $entityId,
            batchId: $properties['batch_id'] ?? null,
            description: $description
        );
    }
}
