<?php

namespace App\Domains\Purchase\Services;

use App\Models\Tenant;

/**
 * Per-tenant 3-way match settings, kept in tenants.settings['three_way_match'].
 *
 *  - mode: 'off'  — no checking.
 *          'warn' — (default) check and flag exceptions on the bill, but post it
 *                   as before. Safe default: nothing changes for a tenant except
 *                   that mismatches become visible.
 *          'hold' — a bill with exceptions is saved 'On Hold': not posted to the
 *                   ledger and not payable until someone releases it.
 *  - qty_tolerance_percent:   how far billed quantity may exceed what was
 *                             received (accepted) before it is an exception.
 *  - price_tolerance_percent: how far the billed rate may exceed the PO rate.
 */
class ThreeWayMatchSettings
{
    public const MODE_OFF = 'off';
    public const MODE_WARN = 'warn';
    public const MODE_HOLD = 'hold';
    public const MODES = [self::MODE_OFF, self::MODE_WARN, self::MODE_HOLD];

    private const KEY = 'three_way_match';

    private const DEFAULTS = [
        'mode' => self::MODE_WARN,
        'qty_tolerance_percent' => 0.0,
        'price_tolerance_percent' => 2.0,
    ];

    /** @return array{mode: string, qty_tolerance_percent: float, price_tolerance_percent: float} */
    public function for(int $tenantId): array
    {
        $stored = Tenant::query()->whereKey($tenantId)->value('settings');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;

        return $this->normalise(array_merge(self::DEFAULTS, (array) (($stored ?? [])[self::KEY] ?? [])));
    }

    public function update(int $tenantId, array $values): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $settings = $tenant->settings ?? [];
        $settings[self::KEY] = $this->normalise(array_merge($this->for($tenantId), array_intersect_key($values, self::DEFAULTS)));

        $tenant->forceFill(['settings' => $settings])->save();

        return $settings[self::KEY];
    }

    private function normalise(array $values): array
    {
        return [
            'mode' => in_array($values['mode'], self::MODES, true) ? $values['mode'] : self::MODE_WARN,
            'qty_tolerance_percent' => min(100.0, max(0.0, round((float) $values['qty_tolerance_percent'], 2))),
            'price_tolerance_percent' => min(100.0, max(0.0, round((float) $values['price_tolerance_percent'], 2))),
        ];
    }
}
