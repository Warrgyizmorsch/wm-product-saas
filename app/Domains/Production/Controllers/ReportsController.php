<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\ReportingService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Exports\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $customers = \App\Domains\CRM\Models\Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);
        $statuses = ProductionOrder::STATUSES;

        return view('modules.production.intelligence.reports', compact('machines', 'workCenters', 'products', 'materials', 'orders', 'customers', 'statuses'));
    }

    public function show(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id', 'customer_id'
        ]);

        // order-detail requires a specific order_id — redirect back if missing
        if ($type === 'order-detail' && empty($filters['order_id'])) {
            return redirect()->route('production.intelligence.reports.index')
                ->with('error', 'Please select a Production Order to generate the Order Detail report.');
        }

        $reportTitles = [
            'machine'              => 'Machine Performance & OEE Report',
            'work-center'          => 'Work Center Performance & OEE Report',
            'downtime'             => 'Downtime Breakdown & Events Report',
            'production-orders'    => 'Production Order Summary & Output Report',
            'material-consumption' => 'Material Consumption & Variance Report',
            'cost-variance'        => 'Production Cost & Variance Report',
            'order-detail'         => 'Production Order Detail Report',
            'daily-production'     => 'Daily Production Report',
            'sales-order-tracking' => 'Sales Order Tracking Report (Order-to-Delivery Pipeline)',
        ];
        $displayTitle = $reportTitles[$type] ?? ucwords(str_replace('-', ' ', $type)) . ' Report';

        $reportData = $this->resolveReportData($type, $tenantId, $filters);
        $print = $request->has('print');

        return view('modules.production.intelligence.reports-detail', compact('reportData', 'type', 'print', 'displayTitle'));
    }

    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id', 'customer_id'
        ]);

        // order-detail CSV export
        if ($type === 'order-detail') {
            if (empty($filters['order_id'])) {
                abort(400, 'order_id is required for order-detail export.');
            }
            $reportData = $this->reportService->generateOrderDetailReport($tenantId, (int) $filters['order_id']);
            return $this->exportOrderDetail($reportData);
        }

        $reportData = $this->resolveReportData($type, $tenantId, $filters);

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
                    'Order Number', 'Finished Good', 'Consuming Operation', 'Work Center', 'Component SKU', 'Component Name', 'UOM',
                    'Planned Qty', 'Issued Qty', 'Consumed Qty', 'Floor Stock WIP', 'Consumption (%)',
                    'Unit Cost', 'Planned Cost', 'Consumed Cost', 'Issued Cost', 'Variance Cost'
                ]);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['order_number'],
                        $row['finished_good'],
                        $row['operation_name'] ?? 'Intake',
                        $row['work_center'] ?? '',
                        $row['material_sku'],
                        $row['material_name'],
                        $row['uom'],
                        $row['planned_qty'],
                        $row['issued_qty'],
                        $row['consumed_qty'] ?? 0,
                        $row['floor_balance'] ?? 0,
                        ($row['consumption_pct'] ?? 0) . '%',
                        number_format($row['unit_cost'], 2, '.', ''),
                        number_format($row['planned_cost'], 2, '.', ''),
                        number_format($row['consumed_cost'] ?? 0, 2, '.', ''),
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
            } elseif ($type === 'sales-order-tracking') {
                fputcsv($file, [
                    'SR No', 'Sales Person', 'Sale Order No', 'Sale Order Date', 'Customer Name',
                    'Product Description', 'Product SKU', 'SO Qty', 'Customer Delivery Date', 'Status', 'Approval Date',
                    'MO No', 'MO Date', 'Requisition No', 'Indent No', 'Indent Date',
                    'PO No', 'Supplier Name', 'PO Date', 'MO Done Qty', 'MO Done Date',
                    'MO Pending Qty', 'Delivered Qty', 'Delivered Date', 'Pending Qty'
                ]);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['sr_no'],
                        $row['sales_person'],
                        $row['sales_order_no'],
                        $row['sales_order_date'],
                        $row['customer_name'],
                        $row['product_name'],
                        $row['product_sku'],
                        $row['so_qty'],
                        $row['customer_delivery_date'],
                        $row['status'],
                        $row['approval_date'],
                        $row['mo_no'],
                        $row['mo_date'],
                        $row['requisition_no'],
                        $row['indent_no'],
                        $row['indent_date'],
                        $row['po_no'],
                        $row['supplier_name'],
                        $row['po_date'],
                        $row['mo_done_qty'],
                        $row['mo_done_date'],
                        $row['mo_pending_qty'],
                        $row['delivered_qty'],
                        $row['delivered_date'],
                        $row['pending_qty'],
                    ]);
                }
            } elseif ($type === 'daily-production') {
                fputcsv($file, [
                    'Date', 'Time', 'Order Number', 'Output Type', 'Stage Role', 'Product SKU', 'Product Name', 'UOM',
                    'Operation Stage', 'Work Center', 'Machine', 'Batch',
                    'Processed Qty', 'Rejected Qty', 'Scrapped Qty', 'Stage Yield (%)',
                    'Run Time (min)', 'Run Time (hrs)', 'Setup Time (min)', 'Operator', 'Remarks'
                ]);
                foreach ($reportData['detailed_logs'] as $row) {
                    fputcsv($file, [
                        $row['date'],
                        $row['time'],
                        $row['order_number'],
                        $row['output_type_label'] ?? strtoupper($row['output_type'] ?? 'FG'),
                        $row['stage_role'] ?? 'Process Stage',
                        $row['product_sku'],
                        $row['product_name'],
                        $row['uom'],
                        $row['operation_name'],
                        $row['work_center'],
                        $row['machine'],
                        $row['batch_number'],
                        $row['good_qty'],
                        $row['rejected_qty'],
                        $row['scrapped_qty'],
                        $row['yield_pct'] . '%',
                        $row['run_minutes'],
                        $row['run_hours'],
                        $row['setup_minutes'],
                        $row['operator'],
                        $row['remarks'],
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export the Order Detail report as a multi-section CSV.
     */
    private function exportOrderDetail(array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $order    = $data['order'];
        $filename = 'order_detail_' . ($order['order_number'] ?? 'unknown') . '_' . now()->format('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data, $order) {
            $f = fopen('php://output', 'w');

            // Section 1: Order Header
            fputcsv($f, ['=== ORDER SUMMARY ===']);
            fputcsv($f, ['Order Number', 'Product', 'SKU', 'Status', 'Planned Qty', 'Produced Qty', 'Scrapped Qty', 'Rejected Qty', 'Completion %', 'Yield %', 'Start Date', 'End Date']);
            fputcsv($f, [
                $order['order_number'], $order['product_name'], $order['product_sku'],
                ucfirst(str_replace('_', ' ', $order['status'])),
                $order['planned_qty'], $order['produced_qty'], $order['scrapped_qty'], $order['rejected_qty'],
                $order['completion_pct'] . '%', $order['yield_pct'] . '%',
                $order['start_date'], $order['end_date'],
            ]);

            // Section 1B: Cost Estimation vs Actual Cost Breakdown
            if (!empty($data['cost_estimation'])) {
                $ce = $data['cost_estimation'];
                fputcsv($f, []);
                fputcsv($f, ['=== COST ESTIMATION VS ACTUAL BREAKDOWN ===']);
                fputcsv($f, ['Cost Element', 'Estimated (Planned)', 'Actual Incurred', 'Variance', 'Status']);
                fputcsv($f, ['Direct Materials', number_format($ce['estimated']['material_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['material_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['material'] ?? 0, 2, '.', ''), ($ce['variance']['material'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable']);
                fputcsv($f, ['Direct Labor', number_format($ce['estimated']['labor_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['labor_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['labor'] ?? 0, 2, '.', ''), ($ce['variance']['labor'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable']);
                fputcsv($f, ['Machine / Equipment', number_format($ce['estimated']['machine_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['machine_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['machine'] ?? 0, 2, '.', ''), ($ce['variance']['machine'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable']);
                fputcsv($f, ['Factory Overhead', number_format($ce['estimated']['overhead_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['overhead_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['overhead'] ?? 0, 2, '.', ''), ($ce['variance']['overhead'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable']);
                fputcsv($f, ['Total Production Cost', number_format($ce['estimated']['total_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['total_cost'] ?? 0, 2, '.', ''), number_format($ce['net_variance'] ?? 0, 2, '.', ''), $ce['variance_status'] ?? '']);
            }

            // Section 2: Operations
            fputcsv($f, []);
            fputcsv($f, ['=== OPERATION STAGES ===']);
            fputcsv($f, ['Seq', 'Operation', 'Work Center', 'Machine', 'Status', 'Produced', 'Rejected', 'Scrapped', 'Setup Plan (min)', 'Setup Act (min)', 'Process Plan (min)', 'Process Act (min)', 'Efficiency %', 'Started At', 'Ended At']);
            foreach ($data['operations'] as $op) {
                fputcsv($f, [
                    $op['sequence'], $op['name'], $op['work_center'], $op['machine'],
                    ucfirst($op['status']), $op['qty_produced'], $op['qty_rejected'], $op['qty_scrapped'],
                    $op['setup_planned'], $op['setup_actual'], $op['process_planned'], $op['process_actual'],
                    $op['efficiency_pct'] !== null ? $op['efficiency_pct'] . '%' : '—',
                    $op['actual_start'] ?? '—', $op['actual_end'] ?? '—',
                ]);
            }

            // Section 3: Materials
            fputcsv($f, []);
            fputcsv($f, ['=== MATERIAL CONSUMPTION & OPERATION ALLOCATION ===']);
            fputcsv($f, ['Material', 'SKU', 'Consuming Operation', 'Work Center', 'UOM', 'Planned Qty', 'Issued Qty', 'Consumed Qty', 'Floor Stock WIP', 'Consumption %', 'Unit Cost', 'Planned Cost', 'Consumed Cost', 'Variance Cost']);
            foreach ($data['materials'] as $mat) {
                fputcsv($f, [
                    $mat['material_name'],
                    $mat['material_sku'],
                    $mat['operation_name'] ?? 'Intake',
                    $mat['work_center'] ?? '',
                    $mat['uom'],
                    $mat['planned_qty'],
                    $mat['issued_qty'],
                    $mat['consumed_qty'] ?? 0,
                    $mat['floor_balance'] ?? 0,
                    ($mat['consumption_pct'] ?? 0) . '%',
                    number_format($mat['unit_cost'], 2, '.', ''),
                    number_format($mat['planned_cost'], 2, '.', ''),
                    number_format($mat['consumed_cost'] ?? 0, 2, '.', ''),
                    number_format($mat['variance_cost'], 2, '.', ''),
                ]);
            }

            // Section 4: Scrap Events
            fputcsv($f, []);
            fputcsv($f, ['=== SCRAP EVENTS ===']);
            fputcsv($f, ['Product', 'SKU', 'Operation', 'Quantity', 'Reason', 'Recorded At', 'Stock Posted']);
            foreach ($data['scrap_events'] as $s) {
                fputcsv($f, [
                    $s['product'], $s['product_sku'], $s['operation'],
                    $s['quantity'], $s['reason'], $s['recorded_at'],
                    $s['stock_posted'] ? 'Yes' : 'No',
                ]);
            }

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
    /**
     * Export as Excel (.xlsx) — multi-sheet for order-detail, single-sheet for all others.
     */
    public function exportExcel(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters  = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id',
        ]);

        $reportData = $this->resolveReportData($type, $tenantId, $filters);
        $filename   = "report_{$type}_" . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ReportExport($type, $reportData), $filename);
    }

    /**
     * Export as PDF using dompdf — uses a dedicated print-clean blade view.
     */
    public function exportPdf(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters  = $request->only([
            'date_start', 'date_end', 'machine_id', 'work_center_id',
            'product_id', 'order_id', 'order_number', 'status', 'material_id',
        ]);

        $reportData = $this->resolveReportData($type, $tenantId, $filters);

        $reportTitles = [
            'machine'              => 'Machine Performance & OEE Report',
            'work-center'         => 'Work Center Performance & OEE Report',
            'downtime'            => 'Downtime Breakdown & Events Report',
            'production-orders'   => 'Production Order Summary & Output Report',
            'material-consumption'=> 'Material Consumption & Variance Report',
            'cost-variance'       => 'Production Cost & Variance Report',
            'order-detail'        => 'Production Order Detail Report',
            'daily-production'    => 'Daily Production Report',
            'sales-order-tracking'=> 'Sales Order Tracking Report (Order-to-Delivery Pipeline)',
        ];
        $displayTitle = $reportTitles[$type] ?? ucwords(str_replace('-', ' ', $type)) . ' Report';
        $filename     = "report_{$type}_" . now()->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('modules.production.intelligence.reports-pdf', compact('reportData', 'type', 'displayTitle'))
            ->setPaper('a4', 'landscape')
            ->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        return $pdf->download($filename);
    }

    /**
     * Shared helper — resolves report data for any type/filters combo.
     * Eliminates the duplicated match() blocks across export methods.
     */
    private function resolveReportData(string $type, int $tenantId, array $filters): array
    {
        if ($type === 'order-detail') {
            abort_if(empty($filters['order_id']), 400, 'order_id is required for order-detail export.');
            return $this->reportService->generateOrderDetailReport($tenantId, (int) $filters['order_id']);
        }

        return match ($type) {
            'machine'              => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'          => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'             => $this->reportService->generateDowntimeReport($tenantId, $filters),
            'production-orders'    => $this->reportService->generateProductionOrderReport($tenantId, $filters),
            'material-consumption' => $this->reportService->generateMaterialConsumptionReport($tenantId, $filters),
            'cost-variance'        => $this->reportService->generateCostVarianceReport($tenantId, $filters),
            'daily-production'     => $this->reportService->generateDailyProductionReport($tenantId, $filters),
            'sales-order-tracking' => $this->reportService->generateSalesOrderTrackingReport($tenantId, $filters),
            default                => abort(404),
        };
    }
}
