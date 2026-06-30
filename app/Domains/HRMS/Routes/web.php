<?php

use App\Domains\HRMS\Controllers\OrgController;
use Illuminate\Support\Facades\Route;

Route::prefix('hrms')
    ->as('hrms.')
    ->group(function (): void {
        Route::get('/org', [OrgController::class, 'index'])->name('org.index');
        Route::get('/org/company/create', [OrgController::class, 'create']);
        Route::post('/org/company/store', [OrgController::class, 'store'])->name('company.store');
        Route::post('/org/company/update/{company}', [OrgController::class, 'update'])->name('company.update');
        Route::get('/org/business-unit/create', [OrgController::class, 'createBusinessUnit'])->name('business-unit.create');
        Route::post('/org/business-unit/store', [OrgController::class, 'storeBusinessUnit'])->name('business-unit.store');
        Route::post('/org/business-unit/update/{businessUnit}', [OrgController::class, 'updateBusinessUnit'])->name('business-unit.update');
        Route::get('/org/branch/create', [OrgController::class, 'createBranch'])->name('branch.create');
        Route::post('/org/branch/store', [OrgController::class, 'storeBranch'])->name('branch.store');
        Route::post('/org/branch/update/{branch}', [OrgController::class, 'updateBranch'])->name('branch.update');
        Route::get('/org/department/create', [OrgController::class, 'createDepartment'])->name('department.create');
        Route::post('/org/department/store', [OrgController::class, 'storeDepartment'])->name('department.store');
        Route::post('/org/department/update/{department}', [OrgController::class, 'updateDepartment'])->name('department.update');
        Route::get('/org/designation/create', [OrgController::class, 'createDesignation'])->name('designation.create');
        Route::post('/org/designation/store', [OrgController::class, 'storeDesignation'])->name('designation.store');
        Route::post('/org/designation/update/{designation}', [OrgController::class, 'updateDesignation'])->name('designation.update');
    });
