<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AddTestMastersSeeder — the "adding" half of the test-data reset.
 *
 * Runs AFTER HrmsDemoSeeder (see DatabaseSeeder). CleanBeforeHrmsSeeder has already emptied every table this
 * seeder (and the test production data that follows it) fills, so this seeder only ADDS — it never wipes and never
 * has to preserve anything. It replaces what TableManufacturingProductSeeder used to provide, minus its products and stock:
 *
 *   - units of measure  Pcs, Mtr, Set, Kg
 *   - three warehouses  WH-RM-TBL (raw material), WH-WIP-TBL (work in progress), WH-FG-TBL (finished goods),
 *                       all on the Headquarters branch (HQ) so branch-based stock lookups count them
 *
 * (The chart-of-accounts rows 1200 / 4010 / 5010 that TableManufacturingProductSeeder looked up come from the chart template.)
 *
 * Written with the query builder, not Eloquent: HrmsDemoSeeder leaves a tenant / company / branch context set, and the
 * company / branch model traits would then rewrite or hide these rows. Safe to run twice: rows are matched on their code.
 *
 * The test production data (products, employees, shifts, work centres, routings, BOMs, orders …) is added by
 * ProductionE2EDemoSeeder, which DatabaseSeeder runs right after this one.
 */
class AddTestMastersSeeder extends Seeder
{
    /** @var array<int, array{code:string, name:string, category:string}> */
    public const UOMS = [
        ['code' => 'Pcs', 'name' => 'Pieces', 'category' => 'unit'],
        ['code' => 'Mtr', 'name' => 'Meters', 'category' => 'length'],
        ['code' => 'Set', 'name' => 'Set', 'category' => 'unit'],
        ['code' => 'Kg', 'name' => 'Kilograms', 'category' => 'weight'],
    ];

    /** @var array<int, array{code:string, name:string, type:string}> */
    public const WAREHOUSES = [
        ['code' => 'WH-RM-TBL', 'name' => 'Central Raw Material Store', 'type' => 'raw_material'],
        ['code' => 'WH-WIP-TBL', 'name' => 'Work-In-Progress Store', 'type' => 'wip'],
        ['code' => 'WH-FG-TBL', 'name' => 'Finished Goods Warehouse', 'type' => 'finished_goods'],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (! $tenant) {
            return;
        }

        $uoms = $this->addUoms((int) $tenant->id);
        $warehouses = $this->addWarehouses((int) $tenant->id);

        $this->command?->info("AddTestMastersSeeder: {$uoms} unit(s) and {$warehouses} warehouse(s) added.");
    }

    private function addUoms(int $tenantId): int
    {
        $columns = Schema::getColumnListing('uoms');
        $added = 0;

        foreach (self::UOMS as $uom) {
            if (DB::table('uoms')->where('tenant_id', $tenantId)->where('code', $uom['code'])->exists()) {
                continue;
            }

            $row = ['tenant_id' => $tenantId, 'code' => $uom['code'], 'name' => $uom['name'], 'category' => $uom['category'], 'created_at' => now(), 'updated_at' => now()];
            DB::table('uoms')->insert(array_intersect_key($row, array_flip($columns)));
            $added++;
        }

        return $added;
    }

    private function addWarehouses(int $tenantId): int
    {
        $columns = Schema::getColumnListing('warehouses');
        $companyId = DB::table('companies')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        // Headquarters (code HQ, created by InventoryMasterDemoSeeder); first branch of the tenant if HQ is missing
        $branchId = DB::table('branches')->where('tenant_id', $tenantId)->where('code', 'HQ')->value('id')
            ?? DB::table('branches')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        $added = 0;

        foreach (self::WAREHOUSES as $wh) {
            if (DB::table('warehouses')->where('tenant_id', $tenantId)->where('code', $wh['code'])->exists()) {
                continue;
            }

            // company and branch (Headquarters) set, active, not the default warehouse
            $row = [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'branch_id' => $branchId,
                'code' => $wh['code'], 'name' => $wh['name'], 'type' => $wh['type'], 'status' => 'active',
                'is_default' => false, 'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('warehouses')->insert(array_intersect_key($row, array_flip($columns)));
            $added++;
        }

        return $added;
    }
}
