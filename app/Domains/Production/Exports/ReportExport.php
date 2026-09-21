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
            'order-detail' => $this->orderDetailSheets(),
            default        => [new ReportSingleSheetExport($this->type, $this->reportData)],
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
                ['Cost Element', 'Estimated Cost', 'Actual Incurred Cost', 'Variance Cost', 'Status'],
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
                ['Material', 'SKU', 'Consuming Operation', 'Work Center', 'UOM', 'Planned Qty', 'Issued Qty', 'Consumed Qty', 'Floor Stock WIP', 'Consumption %', 'Unit Cost', 'Planned Cost', 'Consumed Cost', 'Variance Cost'],
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
                ['Product', 'SKU', 'Operation', 'Quantity', 'Reason', 'Recorded At', 'Stock Posted'],
                collect($data['scrap_events'])->map(fn($s) => [
                    $s['product'],
                    $s['product_sku'],
                    $s['operation'],
                    $s['quantity'],
                    $s['reason'],
                    $s['recorded_at'],
                    $s['stock_posted'] ? 'Yes' : 'No',
                ])->toArray()
            ),
        ];
    }
}
