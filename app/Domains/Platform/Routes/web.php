<?php

use App\Domains\Platform\Controllers\TenantController;
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
    });
