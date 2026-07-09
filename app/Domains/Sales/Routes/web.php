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
        Route::get('orders/{order}/plan', [SalesOrderController::class, 'plan'])->name('orders.plan');
        Route::post('orders/{order}/plan', [SalesOrderController::class, 'processPlan'])->name('orders.processPlan');

        // Delivery Orders Routes
        Route::get('deliveries', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/create', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'create'])->name('deliveries.create');
        Route::post('deliveries', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{delivery}/ship', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'ship'])->name('deliveries.ship');
        Route::post('deliveries/{delivery}/cancel', [\App\Domains\Sales\Controllers\DeliveryOrderController::class, 'cancel'])->name('deliveries.cancel');

        // Invoices Routes
        Route::get('invoices', [\App\Domains\Sales\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [\App\Domains\Sales\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [\App\Domains\Sales\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [\App\Domains\Sales\Controllers\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/send', [\App\Domains\Sales\Controllers\InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/pay', [\App\Domains\Sales\Controllers\InvoiceController::class, 'pay'])->name('invoices.pay');

        // Payments Routes
        Route::get('payments', [\App\Domains\Sales\Controllers\CustomerPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [\App\Domains\Sales\Controllers\CustomerPaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [\App\Domains\Sales\Controllers\CustomerPaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}', [\App\Domains\Sales\Controllers\CustomerPaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/confirm', [\App\Domains\Sales\Controllers\CustomerPaymentController::class, 'confirm'])->name('payments.confirm');

        // Returns Routes
        Route::get('returns', [\App\Domains\Sales\Controllers\SalesReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/create', [\App\Domains\Sales\Controllers\SalesReturnController::class, 'create'])->name('returns.create');
        Route::post('returns', [\App\Domains\Sales\Controllers\SalesReturnController::class, 'store'])->name('returns.store');
        Route::get('returns/{return}', [\App\Domains\Sales\Controllers\SalesReturnController::class, 'show'])->name('returns.show');
        Route::post('returns/{return}/complete', [\App\Domains\Sales\Controllers\SalesReturnController::class, 'complete'])->name('returns.complete');
    });
