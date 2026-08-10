<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = \App\Domains\Inventory\Models\Product::where('sku', 'PR-RM-ALU-FIN-01')->first();
if ($p) {
    $stockSum = \App\Domains\Inventory\Models\ProductWarehouseStock::where('product_id', $p->id)->sum('quantity');
    $txCount = \App\Domains\Inventory\Models\StockTransaction::where('product_id', $p->id)->count();
    $txs = \App\Domains\Inventory\Models\StockTransaction::where('product_id', $p->id)->get();
    echo "SKU: {$p->sku}\n";
    echo "Opening Stock: {$p->opening_stock}\n";
    echo "ProductWarehouseStock Total Qty: {$stockSum}\n";
    echo "StockTransaction Count: {$txCount}\n";
    foreach ($txs as $t) {
        echo " - TX #{$t->id}: {$t->type} Qty {$t->quantity} Ref: {$t->reference_type} WH: {$t->warehouse_id}\n";
    }
} else {
    echo "Product not found\n";
}
