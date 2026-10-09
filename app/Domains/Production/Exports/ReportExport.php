<?php

namespace App\Domains\Production\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $type,
        private readonly array $reportData
    ) {}

    public function sheets(): array
    {
        return match ($this->type) {
            'order-detail'     => $this->orderDetailSheets(),
            'daily-production' => $this->dailyProductionSheets(),
            default            => [new ReportSingleSheetExport($this->type, $this->reportData)],
        };
    }

    private function orderDetailSheets(): array
    {
        $data  = $this->reportData;
        $order = $data['order'];

        $costEstRows = [];
        if (!empty($data['cost_estimation'])) {
            $ce = $data['cost_estimation'];
            $costEstRows = [
                ['Direct Materials', number_format($ce['estimated']['material_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['material_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['material'] ?? 0, 2, '.', ''), ($ce['variance']['material'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable'],
                ['Direct Labor', number_format($ce['estimated']['labor_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['labor_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['labor'] ?? 0, 2, '.', ''), ($ce['variance']['labor'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable'],
                ['Machine / Equipment', number_format($ce['estimated']['machine_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['machine_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['machine'] ?? 0, 2, '.', ''), ($ce['variance']['machine'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable'],
                ['Factory Overhead', number_format($ce['estimated']['overhead_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['overhead_cost'] ?? 0, 2, '.', ''), number_format($ce['variance']['overhead'] ?? 0, 2, '.', ''), ($ce['variance']['overhead'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable'],
                ['Total Production Cost', number_format($ce['estimated']['total_cost'] ?? 0, 2, '.', ''), number_format($ce['actual']['total_cost'] ?? 0, 2, '.', ''), number_format($ce['net_variance'] ?? 0, 2, '.', ''), $ce['variance_status'] ?? ''],
            ];
        }

        return [
            new ReportSheetExport(
                'Order Summary',
                ['Order Number', 'Product', 'SKU', 'Status', 'Planned Qty', 'Produced Qty', 'Scrapped Qty', 'Rejected Qty', 'Completion %', 'Yield %', 'Start Date', 'End Date', 'Actual Start', 'Actual End', 'Created By'],
                [[
                    $order['order_number'],
                    $order['product_name'],
                    $order['product_sku'],
                    ucfirst(str_replace('_', ' ', $order['status'])),
                    $order['planned_qty'],
                    $order['produced_qty'],
                    $order['scrapped_qty'],
                    $order['rejected_qty'],
                    $order['completion_pct'] . '%',
                    $order['yield_pct'] . '%',
                    $order['start_date'],
                    $order['end_date'],
                    $order['actual_start'] ?? '—',
                    $order['actual_end'] ?? '—',
                    $order['created_by'],
                ]]
            ),
            new ReportSheetExport(
                'Cost Estimation',
                ['Cost Element', 'Estimated Cost (' . active_currency() . ')', 'Actual Incurred Cost (' . active_currency() . ')', 'Variance Cost (' . active_currency() . ')', 'Status'],
                $costEstRows
            ),
            new ReportSheetExport(
                'Operations',
                ['Seq', 'Operation', 'Work Center', 'Machine', 'Status', 'External?', 'QC Gate?', 'Produced', 'Rejected', 'Scrapped', 'Setup Plan (min)', 'Setup Act (min)', 'Process Plan (min)', 'Process Act (min)', 'Efficiency %', 'Started At', 'Ended At'],
                collect($data['operations'])->map(fn($op) => [
                    $op['sequence'],
                    $op['name'],
                    $op['work_center'],
                    $op['machine'],
                    ucfirst(str_replace('_', ' ', $op['status'])),
                    $op['is_external'] ? 'Yes' : 'No',
                    $op['quality_required'] ? 'Yes' : 'No',
                    $op['qty_produced'],
                    $op['qty_rejected'],
                    $op['qty_scrapped'],
                    $op['setup_planned'],
                    $op['setup_actual'],
                    $op['process_planned'],
                    $op['process_actual'],
                    $op['efficiency_pct'] !== null ? $op['efficiency_pct'] . '%' : '—',
                    $op['actual_start'] ?? '—',
                    $op['actual_end'] ?? '—',
                ])->toArray()
            ),
            new ReportSheetExport(
                'Material Consumption',
                ['Material', 'SKU', 'Consuming Operation', 'Work Center', 'UOM', 'Planned Qty', 'Issued Qty', 'Consumed Qty', 'Floor Stock WIP', 'Consumption %', 'Unit Cost (' . active_currency() . ')', 'Planned Cost (' . active_currency() . ')', 'Consumed Cost (' . active_currency() . ')', 'Variance Cost (' . active_currency() . ')'],
                collect($data['materials'])->map(fn($m) => [
                    $m['material_name'],
                    $m['material_sku'],
                    $m['operation_name'] ?? 'Intake',
                    $m['work_center'] ?? '',
                    $m['uom'],
                    $m['planned_qty'],
                    $m['issued_qty'],
                    $m['consumed_qty'] ?? 0,
                    $m['floor_balance'] ?? 0,
                    ($m['consumption_pct'] ?? 0) . '%',
                    number_format($m['unit_cost'], 2, '.', ''),
                    number_format($m['planned_cost'], 2, '.', ''),
                    number_format($m['consumed_cost'] ?? 0, 2, '.', ''),
                    number_format($m['variance_cost'], 2, '.', ''),
                ])->toArray()
            ),
            new ReportSheetExport(
                'Scrap Events',
                ['Product', 'SKU', 'Operation', 'Quantity', 'Measurement Type', 'Dimensions', 'Pieces', 'Reason', 'Recorded At', 'Stock Posted'],
                collect($data['scrap_events'])->map(function($s) {
                    $dim = match($s['measurement_type'] ?? '') {
                        'linear' => !empty($s['length']) ? $s['length'] . ' mm' : '',
                        'sheet'  => !empty($s['length']) ? $s['length'] . 'x' . $s['width'] . ' mm' : '',
                        'weight' => !empty($s['weight']) ? $s['weight'] . ' ' . ($s['weight_unit'] ?? 'kg') : '',
                        default  => '',
                    };
                    return [
                        $s['product'],
                        $s['product_sku'],
                        $s['operation'],
                        $s['quantity'],
                        $s['measurement_type'] ?? 'direct',
                        $dim,
                        $s['pieces'] ?? 1,
                        $s['reason'],
                        $s['recorded_at'],
                        $s['stock_posted'] ? 'Yes' : 'No',
                    ];
                })->toArray()
            ),
            new ReportSheetExport(
                'Reusable Offcuts',
                ['Remnant Code', 'Product', 'SKU', 'Type', 'Dimensions', 'Available for Reuse', 'Warehouse', 'Location', 'Valuation (' . active_currency() . ')', 'Status', 'Recorded At'],
                collect($data['remnants'] ?? [])->map(fn($r) => [
                    $r['remnant_code'],
                    $r['product_name'],
                    $r['product_sku'],
                    $r['measurement_type'],
                    $r['dimensions'],
                    $r['available_display'],
                    $r['warehouse'],
                    $r['location'],
                    number_format($r['valuation'], 2, '.', ''),
                    $r['status'],
                    $r['created_at'],
                ])->toArray()
            ),
        ];
    }

    private function dailyProductionSheets(): array
    {
        $data = $this->reportData;

        $sheets = [
            new ReportSheetExport(
                'Production Events',
                ['Date', 'Time', 'Order Number', 'Output Type', 'Stage Role', 'Product SKU', 'Product Name', 'UOM', 'Operation Stage', 'Work Center', 'Machine', 'Batch', 'Processed Qty', 'Rejected Qty', 'Scrapped Qty', 'Yield (%)', 'Run Time (min)', 'Run Time (hrs)', 'Setup Time (min)', 'Operator', 'Remarks'],
                collect($data['detailed_logs'] ?? [])->map(fn($r) => [
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
                ])->toArray()
            ),
            new ReportSheetExport(
                'Daily Summary',
                ['Date', 'Active Orders', 'FG Output', 'SFG Output', 'Component Output', 'Events', 'Rejected Qty', 'Scrapped Qty', 'FG Yield (%)', 'Run (hrs)', 'Setup (hrs)'],
                collect($data['daily_breakdown'] ?? [])->map(fn($d) => [
                    $d['date'],
                    $d['active_orders_count'],
                    $d['fg_output'],
                    $d['sfg_output'],
                    $d['component_output'],
                    $d['operation_events_count'],
                    $d['rejected_qty'],
                    $d['scrapped_qty'],
                    $d['fg_yield_pct'] . '%',
                    $d['run_hours'],
                    $d['setup_hours'],
                ])->toArray()
            ),
            new ReportSheetExport(
                'Operational Scrap',
                ['Date / Time', 'Order Number', 'Product', 'SKU', 'Operation', 'Work Center', 'Quantity', 'UOM', 'Measurement Type', 'Dimensions', 'Pieces', 'Reason', 'Warehouse', 'Storage Location', 'Operator', 'Stock Posted'],
                collect($data['scraps'] ?? [])->map(fn($s) => [
                    $s['recorded_at'],
                    $s['order_number'],
                    $s['product_name'],
                    $s['product_sku'],
                    $s['operation'],
                    $s['work_center'],
                    $s['quantity'],
                    $s['uom'],
                    $s['measurement_type'],
                    $s['dimensions'],
                    $s['pieces'],
                    $s['reason'],
                    $s['warehouse'],
                    $s['storage_location'],
                    $s['operator'],
                    $s['stock_posted'] ? 'Yes' : 'No',
                ])->toArray()
            ),
            new ReportSheetExport(
                'Reusable Offcuts',
                ['Remnant Code', 'Source Order', 'Product', 'SKU', 'Type', 'Dimensions', 'Available for Reuse', 'Warehouse', 'Location', 'Valuation', 'Status', 'Recorded At'],
                collect($data['offcuts'] ?? [])->map(fn($r) => [
                    $r['remnant_code'],
                    $r['order_number'],
                    $r['product_name'],
                    $r['product_sku'],
                    $r['measurement_type'],
                    $r['dimensions'],
                    $r['available_display'],
                    $r['warehouse'],
                    $r['location'],
                    number_format($r['valuation'], 2, '.', ''),
                    $r['status'],
                    $r['created_at'],
                ])->toArray()
            ),
        ];

        return $sheets;
    }
}
