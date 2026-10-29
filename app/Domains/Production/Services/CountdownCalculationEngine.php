<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CountdownCalculationEngine
{
    public function __construct(
        private readonly WorkCenterCalculationService $workCenterCalculationService,
        private readonly WorkCenterActivityLogger $activityLogger,
    ) {}

    /**
     * Check if a countdown timer is currently active for this Work Center.
     */
    public function isCountdownActive(WorkCenter $workCenter): bool
    {
        // TEMPORARILY COMMENTED OUT FOR TESTING: Debounce timer disabled.
        // Recalculations run on-demand via the "Recalculate" button click.
        return false;

        /*
        return !empty($workCenter->calculation_countdown_end_at)
            && now()->lt($workCenter->calculation_countdown_end_at);
        */
    }

    /**
     * Get remaining seconds on active countdown timer.
     */
    public function getRemainingSeconds(WorkCenter $workCenter): int
    {
        if (!$this->isCountdownActive($workCenter)) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($workCenter->calculation_countdown_end_at, false));
    }

    /**
     * Trigger or enqueue a Section into the Work Center's calculation countdown.
     */
    public function triggerCountdown(WorkCenter $workCenter, ?int $sectionId = null): void
    {
        // TEMPORARILY COMMENTED OUT FOR TESTING: Debounce logic disabled.
        // Recalculations are triggered on-demand via the "Recalculate" button.
        /*
        $pending = is_array($workCenter->pending_calculation_section_ids)
            ? $workCenter->pending_calculation_section_ids
            : [];

        if ($sectionId && !in_array($sectionId, $pending, true)) {
            $pending[] = $sectionId;
        }

        if ($this->isCountdownActive($workCenter)) {
            // Timer already running: append section to batch without resetting timer
            $workCenter->pending_calculation_section_ids = array_values(array_unique($pending));
            $workCenter->save();

            $this->activityLogger->log(
                tenantId: (int) $workCenter->tenant_id,
                workCenterId: (int) $workCenter->id,
                sectionId: $sectionId,
                userId: null,
                eventType: 'calculation_queued',
                entityType: 'WorkCenter',
                entityId: (int) $workCenter->id,
                description: "Section #{$sectionId} added to active calculation countdown queue."
            );
        } else {
            // Arm new countdown
            $durationMinutes = max(1, (int) ($workCenter->calculation_countdown_minutes ?: 10));
            $endAt = now()->addMinutes($durationMinutes);

            $workCenter->calculation_countdown_end_at = $endAt;
            $workCenter->pending_calculation_section_ids = array_values(array_unique($pending));
            $workCenter->save();

            $this->activityLogger->log(
                tenantId: (int) $workCenter->tenant_id,
                workCenterId: (int) $workCenter->id,
                sectionId: $sectionId,
                userId: null,
                eventType: 'countdown_started',
                entityType: 'WorkCenter',
                entityId: (int) $workCenter->id,
                description: "Calculation countdown started ({$durationMinutes} mins, expires at {$endAt->toTimeString()}).",
                properties: [
                    'duration_minutes' => $durationMinutes,
                    'countdown_end_at' => $endAt->toIso8601String(),
                    'pending_sections' => $pending,
                ]
            );
        }
        */
    }

    /**
     * Update countdown duration configuration.
     * Locked out if a countdown timer is currently active.
     */
    public function updateCountdownDuration(WorkCenter $workCenter, int $minutes, ?int $userId = null): WorkCenter
    {
        if ($this->isCountdownActive($workCenter)) {
            throw new InvalidArgumentException('Cannot change countdown duration while a countdown timer is actively ticking.');
        }

        if ($minutes < 1 || $minutes > 1440) {
            throw new InvalidArgumentException('Countdown duration must be between 1 and 1440 minutes.');
        }

        $oldMinutes = $workCenter->calculation_countdown_minutes;
        $workCenter->calculation_countdown_minutes = $minutes;
        $workCenter->save();

        $this->activityLogger->log(
            tenantId: (int) $workCenter->tenant_id,
            workCenterId: (int) $workCenter->id,
            sectionId: null,
            userId: $userId ?? auth()->id(),
            eventType: 'countdown_duration_updated',
            entityType: 'WorkCenter',
            entityId: (int) $workCenter->id,
            description: "Updated calculation countdown duration from {$oldMinutes}m to {$minutes}m.",
            properties: [
                'previous_minutes' => $oldMinutes,
                'new_minutes'      => $minutes,
            ]
        );

        return $workCenter;
    }

    /**
     * Recalculate pending sections and roll up Work Center immediately.
     * Can be invoked manually (Recalculate Now bypass) or via expired countdown timer.
     */
    public function recalculateNow(WorkCenter $workCenter, string $triggerType = 'manual'): WorkCenterCalculation
    {
        // Mutex Concurrency Lock
        $workCenter->is_calculation_running = true;
        $workCenter->save();

        try {
            $pendingSectionIds = is_array($workCenter->pending_calculation_section_ids)
                ? $workCenter->pending_calculation_section_ids
                : [];

            $calculation = $this->workCenterCalculationService->calculateAndSave(
                workCenter: $workCenter,
                triggerType: $triggerType,
                impactedSectionIds: $pendingSectionIds
            );

            // Reset countdown state
            $workCenter->calculation_countdown_end_at = null;
            $workCenter->pending_calculation_section_ids = [];
            $workCenter->is_calculation_running = false;
            $workCenter->save();

            return $calculation;
        } catch (\Throwable $e) {
            $workCenter->is_calculation_running = false;
            $workCenter->save();
            throw $e;
        }
    }

    /**
     * Process all expired countdowns across tenants.
     */
    public function processExpiredCountdowns(): int
    {
        $expiredWorkCenters = WorkCenter::withoutGlobalScopes()
            ->whereNotNull('calculation_countdown_end_at')
            ->where('calculation_countdown_end_at', '<=', now())
            ->where('is_calculation_running', false)
            ->get();

        $count = 0;
        foreach ($expiredWorkCenters as $wc) {
            $this->recalculateNow($wc, triggerType: 'countdown_timer');
            $count++;
        }

        return $count;
    }
}
