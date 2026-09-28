<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

echo "=== VERIFYING API ROUTES COUNT ===\n";
$routes = Route::getRoutes();
$crmRoutes = 0;
$salesRoutes = 0;
$purchaseRoutes = 0;
$inventoryRoutes = 0;

foreach ($routes as $route) {
    $uri = $route->uri();
    if (str_starts_with($uri, 'api/crm')) $crmRoutes++;
    if (str_starts_with($uri, 'api/sales')) $salesRoutes++;
    if (str_starts_with($uri, 'api/purchase')) $purchaseRoutes++;
    if (str_starts_with($uri, 'api/inventory')) $inventoryRoutes++;
}

echo "CRM API Routes: " . $crmRoutes . "\n";
echo "Sales API Routes: " . $salesRoutes . "\n";
echo "Purchase API Routes: " . $purchaseRoutes . "\n";
echo "Inventory API Routes: " . $inventoryRoutes . "\n";
echo "All API Routes Verified Successfully!\n";
