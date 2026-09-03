<?php

use App\Domains\CRM\Controllers\CustomerController;
use App\Domains\CRM\Controllers\CrmAccountController;
use App\Domains\CRM\Controllers\CrmDealController;
use App\Domains\CRM\Controllers\LeadController;
use App\Domains\CRM\Controllers\LeadFollowupController;
use App\Domains\CRM\Controllers\LeadActivityController;
use App\Domains\CRM\Controllers\LeadStatusController;
use App\Domains\CRM\Controllers\DealStatusController;
use App\Domains\CRM\Controllers\QuotationController;
use App\Domains\CRM\Controllers\CrmSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm')
    ->as('crm.')
    ->group(function (): void {
        // CRM Settings Routes
        Route::get('settings', [CrmSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings/invoicing-policy', [CrmSettingsController::class, 'updateInvoicingPolicy'])->name('settings.update-invoicing-policy');
        // CRM Masters Routes
        Route::prefix('masters')->as('masters.')->group(function (): void {
            Route::get('lead-statuses', [LeadStatusController::class, 'index'])->name('lead-statuses.index');
            Route::post('lead-statuses', [LeadStatusController::class, 'store'])->name('lead-statuses.store');
            Route::put('lead-statuses/{leadStatus}', [LeadStatusController::class, 'update'])->name('lead-statuses.update');
            Route::delete('lead-statuses/{leadStatus}', [LeadStatusController::class, 'destroy'])->name('lead-statuses.destroy');
            Route::post('lead-statuses/reorder', [LeadStatusController::class, 'reorder'])->name('lead-statuses.reorder');
            Route::post('lead-statuses/{leadStatus}/move/{direction}', [LeadStatusController::class, 'move'])->name('lead-statuses.move');

            Route::get('deal-statuses', [DealStatusController::class, 'index'])->name('deal-statuses.index');
            Route::post('deal-statuses', [DealStatusController::class, 'store'])->name('deal-statuses.store');
            Route::put('deal-statuses/{dealStatus}', [DealStatusController::class, 'update'])->name('deal-statuses.update');
            Route::delete('deal-statuses/{dealStatus}', [DealStatusController::class, 'destroy'])->name('deal-statuses.destroy');
            Route::post('deal-statuses/reorder', [DealStatusController::class, 'reorder'])->name('deal-statuses.reorder');
            Route::post('deal-statuses/{dealStatus}/move/{direction}', [DealStatusController::class, 'move'])->name('deal-statuses.move');
        });

        // CRM Accounts Routes
        Route::get('accounts', [CrmAccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/create', [CrmAccountController::class, 'create'])->name('accounts.create');
        Route::post('accounts', [CrmAccountController::class, 'store'])->name('accounts.store');
        Route::get('accounts/{account}', [CrmAccountController::class, 'show'])->name('accounts.show');
        Route::get('accounts/{account}/edit', [CrmAccountController::class, 'edit'])->name('accounts.edit');
        Route::put('accounts/{account}', [CrmAccountController::class, 'update'])->name('accounts.update');
        Route::post('accounts/{account}/contacts', [CrmAccountController::class, 'storeContact'])->name('accounts.contacts.store');
        Route::get('accounts/{account}/contacts-list', [CrmAccountController::class, 'getContactsList'])->name('accounts.contactsList');
        Route::post('contacts/quick-create', [CrmAccountController::class, 'quickStoreContact'])->name('contacts.quick-create');
        Route::delete('accounts/{account}', [CrmAccountController::class, 'destroy'])->name('accounts.destroy');

        // CRM Deals Routes
        Route::get('deals', [CrmDealController::class, 'index'])->name('deals.index');
        Route::get('deals/kanban', [CrmDealController::class, 'kanban'])->name('deals.kanban');
        Route::get('deals/create', [CrmDealController::class, 'create'])->name('deals.create');
        Route::post('deals', [CrmDealController::class, 'store'])->name('deals.store');
        Route::get('deals/{deal}', [CrmDealController::class, 'show'])->name('deals.show');
        Route::get('deals/{deal}/edit', [CrmDealController::class, 'edit'])->name('deals.edit');
        Route::put('deals/{deal}', [CrmDealController::class, 'update'])->name('deals.update');
        Route::patch('deals/{deal}/stage', [CrmDealController::class, 'updateStage'])->name('deals.updateStage');
        Route::patch('deals/{deal}/requirement', [CrmDealController::class, 'updateRequirement'])->name('deals.updateRequirement');
        Route::delete('deals/{deal}', [CrmDealController::class, 'destroy'])->name('deals.destroy');
        Route::post('deals/{deal}/documents', [CrmDealController::class, 'uploadDocuments'])->name('deals.documents.upload');
        Route::post('deals/{deal}/followups', [LeadFollowupController::class, 'storeDealFollowup'])->name('deals.followups.store');
        Route::get('leads/create', [LeadController::class, 'create'])
            ->name('leads.create');
        Route::get('leads', [LeadController::class, 'index'])
            ->name('leads.index');
        Route::get('leads/kanban', [LeadController::class, 'kanban'])
            ->name('leads.kanban');
        Route::get('activities', [LeadActivityController::class, 'index'])
            ->name('activities.index');
        Route::get('leads/track-status', [LeadController::class, 'trackStatus'])
            ->name('leads.trackStatus');
        Route::get('leads/download-sample', [LeadController::class, 'downloadSample'])
            ->name('leads.downloadSample');
        Route::post('leads/import', [LeadController::class, 'import'])
            ->name('leads.import');
        Route::get('leads/export', [LeadController::class, 'export'])
            ->name('leads.export');
        Route::post('leads/check-duplicate', [LeadController::class, 'checkDuplicate'])
            ->name('leads.checkDuplicate');
        Route::patch('leads/{lead}/qualify', [LeadController::class, 'qualify'])
            ->name('leads.qualify');
        Route::get('leads/{lead}', [LeadController::class, 'show'])
            ->name('leads.show');
        Route::post('leads', [LeadController::class, 'store'])
            ->name('leads.store');
        Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])
            ->name('leads.edit');
        Route::put('leads/{lead}', [LeadController::class, 'update'])
            ->name('leads.update');
        Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])
            ->name('leads.updateStatus');
        Route::patch('leads/{lead}/priority', [LeadController::class, 'updatePriority'])
            ->name('leads.updatePriority');
        Route::patch('leads/{lead}/owner', [LeadController::class, 'updateOwner'])
            ->name('leads.updateOwner');
        Route::patch('leads/{lead}/requirement', [LeadController::class, 'updateRequirement'])
            ->name('leads.updateRequirement');
        Route::post('leads/{lead}/convert-to-quotation', [LeadController::class, 'convertToQuotation'])
            ->name('leads.convertToQuotation');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])
            ->name('leads.destroy');

        Route::post('leads/{lead}/documents', [LeadController::class, 'uploadDocuments'])
            ->name('leads.documents.upload');
        Route::get('lead-documents/{document}', [LeadController::class, 'viewDocument'])
            ->name('leads.documents.view');
        Route::get('lead-documents/{document}/download', [LeadController::class, 'downloadDocument'])
            ->name('leads.documents.download');
        Route::delete('lead-documents/{document}', [LeadController::class, 'deleteDocument'])
            ->name('leads.documents.delete');

        // Follow-ups
        Route::post('leads/{lead}/followups', [LeadFollowupController::class, 'store'])
            ->name('leads.followups.store');
        Route::put('followups/{followup}', [LeadFollowupController::class, 'update'])
            ->name('followups.update');
        Route::delete('followups/{followup}', [LeadFollowupController::class, 'destroy'])
            ->name('followups.destroy');

        Route::get('customers', [CustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])
            ->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])
            ->name('customers.store');
        Route::post('customers/quick-create', [CustomerController::class, 'quickCreate'])
            ->name('customers.quick-create');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])
            ->name('customers.show');
        Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])
            ->name('customers.updateStatus');
        Route::get('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])
            ->name('customers.toggleStatus');

        Route::get('approvals/quotations', [QuotationController::class, 'approvalsIndex'])->name('approvals.quotations.index');
        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('quotations/{quotation}/detail-partial', [QuotationController::class, 'detailPartial'])->name('quotations.detail-partial');
        Route::get('quotations/{quotation}/download', [QuotationController::class, 'downloadPdf'])->name('quotations.download');
        Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.updateStatus');
        Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });
