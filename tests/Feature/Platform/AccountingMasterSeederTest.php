<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use Database\Seeders\AccountingChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingMasterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_gives_every_tenant_its_own_common_masters(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active', 'plan' => 'enterprise']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active', 'plan' => 'enterprise']);

        $this->seed(AccountingChartOfAccountsSeeder::class);
        $this->seed(AccountingChartOfAccountsSeeder::class);

        foreach ([$a, $b] as $tenant) {
            foreach (['chart_of_accounts', 'accounting_tax_rates', 'accounting_fiscal_years'] as $table) {
                $rows = DB::table($table)->where('tenant_id', $tenant->id);
                $this->assertGreaterThan(0, (clone $rows)->count(), "{$tenant->slug}: {$table} empty");
                $this->assertSame(0, (clone $rows)->whereNotNull('company_id')->count(), "{$tenant->slug}: {$table} should not be company specific");
            }

            $this->assertSame(5, DB::table('accounting_tax_rates')->where('tenant_id', $tenant->id)->count());
        }
    }

    public function test_the_seeder_skips_tenants_whose_plan_has_no_accounting(): void
    {
        $plan = \App\Domains\Platform\Models\Plan::create([
            'name' => 'CRM', 'slug' => 'crm-plan', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => ['crm'], 'is_active' => true,
        ]);
        $crm = Tenant::create(['name' => 'C', 'slug' => 'c', 'status' => 'active', 'plan' => 'starter', 'plan_id' => $plan->id]);
        $full = Tenant::create(['name' => 'F', 'slug' => 'f', 'status' => 'active', 'plan' => 'enterprise']);

        $this->seed(AccountingChartOfAccountsSeeder::class);

        $this->assertSame(0, DB::table('chart_of_accounts')->where('tenant_id', $crm->id)->count());
        $this->assertGreaterThan(0, DB::table('chart_of_accounts')->where('tenant_id', $full->id)->count());
    }

    public function test_seed_only_limits_the_seeder_and_never_overwrites_an_existing_chart(): void
    {
        $old = Tenant::create(['name' => 'Old', 'slug' => 'old', 'status' => 'active', 'plan' => 'enterprise']);
        $new = Tenant::create(['name' => 'New', 'slug' => 'new', 'status' => 'active', 'plan' => 'enterprise']);

        config(['tenancy.seed_only' => ['old']]);
        $this->seed(AccountingChartOfAccountsSeeder::class);
        DB::table('chart_of_accounts')->where('tenant_id', $old->id)->where('code', '1020')->update(['name' => 'SBI Current A/c']);

        config(['tenancy.seed_only' => []]);
        $this->seed(AccountingChartOfAccountsSeeder::class);

        $this->assertSame('SBI Current A/c', DB::table('chart_of_accounts')->where('tenant_id', $old->id)->where('code', '1020')->value('name'));
        $this->assertGreaterThan(0, DB::table('chart_of_accounts')->where('tenant_id', $new->id)->count());

        DB::table('chart_of_accounts')->where('tenant_id', $new->id)->delete();
        config(['tenancy.seed_only' => ['old']]);
        $this->seed(AccountingChartOfAccountsSeeder::class);
        $this->assertSame(0, DB::table('chart_of_accounts')->where('tenant_id', $new->id)->count());
    }
}
