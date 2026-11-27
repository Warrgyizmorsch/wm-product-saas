<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * One rule for the dates of an outsourced operation (N-18 / H-02), used by the PO delivery date, the dispatch slip's
 * expected return date, the order screen, the touch UI and the planning exceptions:
 *
 *   dispatch      = the operation's scheduled start (else the order start, else today)
 *   leaves plant  = dispatch + dispatch buffer days
 *   expected back = leaves plant + vendor lead time days + return buffer days
 *
 * The lead time is taken as configured on the routing operation (0 is a valid value); there is no hidden default
 * (before: 0 / 1 / 3 / 3 / 7 days depending on the screen).
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

    public function leavesPlant(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return Carbon::parse($from ?? $this->plannedStart($op))->copy()->addDays((int) ($op->dispatch_buffer_days ?? 0));
    }

    public function expectedReturnDate(ProductionOrderOperation $op, ?CarbonInterface $from = null): CarbonInterface
    {
        return $this->leavesPlant($op, $from)
            ->addDays((int) ($op->subcontract_lead_time_days ?? 0))
            ->addDays((int) ($op->return_buffer_days ?? 0));
    }

    /** Total calendar days the vendor work takes (buffers included) */
    public function totalDays(ProductionOrderOperation $op): int
    {
        return (int) ($op->dispatch_buffer_days ?? 0) + (int) ($op->subcontract_lead_time_days ?? 0) + (int) ($op->return_buffer_days ?? 0);
    }
}
