<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STOCK RESERVATIONS ===\n";
print_r(DB::table('stock_reservations')->get()->toArray());

echo "\n=== MATERIAL REQUIREMENTS ===\n";
print_r(DB::table('material_requirements')->select('id', 'mr_number', 'status')->get()->toArray());

echo "\n=== MATERIAL REQUIREMENT ITEMS ===\n";
print_r(DB::table('material_requirement_items')->select('id', 'material_requirement_id', 'product_id', 'quantity', 'quantity_reserved', 'status')->get()->toArray());

echo "\n=== DISPATCH ORDERS ===\n";
print_r(DB::table('dispatch_orders')->select('id', 'dispatch_number', 'material_requirement_id', 'status')->get()->toArray());

echo "\n=== DISPATCH ORDER ITEMS ===\n";
print_r(DB::table('dispatch_order_items')->select('id', 'dispatch_order_id', 'product_id', 'quantity_ordered', 'quantity_dispatched', 'status')->get()->toArray());
