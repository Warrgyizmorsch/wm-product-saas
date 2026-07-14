<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkCenterTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Capacity Hours Per Day',
            'Efficiency Percentage',
            'Active'
        ];
    }

    public function collection()
    {
        return collect([
            [
                'WC-CNC',
                'CNC Cutting and Milling Center',
                '16.0',
                '95.0',
                'Yes'
            ],
            [
                'WC-ASY',
                'Assembly Line 1',
                '8.0',
                '98.0',
                'Yes'
            ]
        ]);
    }
}
