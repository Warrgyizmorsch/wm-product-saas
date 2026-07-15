<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

class TaxRateRepository implements TaxRateRepositoryInterface
{
    public function getAll(): Collection
    {
        return TaxRate::query()->orderBy('name')->get();
    }

    public function getActive(): Collection
    {
        return TaxRate::active()->orderBy('name')->get();
    }

    public function find(int $id): ?TaxRate
    {
        return TaxRate::find($id);
    }

    public function create(array $data): TaxRate
    {
        return TaxRate::create($data);
    }

    public function update(int $id, array $data): TaxRate
    {
        $taxRate = TaxRate::findOrFail($id);
        $taxRate->update($data);

        return $taxRate->fresh();
    }

    public function delete(int $id): bool
    {
        $taxRate = TaxRate::findOrFail($id);

        return (bool) $taxRate->delete();
    }
}
