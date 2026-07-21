<?php

use App\Domains\HRMS\Controllers\OrgController;
use App\Domains\HRMS\Controllers\EmployeeController;
use App\Domains\HRMS\Controllers\SalaryStructureController;
use App\Domains\HRMS\Controllers\LeaveStructureController;
use App\Domains\HRMS\Controllers\PenalizationPolicyController;
use App\Domains\HRMS\Controllers\RosterController;
use App\Domains\HRMS\Controllers\AssetController;
use App\Domains\HRMS\Controllers\LeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('hrms')
    ->as('hrms.')
    ->group(function (): void {
        
        // Org Structure Management
        Route::prefix('org')->group(function (): void {
            Route::get('/', [OrgController::class, 'index'])->name('org.index');
            
            Route::get('/company/create', [OrgController::class, 'create']);
            Route::post('/company/store', [OrgController::class, 'store'])->name('company.store');
            Route::post('/company/update/{company}', [OrgController::class, 'update'])->name('company.update');
            Route::delete('/company/delete/{company}', [OrgController::class, 'destroy'])->name('company.destroy');

            Route::get('/business-unit/create', [OrgController::class, 'createBusinessUnit'])->name('business-unit.create');
            Route::post('/business-unit/store', [OrgController::class, 'storeBusinessUnit'])->name('business-unit.store');
            Route::post('/business-unit/update/{businessUnit}', [OrgController::class, 'updateBusinessUnit'])->name('business-unit.update');
            Route::delete('/business-unit/delete/{businessUnit}', [OrgController::class, 'destroyBusinessUnit'])->name('business-unit.destroy');

            Route::get('/branch/create', [OrgController::class, 'createBranch'])->name('branch.create');
            Route::post('/branch/store', [OrgController::class, 'storeBranch'])->name('branch.store');
            Route::post('/branch/update/{branch}', [OrgController::class, 'updateBranch'])->name('branch.update');
            Route::delete('/branch/delete/{branch}', [OrgController::class, 'destroyBranch'])->name('branch.destroy');

            Route::get('/department/create', [OrgController::class, 'createDepartment'])->name('department.create');
            Route::post('/department/store', [OrgController::class, 'storeDepartment'])->name('department.store');
            Route::post('/department/update/{department}', [OrgController::class, 'updateDepartment'])->name('department.update');
            Route::delete('/department/delete/{department}', [OrgController::class, 'destroyDepartment'])->name('department.destroy');

            Route::get('/designation/create', [OrgController::class, 'createDesignation'])->name('designation.create');
            Route::post('/designation/store', [OrgController::class, 'storeDesignation'])->name('designation.store');
            Route::post('/designation/update/{designation}', [OrgController::class, 'updateDesignation'])->name('designation.update');
            Route::delete('/designation/delete/{designation}', [OrgController::class, 'destroyDesignation'])->name('designation.destroy');

            Route::post('/salary-component/store', [OrgController::class, 'storeSalaryComponent'])->name('salary-component.store');
            Route::post('/salary-component/update/{salaryComponent}', [OrgController::class, 'updateSalaryComponent'])->name('salary-component.update');
            Route::delete('/salary-component/delete/{salaryComponent}', [OrgController::class, 'destroySalaryComponent'])->name('salary-component.destroy');
        });

        // Roster Management & Shifts
        Route::prefix('roster')->group(function (): void {
            Route::get('/', [RosterController::class, 'index'])->name('roster.index');
            Route::post('/assign', [RosterController::class, 'assign'])->name('roster.assign');
            Route::post('/update-cell', [RosterController::class, 'updateCell'])->name('roster.update-cell');
            Route::post('/update-weekly-pattern', [RosterController::class, 'updateWeeklyPattern'])->name('roster.update-weekly-pattern');
            Route::post('/weekly/assign', [RosterController::class, 'assignWeekly'])->name('roster.assign-weekly');
            Route::delete('/weekly/clear', [RosterController::class, 'clearWeekly'])->name('roster.clear-weekly');
            Route::delete('/clear', [RosterController::class, 'clear'])->name('roster.clear');
            Route::post('/shift/store', [RosterController::class, 'storeShift'])->name('shift.store');
            Route::post('/shift/update/{shift}', [RosterController::class, 'updateShift'])->name('shift.update');
            Route::delete('/shift/delete/{shift}', [RosterController::class, 'destroyShift'])->name('shift.destroy');
        });

        // Employee Management
        Route::prefix('employees')->group(function (): void {
            Route::get('/', [EmployeeController::class, 'index'])->name('employees.index');
            Route::post('/import', [EmployeeController::class, 'import'])->name('employees.import');
            Route::get('/export', [EmployeeController::class, 'export'])->name('employees.export');
            Route::get('/import/template', [EmployeeController::class, 'downloadTemplate'])->name('employees.import.template');
            Route::post('/store', [EmployeeController::class, 'store'])->name('employees.store');
            Route::post('/update/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::get('/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
            Route::delete('/delete/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
            
            Route::post('/{employee}/documents/request', [EmployeeController::class, 'requestDocument'])->name('employees.documents.request');
            Route::post('/{employee}/documents/upload', [EmployeeController::class, 'uploadDocument'])->name('employees.documents.upload');
            Route::delete('/documents/{document}', [EmployeeController::class, 'destroyDocument'])->name('employees.documents.destroy');

            Route::post('/{employee}/adhoc-components', [EmployeeController::class, 'storeAdhocComponent'])->name('employees.adhoc-components.store');
            Route::delete('/adhoc-components/{adhocComponent}', [EmployeeController::class, 'destroyAdhocComponent'])->name('employees.adhoc-components.destroy');
            Route::post('/{employee}/penalties', [EmployeeController::class, 'storePenalty'])->name('employees.penalties.store');
            Route::delete('/penalties/{penalty}', [EmployeeController::class, 'destroyPenalty'])->name('employees.penalties.destroy');
            Route::post('/{employee}/employment-history', [EmployeeController::class, 'storeEmploymentHistory'])->name('employees.history.store');
            Route::delete('/{employee}/employment-history/{history}', [EmployeeController::class, 'destroyEmploymentHistory'])->name('employees.history.destroy');
        });

        // Salary Structure Management
        Route::prefix('salary-structure')->group(function (): void {
            Route::get('/', [SalaryStructureController::class, 'index'])->name('salary-structure.index');
            
            Route::post('/component/store', [SalaryStructureController::class, 'storeComponent'])->name('salary-structure.store');
            Route::post('/component/update/{salaryComponent}', [SalaryStructureController::class, 'updateComponent'])->name('salary-structure.update');
            Route::delete('/component/delete/{salaryComponent}', [SalaryStructureController::class, 'destroyComponent'])->name('salary-structure.destroy');

            Route::post('/structure/store', [SalaryStructureController::class, 'storeStructure'])->name('salary-structure.structure.store');
            Route::post('/structure/update/{salaryStructure}', [SalaryStructureController::class, 'updateStructure'])->name('salary-structure.structure.update');
            Route::delete('/structure/delete/{salaryStructure}', [SalaryStructureController::class, 'destroyStructure'])->name('salary-structure.structure.destroy');

            Route::post('/pay-group/store', [SalaryStructureController::class, 'storePayGroup'])->name('salary-structure.pay-group.store');
            Route::post('/pay-group/update/{payGroup}', [SalaryStructureController::class, 'updatePayGroup'])->name('salary-structure.pay-group.update');
            Route::delete('/pay-group/delete/{payGroup}', [SalaryStructureController::class, 'destroyPayGroup'])->name('salary-structure.pay-group.destroy');
        });

        // Leave Structure Management
        Route::prefix('leave-structure')->group(function (): void {
            Route::get('/', [LeaveStructureController::class, 'index'])->name('leave-structure.index');
            Route::post('/plan/store', [LeaveStructureController::class, 'storePlan'])->name('leave-structure.plan.store');
            Route::post('/plan/update/{leavePlan}', [LeaveStructureController::class, 'updatePlan'])->name('leave-structure.plan.update');
            Route::delete('/plan/delete/{leavePlan}', [LeaveStructureController::class, 'destroyPlan'])->name('leave-structure.plan.destroy');
            Route::post('/plan/renew', [LeaveStructureController::class, 'renewPlanBalances'])->name('leave-structure.plan.renew');
            
            Route::get('/transition', [LeaveStructureController::class, 'transitionView'])->name('leave-structure.transition');
            Route::post('/transition/process', [LeaveStructureController::class, 'processTransition'])->name('leave-structure.transition.process');
            
            Route::post('/type/store', [LeaveStructureController::class, 'storeType'])->name('leave-structure.type.store');
            Route::post('/type/update/{leaveType}', [LeaveStructureController::class, 'updateType'])->name('leave-structure.type.update');
            Route::delete('/type/delete/{leaveType}', [LeaveStructureController::class, 'destroyType'])->name('leave-structure.type.destroy');
            Route::post('/type/{leaveType}/rules', [LeaveStructureController::class, 'updateRules'])->name('leave-structure.type.rules');
        });

        // Penalization Policy Management
        Route::prefix('penalization-policy')->group(function (): void {
            Route::get('/', [PenalizationPolicyController::class, 'index'])->name('penalization-policy.index');
            Route::post('/store', [PenalizationPolicyController::class, 'store'])->name('penalization-policy.store');
        });

        // Leave Request Management
        Route::prefix('leaves')->group(function (): void {
            Route::get('/', [LeaveRequestController::class, 'index'])->name('leaves.index');
            Route::post('/store', [LeaveRequestController::class, 'store'])->name('leaves.store');
            Route::post('/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
            Route::post('/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');
            Route::post('/{leaveRequest}/update-status', [LeaveRequestController::class, 'updateStatus'])->name('leaves.update-status');
            
            // Leave Encashments
            Route::post('/encashment/store', [\App\Domains\HRMS\Controllers\LeaveEncashmentController::class, 'store'])->name('leaves.encashment.store');
            Route::post('/encashment/{leaveEncashment}/approve', [\App\Domains\HRMS\Controllers\LeaveEncashmentController::class, 'approve'])->name('leaves.encashment.approve');
            Route::post('/encashment/{leaveEncashment}/reject', [\App\Domains\HRMS\Controllers\LeaveEncashmentController::class, 'reject'])->name('leaves.encashment.reject');
            Route::delete('/encashment/{leaveEncashment}', [\App\Domains\HRMS\Controllers\LeaveEncashmentController::class, 'destroy'])->name('leaves.encashment.destroy');
        });

        // Asset Management
        Route::prefix('assets')->group(function (): void {
            Route::get('/', [AssetController::class, 'index'])->name('assets.index');
            Route::post('/store', [AssetController::class, 'store'])->name('assets.store');
            Route::post('/update/{asset}', [AssetController::class, 'update'])->name('assets.update');
            Route::delete('/delete/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
            Route::get('/export', [AssetController::class, 'export'])->name('assets.export');
            Route::post('/import', [AssetController::class, 'import'])->name('assets.import');
            Route::get('/import/template', [AssetController::class, 'downloadTemplate'])->name('assets.import.template');
            
            Route::get('/categories/export', [AssetController::class, 'exportCategories'])->name('assets.categories.export');
            Route::post('/categories/import', [AssetController::class, 'importCategories'])->name('assets.categories.import');
            Route::get('/categories/import/template', [AssetController::class, 'downloadCategoriesTemplate'])->name('assets.categories.import.template');
            
            Route::post('/category/store', [AssetController::class, 'storeCategory'])->name('assets.category.store');
            Route::post('/category/update/{assetCategory}', [AssetController::class, 'updateCategory'])->name('assets.category.update');
            Route::delete('/category/delete/{assetCategory}', [AssetController::class, 'destroyCategory'])->name('assets.category.destroy');
            
            Route::post('/item/store', [AssetController::class, 'storeItem'])->name('assets.item.store');
            Route::post('/item/update/{assetItem}', [AssetController::class, 'updateItem'])->name('assets.item.update');
            Route::delete('/item/delete/{assetItem}', [AssetController::class, 'destroyItem'])->name('assets.item.destroy');
            
            Route::post('/{asset}/allocate', [AssetController::class, 'allocate'])->name('assets.allocate');
            Route::post('/{asset}/return', [AssetController::class, 'returnAsset'])->name('assets.return');
            
            Route::post('/requests/store', [AssetController::class, 'storeRequest'])->name('assets.requests.store');
            Route::post('/requests/{assetRequest}/reject', [AssetController::class, 'rejectRequest'])->name('assets.requests.reject');
            Route::post('/requests/{assetRequest}/allocate-direct', [AssetController::class, 'allocateDirect'])->name('assets.requests.allocate-direct');
            Route::post('/requests/{assetRequest}/allocate', [AssetController::class, 'allocateRequest'])->name('assets.requests.allocate');
            Route::post('/requests/bulk-allocate', [AssetController::class, 'bulkAllocate'])->name('assets.requests.bulk-allocate');
            Route::post('/requests/bulk-reject', [AssetController::class, 'bulkReject'])->name('assets.requests.bulk-reject');
        });

        Route::view('/track-status', 'modules.hrms.track-status')->name('track-status');
    });
