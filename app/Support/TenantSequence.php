<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gap-tolerant, concurrency-safe document counters per tenant and key
 * (e.g. "lead:2026"). Replaces "count rows + 1", which handed the same number
 * to parallel requests and reused numbers after a soft delete.
 */
class TenantSequence
{
    /**
     * Next value for ($tenantId, $key). On first use the counter starts from
     * $initial(), the highest number already issued, so existing numbering
     * continues without a clash.
     *
     * @param callable(): int $initial
     */
    public static function next(int $tenantId, string $key, callable $initial): int
    {
        return self::reserve($tenantId, $key, 1, $initial);
    }

    /**
     * Reserves $count consecutive values in one locked step (bulk imports) and
     * returns the first; the caller owns first .. first + $count - 1.
     *
     * @param callable(): int $initial
     */
    public static function reserve(int $tenantId, string $key, int $count, callable $initial): int
    {
        $count = max(1, $count);

        return DB::transaction(function () use ($tenantId, $key, $count, $initial): int {
            $row = self::lockedRow($tenantId, $key);

            if ($row === null) {
                DB::table('tenant_sequences')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'key' => $key,
                    'value' => max(0, (int) $initial()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = self::lockedRow($tenantId, $key);
            }

            $first = (int) $row->value + 1;

            DB::table('tenant_sequences')->where('id', $row->id)->update([
                'value' => (int) $row->value + $count,
                'updated_at' => now(),
            ]);

            return $first;
        });
    }

    /**
     * Highest trailing number among $numbers ("LD-2026-0042" -> 42).
     *
     * @param iterable<string|null> $numbers
     */
    public static function highestSuffix(iterable $numbers): int
    {
        $max = 0;
        foreach ($numbers as $number) {
            if (is_string($number) && preg_match('/(\d+)\s*$/', $number, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    private static function lockedRow(int $tenantId, string $key): ?object
    {
        return DB::table('tenant_sequences')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
    }
}
