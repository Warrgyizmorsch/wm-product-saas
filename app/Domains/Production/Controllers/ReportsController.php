<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\ReportingService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportService
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $machines = Machine::where('tenant_id', $tenantId)->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();
        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])
            ->orderBy('name')
            ->get();
        $materials = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['raw_material', 'raw_materials', 'consumable', 'consumables', 'semi_finished', 'semi_finished_goods'])
            ->orderBy('name')
            ->get();
        $orders = ProductionOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('order_number', 'desc')
            ->take(100)
            ->get(['id', 'order_number']);
        $statuses = ProductionOrder::STATUSES;

        return view('modules.production.intelligence.reports', compact('machines', 'workCenters', 'products', 'materials', 'orders', 'statuses'));
    }

    public function show(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id'
        ]);

        $reportData = match ($type) {
            'machine'              => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'          => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'             => $this->reportService->generateDowntimeReport($tenantId, $filters),
            'production-orders'    => $this->reportService->generateProductionOrderReport($tenantId, $filters),
            'material-consumption' => $this->reportService->generateMaterialConsumptionReport($tenantId, $filters),
            'cost-variance'        => $this->reportService->generateCostVarianceReport($tenantId, $filters),
            default                => abort(404),
        };

        // If print view is requested, we pass it to a simple print layout
        $print = $request->has('print');

        return view('modules.production.intelligence.reports-detail', compact('reportData', 'type', 'print'));
    }

    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id'
        ]);

        $reportData = match ($type) {
            'machine'              => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'          => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'             => $this->reportService->generateDowntimeReport($tenantId, $filters),
            'production-orders'    => $this->reportService->generateProductionOrderReport($tenantId, $filters),
            'material-consumption' => $this->reportService->generateMaterialConsumptionReport($tenantId, $filters),
            'cost-variance'        => $this->reportService->generateCostVarianceReport($tenantId, $filters),
            default                => abort(404),
        };

        $filename = "report_{$type}_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($type, $reportData) {
            $file = fopen('php://output', 'w');

            if ($type === 'machine') {
                fputcsv($file, ['Machine Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)', 'Total Produced', 'Good Quantity', 'Downtime (min)']);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['name'],
                        $row['code'],
                        $row['oee'],
                        $row['availability'],
                        $row['performance'],
                        $row['quality'],
                        $row['total_produced'],
                        $row['good_quantity'],
                        $row['downtime_minutes'],
                    ]);
                }
            } elseif ($type === 'work-center') {
                fputcsv($file, ['Work Center Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)']);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['name'],
                        $row['code'],
                        $row['oee'],
                        $row['availability'],
                        $row['performance'],
                        $row['quality'],
                    ]);
                }
            } elseif ($type === 'downtime') {
                fputcsv($file, ['Machine', 'Reason', 'Category', 'Start Time', 'End Time', 'Duration (min)', 'Status']);
                foreach ($reportData['downtimes'] as $row) {
                    fputcsv($file, [
                        $row->machine->name ?? 'Unknown',
                        $row->reason,
                        $row->category,
                        $row->start_time->toDateTimeString(),
                        $row->end_time ? $row->end_time->toDateTimeString() : 'N/A',
                        $row->duration_minutes,
                        ucfirst($row->status),
                    ]);
                }
            } elseif ($type === 'production-orders') {
                fputcsv($file, [
                    'Order Number', 'Product SKU', 'Product Name', 'UOM', 'Status',
                    'Planned Qty', 'Produced Qty', 'Scrapped Qty', 'Rejected Qty',
                    'Completion (%)', 'Yield (%)', 'Start Date', 'End Date'
                ]);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['order_number'],
                        $row['product_sku'],
                        $row['product_name'],
                        $row['uom'],
                        ucfirst($row['status']),
                        $row['planned_qty'],
                        $row['produced_qty'],
                        $row['scrapped_qty'],
                        $row['rejected_qty'],
                        $row['completion_pct'] . '%',
                        $row['yield_pct'] . '%',
                        $row['start_date'],
                        $row['end_date'],
                    ]);
                }
            } elseif ($type === 'material-consumption') {
                fputcsv($file, [
                    'Order Number', 'Finished Good', 'Component SKU', 'Component Name', 'UOM',
                    'Planned Qty', 'Issued Qty', 'Variance Qty', 'Variance (%)',
                    'Unit Cost', 'Planned Cost', 'Issued Cost', 'Variance Cost'
                ]);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['order_number'],
                        $row['finished_good'],
                        $row['material_sku'],
                        $row['material_name'],
                        $row['uom'],
                        $row['planned_qty'],
                        $row['issued_qty'],
                        $row['variance_qty'],
                        $row['variance_pct'] . '%',
                        number_format($row['unit_cost'], 2, '.', ''),
                        number_format($row['planned_cost'], 2, '.', ''),
                        number_format($row['issued_cost'], 2, '.', ''),
                        number_format($row['variance_cost'], 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'cost-variance') {
                fputcsv($file, [
                    'Order Number', 'Product SKU', 'Product Name', 'Status',
                    'Planned Cost', 'Actual Material Cost', 'Actual Labor Cost', 'Actual Machine Cost',
                    'Actual Overhead Cost', 'Adjustments', 'Actual Total Cost', 'Variance Amount', 'Variance (%)'
                ]);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['order_number'],
                        $row['product_sku'],
                        $row['product_name'],
                        ucfirst($row['status']),
                        number_format($row['planned_cost'], 2, '.', ''),
                        number_format($row['actual_material_cost'], 2, '.', ''),
                        number_format($row['actual_labor_cost'], 2, '.', ''),
                        number_format($row['actual_machine_cost'], 2, '.', ''),
                        number_format($row['actual_overhead_cost'], 2, '.', ''),
                        number_format($row['adjustments'], 2, '.', ''),
                        number_format($row['actual_total_cost'], 2, '.', ''),
                        number_format($row['variance_amount'], 2, '.', ''),
                        $row['variance_pct'] . '%',
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
