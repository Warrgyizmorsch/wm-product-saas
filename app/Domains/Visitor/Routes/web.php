<?php

use App\Domains\Visitor\Controllers\VisitorController;
use App\Domains\Visitor\Controllers\VisitorPassController;
use App\Domains\Visitor\Controllers\VisitorApprovalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Visitor Management Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('visitor')
    ->as('visitor.')
    ->group(function (): void {
        // 1. Gate Desk / Main Operational Overview
        Route::get('/', [VisitorController::class, 'index'])->name('index');
        Route::post('/', [VisitorController::class, 'store'])->name('store');
        Route::post('/passes/{id}/check-in', [VisitorController::class, 'checkIn'])->name('passes.check-in');
        Route::post('/passes/{id}/check-out', [VisitorController::class, 'checkOut'])->name('passes.check-out');

        // Fast Lookup AJAX
        Route::get('/lookup', [VisitorController::class, 'lookup'])->name('lookup');

        // 2. Passes Detail / Create
        Route::get('/passes/create', [VisitorPassController::class, 'create'])->name('passes.create');
        Route::post('/passes', [VisitorPassController::class, 'store'])->name('passes.store');
        Route::get('/passes/{pass}', [VisitorPassController::class, 'show'])->name('passes.show');

        // 3. My Approvals (Employee / Host Self-Service)
        Route::get('/approvals', [VisitorApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{id}/approve', [VisitorApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{id}/reject', [VisitorApprovalController::class, 'reject'])->name('approvals.reject');
    });
