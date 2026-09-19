<?php

namespace App\Core\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant is being set up (new tenant, or `tenant:provision` backfill). Each
 * module listens and creates its own default master data.
 *
 * $modules is the tenant's plan modules (null = no limit). A listener only adds
 * its module's masters when includes() says the plan has that module.
 *
 * Dispatched inside TenantRunner::run() for the tenant, so the tenant global
 * scope already points at it. Listeners must only add rows that are missing —
 * this event fires again for existing tenants.
 */
class TenantProvisioning
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly ?array $modules = null,
    ) {
    }

    /** True when the plan includes ANY of the given modules (or has no module limit). */
    public function includes(string ...$modules): bool
    {
        return $this->modules === null || array_intersect($modules, $this->modules) !== [];
    }
}
