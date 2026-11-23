<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Purchase\Controllers\PurchaseRequisitionController;
use App\Domains\Purchase\Controllers\PurchaseRfqController;
use App\Domains\Purchase\Controllers\PurchaseOrderController;
use App\Domains\Purchase\Controllers\GoodsReceiptNoteController;
use App\Domains\Purchase\Controllers\PurchaseAdvancePaymentController;
use App\Domains\Purchase\Controllers\VendorBillController;
use App\Domains\Purchase\Controllers\BillMatchingController;
use App\Domains\Purchase\Controllers\VendorBankChangeController;
use App\Domains\Purchase\Controllers\VendorBranchController;
use App\Domains\Purchase\Controllers\VendorPaymentController;
use App\Domains\Purchase\Controllers\VendorStatusController;
use App\Domains\Purchase\Controllers\VendorVerificationController;
use App\Domains\Purchase\Controllers\PurchaseReturnController;
use App\Domains\Purchase\Controllers\VendorDocumentController;
use App\Domains\Purchase\Controllers\VendorItemController;

use App\Domains\Purchase\Controllers\LandedCostController;

Route::prefix('purchase')
    ->as('purchase.')
    ->group(function (): void {
        Route::get('requisitions/export', [PurchaseRequisitionController::class, 'export'])->name('requisitions.export');
        Route::get('requisitions/get-source-items', [PurchaseRequisitionController::class, 'getSourceItems'])->name('requisitions.get-source-items');
        Route::get('requisitions/pending-items', [PurchaseRequisitionController::class, 'pendingItems'])->name('requisitions.pending-items');
        Route::post('requisitions/pending-items/create-po', [PurchaseRequisitionController::class, 'createPosFromPendingItems'])->name('requisitions.pending-items.create-po');
        Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::post('requisitions/{requisition}/remind', [PurchaseRequisitionController::class, 'remind'])->name('requisitions.remind');
        Route::get('requisitions/{requisition}/detail-partial', [PurchaseRequisitionController::class, 'detailPartial'])->name('requisitions.detail-partial');
        Route::get('pr-approvals', [PurchaseRequisitionController::class, 'prApprovals'])->name('pr-approvals.index');
        Route::resource('requisitions', PurchaseRequisitionController::class);

        Route::get('rfqs/export', [PurchaseRfqController::class, 'export'])->name('rfqs.export');
        Route::get('rfqs/{rfq}/enter-quotes', [PurchaseRfqController::class, 'enterQuotes'])->name('rfqs.enter-quotes');
        Route::post('rfqs/{rfq}/store-quotes', [PurchaseRfqController::class, 'storeQuotes'])->name('rfqs.store-quotes');
        Route::post('rfqs/{rfq}/send', [PurchaseRfqController::class, 'sendRfq'])->name('rfqs.send');
        Route::post('rfqs/vendors/{rfqVendor}/send-whatsapp', [PurchaseRfqController::class, 'sendVendorWhatsapp'])->name('rfqs.send-vendor-whatsapp');
        Route::post('rfqs/{rfq}/confirm', [PurchaseRfqController::class, 'confirmRfq'])->name('rfqs.confirm');
        Route::post('rfqs/{rfq}/create-po', [PurchaseRfqController::class, 'createPo'])->name('rfqs.create-po');
        Route::post('rfqs/{rfq}/save-comparison', [PurchaseRfqController::class, 'saveComparison'])->name('rfqs.save-comparison');
        Route::get('rfqs/savings-dashboard', [PurchaseRfqController::class, 'savingsDashboard'])->name('rfqs.savings');
        Route::get('rfqs/savings-details/{order}', [PurchaseRfqController::class, 'poSavingsDetails'])->name('rfqs.savings-details');
        Route::get('rfqs/get-requisition-items', [PurchaseRfqController::class, 'getRequisitionItems'])->name('rfqs.get-requisition-items');
        Route::resource('rfqs', PurchaseRfqController::class);

        Route::get('orders/export', [PurchaseOrderController::class, 'export'])->name('orders.export');
        Route::get('orders/get-requisition-items', [PurchaseOrderController::class, 'getRequisitionItems'])->name('orders.get-requisition-items');
        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [PurchaseOrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/remind', [PurchaseOrderController::class, 'remind'])->name('orders.remind');
        Route::get('orders/{order}/download', [PurchaseOrderController::class, 'downloadPdf'])->name('orders.download');
        Route::get('orders/{order}/po-detail-partial', [PurchaseOrderController::class, 'poDetailPartial'])->name('orders.po-detail-partial');
        Route::get('po-approvals', [PurchaseOrderController::class, 'poApprovals'])->name('po-approvals.index');
        Route::post('orders/advance-payments', [PurchaseAdvancePaymentController::class, 'store'])->name('orders.advance-payments.store');
        Route::match(['get', 'post'], 'orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::resource('orders', PurchaseOrderController::class)->except(['create']);

        Route::get('landed-costs/get-grn-items', [LandedCostController::class, 'getGrnItems'])->name('landed-costs.get-grn-items');
        Route::post('landed-costs/{landed_cost}/post', [LandedCostController::class, 'post'])->name('landed-costs.post');
        Route::resource('landed-costs', LandedCostController::class);

        Route::get('bills/export', [VendorBillController::class, 'export'])->name('bills.export');
        Route::get('bills/pending', [VendorBillController::class, 'pendingGrns'])->name('bills.pending');
        Route::get('bills/pending-freight', [VendorBillController::class, 'pendingFreight'])->name('bills.pending-freight');
        Route::get('bills/tds-preview', [VendorBillController::class, 'tdsPreview'])->name('bills.tds-preview');
        Route::get('bills/create-service', [VendorBillController::class, 'createService'])->name('bills.create-service');
        Route::post('bills/store-service', [VendorBillController::class, 'storeService'])->name('bills.store-service');
        Route::post('bills/{bill}/apply-advance', [VendorBillController::class, 'applyAdvance'])->name('bills.apply-advance');
        // 3-way match (PO ↔ GRN ↔ bill): exceptions queue, settings, releasing held bills.
        Route::get('bill-matching', [BillMatchingController::class, 'index'])->name('bill-matching.index');
        Route::put('bill-matching/settings', [BillMatchingController::class, 'updateSettings'])->name('bill-matching.settings');
        Route::post('bills/{bill}/release-hold', [BillMatchingController::class, 'release'])->name('bills.release-hold');
        Route::get('bills/{bill}/pdf', [VendorBillController::class, 'downloadPdf'])->name('bills.pdf');
        Route::resource('bills', VendorBillController::class);
        Route::get('payments/export', [VendorPaymentController::class, 'export'])->name('payments.export');
        Route::get('payments/{payment}/download', [VendorPaymentController::class, 'downloadPdf'])->name('payments.download');
        Route::resource('payments', VendorPaymentController::class);
        Route::get('advances/{advance}/download', [PurchaseAdvancePaymentController::class, 'downloadPdf'])->name('advances.download');
        Route::resource('advances', PurchaseAdvancePaymentController::class);
        Route::resource('advance-payments', PurchaseAdvancePaymentController::class);

        Route::get('returns', [PurchaseReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/export', [PurchaseReturnController::class, 'export'])->name('returns.export');
        Route::get('returns/create', [PurchaseReturnController::class, 'create'])->name('returns.create');
        Route::post('returns', [PurchaseReturnController::class, 'store'])->name('returns.store');
        Route::get('returns/{return}/download', [PurchaseReturnController::class, 'downloadPdf'])->name('returns.download');
        Route::get('returns/{return}', [PurchaseReturnController::class, 'show'])->name('returns.show');
        Route::post('returns/{return}/approve', [PurchaseReturnController::class, 'approve'])->name('returns.approve');

        // Vendor / Supplier Management Routes
        Route::get('vendors/export', [\App\Domains\Purchase\Controllers\VendorController::class, 'export'])->name('vendors.export');
        Route::get('vendors/duplicate-check', [\App\Domains\Purchase\Controllers\VendorController::class, 'duplicateCheck'])->name('vendors.duplicate-check');
        Route::get('vendors/verification', [VendorVerificationController::class, 'index'])->name('vendors.verification.index');
        Route::get('vendors/bank-changes', [VendorBankChangeController::class, 'index'])->name('vendors.bank-changes.index');
        Route::get('vendors/bank-changes/{bankChange}', [VendorBankChangeController::class, 'show'])->name('vendors.bank-changes.show');
        Route::post('vendors/bank-changes/{bankChange}/approve', [VendorBankChangeController::class, 'approve'])->name('vendors.bank-changes.approve');
        Route::post('vendors/bank-changes/{bankChange}/reject', [VendorBankChangeController::class, 'reject'])->name('vendors.bank-changes.reject');
        Route::post('vendors/bank-changes/{bankChange}/cancel', [VendorBankChangeController::class, 'cancel'])->name('vendors.bank-changes.cancel');
        Route::post('vendors/quick-create', [\App\Domains\Purchase\Controllers\VendorController::class, 'quickCreate'])->name('vendors.quick-create');
        Route::post('vendors/{vendor}/toggle-status', [\App\Domains\Purchase\Controllers\VendorController::class, 'toggleStatus'])->name('vendors.toggle-status');
        Route::post('vendors/{vendor}/verify', [VendorVerificationController::class, 'verify'])->name('vendors.verify');
        Route::post('vendors/{vendor}/verification/return', [VendorVerificationController::class, 'returnForCorrection'])->name('vendors.verification.return');
        Route::post('vendors/{vendor}/verification/resubmit', [VendorVerificationController::class, 'resubmit'])->name('vendors.verification.resubmit');
        Route::get('vendors/{vendor}/bank-changes/create', [VendorBankChangeController::class, 'create'])->name('vendors.bank-changes.create');
        Route::post('vendors/{vendor}/bank-changes', [VendorBankChangeController::class, 'store'])->name('vendors.bank-changes.store');
        Route::get('vendors/{vendor}/deactivate', [VendorStatusController::class, 'deactivateForm'])->name('vendors.deactivate.create');
        Route::post('vendors/{vendor}/deactivate', [VendorStatusController::class, 'deactivate'])->name('vendors.deactivate.store');
        Route::get('vendors/{vendor}/reactivate', [VendorStatusController::class, 'reactivateForm'])->name('vendors.reactivate.create');
        Route::post('vendors/{vendor}/reactivate', [VendorStatusController::class, 'reactivate'])->name('vendors.reactivate.store');
        // Party Merge Tool (FRS 2.3 / Task C-18)
        Route::get('vendors/{vendor}/merge-preview', [\App\Domains\Purchase\Controllers\VendorMergeController::class, 'preview'])->name('vendors.merge.preview');
        Route::post('vendors/{vendor}/merge', [\App\Domains\Purchase\Controllers\VendorMergeController::class, 'merge'])->name('vendors.merge.store');
        // Multi-branch & unified vendor contacts (FRS 2.3 / C-17)
        Route::get('vendors/{vendor}/branches', [VendorBranchController::class, 'index'])->name('vendors.branches.index');
        Route::post('vendors/{vendor}/branches', [VendorBranchController::class, 'store'])->name('vendors.branches.store');
        Route::put('vendors/{vendor}/branches/{partyBranch}', [VendorBranchController::class, 'update'])->name('vendors.branches.update');
        Route::post('vendors/{vendor}/branches/{partyBranch}/deactivate', [VendorBranchController::class, 'deactivate'])->name('vendors.branches.deactivate');
        Route::post('vendors/{vendor}/branches/{partyBranch}/activate', [VendorBranchController::class, 'activate'])->name('vendors.branches.activate');
        Route::post('vendors/{vendor}/branches/{partyBranch}/verify', [VendorBranchController::class, 'verify'])->name('vendors.branches.verify');
        Route::post('vendors/{vendor}/branches/{partyBranch}/verification/return', [VendorBranchController::class, 'returnForCorrection'])->name('vendors.branches.verification.return');
        Route::post('vendors/{vendor}/branches/{partyBranch}/verification/resubmit', [VendorBranchController::class, 'resubmit'])->name('vendors.branches.verification.resubmit');
        Route::post('vendors/{vendor}/contacts', [VendorBranchController::class, 'storeContact'])->name('vendors.contacts.store');
        Route::put('vendors/{vendor}/contacts/{contact}', [VendorBranchController::class, 'updateContact'])->name('vendors.contacts.update');
        Route::delete('vendors/{vendor}/contacts/{contact}', [VendorBranchController::class, 'destroyContact'])->name('vendors.contacts.destroy');

        // Vendor Supplied Items Catalog, MOQ, Lead Time & Price History (FRS 2.3 / Task C-19)
        Route::get('vendors/{vendor}/items/lookup', [VendorItemController::class, 'lookup'])->name('vendors.items.lookup');
        Route::post('vendors/{vendor}/items', [VendorItemController::class, 'store'])->name('vendors.items.store');
        Route::put('vendors/{vendor}/items/{vendorItem}', [VendorItemController::class, 'update'])->name('vendors.items.update');
        Route::delete('vendors/{vendor}/items/{vendorItem}', [VendorItemController::class, 'destroy'])->name('vendors.items.destroy');
        Route::get('vendors/{vendor}/items/{vendorItem}/price-history', [VendorItemController::class, 'priceHistory'])->name('vendors.items.price-history');

        // Vendor Compliance & Statutory Documents Expiry Tracker (FRS 2.3 / Task C-19)
        Route::post('vendors/{vendor}/documents', [VendorDocumentController::class, 'store'])->name('vendors.documents.store');
        Route::get('vendors/{vendor}/documents/{document}/download', [VendorDocumentController::class, 'download'])->name('vendors.documents.download');
        Route::delete('vendors/{vendor}/documents/{document}', [VendorDocumentController::class, 'destroy'])->name('vendors.documents.destroy');

        Route::resource('vendors', \App\Domains\Purchase\Controllers\VendorController::class);
    });

// Top level alias /vendors resource
Route::get('vendors/export', [\App\Domains\Purchase\Controllers\VendorController::class, 'export']);
Route::resource('vendors', \App\Domains\Purchase\Controllers\VendorController::class);

// Standalone Top-Level GRN Routes (/grns/...)
Route::prefix('grns')
    ->as('grns.')
    ->group(function (): void {
        Route::get('export', [GoodsReceiptNoteController::class, 'export'])->name('export');
        Route::get('pending', [GoodsReceiptNoteController::class, 'indexPending'])->name('pending');
        Route::get('get-po-items/{po}', [GoodsReceiptNoteController::class, 'getPurchaseOrderItems'])->name('get-po-items');
        Route::get('next-serial-sequence', [GoodsReceiptNoteController::class, 'getNextSerialSequence'])->name('next-serial-sequence');
        Route::post('validate-serials', [GoodsReceiptNoteController::class, 'validateSerials'])->name('validate-serials');
        Route::post('{grn}/approve', [GoodsReceiptNoteController::class, 'approve'])->name('approve');
        Route::get('{grn}/download', [GoodsReceiptNoteController::class, 'downloadPdf'])->name('download');
        Route::resource('/', GoodsReceiptNoteController::class)->parameters(['' => 'grn']);
    });

// Alias routes for purchase.grns.* backward compatibility
Route::prefix('purchase/grns')
    ->as('purchase.grns.')
    ->group(function (): void {
        Route::get('export', [GoodsReceiptNoteController::class, 'export'])->name('export');
        Route::get('pending', fn() => redirect()->route('grns.pending'))->name('pending');
        Route::get('get-po-items/{po}', [GoodsReceiptNoteController::class, 'getPurchaseOrderItems'])->name('get-po-items');
        Route::get('next-serial-sequence', [GoodsReceiptNoteController::class, 'getNextSerialSequence'])->name('next-serial-sequence');
        Route::post('validate-serials', [GoodsReceiptNoteController::class, 'validateSerials'])->name('validate-serials');
        Route::post('{grn}/approve', [GoodsReceiptNoteController::class, 'approve'])->name('approve');
        Route::get('{grn}/download', [GoodsReceiptNoteController::class, 'downloadPdf'])->name('download');
        Route::resource('/', GoodsReceiptNoteController::class)->parameters(['' => 'grn']);
    });
