<?php

use App\Domains\Projects\Controllers\MilestoneController;
use App\Domains\Projects\Controllers\ProjectActivityLogController;
use App\Domains\Projects\Controllers\ProjectController;
use App\Domains\Projects\Controllers\ProjectMemberController;
use App\Domains\Projects\Controllers\TaskController;
use App\Domains\Projects\Controllers\TaskListController;
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

        Route::prefix('{project}/tasklists')
            ->as('tasklists.')
            ->scopeBindings()
            ->group(function (): void {
                Route::post('/', [TaskListController::class, 'store'])->name('store');
                Route::put('{taskList}', [TaskListController::class, 'update'])->name('update');
                Route::delete('{taskList}', [TaskListController::class, 'destroy'])->name('destroy');
                Route::patch('{taskList}/move-up', [TaskListController::class, 'moveUp'])->name('move-up');
                Route::patch('{taskList}/move-down', [TaskListController::class, 'moveDown'])->name('move-down');
            });

        Route::prefix('{project}/tasks')
            ->as('tasks.')
            ->scopeBindings()
            ->group(function (): void {
                Route::post('/', [TaskController::class, 'store'])->name('store');
                Route::put('{task}', [TaskController::class, 'update'])->name('update');
                Route::delete('{task}', [TaskController::class, 'destroy'])->name('destroy');
                Route::patch('{task}/status', [TaskController::class, 'updateStatus'])->name('update-status');
                Route::patch('{task}/assign', [TaskController::class, 'assign'])->name('assign');
            });
    });
