<?php

namespace Tests\Feature\Seeding;

use App\Models\Tenant;
use Database\Seeders\AddTestMastersSeeder;
use Database\Seeders\CleanBeforeHrmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The test-data reset: CleanBeforeHrmsSeeder (before HrmsDemoSeeder) empties the HR / production / inventory tables,
 * AddTestMastersSeeder (after it) only adds. The full DatabaseSeeder chain (HRMS is ~5,000 lines) is checked on a
 * throw-away MySQL database, not here.
 */
class TestResetSeedersTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-seed', 'status' => 'active', 'plan' => 'enterprise']);
        $this->other = Tenant::create(['name' => 'Other', 'slug' => 'other-seed', 'status' => 'active', 'plan' => 'enterprise']);
        config(['tenancy.local_fallback_slug' => 'demo-seed']);
    }

    private function shift(Tenant $tenant, string $code): int
    {
        return (int) DB::table('production_shifts')->insertGetId([
            'tenant_id' => $tenant->id, 'name' => "Shift {$code}", 'code' => $code,
            'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function warehouse(Tenant $tenant, string $code): void
    {
        DB::table('warehouses')->insert(['tenant_id' => $tenant->id, 'name' => $code, 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function uom(Tenant $tenant, string $code): void
    {
        DB::table('uoms')->insert(['tenant_id' => $tenant->id, 'name' => $code, 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function journal(Tenant $tenant, string $number, string $source, string $referenceType): int
    {
        $accountId = DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->value('id')
            ?? DB::table('chart_of_accounts')->insertGetId([
                'tenant_id' => $tenant->id, 'code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit',
                'created_at' => now(), 'updated_at' => now(),
            ]);

        $id = (int) DB::table('journals')->insertGetId([
            'tenant_id' => $tenant->id, 'journal_number' => $number, 'journal_date' => '2026-11-01',
            'source' => $source, 'reference_type' => $referenceType, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entries')->insert(['tenant_id' => $tenant->id, 'journal_id' => $id, 'chart_of_account_id' => $accountId, 'debit' => 10, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_the_hr_list_is_identical_to_the_one_in_the_hrms_seeder(): void
    {
        preg_match('/\$tablesToTruncate = \[(.*?)\];/s', file_get_contents(database_path('seeders/HrmsDemoSeeder.php')), $block);
        preg_match_all("/'([a-z0-9_]+)'/", $block[1], $names);

        $this->assertCount(104, $names[1]);
        $this->assertEqualsCanonicalizing($names[1], CleanBeforeHrmsSeeder::HR_TABLES);

        foreach (CleanBeforeHrmsSeeder::HR_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "HR table {$table} does not exist");
        }
    }

    public function test_the_default_seeder_runs_clean_before_hrms_then_add_then_the_test_data_and_no_longer_calls_table_manufacturing(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $code = preg_replace('#//[^\n]*#', '', $source); // comments out

        $this->assertStringNotContainsString('TableManufacturingProductSeeder', $code);

        $order = [];
        foreach (['InventoryMasterDemoSeeder', 'CleanBeforeHrmsSeeder', 'HrmsDemoSeeder', 'AddTestMastersSeeder', 'ProductionE2EDemoSeeder'] as $class) {
            $position = strpos($code, $class . '::class');
            $this->assertNotFalse($position, "{$class} is not called");
            $order[$class] = $position;
        }
        $this->assertSame(array_keys($order), array_keys(collect($order)->sort()->all()), 'seeders are called in the wrong order');
        $this->assertLessThan(strpos($code, 'TenantProvisioner'), $order['ProductionE2EDemoSeeder'], 'the provisioner must run last');
    }

    public function test_the_cleaner_empties_hr_production_and_inventory_tables_and_keeps_other_tenants_and_base_masters(): void
    {
        // HR — wiped globally, like HrmsDemoSeeder does
        DB::table('pay_groups')->insert([['tenant_id' => $this->tenant->id, 'name' => 'A'], ['tenant_id' => $this->other->id, 'name' => 'B']]);
        // production — only the tenant's rows go where another tenant has rows too
        DB::table('production_work_centers')->insert(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-1', 'created_at' => now(), 'updated_at' => now()]);
        $ownShift = $this->shift($this->tenant, 'OWN');
        $otherShift = $this->shift($this->other, 'THEIRS');
        // inventory
        DB::table('products')->insert(['tenant_id' => $this->tenant->id, 'name' => 'P', 'sku' => 'P-1', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['WH-MAIN', 'WH-SEC', 'WH-EXTRA'] as $code) {
            $this->warehouse($this->tenant, $code);
        }
        $this->warehouse($this->other, 'WH-OTHER');
        $this->uom($this->tenant, 'ltr');
        $this->uom($this->other, 'ltr');
        // accounting postings of seeded stock vs. an unrelated journal
        $openingStock = $this->journal($this->tenant, 'J-1', 'inventory', 'ProductOpeningStock');
        $consumption = $this->journal($this->tenant, 'J-2', 'production', 'stock_transaction');
        $invoice = $this->journal($this->tenant, 'J-3', 'sales', 'Invoice');

        $this->seed(CleanBeforeHrmsSeeder::class);

        $this->assertSame(0, DB::table('pay_groups')->count(), 'HR tables are truncated for every tenant');
        $this->assertSame(0, DB::table('production_work_centers')->count());
        $this->assertSame(0, DB::table('products')->count());

        $this->assertSame([$otherShift], DB::table('production_shifts')->pluck('id')->map(fn ($i) => (int) $i)->all(), "another tenant's shift survives, the tenant's goes");
        $this->assertSame($otherShift + 1, $this->shift($this->tenant, 'NEXT'), 'ids restart right above the surviving rows, not above the deleted ones');
        $this->assertNotSame($ownShift, $otherShift);

        $this->assertEqualsCanonicalizing(['WH-MAIN', 'WH-SEC', 'WH-OTHER'], DB::table('warehouses')->pluck('code')->all());
        $this->assertSame([$this->other->id], DB::table('uoms')->pluck('tenant_id')->map(fn ($i) => (int) $i)->all(), "the tenant's units go, another tenant's stay");

        $this->assertEqualsCanonicalizing([$invoice], DB::table('journals')->pluck('id')->map(fn ($i) => (int) $i)->all(), 'only stock postings are removed');
        $this->assertSame(1, DB::table('journal_entries')->count());
        $this->assertNotContains($openingStock, DB::table('journals')->pluck('id')->all());
        $this->assertNotContains($consumption, DB::table('journals')->pluck('id')->all());

        // a fully emptied table restarts its ids at 1
        $this->assertSame(1, (int) DB::table('production_work_centers')->insertGetId(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-2', 'created_at' => now(), 'updated_at' => now()]));
    }

    public function test_the_cleaner_refuses_to_run_outside_local_and_testing(): void
    {
        DB::table('pay_groups')->insert(['tenant_id' => $this->tenant->id, 'name' => 'A']);

        $this->app->detectEnvironment(fn () => 'production');
        try {
            // called directly: `db:seed` itself would stop to ask for a confirmation in production
            $this->app->make(CleanBeforeHrmsSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame(1, DB::table('pay_groups')->count(), 'nothing may be wiped outside local / testing');
    }

    public function test_the_adder_creates_four_units_and_three_warehouses_and_can_run_twice(): void
    {
        $hq = DB::table('branches')->insertGetId(['tenant_id' => $this->tenant->id, 'name' => 'Headquarters', 'code' => 'HQ', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branches')->insert(['tenant_id' => $this->tenant->id, 'name' => 'Plant', 'code' => 'PLANT', 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(AddTestMastersSeeder::class);
        $this->seed(AddTestMastersSeeder::class);

        $this->assertEqualsCanonicalizing(['Pcs', 'Mtr', 'Set', 'Kg'], DB::table('uoms')->where('tenant_id', $this->tenant->id)->pluck('code')->all());
        $this->assertSame('weight', DB::table('uoms')->where('tenant_id', $this->tenant->id)->where('code', 'Kg')->value('category'));

        $warehouses = DB::table('warehouses')->where('tenant_id', $this->tenant->id)->get()->keyBy('code');
        $this->assertEqualsCanonicalizing(['WH-RM-TBL', 'WH-WIP-TBL', 'WH-FG-TBL'], $warehouses->keys()->all());
        $this->assertSame('raw_material', $warehouses['WH-RM-TBL']->type);
        $this->assertSame('wip', $warehouses['WH-WIP-TBL']->type);
        $this->assertSame('finished_goods', $warehouses['WH-FG-TBL']->type);
        $this->assertSame([$hq], $warehouses->pluck('branch_id')->unique()->map(fn ($i) => (int) $i)->all(), 'all three sit on the Headquarters branch');

        $this->assertSame(0, DB::table('uoms')->where('tenant_id', $this->other->id)->count(), 'only the fallback tenant is touched');
    }

    public function test_clean_then_add_leaves_the_inventory_master_warehouses_plus_ours(): void
    {
        foreach (['WH-MAIN', 'WH-SEC', 'NW-WH-RM'] as $code) {
            $this->warehouse($this->tenant, $code);
        }
        $this->uom($this->tenant, 'ltr');

        $this->seed(CleanBeforeHrmsSeeder::class);
        $this->seed(AddTestMastersSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['WH-MAIN', 'WH-SEC', 'WH-RM-TBL', 'WH-WIP-TBL', 'WH-FG-TBL'],
            DB::table('warehouses')->where('tenant_id', $this->tenant->id)->pluck('code')->all()
        );
        $this->assertEqualsCanonicalizing(['Pcs', 'Mtr', 'Set', 'Kg'], DB::table('uoms')->where('tenant_id', $this->tenant->id)->pluck('code')->all());
    }
}
