<?php

use App\Domains\Production\Controllers\Api\MachineApiController;
use App\Domains\Production\Controllers\Api\MaintenanceWorkOrderApiController;
use App\Domains\Production\Controllers\Api\MesExecutionApiController;
use App\Domains\Production\Controllers\Api\NcrApiController;
use App\Domains\Production\Controllers\Api\ProductionBomApiController;
use App\Domains\Production\Controllers\Api\ProductionDashboardApiController;
use App\Domains\Production\Controllers\Api\ProductionOrderApiController;
use App\Domains\Production\Controllers\Api\ProductionPlanApiController;
use App\Domains\Production\Controllers\Api\ProductionScheduleApiController;
use App\Domains\Production\Controllers\Api\ProductionWipApiController;
use App\Domains\Production\Controllers\Api\QualityInspectionApiController;
use App\Domains\Production\Controllers\Api\QualityPlanApiController;
use App\Domains\Production\Controllers\Api\RoutingApiController;
use App\Domains\Production\Controllers\Api\ShiftApiController;
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
        Route::get('/export', [ProductionOrderApiController::class, 'export'])->name('api.v1.production.orders.export');
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
        Route::post('/{order}/cancel', [ProductionOrderApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.orders.cancel');
        Route::post('/{order}/close', [ProductionOrderApiController::class, 'close'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.orders.close');

        // Material Operations
        Route::post('/{order}/issue-material', [ProductionOrderApiController::class, 'issueMaterial'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.issue_material');
        Route::post('/{order}/return-material', [ProductionOrderApiController::class, 'returnMaterial'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.return_material');
        Route::post('/{order}/request-additional-material', [ProductionOrderApiController::class, 'requestAdditionalMaterial'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.request_additional_material');

        // Remnant Management
        Route::post('/{order}/remnants', [ProductionOrderApiController::class, 'registerRemnant'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.remnants');
        Route::post('/{order}/allocate-remnant', [ProductionOrderApiController::class, 'allocateRemnant'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.allocate_remnant');
        Route::post('/{order}/release-remnant-allocation/{allocation}', [ProductionOrderApiController::class, 'releaseRemnantAllocation'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.release_remnant_allocation');
        Route::post('/{order}/consume-remnant/{allocation}', [ProductionOrderApiController::class, 'consumeRemnant'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.consume_remnant');

        // Execution & Feedback
        Route::post('/{order}/progress', [ProductionOrderApiController::class, 'logProgress'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.progress');
        Route::post('/{order}/scrap', [ProductionOrderApiController::class, 'logScrap'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.scrap');
        Route::post('/{order}/rework', [ProductionOrderApiController::class, 'logRework'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.rework');
        Route::post('/{order}/receive-fg', [ProductionOrderApiController::class, 'receiveFg'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.receive_fg');
        Route::post('/{order}/complete', [ProductionOrderApiController::class, 'complete'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.orders.complete');
    });

    // --- Bills of Materials (BOM) ---
    Route::prefix('boms')->group(function () {
        Route::get('/export', [ProductionBomApiController::class, 'export'])->name('api.v1.production.boms.export');
        Route::get('/', [ProductionBomApiController::class, 'index'])->name('api.v1.production.boms.index');
        Route::post('/import', [ProductionBomApiController::class, 'import'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.import');
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
        Route::post('/{bom}/reject', [ProductionBomApiController::class, 'reject'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.reject');
        Route::post('/{bom}/cancel', [ProductionBomApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.cancel');
        Route::post('/{bom}/clone', [ProductionBomApiController::class, 'clone'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.boms.clone');
    });

    // --- Routings ---
    Route::prefix('routings')->group(function () {
        Route::get('/export', [RoutingApiController::class, 'export'])->name('api.v1.production.routings.export');
        Route::get('/', [RoutingApiController::class, 'index'])->name('api.v1.production.routings.index');
        Route::post('/import', [RoutingApiController::class, 'import'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.import');
        Route::post('/', [RoutingApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.store');
        Route::get('/{routing}', [RoutingApiController::class, 'show'])->name('api.v1.production.routings.show');
        Route::get('/{routing}/operations', [RoutingApiController::class, 'operations'])->name('api.v1.production.routings.operations');
        Route::put('/{routing}', [RoutingApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.update');
        Route::delete('/{routing}', [RoutingApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.destroy');
        Route::post('/{routing}/submit', [RoutingApiController::class, 'submitApproval'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.submit');
        Route::post('/{routing}/approve', [RoutingApiController::class, 'approve'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.approve');
        Route::post('/{routing}/reject', [RoutingApiController::class, 'reject'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.reject');
        Route::post('/{routing}/cancel', [RoutingApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.cancel');
        Route::post('/{routing}/duplicate', [RoutingApiController::class, 'duplicate'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.routings.duplicate');
    });

    // --- Production Plans ---
    Route::prefix('plans')->group(function () {
        Route::get('/export', [ProductionPlanApiController::class, 'export'])->name('api.v1.production.plans.export');
        Route::get('/', [ProductionPlanApiController::class, 'index'])->name('api.v1.production.plans.index');
        Route::post('/', [ProductionPlanApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.store');
        Route::get('/{plan}', [ProductionPlanApiController::class, 'show'])->name('api.v1.production.plans.show');
        Route::put('/{plan}', [ProductionPlanApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.update');
        Route::delete('/{plan}', [ProductionPlanApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.destroy');
        Route::post('/{plan}/submit', [ProductionPlanApiController::class, 'submitApproval'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.submit');
        Route::post('/{plan}/approve', [ProductionPlanApiController::class, 'approve'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.approve');
        Route::post('/{plan}/reject', [ProductionPlanApiController::class, 'reject'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.reject');
        Route::post('/{plan}/cancel', [ProductionPlanApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.cancel');
        Route::post('/{plan}/release', [ProductionPlanApiController::class, 'release'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.release');
        Route::post('/{plan}/complete', [ProductionPlanApiController::class, 'complete'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.complete');
        Route::post('/{plan}/close', [ProductionPlanApiController::class, 'close'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.close');
        Route::post('/{plan}/run-mrp', [ProductionPlanApiController::class, 'runMrp'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.run_mrp');
        Route::post('/{plan}/create-order', [ProductionPlanApiController::class, 'createOrder'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.plans.create_order');
    });

    // --- Work Centers ---
    Route::prefix('work-centers')->group(function () {
        Route::get('/export', [WorkCenterApiController::class, 'export'])->name('api.v1.production.work_centers.export');
        Route::get('/', [WorkCenterApiController::class, 'index'])->name('api.v1.production.work_centers.index');
        Route::post('/import', [WorkCenterApiController::class, 'import'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.import');
        Route::post('/', [WorkCenterApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.store');
        Route::get('/{workCenter}', [WorkCenterApiController::class, 'show'])->name('api.v1.production.work_centers.show');
        Route::put('/{workCenter}', [WorkCenterApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.update');
        Route::delete('/{workCenter}', [WorkCenterApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.work_centers.destroy');
    });

    // --- Machines ---
    Route::prefix('machines')->group(function () {
        Route::get('/export', [MachineApiController::class, 'export'])->name('api.v1.production.machines.export');
        Route::get('/', [MachineApiController::class, 'index'])->name('api.v1.production.machines.index');
        Route::post('/import', [MachineApiController::class, 'import'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.import');
        Route::post('/', [MachineApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.store');
        Route::get('/{machine}', [MachineApiController::class, 'show'])->name('api.v1.production.machines.show');
        Route::put('/{machine}', [MachineApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.update');
        Route::delete('/{machine}', [MachineApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.destroy');
        Route::post('/{machine}/link-asset', [MachineApiController::class, 'linkAsset'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.link_asset');
        Route::post('/{machine}/unlink-asset', [MachineApiController::class, 'unlinkAsset'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.machines.unlink_asset');
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
        Route::post('/operations/{operation}/hold', [MesExecutionApiController::class, 'hold'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.operation.hold');
        Route::post('/operations/{operation}/progress', [MesExecutionApiController::class, 'logProgress'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-mes'])
            ->name('api.v1.production.mes.operation.progress');
        Route::post('/operations/{operation}/andon-alert', [MesExecutionApiController::class, 'andonAlert'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.operation.andon_alert');
        Route::post('/operations/{operation}/scrap', [MesExecutionApiController::class, 'scrap'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-mes'])
            ->name('api.v1.production.mes.operation.scrap');

        Route::post('/downtime/start', [MesExecutionApiController::class, 'startDowntime'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.downtime.start');
        Route::post('/downtime/{downtime}/end', [MesExecutionApiController::class, 'endDowntime'])
            ->middleware('throttle:production-api-mes')
            ->name('api.v1.production.mes.downtime.end');
    });

    // --- Quality Management (Inspections, NCRs, Scrap Disposal) ---
    Route::prefix('quality')->group(function () {
        Route::get('/inspections', [QualityInspectionApiController::class, 'index'])->name('api.v1.production.quality.index');
        Route::post('/inspections', [QualityInspectionApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality.store');
        Route::get('/inspections/{inspection}', [QualityInspectionApiController::class, 'show'])->name('api.v1.production.quality.show');
        Route::post('/inspections/{inspection}/submit', [QualityInspectionApiController::class, 'submitResults'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.quality.submit');
        Route::post('/inspections/{inspection}/approve', [QualityInspectionApiController::class, 'approve'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.quality.approve');
        Route::post('/orders/{order}/quick-check', [QualityInspectionApiController::class, 'quickCheck'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.quality.quick_check');

        // NCR
        Route::get('/ncrs', [NcrApiController::class, 'index'])->name('api.v1.production.quality.ncrs.index');
        Route::post('/ncrs', [NcrApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality.ncrs.store');
        Route::get('/ncrs/{ncr}', [NcrApiController::class, 'show'])->name('api.v1.production.quality.ncrs.show');
        Route::post('/ncrs/{ncr}/disposition', [NcrApiController::class, 'disposition'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality.ncrs.disposition');
        Route::post('/ncrs/{ncr}/close', [NcrApiController::class, 'close'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality.ncrs.close');

        // Scrap Disposal Approval
        Route::post('/scrap/{scrap}/approve', [NcrApiController::class, 'approveScrap'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality.scrap.approve');
    });

    // --- Quality Plans ---
    Route::prefix('quality-plans')->group(function () {
        Route::get('/', [QualityPlanApiController::class, 'index'])->name('api.v1.production.quality_plans.index');
        Route::post('/', [QualityPlanApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality_plans.store');
        Route::get('/{qualityPlan}', [QualityPlanApiController::class, 'show'])->name('api.v1.production.quality_plans.show');
        Route::put('/{qualityPlan}', [QualityPlanApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality_plans.update');
        Route::delete('/{qualityPlan}', [QualityPlanApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.quality_plans.destroy');
    });

    // --- Work-In-Progress (WIP) ---
    Route::prefix('wip')->group(function () {
        Route::get('/export', [ProductionWipApiController::class, 'export'])->name('api.v1.production.wip.export');
        Route::get('/', [ProductionWipApiController::class, 'index'])->name('api.v1.production.wip.index');
        Route::get('/{wip}', [ProductionWipApiController::class, 'show'])->name('api.v1.production.wip.show');
        Route::post('/{wip}/transfer', [ProductionWipApiController::class, 'transfer'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.wip.transfer');
        Route::post('/{wip}/convert', [ProductionWipApiController::class, 'convert'])
            ->middleware(['production.api.idempotency', 'throttle:production-api-write'])
            ->name('api.v1.production.wip.convert');
    });

    // --- Production Schedules ---
    Route::prefix('schedules')->group(function () {
        Route::get('/export', [ProductionScheduleApiController::class, 'export'])->name('api.v1.production.schedules.export');
        Route::get('/', [ProductionScheduleApiController::class, 'index'])->name('api.v1.production.schedules.index');
        Route::post('/', [ProductionScheduleApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.schedules.store');
        Route::get('/{schedule}', [ProductionScheduleApiController::class, 'show'])->name('api.v1.production.schedules.show');
        Route::post('/{schedule}/release', [ProductionScheduleApiController::class, 'release'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.schedules.release');
        Route::post('/{schedule}/cancel', [ProductionScheduleApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.schedules.cancel');
    });

    // --- Plant Maintenance ---
    Route::prefix('maintenance')->group(function () {
        Route::get('/work-orders', [MaintenanceWorkOrderApiController::class, 'index'])->name('api.v1.production.maintenance.work_orders.index');
        Route::post('/work-orders', [MaintenanceWorkOrderApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.maintenance.work_orders.store');
        Route::post('/work-orders/breakdown', [MaintenanceWorkOrderApiController::class, 'reportBreakdown'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.maintenance.work_orders.breakdown');
        Route::get('/work-orders/{workOrder}', [MaintenanceWorkOrderApiController::class, 'show'])->name('api.v1.production.maintenance.work_orders.show');
        Route::post('/work-orders/{workOrder}/complete', [MaintenanceWorkOrderApiController::class, 'complete'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.maintenance.work_orders.complete');
        Route::post('/work-orders/{workOrder}/cancel', [MaintenanceWorkOrderApiController::class, 'cancel'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.maintenance.work_orders.cancel');
    });

    // --- Production Shifts ---
    Route::prefix('shifts')->group(function () {
        Route::get('/', [ShiftApiController::class, 'index'])->name('api.v1.production.shifts.index');
        Route::post('/', [ShiftApiController::class, 'store'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.shifts.store');
        Route::get('/{shift}', [ShiftApiController::class, 'show'])->name('api.v1.production.shifts.show');
        Route::put('/{shift}', [ShiftApiController::class, 'update'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.shifts.update');
        Route::delete('/{shift}', [ShiftApiController::class, 'destroy'])
            ->middleware('throttle:production-api-write')
            ->name('api.v1.production.shifts.destroy');
    });

});
