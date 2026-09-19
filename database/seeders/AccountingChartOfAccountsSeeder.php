<?php

namespace Database\Seeders;

use App\Core\Tenant\DefaultOrganization;
use App\Core\Tenant\TenantRunner;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\TaxRateService;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Common masters: every tenant whose plan includes Accounting gets the same defaults, each on its own default
        // company/branch so they show up in the company-scoped UI.
        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            if (! $tenant->hasModule('accounting')) {
                continue;
            }

            // Run inside the tenant so the default company/branch lookup can't pick another tenant's.
            app(TenantRunner::class)->run($tenant, function () use ($tenant): void {
                [$company, $branch] = app(DefaultOrganization::class)->ensure($tenant);

                app(ChartOfAccountsService::class)->provisionDefaults($tenant->id, $company->id, $branch->id);
                app(TaxRateService::class)->provisionDefaultsIfMissing($tenant->id, $company->id, $branch->id);
                app(FiscalPeriodService::class)->provisionCurrentFiscalYearIfMissing($tenant->id, $company->id, $branch->id);
            });
        }
    }
}
