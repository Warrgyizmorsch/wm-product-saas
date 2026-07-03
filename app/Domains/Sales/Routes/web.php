<?php

use App\Domains\Sales\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')
    ->as('sales.')
    ->group(function (): void {
        Route::get('orders', [SalesOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [SalesOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [SalesOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [SalesOrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/edit', [SalesOrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}', [SalesOrderController::class, 'update'])->name('orders.update');
        Route::delete('orders/{order}', [SalesOrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('orders/{order}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/ship', [SalesOrderController::class, 'ship'])->name('orders.ship');
        Route::post('orders/{order}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');
    });
