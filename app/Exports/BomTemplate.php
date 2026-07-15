<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BomTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'BOM Number',
            'BOM Name',
            'Product Code',
            'Base Quantity',
            'Base UOM Code',
            'Version',
            'BOM Type',
            'Usage Context',
            'Effective Date',
            'Expiry Date',
            'Component Code',
            'Item Quantity',
            'Item UOM Code',
            'Material Scrap Percentage',
            'Child BOM Number'
        ];
    }

    public function collection()
    {
        return collect([
            [
                'BOM-FG-OAK-01',
                'Premium Oak Table BOM',
                'FG-PROD-X', // Finished product SKU
                '1',
                'PCS',
                '1.0.0',
                'manufacturing',
                'manufacturing',
                '2026-07-14',
                '',
                'RM-OAK-WD', // Component product SKU (e.g. Oak Wood)
                '4',
                'PCS',
                '5.0',
                ''
            ],
            [
                'BOM-FG-OAK-01',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'RM-SCR-01', // Component product SKU 2 (e.g. Screws)
                '16',
                'PCS',
                '0.0',
                ''
            ]
        ]);
    }
}
