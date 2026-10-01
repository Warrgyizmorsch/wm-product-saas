<?php

use App\Domains\Visitor\Controllers\Api\VisitorApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Visitor Management REST API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/visitor')
    ->name('api.visitor.')
    ->group(function () {
        Route::get('/visitors', [VisitorApiController::class, 'index'])->name('visitors.index');
        Route::post('/visitors', [VisitorApiController::class, 'store'])->name('visitors.store');
        Route::get('/passes', [VisitorApiController::class, 'passes'])->name('passes.index');
        Route::post('/passes/pre-register', [VisitorApiController::class, 'preRegister'])->name('passes.pre-register');
        Route::post('/passes/{id}/check-in', [VisitorApiController::class, 'checkIn'])->name('passes.check-in');
        Route::post('/passes/{id}/check-out', [VisitorApiController::class, 'checkOut'])->name('passes.check-out');
        Route::get('/passes/live-headcount', [VisitorApiController::class, 'liveHeadcount'])->name('passes.live-headcount');
    });
