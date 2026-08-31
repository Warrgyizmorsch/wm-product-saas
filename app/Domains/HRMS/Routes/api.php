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
use App\Domains\HRMS\Controllers\Api\WfhRequestApiController;
use App\Domains\HRMS\Controllers\Api\ShiftChangeRequestApiController;
use App\Domains\HRMS\Controllers\Api\BiometricWebhookController;
use App\Domains\HRMS\Controllers\Api\OvertimeRequestApiController;
use App\Domains\HRMS\Controllers\Api\DocumentApiController;
use App\Domains\HRMS\Controllers\Api\DocumentMasterApiController;
use App\Domains\HRMS\Controllers\Api\AttendanceApiController;
use App\Domains\HRMS\Controllers\AttendanceCorrectionController;
use App\Domains\HRMS\Controllers\Api\TravelExpenseApiController;
use App\Domains\HRMS\Controllers\Api\PayrollRunApiController;

/*
|--------------------------------------------------------------------------
| HRMS REST API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. ORGANIZATION STRUCTURE API ROUTES
// ==========================================
Route::prefix('api/hrms/org')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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
        Route::put('/pay-groups/{payGroup}/rules', [SalaryStructureApiController::class, 'updatePayGroupRules'])->name('pay-groups.rules');

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
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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

        // Attendance Rules / Geofencing Configuration APIs
        Route::get('/attendance-rules/query', [PenalizationPolicyApiController::class, 'queryAttendanceRule'])->name('attendance-rules.query');
        Route::post('/attendance-rules/save', [PenalizationPolicyApiController::class, 'saveAttendanceRule'])->name('attendance-rules.save');
    });

// ==========================================
// 5. WORK ROSTER & SHIFT SCHEDULING API ROUTES
// ==========================================
Route::prefix('api/hrms/roster')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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
        Route::post('/weekly-pattern/assign', [RosterApiController::class, 'assignWeekly'])->name('weekly-pattern.assign');
        Route::post('/weekly-pattern/clear', [RosterApiController::class, 'clearWeekly'])->name('weekly-pattern.clear');
        Route::post('/clear', [RosterApiController::class, 'clear'])->name('clear');
    });

// ==========================================
// 6. ASSET MANAGEMENT API ROUTES
// ==========================================
Route::prefix('api/hrms/assets')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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

        // Asset Items APIs
        Route::get('/items', [AssetApiController::class, 'indexItems'])->name('items.index');
        Route::post('/items', [AssetApiController::class, 'storeItem'])->name('items.store');
        Route::get('/items/{assetItem}', [AssetApiController::class, 'showItem'])->name('items.show');
        Route::put('/items/{assetItem}', [AssetApiController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{assetItem}', [AssetApiController::class, 'destroyItem'])->name('items.destroy');
        Route::post('/items/{assetItem}/allocate', [AssetApiController::class, 'allocateItem'])->name('items.allocate');
        Route::post('/items/{assetItem}/return', [AssetApiController::class, 'returnItem'])->name('items.return');

        // Asset Requests APIs
        Route::get('/requests', [AssetApiController::class, 'indexRequests'])->name('requests.index');
        Route::post('/requests', [AssetApiController::class, 'storeRequest'])->name('requests.store');
        Route::post('/requests/{assetRequest}/reject', [AssetApiController::class, 'rejectRequest'])->name('requests.reject');
        Route::post('/requests/{assetRequest}/allocate-direct', [AssetApiController::class, 'allocateDirectRequest'])->name('requests.allocate-direct');
        Route::post('/requests/{assetRequest}/allocate', [AssetApiController::class, 'allocateRequest'])->name('requests.allocate');
        Route::post('/requests/bulk-allocate', [AssetApiController::class, 'bulkAllocateRequests'])->name('requests.bulk-allocate');
        Route::post('/requests/bulk-reject', [AssetApiController::class, 'bulkRejectRequests'])->name('requests.bulk-reject');

        // Employee Dashboard & direct bulk allocations routes
        Route::get('/my-assets', [AssetApiController::class, 'myAssets'])->name('my-assets');
        Route::post('/allocate-direct', [AssetApiController::class, 'allocateDirect'])->name('allocate-direct');
        Route::post('/return-direct-multi', [AssetApiController::class, 'returnDirectMulti'])->name('return-direct-multi');
    });

// ==========================================
// 7. EMPLOYEE DIRECTORY & PROFILE API ROUTES
// ==========================================
Route::prefix('api/hrms/employees')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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
        Route::put('/{employee}/status', [EmployeeApiController::class, 'updateStatus'])->name('status.update');
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

        Route::post('/{employee}/documents/upload', [EmployeeApiController::class, 'uploadDocument'])->name('documents.upload');
        Route::patch('/documents/{document}/approve', [EmployeeApiController::class, 'approveDocument'])->name('documents.approve');
        Route::patch('/documents/{document}/reject', [EmployeeApiController::class, 'rejectDocument'])->name('documents.reject');
        Route::patch('/documents/{document}/status', [EmployeeApiController::class, 'updateDocumentStatus'])->name('documents.status.update');
        Route::delete('/documents/{document}', [EmployeeApiController::class, 'destroyDocument'])->name('documents.destroy');
    });

// ==========================================
// 8. LEAVE REQUESTS & APPLICATIONS API ROUTES
// ==========================================
Route::prefix('api/hrms/leave-requests')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.leave-requests.')
    ->group(function () {

        // Summary & Balances APIs
        Route::get('/summary', [LeaveRequestApiController::class, 'summary'])->name('summary');
        Route::get('/balances', [LeaveRequestApiController::class, 'balances'])->name('balances');
        Route::get('/rules', [LeaveRequestApiController::class, 'getRules'])->name('rules');
 
        // Leave Application & Approval Workflows
        Route::get('/', [LeaveRequestApiController::class, 'indexRequests'])->name('index');
        Route::post('/', [LeaveRequestApiController::class, 'storeRequest'])->name('store');
        Route::post('/calculate-duration', [LeaveRequestApiController::class, 'calculateDuration'])->name('calculate-duration');
        Route::get('/{leaveRequest}', [LeaveRequestApiController::class, 'showRequest'])->name('show');
        Route::post('/{leaveRequest}/approve', [LeaveRequestApiController::class, 'approveRequest'])->name('approve');
        Route::post('/{leaveRequest}/reject', [LeaveRequestApiController::class, 'rejectRequest'])->name('reject');
        Route::put('/{leaveRequest}/status', [LeaveRequestApiController::class, 'updateStatus'])->name('status.update');
        Route::post('/{leaveRequest}/withdraw', [LeaveRequestApiController::class, 'withdraw'])->name('withdraw');
        Route::post('/{leaveRequest}/request-cancellation', [LeaveRequestApiController::class, 'requestCancellation'])->name('request-cancellation');
        Route::post('/{leaveRequest}/approve-cancellation', [LeaveRequestApiController::class, 'approveCancellation'])->name('approve-cancellation');
        Route::post('/{leaveRequest}/deny-cancellation', [LeaveRequestApiController::class, 'denyCancellation'])->name('deny-cancellation');
    });

// ==========================================
// 8B. WFH REQUESTS & APPLICATIONS API ROUTES
// ==========================================
Route::prefix('api/hrms/wfh-requests')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.wfh-requests.')
    ->group(function () {
        Route::get('/summary', [WfhRequestApiController::class, 'summary'])->name('summary');
        Route::get('/', [WfhRequestApiController::class, 'indexRequests'])->name('index');
        Route::post('/', [WfhRequestApiController::class, 'storeRequest'])->name('store');
        Route::get('/{wfhRequest}', [WfhRequestApiController::class, 'showRequest'])->name('show');
        Route::post('/{wfhRequest}/approve', [WfhRequestApiController::class, 'approveRequest'])->name('approve');
        Route::post('/{wfhRequest}/reject', [WfhRequestApiController::class, 'rejectRequest'])->name('reject');
        Route::put('/{wfhRequest}/status', [WfhRequestApiController::class, 'updateStatus'])->name('status.update');
        Route::post('/{wfhRequest}/withdraw', [WfhRequestApiController::class, 'withdrawRequest'])->name('withdraw');
        Route::post('/{wfhRequest}/request-cancellation', [WfhRequestApiController::class, 'requestCancellation'])->name('request-cancellation');
        Route::post('/{wfhRequest}/approve-cancellation', [WfhRequestApiController::class, 'approveCancellation'])->name('approve-cancellation');
        Route::post('/{wfhRequest}/deny-cancellation', [WfhRequestApiController::class, 'denyCancellation'])->name('deny-cancellation');
    });

// ==========================================
// 8C. SHIFT CHANGE REQUESTS API ROUTES
// ==========================================
Route::prefix('api/hrms/shift-change-requests')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.shift-change-requests.')
    ->group(function () {
        Route::get('/summary', [ShiftChangeRequestApiController::class, 'summary'])->name('summary');
        Route::get('/', [ShiftChangeRequestApiController::class, 'indexRequests'])->name('index');
        Route::post('/', [ShiftChangeRequestApiController::class, 'storeRequest'])->name('store');
        Route::get('/{shiftChangeRequest}', [ShiftChangeRequestApiController::class, 'showRequest'])->name('show');
        Route::post('/{shiftChangeRequest}/approve', [ShiftChangeRequestApiController::class, 'approveRequest'])->name('approve');
        Route::post('/{shiftChangeRequest}/reject', [ShiftChangeRequestApiController::class, 'rejectRequest'])->name('reject');
        Route::put('/{shiftChangeRequest}/status', [ShiftChangeRequestApiController::class, 'updateStatus'])->name('status.update');
        Route::delete('/{shiftChangeRequest}', [ShiftChangeRequestApiController::class, 'destroy'])->name('destroy');
    });

// ==========================================
// 8D. OVERTIME REQUESTS API ROUTES
// ==========================================
Route::prefix('api/hrms/overtime-requests')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.overtime-requests.')
    ->group(function () {
        Route::get('/summary', [OvertimeRequestApiController::class, 'summary'])->name('summary');
        Route::get('/', [OvertimeRequestApiController::class, 'indexRequests'])->name('index');
        Route::post('/', [OvertimeRequestApiController::class, 'storeRequest'])->name('store');
        Route::get('/{overtimeRequest}', [OvertimeRequestApiController::class, 'showRequest'])->name('show');
        Route::post('/{overtimeRequest}/approve', [OvertimeRequestApiController::class, 'approveRequest'])->name('approve');
        Route::post('/{overtimeRequest}/reject', [OvertimeRequestApiController::class, 'rejectRequest'])->name('reject');
        Route::put('/{overtimeRequest}/status', [OvertimeRequestApiController::class, 'updateStatus'])->name('status.update');
        Route::delete('/{overtimeRequest}', [OvertimeRequestApiController::class, 'destroy'])->name('destroy');
    });


// ==========================================
// 9. LEAVE ENCASHMENTS API ROUTES
// ==========================================
Route::prefix('api/hrms/leave-encashments')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
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

// ==========================================
// 10. HOLIDAY CALENDAR API ROUTES
// ==========================================
Route::prefix('api/hrms/holidays')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.holidays.')
    ->group(function () {
        Route::get('/', [\App\Domains\HRMS\Controllers\Api\HolidayCalendarApiController::class, 'index'])->name('index');
        Route::post('/', [\App\Domains\HRMS\Controllers\Api\HolidayCalendarApiController::class, 'store'])->name('store');
        Route::get('/{holiday}', [\App\Domains\HRMS\Controllers\Api\HolidayCalendarApiController::class, 'show'])->name('show');
        Route::put('/{holiday}', [\App\Domains\HRMS\Controllers\Api\HolidayCalendarApiController::class, 'update'])->name('update');
        Route::delete('/{holiday}', [\App\Domains\HRMS\Controllers\Api\HolidayCalendarApiController::class, 'destroy'])->name('destroy');
    });

// ==========================================
// 10B. ATTENDANCE CORRECTION REQUESTS API ROUTES
// ==========================================
Route::prefix('api/hrms/attendance-corrections')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.attendance-corrections.')
    ->group(function () {
        Route::post('/', [AttendanceCorrectionController::class, 'store'])->name('store');
        Route::post('/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('approve');
        Route::post('/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('reject');
    });

// ==========================================
// 11. ATTENDANCE & BIOMETRIC INTEGRATION API ROUTES
// ==========================================
Route::prefix('api/hrms/attendance')
    ->middleware(['auth:sanctum', 'throttle:100,1'])
    ->name('api.hrms.attendance.')
    ->group(function () {
        Route::post('/biometric-sync', [BiometricWebhookController::class, 'syncLogs'])->name('biometric-sync');
        Route::get('/summary', [AttendanceApiController::class, 'summary'])->name('summary');
        Route::get('/', [AttendanceApiController::class, 'index'])->name('index');
        Route::get('/my-attendance', [AttendanceApiController::class, 'myAttendance'])->name('my-attendance');
        Route::post('/check-in', [AttendanceApiController::class, 'checkIn'])->name('check-in');
        Route::post('/{attendance}/check-out', [AttendanceApiController::class, 'checkOut'])->name('check-out');
        Route::post('/{attendance}/break-in', [AttendanceApiController::class, 'breakIn'])->name('break-in');
        Route::post('/{attendance}/break-out', [AttendanceApiController::class, 'breakOut'])->name('break-out');
        Route::post('/manual', [AttendanceApiController::class, 'storeManual'])->name('manual.store');
        Route::delete('/date/{date}', [AttendanceApiController::class, 'destroyDate'])->name('destroy-date');
        Route::post('/track-location', [AttendanceApiController::class, 'trackLocation'])->name('track-location');
    });

// Public Webhook route for ADMS devices
Route::post('api/hrms/biometric/webhook', [BiometricWebhookController::class, 'handleAdmsRequest'])
    ->middleware(['throttle:100,1'])
    ->name('api.hrms.biometric.webhook');

// ==========================================
// 12. EMPLOYEE DOCUMENTS API ROUTES
// ==========================================
Route::prefix('api/hrms/documents')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.documents.')
    ->group(function () {
        Route::get('/', [DocumentApiController::class, 'index'])->name('index');
        Route::post('/upload', [DocumentApiController::class, 'upload'])->name('upload');
        Route::post('/{document}/approve', [DocumentApiController::class, 'approve'])->name('approve');
        Route::post('/{document}/reject', [DocumentApiController::class, 'reject'])->name('reject');
        Route::put('/{document}/status', [DocumentApiController::class, 'updateStatus'])->name('status.update');
    });

// ==========================================
// 13. DOCUMENT MASTERS & CATEGORIES API ROUTES
// ==========================================
Route::prefix('api/hrms/documents-master')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.hrms.documents-master.')
    ->group(function () {
        Route::get('/', [DocumentMasterApiController::class, 'index'])->name('index');
        Route::post('/categories', [DocumentMasterApiController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [DocumentMasterApiController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [DocumentMasterApiController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('/documents', [DocumentMasterApiController::class, 'storeDocument'])->name('documents.store');
        Route::put('/documents/{document}', [DocumentMasterApiController::class, 'updateDocument'])->name('documents.update');
        Route::delete('/documents/{document}', [DocumentMasterApiController::class, 'destroyDocument'])->name('documents.destroy');
        Route::patch('/documents/{document}/toggle', [DocumentMasterApiController::class, 'toggleStatus'])->name('documents.toggle');
    });

// ==========================================
// 14. TRAVEL & EXPENSE API ROUTES
// ==========================================
Route::prefix('api/hrms/travel-expense')
    ->middleware(['auth:sanctum', 'throttle:100,1'])
    ->name('api.hrms.travel-expense.')
    ->group(function () {
        Route::get('/', [TravelExpenseApiController::class, 'index'])->name('index');
        Route::post('/travel/store', [TravelExpenseApiController::class, 'storeTravelRequest'])->name('travel.store');
        Route::post('/travel/{travelRequest}/approve', [TravelExpenseApiController::class, 'approveTravelRequest'])->name('travel.approve');
        Route::post('/travel/{travelRequest}/reject', [TravelExpenseApiController::class, 'rejectTravelRequest'])->name('travel.reject');
        
        Route::post('/advance/store', [TravelExpenseApiController::class, 'storeCashAdvance'])->name('advance.store');
        Route::post('/advance/{cashAdvance}/approve', [TravelExpenseApiController::class, 'approveCashAdvance'])->name('advance.approve');
        Route::post('/advance/{cashAdvance}/disburse', [TravelExpenseApiController::class, 'disburseCashAdvance'])->name('advance.disburse');
        Route::post('/advance/{cashAdvance}/reject', [TravelExpenseApiController::class, 'rejectCashAdvance'])->name('advance.reject');
        
        Route::post('/report/store', [TravelExpenseApiController::class, 'storeExpenseReport'])->name('report.store');
        Route::post('/report/{expenseReport}/update', [TravelExpenseApiController::class, 'updateExpenseReport'])->name('report.update');
        Route::post('/report/{expenseReport}/submit', [TravelExpenseApiController::class, 'submitExpenseReport'])->name('report.submit');
        Route::post('/report/{expenseReport}/approve', [TravelExpenseApiController::class, 'approveExpenseReport'])->name('report.approve');
        Route::post('/report/{expenseReport}/reject', [TravelExpenseApiController::class, 'rejectExpenseReport'])->name('report.reject');
        Route::post('/report/{expenseReport}/pay', [TravelExpenseApiController::class, 'payExpenseReport'])->name('report.pay');
        
        Route::get('/employee-policy/{employee}', [TravelExpenseApiController::class, 'getEmployeePolicy'])->name('employee-policy');
    });

// ==========================================
// 15. PAYROLL API ROUTES
// ==========================================
Route::prefix('api/hrms/payroll')
    ->middleware(['auth:sanctum', 'throttle:100,1'])
    ->name('api.hrms.payroll.')
    ->group(function () {
        Route::get('/', [PayrollRunApiController::class, 'index'])->name('index');
        Route::post('/store', [PayrollRunApiController::class, 'storeRun'])->name('store');
        Route::post('/{run}/lock', [PayrollRunApiController::class, 'lockRun'])->name('lock');
        Route::post('/{run}/resolve', [PayrollRunApiController::class, 'resolvePending'])->name('resolve');
        Route::post('/{run}/release', [PayrollRunApiController::class, 'releasePayouts'])->name('release');
        Route::post('/hold/toggle', [PayrollRunApiController::class, 'toggleHold'])->name('hold.toggle');
        Route::get('/my-salary', [PayrollRunApiController::class, 'mySalary'])->name('my-salary');
        Route::post('/bulk-adhoc', [PayrollRunApiController::class, 'storeBulkAdhoc'])->name('bulk-adhoc');
    });



