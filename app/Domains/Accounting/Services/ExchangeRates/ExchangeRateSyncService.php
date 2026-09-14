<?php

namespace App\Domains\Accounting\Services\ExchangeRates;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\ExchangeRateSyncSetting;
use App\Domains\Accounting\Services\CurrencyService;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pulls market rates from the configured provider into a tenant's exchange_rates.
 *
 * Rules:
 * - Rates are stored foreign → base ("1 GBP = 112.4 INR") against the tenant's
 *   currency. CurrencyService::rate() resolves the inverse.
 * - The provider's publication date is the effective date, so no weekend or
 *   holiday rows are invented.
 * - Manual rates win: a row a user entered (source=manual) is never overwritten;
 *   a previously synced row (source=api) for the same pair and date is refreshed.
 *
 * All queries pass tenant_id explicitly — this runs from the scheduler, where
 * there is no TenantContext.
 */
class ExchangeRateSyncService
{
    public function __construct(
        private readonly ExchangeRateProvider $provider,
        private readonly CurrencyService $currencies,
    ) {
    }

    /**
     * Sync every tenant that has auto-sync switched on.
     *
     * @return array<int, array<string, mixed>> result per tenant id
     */
    public function syncEnabledTenants(): array
    {
        $results = [];

        $tenantIds = ExchangeRateSyncSetting::withoutGlobalScopes()
            ->where('is_enabled', true)
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            // syncTenant() already records provider failures; this guard only stops
            // an unexpected error in one tenant from aborting the rest.
            try {
                $results[$tenantId] = $this->syncTenant((int) $tenantId);
            } catch (Throwable $e) {
                report($e);
                $results[$tenantId] = ['status' => ExchangeRateSyncSetting::STATUS_FAILED, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * @return array{status: string, message: string, inserted: int, updated: int, kept_manual: int, unsupported: array<int, string>, errors: array<int, string>}
     */
    public function syncTenant(int $tenantId): array
    {
        $setting = ExchangeRateSyncSetting::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['is_enabled' => false, 'currencies' => []],
        );

        $result = [
            'status' => ExchangeRateSyncSetting::STATUS_SUCCESS,
            'message' => '',
            'inserted' => 0,
            'updated' => 0,
            'kept_manual' => 0,
            'unsupported' => [],
            'errors' => [],
        ];

        $targets = collect($setting->currencies ?? [])->map(fn ($code) => strtoupper((string) $code))->unique()->values();

        if ($targets->isEmpty()) {
            $result['status'] = ExchangeRateSyncSetting::STATUS_SKIPPED;
            $result['message'] = 'No currencies selected to sync.';

            return $this->record($setting, $result);
        }

        try {
            $supported = $this->provider->supportedCurrencies();
        } catch (Throwable $e) {
            $result['status'] = ExchangeRateSyncSetting::STATUS_FAILED;
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'Rate provider unavailable: ' . $e->getMessage();

            return $this->record($setting, $result);
        }

        foreach ($this->baseCurrenciesFor($tenantId) as $base) {
            $symbols = $targets->reject(fn ($code) => $code === $base);

            if (! in_array($base, $supported, true)) {
                // Nothing can be fetched against an unpublished base currency.
                $result['unsupported'][] = $base;
                continue;
            }

            $unsupported = $symbols->reject(fn ($code) => in_array($code, $supported, true));
            array_push($result['unsupported'], ...$unsupported->all());

            $fetchable = $symbols->diff($unsupported)->values()->all();
            if ($fetchable === []) {
                continue;
            }

            try {
                $response = $this->provider->fetch($base, $fetchable);
            } catch (Throwable $e) {
                $result['errors'][] = "{$base}: {$e->getMessage()}";
                continue;
            }

            foreach ($response['rates'] as $code => $baseToForeign) {
                if ($baseToForeign <= 0) {
                    continue;
                }

                // Provider gives 1 BASE = x FOREIGN; store 1 FOREIGN = 1/x BASE.
                $this->upsert($tenantId, strtoupper($code), $base, round(1 / $baseToForeign, 10), $response['date'], $result);
            }
        }

        $result['unsupported'] = array_values(array_unique($result['unsupported']));
        $changed = $result['inserted'] + $result['updated'];

        if ($result['errors'] !== []) {
            $result['status'] = $changed > 0 ? ExchangeRateSyncSetting::STATUS_PARTIAL : ExchangeRateSyncSetting::STATUS_FAILED;
        }

        $result['message'] = $this->summary($result);

        return $this->record($setting, $result);
    }

    /**
     * The tenant's base currency, as a list so a future multi-base setup needs no
     * caller changes.
     *
     * @return array<int, string>
     */
    public function baseCurrenciesFor(int $tenantId): array
    {
        return [$this->currencies->baseCurrencyForTenant($tenantId)];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function upsert(int $tenantId, string $from, string $to, float $rate, string $date, array &$result): void
    {
        $effectiveDate = Carbon::parse($date)->toDateString();

        $existing = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('effective_date', $effectiveDate)
            ->first();

        if ($existing !== null && ! $existing->isSynced()) {
            $result['kept_manual']++;

            return;
        }

        if ($existing !== null) {
            $existing->update(['rate' => $rate]);
            $result['updated']++;

            return;
        }

        ExchangeRate::create([
            'tenant_id' => $tenantId,
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'effective_date' => $effectiveDate,
            'source' => ExchangeRate::SOURCE_API,
        ]);
        $result['inserted']++;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function record(ExchangeRateSyncSetting $setting, array $result): array
    {
        $setting->forceFill([
            'last_synced_at' => now(),
            'last_status' => $result['status'],
            'last_error' => $result['errors'] === [] ? null : implode("\n", $result['errors']),
            'unsupported' => $result['unsupported'],
        ])->save();

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function summary(array $result): string
    {
        $parts = ["{$result['inserted']} added", "{$result['updated']} updated"];

        if ($result['kept_manual'] > 0) {
            $parts[] = "{$result['kept_manual']} manual kept";
        }

        $message = 'Exchange rates synced: ' . implode(', ', $parts) . '.';

        if ($result['unsupported'] !== []) {
            $message .= ' Not published by provider (enter manually): ' . implode(', ', $result['unsupported']) . '.';
        }

        if ($result['errors'] !== []) {
            $message .= ' Errors: ' . implode('; ', $result['errors']);
        }

        return $message;
    }
}
