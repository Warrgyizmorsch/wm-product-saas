<?php

namespace App\Domains\Accounting\Services;

use App\Models\Tenant;

/**
 * Per-tenant maker-checker settings for manual journals and vouchers, kept in
 * tenants.settings['journal_approval'] (no migration needed to add options).
 *
 *  - enabled:                 off by default, so nothing changes for a tenant
 *                             until an owner turns it on.
 *  - threshold:               entries whose total is below this post directly;
 *                             0 sends every manual entry for approval.
 *  - approvers_post_directly: an entry made by someone who can approve posts
 *                             straight away. On by default so a one-person
 *                             finance team isn't locked out; turn it off for
 *                             strict four-eyes control.
 */
class JournalApprovalSettings
{
    private const KEY = 'journal_approval';

    private const DEFAULTS = [
        'enabled' => false,
        'threshold' => 0.0,
        'approvers_post_directly' => true,
    ];

    /** @return array{enabled: bool, threshold: float, approvers_post_directly: bool} */
    public function for(int $tenantId): array
    {
        $stored = Tenant::query()->whereKey($tenantId)->value('settings');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;
        $values = array_merge(self::DEFAULTS, (array) (($stored ?? [])[self::KEY] ?? []));

        return [
            'enabled' => (bool) $values['enabled'],
            'threshold' => max(0.0, round((float) $values['threshold'], 2)),
            'approvers_post_directly' => (bool) $values['approvers_post_directly'],
        ];
    }

    /** @param array{enabled?: bool, threshold?: float|int|string, approvers_post_directly?: bool} $values */
    public function update(int $tenantId, array $values): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $merged = array_merge($this->for($tenantId), array_intersect_key($values, self::DEFAULTS));

        $settings = $tenant->settings ?? [];
        $settings[self::KEY] = [
            'enabled' => (bool) $merged['enabled'],
            'threshold' => max(0.0, round((float) $merged['threshold'], 2)),
            'approvers_post_directly' => (bool) $merged['approvers_post_directly'],
        ];

        $tenant->forceFill(['settings' => $settings])->save();

        return $settings[self::KEY];
    }
}
