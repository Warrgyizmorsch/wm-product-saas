<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;

$tablesToExport = [
    'production_shifts',
    'production_external_worker_pay_grades',
    'production_work_centers',
    'production_work_center_sections',
    'production_machines',
    'production_section_manual_productions',
    'production_section_manual_production_employees',
    'production_work_center_employees',
    'production_external_worker_allocations',
    'production_work_center_calculations',
    'production_work_center_section_calculations',
    'production_work_center_activity_logs',
    'routings',
    'production_routing_operations',
    'production_routing_operation_materials',
    'production_routing_approvals',
    'production_boms',
    'production_bom_items',
    'production_bom_approvals',
    'production_orders',
    'production_order_operations',
    'production_order_operation_dependencies',
    'production_order_reservations',
    'production_order_issues',
    'production_order_issue_batches',
    'production_order_progress_logs',
    'production_order_receipts',
    'production_requisition_slips',
    'production_requisition_slip_items',
    'production_schedules',
    'production_schedule_operations',
    'production_wips',
    'production_wip_transactions',
    'production_event_timelines',
    'production_machine_downtimes',
    'production_machine_state_histories',
];

$exportData = [];

foreach ($tablesToExport as $table) {
    if (!Schema::hasTable($table)) {
        continue;
    }
    $query = DB::table($table);
    if (Schema::hasColumn($table, 'tenant_id')) {
        $query->where('tenant_id', $tenantId);
    }
    $rows = $query->get()->map(fn($r) => (array)$r)->toArray();
    $exportData[$table] = $rows;
    echo str_pad($table, 45) . " : " . count($rows) . " rows exported" . PHP_EOL;
}

// Export custom products (e.g. test-*)
$exportData['test_products'] = DB::table('products')
    ->where('tenant_id', $tenantId)
    ->where('sku', 'LIKE', 'test-%')
    ->get()
    ->map(fn($r) => (array)$r)
    ->toArray();
echo str_pad("test_products", 45) . " : " . count($exportData['test_products']) . " rows exported" . PHP_EOL;

// Export custom employees (e.g. test-emp-*)
$exportData['test_employees'] = DB::table('employees')
    ->where('tenant_id', $tenantId)
    ->where('employee_id', 'LIKE', 'test-emp-%')
    ->get()
    ->map(fn($r) => (array)$r)
    ->toArray();
echo str_pad("test_employees", 45) . " : " . count($exportData['test_employees']) . " rows exported" . PHP_EOL;

$outputFile = __DIR__ . '/exported_live_production_data.json';
file_put_contents($outputFile, json_encode($exportData, JSON_PRETTY_PRINT));
echo PHP_EOL . "Successfully exported all live data to: " . $outputFile . PHP_EOL;
echo "File size: " . number_format(filesize($outputFile) / 1024, 2) . " KB" . PHP_EOL;
