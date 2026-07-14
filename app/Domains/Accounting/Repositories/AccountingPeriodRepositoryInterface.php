<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Collection;

interface AccountingPeriodRepositoryInterface
{
    public function getForFiscalYear(int $fiscalYearId): Collection;

    public function find(int $id): ?AccountingPeriod;

    public function findByDate(\DateTimeInterface $date): ?AccountingPeriod;

    public function create(array $data): AccountingPeriod;

    public function update(int $id, array $data): AccountingPeriod;
}
