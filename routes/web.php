<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TenantSwitchController;
use Illuminate\Support\Facades\Route;

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

Route::get('/org/company', [CompanyController::class, 'index'])->name('company.index');
Route::get('/org/company/create', [CompanyController::class, 'create']);
Route::post('/org/company/store', [CompanyController::class, 'store'])->name('campany.store');
