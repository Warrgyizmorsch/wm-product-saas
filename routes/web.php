<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TenantSwitchController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('localization.supported', [])))
    ->name('locale.switch');

Route::middleware(['tenant'])->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Public RFQ Vendor Portal
    Route::get('/purchase/rfq-portal/{token}', [\App\Domains\Purchase\Controllers\PurchaseRfqController::class, 'showPortal'])->name('purchase.rfqs.portal');
    Route::post('/purchase/rfq-portal/{token}/submit', [\App\Domains\Purchase\Controllers\PurchaseRfqController::class, 'submitPortal'])->name('purchase.rfqs.portal-submit');

    Route::middleware(['auth'])->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/tenant-switch/{tenant:slug}', TenantSwitchController::class)
            ->name('tenant.switch');

        Route::get('/', function () {
            return view('dashboard');
        })->name('home');

        Route::get('/ui-elements', function () {
            return view('ui-elements');
        })->name('ui-elements');

        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        foreach (glob(app_path('Domains/*/Routes/web.php')) as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
});
