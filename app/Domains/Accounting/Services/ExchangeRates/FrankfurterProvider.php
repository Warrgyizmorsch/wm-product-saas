<?php

namespace App\Domains\Accounting\Services\ExchangeRates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Frankfurter (https://frankfurter.dev) — a free, keyless API over the European
 * Central Bank's reference rates, published once per working day (~16:00 CET).
 * Covers roughly 30 major currencies; Gulf currencies (KWD, AED, SAR, …) are not
 * included and must be entered manually.
 */
class FrankfurterProvider implements ExchangeRateProvider
{
    public function fetch(string $base, array $symbols, ?\DateTimeInterface $date = null): array
    {
        $path = $date ? $date->format('Y-m-d') : 'latest';

        $data = $this->get('/' . $path, [
            'from' => strtoupper($base),
            'to' => implode(',', array_map('strtoupper', $symbols)),
        ]);

        if (! isset($data['date']) || ! is_array($data['rates'] ?? null)) {
            throw new ExchangeRateProviderException("Unexpected response from Frankfurter for base {$base}.");
        }

        return [
            'date' => (string) $data['date'],
            'rates' => array_map('floatval', $data['rates']),
        ];
    }

    public function supportedCurrencies(): array
    {
        return Cache::remember('exchange-rates:frankfurter:currencies', now()->addDay(), function (): array {
            return array_keys($this->get('/currencies'));
        });
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->client()->get($path, $query);
        } catch (ConnectionException $e) {
            throw new ExchangeRateProviderException('Could not reach Frankfurter: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new ExchangeRateProviderException("Frankfurter returned HTTP {$response->status()} for {$path}.");
        }

        return (array) $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.frankfurter.url'), '/'))
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);
    }
}
