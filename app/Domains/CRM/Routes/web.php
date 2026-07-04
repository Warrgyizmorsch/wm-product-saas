<?php

use App\Domains\CRM\Controllers\CustomerController;
use App\Domains\CRM\Controllers\LeadController;
use App\Domains\CRM\Controllers\LeadFollowupController;
use App\Domains\CRM\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm')
    ->as('crm.')
    ->group(function (): void {
        Route::get('leads/create', [LeadController::class, 'create'])
            ->name('leads.create');
        Route::get('leads', [LeadController::class, 'index'])
            ->name('leads.index');
        Route::get('leads/track-status', [LeadController::class, 'trackStatus'])
            ->name('leads.trackStatus');
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
        Route::patch('leads/{lead}/owner', [LeadController::class, 'updateOwner'])
            ->name('leads.updateOwner');
        Route::post('leads/{lead}/convert-to-quotation', [LeadController::class, 'convertToQuotation'])
            ->name('leads.convertToQuotation');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])
            ->name('leads.destroy');

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

        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.updateStatus');
        Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });
