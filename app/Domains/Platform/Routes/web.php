<?php

use App\Domains\Platform\Controllers\CurrencyController;
use App\Domains\Platform\Controllers\PaymentGatewaySettingsController;
use App\Domains\Platform\Controllers\PlanController;
use App\Domains\Platform\Controllers\SubscriptionController;
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

        Route::get('payment-gateway', [PaymentGatewaySettingsController::class, 'index'])
            ->name('payment-gateway.index');
        Route::put('payment-gateway', [PaymentGatewaySettingsController::class, 'update'])
            ->name('payment-gateway.update');

        Route::get('subscription', [SubscriptionController::class, 'index'])
            ->name('subscription.index');
        Route::put('subscription', [SubscriptionController::class, 'update'])
            ->name('subscription.update');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])
            ->name('subscription.checkout');
        Route::post('subscription/verify', [SubscriptionController::class, 'verify'])
            ->name('subscription.verify');

        Route::get('currencies', [CurrencyController::class, 'index'])
            ->name('currencies.index');
        Route::post('currencies', [CurrencyController::class, 'store'])
            ->name('currencies.store');
        Route::put('currencies/{currency}', [CurrencyController::class, 'update'])
            ->name('currencies.update');
        Route::patch('currencies/{currency}/status', [CurrencyController::class, 'toggleStatus'])
            ->name('currencies.status');

        Route::post('payment-terms/{paymentTerm}/toggle-status', [\App\Domains\Platform\Controllers\PaymentTermController::class, 'toggleStatus'])
            ->name('payment-terms.toggle-status');
        Route::resource('payment-terms', \App\Domains\Platform\Controllers\PaymentTermController::class);

        Route::post('transporters/quick-create', [TransporterController::class, 'quickCreate'])
            ->name('transporters.quick-create');
        Route::resource('transporters', TransporterController::class);
    });

