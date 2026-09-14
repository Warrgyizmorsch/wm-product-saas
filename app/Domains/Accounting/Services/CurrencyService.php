<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Repositories\ExchangeRateRepositoryInterface;
use App\Domains\HRMS\Models\Company;
use App\Models\Currency;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Base-currency resolution and exchange-rate conversion for the ledger.
 *
 * The base (functional) currency is per company — companies.currency — so a tenant
 * can run legal entities that report in different currencies. This is separate
 * from the session display currency in app/helpers.php (active_currency(),
 * convert_to_base()), which must never be applied to journal amounts.
 */
class CurrencyService
{
    /** Matches the default TenantService and the companies.currency column use. */
    public const FALLBACK_BASE_CURRENCY = 'INR';

    /** @var array<string, int> */
    private array $decimalsCache = [];

    public function __construct(
        private readonly ExchangeRateRepositoryInterface $rates,
    ) {
    }

    public function baseCurrencyFor(?int $companyId = null): string
    {
        $companyId ??= company_id();

        if ($companyId !== null) {
            // withoutGlobalScopes: callable from queue workers with no TenantContext.
            $code = Company::withoutGlobalScopes()->whereKey($companyId)->value('currency');

            if ($code) {
                return strtoupper($code);
            }
        }

        $tenantCode = tenant()?->settings['currency'] ?? null;

        return $tenantCode ? strtoupper($tenantCode) : self::FALLBACK_BASE_CURRENCY;
    }

    /**
     * Minor-unit scale of a currency: 2 for INR/USD, 0 for JPY, 3 for KWD.
     */
    public function decimalsFor(string $currencyCode): int
    {
        $code = strtoupper($currencyCode);

        if (! array_key_exists($code, $this->decimalsCache)) {
            $decimals = Currency::query()->where('code', $code)->value('decimals');

            if ($decimals === null) {
                throw new InvalidArgumentException("Unknown currency: {$code}.");
            }

            $this->decimalsCache[$code] = (int) $decimals;
        }

        return $this->decimalsCache[$code];
    }

    /**
     * Units of $toCurrency for 1 unit of $fromCurrency on $date.
     *
     * Uses the latest rate effective on or before $date for the direct pair, falling
     * back to the inverse of the reverse pair, so a tenant need only maintain one
     * direction. Throws rather than guessing when neither exists — posting a foreign
     * transaction at an invented rate would silently misstate the ledger.
     */
    public function rate(string $fromCurrency, string $toCurrency, \DateTimeInterface|string $date, ?int $tenantId = null): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $tenantId ??= require_tenant_id();
        $onDate = Carbon::parse($date);

        $direct = $this->rates->latestOnOrBefore($tenantId, $from, $to, $onDate);
        if ($direct !== null && $direct->rate > 0) {
            return (float) $direct->rate;
        }

        $inverse = $this->rates->latestOnOrBefore($tenantId, $to, $from, $onDate);
        if ($inverse !== null && $inverse->rate > 0) {
            return 1 / (float) $inverse->rate;
        }

        throw new InvalidArgumentException(
            "No exchange rate from {$from} to {$to} effective on or before {$onDate->toDateString()}."
        );
    }

    /**
     * Convert an amount and round it to the target currency's minor unit.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency, \DateTimeInterface|string $date, ?int $tenantId = null): float
    {
        $rate = $this->rate($fromCurrency, $toCurrency, $date, $tenantId);

        return round($amount * $rate, $this->decimalsFor($toCurrency));
    }
}
