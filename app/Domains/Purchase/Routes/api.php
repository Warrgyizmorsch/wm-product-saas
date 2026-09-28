<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Purchase\Controllers\Api\VendorApiController;
use App\Domains\Purchase\Controllers\Api\PurchaseRequisitionApiController;
use App\Domains\Purchase\Controllers\Api\PurchaseRfqApiController;
use App\Domains\Purchase\Controllers\Api\PurchaseOrderApiController;
use App\Domains\Purchase\Controllers\Api\GoodsReceiptNoteApiController;
use App\Domains\Purchase\Controllers\Api\LandedCostApiController;
use App\Domains\Purchase\Controllers\Api\VendorBillApiController;
use App\Domains\Purchase\Controllers\Api\VendorPaymentApiController;
use App\Domains\Purchase\Controllers\Api\PurchaseAdvancePaymentApiController;
use App\Domains\Purchase\Controllers\Api\PurchaseReturnApiController;

/*
|--------------------------------------------------------------------------
| Purchase Domain REST API Kit Routes
|--------------------------------------------------------------------------
| Base Prefix: /api/purchase
| Protected by: auth:sanctum, company, branch
|--------------------------------------------------------------------------
*/

Route::prefix('api/purchase')->name('api.purchase.')->group(function () {

    // Vendor APIs
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/meta', [VendorApiController::class, 'meta'])->name('meta');
        Route::get('/export', [VendorApiController::class, 'export'])->name('export');
        Route::post('/quick-create', [VendorApiController::class, 'quickCreate'])->name('quick-create');
        Route::get('/', [VendorApiController::class, 'index'])->name('index');
        Route::post('/', [VendorApiController::class, 'store'])->name('store');
        Route::get('/{id}', [VendorApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [VendorApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [VendorApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [VendorApiController::class, 'toggleStatus'])->name('toggle-status');
    });

    // Purchase Requisition (PR) APIs
    Route::prefix('requisitions')->name('requisitions.')->group(function () {
        Route::get('/meta', [PurchaseRequisitionApiController::class, 'meta'])->name('meta');
        Route::get('/export', [PurchaseRequisitionApiController::class, 'export'])->name('export');
        Route::get('/pending-items', [PurchaseRequisitionApiController::class, 'pendingItems'])->name('pending-items');
        Route::post('/pending-items/create-po', [PurchaseRequisitionApiController::class, 'createPosFromPendingItems'])->name('pending-items.create-po');
        Route::get('/', [PurchaseRequisitionApiController::class, 'index'])->name('index');
        Route::post('/', [PurchaseRequisitionApiController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseRequisitionApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [PurchaseRequisitionApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [PurchaseRequisitionApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/approve', [PurchaseRequisitionApiController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [PurchaseRequisitionApiController::class, 'reject'])->name('reject');
        Route::post('/{id}/remind', [PurchaseRequisitionApiController::class, 'remind'])->name('remind');
    });

    // Request for Quotation (RFQ) APIs
    Route::prefix('rfqs')->name('rfqs.')->group(function () {
        Route::get('/meta', [PurchaseRfqApiController::class, 'meta'])->name('meta');
        Route::get('/export', [PurchaseRfqApiController::class, 'export'])->name('export');
        Route::get('/savings-dashboard', [PurchaseRfqApiController::class, 'savingsDashboard'])->name('savings-dashboard');
        Route::get('/savings-details/{orderId}', [PurchaseRfqApiController::class, 'poSavingsDetails'])->name('savings-details');
        Route::get('/get-requisition-items', [PurchaseRfqApiController::class, 'getRequisitionItems'])->name('get-requisition-items');
        Route::get('/', [PurchaseRfqApiController::class, 'index'])->name('index');
        Route::post('/', [PurchaseRfqApiController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseRfqApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [PurchaseRfqApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [PurchaseRfqApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/store-quotes', [PurchaseRfqApiController::class, 'storeQuotes'])->name('store-quotes');
        Route::post('/{id}/confirm', [PurchaseRfqApiController::class, 'confirmRfq'])->name('confirm');
        Route::post('/{id}/create-po', [PurchaseRfqApiController::class, 'createPo'])->name('create-po');
    });

    // Purchase Order (PO) APIs
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/meta', [PurchaseOrderApiController::class, 'meta'])->name('meta');
        Route::get('/export', [PurchaseOrderApiController::class, 'export'])->name('export');
        Route::get('/approvals', [PurchaseOrderApiController::class, 'poApprovals'])->name('approvals');
        Route::get('/get-requisition-items', [PurchaseOrderApiController::class, 'getRequisitionItems'])->name('get-requisition-items');
        Route::get('/', [PurchaseOrderApiController::class, 'index'])->name('index');
        Route::post('/', [PurchaseOrderApiController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseOrderApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [PurchaseOrderApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/approve', [PurchaseOrderApiController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [PurchaseOrderApiController::class, 'reject'])->name('reject');
        Route::post('/{id}/remind', [PurchaseOrderApiController::class, 'remind'])->name('remind');
        Route::post('/{id}/convert-to-grn', [PurchaseOrderApiController::class, 'convertToGrn'])->name('convert-to-grn');
        Route::post('/{id}/convert-to-bill', [PurchaseOrderApiController::class, 'convertToBill'])->name('convert-to-bill');
        Route::delete('/{id}', [PurchaseOrderApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [PurchaseOrderApiController::class, 'restore'])->name('restore');
    });

    // Goods Receipt Note (GRN) APIs
    Route::prefix('grns')->name('grns.')->group(function () {
        Route::get('/export', [GoodsReceiptNoteApiController::class, 'export'])->name('export');
        Route::get('/pending', [GoodsReceiptNoteApiController::class, 'indexPending'])->name('pending');
        Route::get('/get-po-items/{poId}', [GoodsReceiptNoteApiController::class, 'getPurchaseOrderItems'])->name('get-po-items');
        Route::get('/', [GoodsReceiptNoteApiController::class, 'index'])->name('index');
        Route::post('/', [GoodsReceiptNoteApiController::class, 'store'])->name('store');
        Route::get('/{id}', [GoodsReceiptNoteApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [GoodsReceiptNoteApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/approve', [GoodsReceiptNoteApiController::class, 'approve'])->name('approve');
    });

    // Landed Cost Voucher APIs
    Route::prefix('landed-costs')->name('landed-costs.')->group(function () {
        Route::get('/get-grn-items', [LandedCostApiController::class, 'getGrnItems'])->name('get-grn-items');
        Route::get('/', [LandedCostApiController::class, 'index'])->name('index');
        Route::post('/', [LandedCostApiController::class, 'store'])->name('store');
        Route::get('/{id}', [LandedCostApiController::class, 'show'])->name('show');
        Route::post('/{id}/post', [LandedCostApiController::class, 'post'])->name('post');
        Route::delete('/{id}', [LandedCostApiController::class, 'destroy'])->name('destroy');
    });

    // Vendor Bill APIs
    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('/meta', [VendorBillApiController::class, 'meta'])->name('meta');
        Route::get('/export', [VendorBillApiController::class, 'export'])->name('export');
        Route::get('/pending', [VendorBillApiController::class, 'pendingGrns'])->name('pending');
        Route::get('/pending-freight', [VendorBillApiController::class, 'pendingFreight'])->name('pending-freight');
        Route::post('/store-service', [VendorBillApiController::class, 'storeService'])->name('store-service');
        Route::get('/', [VendorBillApiController::class, 'index'])->name('index');
        Route::post('/', [VendorBillApiController::class, 'store'])->name('store');
        Route::get('/{id}', [VendorBillApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [VendorBillApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/apply-advance', [VendorBillApiController::class, 'applyAdvance'])->name('apply-advance');
        Route::delete('/{id}', [VendorBillApiController::class, 'destroy'])->name('destroy');
    });

    // Vendor Payment APIs
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/meta', [VendorPaymentApiController::class, 'meta'])->name('meta');
        Route::get('/export', [VendorPaymentApiController::class, 'export'])->name('export');
        Route::get('/', [VendorPaymentApiController::class, 'index'])->name('index');
        Route::post('/', [VendorPaymentApiController::class, 'store'])->name('store');
        Route::get('/{id}', [VendorPaymentApiController::class, 'show'])->name('show');
        Route::delete('/{id}', [VendorPaymentApiController::class, 'destroy'])->name('destroy');
    });

    // Purchase Advance Payment APIs
    Route::prefix('advances')->name('advances.')->group(function () {
        Route::get('/', [PurchaseAdvancePaymentApiController::class, 'index'])->name('index');
        Route::post('/', [PurchaseAdvancePaymentApiController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseAdvancePaymentApiController::class, 'show'])->name('show');
    });

    // Purchase Return / Debit Note APIs
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/meta', [PurchaseReturnApiController::class, 'meta'])->name('meta');
        Route::get('/export', [PurchaseReturnApiController::class, 'export'])->name('export');
        Route::get('/', [PurchaseReturnApiController::class, 'index'])->name('index');
        Route::post('/', [PurchaseReturnApiController::class, 'store'])->name('store');
        Route::get('/{id}', [PurchaseReturnApiController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [PurchaseReturnApiController::class, 'approve'])->name('approve');
    });

});
