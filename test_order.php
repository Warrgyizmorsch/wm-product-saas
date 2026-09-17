<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$order = \App\Domains\Production\Models\ProductionOrder::where('order_number', 'ORD-2026-000001')->with('operations')->first();
if ($order) {
    echo "ORDER ID: {$order->id} | NUM: {$order->order_number} | PRODUCT_ID: {$order->product_id}\n";
    foreach ($order->operations as $op) {
        echo "  - OP ID: {$op->id} | NAME: {$op->operation_name} | SOURCE_PRODUCT_ID: {$op->source_product_id} | PRODUCT_ID: {$op->product_id}\n";
    }
}
$product = \App\Domains\Production\Models\Product::find($order->product_id);
echo "Order Product: " . ($product ? $product->name : 'N/A') . " (ID: {$order->product_id})\n";

foreach (\App\Domains\Production\Models\Product::whereIn('id', [150, 151, 152, 153])->get() as $p) {
    echo "PRODUCT {$p->id}: {$p->name} ({$p->code})\n";
}

unlink(__FILE__);
