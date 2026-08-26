<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// If there's an MR item with ordered quantity, let's ensure its reservation is active
$mrItem = DB::table('material_requirement_items')->where('product_id', 7)->first();
if ($mrItem) {
    // Reset quantity_reserved to 8 (since 2 was dispatched and shipped)
    DB::table('material_requirement_items')->where('id', $mrItem->id)->update(['quantity_reserved' => 8]);

    DB::table('stock_reservations')->where('reference_type', 'DeliveryOrder')->where('reference_id', $mrItem->material_requirement_id)->update([
        'reserved_qty' => 8,
        'status' => 'Active',
    ]);
}

echo "Reservations updated successfully!\n";
print_r(DB::table('stock_reservations')->get()->toArray());
