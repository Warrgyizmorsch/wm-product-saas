<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$p = \App\Domains\Inventory\Models\Product::find(17);
if ($p) {
    echo "Product: " . $p->name . "\n";
    echo "attributes_config: " . json_encode($p->attributes_config, JSON_PRETTY_PRINT) . "\n";
    echo "variation_type: " . $p->variation_type . "\n";
    echo "variants count: " . $p->variants()->count() . "\n";
    foreach ($p->variants as $v) {
        echo "  variant: " . $v->name . " | variant_values: " . json_encode($v->variant_values) . "\n";
    }
} else {
    echo "Product 17 not found\n";
    // list some products
    $prods = \App\Domains\Inventory\Models\Product::where('variation_type','Variant')->whereNull('parent_id')->take(3)->get();
    foreach ($prods as $pr) {
        echo "ID: {$pr->id} | {$pr->name} | attributes_config: " . json_encode($pr->attributes_config) . "\n";
    }
}
