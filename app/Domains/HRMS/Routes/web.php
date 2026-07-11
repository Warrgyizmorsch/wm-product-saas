<?php

use App\Domains\HRMS\Controllers\OrgController;
use App\Domains\HRMS\Controllers\EmployeeController;
use App\Domains\HRMS\Controllers\SalaryStructureController;
use App\Domains\HRMS\Controllers\LeaveStructureController;
use App\Domains\HRMS\Controllers\PenalizationPolicyController;
use App\Domains\HRMS\Controllers\RosterController;
use App\Domains\HRMS\Controllers\AssetController;
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

        // Roster Management & Shifts Routing
        Route::get('/roster', [RosterController::class, 'index'])->name('roster.index');
        Route::post('/roster/assign', [RosterController::class, 'assign'])->name('roster.assign');
        Route::post('/roster/update-cell', [RosterController::class, 'updateCell'])->name('roster.update-cell');
        Route::delete('/roster/clear', [RosterController::class, 'clear'])->name('roster.clear');
        Route::post('/roster/shift/store', [RosterController::class, 'storeShift'])->name('shift.store');
        Route::post('/roster/shift/update/{shift}', [RosterController::class, 'updateShift'])->name('shift.update');
        Route::delete('/roster/shift/delete/{shift}', [RosterController::class, 'destroyShift'])->name('shift.destroy');
        // Dynamic Document requesting and uploading
        Route::post('/employees/{employee}/documents/request', [\App\Domains\HRMS\Controllers\EmployeeController::class, 'requestDocument'])->name('employees.documents.request');
        Route::post('/employees/{employee}/documents/upload', [\App\Domains\HRMS\Controllers\EmployeeController::class, 'uploadDocument'])->name('employees.documents.upload');
        Route::delete('/employees/documents/{document}', [\App\Domains\HRMS\Controllers\EmployeeController::class, 'destroyDocument'])->name('employees.documents.destroy');

        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees/store', [EmployeeController::class, 'store'])->name('employees.store');
        Route::post('/employees/update/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::delete('/employees/delete/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/{employee}/adhoc-components', [EmployeeController::class, 'storeAdhocComponent'])->name('employees.adhoc-components.store');
        Route::delete('/employees/adhoc-components/{adhocComponent}', [EmployeeController::class, 'destroyAdhocComponent'])->name('employees.adhoc-components.destroy');
        Route::post('/employees/{employee}/penalties', [EmployeeController::class, 'storePenalty'])->name('employees.penalties.store');
        Route::delete('/employees/penalties/{penalty}', [EmployeeController::class, 'destroyPenalty'])->name('employees.penalties.destroy');
        Route::post('/employees/{employee}/employment-history', [EmployeeController::class, 'storeEmploymentHistory'])->name('employees.history.store');
        Route::delete('/employees/{employee}/employment-history/{history}', [EmployeeController::class, 'destroyEmploymentHistory'])->name('employees.history.destroy');

        Route::get('/salary-structure', [SalaryStructureController::class, 'index'])->name('salary-structure.index');
        Route::post('/salary-structure/component/store', [SalaryStructureController::class, 'storeComponent'])->name('salary-structure.store');
        Route::post('/salary-structure/component/update/{salaryComponent}', [SalaryStructureController::class, 'updateComponent'])->name('salary-structure.update');
        Route::delete('/salary-structure/component/delete/{salaryComponent}', [SalaryStructureController::class, 'destroyComponent'])->name('salary-structure.destroy');

        Route::post('/salary-structure/structure/store', [SalaryStructureController::class, 'storeStructure'])->name('salary-structure.structure.store');
        Route::post('/salary-structure/structure/update/{salaryStructure}', [SalaryStructureController::class, 'updateStructure'])->name('salary-structure.structure.update');
        Route::delete('/salary-structure/structure/delete/{salaryStructure}', [SalaryStructureController::class, 'destroyStructure'])->name('salary-structure.structure.destroy');

        Route::post('/salary-structure/pay-group/store', [SalaryStructureController::class, 'storePayGroup'])->name('salary-structure.pay-group.store');
        Route::post('/salary-structure/pay-group/update/{payGroup}', [SalaryStructureController::class, 'updatePayGroup'])->name('salary-structure.pay-group.update');
        Route::delete('/salary-structure/pay-group/delete/{payGroup}', [SalaryStructureController::class, 'destroyPayGroup'])->name('salary-structure.pay-group.destroy');

        Route::get('/leave-structure', [LeaveStructureController::class, 'index'])->name('leave-structure.index');
        Route::post('/leave-structure/plan/store', [LeaveStructureController::class, 'storePlan'])->name('leave-structure.plan.store');
        Route::post('/leave-structure/plan/update/{leavePlan}', [LeaveStructureController::class, 'updatePlan'])->name('leave-structure.plan.update');
        Route::delete('/leave-structure/plan/delete/{leavePlan}', [LeaveStructureController::class, 'destroyPlan'])->name('leave-structure.plan.destroy');
        Route::post('/leave-structure/type/store', [LeaveStructureController::class, 'storeType'])->name('leave-structure.type.store');
        Route::post('/leave-structure/type/update/{leaveType}', [LeaveStructureController::class, 'updateType'])->name('leave-structure.type.update');
        Route::delete('/leave-structure/type/delete/{leaveType}', [LeaveStructureController::class, 'destroyType'])->name('leave-structure.type.destroy');

        Route::get('/penalization-policy', [PenalizationPolicyController::class, 'index'])->name('penalization-policy.index');
        Route::post('/penalization-policy/store', [PenalizationPolicyController::class, 'store'])->name('penalization-policy.store');

        Route::post('/org/salary-component/store', [OrgController::class, 'storeSalaryComponent'])->name('salary-component.store');
        Route::post('/org/salary-component/update/{salaryComponent}', [OrgController::class, 'updateSalaryComponent'])->name('salary-component.update');

        // Delete routes
        Route::delete('/org/company/delete/{company}', [OrgController::class, 'destroy'])->name('company.destroy');
        Route::delete('/org/business-unit/delete/{businessUnit}', [OrgController::class, 'destroyBusinessUnit'])->name('business-unit.destroy');
        Route::delete('/org/branch/delete/{branch}', [OrgController::class, 'destroyBranch'])->name('branch.destroy');
        Route::delete('/org/department/delete/{department}', [OrgController::class, 'destroyDepartment'])->name('department.destroy');
        Route::delete('/org/designation/delete/{designation}', [OrgController::class, 'destroyDesignation'])->name('designation.destroy');
        Route::delete('/org/salary-component/delete/{salaryComponent}', [OrgController::class, 'destroySalaryComponent'])->name('salary-component.destroy');

        // Leave Type Rules Configuration Route
        Route::post('/leave-structure/type/{leaveType}/rules', [LeaveStructureController::class, 'updateRules'])->name('leave-structure.type.rules');

        Route::view('/track-status', 'modules.hrms.track-status')->name('track-status');

        // Asset Management
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::post('/assets/store', [AssetController::class, 'store'])->name('assets.store');
        Route::post('/assets/update/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/delete/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/category/store', [AssetController::class, 'storeCategory'])->name('assets.category.store');
        Route::post('/assets/{asset}/allocate', [AssetController::class, 'allocate'])->name('assets.allocate');
        Route::post('/assets/{asset}/return', [AssetController::class, 'returnAsset'])->name('assets.return');
        Route::post('/assets/requests/store', [AssetController::class, 'storeRequest'])->name('assets.requests.store');
        Route::post('/assets/requests/{request}/reject', [AssetController::class, 'rejectRequest'])->name('assets.requests.reject');
    });
