<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Purchase\Controllers\PurchaseRequisitionController;
use App\Domains\Purchase\Controllers\PurchaseRfqController;
use App\Domains\Purchase\Controllers\PurchaseOrderController;
use App\Domains\Purchase\Controllers\GoodsReceiptNoteController;
use App\Domains\Purchase\Controllers\PurchaseAdvancePaymentController;
use App\Domains\Purchase\Controllers\VendorBillController;
use App\Domains\Purchase\Controllers\VendorPaymentController;

use App\Domains\Purchase\Controllers\LandedCostController;

Route::prefix('purchase')
    ->as('purchase.')
    ->group(function (): void {
        Route::get('requisitions/get-source-items', [PurchaseRequisitionController::class, 'getSourceItems'])->name('requisitions.get-source-items');
        Route::get('requisitions/pending-items', [PurchaseRequisitionController::class, 'pendingItems'])->name('requisitions.pending-items');
        Route::post('requisitions/pending-items/create-po', [PurchaseRequisitionController::class, 'createPosFromPendingItems'])->name('requisitions.pending-items.create-po');
        Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::get('requisitions/{requisition}/detail-partial', [PurchaseRequisitionController::class, 'detailPartial'])->name('requisitions.detail-partial');
        Route::get('pr-approvals', [PurchaseRequisitionController::class, 'prApprovals'])->name('pr-approvals.index');
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
        Route::get('orders/{order}/po-detail-partial', [PurchaseOrderController::class, 'poDetailPartial'])->name('orders.po-detail-partial');
        Route::get('po-approvals', [PurchaseOrderController::class, 'poApprovals'])->name('po-approvals.index');
        Route::post('orders/advance-payments', [PurchaseAdvancePaymentController::class, 'store'])->name('orders.advance-payments.store');
        Route::match(['get', 'post'], 'orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::resource('orders', PurchaseOrderController::class);

        Route::get('landed-costs/get-grn-items', [LandedCostController::class, 'getGrnItems'])->name('landed-costs.get-grn-items');
        Route::post('landed-costs/{landed_cost}/post', [LandedCostController::class, 'post'])->name('landed-costs.post');
        Route::resource('landed-costs', LandedCostController::class);

        Route::get('bills/pending', [VendorBillController::class, 'pendingGrns'])->name('bills.pending');
        Route::post('bills/{bill}/apply-advance', [VendorBillController::class, 'applyAdvance'])->name('bills.apply-advance');
        Route::resource('bills', VendorBillController::class);
        Route::resource('payments', VendorPaymentController::class);
        Route::resource('advances', PurchaseAdvancePaymentController::class);
        Route::resource('advance-payments', PurchaseAdvancePaymentController::class);
    });

// Standalone Top-Level GRN Routes (/grns/...)
Route::prefix('grns')
    ->as('grns.')
    ->group(function (): void {
        Route::get('pending', [GoodsReceiptNoteController::class, 'indexPending'])->name('pending');
        Route::get('get-po-items/{po}', [GoodsReceiptNoteController::class, 'getPurchaseOrderItems'])->name('get-po-items');
        Route::post('{grn}/approve', [GoodsReceiptNoteController::class, 'approve'])->name('approve');
        Route::get('{grn}/download', [GoodsReceiptNoteController::class, 'downloadPdf'])->name('download');
        Route::resource('/', GoodsReceiptNoteController::class)->parameters(['' => 'grn']);
    });

// Alias routes for purchase.grns.* backward compatibility
Route::prefix('purchase/grns')
    ->as('purchase.grns.')
    ->group(function (): void {
        Route::get('pending', fn() => redirect()->route('grns.pending'))->name('pending');
        Route::get('get-po-items/{po}', [GoodsReceiptNoteController::class, 'getPurchaseOrderItems'])->name('get-po-items');
        Route::post('{grn}/approve', [GoodsReceiptNoteController::class, 'approve'])->name('approve');
        Route::get('{grn}/download', [GoodsReceiptNoteController::class, 'downloadPdf'])->name('download');
        Route::resource('/', GoodsReceiptNoteController::class)->parameters(['' => 'grn']);
    });
