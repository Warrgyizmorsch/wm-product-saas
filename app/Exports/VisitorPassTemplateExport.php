<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VisitorPassTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Visitor Name *',
            'Phone Number *',
            'Email Address',
            'Company / Org',
            'Designation',
            'ID Proof Type',
            'ID Proof Number',
            'Host Employee Email',
            'Purpose of Visit',
            'Gate Number',
            'Entry Type',
            'Pass Fee',
            'Expected Arrival (YYYY-MM-DD HH:MM)',
            'Security Notes',
        ];
    }

    public function collection()
    {
        return collect([
            [
                'Ramesh Kumar',
                '9876543210',
                'ramesh@example.com',
                'TechCorp Solutions',
                'Senior Consultant',
                'Aadhaar Card',
                '1234-5678-9012',
                'admin@example.com',
                'Meeting',
                'Main Gate 1',
                'Walk-in',
                '0.00',
                now()->addHours(2)->format('Y-m-d H:i'),
                'Car No. DL-01-AB-1234, Laptop carried',
            ],
            [
                'Priya Sharma',
                '9811223344',
                'priya@vendorcorp.com',
                'Global Vendor Pvt Ltd',
                'Audit Manager',
                'PAN Card',
                'ABCDE1234F',
                'hr.manager@example.com',
                'Audit',
                'Gate 2',
                'Walk-in',
                '50.00',
                now()->addDays(1)->format('Y-m-d 10:00'),
                'Annual compliance security review',
            ],
        ]);
    }
}
