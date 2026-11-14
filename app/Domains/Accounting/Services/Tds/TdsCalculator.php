<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Models\TdsSection;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Support\Carbon;

/**
 * Works out the TDS on a vendor bill the way Tally does:
 *
 *  - Threshold. A section limits either each payment (single_threshold), the
 *    year's total per vendor (aggregate_threshold, April-March), or each
 *    month's total (rent). Below it nothing is deducted, but the bill is still
 *    recorded so the running total is right.
 *  - Catch-up. When a vendor's yearly total first crosses the aggregate limit,
 *    TDS is also deducted on the earlier untaxed bills of the year (194C).
 *    Sections marked deduct_on_excess_only (194Q) tax just the part above it.
 *  - Rate. A valid section 197 lower-deduction certificate wins; otherwise a
 *    vendor without a PAN pays the 206AA rate (20%, or the normal rate if
 *    higher); otherwise the section's rate for the deductee type.
 *  - TDS is rounded to the nearest rupee (section 288B).
 */
class TdsCalculator implements TdsCalculatorInterface
{
    public function forBill(Vendor $vendor, float $base, \DateTimeInterface|string $date, ?int $sectionId = null, ?int $excludeBillId = null): ?TdsResult
    {
        if ($sectionId === null && ! $vendor->tds_applicable) {
            return null;
        }

        $section = TdsSection::query()->withoutGlobalScopes()
            ->where('tenant_id', $vendor->tenant_id)
            ->whereKey($sectionId ?? $vendor->tds_section_id)
            ->first();

        $base = round($base, 2);

        if ($section === null || ! $section->is_active || $section->isSalary() || $base <= 0) {
            return null;
        }

        $date = Carbon::parse($date);
        $prior = $this->priorDeductions($vendor, $section, $date, $excludeBillId);
        [$rate, $reason] = $this->rate($vendor, $section, $date, $base, $prior);

        [$taxable, $catchup] = $this->taxableBase($section, $date, $base, $prior);

        if ($taxable <= 0) {
            return new TdsResult($section->id, $base, 0.0, $rate, 0.0, TdsDeduction::REASON_BELOW_THRESHOLD);
        }

        return new TdsResult($section->id, $base, $catchup, $rate, round(($base + $catchup) * $rate / 100), $reason);
    }

    /**
     * @return array{0: float, 1: float} [base taxed on this bill, catch-up base]
     *  — catch-up is negative for an excess-only section, so that
     *  (base + catchup) is the part above the limit.
     */
    private function taxableBase(TdsSection $section, Carbon $date, float $base, \Illuminate\Support\Collection $prior): array
    {
        $single = $section->single_threshold;
        $aggregate = $section->aggregate_threshold;

        if ($section->threshold_period === TdsSection::PERIOD_PER_TRANSACTION) {
            return [$single === null || $base > $single ? $base : 0.0, 0.0];
        }

        if ($section->threshold_period === TdsSection::PERIOD_MONTHLY) {
            $monthBase = $prior->filter(fn (TdsDeduction $d) => $d->deduction_date->isSameMonth($date))->sum('base_amount');

            return [$aggregate === null || $monthBase + $base > $aggregate ? $base : 0.0, 0.0];
        }

        // Annual: once the vendor has been taxed this year, every later bill is.
        $crossed = $prior->contains(fn (TdsDeduction $d) => $d->reason !== TdsDeduction::REASON_BELOW_THRESHOLD);
        $priorBase = (float) $prior->sum('base_amount');
        $aggregateCrossed = $aggregate !== null && $priorBase + $base > $aggregate;
        $singleCrossed = $single !== null && $base > $single;

        if ($crossed || ($single === null && $aggregate === null)) {
            return [$base, 0.0];
        }

        if ($section->deduct_on_excess_only) {
            $excess = $aggregateCrossed ? round($priorBase + $base - $aggregate, 2) : 0.0;

            return [$excess, $excess > 0 ? round($excess - $base, 2) : 0.0];
        }

        if ($aggregateCrossed) {
            $untaxed = (float) $prior->where('reason', TdsDeduction::REASON_BELOW_THRESHOLD)->sum('base_amount');

            return [$base, round($untaxed, 2)];
        }

        return [$singleCrossed ? $base : 0.0, 0.0];
    }

    /** @return array{0: float, 1: string} */
    private function rate(Vendor $vendor, TdsSection $section, Carbon $date, float $base, \Illuminate\Support\Collection $prior): array
    {
        $normal = $section->rateFor($vendor->deductee_type);

        if ($this->hasValidCertificate($vendor, $date, $base, $prior)) {
            return [(float) $vendor->ldc_rate, TdsDeduction::REASON_LOWER_CERT];
        }

        if (blank($vendor->pan)) {
            return [max($section->rate_no_pan, $normal), TdsDeduction::REASON_NO_PAN];
        }

        return [$normal, TdsDeduction::REASON_NORMAL];
    }

    private function hasValidCertificate(Vendor $vendor, Carbon $date, float $base, \Illuminate\Support\Collection $prior): bool
    {
        if (blank($vendor->ldc_number) || $vendor->ldc_rate === null || $vendor->ldc_valid_from === null || $vendor->ldc_valid_to === null) {
            return false;
        }

        if ($date->lt(Carbon::parse($vendor->ldc_valid_from)->startOfDay()) || $date->gt(Carbon::parse($vendor->ldc_valid_to)->endOfDay())) {
            return false;
        }

        if ($vendor->ldc_limit === null) {
            return true;
        }

        $used = (float) $prior->where('reason', TdsDeduction::REASON_LOWER_CERT)->sum('base_amount');

        return $used + $base <= (float) $vendor->ldc_limit;
    }

    /** This vendor's live register rows for the section in the bill's financial year. */
    private function priorDeductions(Vendor $vendor, TdsSection $section, Carbon $date, ?int $excludeBillId): \Illuminate\Support\Collection
    {
        return TdsDeduction::query()->withoutGlobalScopes()
            ->where('tenant_id', $vendor->tenant_id)
            ->where('tds_section_id', $section->id)
            ->where('party_type', JournalEntry::PARTY_VENDOR)
            ->where('party_id', $vendor->id)
            ->where('fiscal_year', TdsDeduction::fiscalYearFor($date))
            ->live()
            ->when($excludeBillId, fn ($q) => $q->where(fn ($w) => $w->where('source_type', '!=', TdsDeduction::SOURCE_VENDOR_BILL)->orWhere('source_id', '!=', $excludeBillId)))
            ->get(['id', 'deduction_date', 'base_amount', 'tds_amount', 'reason']);
    }
}
