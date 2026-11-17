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
            'Routing Number',
            'Base Quantity',
            'Base UOM Code',
            'Version',
            'BOM Type',
            'Usage Context',
            'Effective Date',
            'Expiry Date',
            'Operation Sequence',
            'Component Code',
            'Item Quantity',
            'Item UOM Code',
            'Material Scrap Percentage',
            'Is Alternative',
            'Alternative Of',
            'Priority',
            'Child BOM Number',
            'Notes',
        ];
    }

    public function collection()
    {
        return collect([
            [
                'BOM-FG-OAK-01',
                'Premium Oak Table BOM',
                'FG-PROD-X', // Finished product SKU
                'RTG-001',   // Active or Scheduled Routing Number
                '1',
                'PCS',
                '1.0.0',
                'manufacturing',
                'manufacturing',
                '2026-07-14',
                '',
                '10',        // Routing Operation Sequence (e.g. 10)
                'RM-OAK-WD', // Component product SKU (e.g. Oak Wood)
                '4',
                'PCS',
                '5.0',
                '0',         // Primary component
                '',
                '1',
                '',
                'Cut to spec',
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
                '',
                '10',        // Same Operation Sequence as primary
                'RM-MAPLE-ALT',
                '4',
                'PCS',
                '5.0',
                '1',         // Alternative to RM-OAK-WD
                'RM-OAK-WD',
                '1',
                '',
                'Alternative hardwood',
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
                '',
                '20',
                'RM-SCR-01', // Component product SKU 2 (e.g. Screws)
                '16',
                'PCS',
                '0.0',
                '0',
                '',
                '1',
                '',
                '',
            ],
        ]);
    }
}
