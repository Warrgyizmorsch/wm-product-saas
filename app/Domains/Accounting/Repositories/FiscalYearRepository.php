<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\FiscalYear;
use Illuminate\Database\Eloquent\Collection;

class FiscalYearRepository implements FiscalYearRepositoryInterface
{
    public function getAll(): Collection
    {
        return FiscalYear::query()->orderByDesc('start_date')->get();
    }

    public function find(int $id): ?FiscalYear
    {
        return FiscalYear::find($id);
    }

    public function findByDate(\DateTimeInterface $date): ?FiscalYear
    {
        return FiscalYear::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function create(array $data): FiscalYear
    {
        return FiscalYear::create($data);
    }

    public function update(int $id, array $data): FiscalYear
    {
        $fiscalYear = FiscalYear::findOrFail($id);
        $fiscalYear->update($data);

        return $fiscalYear->fresh();
    }
}
