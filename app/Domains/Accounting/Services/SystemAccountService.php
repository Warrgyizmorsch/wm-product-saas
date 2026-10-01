<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Support\SystemAccount;
use InvalidArgumentException;

/**
 * The one way posting logic should find a platform-required account
 * (Accounts Receivable, Output CGST, Round Off, ...). Lookups go by
 * system_key, so they keep working after a tenant renumbers the account.
 */
class SystemAccountService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {}

    /**
     * Resolve a system account for a tenant, or null when the tenant has none.
     *
     * Falls back to the original template code, but only for an account that
     * has no system_key of its own — a tenant provisioned before system keys,
     * or one that renumbered the account before the code lock. A keyed account
     * that now carries another account's old code is never matched.
     */
    public function get(string $key, int $tenantId): ?ChartOfAccount
    {
        $templateCode = SystemAccount::templateCode($key);

        if ($templateCode === null) {
            throw new InvalidArgumentException("Unknown system account key '{$key}'.");
        }

        $account = $this->accounts->findBySystemKey($key, $tenantId);

        if ($account !== null) {
            return $account;
        }

        $legacy = $this->accounts->findByCode($templateCode, $tenantId);

        return $legacy !== null && $legacy->system_key === null ? $legacy : null;
    }

    /** Like get(), but throws when the tenant has no such account. */
    public function require(string $key, int $tenantId): ChartOfAccount
    {
        return $this->get($key, $tenantId)
            ?? throw new InvalidArgumentException(
                "System account '{$key}' (default code " . SystemAccount::templateCode($key) . ") is missing for this tenant."
            );
    }
}
