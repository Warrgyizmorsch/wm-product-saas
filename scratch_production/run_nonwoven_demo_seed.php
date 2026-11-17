<?php
/**
 * run_nonwoven_demo_seed.php
 *
 * Single-command runner for the non-woven demo plant data.
 * Kept separate from DatabaseSeeder and from run_production_master_seed.php.
 *
 * Unlike run_production_master_seed.php this runner WIPES NOTHING: every row is matched on its
 * code and created or updated, so it is safe to run again after editing the data file.
 *
 * Usage: php scratch_production/run_nonwoven_demo_seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Database\Seeders\NonWovenDemoMasterDataSeeder;
use Illuminate\Support\Facades\DB;

$tenantId = 1;
$startTime = microtime(true);

echo str_repeat('=', 96) . PHP_EOL;
echo "  NON-WOVEN DEMO PLANT - DATA RUNNER (Tenant ID: {$tenantId}, Branch: 3)" . PHP_EOL;
echo str_repeat('=', 96) . PHP_EOL . PHP_EOL;

$data = NonWovenDemoMasterDataSeeder::loadData();
if ($data === null) {
    echo 'Data file missing or invalid: ' . NonWovenDemoMasterDataSeeder::DATA_FILE . PHP_EOL;
    exit(1);
}

// ── 1. LOAD ───────────────────────────────────────────────────────────────────
echo '>>> STEP 1: Loading master data...' . PHP_EOL;
(new NonWovenDemoMasterDataSeeder())->run($tenantId);
echo '    done.' . PHP_EOL . PHP_EOL;

// ── 2. VERIFY ─────────────────────────────────────────────────────────────────
$currency = $data['meta']['money_currency'] ?? 'INR';
$rate = NonWovenDemoMasterDataSeeder::rateToBase($currency);
$show = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');

// 2.1 Shifts
echo ">>> STEP 2.1: Shifts in Tenant {$tenantId}" . PHP_EOL;
$shiftCodes = array_column($data['production_shifts'] ?? [], 'code');
$shifts = DB::table('production_shifts')->where('tenant_id', $tenantId)->whereIn('code', $shiftCodes)->get();
foreach ($shifts as $s) {
    printf("  %-12s | %-36s | %s - %s (break %dm)\n", $s->code, $s->name, $s->start_time, $s->end_time, $s->break_minutes);
}
echo PHP_EOL;

// 2.2 Warehouses (Branch 3)
echo ">>> STEP 2.2: Warehouses (Branch 3)" . PHP_EOL;
$whCodes = array_column($data['warehouses'] ?? [], 'code');
$warehouses = DB::table('warehouses')->where('tenant_id', $tenantId)->whereIn('code', $whCodes)->get();
foreach ($warehouses as $w) {
    printf("  %-14s | %-32s | Type: %-13s | Default: %s\n", $w->code, $w->name, $w->type, $w->is_default ? 'YES' : 'NO');
}
echo PHP_EOL;

// 2.3 Work Centers & Sections
echo ">>> STEP 2.3: Work Centers & Sections" . PHP_EOL;
$wcCodes = array_column($data['production_work_centers'] ?? [], 'code');
$wcs = DB::table('production_work_centers')->where('tenant_id', $tenantId)->whereIn('code', $wcCodes)->get();
foreach ($wcs as $wc) {
    $overheadInr = (float) $wc->overhead_cost * $rate;
    printf("  [WC] %-10s | %-40s | Overhead: ₹%s/day\n", $wc->code, $wc->name, $show($overheadInr));

    $sections = DB::table('production_work_center_sections')->where('work_center_id', $wc->id)->get();
    foreach ($sections as $sec) {
        $machines = DB::table('production_machines')->where('section_id', $sec->id)->pluck('code')->all();
        $batches = DB::table('production_section_manual_productions')->where('section_id', $sec->id)->pluck('batch_name')->all();

        $machStr = !empty($machines) ? 'Machines: [' . implode(', ', $machines) . ']' : 'No machines';
        $batchStr = !empty($batches) ? 'Batches: [' . implode(', ', $batches) . ']' : '';
        printf("       └─ [SEC] %-12s | %-30s | %s %s\n", $sec->code, $sec->name, $machStr, $batchStr);
    }
}
echo PHP_EOL;

// 2.4 Machines
echo ">>> STEP 2.4: Machines" . PHP_EOL;
$machineCodes = array_column($data['production_machines'] ?? [], 'code');
$machineRows = DB::table('production_machines')
    ->leftJoin('production_work_center_sections', 'production_machines.section_id', '=', 'production_work_center_sections.id')
    ->where('production_machines.tenant_id', $tenantId)
    ->whereIn('production_machines.code', $machineCodes)
    ->select('production_machines.*', 'production_work_center_sections.code as section_code')
    ->get();

printf("  %-10s | %-44s | %-12s | %9s | %s\n", 'Code', 'Name', 'Section', 'Cap/hour', 'Status');
echo '  ' . str_repeat('-', 96) . PHP_EOL;
foreach ($machineRows as $m) {
    printf("  %-10s | %-44s | %-12s | %9s | %s\n",
        $m->code,
        mb_strimwidth($m->name, 0, 44, '..'),
        $m->section_code ?? 'UNASSIGNED',
        $show($m->capacity),
        $m->status
    );
}
echo PHP_EOL;

// 2.5 Products & Opening Stock (Branch 3)
echo ">>> STEP 2.5: Products & Opening Stock in Branch 3" . PHP_EOL;
$prodCodes = array_column($data['products'] ?? [], 'code');
$stockRows = DB::table('product_warehouse_stocks')
    ->join('products', 'product_warehouse_stocks.product_id', '=', 'products.id')
    ->join('warehouses', 'product_warehouse_stocks.warehouse_id', '=', 'warehouses.id')
    ->where('product_warehouse_stocks.tenant_id', $tenantId)
    ->where('warehouses.branch_id', 3)
    ->whereIn('products.sku', $prodCodes)
    ->select('products.sku', 'products.name', 'warehouses.code as wh_code', 'product_warehouse_stocks.quantity', 'product_warehouse_stocks.unit_cost')
    ->get();

foreach ($stockRows as $st) {
    printf("  %-16s | %-38s | %-14s | %8s @ ₹%s\n",
        $st->sku,
        mb_strimwidth($st->name, 0, 38, '..'),
        $st->wh_code,
        $show($st->quantity),
        $show($st->unit_cost * $rate)
    );
}
echo PHP_EOL;

// 2.6 Routings & Operations
echo ">>> STEP 2.6: Routings & Operations" . PHP_EOL;
$routingNums = array_column($data['production_routings'] ?? [], 'routing_number');
$routings = DB::table('routings')->where('tenant_id', $tenantId)->whereIn('routing_number', $routingNums)->get();
foreach ($routings as $r) {
    printf("  [RT] %-18s | %-36s | Status: %s\n", $r->routing_number, $r->name, $r->status);
    $ops = DB::table('production_routing_operations')
        ->leftJoin('production_work_center_sections', 'production_routing_operations.section_id', '=', 'production_work_center_sections.id')
        ->leftJoin('production_machines', 'production_routing_operations.machine_id', '=', 'production_machines.id')
        ->leftJoin('production_section_manual_productions', 'production_routing_operations.section_manual_production_id', '=', 'production_section_manual_productions.id')
        ->where('production_routing_operations.routing_id', $r->id)
        ->select('production_routing_operations.*', 'production_work_center_sections.code as sec_code', 'production_machines.code as mach_code', 'production_section_manual_productions.batch_name')
        ->orderBy('production_routing_operations.sequence')
        ->get();
    foreach ($ops as $op) {
        $res = $op->mach_code ?? $op->batch_name ?? 'NONE';
        printf("       └─ Seq %2d (%s) | %-38s | %-12s | Res: %-16s | Yield: %5.1f%% | Cost: ₹%s\n",
            $op->sequence, $op->operation_number, mb_strimwidth($op->name, 0, 38, '..'), $op->sec_code ?? '-', $res, $op->expected_yield_percentage, $show($op->cost_per_unit * $rate)
        );
    }
}
echo PHP_EOL;

// 2.7 BOMs & Items
echo ">>> STEP 2.7: Bills of Materials (BOM) & Allocations" . PHP_EOL;
$bomNums = array_column($data['production_boms'] ?? [], 'bom_number');
$boms = DB::table('production_boms')
    ->join('products', 'production_boms.product_id', '=', 'products.id')
    ->leftJoin('routings', 'production_boms.routing_id', '=', 'routings.id')
    ->where('production_boms.tenant_id', $tenantId)
    ->whereIn('production_boms.bom_number', $bomNums)
    ->select('production_boms.*', 'products.sku as prod_sku', 'routings.routing_number')
    ->get();
foreach ($boms as $b) {
    printf("  [BOM] %-18s | Target: %-18s | Base Qty: %s | RT: %s\n",
        $b->bom_number, $b->prod_sku, $show($b->base_quantity), $b->routing_number ?? 'NONE'
    );
    $items = DB::table('production_bom_items')
        ->join('products', 'production_bom_items.material_id', '=', 'products.id')
        ->where('production_bom_items.bom_id', $b->id)
        ->select('production_bom_items.*', 'products.sku as mat_sku')
        ->get();
    foreach ($items as $it) {
        $altStr = $it->alternative_quantity ? " (Alt Qty: {$show($it->alternative_quantity)})" : '';
        printf("        ├─ [Item] %-18s | Qty: %8s | Role: %-11s%s\n",
            $it->mat_sku, $show($it->quantity), $it->allocation_role, $altStr
        );
    }
    $allocs = DB::table('production_routing_operation_materials')
        ->join('products', 'production_routing_operation_materials.material_id', '=', 'products.id')
        ->join('production_routing_operations', 'production_routing_operation_materials.routing_operation_id', '=', 'production_routing_operations.id')
        ->where('production_routing_operation_materials.bom_id', $b->id)
        ->select('production_routing_operation_materials.*', 'products.sku as mat_sku', 'production_routing_operations.sequence', 'production_routing_operations.operation_number')
        ->get();
    foreach ($allocs as $al) {
        $altFlag = $al->is_alternative ? " [ALT Prio {$al->priority}]" : " [PRIMARY]";
        printf("        └─ [Alloc] Op %s | %-18s | Qty: %8s%s\n",
            $al->operation_number, $al->mat_sku, $show($al->quantity), $altFlag
        );
    }
}

echo PHP_EOL;
echo 'Finished in ' . round(microtime(true) - $startTime, 2) . 's.' . PHP_EOL;
