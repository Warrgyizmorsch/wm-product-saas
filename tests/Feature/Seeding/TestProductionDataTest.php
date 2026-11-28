<?php

namespace Tests\Feature\Seeding;

use App\Models\Tenant;
use Database\Seeders\Support\TestProductionLinks;
use Database\Seeders\TestProductionEngineeringSeeder;
use Database\Seeders\TestProductionMasterDataSeeder;
use Database\Seeders\TestProductionQualitySeeder;
use Database\Seeders\TestProductionStockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The test production data (docs/production_seeder_audit_2026-11-26.md) and TestProductionLinks. The master rows
 * (TestProductionMasterDataSeeder::tables) are checked against the schema; the quality plans, routings, BOMs and
 * opening stock are inputs written with codes, checked here to only point at codes the master data owns. The whole
 * chain (HRMS data underneath it) is verified on a throw-away MySQL database.
 */
class TestProductionDataTest extends TestCase
{
    use RefreshDatabase;

    /** keys of a data row that are not columns: TestProductionLinks turns them into ids */
    private const NATURAL_KEYS = ['company', 'branch', 'business_unit', 'department', 'pay_group', 'leave_plan', 'salary_structure', 'uom', 'base_uom', 'warehouse', 'shift', 'employee'];

    /** @return array<string, array<int, array<string, mixed>>> */
    private function dataset(): array
    {
        return (new TestProductionMasterDataSeeder())->tables();
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);
    }

    public function test_every_master_row_fits_the_current_schema(): void
    {
        $this->assertArrayNotHasKey('production_work_center_calculations', $this->dataset(), 'calculation snapshots are made by the engine, never pasted');
        $this->assertArrayNotHasKey('production_work_center_section_calculations', $this->dataset());
        $this->assertArrayNotHasKey('production_work_center_activity_logs', $this->dataset());

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

    public function test_money_is_in_inr_the_base_currency(): void
    {
        $products = collect($this->dataset()['products'])->keyBy('sku');
        $this->assertSame('6200.0000', $products['test-final-01']['selling_price']);
        $this->assertSame('80.0000', $products['test-raw-01']['cost_price']);
        $this->assertSame('80.0000', collect($this->dataset()['production_external_worker_pay_grades'])->firstWhere('code', 'test-pg-1')['hourly_rate']);
        $this->assertSame('2500.0000', collect($this->dataset()['production_work_centers'])->firstWhere('code', 'TEST-WC-1')['overhead_cost']);
        $this->assertSame('600000.00', collect($this->dataset()['employees'])->firstWhere('employee_id', 'test-emp-1')['current_salary']);
    }

    public function test_routings_boms_plans_and_stock_only_point_at_codes_the_data_owns(): void
    {
        $sections = collect($this->dataset()['production_work_center_sections'])->pluck('code', 'id');
        $machineSection = collect($this->dataset()['production_machines'])->mapWithKeys(fn ($m) => [$m['code'] => $sections[$m['section_id'] ?? 0] ?? null]);
        $batchSection = collect($this->dataset()['production_section_manual_productions'])->mapWithKeys(fn ($b) => [$b['batch_name'] => $sections[$b['section_id']]]);
        $skus = collect($this->dataset()['products'])->pluck('type', 'sku');

        $vendors = collect($this->dataset()['vendors'])->keyBy('code');
        $this->assertCount(count($this->dataset()['vendors']), $vendors, 'vendor codes are unique');

        $plans = collect((new TestProductionQualitySeeder())->plans())->keyBy('name');
        $this->assertCount(30, $plans, 'plan names are unique');
        foreach ($plans as $plan) {
            if ($plan['scope'] === 'section') {
                $this->assertContains($plan['section'], $sections->all(), "plan {$plan['name']}: unknown section");
            }
            if ($plan['scope'] === 'product') {
                $this->assertTrue($skus->has($plan['product']), "plan {$plan['name']}: unknown product");
            }
        }
        $this->assertSame(collect($plans)->where('scope', 'product')->pluck('product')->unique()->count(), collect($plans)->where('scope', 'product')->count(), 'one plan family per product (QM-14)');

        $engineering = new TestProductionEngineeringSeeder();
        $routingNumbers = array_column($engineering->routings(), 'routing_number');
        $this->assertSame($routingNumbers, array_values(array_unique($routingNumbers)), 'routing numbers must be unique');
        foreach ($engineering->routings() as $routing) {
            foreach ($routing['operations'] as $i => $op) {
                $where = "{$routing['routing_number']} op {$op['seq']}";
                if (isset($op['external'])) {
                    // SF-1: outsourced = no own resources; an active job-work vendor and the three times in hours (no rate)
                    $this->assertFalse(isset($op['section']) || isset($op['machine']) || isset($op['batch']), "{$where}: an outsourced operation has no section, machine or batch");
                    $this->assertSame('active', $vendors[$op['external']['vendor']]['status'] ?? null, "{$where}: unknown or inactive vendor");
                    foreach (['dispatch_h', 'processing_h', 'receipt_h'] as $timeKey) {
                        $this->assertGreaterThanOrEqual(0, $op['external'][$timeKey] ?? -1, "{$where}: {$timeKey} must be 0 or more hours");
                    }
                    $this->assertArrayNotHasKey('rate', $op['external'], "{$where}: no rate on the routing (SF-1)");
                } else {
                    $this->assertContains($op['section'], $sections->all(), "{$where}: unknown section");
                    $this->assertTrue(isset($op['machine']) xor isset($op['batch']), "{$where}: exactly one machine or batch");
                    if (isset($op['machine'])) {
                        $this->assertSame($op['section'], $machineSection[$op['machine']] ?? null, "{$where}: machine is not in the section");
                    } else {
                        $this->assertSame($op['section'], $batchSection[$op['batch']] ?? null, "{$where}: batch is not in the section");
                    }
                }
                foreach ($op['qc'] ?? [] as $link) {
                    $plan = $plans[$link['plan']] ?? null;
                    $this->assertNotNull($plan, "{$where}: unknown plan {$link['plan']}");
                    $this->assertNotSame('product', $plan['scope'], "{$where}: product plans are never linked to a routing");
                    if ($plan['scope'] === 'section') {
                        $this->assertSame($op['section'] ?? null, $plan['section'], "{$where}: section plan of another section (an outsourced operation takes process plans only)");
                    }
                }
            }
        }

        $seen = [];
        foreach ($engineering->boms() as $bom) {
            $key = $bom['bom_number'] . ' v' . ($bom['version'] ?? '1.0.0');
            $this->assertNotContains($key, array_keys($seen), "{$key} twice");
            $this->assertContains($bom['routing'], $routingNumbers, "{$key}: unknown routing");
            $this->assertTrue($skus->has($bom['product']), "{$key}: unknown product");
            foreach ($bom['materials'] as $m) {
                $this->assertTrue($skus->has($m['material']), "{$key}: unknown material {$m['material']}");
                $this->assertNotSame($bom['product'], $m['material'], "{$key}: a BOM cannot use its own product");
                if (isset($m['child'])) {
                    $this->assertSame($m['material'], collect($seen)->firstWhere('bom_number', $m['child'])['product'] ?? null, "{$key}: sub-BOM {$m['child']} must come earlier and be a BOM of {$m['material']}");
                }
            }
            $seen[$key] = $bom;
        }

        $stock = TestProductionStockSeeder::openingStock();
        foreach ($skus as $sku => $type) {
            if (!in_array($type, ['raw_material', 'service'], true)) {
                $this->assertArrayHasKey($sku, $stock, "no opening stock for {$sku}");
            }
        }
        $this->assertSame([], array_values(array_intersect(array_keys($stock), $skus->filter(fn ($t) => $t === 'service')->keys()->all())), 'service products hold no stock');
    }

    public function test_test_employees_are_not_all_ceos(): void
    {
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
