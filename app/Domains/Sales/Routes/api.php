<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Sales\Controllers\Api\CustomerApiController;
use App\Domains\Sales\Controllers\Api\SalesOrderApiController;
use App\Domains\Sales\Controllers\Api\InvoiceApiController;
use App\Domains\Sales\Controllers\Api\CustomerPaymentApiController;
use App\Domains\Sales\Controllers\Api\DispatchOrderApiController;
use App\Domains\Sales\Controllers\Api\SalesReturnApiController;
use App\Domains\Sales\Controllers\Api\SalesSettingsApiController;
use App\Domains\Sales\Controllers\Api\EInvoiceApiController;

/*
|--------------------------------------------------------------------------
| Sales Domain REST API Kit Routes
|--------------------------------------------------------------------------
| Base Prefix: /api/sales
| Protected by: auth:sanctum, company, branch
|--------------------------------------------------------------------------
*/

Route::prefix('api/sales')->name('api.sales.')->group(function () {

    // Sales Module Settings APIs
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SalesSettingsApiController::class, 'index'])->name('index');
        Route::post('/', [SalesSettingsApiController::class, 'update'])->name('update');
    });

    // Customer APIs
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/meta', [CustomerApiController::class, 'meta'])->name('meta');
        Route::get('/', [CustomerApiController::class, 'index'])->name('index');
        Route::post('/', [CustomerApiController::class, 'store'])->name('store');
        Route::get('/{id}', [CustomerApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [CustomerApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [CustomerApiController::class, 'destroy'])->name('destroy');
    });

    // Sales Orders APIs
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/meta', [SalesOrderApiController::class, 'meta'])->name('meta');
        Route::get('/export', [SalesOrderApiController::class, 'export'])->name('export');
        Route::get('/', [SalesOrderApiController::class, 'index'])->name('index');
        Route::post('/', [SalesOrderApiController::class, 'store'])->name('store');
        Route::get('/{id}', [SalesOrderApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [SalesOrderApiController::class, 'update'])->name('update');
        Route::patch('/{id}/status', [SalesOrderApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/confirm', [SalesOrderApiController::class, 'confirm'])->name('confirm');
        Route::post('/{id}/cancel', [SalesOrderApiController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/convert-to-invoice', [SalesOrderApiController::class, 'convertToInvoice'])->name('convert-to-invoice');
        Route::post('/{id}/convert-to-dispatch', [SalesOrderApiController::class, 'convertToDispatch'])->name('convert-to-dispatch');
        Route::delete('/{id}', [SalesOrderApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [SalesOrderApiController::class, 'restore'])->name('restore');
    });

    // Invoices APIs
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/meta', [InvoiceApiController::class, 'meta'])->name('meta');
        Route::get('/export', [InvoiceApiController::class, 'export'])->name('export');
        Route::get('/', [InvoiceApiController::class, 'index'])->name('index');
        Route::post('/', [InvoiceApiController::class, 'store'])->name('store');
        Route::get('/{id}', [InvoiceApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [InvoiceApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/post', [InvoiceApiController::class, 'post'])->name('post');
        Route::post('/{id}/pay', [InvoiceApiController::class, 'pay'])->name('pay');
        Route::post('/{id}/send-email', [InvoiceApiController::class, 'sendEmail'])->name('send-email');
        Route::post('/{id}/send-whatsapp', [InvoiceApiController::class, 'sendWhatsApp'])->name('send-whatsapp');
        Route::delete('/{id}', [InvoiceApiController::class, 'destroy'])->name('destroy');

        // E-Invoice & E-Way Bill
        Route::post('/{id}/einvoice/generate', [EInvoiceApiController::class, 'generateEInvoice'])->name('einvoice.generate');
        Route::post('/{id}/einvoice/cancel', [EInvoiceApiController::class, 'cancelEInvoice'])->name('einvoice.cancel');
        Route::get('/{id}/einvoice/export-json', [EInvoiceApiController::class, 'exportJson'])->name('einvoice.export-json');
        Route::post('/{id}/eway-bill/generate', [EInvoiceApiController::class, 'generateEWayBill'])->name('eway-bill.generate');
        Route::post('/{id}/eway-bill/cancel', [EInvoiceApiController::class, 'cancelEWayBill'])->name('eway-bill.cancel');
    });

    // Customer Payments APIs
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/meta', [CustomerPaymentApiController::class, 'meta'])->name('meta');
        Route::get('/export', [CustomerPaymentApiController::class, 'export'])->name('export');
        Route::get('/', [CustomerPaymentApiController::class, 'index'])->name('index');
        Route::post('/', [CustomerPaymentApiController::class, 'store'])->name('store');
        Route::get('/{id}', [CustomerPaymentApiController::class, 'show'])->name('show');
        Route::post('/{id}/confirm', [CustomerPaymentApiController::class, 'confirm'])->name('confirm');
        Route::delete('/{id}', [CustomerPaymentApiController::class, 'destroy'])->name('destroy');
    });

    // Dispatch Orders / Challans APIs
    Route::prefix('dispatches')->name('dispatches.')->group(function () {
        Route::get('/meta', [DispatchOrderApiController::class, 'meta'])->name('meta');
        Route::get('/available-serials', [DispatchOrderApiController::class, 'getAvailableSerials'])->name('available-serials');
        Route::get('/available-batches', [DispatchOrderApiController::class, 'getAvailableBatches'])->name('available-batches');
        Route::get('/export', [DispatchOrderApiController::class, 'export'])->name('export');
        Route::get('/', [DispatchOrderApiController::class, 'index'])->name('index');
        Route::post('/', [DispatchOrderApiController::class, 'store'])->name('store');
        Route::get('/{id}', [DispatchOrderApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [DispatchOrderApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/confirm', [DispatchOrderApiController::class, 'confirm'])->name('confirm');
        Route::post('/{id}/ship', [DispatchOrderApiController::class, 'ship'])->name('ship');
        Route::post('/{id}/update-tracking', [DispatchOrderApiController::class, 'updateTracking'])->name('update-tracking');
        Route::post('/{id}/pod', [DispatchOrderApiController::class, 'uploadPod'])->name('pod');
    });

    // Sales Returns APIs
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/meta', [SalesReturnApiController::class, 'meta'])->name('meta');
        Route::get('/export', [SalesReturnApiController::class, 'export'])->name('export');
        Route::get('/', [SalesReturnApiController::class, 'index'])->name('index');
        Route::post('/', [SalesReturnApiController::class, 'store'])->name('store');
        Route::get('/{id}', [SalesReturnApiController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [SalesReturnApiController::class, 'approve'])->name('approve');
    });

    // Material Requests (Production Requisition Slips) APIs
    Route::prefix('material-requests')->name('material-requests.')->group(function () {
        Route::get('/export', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'export'])->name('export');
        Route::get('/', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'show'])->name('show');
        Route::post('/items/{itemId}/reserve', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'reserve'])->name('reserve');
        Route::post('/items/{itemId}/issue', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'issue'])->name('issue');
        Route::post('/items/{itemId}/create-pr', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'createPurchaseRequisition'])->name('create-pr');
        Route::post('/{id}/bulk-action', [\App\Domains\Sales\Controllers\Api\MaterialRequestApiController::class, 'bulkAction'])->name('bulk-action');
    });

});
