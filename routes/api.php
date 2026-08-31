<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\MapApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider or bootstrap/app.php.
|
*/

Route::prefix('auth')->name('auth.')->group(function () {
    // Public login route to obtain token
    Route::post('/login', [LoginController::class, 'apiLogin'])->name('login');

    // Authenticated logout route to revoke token
    Route::post('/logout', [LoginController::class, 'apiLogout'])
        ->middleware('auth:sanctum')
        ->name('logout');
});

// Map & Location Utility routes
Route::get('/config', [MapApiController::class, 'config'])->name('api.config');

Route::prefix('maps')
    ->middleware(['auth:sanctum'])
    ->name('api.maps.')
    ->group(function () {
        Route::get('/geocode', [MapApiController::class, 'geocode'])->name('geocode');
        Route::get('/autocomplete', [MapApiController::class, 'autocomplete'])->name('autocomplete');
    });
