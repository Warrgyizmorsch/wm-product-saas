<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;

echo "=== INSPECTING CURRENT DATABASE STATE (Tenant ID: $tenantId) ===" . PHP_EOL . PHP_EOL;

// Get all tables in database
$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();
$tableKey = "Tables_in_" . $dbName;

$productionTables = [];
$otherKeyTables = ['products', 'employees', 'product_warehouse_stocks', 'stock_transactions'];

foreach ($tables as $t) {
    $tableName = $t->$tableKey;
    if (str_starts_with($tableName, 'production_') || in_array($tableName, $otherKeyTables)) {
        if (Schema::hasColumn($tableName, 'tenant_id')) {
            $count = DB::table($tableName)->where('tenant_id', $tenantId)->count();
            if ($count > 0) {
                echo str_pad($tableName, 45) . " : " . $count . " rows" . PHP_EOL;
                $productionTables[$tableName] = $count;
            }
        } else {
            $count = DB::table($tableName)->count();
            if ($count > 0 && str_starts_with($tableName, 'production_')) {
                echo str_pad($tableName . " (no tenant_id)", 45) . " : " . $count . " rows" . PHP_EOL;
                $productionTables[$tableName] = $count;
            }
        }
    }
}

echo PHP_EOL . "Total active tables with data: " . count($productionTables) . PHP_EOL;
