<?php

use App\Http\Controllers\Auth\LoginController;
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
