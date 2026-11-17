<?php
/**
 * run_production_master_seed.php
 *
 * Single-command executable master scratch runner:
 * 1. Cleanly wipes/resets old test production data, test orders, routings, BOMs,
 *    test products, and test employees for Tenant 1.
 * 2. Invokes the dedicated standalone ProductionE2EDemoSeeder (which is kept separate
 *    from DatabaseSeeder).
 * 3. Audits all production tables and prints a full verification report.
 *
 * Usage: php scratch_production/run_production_master_seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Database\Seeders\ProductionE2EDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;
$startTime = microtime(true);

echo "================================================================================" . PHP_EOL;
echo "  ASTRA ERP - PRODUCTION MASTER DATA & DEMO FLOW RUNNER (Tenant ID: {$tenantId})" . PHP_EOL;
echo "================================================================================" . PHP_EOL . PHP_EOL;

// ── 1. CLEANUP PHASE ──────────────────────────────────────────────────────────
echo ">>> STEP 1: Wiping old test production data, orders, routings & test items..." . PHP_EOL;

Schema::disableForeignKeyConstraints();

$tablesToClean = [
    // Execution & Logs
    'production_event_timelines',
    'production_machine_downtimes',
    'production_machine_state_histories',
    'production_wip_transactions',
    'production_wips',
    'production_schedule_operations',
    'production_schedules',
    'production_order_receipts',
    'production_order_issue_batches',
    'production_order_issues',
    'production_requisition_slip_items',
    'production_requisition_slips',
    'production_order_reservations',
    'production_order_progress_logs',
    'production_order_operation_dependencies',
    'production_order_operations',
    'production_orders',

    // Engineering & BOMs
    'production_bom_approvals',
    'production_bom_items',
    'production_boms',
    'production_routing_approvals',
    'production_routing_operation_materials',
    'production_routing_operations',
    'routings',

    // Plant Infrastructure & Personnel
    'production_work_center_calculations',
    'production_work_center_section_calculations',
    'production_work_center_activity_logs',
    'production_external_worker_allocations',
    'production_work_center_employees',
    'production_section_manual_production_employees',
    'production_section_manual_productions',
    'production_machines',
    'production_work_center_sections',
    'production_work_centers',
    'production_external_worker_pay_grades',
];

foreach ($tablesToClean as $table) {
    if (Schema::hasTable($table)) {
        if (Schema::hasColumn($table, 'tenant_id')) {
            $deleted = DB::table($table)->where('tenant_id', $tenantId)->delete();
        } else {
            $deleted = DB::table($table)->delete();
        }
        echo "  [WIPED] Table {$table}" . ($deleted > 0 ? " ({$deleted} rows removed)" : "") . PHP_EOL;
    }
}

// Wipe custom shifts (preserving core DAY, MRN, EVE)
$deletedShifts = DB::table('production_shifts')
    ->where('tenant_id', $tenantId)
    ->where('code', 'LIKE', 'SH-%')
    ->delete();
echo "  [WIPED] Cleaned {$deletedShifts} custom production shifts (core HRMS shifts preserved)" . PHP_EOL;

// Wipe test products & warehouse stocks
$testProductIds = DB::table('products')
    ->where('tenant_id', $tenantId)
    ->where('sku', 'LIKE', 'test-%')
    ->pluck('id')
    ->toArray();

if (!empty($testProductIds)) {
    DB::table('product_warehouse_stocks')->where('tenant_id', $tenantId)->whereIn('product_id', $testProductIds)->delete();
    DB::table('stock_transactions')->where('tenant_id', $tenantId)->whereIn('product_id', $testProductIds)->delete();
    $deletedProds = DB::table('products')->where('tenant_id', $tenantId)->whereIn('id', $testProductIds)->delete();
    echo "  [WIPED] Cleaned {$deletedProds} test products, stock balances & transactions" . PHP_EOL;
}

// Wipe test employees
$deletedEmps = DB::table('employees')
    ->where('tenant_id', $tenantId)
    ->where('employee_id', 'LIKE', 'test-emp-%')
    ->delete();
echo "  [WIPED] Cleaned {$deletedEmps} test employees (corporate employees WRG-001..016 preserved)" . PHP_EOL;

Schema::enableForeignKeyConstraints();
echo PHP_EOL . ">>> Cleanup complete. Database ready for master seeding." . PHP_EOL . PHP_EOL;

// ── 2. SEEDING PHASE ──────────────────────────────────────────────────────────
echo ">>> STEP 2: Executing Standalone ProductionE2EDemoSeeder..." . PHP_EOL;

$seeder = new ProductionE2EDemoSeeder();
$seeder->run($tenantId);

echo PHP_EOL . ">>> Seeding complete." . PHP_EOL . PHP_EOL;

// ── 3. VERIFICATION & AUDIT PHASE ─────────────────────────────────────────────
echo ">>> STEP 3: Verifying Seeded Records & Data Integrity..." . PHP_EOL . PHP_EOL;

$auditTables = [
    'products'                                    => 'Test Products (test-*)',
    'employees'                                   => 'Test Employees (test-emp-*)',
    'production_shifts'                           => 'Production Shifts',
    'production_external_worker_pay_grades'        => 'External Pay Grades',
    'production_work_centers'                      => 'Work Centers',
    'production_work_center_sections'              => 'Work Center Sections',
    'production_machines'                          => 'Production Machines',
    'production_section_manual_productions'        => 'Manual Batches',
    'production_section_manual_production_employees'=> 'Manual Batch Employees Pivot',
    'production_work_center_employees'             => 'Work Center Staffing',
    'production_external_worker_allocations'       => 'External Worker Allocations',
    'production_work_center_calculations'          => 'Work Center Calculations',
    'production_work_center_section_calculations'  => 'Section Calculations',
    'production_work_center_activity_logs'         => 'Activity Logs',
    'routings'                                     => 'Manufacturing Routings',
    'production_routing_operations'                => 'Routing Operations',
    'production_routing_operation_materials'       => 'Operation Materials',
    'production_routing_approvals'                 => 'Routing Approvals',
    'production_boms'                              => 'Bills of Materials (BOM)',
    'production_bom_items'                         => 'BOM Line Items',
    'production_bom_approvals'                     => 'BOM Approvals',
    'production_orders'                            => 'Production Orders',
    'production_order_operations'                  => 'Order Operations',
    'production_order_operation_dependencies'      => 'Operation Dependencies',
    'production_order_reservations'                => 'Material Reservations',
    'production_order_issues'                      => 'Material Issues',
    'production_order_issue_batches'               => 'Issue Batch Traceability',
    'production_order_progress_logs'               => 'Progress Logs',
    'production_order_receipts'                    => 'Finished Goods Receipts',
    'production_requisition_slips'                 => 'Material Requisitions',
    'production_requisition_slip_items'            => 'Requisition Items',
    'production_schedules'                         => 'Production Schedules',
    'production_schedule_operations'               => 'Schedule Operations',
    'production_wips'                              => 'WIP Master Records',
    'production_wip_transactions'                  => 'WIP Cost Transactions',
    'production_event_timelines'                   => 'Event Timelines',
    'production_machine_downtimes'                 => 'Machine Downtimes',
    'production_machine_state_histories'           => 'Machine State Histories',
];

printf("%-45s | %-12s | %s\n", "Table / Entity", "Row Count", "Status");
echo str_repeat("-", 75) . PHP_EOL;

$allPassed = true;
foreach ($auditTables as $table => $label) {
    if ($table === 'products') {
        $count = DB::table('products')->where('tenant_id', $tenantId)->where('sku', 'LIKE', 'test-%')->count();
    } elseif ($table === 'employees') {
        $count = DB::table('employees')->where('tenant_id', $tenantId)->where('employee_id', 'LIKE', 'test-emp-%')->count();
    } elseif (Schema::hasColumn($table, 'tenant_id')) {
        $count = DB::table($table)->where('tenant_id', $tenantId)->count();
    } else {
        $count = DB::table($table)->count();
    }

    $status = $count > 0 ? "OK" : "EMPTY";
    if ($count === 0 && !in_array($table, ['production_machine_downtimes'])) {
        $allPassed = false;
    }
    printf("%-45s | %-12d | %s\n", $label, $count, $status);
}

$elapsed = round(microtime(true) - $startTime, 2);

echo PHP_EOL . "================================================================================" . PHP_EOL;
if ($allPassed) {
    echo "  ALL PRODUCTION DATA SUCCESSFULLY SEEDED & VERIFIED IN {$elapsed} SECONDS!" . PHP_EOL;
} else {
    echo "  WARNING: One or more tables remained empty after execution ({$elapsed}s)." . PHP_EOL;
}
echo "================================================================================" . PHP_EOL;
