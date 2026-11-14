<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tenantId = 1;

echo "=== DETAILED INSPECTION OF USER CREATED DATA ===" . PHP_EOL . PHP_EOL;

// 1. BOMs
echo "--- BOMs ---" . PHP_EOL;
$boms = DB::table('production_boms')->where('tenant_id', $tenantId)->get();
foreach ($boms as $b) {
    echo "BOM ID: {$b->id} | Number: {$b->bom_number} | Product ID: {$b->product_id} | Status: {$b->status} | Version: {$b->version}" . PHP_EOL;
}

// 2. Routings
echo PHP_EOL . "--- Routings ---" . PHP_EOL;
if (DB::getSchemaBuilder()->hasTable('production_routings')) {
    $routings = DB::table('production_routings')->where('tenant_id', $tenantId)->get();
    foreach ($routings as $r) {
        echo "Routing ID: {$r->id} | Number: {$r->routing_number} | Product ID: {$r->product_id} | Status: {$r->status}" . PHP_EOL;
    }
} else {
    echo "No production_routings table. Operations:" . PHP_EOL;
    $ops = DB::table('production_routing_operations')->where('tenant_id', $tenantId)->get();
    foreach ($ops as $op) {
        echo "Op ID: {$op->id} | Name: {$op->operation_name} | Seq: {$op->sequence} | WC ID: {$op->work_center_id}" . PHP_EOL;
    }
}

// 3. Production Orders
echo PHP_EOL . "--- Production Orders ---" . PHP_EOL;
$pos = DB::table('production_orders')->where('tenant_id', $tenantId)->get();
foreach ($pos as $po) {
    echo "PO ID: {$po->id} | Number: {$po->order_number} | Product ID: {$po->product_id} | Status: {$po->status} | Planned Qty: {$po->planned_quantity} | Due: {$po->due_date}" . PHP_EOL;
}

// 4. Schedules
echo PHP_EOL . "--- Schedules ---" . PHP_EOL;
$schedules = DB::table('production_schedules')->where('tenant_id', $tenantId)->get();
foreach ($schedules as $s) {
    echo "Schedule ID: {$s->id} | Number: {$s->schedule_number} | PO ID: {$s->production_order_id} | Status: {$s->status} | Start: {$s->start_date} | End: {$s->end_date}" . PHP_EOL;
}

// 5. WIP & Receipts
echo PHP_EOL . "--- WIP & Receipts ---" . PHP_EOL;
$wips = DB::table('production_wips')->where('tenant_id', $tenantId)->get();
foreach ($wips as $w) {
    echo "WIP ID: {$w->id} | PO ID: {$w->production_order_id} | Current Op: {$w->current_operation_id} | Status: {$w->status}" . PHP_EOL;
}
$receipts = DB::table('production_order_receipts')->where('tenant_id', $tenantId)->get();
foreach ($receipts as $rc) {
    echo "Receipt ID: {$rc->id} | PO ID: {$rc->production_order_id} | Qty: {$rc->quantity_received} | Date: {$rc->receipt_date}" . PHP_EOL;
}
