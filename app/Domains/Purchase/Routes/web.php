<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Purchase\Controllers\PurchaseRequisitionController;
use App\Domains\Purchase\Controllers\PurchaseRfqController;

Route::prefix('purchase')
    ->as('purchase.')
    ->group(function (): void {
        Route::get('requisitions/get-source-items', [PurchaseRequisitionController::class, 'getSourceItems'])->name('requisitions.get-source-items');
        Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::resource('requisitions', PurchaseRequisitionController::class);

        Route::get('rfqs/{rfq}/enter-quotes', [PurchaseRfqController::class, 'enterQuotes'])->name('rfqs.enter-quotes');
        Route::post('rfqs/{rfq}/store-quotes', [PurchaseRfqController::class, 'storeQuotes'])->name('rfqs.store-quotes');
        Route::post('rfqs/{rfq}/send', [PurchaseRfqController::class, 'sendRfq'])->name('rfqs.send');
        Route::post('rfqs/{rfq}/confirm', [PurchaseRfqController::class, 'confirmRfq'])->name('rfqs.confirm');
        Route::post('rfqs/{rfq}/save-comparison', [PurchaseRfqController::class, 'saveComparison'])->name('rfqs.save-comparison');
        Route::get('rfqs/get-requisition-items', [PurchaseRfqController::class, 'getRequisitionItems'])->name('rfqs.get-requisition-items');
        Route::resource('rfqs', PurchaseRfqController::class);
    });
