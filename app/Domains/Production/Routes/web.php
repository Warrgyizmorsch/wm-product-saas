<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Production\Controllers\ProductionBomController;
use App\Domains\Production\Controllers\WorkCenterController;
use App\Domains\Production\Controllers\MachineController;
use App\Domains\Production\Controllers\RoutingController;
use App\Domains\Production\Controllers\ProductionPlanController;
use App\Domains\Production\Controllers\ProductionOrderController;
use App\Domains\Production\Controllers\ProductionScheduleController;
use App\Domains\Production\Controllers\MesController;
use App\Domains\Production\Controllers\MachineDashboardController;
use App\Domains\Production\Controllers\WorkCenterDashboardController;
use App\Domains\Production\Controllers\OperatorAssignmentController;
use App\Domains\Production\Controllers\BatchProductionController;
use App\Domains\Production\Controllers\SerialNumberController;
use App\Domains\Production\Controllers\LotTraceabilityController;
use App\Domains\Production\Controllers\ScannerController;
use App\Domains\Production\Controllers\MachineStateController;
use App\Domains\Production\Controllers\DowntimeController;
use App\Domains\Production\Controllers\ProductionTimelineController;

Route::prefix('production')
    ->as('production.')
    ->group(function (): void {

        Route::get('track-status', function () {
            return view('modules.production.track-status');
        })->name('track-status');

        // ── BOM (Frozen) ──────────────────────────────────────────────────────
        Route::get('boms/check-child/{productId}', [ProductionBomController::class, 'checkChildBom'])->name('boms.check-child');
        Route::post('boms/{bom}/create-revision',  [ProductionBomController::class, 'createRevision'])->name('boms.create-revision');
        Route::post('boms/{bom}/submit',    [ProductionBomController::class, 'submitApproval'])->name('boms.submit');
        Route::post('boms/{bom}/approve',   [ProductionBomController::class, 'approve'])->name('boms.approve');
        Route::post('boms/{bom}/reject',    [ProductionBomController::class, 'reject'])->name('boms.reject');
        Route::post('boms/{bom}/cancel',    [ProductionBomController::class, 'cancel'])->name('boms.cancel');
        Route::post('boms/{bom}/duplicate', [ProductionBomController::class, 'duplicateVersion'])->name('boms.duplicate');
        Route::resource('boms', ProductionBomController::class);

        // ── Work Centers ──────────────────────────────────────────────────────
        Route::resource('work-centers', WorkCenterController::class);

        // ── Machines (AJAX endpoint must be before resource to avoid routing conflict) ──
        Route::get('machines/by-work-center/{workCenter}', [MachineController::class, 'byWorkCenter'])
            ->name('machines.by-work-center');
        Route::resource('machines', MachineController::class)->except(['show']);

        // ── Routing ───────────────────────────────────────────────────────────
        Route::post('routing/{routing}/submit',    [RoutingController::class, 'submitApproval'])->name('routing.submit');
        Route::post('routing/{routing}/approve',   [RoutingController::class, 'approve'])->name('routing.approve');
        Route::post('routing/{routing}/reject',    [RoutingController::class, 'reject'])->name('routing.reject');
        Route::post('routing/{routing}/cancel',    [RoutingController::class, 'cancel'])->name('routing.cancel');
        Route::post('routing/{routing}/duplicate', [RoutingController::class, 'duplicateVersion'])->name('routing.duplicate');
        Route::get('routing/{routing}/operations', [RoutingController::class, 'getOperationsForAjax'])->name('routing.operations');
        Route::resource('routing', RoutingController::class);

        // ── Production Planning ───────────────────────────────────────────────
        Route::post('plans/{plan}/submit',    [ProductionPlanController::class, 'submitApproval'])->name('plans.submit');
        Route::post('plans/{plan}/approve',   [ProductionPlanController::class, 'approve'])->name('plans.approve');
        Route::post('plans/{plan}/reject',    [ProductionPlanController::class, 'reject'])->name('plans.reject');
        Route::post('plans/{plan}/release',   [ProductionPlanController::class, 'release'])->name('plans.release');
        Route::post('plans/{plan}/complete',  [ProductionPlanController::class, 'complete'])->name('plans.complete');
        Route::post('plans/{plan}/close',     [ProductionPlanController::class, 'close'])->name('plans.close');
        Route::post('plans/{plan}/cancel',    [ProductionPlanController::class, 'cancel'])->name('plans.cancel');
        Route::post('plans/{plan}/run-mrp',   [ProductionPlanController::class, 'runMrp'])->name('plans.run-mrp');
        Route::get('plans/ajax-engineering-options', [ProductionPlanController::class, 'getEngineeringOptions'])->name('plans.engineering-options');
        Route::resource('plans', ProductionPlanController::class);

        // ── Production Orders ─────────────────────────────────────────────────
        Route::post('plans/{plan}/create-order', [ProductionOrderController::class, 'createFromPlan'])->name('plans.create-order');
        Route::post('orders/{order}/release',  [ProductionOrderController::class, 'release'])->name('orders.release');
        Route::post('orders/{order}/issue',    [ProductionOrderController::class, 'issueMaterial'])->name('orders.issue');
        Route::post('orders/{order}/return',   [ProductionOrderController::class, 'returnMaterial'])->name('orders.return');
        Route::post('orders/{order}/log-progress', [ProductionOrderController::class, 'logProgress'])->name('orders.log-progress');
        Route::post('orders/{order}/log-scrap', [ProductionOrderController::class, 'logScrap'])->name('orders.log-scrap');
        Route::post('orders/{order}/log-rework', [ProductionOrderController::class, 'logRework'])->name('orders.log-rework');
        Route::post('orders/{order}/receive-fg', [ProductionOrderController::class, 'receiveFg'])->name('orders.receive-fg');
        Route::post('orders/{order}/complete', [ProductionOrderController::class, 'complete'])->name('orders.complete');
        Route::post('orders/{order}/close',    [ProductionOrderController::class, 'close'])->name('orders.close');
        Route::post('orders/{order}/cancel',   [ProductionOrderController::class, 'cancel'])->name('orders.cancel');
        Route::resource('orders', ProductionOrderController::class);

        // ── Production Scheduling ─────────────────────────────────────────────
        // Static named routes must be registered before the resource to avoid conflicts
        Route::get('schedules/calendar',          [ProductionScheduleController::class, 'calendarView'])->name('schedules.calendar');
        Route::get('schedules/work-center-view',  [ProductionScheduleController::class, 'workCenterView'])->name('schedules.work-center-view');
        Route::post('schedules/{schedule}/release',  [ProductionScheduleController::class, 'release'])->name('schedules.release');
        Route::post('schedules/{schedule}/cancel',   [ProductionScheduleController::class, 'cancel'])->name('schedules.cancel');
        Route::resource('schedules', ProductionScheduleController::class)->except(['edit', 'update']);

        // ── MES / Shop Floor ──────────────────────────────────────────────────
        Route::get('mes',                    [MesController::class, 'dashboard'])->name('mes.dashboard');
        Route::post('mes/{op}/start',        [MesController::class, 'start'])->name('mes.start');
        Route::post('mes/{op}/pause',        [MesController::class, 'pause'])->name('mes.pause');
        Route::post('mes/{op}/resume',       [MesController::class, 'resume'])->name('mes.resume');
        Route::post('mes/{op}/complete',     [MesController::class, 'complete'])->name('mes.complete');
        Route::post('mes/{op}/hold',         [MesController::class, 'hold'])->name('mes.hold');
        Route::post('mes/{op}/cancel',       [MesController::class, 'cancel'])->name('mes.cancel');

        // ── Advanced MES Refinements ───────────────────────────────────────────
        // Touch Operator Dashboard and My Operations
        Route::get('mes/operator', [MesController::class, 'operatorDashboard'])->name('mes.operator.dashboard');
        Route::get('mes/operator/my-operations', [MesController::class, 'myOperations'])->name('mes.operator.my-operations');
        Route::get('mes/operator/operations/{op}', [MesController::class, 'operationExecution'])->name('mes.operator.execution');

        // Operator Assignments
        Route::post('mes/assignments', [OperatorAssignmentController::class, 'assign'])->name('mes.assignments.assign');
        Route::post('mes/assignments/{assignment}/reassign', [OperatorAssignmentController::class, 'reassign'])->name('mes.assignments.reassign');
        Route::post('mes/assignments/{assignment}/accept', [OperatorAssignmentController::class, 'accept'])->name('mes.assignments.accept');
        Route::post('mes/assignments/{assignment}/reject', [OperatorAssignmentController::class, 'reject'])->name('mes.assignments.reject');

        // Batch Management
        Route::post('mes/batches', [BatchProductionController::class, 'create'])->name('mes.batches.create');
        Route::post('mes/batches/split', [BatchProductionController::class, 'split'])->name('mes.batches.split');
        Route::post('mes/batches/merge', [BatchProductionController::class, 'merge'])->name('mes.batches.merge');

        // Serial Numbers
        Route::post('mes/serials/generate', [SerialNumberController::class, 'generate'])->name('mes.serials.generate');
        Route::post('mes/serials/manual-assign', [SerialNumberController::class, 'manualAssign'])->name('mes.serials.manual-assign');

        // Lot Traceability
        Route::get('mes/traceability', [LotTraceabilityController::class, 'index'])->name('mes.traceability.index');
        Route::get('mes/traceability/search', [LotTraceabilityController::class, 'search'])->name('mes.traceability.search');

        // Barcode / QR Scanning
        Route::get('mes/scanner', [ScannerController::class, 'index'])->name('mes.scanner.index');
        Route::post('mes/scanner/scan', [ScannerController::class, 'scan'])->name('mes.scanner.scan');

        // ── MES Machine & Work Center Dashboards ──────────────────────────────
        Route::get('mes/machines',           [MachineDashboardController::class, 'index'])->name('mes.machines.index');
        Route::get('mes/machines/{id}',      [MachineDashboardController::class, 'show'])->name('mes.machines.show');
        Route::get('mes/work-centers',       [WorkCenterDashboardController::class, 'index'])->name('mes.work-centers.index');
        Route::get('mes/work-centers/{id}',  [WorkCenterDashboardController::class, 'show'])->name('mes.work-centers.show');

        // ── OEE Foundation routes ─────────────────────────────────────────────
        Route::post('mes/machines/override-state', [MachineStateController::class, 'overrideState'])->name('mes.machines.override-state');
        Route::post('mes/downtime/start',          [DowntimeController::class, 'start'])->name('mes.downtime.start');
        Route::post('mes/downtime/{id}/end',       [DowntimeController::class, 'end'])->name('mes.downtime.end');
        Route::get('mes/timeline',                 [ProductionTimelineController::class, 'index'])->name('mes.timeline.index');
    });
