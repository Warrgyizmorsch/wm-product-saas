<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return ExchangeRate::query()
            ->with('creator:id,name')
            ->when($filters['from_currency'] ?? null, fn ($q, $code) => $q->where('from_currency', strtoupper($code)))
            ->when($filters['to_currency'] ?? null, fn ($q, $code) => $q->where('to_currency', strtoupper($code)))
            ->when($filters['currency'] ?? null, fn ($q, $code) => $q->where(fn ($either) => $either
                ->where('from_currency', strtoupper($code))
                ->orWhere('to_currency', strtoupper($code))))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->orderByDesc('effective_date')
            ->orderBy('from_currency')
            ->paginate($perPage);
    }

    public function find(int $id): ?ExchangeRate
    {
        return ExchangeRate::find($id);
    }

    public function create(array $data): ExchangeRate
    {
        return ExchangeRate::create($data);
    }

    public function update(int $id, array $data): ExchangeRate
    {
        $rate = ExchangeRate::findOrFail($id);
        $rate->update($data);

        return $rate;
    }

    public function delete(int $id): bool
    {
        return (bool) ExchangeRate::destroy($id);
    }

    public function latestOnOrBefore(int $tenantId, string $fromCurrency, string $toCurrency, \DateTimeInterface $date): ?ExchangeRate
    {
        // Explicit tenant_id rather than relying on the global scope alone, so the
        // lookup is correct from queue workers where TenantContext is unset.
        return ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }
}
