<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * One rule for the dates of an outsourced operation (SF-1, docs/production_ui_rules_review_2026-11-27.md), used by the
 * dispatch slip's expected return date, the order screen, the touch UI, scheduling and the planning exceptions.
 * All three times are hours of calendar time (not our working calendar):
 *
 *   leaves our floor = the operation's scheduled start (else the order start, else now)
 *   at the vendor    = leaves our floor + dispatch time   (travel from our work floor to the vendor)
 *   work done        = at the vendor + processing time    (the vendor's work)
 *   back on floor    = work done + receipt time            (travel from the vendor back to our work floor)
 */
class SubcontractDates
{
    public function plannedStart(ProductionOrderOperation $op): CarbonInterface
    {
        $scheduled = $op->scheduleOperation?->planned_start;
        if ($scheduled) {
            return Carbon::parse($scheduled);
        }

        $orderStart = $op->order?->start_date;

        return $orderStart ? Carbon::parse($orderStart) : now();
    }

    /** The goods leave our floor at the operation start (the dispatch time is travel, not a wait before leaving) */
    public function leavesPlant(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return Carbon::parse($from ?? $this->plannedStart($op))->copy();
    }

    public function reachesVendor(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return $this->leavesPlant($op, $from)->addMinutes((int) round((float) ($op->vendor_dispatch_hours ?? 0) * 60));
    }

    public function workDone(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return $this->reachesVendor($op, $from)->addMinutes((int) round((float) ($op->vendor_processing_hours ?? 0) * 60));
    }

    public function expectedReturnDate(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return $this->workDone($op, $from)->addMinutes((int) round((float) ($op->vendor_receipt_hours ?? 0) * 60));
    }

    /** Total calendar hours of the vendor work (dispatch + processing + receipt) */
    public function totalHours(ProductionOrderOperation $op): float
    {
        return $op->vendorTotalHours();
    }

    /** Total in calendar days (fractions kept), for screens and checks that count days */
    public function totalDays(ProductionOrderOperation $op): float
    {
        return round($this->totalHours($op) / 24, 4);
    }

    /** "2 d 6 h" / "6 h" / "0 h" — how the times are shown on screens (SF-1: days + hours) */
    public static function formatHours(?float $hours): string
    {
        $hours = max(0.0, (float) $hours);
        $days = (int) floor($hours / 24);
        $rest = round($hours - $days * 24, 2);
        $restText = rtrim(rtrim(number_format($rest, 2, '.', ''), '0'), '.');

        if ($days === 0) {
            return $restText . ' h';
        }

        return $rest > 0 ? "{$days} d {$restText} h" : "{$days} d";
    }
}
