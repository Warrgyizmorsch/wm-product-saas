<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Inventory\Controllers\Api\ProductApiController;

/*
|--------------------------------------------------------------------------
| Inventory Domain REST API Routes
|--------------------------------------------------------------------------
| Location: app/Domains/Inventory/Routes/api.php
| Automatically loaded by domain route loader in routes/web.php
| Endpoints:
| - GET  /api/inventory/products/export  (Full Product Export API with variants & images)
| - GET  /api/inventory/products         (Alias to Export / List API)
| - POST /api/inventory/products         (Single & Bulk Product Import / Ingest API)
|--------------------------------------------------------------------------
*/

Route::prefix('api/inventory/products')
    ->middleware(['auth:sanctum', 'throttle:120,1'])
    ->name('api.inventory.products.')
    ->group(function () {
        Route::get('/export', [ProductApiController::class, 'export'])->name('export');
        Route::post('/', [ProductApiController::class, 'store'])->name('store');
    });
