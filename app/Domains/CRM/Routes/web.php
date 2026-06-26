<?php

use App\Domains\CRM\Controllers\CustomerController;
use App\Domains\CRM\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm')
    ->as('crm.')
    ->group(function (): void {
        Route::get('leads/create', [LeadController::class, 'create'])
            ->name('leads.create');
        Route::get('leads', [LeadController::class, 'index'])
            ->name('leads.index');
        Route::post('leads', [LeadController::class, 'store'])
            ->name('leads.store');
        Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])
            ->name('leads.edit');
        Route::put('leads/{lead}', [LeadController::class, 'update'])
            ->name('leads.update');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])
            ->name('leads.destroy');

        Route::get('customers', [CustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])
            ->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])
            ->name('customers.store');
    });
