<?php

use App\Domains\Platform\Controllers\Admin\AuditLogController;
use App\Domains\Platform\Controllers\Admin\AuthController;
use App\Domains\Platform\Controllers\Admin\DashboardController;
use App\Domains\Platform\Controllers\CurrencyController;
use App\Domains\Platform\Controllers\DemoRequestController;
use App\Domains\Platform\Controllers\ModulePriceController;
use App\Domains\Platform\Controllers\PaymentGatewaySettingsController;
use App\Domains\Platform\Controllers\PlanController;
use App\Domains\Platform\Controllers\TenantController;
use App\Domains\Platform\Controllers\UsageOverviewController;
use Illuminate\Support\Facades\Route;

/*
 * Platform console (/admin), loaded from routes/web.php OUTSIDE the `tenant`
 * middleware: no tenant is resolved, so console screens see every tenant.
 * Every route past the login is platform-staff only (platform.admin) and every
 * state-changing request is written to platform_audit_logs (platform.audit).
 *
 * Screens moved here from the tenant app keep their `platform.*` route names,
 * so their views, controllers and policies did not have to change.
 */

Route::get('login', [AuthController::class, 'create'])->name('admin.login');
Route::post('login', [AuthController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('admin.login.store');

Route::middleware(['auth', 'platform.admin', 'platform.audit'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'destroy'])->name('admin.logout');

    Route::get('/', DashboardController::class)->name('admin.dashboard');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');

    Route::as('platform.')->group(function (): void {
        Route::get('demo-requests', [DemoRequestController::class, 'index'])
            ->name('demo-requests.index');
        Route::patch('demo-requests/{demoRequest}/status', [DemoRequestController::class, 'updateStatus'])
            ->name('demo-requests.status');
        Route::delete('demo-requests/{demoRequest}', [DemoRequestController::class, 'destroy'])
            ->name('demo-requests.destroy');

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
        Route::get('module-prices', [ModulePriceController::class, 'index'])
            ->name('module-prices.index');
        Route::put('module-prices', [ModulePriceController::class, 'update'])
            ->name('module-prices.update');

        Route::get('usage', [UsageOverviewController::class, 'index'])
            ->name('usage.index');

        Route::get('payment-gateway', [PaymentGatewaySettingsController::class, 'index'])
            ->name('payment-gateway.index');
        Route::put('payment-gateway', [PaymentGatewaySettingsController::class, 'update'])
            ->name('payment-gateway.update');

        Route::get('currencies', [CurrencyController::class, 'index'])
            ->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])
            ->name('currencies.store');
        Route::put('currencies/{currency}', [CurrencyController::class, 'update'])
            ->name('currencies.update');
        Route::patch('currencies/{currency}/status', [CurrencyController::class, 'toggleStatus'])
            ->name('currencies.status');
    });
});
