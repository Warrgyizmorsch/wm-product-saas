<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Repositories\AccountingPeriodRepositoryInterface;
use App\Domains\Accounting\Repositories\FiscalYearRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FiscalPeriodService
{
    public function __construct(
        private readonly FiscalYearRepositoryInterface $fiscalYears,
        private readonly AccountingPeriodRepositoryInterface $periods,
    ) {
    }

    /**
     * Create a fiscal year and split it into monthly accounting periods.
     * This is the normal onboarding path — a tenant rarely needs
     * non-monthly periods for a v1 ledger.
     */
    public function createFiscalYearWithMonthlyPeriods(array $data): FiscalYear
    {
        return DB::transaction(function () use ($data) {
            $fiscalYear = $this->fiscalYears->create([
                'tenant_id' => $data['tenant_id'] ?? tenant_id(),
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => FiscalYear::STATUS_OPEN,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $cursor = Carbon::parse($fiscalYear->start_date);
            $end = Carbon::parse($fiscalYear->end_date);

            while ($cursor->lte($end)) {
                $periodStart = $cursor->copy()->startOfMonth()->max($fiscalYear->start_date);
                $periodEnd = $cursor->copy()->endOfMonth()->min($fiscalYear->end_date);

                $this->periods->create([
                    'tenant_id' => $fiscalYear->tenant_id,
                    'fiscal_year_id' => $fiscalYear->id,
                    'name' => $cursor->format('F Y'),
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'status' => AccountingPeriod::STATUS_OPEN,
                ]);

                $cursor->addMonthNoOverflow()->startOfMonth();
            }

            return $fiscalYear;
        });
    }

    public function periodForDate(\DateTimeInterface $date): ?AccountingPeriod
    {
        return $this->periods->findByDate($date);
    }

    /**
     * Resolve the open period a journal on $date must post into, or throw if
     * no period exists or the period is closed/locked. Callers (JournalService)
     * rely on this to keep postings out of closed books.
     */
    public function assertOpenPeriodForDate(\DateTimeInterface $date): AccountingPeriod
    {
        $period = $this->periodForDate($date);

        if ($period === null) {
            throw new InvalidArgumentException('No accounting period exists for the given date.');
        }

        if (!$period->acceptsPostings()) {
            throw new InvalidArgumentException("Accounting period '{$period->name}' is {$period->status} and does not accept new postings.");
        }

        return $period;
    }

    public function closePeriod(int $periodId): AccountingPeriod
    {
        return $this->periods->update($periodId, [
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function lockPeriod(int $periodId): AccountingPeriod
    {
        return $this->periods->update($periodId, [
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    public function reopenPeriod(int $periodId): AccountingPeriod
    {
        return $this->periods->update($periodId, [
            'status' => AccountingPeriod::STATUS_OPEN,
            'closed_at' => null,
        ]);
    }

    public function closeFiscalYear(int $fiscalYearId): FiscalYear
    {
        return $this->fiscalYears->update($fiscalYearId, [
            'status' => FiscalYear::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
