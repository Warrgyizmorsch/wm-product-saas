<?php

use App\Domains\Access\Controllers\AuditLogController;
use App\Domains\Access\Controllers\RoleController;
use App\Domains\Access\Controllers\UserController;
use App\Domains\Access\Controllers\UserPermissionOverrideController;
use Illuminate\Support\Facades\Route;

Route::prefix('access')
    ->as('access.')
    ->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('users/{user}/overrides', [UserPermissionOverrideController::class, 'edit'])->name('users.overrides.edit');
        Route::put('users/{user}/overrides', [UserPermissionOverrideController::class, 'update'])->name('users.overrides.update');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });
