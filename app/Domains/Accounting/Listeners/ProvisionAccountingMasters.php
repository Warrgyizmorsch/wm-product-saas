<?php

namespace App\Domains\Accounting\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\TaxRateService;
use App\Domains\Accounting\Services\Tds\TdsSectionService;

/**
 * Provisions the Accounting masters that ProvisionChartOfAccounts does not
 * cover: default GST reference tax rates, the TDS section master with its
 * payable ledgers, and the current (April-March) fiscal year with its 12
 * monthly periods. Runs after ProvisionChartOfAccounts so the ledger
 * accounts tax rates point at already exist.
 */
class ProvisionAccountingMasters
{
    public function __construct(
        private readonly TaxRateService $taxRates,
        private readonly FiscalPeriodService $fiscalPeriods,
        private readonly TdsSectionService $tdsSections,
    ) {
    }

    public function handle(TenantProvisioning $event): void
    {
        if (! $event->includes('accounting')) {
            return;
        }

        $this->taxRates->provisionDefaultsIfMissing($event->tenantId);
        $this->tdsSections->provisionDefaultsIfMissing($event->tenantId);
        $this->fiscalPeriods->provisionCurrentFiscalYearIfMissing($event->tenantId);
    }
}
