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
        
        // Pass Operational Actions
        Route::post('/passes/{id}/check-in', [VisitorController::class, 'checkIn'])->name('passes.check-in');
        Route::post('/passes/{id}/check-out', [VisitorController::class, 'checkOut'])->name('passes.check-out');
        Route::post('/passes/{id}/mark-arrived', [VisitorController::class, 'markArrived'])->name('passes.mark-arrived');
        Route::post('/passes/{id}/start-meeting', [VisitorController::class, 'startMeeting'])->name('passes.start-meeting');
        Route::post('/passes/{id}/notify-host', [VisitorController::class, 'notifyHostManual'])->name('passes.notify-host');
        Route::post('/passes/{id}/deny-entry', [VisitorController::class, 'denyEntry'])->name('passes.deny-entry');
        Route::post('/passes/{id}/extend', [VisitorController::class, 'extendVisit'])->name('passes.extend');
        Route::post('/passes/{id}/report-incident', [VisitorController::class, 'reportIncident'])->name('passes.report-incident');
        Route::post('/visitors/{id}/toggle-blacklist', [VisitorController::class, 'toggleBlacklist'])->name('visitors.toggle-blacklist');

        // Fast Lookup AJAX (Phone, Email, Company)
        Route::get('/lookup', [VisitorController::class, 'lookup'])->name('lookup');

        // Import & Export
        Route::get('/export', [VisitorController::class, 'export'])->name('export');
        Route::get('/sample-template', [VisitorController::class, 'downloadSample'])->name('sample-template');
        Route::post('/import', [VisitorController::class, 'import'])->name('import');
        Route::post('/import/parse', [VisitorController::class, 'parseImportFile'])->name('import.parse');
        Route::post('/import/process', [VisitorController::class, 'processMappedImport'])->name('import.process');

        // 2. Passes Detail / Create
        Route::get('/passes/create', [VisitorPassController::class, 'create'])->name('passes.create');
        Route::post('/passes', [VisitorPassController::class, 'store'])->name('passes.store');
        Route::get('/passes/{pass}', [VisitorPassController::class, 'show'])->name('passes.show');

        // 3. My Approvals (Employee / Host Self-Service)
        Route::get('/approvals', [VisitorApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{id}/approve', [VisitorApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{id}/reject', [VisitorApprovalController::class, 'reject'])->name('approvals.reject');
    });
