<?php

use App\Domains\Sales\Controllers\SalesOrderController;
use App\Domains\Sales\Controllers\MaterialRequestController;
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
        Route::get('orders/{order}/download', [SalesOrderController::class, 'downloadPdf'])->name('orders.download');

        // Material Requirements Routes
        Route::get('material-requirements', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'index'])->name('material-requirements.index');
        Route::get('material-requirements/create', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'create'])->name('material-requirements.create');
        Route::post('material-requirements', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'store'])->name('material-requirements.store');
        Route::get('material-requirements/{delivery}', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'show'])->name('material-requirements.show');
        Route::post('material-requirements/{delivery}/ship', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'ship'])->name('material-requirements.ship');
        Route::post('material-requirements/{delivery}/cancel', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'cancel'])->name('material-requirements.cancel');

        Route::post('material-requirements/items/{itemId}/warehouse', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'updateWarehouse'])->name('material-requirements.update-warehouse');
        Route::post('material-requirements/items/{itemId}/reserve', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'reserveQty'])->name('material-requirements.reserve-qty');
        Route::post('material-requirements/items/{itemId}/indent', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'mockIndent'])->name('material-requirements.mock-indent');
        Route::post('material-requirements/items/{itemId}/mo', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'mockMo'])->name('material-requirements.mock-mo');
        Route::post('material-requirements/{delivery}/picking', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'startPicking'])->name('material-requirements.picking');
        Route::post('material-requirements/{delivery}/pack', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'pack'])->name('material-requirements.pack');
        Route::post('material-requirements/{delivery}/dispatch', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'dispatch'])->name('material-requirements.dispatch');
        Route::post('material-requirements/{delivery}/dispatch-order', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'storeDispatchOrder'])->name('material-requirements.dispatch-order.store');
        Route::post('material-requirements/{delivery}/deliver', [\App\Domains\Sales\Controllers\MaterialRequirementController::class, 'deliver'])->name('material-requirements.deliver');

        // Material Requests (Prod) Routes
        Route::get('material-requests', [MaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::get('material-requests/{id}', [MaterialRequestController::class, 'show'])->name('material-requests.show');
        Route::post('material-requests/items/{id}/reserve', [MaterialRequestController::class, 'reserve'])->name('material-requests.reserve');
        Route::post('material-requests/items/{id}/issue', [MaterialRequestController::class, 'issue'])->name('material-requests.issue');
        Route::post('material-requests/items/{id}/create-pr', [MaterialRequestController::class, 'createPurchaseRequisition'])->name('material-requests.create-pr');
        Route::post('material-requests/{id}/bulk-action', [MaterialRequestController::class, 'bulkAction'])->name('material-requests.bulk-action');

        // Dispatch Orders Routes
        Route::get('dispatches', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'index'])->name('dispatches.index');
        Route::get('dispatches/create', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'create'])->name('dispatches.create');
        Route::post('dispatches', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'store'])->name('dispatches.store');
        Route::get('dispatches/material-requirements', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'pendingMaterialRequirements'])->name('dispatches.pending-mr');
        Route::get('dispatches/warehouse/{warehouse}/address', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'warehouseAddress'])->name('dispatches.warehouse-address');
        Route::get('dispatches/{dispatch}', [\App\Domains\Sales\Controllers\DispatchOrderController::class, 'show'])->name('dispatches.show');



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
