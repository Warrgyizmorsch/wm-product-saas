<?php

namespace Tests\Feature\Seeding;

use App\Models\Tenant;
use Database\Seeders\Support\TestProductionLinks;
use Database\Seeders\TestProductionEngineeringSeeder;
use Database\Seeders\TestProductionExecutionSeeder;
use Database\Seeders\TestProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The test production data (held inside TestProductionMasterDataSeeder / EngineeringSeeder / ExecutionSeeder) and
 * TestProductionLinks. The whole chain (HRMS data underneath it) is verified on a throw-away MySQL database; here:
 * the data fits the current schema, and every reference to a row the data does not own is a natural key that is
 * looked up by code.
 */
class TestProductionDataTest extends TestCase
{
    use RefreshDatabase;

    /** keys of a data row that are not columns: TestProductionLinks turns them into ids */
    private const NATURAL_KEYS = ['company', 'branch', 'business_unit', 'department', 'pay_group', 'leave_plan', 'salary_structure', 'uom', 'base_uom', 'warehouse', 'shift', 'employee'];

    /** @return array<string, array<int, array<string, mixed>>> */
    private function dataset(): array
    {
        return (new TestProductionMasterDataSeeder())->tables()
            + (new TestProductionEngineeringSeeder())->tables()
            + (new TestProductionExecutionSeeder())->tables();
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);
    }

    public function test_every_table_is_in_exactly_one_seeder_and_every_row_fits_the_current_schema(): void
    {
        $seen = [];
        foreach ([new TestProductionMasterDataSeeder(), new TestProductionEngineeringSeeder(), new TestProductionExecutionSeeder()] as $seeder) {
            foreach (array_keys($seeder->tables()) as $table) {
                $this->assertNotContains($table, $seen, "{$table} is loaded by two seeders");
                $seen[] = $table;
            }
        }

        $unknown = [];
        foreach ($this->dataset() as $table => $rows) {
            $this->assertTrue(Schema::hasTable($table), "table {$table} does not exist");
            $this->assertNotEmpty($rows, "{$table} has no rows");
            $columns = Schema::getColumnListing($table);

            foreach ($rows as $row) {
                foreach (array_diff(array_keys($row), $columns, self::NATURAL_KEYS) as $key) {
                    $unknown[$table . '.' . $key] = true;
                }
                $this->assertArrayNotHasKey('tenant_id', $row, "{$table}: the tenant is set by the loader");
            }
        }

        $this->assertSame([], array_keys($unknown), 'the data carries columns the schema does not have');
    }

    public function test_references_to_rows_the_data_does_not_own_are_natural_keys_never_ids(): void
    {
        foreach ($this->dataset() as $table => $rows) {
            foreach ($rows as $row) {
                foreach (['uom_id', 'base_uom_id', 'warehouse_id', 'shift_id', 'company_id', 'branch_id', 'business_unit_id', 'department_id', 'pay_group_id', 'leave_plan_id', 'salary_structure_id', 'designation_id'] as $idColumn) {
                    $this->assertArrayNotHasKey($idColumn, $row, "{$table} points at {$idColumn} by id; use the natural key");
                }
            }
        }

        $products = $this->dataset()['products'];
        $this->assertSame(['1200', '4010', '5010'], [$products[0]['inventory_account'], $products[0]['sales_account'], $products[0]['purchase_account']]);
    }

    public function test_bom_numbers_are_unique_and_test_employees_are_not_all_ceos(): void
    {
        $numbers = array_column($this->dataset()['production_boms'], 'bom_number');
        $this->assertSame($numbers, array_values(array_unique($numbers)), 'BOM numbers must be unique');

        $links = new TestProductionLinks(1);
        $designations = collect($this->dataset()['employees'])->map(fn ($e) => $links->designationFor($e['job_title'])['name'])->unique();
        $this->assertGreaterThan(1, $designations->count());
        $this->assertNotContains('Chief Executive Officer', $designations->all());
    }

    public function test_job_titles_map_to_production_designations(): void
    {
        $links = new TestProductionLinks(1);

        $this->assertSame('Production Supervisor', $links->designationFor('Assembly Lead')['name']);
        $this->assertSame('Quality Inspector', $links->designationFor('Night Facility Inspector')['name']);
        $this->assertSame('Assembler / Fabricator', $links->designationFor('Master Fabricator')['name']);
        $this->assertSame('Machine Operator', $links->designationFor('CNC Operator')['name']);
        $this->assertSame('Maintenance Technician', $links->designationFor('Electro-Mech Maintenance')['name']);
        $this->assertSame('Production Technician', $links->designationFor('Sub-Assembly Tech')['name']);
    }

    public function test_units_warehouses_shifts_and_accounts_are_found_by_code(): void
    {
        $other = $this->tenant('other');
        $tenant = $this->tenant('mine');
        $now = now();

        // another tenant owns the low ids, so the codes of this tenant get higher ones
        DB::table('uoms')->insert(['tenant_id' => $other->id, 'name' => 'Their Pcs', 'code' => 'Pcs', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('warehouses')->insert(['tenant_id' => $other->id, 'name' => 'Theirs', 'code' => 'WH-RM-TBL', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('production_shifts')->insert(['tenant_id' => $other->id, 'name' => 'Theirs', 'code' => 'DAY', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'created_at' => $now, 'updated_at' => $now]);

        $pcs = DB::table('uoms')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Pcs', 'code' => 'Pcs', 'created_at' => $now, 'updated_at' => $now]);
        $rm = DB::table('warehouses')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'RM', 'code' => 'WH-RM-TBL', 'created_at' => $now, 'updated_at' => $now]);
        $day = DB::table('production_shifts')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Day', 'code' => 'DAY', 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => $now, 'updated_at' => $now]);
        $mrn = DB::table('production_shifts')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Morning', 'code' => 'MRN', 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'created_at' => $now, 'updated_at' => $now]);
        foreach (['1200' => 'asset', '4010' => 'income', '5010' => 'expense'] as $code => $type) {
            DB::table('chart_of_accounts')->insert(['tenant_id' => $tenant->id, 'code' => $code, 'name' => "Account {$code}", 'type' => $type, 'normal_balance' => 'debit', 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->assertNotSame(1, $pcs, 'the test only means something when the ids are not the first ones');

        $links = new TestProductionLinks($tenant->id);

        $row = $links->translate('production_bom_items', ['uom' => 'Pcs', 'base_uom' => 'Pcs', 'warehouse' => 'WH-RM-TBL', 'shift' => 'DAY']);
        $this->assertSame(['uom_id' => $pcs, 'base_uom_id' => $pcs, 'warehouse_id' => $rm, 'shift_id' => $day], $row);

        $section = $links->translate('production_work_center_sections', ['assigned_shifts' => ['DAY', 'MRN']]);
        $this->assertSame(json_encode([$day, $mrn]), $section['assigned_shifts']);

        $product = $links->translate('products', ['inventory_account' => '1200', 'sales_account' => '4010', 'purchase_account' => '5010']);
        $codes = collect($product)->map(fn ($id) => DB::table('chart_of_accounts')->where('id', $id)->value('code'))->all();
        $this->assertSame(['inventory_account' => '1200', 'sales_account' => '4010', 'purchase_account' => '5010'], $codes);
    }

    public function test_a_key_that_cannot_be_resolved_stops_the_seeding_with_a_clear_message(): void
    {
        $links = new TestProductionLinks($this->tenant('mine')->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("no uoms found for code 'Pcs'");

        $links->translate('products', ['uom' => 'Pcs']);
    }
}
