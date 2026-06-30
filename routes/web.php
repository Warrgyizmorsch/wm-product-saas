<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TenantSwitchController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', array_keys(config('localization.supported', [])))
    ->name('locale.switch');

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

Route::middleware(['tenant'])->group(function (): void {
    foreach (glob(app_path('Domains/*/Routes/web.php')) as $moduleRoutes) {
        require $moduleRoutes;
    }
});
