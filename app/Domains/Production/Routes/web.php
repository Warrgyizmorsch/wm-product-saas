<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Production\Controllers\ProductionBomController;
use App\Domains\Production\Controllers\WorkCenterController;
use App\Domains\Production\Controllers\MachineController;
use App\Domains\Production\Controllers\RoutingController;

Route::prefix('production')
    ->as('production.')
    ->group(function (): void {

        // ── BOM (Frozen) ──────────────────────────────────────────────────────
        Route::get('boms/check-child/{productId}', [ProductionBomController::class, 'checkChildBom'])->name('boms.check-child');
        Route::post('boms/{bom}/create-revision',  [ProductionBomController::class, 'createRevision'])->name('boms.create-revision');
        Route::post('boms/{bom}/submit',    [ProductionBomController::class, 'submitApproval'])->name('boms.submit');
        Route::post('boms/{bom}/approve',   [ProductionBomController::class, 'approve'])->name('boms.approve');
        Route::post('boms/{bom}/reject',    [ProductionBomController::class, 'reject'])->name('boms.reject');
        Route::post('boms/{bom}/cancel',    [ProductionBomController::class, 'cancel'])->name('boms.cancel');
        Route::post('boms/{bom}/duplicate', [ProductionBomController::class, 'duplicateVersion'])->name('boms.duplicate');
        Route::resource('boms', ProductionBomController::class);

        // ── Work Centers ──────────────────────────────────────────────────────
        Route::resource('work-centers', WorkCenterController::class);

        // ── Machines (AJAX endpoint must be before resource to avoid routing conflict) ──
        Route::get('machines/by-work-center/{workCenter}', [MachineController::class, 'byWorkCenter'])
            ->name('machines.by-work-center');
        Route::resource('machines', MachineController::class)->except(['show']);

        // ── Routing ───────────────────────────────────────────────────────────
        Route::post('routing/{routing}/submit',    [RoutingController::class, 'submitApproval'])->name('routing.submit');
        Route::post('routing/{routing}/approve',   [RoutingController::class, 'approve'])->name('routing.approve');
        Route::post('routing/{routing}/reject',    [RoutingController::class, 'reject'])->name('routing.reject');
        Route::post('routing/{routing}/cancel',    [RoutingController::class, 'cancel'])->name('routing.cancel');
        Route::post('routing/{routing}/duplicate', [RoutingController::class, 'duplicateVersion'])->name('routing.duplicate');
        Route::get('routing/{routing}/operations', [RoutingController::class, 'getOperationsForAjax'])->name('routing.operations');
        Route::resource('routing', RoutingController::class);
    });
