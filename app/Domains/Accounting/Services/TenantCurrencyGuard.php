<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Stops a tenant's currency changing once it has journals.
 *
 * Journal amounts are stored as plain numbers in the tenant's currency, so
 * switching INR → GBP after posting would silently relabel every existing
 * ₹18,000 as £18,000. The ledger owns that rule, so the tenant master asks this
 * service rather than querying journals itself.
 */
class TenantCurrencyGuard
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
    ) {
    }

    /**
     * @throws ValidationException when the currency changes and journals exist
     */
    public function assertCanChange(int $tenantId, ?string $currentCode, ?string $newCode, string $field = 'currency'): void
    {
        $current = strtoupper(trim((string) $currentCode));
        $new = strtoupper(trim((string) $newCode));

        if ($current === '' || $current === $new) {
            return;
        }

        $count = $this->journals->countForTenant($tenantId);

        if ($count > 0) {
            throw ValidationException::withMessages([
                $field => "The currency can't be changed from {$current} to {$new}: this tenant already has {$count} "
                    . ($count === 1 ? 'journal' : 'journals')
                    . " recorded in {$current}.",
            ]);
        }
    }

    public function isLocked(int $tenantId): bool
    {
        return $this->journals->countForTenant($tenantId) > 0;
    }
}
