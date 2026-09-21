<?php

namespace Database\Seeders;

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
        // Common masters: every tenant whose plan includes Accounting gets the same common defaults
        // (no company/branch, so every company sees them).
        foreach (Tenant::query()->when(config('tenancy.seed_only'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))->orderBy('id')->get() as $tenant) {
            if (! $tenant->hasModule('accounting')) {
                continue;
            }

            // Run inside the tenant so the default company/branch lookup can't pick another tenant's.
            app(TenantRunner::class)->run($tenant, function () use ($tenant): void {
                // IfMissing: never re-apply the template over a chart the tenant already has/renamed.
                // Masters carry no company/branch: they are common and show in every company.
                app(ChartOfAccountsService::class)->provisionDefaultsIfMissing($tenant->id);
                app(TaxRateService::class)->provisionDefaultsIfMissing($tenant->id);
                app(FiscalPeriodService::class)->provisionCurrentFiscalYearIfMissing($tenant->id);
            });
        }
    }
}
