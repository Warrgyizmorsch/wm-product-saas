<?php

namespace App\Core\Tenant;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Gives a tenant the default master data of the modules its plan includes.
 * Modules outside the plan get nothing; when the plan changes, provision() is
 * run again (TenantService) so newly subscribed modules are filled.
 *
 * Safe to re-run: every step only adds what is missing.
 */
class TenantProvisioner
{
    public function __construct(
        private readonly TenantRunner $runner,
        private readonly DefaultOrganization $organization,
    ) {
    }

    public function provision(Tenant $tenant): void
    {
        DB::transaction(fn () => $this->runner->run($tenant, function () use ($tenant): void {
            [$company, $branch] = $this->organization->ensure($tenant);

            event(new TenantProvisioning($tenant->id, $company->id, $branch->id, $tenant->planModules()));
        }));
    }
}
