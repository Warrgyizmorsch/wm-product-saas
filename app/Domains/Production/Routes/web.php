<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Production\Controllers\ProductionBomController;

Route::prefix('production')
    ->as('production.')
    ->group(function (): void {
        Route::post('boms/{bom}/submit', [ProductionBomController::class, 'submitApproval'])
            ->name('boms.submit');
        Route::post('boms/{bom}/approve', [ProductionBomController::class, 'approve'])
            ->name('boms.approve');
        Route::post('boms/{bom}/reject', [ProductionBomController::class, 'reject'])
            ->name('boms.reject');
        Route::post('boms/{bom}/cancel', [ProductionBomController::class, 'cancel'])
            ->name('boms.cancel');
        Route::post('boms/{bom}/duplicate', [ProductionBomController::class, 'duplicateVersion'])
            ->name('boms.duplicate');

        Route::resource('boms', ProductionBomController::class);
    });
