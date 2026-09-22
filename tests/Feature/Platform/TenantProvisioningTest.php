<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Core\Tenant\TenantProvisioner;
use App\Domains\Platform\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'companies', 'branches', 'uoms',
        'warehouses', 'payment_terms', 'production_shifts', 'chart_of_accounts',
        'cost_centers', 'asset_categories',
    ];

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);
    }

    /** @return array<string, int> */
    private function counts(Tenant $tenant): array
    {
        return collect(self::TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->where('tenant_id', $tenant->id)->count()])
            ->all();
    }

    public function test_a_tenant_gets_default_masters_for_every_module(): void
    {
        $tenant = $this->makeTenant('acme');

        app(TenantProvisioner::class)->provision($tenant);

        $counts = $this->counts($tenant);
        $this->assertSame(1, $counts['companies']);
        $this->assertSame(1, $counts['branches']);
        $this->assertSame(12, $counts['uoms']);
        $this->assertSame(1, $counts['warehouses']);
        $this->assertSame(6, $counts['payment_terms']);
        $this->assertSame(2, $counts['production_shifts']);
        $this->assertGreaterThan(0, $counts['chart_of_accounts']);
        $this->assertSame(4, $counts['cost_centers']);
        $this->assertGreaterThanOrEqual(5, $counts['asset_categories']);

        // CRM statuses are global system defaults
        $this->assertSame(5, DB::table('lead_statuses')->whereNull('tenant_id')->count());
        $this->assertSame(6, DB::table('deal_statuses')->whereNull('tenant_id')->count());
    }

    public function test_running_it_again_adds_nothing_and_keeps_renamed_accounts(): void
    {
        $tenant = $this->makeTenant('acme');
        app(TenantProvisioner::class)->provision($tenant);

        DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '1020')->update(['name' => 'SBI Current A/c']);
        $before = $this->counts($tenant);

        app(TenantProvisioner::class)->provision($tenant);

        $this->assertSame($before, $this->counts($tenant));
        $this->assertSame('SBI Current A/c', DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '1020')->value('name'));
    }

    public function test_lookup_masters_are_common_and_show_in_the_default_company(): void
    {
        $tenant = $this->makeTenant('acme');
        app(TenantProvisioner::class)->provision($tenant);
        $companyId = DB::table('companies')->where('tenant_id', $tenant->id)->value('id');

        // Common masters carry no company/branch ...
        foreach (['chart_of_accounts', 'accounting_tax_rates', 'accounting_fiscal_years', 'cost_centers', 'uoms', 'payment_terms', 'production_shifts'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->where('tenant_id', $tenant->id)->count(), $table);
            $this->assertSame(0, DB::table($table)->where('tenant_id', $tenant->id)->whereNotNull('company_id')->count(), "{$table} should not be company specific");
        }

        // ... and are still listed while a company is selected.
        app(TenantContext::class)->set($tenant);
        app(\App\Core\Company\CompanyContext::class)->set(\App\Domains\HRMS\Models\Company::query()->find($companyId));

        $this->assertSame(5, \App\Domains\Accounting\Models\TaxRate::query()->count());
        $this->assertSame(4, \App\Domains\Accounting\Models\CostCenter::query()->count());
        $this->assertGreaterThan(0, \App\Domains\Accounting\Models\ChartOfAccount::query()->count());
        $this->assertSame(1, \App\Domains\Accounting\Models\FiscalYear::query()->count());
        $this->assertSame(5, \App\Domains\CRM\Models\LeadStatus::query()->count());
        $this->assertSame(6, \App\Domains\CRM\Models\DealStatus::query()->count());
        $this->assertSame(12, \App\Domains\Inventory\Models\Uom::query()->count());
        $this->assertSame(6, \App\Domains\Platform\Models\PaymentTerm::query()->count());
        $this->assertSame(0, DB::table('asset_categories')->where('tenant_id', $tenant->id)->where('company_id', '!=', $companyId)->count());
    }

    private function tenantOnPlan(string $slug, array $modules): Tenant
    {
        $plan = \App\Domains\Platform\Models\Plan::create([
            'name' => ucfirst($slug).' plan', 'slug' => $slug.'-plan', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => $modules, 'is_active' => true,
        ]);

        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'starter', 'plan_id' => $plan->id]);
    }

    public function test_only_the_modules_in_the_plan_get_their_masters(): void
    {
        $tenant = $this->tenantOnPlan('crm-only', ['crm', 'inventory', 'hrms']);

        app(TenantProvisioner::class)->provision($tenant);

        $counts = $this->counts($tenant);
        $this->assertSame(1, $counts['companies']);
        $this->assertSame(12, $counts['uoms']);
        $this->assertSame(0, $counts['chart_of_accounts']);
        $this->assertSame(0, $counts['cost_centers']);
        $this->assertSame(0, $counts['asset_categories']);
        $this->assertSame(0, $counts['production_shifts']);
        $this->assertSame(0, $counts['payment_terms']);
        $this->assertSame(0, DB::table('accounting_tax_rates')->where('tenant_id', $tenant->id)->count());
        $this->assertGreaterThan(0, DB::table('departments')->where('tenant_id', $tenant->id)->count());
    }

    public function test_switching_to_a_bigger_plan_fills_the_new_modules(): void
    {
        $tenant = $this->tenantOnPlan('grow', ['crm']);
        app(TenantProvisioner::class)->provision($tenant);
        $this->assertSame(0, $this->counts($tenant)['chart_of_accounts']);

        $bigger = \App\Domains\Platform\Models\Plan::create([
            'name' => 'Big', 'slug' => 'big', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => ['crm', 'accounting'], 'is_active' => true,
        ]);

        app(\App\Domains\Platform\Services\TenantService::class)->switchOwnPlan($tenant, $bigger->id);

        $counts = $this->counts($tenant);
        $this->assertGreaterThan(0, $counts['chart_of_accounts']);
        $this->assertSame(4, $counts['cost_centers']);
        $this->assertSame(5, DB::table('accounting_tax_rates')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, $counts['uoms']);
    }

    public function test_setting_up_a_tenant_from_inside_another_tenant_writes_only_to_the_new_one(): void
    {
        $adminTenant = $this->makeTenant('platform-home');
        $newTenant = $this->makeTenant('acme');
        app(TenantContext::class)->set($adminTenant);
        // Not zero: old migrations insert lead/deal statuses for tenant_id 1.
        $adminBefore = $this->counts($adminTenant);

        app(TenantProvisioner::class)->provision($newTenant);

        $this->assertSame(5, \App\Domains\CRM\Models\LeadStatus::query()->count());
        $this->assertSame($adminBefore, $this->counts($adminTenant));
        $this->assertSame($adminTenant->id, app(TenantContext::class)->id());
    }

    public function test_creating_a_tenant_from_tenant_console_sets_it_up(): void
    {
        $tenant = app(TenantService::class)->create([
            'name' => 'Acme', 'slug' => 'acme', 'domain' => null, 'billing_email' => null,
            'status' => Tenant::STATUS_ACTIVE, 'plan' => Tenant::PLAN_ENTERPRISE, 'plan_id' => null,
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE, 'max_users' => null, 'max_storage_mb' => null,
            'trial_ends_at' => null, 'plan_started_at' => null, 'plan_expires_at' => null,
            'timezone' => 'Asia/Kolkata', 'locale' => 'en', 'display_name' => 'Acme Industries',
            'branch' => 'Pune HQ', 'currency' => 'INR', 'financial_year' => null,
            'owner_name' => 'Owner', 'owner_email' => 'owner@acme.test', 'owner_password' => 'secret-pass',
        ]);

        $this->assertSame('Acme Industries', DB::table('companies')->where('tenant_id', $tenant->id)->value('company_name'));
        $this->assertSame('Pune HQ', DB::table('branches')->where('tenant_id', $tenant->id)->value('name'));
        $this->assertSame(6, $this->counts($tenant)['payment_terms']);
    }

    public function test_the_command_backfills_existing_tenants(): void
    {
        $tenant = $this->makeTenant('acme');

        $this->artisan('tenant:provision', ['--all' => true])->assertSuccessful();

        $this->assertSame(6, \App\Domains\CRM\Models\DealStatus::query()->count());
    }

    public function test_the_command_rejects_an_unknown_slug(): void
    {
        $this->artisan('tenant:provision', ['tenant' => ['nope']])->assertFailed();
    }
}
