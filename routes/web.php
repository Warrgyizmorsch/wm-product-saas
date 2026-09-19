<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TenantSwitchController;
use App\Domains\Platform\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('localization.supported', [])))
    ->name('locale.switch');

// Public — Razorpay's servers call this directly, no tenant/session context.
// Authenticity is verified inside the controller via the webhook signature,
// not by tenant resolution. Excluded from CSRF in bootstrap/app.php.
Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])
    ->name('webhooks.razorpay');

Route::middleware(['tenant'])->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    // Public RFQ Vendor Portal
    Route::middleware(['throttle:30,1'])->group(function (): void {
        Route::get('/purchase/rfq-portal/{token}', [\App\Domains\Purchase\Controllers\PurchaseRfqController::class, 'showPortal'])->name('purchase.rfqs.portal');
        Route::post('/purchase/rfq-portal/{token}/submit', [\App\Domains\Purchase\Controllers\PurchaseRfqController::class, 'submitPortal'])->name('purchase.rfqs.portal-submit');
    });

    // Public WhatsApp Webhook Route for Node.js Bridge
    Route::post('/platform/whatsapp/webhook', [\App\Http\Controllers\WhatsAppController::class, 'handleWebhook'])->name('platform.whatsapp.webhook');
    Route::post('/crm/whatsapp/webhook', [\App\Http\Controllers\WhatsAppController::class, 'handleWebhook'])->name('crm.whatsapp.webhook');

    Route::middleware(['auth', 'company', 'branch'])->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/tenant-switch/{tenant:slug}', TenantSwitchController::class)
            ->name('tenant.switch');

        Route::get('/company-switch/{company}', CompanySwitchController::class)
            ->name('company.switch');

        Route::get('/branch-switch/{branch}', BranchSwitchController::class)
            ->name('branch.switch');

        Route::get('/', function () {
            return view('dashboard');
        })->name('home');

        Route::get('/ui-elements', function () {
            return view('ui-elements');
        })->name('ui-elements');

        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::get('/global-search', [GlobalSearchController::class, 'search'])
            ->name('global-search');

        Route::get('/global-approvals', [ApprovalController::class, 'index'])
            ->name('global-approvals');

        // System-Wide Notifications Engine
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
            Route::get('/unread', [\App\Http\Controllers\NotificationController::class, 'unread'])->name('unread');
            Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('read');
            Route::post('/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['module.access'])->group(function (): void {
            foreach (glob(str_replace('/', DIRECTORY_SEPARATOR, app_path('Domains/*/Routes/web.php'))) as $moduleRoutes) {
                require $moduleRoutes;
            }
        });
    });

    Route::middleware(['auth:sanctum', 'company', 'branch'])->group(function (): void {
        foreach (glob(str_replace('/', DIRECTORY_SEPARATOR, app_path('Domains/*/Routes/api.php'))) as $moduleApiRoutes) {
            require $moduleApiRoutes;
        }
    });
});
