<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CountdownCalculationEngine
{
    public function __construct(
        private readonly WorkCenterCalculationService $workCenterCalculationService,
        private readonly ?WorkCenterActivityLogger $activityLogger = null,
    ) {}

    /**
     * Check if a countdown timer is currently active for this Work Center.
     */
    public function isCountdownActive(WorkCenter $workCenter): bool
    {
        return !empty($workCenter->calculation_countdown_end_at)
            && now()->lt($workCenter->calculation_countdown_end_at);
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
     * Note: Activity logs are strictly reserved for completed calculation snapshots,
     * so intermediate countdown/queuing events are NOT written to the audit log.
     */
    public function triggerCountdown(WorkCenter $workCenter, ?int $sectionId = null): void
    {
        $pending = is_array($workCenter->pending_calculation_section_ids)
            ? $workCenter->pending_calculation_section_ids
            : [];

        // Allow active, inactive, and soft-deleted sections belonging to this work center to be tracked
        $validSectionIds = WorkCenterSection::withTrashed()
            ->where('work_center_id', $workCenter->id)
            ->where('tenant_id', $workCenter->tenant_id)
            ->pluck('id')
            ->map(fn($val) => (int) $val)
            ->all();

        $pending = array_values(array_intersect($pending, $validSectionIds));

        if ($sectionId && in_array((int) $sectionId, $validSectionIds, true) && !in_array((int) $sectionId, $pending, true)) {
            $pending[] = (int) $sectionId;
        }

        if ($this->isCountdownActive($workCenter)) {
            // Timer already running: append section to batch without resetting timer
            $workCenter->pending_calculation_section_ids = array_values(array_unique($pending));
            $workCenter->save();
        } else {
            // Arm new countdown
            $durationMinutes = max(1, (int) ($workCenter->calculation_countdown_minutes ?: 10));
            $endAt = now()->addMinutes($durationMinutes);

            $workCenter->calculation_countdown_end_at = $endAt;
            $workCenter->pending_calculation_section_ids = array_values(array_unique($pending));
            $workCenter->save();
        }
    }

    /**
     * Helper triggered when a section entity (e.g. manual batch) is modified.
     */
    public function onSectionModified(WorkCenterSection $section, string $event, ?int $entityId = null): void
    {
        $workCenter = $section->workCenter;
        if ($workCenter) {
            $this->triggerCountdown($workCenter, $section->id);
        }
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

        $workCenter->calculation_countdown_minutes = $minutes;
        $workCenter->save();

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
