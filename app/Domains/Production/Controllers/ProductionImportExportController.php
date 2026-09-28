<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Exports\WorkCenterExport;
use App\Exports\MachineExport;
use App\Exports\BomExport;
use App\Exports\RoutingExport;
use App\Exports\ProductionOrderExport;
use App\Exports\ProductionPlanExport;
use App\Exports\ProductionWipExport;
use App\Exports\ProductionScheduleExport;
use App\Exports\WorkCenterTemplate;
use App\Exports\MachineTemplate;
use App\Exports\BomTemplate;
use App\Exports\RoutingTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

class ProductionImportExportController extends Controller
{
    public function __construct(
        private readonly \App\Domains\Production\Services\ProductionMasterImportService $importService
    ) {}

    /**
     * Download the template for import.
     */
    public function downloadTemplate(string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        return match ($type) {
            'work-centers' => Excel::download(new WorkCenterTemplate, 'work_centers_template.xlsx'),
            'machines' => Excel::download(new MachineTemplate, 'machines_template.xlsx'),
            'boms' => Excel::download(new BomTemplate, 'boms_template.xlsx'),
            'routings' => Excel::download(new RoutingTemplate, 'routings_template.xlsx'),
            default => abort(404, 'Invalid template type'),
        };
    }

    /**
     * Export data.
     */
    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        return match ($type) {
            'work-centers' => Excel::download(new WorkCenterExport($tenantId, $request->all()), 'work_centers_export.xlsx'),
            'machines' => Excel::download(new MachineExport($tenantId, $request->all()), 'machines_export.xlsx'),
            'boms' => Excel::download(new BomExport($tenantId, $request->all()), 'boms_export.xlsx'),
            'routings' => Excel::download(new RoutingExport($tenantId, $request->all()), 'routings_export.xlsx'),
            'orders' => Excel::download(new ProductionOrderExport($tenantId, $request->all()), 'production_orders_export.xlsx'),
            'plans' => Excel::download(new ProductionPlanExport($tenantId, $request->all()), 'production_plans_export.xlsx'),
            'wip' => Excel::download(new ProductionWipExport($tenantId, $request->all()), 'production_wip_export.xlsx'),
            'schedules' => Excel::download(new ProductionScheduleExport($tenantId, $request->all()), 'production_schedules_export.xlsx'),
            default => abort(404, 'Invalid export type'),
        };
    }

    /**
     * Parse and preview rows.
     */
    public function importPreview(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $cancelRoute = match ($type) {
            'work-centers' => 'production.work-centers.index',
            'machines' => 'production.machines.index',
            'boms' => 'production.boms.index',
            'routings' => 'production.routing.index',
            default => 'production.work-centers.index',
        };

        if ($request->isMethod('get')) {
            $previewRows = Session::get("production_import_preview_{$type}");
            if (!empty($previewRows)) {
                $errorCount = collect($previewRows)->where('valid', false)->count();
                return view('modules.production.import_preview', compact('previewRows', 'type', 'errorCount'));
            }

            return redirect()->route($cancelRoute)->with('info', 'No active import preview found. Please upload a file to import.');
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $tenantId = require_tenant_id();

        $preview = $this->importService->parseAndValidate($type, $file, $tenantId);

        if ($preview['empty']) {
            return redirect()->back()->withErrors(['file' => 'The uploaded file is empty or could not be parsed.']);
        }

        $previewRows = $preview['preview_rows'];
        $errorCount = $preview['error_count'];

        // Save preview configuration to session
        Session::put("production_import_preview_{$type}", $previewRows);
        Session::put("production_import_type", $type);

        return view('modules.production.import_preview', compact('previewRows', 'type', 'errorCount'));
    }

    /**
     * Commit the validated import rows inside a database transaction.
     */
    public function importConfirm(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $redirectRoute = match ($type) {
            'work-centers' => 'production.work-centers.index',
            'machines' => 'production.machines.index',
            'boms' => 'production.boms.index',
            'routings' => 'production.routing.index',
            default => 'production.work-centers.index',
        };

        if ($request->isMethod('get')) {
            return redirect()->route($redirectRoute);
        }

        $strategy = $request->input('strategy', 'create'); // 'create' or 'update'
        $tenantId = require_tenant_id();

        $previewRows = Session::get("production_import_preview_{$type}");
        if (empty($previewRows)) {
            return redirect()->back()->withErrors(['file' => 'Import preview expired or session lost. Please upload again.']);
        }

        // Filter valid rows
        $validRows = collect($previewRows)->where('valid', true)->all();
        if (empty($validRows)) {
            return redirect()->back()->withErrors(['file' => 'No valid rows found to import.']);
        }

        try {
            $result = $this->importService->commit($type, $validRows, $strategy, $tenantId, auth()->id());
            $successCount = $result['success_count'];
            $failedCount = $result['failed_count'];
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'Database import failed: ' . $e->getMessage()]);
        }

        // Clean up session preview
        Session::forget("production_import_preview_{$type}");
        Session::forget("production_import_type");

        $redirectRoute = match ($type) {
            'work-centers' => 'production.work-centers.index',
            'machines' => 'production.machines.index',
            'boms' => 'production.boms.index',
            'routings' => 'production.routing.index',
            default => 'production.work-centers.index',
        };

        $msg = "Import completed! Successfully imported/updated {$successCount} records.";
        if ($failedCount > 0) {
            $msg .= " Skipped {$failedCount} duplicate record(s) based on create-only strategy.";
        }

        return redirect()->route($redirectRoute)->with('success', $msg);
    }
}
