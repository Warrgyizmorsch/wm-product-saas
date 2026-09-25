<?php

namespace App\Domains\Production\Exports;

/**
 * Wraps the existing 6 "flat" report types (machine, work-center, etc.)
 * as a single-sheet Excel file. Reuses ReportSheetExport for styling.
 */
class ReportSingleSheetExport extends ReportSheetExport
{
    public function __construct(string $type, array $reportData)
    {
        [$headers, $rows, $title] = match ($type) {
            'machine' => [
                ['Machine Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)', 'Total Produced', 'Good Qty', 'Downtime (min)'],
                collect($reportData['data'])->map(fn($r) => [$r['name'], $r['code'], $r['oee'], $r['availability'], $r['performance'], $r['quality'], $r['total_produced'], $r['good_quantity'], $r['downtime_minutes']])->toArray(),
                'Machine Performance',
            ],
            'work-center' => [
                ['Work Center Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)'],
                collect($reportData['data'])->map(fn($r) => [$r['name'], $r['code'], $r['oee'], $r['availability'], $r['performance'], $r['quality']])->toArray(),
                'Work Center Performance',
            ],
            'downtime' => [
                ['Machine', 'Category', 'Reason', 'Started At', 'Resolved At', 'Duration (min)'],
                collect($reportData['downtimes'])->map(fn($r) => [
                    $r->machine->name ?? '—', $r->category, $r->reason ?? '—',
                    $r->start_time, $r->end_time ?? 'Unresolved', $r->duration_minutes ?? 0,
                ])->toArray(),
                'Downtime Events',
            ],
            'production-orders' => [
                ['Order Number', 'Product', 'SKU', 'UOM', 'Status', 'Planned Qty', 'Produced Qty', 'Scrapped Qty', 'Rejected Qty', 'Completion %', 'Yield %', 'Start Date', 'End Date'],
                collect($reportData['data'])->map(fn($r) => [
                    $r['order_number'], $r['product_name'], $r['product_sku'], $r['uom'], ucfirst($r['status']),
                    $r['planned_qty'], $r['produced_qty'], $r['scrapped_qty'], $r['rejected_qty'],
                    $r['completion_pct'] . '%', $r['yield_pct'] . '%', $r['start_date'], $r['end_date'],
                ])->toArray(),
                'Production Orders',
            ],
            'material-consumption' => [
                ['Order Number', 'Finished Good', 'Consuming Operation', 'Work Center', 'Material', 'SKU', 'UOM', 'Planned Qty', 'Issued Qty', 'Consumed Qty', 'Floor Stock WIP', 'Consumption %', 'Unit Cost', 'Planned Cost', 'Consumed Cost', 'Issued Cost', 'Variance Cost'],
                collect($reportData['data'])->map(fn($r) => [
                    $r['order_number'], $r['finished_good'], $r['operation_name'] ?? 'Intake', $r['work_center'] ?? '', $r['material_name'], $r['material_sku'], $r['uom'],
                    $r['planned_qty'], $r['issued_qty'], $r['consumed_qty'] ?? 0, $r['floor_balance'] ?? 0, ($r['consumption_pct'] ?? 0) . '%',
                    number_format($r['unit_cost'], 2, '.', ''), number_format($r['planned_cost'], 2, '.', ''),
                    number_format($r['consumed_cost'] ?? 0, 2, '.', ''),
                    number_format($r['issued_cost'], 2, '.', ''), number_format($r['variance_cost'], 2, '.', ''),
                ])->toArray(),
                'Material Consumption',
            ],
            'cost-variance' => [
                ['Order Number', 'Product', 'SKU', 'Status', 'Planned Cost', 'Actual Material', 'Actual Labor', 'Actual Machine', 'Actual Overhead', 'Adjustments', 'Actual Total', 'Variance Amount', 'Variance %'],
                collect($reportData['data'])->map(fn($r) => [
                    $r['order_number'], $r['product_name'], $r['product_sku'], ucfirst($r['status']),
                    number_format($r['planned_cost'], 2, '.', ''), number_format($r['actual_material_cost'], 2, '.', ''),
                    number_format($r['actual_labor_cost'], 2, '.', ''), number_format($r['actual_machine_cost'], 2, '.', ''),
                    number_format($r['actual_overhead_cost'], 2, '.', ''), number_format($r['adjustments'], 2, '.', ''),
                    number_format($r['actual_total_cost'], 2, '.', ''), number_format($r['variance_amount'], 2, '.', ''),
                    $r['variance_pct'] . '%',
                ])->toArray(),
                'Cost Variance',
            ],
            'sales-order-tracking' => [
                [
                    'SR No', 'Sales Person', 'Sale Order No', 'Sale Order Date', 'Customer Name',
                    'Product Description', 'Product SKU', 'SO Qty', 'Customer Delivery Date', 'Status', 'Approval Date',
                    'MO No', 'MO Date', 'Requisition No', 'Indent No', 'Indent Date',
                    'PO No', 'Supplier Name', 'PO Date', 'MO Done Qty', 'MO Done Date',
                    'MO Pending Qty', 'Delivered Qty', 'Delivered Date', 'Pending Qty'
                ],
                collect($reportData['data'])->map(fn($r) => [
                    $r['sr_no'], $r['sales_person'], $r['sales_order_no'], $r['sales_order_date'], $r['customer_name'],
                    $r['product_name'], $r['product_sku'], $r['so_qty'], $r['customer_delivery_date'], $r['status'], $r['approval_date'],
                    $r['mo_no'], $r['mo_date'], $r['requisition_no'], $r['indent_no'], $r['indent_date'],
                    $r['po_no'], $r['supplier_name'], $r['po_date'], $r['mo_done_qty'], $r['mo_done_date'],
                    $r['mo_pending_qty'], $r['delivered_qty'], $r['delivered_date'], $r['pending_qty'],
                ])->toArray(),
                'Sales Order Tracking',
            ],
            'daily-production' => [
                [
                    'Date', 'Time', 'Order Number', 'Output Type', 'Stage Role', 'Product SKU', 'Product Name', 'UOM',
                    'Operation Stage', 'Work Center', 'Machine', 'Batch',
                    'Processed Qty', 'Rejected Qty', 'Scrapped Qty', 'Yield (%)',
                    'Run Time (min)', 'Run Time (hrs)', 'Setup Time (min)', 'Operator', 'Remarks'
                ],
                collect($reportData['detailed_logs'] ?? $reportData['data'] ?? [])->map(fn($r) => [
                    $r['date'],
                    $r['time'],
                    $r['order_number'],
                    $r['output_type_label'] ?? strtoupper($r['output_type'] ?? 'FG'),
                    $r['stage_role'] ?? 'Process Stage',
                    $r['product_sku'],
                    $r['product_name'],
                    $r['uom'],
                    $r['operation_name'],
                    $r['work_center'],
                    $r['machine'],
                    $r['batch_number'],
                    $r['good_qty'],
                    $r['rejected_qty'],
                    $r['scrapped_qty'],
                    $r['yield_pct'] . '%',
                    $r['run_minutes'],
                    $r['run_hours'],
                    $r['setup_minutes'],
                    $r['operator'],
                    $r['remarks'],
                ])->toArray(),
                'Daily Production Report',
            ],
            default => [['Type', 'Value'], [], 'Report'],
        };

        parent::__construct($title, $headers, $rows);
    }
}
