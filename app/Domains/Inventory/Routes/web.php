<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Inventory\Controllers\ProductController;
use App\Domains\Inventory\Controllers\UomController;

Route::prefix('inventory')
    ->as('inventory.')
    ->group(function (): void {
        Route::get('products/create', function () {
            return view('modules.inventory.products.create');
        })->name('products.create');
    });

Route::post('products/quick-create', [ProductController::class, 'quickCreate'])
    ->name('products.quick-create');
Route::post('uoms/quick-create', [UomController::class, 'quickCreate'])
    ->name('uoms.quick-create');
