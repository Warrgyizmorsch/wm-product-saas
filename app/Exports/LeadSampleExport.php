<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadSampleExport implements FromCollection, WithHeadings
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Company Name',
            'Contact Person',
            'Email',
            'Phone',
            'Requirement',
            'Expected Amount',
            'Expected Sale Date (YYYY-MM-DD)',
            'Source',
            'Priority',
            'Segment',
            'Industry Type',
            'Country',
            'State',
            'City',
            'Address',
            'Status'
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'Example Company Ltd',
                'John Doe',
                'john.doe@example.com',
                '+1234567890',
                'Interested in ERP software license and deployment',
                '50000.00',
                '2026-08-31',
                'Website',
                'Medium',
                'Mid-Market',
                'Technology',
                'India',
                'Delhi',
                'New Delhi',
                '123, Tech Park, Okhla Phase 3',
                'New'
            ]
        ]);
    }
}
