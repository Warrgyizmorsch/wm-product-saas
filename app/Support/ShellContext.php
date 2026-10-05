<?php

namespace App\Support;

use App\Core\Navigation\MenuBuilder;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the app shell (sidebar, header, footer in layouts/duralux) shows
 * about "where you are": tenant, company, branch, fiscal year, currency and
 * the navigation. Resolved once per request and shared by all three partials.
 */
class ShellContext
{
    private ?array $resolved = null;

    public function __construct(private readonly MenuBuilder $menu)
    {
    }

    /**
     * @return array{
     *     tenant: ?\App\Models\Tenant, tenant_name: string, tenant_code: string, plan: string, branding: array,
     *     company: ?Company, company_name: string, branch_name: string, fiscal_year: string,
     *     currency: array{code: string, symbol: string},
     *     companies: Collection, branches: Collection, seats_used: int, seat_limit: int,
     *     nav: array, current_app: ?array
     * }
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $tenant = tenant();
        $settings = $tenant?->settings ?? [];
        $company = company();
        $branch = branch();

        // Same lookup the accounting engine posts with, so the label matches the live period.
        $period = Schema::hasTable('accounting_periods')
            ? app(FiscalPeriodService::class)->periodForDate(now())
            : null;

        $companies = $tenant && Schema::hasTable('companies')
            ? Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('company_name')->get()
                ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->company_name, 'active' => $company?->is($c) ?? false])
            : collect();

        $branches = $company && Schema::hasTable('branches')
            ? Branch::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('name')->get()
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'active' => $branch?->is($b) ?? false])
            : collect();

        $currency = $company ? company_currency() : ['code' => $settings['currency'] ?? 'INR', 'symbol' => '₹'];
        $seatLimit = (int) ($tenant?->max_users ?? 0);

        $nav = $this->menu->navigation(auth()->user(), request()->route()?->getName());

        return $this->resolved = [
            'tenant' => $tenant,
            'tenant_name' => $tenant?->name ?? 'Central Workspace',
            'tenant_code' => strtoupper((string) ($tenant?->slug ?? 'central')),
            'plan' => ucfirst((string) ($tenant?->plan ?? 'Starter')),
            'branding' => tenant_branding($tenant),
            'company' => $company,
            'company_name' => $company?->company_name ?? 'No Company',
            'branch_name' => $branch?->name ?? ($settings['branch'] ?? 'Main Office'),
            'fiscal_year' => $period?->fiscalYear?->name ?? ($settings['financial_year'] ?? 'FY '.now()->format('Y')),
            'currency' => ['code' => $currency['code'] ?? 'INR', 'symbol' => $currency['symbol'] ?? ''],
            'companies' => $companies,
            'branches' => $branches,
            'seat_limit' => $seatLimit,
            'seats_used' => $seatLimit > 0 ? User::query()->where('tenant_id', $tenant->id)->count() : 0,
            'nav' => $nav,
            'current_app' => $nav['app'] !== null ? ($nav['apps'][$nav['app']] ?? null) : null,
        ];
    }
}
