<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

interface TaxRateRepositoryInterface
{
    public function getAll(): Collection;

    public function getActive(): Collection;

    public function find(int $id): ?TaxRate;

    public function create(array $data): TaxRate;

    public function update(int $id, array $data): TaxRate;

    public function delete(int $id): bool;
}
