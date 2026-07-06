<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Inventory\Controllers\ProductController;
use App\Domains\Inventory\Controllers\UomController;
use App\Domains\Inventory\Controllers\WarehouseController;

Route::prefix('inventory')
    ->as('inventory.')
    ->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('products/{product}/opening-stock', [ProductController::class, 'openingStock'])->name('products.opening-stock');
        Route::post('products/{product}/opening-stock', [ProductController::class, 'saveOpeningStock'])->name('products.opening-stock.save');

        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    });

Route::post('products/quick-create', [ProductController::class, 'quickCreate'])
    ->name('products.quick-create');
Route::post('uoms/quick-create', [UomController::class, 'quickCreate'])
    ->name('uoms.quick-create');
