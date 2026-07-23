<?php

use App\Domains\Production\Controllers\AlertController;
use App\Domains\Production\Controllers\AnalyticsController;
use App\Domains\Production\Controllers\AndonController;
use App\Domains\Production\Controllers\BatchProductionController;
use App\Domains\Production\Controllers\CalendarController;
use App\Domains\Production\Controllers\CapacityController;
use App\Domains\Production\Controllers\CapaController;
use App\Domains\Production\Controllers\DeviationController;
use App\Domains\Production\Controllers\DowntimeController;
use App\Domains\Production\Controllers\KpiTargetController;
use App\Domains\Production\Controllers\LabelController;
use App\Domains\Production\Controllers\LotTraceabilityController;
use App\Domains\Production\Controllers\MachineController;
use App\Domains\Production\Controllers\MachineDashboardController;
use App\Domains\Production\Controllers\MachineStateController;
use App\Domains\Production\Controllers\ManufacturingDashboardController;
use App\Domains\Production\Controllers\MesController;
use App\Domains\Production\Controllers\NcrController;
use App\Domains\Production\Controllers\OperatorAssignmentController;
use App\Domains\Production\Controllers\OperatorSkillController;
use App\Domains\Production\Controllers\ProductionBomController;
use App\Domains\Production\Controllers\MaterialRequestController;
use App\Domains\Production\Controllers\ProductionOrderController;
use App\Domains\Production\Controllers\ProductionPlanController;
use App\Domains\Production\Controllers\ProductionScheduleController;
use App\Domains\Production\Controllers\ProductionTimelineController;
use App\Domains\Production\Controllers\QualityDashboardController;
use App\Domains\Production\Controllers\QualityInspectionController;
use App\Domains\Production\Controllers\QualityPlanController;
use App\Domains\Production\Controllers\ReportsController;
use App\Domains\Production\Controllers\ReworkController;
use App\Domains\Production\Controllers\RoutingController;
use App\Domains\Production\Controllers\ScanLogController;
use App\Domains\Production\Controllers\ScannerController;
use App\Domains\Production\Controllers\ScrapController;
use App\Domains\Production\Controllers\SerialNumberController;
use App\Domains\Production\Controllers\ShiftController;
use App\Domains\Production\Controllers\WipController;
use App\Domains\Production\Controllers\WorkCenterController;
use App\Domains\Production\Controllers\WorkCenterDashboardController;
use App\Domains\Production\Controllers\ProductionImportExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('production')
    ->as('production.')
    ->group(function (): void {

        // ── Import/Export (Centralized Master Data) ───────────────────────────
        Route::get('import-export/download-template/{type}', [ProductionImportExportController::class, 'downloadTemplate'])
            ->name('import-export.download-template');
        Route::post('import-export/import-preview/{type}', [ProductionImportExportController::class, 'importPreview'])
            ->name('import-export.import-preview');
        Route::post('import-export/import-confirm/{type}', [ProductionImportExportController::class, 'importConfirm'])
            ->name('import-export.import-confirm');
        Route::get('import-export/export/{type}', [ProductionImportExportController::class, 'export'])
            ->name('import-export.export');

        Route::get('track-status', function () {
            abort_unless(app()->environment(['local', 'testing']) || auth()->user()?->role === 'super_admin', 403);

            return view('modules.production.track-status');
        })->name('track-status');

        // ── BOM (Frozen) ──────────────────────────────────────────────────────
        Route::get('boms/check-child/{productId}', [ProductionBomController::class, 'checkChildBom'])->name('boms.check-child');
        Route::post('boms/{bom}/create-revision', [ProductionBomController::class, 'createRevision'])->name('boms.create-revision');
        Route::post('boms/{bom}/submit', [ProductionBomController::class, 'submitApproval'])->name('boms.submit');
        Route::post('boms/{bom}/approve', [ProductionBomController::class, 'approve'])->name('boms.approve');
        Route::post('boms/{bom}/reject', [ProductionBomController::class, 'reject'])->name('boms.reject');
        Route::post('boms/{bom}/cancel', [ProductionBomController::class, 'cancel'])->name('boms.cancel');
        Route::post('boms/{bom}/duplicate', [ProductionBomController::class, 'duplicateVersion'])->name('boms.duplicate');
        Route::post('boms/bulk-action', [ProductionBomController::class, 'bulkAction'])->name('boms.bulk-action');
        Route::resource('boms', ProductionBomController::class);

        // ── Work Centers ──────────────────────────────────────────────────────
        Route::post('work-centers/bulk-action', [WorkCenterController::class, 'bulkAction'])->name('work-centers.bulk-action');
        Route::resource('work-centers', WorkCenterController::class);

        // ── Machines (AJAX endpoint must be before resource to avoid routing conflict) ──
        Route::get('machines/by-work-center/{workCenter}', [MachineController::class, 'byWorkCenter'])
            ->name('machines.by-work-center');
        Route::post('machines/bulk-action', [MachineController::class, 'bulkAction'])->name('machines.bulk-action');
        Route::resource('machines', MachineController::class)->except(['show']);

        // ── Routing ───────────────────────────────────────────────────────────
        Route::post('routing/{routing}/submit', [RoutingController::class, 'submitApproval'])->name('routing.submit');
        Route::post('routing/{routing}/approve', [RoutingController::class, 'approve'])->name('routing.approve');
        Route::post('routing/{routing}/reject', [RoutingController::class, 'reject'])->name('routing.reject');
        Route::post('routing/{routing}/cancel', [RoutingController::class, 'cancel'])->name('routing.cancel');
        Route::post('routing/{routing}/duplicate', [RoutingController::class, 'duplicateVersion'])->name('routing.duplicate');
        Route::get('routing/{routing}/operations', [RoutingController::class, 'getOperationsForAjax'])->name('routing.operations');
        Route::post('routing/bulk-action', [RoutingController::class, 'bulkAction'])->name('routing.bulk-action');
        Route::resource('routing', RoutingController::class);

        // ── Production Planning ───────────────────────────────────────────────
        Route::post('plans/{plan}/submit', [ProductionPlanController::class, 'submitApproval'])->name('plans.submit');
        Route::post('plans/{plan}/approve', [ProductionPlanController::class, 'approve'])->name('plans.approve');
        Route::post('plans/{plan}/reject', [ProductionPlanController::class, 'reject'])->name('plans.reject');
        Route::post('plans/{plan}/release', [ProductionPlanController::class, 'release'])->name('plans.release');
        Route::post('plans/{plan}/complete', [ProductionPlanController::class, 'complete'])->name('plans.complete');
        Route::post('plans/{plan}/close', [ProductionPlanController::class, 'close'])->name('plans.close');
        Route::post('plans/{plan}/cancel', [ProductionPlanController::class, 'cancel'])->name('plans.cancel');
        Route::post('plans/{plan}/run-mrp', [ProductionPlanController::class, 'runMrp'])->name('plans.run-mrp');
        Route::get('plans/ajax-engineering-options', [ProductionPlanController::class, 'getEngineeringOptions'])->name('plans.engineering-options');
        Route::get('plans/ajax-bom-explosion', [ProductionPlanController::class, 'getBomExplosion'])->name('plans.bom-explosion');
        Route::resource('plans', ProductionPlanController::class);

        // ── Production Orders ─────────────────────────────────────────────────
        Route::post('plans/{plan}/create-order', [ProductionOrderController::class, 'createFromPlan'])->name('plans.create-order');
        Route::post('orders/bulk-action', [ProductionOrderController::class, 'bulkAction'])->name('orders.bulk-action');
        Route::post('orders/{order}/release', [ProductionOrderController::class, 'release'])->name('orders.release');
        Route::post('orders/{order}/issue', [ProductionOrderController::class, 'issueMaterial'])->name('orders.issue');
        Route::post('orders/{order}/return', [ProductionOrderController::class, 'returnMaterial'])->name('orders.return');
        Route::post('orders/{order}/log-progress', [ProductionOrderController::class, 'logProgress'])->name('orders.log-progress');
        Route::post('orders/{order}/log-scrap', [ProductionOrderController::class, 'logScrap'])->name('orders.log-scrap');
        Route::post('orders/{order}/log-rework', [ProductionOrderController::class, 'logRework'])->name('orders.log-rework');
        Route::post('orders/{order}/receive-fg', [ProductionOrderController::class, 'receiveFg'])->name('orders.receive-fg');
        Route::post('orders/{order}/complete', [ProductionOrderController::class, 'complete'])->name('orders.complete');
        Route::post('orders/{order}/close', [ProductionOrderController::class, 'close'])->name('orders.close');
        Route::post('orders/{order}/cancel', [ProductionOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/request-additional-material', [ProductionOrderController::class, 'requestAdditionalMaterial'])->name('orders.request-additional-material');
        Route::post('orders/{order}/cost-adjustments', [\App\Domains\Production\Controllers\ProductionCostAdjustmentController::class, 'store'])->name('orders.cost-adjustments.store');
        Route::put('cost-adjustments/{adjustment}', [\App\Domains\Production\Controllers\ProductionCostAdjustmentController::class, 'update'])->name('cost-adjustments.update');
        Route::delete('cost-adjustments/{adjustment}', [\App\Domains\Production\Controllers\ProductionCostAdjustmentController::class, 'destroy'])->name('cost-adjustments.destroy');
        Route::get('cost-adjustments/{adjustment}/download', [\App\Domains\Production\Controllers\ProductionCostAdjustmentController::class, 'downloadAttachment'])->name('cost-adjustments.download');
        Route::resource('orders', ProductionOrderController::class);

        // ── Requisition Slips (Material Requests) ─────────────────────────────
        Route::get('material-requests', [MaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::get('material-requests/{id}', [MaterialRequestController::class, 'show'])->name('material-requests.show');
        Route::post('material-requests/items/{id}/reserve', [MaterialRequestController::class, 'reserve'])->name('material-requests.reserve');
        Route::post('material-requests/items/{id}/issue', [MaterialRequestController::class, 'issue'])->name('material-requests.issue');
        Route::post('material-requests/items/{id}/create-pr', [MaterialRequestController::class, 'createPurchaseRequisition'])->name('material-requests.create-pr');
        // ── Work-in-Progress (WIP) Management ───────────────────────────────
        Route::post('wip/{wip}/transfer', [WipController::class, 'transfer'])->name('wip.transfer');
        Route::post('wip/{wip}/adjust', [WipController::class, 'adjust'])->name('wip.adjust');
        Route::post('wip/{wip}/convert', [WipController::class, 'convertToFg'])->name('wip.convert');
        Route::get('wip', [WipController::class, 'index'])->name('wip.index');
        Route::get('wip/{wip}', [WipController::class, 'show'])->name('wip.show');

        // ── Production Scheduling ─────────────────────────────────────────────
        // Static named routes must be registered before the resource to avoid conflicts
        Route::get('schedules/calendar', [ProductionScheduleController::class, 'calendarView'])->name('schedules.calendar');
        Route::get('schedules/work-center-view', [ProductionScheduleController::class, 'workCenterView'])->name('schedules.work-center-view');
        Route::post('schedules/{schedule}/release', [ProductionScheduleController::class, 'release'])->name('schedules.release');
        Route::post('schedules/{schedule}/cancel', [ProductionScheduleController::class, 'cancel'])->name('schedules.cancel');
        Route::post('schedules/{schedule}/reschedule-start', [ProductionScheduleController::class, 'rescheduleStart'])->name('schedules.reschedule-start');
        Route::resource('schedules', ProductionScheduleController::class)->except(['edit', 'update']);

        // ── Capacity Planning ──────────────────────────────────────────────────
        Route::get('capacity', [CapacityController::class, 'index'])->name('capacity.index');
        Route::post('capacity/{id}/reschedule', [CapacityController::class, 'reschedule'])->name('capacity.reschedule');
        Route::get('capacity/{id}/suggest', [CapacityController::class, 'suggest'])->name('capacity.suggest');

        // ── Shifts & Calendars ────────────────────────────────────────────────
        Route::resource('shifts', ShiftController::class)->except(['show']);
        Route::resource('calendars', CalendarController::class)->except(['show']);
        Route::post('calendars/{calendar}/holidays', [CalendarController::class, 'storeHoliday'])->name('calendars.holidays.store');
        Route::put('calendars/{calendar}/holidays/{holiday}', [CalendarController::class, 'updateHoliday'])->name('calendars.holidays.update');
        Route::delete('calendars/{calendar}/holidays/{holiday}', [CalendarController::class, 'destroyHoliday'])->name('calendars.holidays.destroy');

        // ── Quality Plans & Operator Skills ───────────────────────────────────
        Route::resource('quality-plans', QualityPlanController::class)->except(['show']);
        Route::resource('operator-skills', OperatorSkillController::class)->except(['show']);

        // ── MES / Shop Floor ──────────────────────────────────────────────────
        Route::get('mes', [MesController::class, 'dashboard'])->name('mes.dashboard');
        Route::post('mes/{op}/start', [MesController::class, 'start'])->name('mes.start');
        Route::post('mes/{op}/pause', [MesController::class, 'pause'])->name('mes.pause');
        Route::post('mes/{op}/resume', [MesController::class, 'resume'])->name('mes.resume');
        Route::post('mes/{op}/complete', [MesController::class, 'complete'])->name('mes.complete');
        Route::post('mes/{op}/log-progress', [MesController::class, 'logProgress'])->name('mes.log-progress');
        Route::post('mes/{op}/hold', [MesController::class, 'hold'])->name('mes.hold');
        Route::post('mes/{op}/cancel', [MesController::class, 'cancel'])->name('mes.cancel');

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
        Route::get('mes/traceability/export-csv', [LotTraceabilityController::class, 'exportCsv'])->name('mes.traceability.export-csv');

        // Barcode / QR Scanning
        Route::get('mes/scanner', [ScannerController::class, 'index'])->name('mes.scanner.index');
        Route::post('mes/scanner/scan', [ScannerController::class, 'scan'])->name('mes.scanner.scan');
        Route::get('mes/scan-logs/export', [ScanLogController::class, 'export'])->name('scan-logs.export');
        Route::get('mes/scan-logs/{id}', [ScanLogController::class, 'show'])->name('scan-logs.show');
        Route::get('mes/scan-logs', [ScanLogController::class, 'index'])->name('scan-logs.index');

        // ── MES Machine & Work Center Dashboards ──────────────────────────────
        Route::get('mes/machines', [MachineDashboardController::class, 'index'])->name('mes.machines.index');
        Route::get('mes/machines/{id}', [MachineDashboardController::class, 'show'])->name('mes.machines.show');
        Route::get('mes/work-centers', [WorkCenterDashboardController::class, 'index'])->name('mes.work-centers.index');
        Route::get('mes/work-centers/{id}', [WorkCenterDashboardController::class, 'show'])->name('mes.work-centers.show');

        // ── OEE Foundation routes ─────────────────────────────────────────────
        Route::post('mes/machines/override-state', [MachineStateController::class, 'overrideState'])->name('mes.machines.override-state');
        Route::post('mes/downtime/start', [DowntimeController::class, 'start'])->name('mes.downtime.start');
        Route::post('mes/downtime/{id}/end', [DowntimeController::class, 'end'])->name('mes.downtime.end');
        Route::get('mes/timeline', [ProductionTimelineController::class, 'index'])->name('mes.timeline.index');

        // ── Phase 3 Manufacturing Intelligence ────────────────────────────────
        Route::get('intelligence/dashboard', [ManufacturingDashboardController::class, 'executiveDashboard'])->name('intelligence.dashboard');
        Route::get('intelligence/work-centers', [ManufacturingDashboardController::class, 'workCenterDashboard'])->name('intelligence.work-centers');
        Route::post('intelligence/dashboard/preferences', [ManufacturingDashboardController::class, 'savePreferences'])->name('intelligence.dashboard.preferences');
        Route::get('intelligence/andon', [AndonController::class, 'index'])->name('intelligence.andon');
        Route::get('intelligence/analytics', [AnalyticsController::class, 'historical'])->name('intelligence.analytics');
        Route::get('intelligence/reports', [ReportsController::class, 'index'])->name('intelligence.reports.index');
        Route::get('intelligence/reports/{type}', [ReportsController::class, 'show'])->name('intelligence.reports.show');
        Route::get('intelligence/reports/{type}/export', [ReportsController::class, 'export'])->name('intelligence.reports.export');
        Route::get('intelligence/alerts', [AlertController::class, 'index'])->name('intelligence.alerts.index');
        Route::post('intelligence/alerts/{id}', [AlertController::class, 'update'])->name('intelligence.alerts.update');
        Route::resource('kpi-targets', KpiTargetController::class)->only(['index', 'store']);

        // ── Phase 4 Quality Management ──────────────────────────────────────
        Route::get('quality/dashboard', [QualityDashboardController::class, 'index'])->name('quality.dashboard');

        Route::post('quality/inspections/{id}/results', [QualityInspectionController::class, 'saveResults'])->name('quality.inspections.results');
        Route::post('quality/inspections/{id}/approve', [QualityInspectionController::class, 'approve'])->name('quality.inspections.approve');
        Route::resource('quality/inspections', QualityInspectionController::class)->only(['index', 'create', 'store', 'show']);

        Route::post('quality/ncrs/{id}/disposition', [NcrController::class, 'disposition'])->name('quality.ncrs.disposition');
        Route::post('quality/ncrs/{id}/close', [NcrController::class, 'close'])->name('quality.ncrs.close');
        Route::resource('quality/ncrs', NcrController::class)->only(['index', 'create', 'store', 'show']);

        Route::post('quality/capas/{id}/rca', [CapaController::class, 'saveRca'])->name('quality.capas.rca');
        Route::post('quality/capas/{id}/close', [CapaController::class, 'close'])->name('quality.capas.close');
        Route::resource('quality/capas', CapaController::class)->only(['index', 'create', 'store', 'show']);

        Route::post('quality/rework/ops/{id}/start', [ReworkController::class, 'startOp'])->name('quality.rework.ops.start');
        Route::post('quality/rework/ops/{id}/complete', [ReworkController::class, 'completeOp'])->name('quality.rework.ops.complete');
        Route::resource('quality/rework', ReworkController::class)->only(['index', 'show']);

        Route::post('quality/scrap/{id}/approve', [ScrapController::class, 'approve'])->name('quality.scrap.approve');
        Route::resource('quality/scrap', ScrapController::class)->only(['index', 'store']);

        Route::post('quality/deviations/{id}/approve', [DeviationController::class, 'approve'])->name('quality.deviations.approve');
        Route::resource('quality/deviations', DeviationController::class)->only(['index', 'store']);

        // ── Label Printing (Printable HTML — no PDF package required) ───────────────
        Route::get('labels/orders/{id}', [LabelController::class, 'printOrder'])->name('labels.orders.print');
        Route::get('labels/batches/{id}', [LabelController::class, 'printBatch'])->name('labels.batches.print');
        Route::get('labels/serials/{id}', [LabelController::class, 'printSerial'])->name('labels.serials.print');
        Route::get('labels/products/{id}/sku', [LabelController::class, 'printProductSku'])->name('labels.products.sku');
    });
