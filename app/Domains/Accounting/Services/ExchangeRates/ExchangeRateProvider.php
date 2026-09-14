<?php

namespace App\Domains\Accounting\Services\ExchangeRates;

/**
 * A source of market exchange rates. Bound in AppServiceProvider so the feed
 * can be swapped (e.g. for a paid provider covering Gulf currencies) without
 * touching ExchangeRateSyncService.
 */
interface ExchangeRateProvider
{
    /**
     * Rates for 1 unit of $base in each of $symbols.
     *
     * The returned date is the provider's publication date, which can be earlier
     * than requested (no weekend/holiday rates) — callers must store that date,
     * not the one they asked for.
     *
     * @param array<int, string> $symbols
     * @return array{date: string, rates: array<string, float>}
     *
     * @throws ExchangeRateProviderException
     */
    public function fetch(string $base, array $symbols, ?\DateTimeInterface $date = null): array;

    /**
     * ISO codes this provider publishes.
     *
     * @return array<int, string>
     *
     * @throws ExchangeRateProviderException
     */
    public function supportedCurrencies(): array;
}
