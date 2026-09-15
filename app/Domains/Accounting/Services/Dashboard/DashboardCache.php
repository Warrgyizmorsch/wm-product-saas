<?php

namespace App\Domains\Accounting\Services\Dashboard;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches computed dashboards per tenant. Each tenant has a version token that
 * is part of every cache key; flush() replaces the token, so every cached
 * dashboard for that tenant (any company, cost center or period) is bypassed
 * at once and the old entries simply expire.
 */
class DashboardCache
{
    public const TTL_SECONDS = 600;

    /**
     * @param array<string, mixed> $dimensions everything that changes the result
     */
    public function remember(int $tenantId, array $dimensions, Closure $callback): array
    {
        $key = sprintf('accounting-dashboard:%d:%s:%s', $tenantId, $this->version($tenantId), md5(json_encode($dimensions)));

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public function flush(int $tenantId): void
    {
        Cache::forever($this->versionKey($tenantId), (string) Str::uuid());
    }

    private function version(int $tenantId): string
    {
        return Cache::rememberForever($this->versionKey($tenantId), fn () => (string) Str::uuid());
    }

    private function versionKey(int $tenantId): string
    {
        return "accounting-dashboard:version:{$tenantId}";
    }
}
