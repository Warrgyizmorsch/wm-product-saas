<?php

use Illuminate\Support\Facades\Route;
use App\Domains\HRMS\Controllers\Api\OrgApiController;
use App\Domains\HRMS\Controllers\Api\SalaryStructureApiController;
use App\Domains\HRMS\Controllers\Api\LeaveStructureApiController;
use App\Domains\HRMS\Controllers\Api\PenalizationPolicyApiController;
use App\Domains\HRMS\Controllers\Api\RosterApiController;
use App\Domains\HRMS\Controllers\Api\AssetApiController;
use App\Domains\HRMS\Controllers\Api\EmployeeApiController;
use App\Domains\HRMS\Controllers\Api\LeaveRequestApiController;
use App\Domains\HRMS\Controllers\Api\LeaveEncashmentApiController;

/*
|--------------------------------------------------------------------------
| HRMS REST API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. ORGANIZATION STRUCTURE API ROUTES
// ==========================================
Route::prefix('api/hrms/org')
    ->middleware(['web'])
    ->name('api.hrms.org.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [OrgApiController::class, 'summary'])->name('summary');

        // Companies (Legal Entities) APIs
        Route::get('/companies', [OrgApiController::class, 'indexCompanies'])->name('companies.index');
        Route::post('/companies', [OrgApiController::class, 'storeCompany'])->name('companies.store');
        Route::get('/companies/{company}', [OrgApiController::class, 'showCompany'])->name('companies.show');
        Route::put('/companies/{company}', [OrgApiController::class, 'updateCompany'])->name('companies.update');
        Route::delete('/companies/{company}', [OrgApiController::class, 'destroyCompany'])->name('companies.destroy');

        // Business Units APIs
        Route::get('/business-units', [OrgApiController::class, 'indexBusinessUnits'])->name('business-units.index');
        Route::post('/business-units', [OrgApiController::class, 'storeBusinessUnit'])->name('business-units.store');
        Route::get('/business-units/{businessUnit}', [OrgApiController::class, 'showBusinessUnit'])->name('business-units.show');
        Route::put('/business-units/{businessUnit}', [OrgApiController::class, 'updateBusinessUnit'])->name('business-units.update');
        Route::delete('/business-units/{businessUnit}', [OrgApiController::class, 'destroyBusinessUnit'])->name('business-units.destroy');

        // Branches APIs
        Route::get('/branches', [OrgApiController::class, 'indexBranches'])->name('branches.index');
        Route::post('/branches', [OrgApiController::class, 'storeBranch'])->name('branches.store');
        Route::get('/branches/{branch}', [OrgApiController::class, 'showBranch'])->name('branches.show');
        Route::put('/branches/{branch}', [OrgApiController::class, 'updateBranch'])->name('branches.update');
        Route::delete('/branches/{branch}', [OrgApiController::class, 'destroyBranch'])->name('branches.destroy');

        // Departments APIs
        Route::get('/departments', [OrgApiController::class, 'indexDepartments'])->name('departments.index');
        Route::post('/departments', [OrgApiController::class, 'storeDepartment'])->name('departments.store');
        Route::get('/departments/{department}', [OrgApiController::class, 'showDepartment'])->name('departments.show');
        Route::put('/departments/{department}', [OrgApiController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [OrgApiController::class, 'destroyDepartment'])->name('departments.destroy');

        // Designations APIs
        Route::get('/designations', [OrgApiController::class, 'indexDesignations'])->name('designations.index');
        Route::post('/designations', [OrgApiController::class, 'storeDesignation'])->name('designations.store');
        Route::get('/designations/{designation}', [OrgApiController::class, 'showDesignation'])->name('designations.show');
        Route::put('/designations/{designation}', [OrgApiController::class, 'updateDesignation'])->name('designations.update');
        Route::delete('/designations/{designation}', [OrgApiController::class, 'destroyDesignation'])->name('designations.destroy');
    });

// ==========================================
// 2. SALARY STRUCTURE API ROUTES
// ==========================================
Route::prefix('api/hrms/salary-structure')
    ->middleware(['web'])
    ->name('api.hrms.salary-structure.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [SalaryStructureApiController::class, 'summary'])->name('summary');

        // Pay Groups APIs
        Route::get('/pay-groups', [SalaryStructureApiController::class, 'indexPayGroups'])->name('pay-groups.index');
        Route::post('/pay-groups', [SalaryStructureApiController::class, 'storePayGroup'])->name('pay-groups.store');
        Route::get('/pay-groups/{payGroup}', [SalaryStructureApiController::class, 'showPayGroup'])->name('pay-groups.show');
        Route::put('/pay-groups/{payGroup}', [SalaryStructureApiController::class, 'updatePayGroup'])->name('pay-groups.update');
        Route::delete('/pay-groups/{payGroup}', [SalaryStructureApiController::class, 'destroyPayGroup'])->name('pay-groups.destroy');

        // Salary Components APIs (Recurring & Ad-hoc)
        Route::get('/components', [SalaryStructureApiController::class, 'indexComponents'])->name('components.index');
        Route::post('/components', [SalaryStructureApiController::class, 'storeComponent'])->name('components.store');
        Route::get('/components/{salaryComponent}', [SalaryStructureApiController::class, 'showComponent'])->name('components.show');
        Route::put('/components/{salaryComponent}', [SalaryStructureApiController::class, 'updateComponent'])->name('components.update');
        Route::delete('/components/{salaryComponent}', [SalaryStructureApiController::class, 'destroyComponent'])->name('components.destroy');

        // Salary Structure Slabs APIs
        Route::get('/structures', [SalaryStructureApiController::class, 'indexStructures'])->name('structures.index');
        Route::post('/structures', [SalaryStructureApiController::class, 'storeStructure'])->name('structures.store');
        Route::get('/structures/{salaryStructure}', [SalaryStructureApiController::class, 'showStructure'])->name('structures.show');
        Route::put('/structures/{salaryStructure}', [SalaryStructureApiController::class, 'updateStructure'])->name('structures.update');
        Route::delete('/structures/{salaryStructure}', [SalaryStructureApiController::class, 'destroyStructure'])->name('structures.destroy');
    });

// ==========================================
// 3. LEAVE STRUCTURE API ROUTES
// ==========================================
Route::prefix('api/hrms/leave-structure')
    ->middleware(['web'])
    ->name('api.hrms.leave-structure.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [LeaveStructureApiController::class, 'summary'])->name('summary');

        // Leave Plans APIs
        Route::get('/plans', [LeaveStructureApiController::class, 'indexPlans'])->name('plans.index');
        Route::post('/plans', [LeaveStructureApiController::class, 'storePlan'])->name('plans.store');
        Route::get('/plans/{leavePlan}', [LeaveStructureApiController::class, 'showPlan'])->name('plans.show');
        Route::put('/plans/{leavePlan}', [LeaveStructureApiController::class, 'updatePlan'])->name('plans.update');
        Route::delete('/plans/{leavePlan}', [LeaveStructureApiController::class, 'destroyPlan'])->name('plans.destroy');

        // Year-End Renewal & Plan Transition APIs
        Route::post('/plans/renew', [LeaveStructureApiController::class, 'renewPlanBalances'])->name('plans.renew');
        Route::post('/plans/transition', [LeaveStructureApiController::class, 'processTransition'])->name('plans.transition');

        // Leave Types & Policy Rules APIs
        Route::get('/types', [LeaveStructureApiController::class, 'indexTypes'])->name('types.index');
        Route::post('/types', [LeaveStructureApiController::class, 'storeType'])->name('types.store');
        Route::get('/types/{leaveType}', [LeaveStructureApiController::class, 'showType'])->name('types.show');
        Route::put('/types/{leaveType}', [LeaveStructureApiController::class, 'updateType'])->name('types.update');
        Route::put('/types/{leaveType}/rules', [LeaveStructureApiController::class, 'updateRules'])->name('types.rules');
        Route::delete('/types/{leaveType}', [LeaveStructureApiController::class, 'destroyType'])->name('types.destroy');
    });

// ==========================================
// 4. PENALIZATION POLICY API ROUTES
// ==========================================
Route::prefix('api/hrms/penalization-policy')
    ->middleware(['web'])
    ->name('api.hrms.penalization-policy.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [PenalizationPolicyApiController::class, 'summary'])->name('summary');

        // Rule Details API
        Route::get('/rules/{ruleType}', [PenalizationPolicyApiController::class, 'showRule'])->name('rules.show');

        // Save / Update Policy Rule API (late_arrival, missing_logs, under_hours)
        Route::post('/save', [PenalizationPolicyApiController::class, 'saveRule'])->name('save');

        // Delete Policy Rule API
        Route::delete('/rules/{attendancePenalty}', [PenalizationPolicyApiController::class, 'destroyRule'])->name('rules.destroy');
    });

// ==========================================
// 5. WORK ROSTER & SHIFT SCHEDULING API ROUTES
// ==========================================
Route::prefix('api/hrms/roster')
    ->middleware(['web'])
    ->name('api.hrms.roster.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [RosterApiController::class, 'summary'])->name('summary');

        // Shift Master APIs
        Route::get('/shifts', [RosterApiController::class, 'indexShifts'])->name('shifts.index');
        Route::post('/shifts', [RosterApiController::class, 'storeShift'])->name('shifts.store');
        Route::get('/shifts/{shift}', [RosterApiController::class, 'showShift'])->name('shifts.show');
        Route::put('/shifts/{shift}', [RosterApiController::class, 'updateShift'])->name('shifts.update');
        Route::delete('/shifts/{shift}', [RosterApiController::class, 'destroyShift'])->name('shifts.destroy');

        // Shift Roster Matrix & Scheduling APIs
        Route::get('/calendar', [RosterApiController::class, 'matrix'])->name('calendar');
        Route::post('/assign', [RosterApiController::class, 'assign'])->name('assign');
        Route::put('/cell', [RosterApiController::class, 'updateCell'])->name('cell.update');
        Route::put('/weekly-pattern', [RosterApiController::class, 'updateWeeklyPattern'])->name('weekly-pattern.update');
        Route::post('/clear', [RosterApiController::class, 'clear'])->name('clear');
    });

// ==========================================
// 6. ASSET MANAGEMENT API ROUTES
// ==========================================
Route::prefix('api/hrms/assets')
    ->middleware(['web'])
    ->name('api.hrms.assets.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [AssetApiController::class, 'summary'])->name('summary');

        // Asset Registry APIs
        Route::get('/registry', [AssetApiController::class, 'indexAssets'])->name('registry.index');
        Route::post('/registry', [AssetApiController::class, 'storeAsset'])->name('registry.store');
        Route::get('/registry/{asset}', [AssetApiController::class, 'showAsset'])->name('registry.show');
        Route::put('/registry/{asset}', [AssetApiController::class, 'updateAsset'])->name('registry.update');
        Route::delete('/registry/{asset}', [AssetApiController::class, 'destroyAsset'])->name('registry.destroy');
        Route::post('/registry/{asset}/allocate', [AssetApiController::class, 'allocateAsset'])->name('registry.allocate');
        Route::post('/registry/{asset}/return', [AssetApiController::class, 'returnAsset'])->name('registry.return');

        // Asset Categories APIs
        Route::get('/categories', [AssetApiController::class, 'indexCategories'])->name('categories.index');
        Route::post('/categories', [AssetApiController::class, 'storeCategory'])->name('categories.store');
        Route::get('/categories/{category}', [AssetApiController::class, 'showCategory'])->name('categories.show');
        Route::put('/categories/{category}', [AssetApiController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AssetApiController::class, 'destroyCategory'])->name('categories.destroy');

        // Asset Requests APIs
        Route::get('/requests', [AssetApiController::class, 'indexRequests'])->name('requests.index');
        Route::post('/requests', [AssetApiController::class, 'storeRequest'])->name('requests.store');
        Route::post('/requests/{assetRequest}/reject', [AssetApiController::class, 'rejectRequest'])->name('requests.reject');
        Route::post('/requests/{assetRequest}/allocate-direct', [AssetApiController::class, 'allocateDirectRequest'])->name('requests.allocate-direct');
        Route::post('/requests/bulk-allocate', [AssetApiController::class, 'bulkAllocateRequests'])->name('requests.bulk-allocate');
    });

// ==========================================
// 7. EMPLOYEE DIRECTORY & PROFILE API ROUTES
// ==========================================
Route::prefix('api/hrms/employees')
    ->middleware(['web'])
    ->name('api.hrms.employees.')
    ->group(function () {

        // Summary & Metadata API
        Route::get('/summary', [EmployeeApiController::class, 'summary'])->name('summary');

        // Import / Export APIs
        Route::get('/export', [EmployeeApiController::class, 'export'])->name('export');
        Route::post('/import', [EmployeeApiController::class, 'import'])->name('import');

        // Employee Directory & Profile CRUD APIs
        Route::get('/', [EmployeeApiController::class, 'indexEmployees'])->name('index');
        Route::post('/', [EmployeeApiController::class, 'storeEmployee'])->name('store');
        Route::get('/{employee}', [EmployeeApiController::class, 'showEmployee'])->name('show');
        Route::put('/{employee}', [EmployeeApiController::class, 'updateEmployee'])->name('update');
        Route::delete('/{employee}', [EmployeeApiController::class, 'destroyEmployee'])->name('destroy');

        // Ad-hoc Salary Components APIs
        Route::post('/{employee}/adhoc-components', [EmployeeApiController::class, 'storeAdhocComponent'])->name('adhoc-components.store');
        Route::delete('/adhoc-components/{adhocComponent}', [EmployeeApiController::class, 'destroyAdhocComponent'])->name('adhoc-components.destroy');

        // Attendance Penalties APIs
        Route::post('/{employee}/penalties', [EmployeeApiController::class, 'storePenalty'])->name('penalties.store');
        Route::delete('/penalties/{penalty}', [EmployeeApiController::class, 'destroyPenalty'])->name('penalties.destroy');

        // Employment Histories APIs
        Route::post('/{employee}/employment-histories', [EmployeeApiController::class, 'storeEmploymentHistory'])->name('employment-histories.store');
        Route::delete('/{employee}/employment-histories/{history}', [EmployeeApiController::class, 'destroyEmploymentHistory'])->name('employment-histories.destroy');
    });

// ==========================================
// 8. LEAVE REQUESTS & APPLICATIONS API ROUTES
// ==========================================
Route::prefix('api/hrms/leave-requests')
    ->middleware(['web'])
    ->name('api.hrms.leave-requests.')
    ->group(function () {

        // Summary & Balances APIs
        Route::get('/summary', [LeaveRequestApiController::class, 'summary'])->name('summary');
        Route::get('/balances', [LeaveRequestApiController::class, 'balances'])->name('balances');

        // Leave Application & Approval Workflows
        Route::get('/', [LeaveRequestApiController::class, 'indexRequests'])->name('index');
        Route::post('/', [LeaveRequestApiController::class, 'storeRequest'])->name('store');
        Route::get('/{leaveRequest}', [LeaveRequestApiController::class, 'showRequest'])->name('show');
        Route::post('/{leaveRequest}/approve', [LeaveRequestApiController::class, 'approveRequest'])->name('approve');
        Route::post('/{leaveRequest}/reject', [LeaveRequestApiController::class, 'rejectRequest'])->name('reject');
        Route::put('/{leaveRequest}/status', [LeaveRequestApiController::class, 'updateStatus'])->name('status.update');
    });

// ==========================================
// 9. LEAVE ENCASHMENTS API ROUTES
// ==========================================
Route::prefix('api/hrms/leave-encashments')
    ->middleware(['web'])
    ->name('api.hrms.leave-encashments.')
    ->group(function () {

        // Summary Dashboard API
        Route::get('/summary', [LeaveEncashmentApiController::class, 'summary'])->name('summary');

        // Leave Encashment Application & Approval Workflows
        Route::get('/', [LeaveEncashmentApiController::class, 'indexEncashments'])->name('index');
        Route::post('/', [LeaveEncashmentApiController::class, 'storeEncashment'])->name('store');
        Route::get('/{leaveEncashment}', [LeaveEncashmentApiController::class, 'showEncashment'])->name('show');
        Route::post('/{leaveEncashment}/approve', [LeaveEncashmentApiController::class, 'approveEncashment'])->name('approve');
        Route::post('/{leaveEncashment}/reject', [LeaveEncashmentApiController::class, 'rejectEncashment'])->name('reject');
        Route::delete('/{leaveEncashment}', [LeaveEncashmentApiController::class, 'destroyEncashment'])->name('destroy');
    });
