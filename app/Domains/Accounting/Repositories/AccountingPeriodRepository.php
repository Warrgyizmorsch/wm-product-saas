<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Collection;

class AccountingPeriodRepository implements AccountingPeriodRepositoryInterface
{
    public function getForFiscalYear(int $fiscalYearId): Collection
    {
        return AccountingPeriod::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->orderBy('start_date')
            ->get();
    }

    public function find(int $id): ?AccountingPeriod
    {
        return AccountingPeriod::find($id);
    }

    public function findByDate(\DateTimeInterface $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function create(array $data): AccountingPeriod
    {
        return AccountingPeriod::create($data);
    }

    public function update(int $id, array $data): AccountingPeriod
    {
        $period = AccountingPeriod::findOrFail($id);
        $period->update($data);

        return $period->fresh();
    }
}
