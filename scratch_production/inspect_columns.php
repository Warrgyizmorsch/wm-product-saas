<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;

$tables = [
    'production_boms',
    'production_bom_items',
    'production_routing_operations',
    'production_orders',
    'production_order_operations',
    'production_order_reservations',
    'production_order_issues',
    'production_order_receipts',
    'production_schedules',
    'production_schedule_operations',
    'production_wips',
    'production_requisition_slips',
];

foreach ($tables as $table) {
    echo "=== Table: $table ===" . PHP_EOL;
    $columns = Schema::getColumnListing($table);
    echo "Columns: " . implode(', ', $columns) . PHP_EOL;
    $first = DB::table($table)->where('tenant_id', $tenantId)->first();
    if ($first) {
        echo "Sample row: " . json_encode($first, JSON_PRETTY_PRINT) . PHP_EOL;
    }
    echo PHP_EOL;
}
