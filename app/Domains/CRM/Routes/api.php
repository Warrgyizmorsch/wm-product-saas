<?php

use Illuminate\Support\Facades\Route;
use App\Domains\CRM\Controllers\Api\LeadApiController;
use App\Domains\CRM\Controllers\Api\LeadFollowupApiController;
use App\Domains\CRM\Controllers\Api\LeadStatusApiController;
use App\Domains\CRM\Controllers\Api\CrmDealApiController;
use App\Domains\CRM\Controllers\Api\DealStatusApiController;
use App\Domains\CRM\Controllers\Api\DealActivityApiController;

/*
|--------------------------------------------------------------------------
| CRM Domain REST API Routes (Leads & Deals Kit)
|--------------------------------------------------------------------------
*/

Route::prefix('api/crm')
    ->middleware(['auth:sanctum', 'throttle:120,1'])
    ->name('api.crm.')
    ->group(function () {

        // ==========================================
        // 1. LEAD STATUS MASTERS (CRUD + LOCKS)
        // ==========================================
        Route::prefix('lead-statuses')->name('lead-statuses.')->group(function () {
            Route::get('/', [LeadStatusApiController::class, 'index'])->name('index');
            Route::post('/', [LeadStatusApiController::class, 'store'])->name('store');
            Route::post('/reorder', [LeadStatusApiController::class, 'reorder'])->name('reorder');
            Route::get('/{leadStatus}', [LeadStatusApiController::class, 'show'])->whereNumber('leadStatus')->name('show');
            Route::put('/{leadStatus}', [LeadStatusApiController::class, 'update'])->whereNumber('leadStatus')->name('update');
            Route::patch('/{leadStatus}', [LeadStatusApiController::class, 'update'])->whereNumber('leadStatus')->name('patch');
            Route::delete('/{leadStatus}', [LeadStatusApiController::class, 'destroy'])->whereNumber('leadStatus')->name('destroy');
        });

        // ==========================================
        // 2. DEAL STATUS MASTERS (STAGES & PROBABILITIES)
        // ==========================================
        Route::prefix('deal-statuses')->name('deal-statuses.')->group(function () {
            Route::get('/', [DealStatusApiController::class, 'index'])->name('index');
            Route::post('/', [DealStatusApiController::class, 'store'])->name('store');
            Route::post('/reorder', [DealStatusApiController::class, 'reorder'])->name('reorder');
            Route::get('/{dealStatus}', [DealStatusApiController::class, 'show'])->whereNumber('dealStatus')->name('show');
            Route::put('/{dealStatus}', [DealStatusApiController::class, 'update'])->whereNumber('dealStatus')->name('update');
            Route::patch('/{dealStatus}', [DealStatusApiController::class, 'update'])->whereNumber('dealStatus')->name('patch');
            Route::delete('/{dealStatus}', [DealStatusApiController::class, 'destroy'])->whereNumber('dealStatus')->name('destroy');
        });

        // ==========================================
        // 3. LEADS (FULL CRUD, FILTER, SORT, ACTIONS)
        // ==========================================
        Route::prefix('leads')->name('leads.')
            ->group(function () {
                Route::get('/meta', [LeadApiController::class, 'meta'])->name('meta');
                Route::get('/export', [LeadApiController::class, 'export'])->name('export');
                Route::get('/kanban', [LeadApiController::class, 'kanban'])->name('kanban');
                Route::match(['get', 'post'], '/check-duplicate', [LeadApiController::class, 'checkDuplicate'])->name('check-duplicate');
                Route::post('/bulk-assign', [LeadApiController::class, 'bulkAssign'])->name('bulk-assign');

                Route::get('/', [LeadApiController::class, 'index'])->name('index');
                Route::post('/', [LeadApiController::class, 'store'])->name('store');

                Route::get('/{lead}', [LeadApiController::class, 'show'])->whereNumber('lead')->name('show');
                Route::put('/{lead}', [LeadApiController::class, 'update'])->whereNumber('lead')->name('update');
                Route::patch('/{lead}', [LeadApiController::class, 'update'])->whereNumber('lead')->name('patch');
                Route::delete('/{lead}', [LeadApiController::class, 'destroy'])->whereNumber('lead')->name('destroy');
                Route::post('/{lead}/restore', [LeadApiController::class, 'restore'])->whereNumber('lead')->name('restore');

                Route::patch('/{lead}/status', [LeadApiController::class, 'updateStatus'])->whereNumber('lead')->name('status');
                Route::patch('/{lead}/priority', [LeadApiController::class, 'updatePriority'])->whereNumber('lead')->name('priority');
                Route::patch('/{lead}/owner', [LeadApiController::class, 'updateOwner'])->whereNumber('lead')->name('owner');
                Route::patch('/{lead}/requirement', [LeadApiController::class, 'updateRequirement'])->whereNumber('lead')->name('requirement');
                Route::post('/{lead}/convert-to-deal', [LeadApiController::class, 'qualify'])->whereNumber('lead')->name('convert-to-deal');
                Route::post('/{lead}/convert-to-quotation', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'convertFromLead'])->whereNumber('lead')->name('convert-to-quotation');
                Route::post('/{lead}/qualify', [LeadApiController::class, 'qualify'])->whereNumber('lead')->name('qualify');

                Route::get('/{lead}/followups', [LeadFollowupApiController::class, 'index'])->whereNumber('lead')->name('followups.index');
                Route::post('/{lead}/followups', [LeadFollowupApiController::class, 'store'])->whereNumber('lead')->name('followups.store');

                Route::get('/{lead}/documents', [LeadApiController::class, 'documents'])->whereNumber('lead')->name('documents.index');
                Route::post('/{lead}/documents', [LeadApiController::class, 'uploadDocuments'])->whereNumber('lead')->name('documents.upload');
            });

        // ==========================================
        // 4. DEALS (FULL CRUD, PIPELINE, WON/LOST)
        // ==========================================
        Route::prefix('deals')->name('deals.')
            ->group(function () {
                Route::get('/meta', [CrmDealApiController::class, 'meta'])->name('meta');
                Route::get('/export', [CrmDealApiController::class, 'export'])->name('export');
                Route::get('/kanban', [CrmDealApiController::class, 'kanban'])->name('kanban');
                Route::post('/bulk-assign', [CrmDealApiController::class, 'bulkAssign'])->name('bulk-assign');

                Route::get('/', [CrmDealApiController::class, 'index'])->name('index');
                Route::post('/', [CrmDealApiController::class, 'store'])->name('store');
                Route::get('/{deal}', [CrmDealApiController::class, 'show'])->whereNumber('deal')->name('show');
                Route::put('/{deal}', [CrmDealApiController::class, 'update'])->whereNumber('deal')->name('update');
                Route::patch('/{deal}', [CrmDealApiController::class, 'update'])->whereNumber('deal')->name('patch');
                Route::delete('/{deal}', [CrmDealApiController::class, 'destroy'])->whereNumber('deal')->name('destroy');
                Route::post('/{deal}/restore', [CrmDealApiController::class, 'restore'])->whereNumber('deal')->name('restore');

                // Quick Deal Actions
                Route::patch('/{deal}/owner', [CrmDealApiController::class, 'updateOwner'])->whereNumber('deal')->name('owner');
                Route::patch('/{deal}/stage', [CrmDealApiController::class, 'updateStage'])->whereNumber('deal')->name('stage');
                Route::patch('/{deal}/move-stage', [CrmDealApiController::class, 'updateStage'])->whereNumber('deal')->name('move-stage');
                Route::patch('/{deal}/requirement', [CrmDealApiController::class, 'updateRequirement'])->whereNumber('deal')->name('requirement');
                Route::post('/{deal}/won', [CrmDealApiController::class, 'markWon'])->whereNumber('deal')->name('won');
                Route::post('/{deal}/mark-won', [CrmDealApiController::class, 'markWon'])->whereNumber('deal')->name('mark-won');
                Route::post('/{deal}/lost', [CrmDealApiController::class, 'markLost'])->whereNumber('deal')->name('lost');
                Route::post('/{deal}/mark-lost', [CrmDealApiController::class, 'markLost'])->whereNumber('deal')->name('mark-lost');
                Route::post('/{deal}/sync-health', [CrmDealApiController::class, 'syncHealth'])->whereNumber('deal')->name('sync-health');
                Route::post('/{deal}/convert-to-customer', [CrmDealApiController::class, 'convertToCustomer'])->whereNumber('deal')->name('convert-to-customer');
                Route::post('/{deal}/draft-reply', [CrmDealApiController::class, 'generateDraftReply'])->whereNumber('deal')->name('draft-reply');

                // Deal Activities & Followups
                Route::get('/{deal}/followups', [DealActivityApiController::class, 'index'])->whereNumber('deal')->name('followups.index');
                Route::post('/{deal}/followups', [DealActivityApiController::class, 'store'])->whereNumber('deal')->name('followups.store');

                // Deal Documents
                Route::get('/{deal}/documents', [CrmDealApiController::class, 'documents'])->whereNumber('deal')->name('documents.index');
                Route::post('/{deal}/documents', [CrmDealApiController::class, 'uploadDocuments'])->whereNumber('deal')->name('documents.upload');
            });

        // Standalone followups & documents delete/patch
        Route::patch('followups/{followup}/status', [LeadFollowupApiController::class, 'updateStatus'])->whereNumber('followup')->name('followups.status');
        Route::delete('followups/{followup}', [LeadFollowupApiController::class, 'destroy'])->whereNumber('followup')->name('followups.destroy');
        Route::delete('documents/{document}', [LeadApiController::class, 'deleteDocument'])->whereNumber('document')->name('documents.destroy');

        // ==========================================
        // 5. GOOGLE CALENDAR & GOOGLE MEET INTEGRATION
        // ==========================================
        Route::prefix('google-calendar')->name('google-calendar.')->group(function () {
            Route::get('/auth-url', [\App\Domains\CRM\Controllers\Api\GoogleCalendarApiController::class, 'authUrl'])->name('auth-url');
            Route::get('/events', [\App\Domains\CRM\Controllers\Api\GoogleCalendarApiController::class, 'events'])->name('events');
            Route::post('/schedule-event', [\App\Domains\CRM\Controllers\Api\GoogleCalendarApiController::class, 'scheduleEvent'])->name('schedule-event');
        });

        // ==========================================
        // 6. CRM ACCOUNTS (COMPANIES MASTER)
        // ==========================================
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/meta', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'meta'])->name('meta');
            Route::post('/bulk-assign', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'bulkAssign'])->name('bulk-assign');
            Route::get('/', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'index'])->name('index');
            Route::post('/', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'store'])->name('store');
            Route::get('/{id}', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'show'])->name('show');
            Route::put('/{id}', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'update'])->name('update');
            Route::patch('/{id}/owner', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'updateOwner'])->name('owner');
            Route::patch('/{id}', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'update'])->name('patch');
            Route::delete('/{id}', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/contacts', [\App\Domains\CRM\Controllers\Api\CrmAccountApiController::class, 'storeContact'])->name('contacts.store');
        });

        // ==========================================
        // 7. CRM CUSTOMERS (CONVERTED CLIENTS / DIRECT CLIENTS)
        // ==========================================
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/meta', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'meta'])->name('meta');
            Route::get('/', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'index'])->name('index');
            Route::post('/', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'store'])->name('store');
            Route::get('/{customer}', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'show'])->name('show');
            Route::put('/{customer}', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'update'])->name('update');
            Route::patch('/{customer}', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'update'])->name('patch');
            Route::delete('/{customer}', [\App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'destroy'])->name('destroy');
        });

        // ==========================================
        // 8. CRM QUOTATIONS & PROPOSALS
        // ==========================================
        Route::prefix('quotations')->name('quotations.')->group(function () {
            Route::get('/', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'index'])->name('index');
            Route::post('/', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'store'])->name('store');
            Route::get('/{id}', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'show'])->name('show');
            Route::put('/{id}', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'update'])->name('update');
            Route::patch('/{id}', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'update'])->name('patch');
            Route::delete('/{id}', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/approve', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'approve'])->name('approve');
            Route::post('/{id}/reject', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'reject'])->name('reject');
            Route::post('/{id}/send-email', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'sendEmail'])->name('send-email');
            Route::post('/{id}/send-whatsapp', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'sendWhatsApp'])->name('send-whatsapp');
            Route::get('/{id}/download', [\App\Domains\CRM\Controllers\Api\QuotationApiController::class, 'downloadPdf'])->name('download');
        });

        // ==========================================
        // 9. CRM EXECUTIVE DASHBOARD ANALYTICS
        // ==========================================
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/metrics', [\App\Domains\CRM\Controllers\Api\CrmDashboardApiController::class, 'metrics'])->name('metrics');
        });

        // ==========================================
        // 10. CRM TENANT SETTINGS (POLICIES)
        // ==========================================
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Domains\CRM\Controllers\Api\CrmSettingsApiController::class, 'index'])->name('index');
            Route::post('/', [\App\Domains\CRM\Controllers\Api\CrmSettingsApiController::class, 'update'])->name('update');
        });
    });
