<?php

use App\Domains\Projects\Controllers\MilestoneController;
use App\Domains\Projects\Controllers\ProjectActivityLogController;
use App\Domains\Projects\Controllers\ProjectController;
use App\Domains\Projects\Controllers\ProjectMemberController;
use App\Domains\Projects\Controllers\SubTaskController;
use App\Domains\Projects\Controllers\TaskController;
use App\Domains\Projects\Controllers\TaskDependencyController;
use App\Domains\Projects\Controllers\TaskListController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects')
    ->as('projects.')
    ->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::post('bulk-action', [ProjectController::class, 'bulkAction'])->name('bulk-action');

        Route::get('export', [ProjectController::class, 'export'])->name('export');

        Route::get('lookups/clients', [ProjectController::class, 'searchClients'])->name('lookups.clients');
        Route::get('lookups/owners', [ProjectController::class, 'searchOwners'])->name('lookups.owners');

        Route::get('milestones', [MilestoneController::class, 'index'])->name('milestones.index');

        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::put('{project}', [ProjectController::class, 'update'])->name('update');
        Route::patch('{project}/field', [ProjectController::class, 'updateField'])->name('field');
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

        Route::prefix('{project}/collaborators')
            ->as('collaborators.')
            ->group(function (): void {
                Route::get('search', [ProjectMemberController::class, 'searchCollaborators'])->name('search');
                Route::post('/', [ProjectMemberController::class, 'storeCollaborator'])->name('store');
                Route::delete('{member}', [ProjectMemberController::class, 'destroyCollaborator'])->name('destroy');
            });

        Route::prefix('{project}/milestones')
            ->as('milestones.')
            ->scopeBindings()
            ->group(function (): void {
                Route::get('{milestone}', [MilestoneController::class, 'show'])->name('show');
                Route::post('/', [MilestoneController::class, 'store'])->name('store');
                Route::put('{milestone}', [MilestoneController::class, 'update'])->name('update');
                Route::patch('{milestone}/field', [MilestoneController::class, 'updateField'])->name('field');
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
                Route::get('{task}', [TaskController::class, 'show'])->name('show');
                Route::post('/', [TaskController::class, 'store'])->name('store');
                Route::put('{task}', [TaskController::class, 'update'])->name('update');
                Route::patch('{task}/field', [TaskController::class, 'updateField'])->name('field');
                Route::delete('{task}', [TaskController::class, 'destroy'])->name('destroy');
                Route::patch('{task}/status', [TaskController::class, 'updateStatus'])->name('update-status');
                Route::patch('{task}/assign', [TaskController::class, 'assign'])->name('assign');

                Route::prefix('{task}/subtasks')
                    ->as('subtasks.')
                    ->scopeBindings()
                    ->group(function (): void {
                        Route::post('/', [SubTaskController::class, 'store'])->name('store');
                        Route::put('{subTask}', [SubTaskController::class, 'update'])->name('update');
                        Route::patch('{subTask}/toggle-complete', [SubTaskController::class, 'toggleComplete'])->name('toggle-complete');
                        Route::delete('{subTask}', [SubTaskController::class, 'destroy'])->name('destroy');
                    });

                Route::prefix('{task}/dependencies')
                    ->as('dependencies.')
                    ->scopeBindings()
                    ->group(function (): void {
                        Route::post('/', [TaskDependencyController::class, 'store'])->name('store');
                        Route::delete('{dependency}', [TaskDependencyController::class, 'destroy'])->name('destroy');
                    });
            });
    });
