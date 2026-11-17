<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;

$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();
$tableKey = "Tables_in_" . $dbName;

foreach ($tables as $t) {
    $tableName = $t->$tableKey;
    if (str_contains($tableName, 'routing')) {
        $count = Schema::hasColumn($tableName, 'tenant_id') 
            ? DB::table($tableName)->where('tenant_id', $tenantId)->count()
            : DB::table($tableName)->count();
        echo "$tableName: $count rows" . PHP_EOL;
        $row = DB::table($tableName)->first();
        if ($row) {
            echo "  Sample: " . json_encode($row) . PHP_EOL;
        }
    }
}
