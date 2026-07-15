<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\FiscalYear;
use Illuminate\Database\Eloquent\Collection;

interface FiscalYearRepositoryInterface
{
    public function getAll(): Collection;

    public function find(int $id): ?FiscalYear;

    public function findByDate(\DateTimeInterface $date): ?FiscalYear;

    public function create(array $data): FiscalYear;

    public function update(int $id, array $data): FiscalYear;
}
