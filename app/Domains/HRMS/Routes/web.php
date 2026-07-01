<?php

use App\Domains\HRMS\Controllers\OrgController;
use App\Domains\HRMS\Controllers\SalaryStructureController;
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

        Route::get('/salary-structure', [SalaryStructureController::class, 'index'])->name('salary-structure.index');
        Route::post('/salary-structure/component/store', [SalaryStructureController::class, 'storeComponent'])->name('salary-structure.store');
        Route::post('/salary-structure/component/update/{salaryComponent}', [SalaryStructureController::class, 'updateComponent'])->name('salary-structure.update');
        Route::delete('/salary-structure/component/delete/{salaryComponent}', [SalaryStructureController::class, 'destroyComponent'])->name('salary-structure.destroy');

        Route::post('/org/salary-component/store', [OrgController::class, 'storeSalaryComponent'])->name('salary-component.store');
        Route::post('/org/salary-component/update/{salaryComponent}', [OrgController::class, 'updateSalaryComponent'])->name('salary-component.update');

        // Delete routes
        Route::delete('/org/company/delete/{company}', [OrgController::class, 'destroy'])->name('company.destroy');
        Route::delete('/org/business-unit/delete/{businessUnit}', [OrgController::class, 'destroyBusinessUnit'])->name('business-unit.destroy');
        Route::delete('/org/branch/delete/{branch}', [OrgController::class, 'destroyBranch'])->name('branch.destroy');
        Route::delete('/org/department/delete/{department}', [OrgController::class, 'destroyDepartment'])->name('department.destroy');
        Route::delete('/org/designation/delete/{designation}', [OrgController::class, 'destroyDesignation'])->name('designation.destroy');
        Route::delete('/org/salary-component/delete/{salaryComponent}', [OrgController::class, 'destroySalaryComponent'])->name('salary-component.destroy');
    });
