<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;

$plans = ProductionQualityPlan::with('parameters')->get();

echo "Total Quality Plans: " . $plans->count() . "\n";

foreach ($plans as $p) {
    echo "----------------------------------------\n";
    echo "ID: {$p->id} | Name: {$p->name} | Status: {$p->status} | Type: {$p->type}\n";
    echo "Parameters Count: " . $p->parameters->count() . "\n";
    foreach ($p->parameters as $param) {
        echo "  - Param ID: {$param->id} | Name: {$param->name} | Type: {$param->type} | Min: {$param->min_value} | Max: {$param->max_value} | Mandatory: {$param->is_mandatory}\n";
    }
}
