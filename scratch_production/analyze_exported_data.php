<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tenantId = 1;

$data = json_decode(file_get_contents(__DIR__ . '/exported_live_production_data.json'), true);

echo "=== SUMMARY OF EXPORTED PRODUCTION DATA ===" . PHP_EOL;

// 1. Products map
$prodMap = DB::table('products')->where('tenant_id', $tenantId)->pluck('sku', 'id')->toArray();
$uomMap = DB::table('uoms')->where('tenant_id', $tenantId)->pluck('code', 'id')->toArray();

echo PHP_EOL . "--- Routings ---" . PHP_EOL;
foreach ($data['routings'] as $r) {
    $ops = array_filter($data['production_routing_operations'], fn($op) => $op['routing_id'] == $r['id']);
    echo "Routing: {$r['routing_number']} ({$r['name']}) -> " . count($ops) . " operations. Status: {$r['status']}" . PHP_EOL;
    foreach ($ops as $op) {
        $mats = array_filter($data['production_routing_operation_materials'], fn($m) => $m['routing_operation_id'] == $op['id']);
        echo "   Op: {$op['operation_number']} ({$op['name']}) | WC: {$op['work_center_id']} | Sec: {$op['section_id']} | Mah: {$op['machine_id']} | Batch: {$op['section_manual_production_id']} | Materials: " . count($mats) . PHP_EOL;
    }
}

echo PHP_EOL . "--- BOMs ---" . PHP_EOL;
foreach ($data['production_boms'] as $b) {
    $pSku = $prodMap[$b['product_id']] ?? "Unknown-ID-{$b['product_id']}";
    $items = array_filter($data['production_bom_items'], fn($item) => $item['bom_id'] == $b['id']);
    echo "BOM: {$b['bom_number']} (v{$b['version']}, status: {$b['status']}) for Product: $pSku (Base Qty: {$b['base_quantity']}) -> " . count($items) . " items. Routing ID: {$b['routing_id']}" . PHP_EOL;
    foreach ($items as $it) {
        $matSku = $prodMap[$it['material_id']] ?? "Unknown-ID-{$it['material_id']}";
        $uomCode = $uomMap[$it['uom_id']] ?? "UOM-{$it['uom_id']}";
        echo "   Item: $matSku | Qty: {$it['quantity']} $uomCode | Gross: {$it['gross_quantity']}" . PHP_EOL;
    }
}

echo PHP_EOL . "--- Production Orders ---" . PHP_EOL;
foreach ($data['production_orders'] as $po) {
    $pSku = $prodMap[$po['product_id']] ?? "Unknown-ID-{$po['product_id']}";
    $poOps = array_filter($data['production_order_operations'], fn($o) => $o['production_order_id'] == $po['id']);
    $poRes = array_filter($data['production_order_reservations'], fn($r) => $r['production_order_id'] == $po['id']);
    $poIssues = array_filter($data['production_order_issues'], fn($i) => $i['production_order_id'] == $po['id']);
    $poRc = array_filter($data['production_order_receipts'], fn($rc) => $rc['production_order_id'] == $po['id']);
    $poReq = array_filter($data['production_requisition_slips'], fn($req) => $req['production_order_id'] == $po['id']);
    $poSched = array_filter($data['production_schedules'], fn($sch) => $sch['production_order_id'] == $po['id']);
    $poWip = array_filter($data['production_wips'], fn($w) => $w['production_order_id'] == $po['id']);

    echo "PO: {$po['order_number']} | Product: $pSku | Status: {$po['status']} | Ordered: {$po['quantity_ordered']} | Produced: {$po['quantity_produced']}" . PHP_EOL;
    echo "   Ops: " . count($poOps) . " | Reservations: " . count($poRes) . " | Issues: " . count($poIssues) . " | Receipts: " . count($poRc) . " | Requisitions: " . count($poReq) . " | Schedules: " . count($poSched) . " | WIP: " . count($poWip) . PHP_EOL;
}
