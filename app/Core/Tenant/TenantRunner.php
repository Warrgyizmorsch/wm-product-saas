<?php

namespace App\Core\Tenant;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Models\Tenant;
use Closure;

/**
 * Runs code as if the request belonged to $tenant, with no company or branch
 * selected, then puts the previous context back.
 *
 * Needed whenever one tenant's data is written from outside that tenant —
 * e.g. a platform admin creating a tenant from Tenant Console, whose own
 * tenant/company/branch would otherwise be applied by the global scopes.
 */
class TenantRunner
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly CompanyContext $companies,
        private readonly BranchContext $branches,
    ) {
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->tenants->tenant();
        $previousCompany = $this->companies->company();
        $previousBranch = $this->branches->branch();

        $this->tenants->set($tenant);
        $this->companies->clear();
        $this->branches->clear();

        try {
            return $callback();
        } finally {
            $this->tenants->set($previousTenant);
            $this->companies->set($previousCompany);
            $this->branches->set($previousBranch);
        }
    }
}
