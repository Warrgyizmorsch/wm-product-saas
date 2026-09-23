<?php

use App\Domains\Platform\Controllers\CurrencyController;
use App\Domains\Platform\Controllers\PaymentGatewaySettingsController;
use App\Domains\Platform\Controllers\BillingCheckoutController;
use App\Domains\Platform\Controllers\ModulePriceController;
use App\Domains\Platform\Controllers\PlanController;
use App\Domains\Platform\Controllers\SubscriptionController;
use App\Domains\Platform\Controllers\TenantController;
use App\Domains\Platform\Controllers\TenantModuleController;
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
        Route::get('module-prices', [ModulePriceController::class, 'index'])
            ->name('module-prices.index');
        Route::put('module-prices', [ModulePriceController::class, 'update'])
            ->name('module-prices.update');

        Route::get('usage', [UsageOverviewController::class, 'index'])
            ->name('usage.index');

        Route::get('subscription/checkout', [BillingCheckoutController::class, 'show'])
            ->name('billing.checkout');
        Route::post('subscription/quote', [BillingCheckoutController::class, 'quote'])
            ->name('billing.quote');
        Route::put('subscription/billing-details', [BillingCheckoutController::class, 'saveBillingDetails'])
            ->name('billing.details');

        Route::post('modules/checkout', [TenantModuleController::class, 'checkout'])
            ->name('modules.checkout');
        Route::post('modules/verify', [TenantModuleController::class, 'verify'])
            ->name('modules.verify');
        Route::post('modules/{module}/uninstall', [TenantModuleController::class, 'uninstall'])
            ->name('modules.uninstall');
        Route::post('modules/{module}/reinstall', [TenantModuleController::class, 'reinstall'])
            ->name('modules.reinstall');

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

        // WhatsApp Bridge Integration Routes
        Route::get('whatsapp-settings', [\App\Http\Controllers\WhatsAppController::class, 'index'])->name('whatsappSettings.index');
        Route::post('whatsapp/config', [\App\Http\Controllers\WhatsAppController::class, 'updateConfig'])->name('whatsapp.updateConfig');
        Route::get('whatsapp/status', [\App\Http\Controllers\WhatsAppController::class, 'status'])->name('whatsapp.status');
        Route::post('whatsapp/connect', [\App\Http\Controllers\WhatsAppController::class, 'connect'])->name('whatsapp.connect');
        Route::delete('whatsapp/disconnect', [\App\Http\Controllers\WhatsAppController::class, 'disconnect'])->name('whatsapp.disconnect');
        Route::post('whatsapp/send-message', [\App\Http\Controllers\WhatsAppController::class, 'sendMessage'])->name('whatsapp.sendMessage');
        Route::get('whatsapp/messages', [\App\Http\Controllers\WhatsAppController::class, 'messages'])->name('whatsapp.messages');

        // SMTP Email Settings Routes
        Route::get('email-settings', [\App\Domains\Platform\Controllers\EmailSettingController::class, 'index'])->name('emailSettings.index');
        Route::post('email-settings/store', [\App\Domains\Platform\Controllers\EmailSettingController::class, 'store'])->name('emailSettings.store');
        Route::post('email-settings/{id}/test', [\App\Domains\Platform\Controllers\EmailSettingController::class, 'testConnection'])->name('emailSettings.test');
        Route::post('email-settings/{id}/send-test-email', [\App\Domains\Platform\Controllers\EmailSettingController::class, 'sendTestMail'])->name('emailSettings.sendTestMail');
        Route::delete('email-settings/{id}', [\App\Domains\Platform\Controllers\EmailSettingController::class, 'destroy'])->name('emailSettings.destroy');

        // GST & E-Invoice / E-Way Bill Settings Routes
        Route::get('gst-settings', [\App\Domains\Platform\Controllers\GstSettingController::class, 'index'])->name('gstSettings.index');
        Route::post('gst-settings/store', [\App\Domains\Platform\Controllers\GstSettingController::class, 'store'])->name('gstSettings.store');
        Route::post('gst-settings/{id}/test', [\App\Domains\Platform\Controllers\GstSettingController::class, 'testConnection'])->name('gstSettings.test');
        Route::delete('gst-settings/{id}', [\App\Domains\Platform\Controllers\GstSettingController::class, 'destroy'])->name('gstSettings.destroy');

        // Notification Rules Master (Absolute ERP Style)
        Route::post('notification-rules/{notificationRule}/toggle-status', [\App\Domains\Platform\Controllers\NotificationRuleController::class, 'toggleStatus'])
            ->name('notification-rules.toggle-status');
        Route::post('notification-rules/{notificationRule}/test-send', [\App\Domains\Platform\Controllers\NotificationRuleController::class, 'testSend'])
            ->name('notification-rules.test-send');
        Route::resource('notification-rules', \App\Domains\Platform\Controllers\NotificationRuleController::class);
    });

