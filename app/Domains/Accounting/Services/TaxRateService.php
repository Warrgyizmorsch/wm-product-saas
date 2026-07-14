<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\TaxRate;
use App\Domains\Accounting\Repositories\TaxRateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaxRateService
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function list(): Collection
    {
        return $this->taxRates->getAll();
    }

    public function active(): Collection
    {
        return $this->taxRates->getActive();
    }

    public function create(array $data): TaxRate
    {
        return $this->taxRates->create($data);
    }

    public function update(int $id, array $data): TaxRate
    {
        return $this->taxRates->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->taxRates->delete($id);
    }

    public function calculate(int $taxRateId, float $baseAmount): float
    {
        $taxRate = $this->taxRates->find($taxRateId);

        return $taxRate === null ? 0.0 : $taxRate->amountFor($baseAmount);
    }
}
