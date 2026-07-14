<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RoutingTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'Routing Code',
            'Routing Name',
            'Product Code',
            'Version',
            'Operation Sequence',
            'Operation Name',
            'Operation Code',
            'Operation Type',
            'Work Center Code',
            'Machine Code',
            'Setup Time Minutes',
            'Processing Time Minutes',
            'Yield Percentage',
            'Is External',
            'Material Code',
            'Material Quantity'
        ];
    }

    public function collection()
    {
        return collect([
            [
                'RT-FG-OAK-01',
                'Standard Oak Table Routing',
                'FG-PROD-X', // Product SKU
                '1.0.0',
                '10',
                'Cutting Plank',
                'OP-CUT',
                'manufacturing',
                'WC-CNC',
                'MCH-CNC-01',
                '15',
                '45',
                '98',
                'No',
                'RM-OAK-WD', // Material SKU
                '4'
            ],
            [
                'RT-FG-OAK-01',
                '',
                '',
                '',
                '20',
                'Quality Check',
                'OP-QC',
                'inspection',
                'WC-ASY',
                '',
                '5',
                '10',
                '100',
                'No',
                '',
                ''
            ]
        ]);
    }
}
