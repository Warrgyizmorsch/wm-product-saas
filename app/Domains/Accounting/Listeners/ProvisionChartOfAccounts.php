<?php

namespace App\Domains\Accounting\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Accounting\Services\ChartOfAccountsService;

class ProvisionChartOfAccounts
{
    public function __construct(
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function handle(TenantProvisioning $event): void
    {
        $this->accounts->provisionDefaultsIfMissing($event->tenantId, $event->companyId, $event->branchId);
    }
}
