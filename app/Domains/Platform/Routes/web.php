<?php

use App\Domains\Platform\Controllers\PlanController;
use App\Domains\Platform\Controllers\TenantController;
use App\Domains\Platform\Controllers\TransporterController;
use App\Domains\Platform\Controllers\UsageOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')
    ->as('platform.')
    ->group(function (): void {
        Route::get('tenants', [TenantController::class, 'index'])
            ->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])
            ->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])
            ->name('tenants.store');
        Route::get('tenants/{tenant}/edit', [TenantController::class, 'edit'])
            ->name('tenants.edit');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])
            ->name('tenants.update');
        Route::patch('tenants/{tenant}/status', [TenantController::class, 'status'])
            ->name('tenants.status');

        Route::get('plans', [PlanController::class, 'index'])
            ->name('plans.index');
        Route::get('plans/create', [PlanController::class, 'create'])
            ->name('plans.create');
        Route::post('plans', [PlanController::class, 'store'])
            ->name('plans.store');
        Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])
            ->name('plans.edit');
        Route::put('plans/{plan}', [PlanController::class, 'update'])
            ->name('plans.update');

        Route::get('usage', [UsageOverviewController::class, 'index'])
            ->name('usage.index');

        Route::post('payment-terms/{paymentTerm}/toggle-status', [\App\Domains\Platform\Controllers\PaymentTermController::class, 'toggleStatus'])
            ->name('payment-terms.toggle-status');
        Route::resource('payment-terms', \App\Domains\Platform\Controllers\PaymentTermController::class);

        Route::post('transporters/quick-create', [TransporterController::class, 'quickCreate'])
            ->name('transporters.quick-create');
        Route::resource('transporters', TransporterController::class);
    });

