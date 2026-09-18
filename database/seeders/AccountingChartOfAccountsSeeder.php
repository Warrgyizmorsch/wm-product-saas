<?php

namespace Database\Seeders;

use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\TaxRateService;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            return;
        }

        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);
        app(TaxRateService::class)->provisionDefaultsIfMissing($tenant->id);
        app(FiscalPeriodService::class)->provisionCurrentFiscalYearIfMissing($tenant->id);
    }
}
