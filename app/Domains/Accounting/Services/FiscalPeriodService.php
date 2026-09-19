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
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
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
                    'company_id' => $data['company_id'] ?? null,
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

    /**
     * The current fiscal year every tenant starts with. Called both at tenant
     * provisioning (ProvisionAccountingMasters listener) and by
     * AccountingChartOfAccountsSeeder for local/demo data. Idempotent (checks
     * for an existing fiscal year starting on the same date before creating),
     * safe to re-run.
     *
     * India: statutory fiscal year is 1 April - 31 March, NOT calendar year.
     * now()->startOfYear()/endOfYear() default to Jan-Dec and will silently
     * produce the wrong FY for every Indian tenant.
     */
    public function provisionCurrentFiscalYearIfMissing(int $tenantId, ?int $companyId = null, ?int $branchId = null): ?FiscalYear
    {
        $today = now();
        $fyStartYear = $today->month >= 4 ? $today->year : $today->year - 1;

        $startDate = now()->setDate($fyStartYear, 4, 1)->startOfDay();
        $endDate = (clone $startDate)->addYear()->subDay()->endOfDay();

        $existing = FiscalYear::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('start_date', $startDate->toDateString());

        if ($existing->exists()) {
            // Attach rows seeded before a company existed (company-scoped UI hides them).
            if ($companyId !== null) {
                $ids = (clone $existing)->whereNull('company_id')->pluck('id');
                FiscalYear::query()->withoutGlobalScopes()->whereIn('id', $ids)
                    ->update(['company_id' => $companyId, 'branch_id' => $branchId]);
                AccountingPeriod::query()->withoutGlobalScopes()->whereIn('fiscal_year_id', $ids)
                    ->whereNull('company_id')->update(['company_id' => $companyId]);
            }

            return null;
        }

        return $this->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . $fyStartYear . '-' . ($fyStartYear + 1),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ]);
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
