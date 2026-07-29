<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = app(\App\Domains\Inventory\Repositories\ProductRepository::class);
$product = \App\Domains\Inventory\Models\Product::find(35);
$details = $repo->findWithDetails($product);
$warehouses = \App\Domains\Inventory\Models\Warehouse::all();

$html = view('modules.inventory.products.show', ['product' => $details, 'warehouses' => $warehouses])->render();
echo "BLADE RENDER SUCCESSFUL! LENGTH: " . strlen($html);
