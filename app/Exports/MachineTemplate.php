<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MachineTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Work Center Code',
            'Hourly Cost',
            'Status'
        ];
    }

    public function collection()
    {
        return collect([
            [
                'MCH-CNC-01',
                '3-Axis CNC Wood Router',
                'WC-CNC',
                '45.00',
                'active'
            ],
            [
                'MCH-CNC-02',
                '5-Axis High Precision Router',
                'WC-CNC',
                '75.00',
                'active'
            ]
        ]);
    }
}
