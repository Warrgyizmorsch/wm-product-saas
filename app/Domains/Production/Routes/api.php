<?php

use App\Domains\Production\Controllers\Api\MesExecutionApiController;
use App\Domains\Production\Controllers\Api\MachineApiController;
use App\Domains\Production\Controllers\Api\ProductionBomApiController;
use App\Domains\Production\Controllers\Api\ProductionDashboardApiController;
use App\Domains\Production\Controllers\Api\ProductionOrderApiController;
use App\Domains\Production\Controllers\Api\ProductionPlanApiController;
use App\Domains\Production\Controllers\Api\QualityInspectionApiController;
use App\Domains\Production\Controllers\Api\RoutingApiController;
use App\Domains\Production\Controllers\Api\WorkCenterApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Production Module API Routes (v1)
|--------------------------------------------------------------------------
|
| Dual-gated via:
| 1. X-API-SECRET header (ProductionApiSecretMiddleware)
| 2. Tenant Context Resolution (ResolveTenant)
| 3. Bearer Token (auth:sanctum)
| 4. Strict Tenant Isolation (ProductionTenantEnforcementMiddleware)
| 5. Named Rate Limiting (throttle:production-api)
|
*/

Route::middleware([
    'production.api.secret',
    'tenant',
    'auth:sanctum',
    'production.api.tenant',
    'throttle:production-api',
])->group(function () {

    // --- Dashboard & Analytics ---
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [ProductionDashboardApiController::class, 'dashboard'])->name('api.v1.production.dashboard');
        Route::get('/metrics', [ProductionDashboardApiController::class, 'metrics'])->name('api.v1.production.dashboard.metrics');
        Route::get('/alerts', [ProductionDashboardApiController::class, 'alerts'])->name('api.v1.production.dashboard.alerts');
    });

    // --- Production Orders ---
    Route::prefix('orders')->group(function () {
        Route::get('/', [ProductionOrderApiController::class, 'index'])->name('api.v1.production.orders.index');
        Route::post('/', [ProductionOrderApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.orders.store');
        Route::get('/{order}', [ProductionOrderApiController::class, 'show'])->name('api.v1.production.orders.show');
        Route::put('/{order}', [ProductionOrderApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.orders.update');

        // Explicit Lifecycle & Execution Actions (No arbitrary status mutations)
        Route::post('/{order}/release', [ProductionOrderApiController::class, 'release'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.orders.release');
        Route::post('/{order}/issue-material', [ProductionOrderApiController::class, 'issueMaterial'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.issue_material');
        Route::post('/{order}/progress', [ProductionOrderApiController::class, 'logProgress'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.progress');
        Route::post('/{order}/receive-fg', [ProductionOrderApiController::class, 'receiveFg'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.receive_fg');
        Route::post('/{order}/complete', [ProductionOrderApiController::class, 'complete'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.complete');
        Route::post('/{order}/scrap', [ProductionOrderApiController::class, 'logScrap'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.scrap');
    });

    // --- Bills of Materials (BOM) ---
    Route::prefix('boms')->group(function () {
        Route::get('/', [ProductionBomApiController::class, 'index'])->name('api.v1.production.boms.index');
        Route::post('/', [ProductionBomApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.store');
        Route::get('/{bom}', [ProductionBomApiController::class, 'show'])->name('api.v1.production.boms.show');
        Route::put('/{bom}', [ProductionBomApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.update');
        Route::post('/{bom}/submit', [ProductionBomApiController::class, 'submitApproval'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.submit');
        Route::post('/{bom}/approve', [ProductionBomApiController::class, 'approve'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.approve');
        Route::post('/{bom}/clone', [ProductionBomApiController::class, 'clone'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.clone');
    });

    // --- Routings ---
    Route::prefix('routings')->group(function () {
        Route::get('/', [RoutingApiController::class, 'index'])->name('api.v1.production.routings.index');
        Route::post('/', [RoutingApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.store');
        Route::get('/{routing}', [RoutingApiController::class, 'show'])->name('api.v1.production.routings.show');
        Route::put('/{routing}', [RoutingApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.update');
    });

    // --- Production Plans ---
    Route::prefix('plans')->group(function () {
        Route::get('/', [ProductionPlanApiController::class, 'index'])->name('api.v1.production.plans.index');
        Route::post('/', [ProductionPlanApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.store');
        Route::get('/{plan}', [ProductionPlanApiController::class, 'show'])->name('api.v1.production.plans.show');
        Route::put('/{plan}', [ProductionPlanApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.update');
        Route::post('/{plan}/submit', [ProductionPlanApiController::class, 'submitApproval'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.submit');
        Route::post('/{plan}/approve', [ProductionPlanApiController::class, 'approve'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.approve');
    });

    // --- Work Centers ---
    Route::prefix('work-centers')->group(function () {
        Route::get('/', [WorkCenterApiController::class, 'index'])->name('api.v1.production.work_centers.index');
        Route::post('/', [WorkCenterApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.store');
        Route::get('/{workCenter}', [WorkCenterApiController::class, 'show'])->name('api.v1.production.work_centers.show');
        Route::put('/{workCenter}', [WorkCenterApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.update');
    });

    // --- Machines ---
    Route::prefix('machines')->group(function () {
        Route::get('/', [MachineApiController::class, 'index'])->name('api.v1.production.machines.index');
        Route::post('/', [MachineApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.store');
        Route::get('/{machine}', [MachineApiController::class, 'show'])->name('api.v1.production.machines.show');
        Route::put('/{machine}', [MachineApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.update');
    });

    // --- MES Operator Execution ---
    Route::prefix('mes')->group(function () {
        Route::get('/queue', [MesExecutionApiController::class, 'operatorQueue'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.queue');
        Route::post('/operations/{operation}/start', [MesExecutionApiController::class, 'startOperation'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.operation.start');
        Route::post('/operations/{operation}/pause', [MesExecutionApiController::class, 'pauseOperation'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.operation.pause');
        Route::post('/operations/{operation}/resume', [MesExecutionApiController::class, 'resumeOperation'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.operation.resume');
        Route::post('/operations/{operation}/complete', [MesExecutionApiController::class, 'completeOperation'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-mes'])
            ->name('api.v1.production.mes.operation.complete');

        Route::post('/downtime/start', [MesExecutionApiController::class, 'startDowntime'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.downtime.start');
        Route::post('/downtime/{downtime}/end', [MesExecutionApiController::class, 'endDowntime'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.downtime.end');
    });

    // --- Quality Inspections ---
    Route::prefix('quality')->group(function () {
        Route::get('/inspections', [QualityInspectionApiController::class, 'index'])->name('api.v1.production.quality.index');
        Route::get('/inspections/{inspection}', [QualityInspectionApiController::class, 'show'])->name('api.v1.production.quality.show');
        Route::post('/inspections/{inspection}/submit', [QualityInspectionApiController::class, 'submitResults'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.quality.submit');
        Route::post('/orders/{order}/quick-check', [QualityInspectionApiController::class, 'quickCheck'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.quality.quick_check');
    });

});
