<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExchangeRateRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?ExchangeRate;

    public function create(array $data): ExchangeRate;

    public function update(int $id, array $data): ExchangeRate;

    public function delete(int $id): bool;

    /**
     * The most recent rate for the exact from→to pair whose effective_date is on or
     * before $date. Does not try the inverse pair — that is CurrencyService's job.
     */
    public function latestOnOrBefore(int $tenantId, string $fromCurrency, string $toCurrency, \DateTimeInterface $date): ?ExchangeRate;
}
