<?php

use App\Domains\Projects\Controllers\MilestoneController;
use App\Domains\Projects\Controllers\ProjectActivityLogController;
use App\Domains\Projects\Controllers\ProjectController;
use App\Domains\Projects\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects')
    ->as('projects.')
    ->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/', [ProjectController::class, 'store'])->name('store');

        Route::get('milestones', [MilestoneController::class, 'index'])->name('milestones.index');

        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');

        Route::get('{project}/activity', [ProjectActivityLogController::class, 'index'])->name('activity');

        Route::prefix('{project}/members')
            ->as('members.')
            ->group(function (): void {
                Route::post('/', [ProjectMemberController::class, 'store'])->name('store');
                Route::put('{member}', [ProjectMemberController::class, 'update'])->name('update');
                Route::patch('{member}/toggle-active', [ProjectMemberController::class, 'toggleActive'])->name('toggle-active');
                Route::delete('{member}', [ProjectMemberController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('{project}/milestones')
            ->as('milestones.')
            ->scopeBindings()
            ->group(function (): void {
                Route::post('/', [MilestoneController::class, 'store'])->name('store');
                Route::put('{milestone}', [MilestoneController::class, 'update'])->name('update');
                Route::delete('{milestone}', [MilestoneController::class, 'destroy'])->name('destroy');
            });
    });
