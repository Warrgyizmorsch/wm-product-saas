<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Purchase\Controllers\PurchaseRequisitionController;
use App\Domains\Purchase\Controllers\PurchaseRfqController;
use App\Domains\Purchase\Controllers\PurchaseOrderController;
use App\Domains\Purchase\Controllers\GoodsReceiptNoteController;

Route::prefix('purchase')
    ->as('purchase.')
    ->group(function (): void {
        Route::get('requisitions/get-source-items', [PurchaseRequisitionController::class, 'getSourceItems'])->name('requisitions.get-source-items');
        Route::get('requisitions/pending-items', [PurchaseRequisitionController::class, 'pendingItems'])->name('requisitions.pending-items');
        Route::post('requisitions/pending-items/create-po', [PurchaseRequisitionController::class, 'createPosFromPendingItems'])->name('requisitions.pending-items.create-po');
        Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::resource('requisitions', PurchaseRequisitionController::class);

        Route::get('rfqs/{rfq}/enter-quotes', [PurchaseRfqController::class, 'enterQuotes'])->name('rfqs.enter-quotes');
        Route::post('rfqs/{rfq}/store-quotes', [PurchaseRfqController::class, 'storeQuotes'])->name('rfqs.store-quotes');
        Route::post('rfqs/{rfq}/send', [PurchaseRfqController::class, 'sendRfq'])->name('rfqs.send');
        Route::post('rfqs/{rfq}/confirm', [PurchaseRfqController::class, 'confirmRfq'])->name('rfqs.confirm');
        Route::post('rfqs/{rfq}/create-po', [PurchaseRfqController::class, 'createPo'])->name('rfqs.create-po');
        Route::post('rfqs/{rfq}/save-comparison', [PurchaseRfqController::class, 'saveComparison'])->name('rfqs.save-comparison');
        Route::get('rfqs/savings-dashboard', [PurchaseRfqController::class, 'savingsDashboard'])->name('rfqs.savings');
        Route::get('rfqs/savings-details/{order}', [PurchaseRfqController::class, 'poSavingsDetails'])->name('rfqs.savings-details');
        Route::get('rfqs/get-requisition-items', [PurchaseRfqController::class, 'getRequisitionItems'])->name('rfqs.get-requisition-items');
        Route::resource('rfqs', PurchaseRfqController::class);

        Route::get('orders/get-requisition-items', [PurchaseOrderController::class, 'getRequisitionItems'])->name('orders.get-requisition-items');
        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [PurchaseOrderController::class, 'reject'])->name('orders.reject');
        Route::get('orders/{order}/download', [PurchaseOrderController::class, 'downloadPdf'])->name('orders.download');
        Route::post('orders/advance-payments', [\App\Domains\Purchase\Controllers\PurchaseAdvancePaymentController::class, 'store'])->name('orders.advance-payments.store');
        Route::match(['get', 'post'], 'orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::resource('orders', PurchaseOrderController::class);

        Route::get('grns/pending', [GoodsReceiptNoteController::class, 'indexPending'])->name('grns.pending');
        Route::get('grns/get-po-items/{po}', [GoodsReceiptNoteController::class, 'getPurchaseOrderItems'])->name('grns.get-po-items');
        Route::post('grns/{grn}/approve', [GoodsReceiptNoteController::class, 'approve'])->name('grns.approve');
        Route::get('grns/{grn}/download', [GoodsReceiptNoteController::class, 'downloadPdf'])->name('grns.download');
        Route::resource('grns', GoodsReceiptNoteController::class);

        Route::resource('bills', \App\Domains\Purchase\Controllers\VendorBillController::class);
        Route::resource('payments', \App\Domains\Purchase\Controllers\VendorPaymentController::class);
    });

