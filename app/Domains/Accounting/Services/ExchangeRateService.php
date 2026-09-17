<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\ExchangeRateSyncSetting;
use App\Domains\Accounting\Repositories\ExchangeRateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Manual maintenance of a tenant's exchange rates and its auto-sync settings.
 * The automated feed itself lives in ExchangeRates\ExchangeRateSyncService.
 */
class ExchangeRateService
{
    public function __construct(
        private readonly ExchangeRateRepositoryInterface $rates,
    ) {
    }

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->rates->paginate($filters, $perPage);
    }

    /**
     * @param array{from_currency: string, to_currency: string, rate: float|string, effective_date: string} $data
     */
    public function create(array $data, ?int $userId = null): ExchangeRate
    {
        $tenantId = require_tenant_id();
        $data = $this->normalise($data);

        $this->assertNoDuplicate($tenantId, $data);

        return $this->rates->create($data + [
            'tenant_id' => $tenantId,
            'source' => ExchangeRate::SOURCE_MANUAL,
            'created_by' => $userId,
        ]);
    }

    /**
     * Editing a synced rate turns it into a manual one, so the next sync leaves
     * the user's figure alone.
     *
     * @param array{from_currency: string, to_currency: string, rate: float|string, effective_date: string} $data
     */
    public function update(ExchangeRate $rate, array $data, ?int $userId = null): ExchangeRate
    {
        $data = $this->normalise($data);

        $this->assertNoDuplicate($rate->tenant_id, $data, $rate->id);

        return $this->rates->update($rate->id, $data + [
            'source' => ExchangeRate::SOURCE_MANUAL,
            'created_by' => $userId ?? $rate->created_by,
        ]);
    }

    public function delete(ExchangeRate $rate): bool
    {
        return $this->rates->delete($rate->id);
    }

    public function settingsFor(int $tenantId): ExchangeRateSyncSetting
    {
        return ExchangeRateSyncSetting::withoutGlobalScopes()->firstOrNew(
            ['tenant_id' => $tenantId],
            ['is_enabled' => false, 'currencies' => []],
        );
    }

    /**
     * @param array<int, string> $currencies
     */
    public function updateSettings(int $tenantId, bool $enabled, array $currencies): ExchangeRateSyncSetting
    {
        $setting = $this->settingsFor($tenantId);

        $setting->fill([
            'tenant_id' => $tenantId,
            'is_enabled' => $enabled,
            'currencies' => array_values(array_unique(array_map('strtoupper', $currencies))),
        ])->save();

        return $setting;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        return [
            'from_currency' => strtoupper($data['from_currency']),
            'to_currency' => strtoupper($data['to_currency']),
            'rate' => (float) $data['rate'],
            'effective_date' => Carbon::parse($data['effective_date'])->toDateString(),
        ];
    }

    /**
     * Mirrors the (tenant, from, to, effective_date) unique index with a friendly
     * message instead of a database error.
     *
     * @param array<string, mixed> $data
     */
    private function assertNoDuplicate(int $tenantId, array $data, ?int $ignoreId = null): void
    {
        $exists = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('from_currency', $data['from_currency'])
            ->where('to_currency', $data['to_currency'])
            ->whereDate('effective_date', $data['effective_date'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'effective_date' => "A {$data['from_currency']} → {$data['to_currency']} rate already exists for {$data['effective_date']}. Edit that rate instead.",
            ]);
        }
    }
}
