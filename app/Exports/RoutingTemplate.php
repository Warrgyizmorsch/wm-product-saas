<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * RT-IMPL-16: routing import template — one row per operation. Section and one resource (machine OR manual batch)
 * are required for internal operations; run time and costs are worked out from the latest section calculation
 * (processing time is not read). Materials belong to the BOM (no material columns).
 */
class RoutingTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'Routing Code',
            'Routing Name',
            'Version',
            'Operation Sequence',
            'Operation Name',
            'Operation Code',
            'Operation Type',
            'Work Center Code',
            'Section Code',
            'Machine Code',
            'Manual Batch Name',
            'Setup Time Minutes',
            'Setup Time Per Piece Minutes',
            'Yield Percentage',
            'Is Alternative',
            'Replaces Operation Number',
            'Priority',
            'Is External',
            'Vendor Code',
            'Subcontract Cost Per Unit',
        ];
    }

    public function collection()
    {
        return collect([
            ['RT-FG-OAK-01', 'Standard Oak Table Routing', '1.0.0', '10', 'Cutting Plank', 'OP-CUT', 'manufacturing', 'WC-CNC', 'SEC-CUT', 'MCH-CNC-01', '', '15', '0.5', '98', 'No', '', '', 'No', '', ''],
            ['RT-FG-OAK-01', '', '', '20', 'Quality Check', 'OP-QC', 'inspection', 'WC-ASY', 'SEC-QC', '', 'QC Team A', '5', '', '100', 'No', '', '', 'No', '', ''],
        ]);
    }
}