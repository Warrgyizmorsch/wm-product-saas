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
        Route::post('orders/{order}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');

        // Delivery Orders Routes
        Route::get('deliveries', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/create', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'create'])->name('deliveries.create');
        Route::post('deliveries', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{delivery}/ship', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'ship'])->name('deliveries.ship');
        Route::post('deliveries/{delivery}/cancel', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'cancel'])->name('deliveries.cancel');
    });
