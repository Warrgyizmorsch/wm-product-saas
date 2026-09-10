<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class LeadSampleExport implements FromCollection, WithHeadings, WithEvents
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Lead Type',
            'Company Name',
            'GSTIN',
            'Company Email',
            'Company Phone',
            'Contact Person',
            'Designation',
            'Contact Email',
            'Contact Phone',
            'Expected Amount',
            'Expected Sale Date',
            'Requirement',
            'Industry Type',
            'Source',
            'Priority',
            'Segment',
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
                'b2b',
                'Warrgyizmorsch Pvt Ltd',
                '08AAFCS1234E1Z0',
                'info@warrgyizmorsch.com',
                '9876543210',
                'Mahipal Singh Rathore',
                'Managing Director',
                'mahipal.rathore@example.com',
                '9876512345',
                '155000.00',
                '2026-09-30',
                'Enterprise ERP Software License & Deployment',
                'Manufacturing',
                'Meta Ads',
                'High',
                'Mid-Market',
                'India',
                'Rajasthan',
                'Udaipur',
                'Sukher Industrial Area, Phase 1',
                'New'
            ],
            [
                'b2c',
                '',
                '',
                '',
                '',
                'Rahul Sharma',
                'Individual Customer',
                'rahul.sharma@example.com',
                '9876543211',
                '25000.00',
                '2026-10-15',
                'Custom Home Furniture Set',
                'Retail',
                'Website Form',
                'Medium',
                'SMB',
                'India',
                'Rajasthan',
                'Jaipur',
                '123, Vaishali Nagar',
                'New'
            ]
        ]);
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Validation for Lead Type (Column A)
                $typeValidation = $sheet->getCell('A2')->getDataValidation();
                $typeValidation->setType(DataValidation::TYPE_LIST);
                $typeValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $typeValidation->setAllowBlank(false);
                $typeValidation->setShowInputMessage(true);
                $typeValidation->setShowErrorMessage(true);
                $typeValidation->setShowDropDown(true);
                $typeValidation->setErrorTitle('Input Error');
                $typeValidation->setError('Select b2b or b2c');
                $typeValidation->setPromptTitle('Lead Type');
                $typeValidation->setPrompt('Select b2b (Business) or b2c (Individual).');
                $typeValidation->setFormula1('"b2b,b2c"');

                // 2. Validation for Source (Column N)
                $sources = ['Direct Inquiry', 'Website Form', 'Web Search', 'Meta Ads', 'IndiaMART', 'Cold Call', 'Referral', 'Employee Referral', 'Partner', 'Advertisement', 'Trade Show'];
                $sourcesList = '"' . implode(',', $sources) . '"';

                $sourceValidation = $sheet->getCell('N2')->getDataValidation();
                $sourceValidation->setType(DataValidation::TYPE_LIST);
                $sourceValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $sourceValidation->setAllowBlank(true);
                $sourceValidation->setShowInputMessage(true);
                $sourceValidation->setShowErrorMessage(true);
                $sourceValidation->setShowDropDown(true);
                $sourceValidation->setErrorTitle('Input error');
                $sourceValidation->setError('Value is not in the list');
                $sourceValidation->setPromptTitle('Pick Source');
                $sourceValidation->setPrompt('Please pick a lead source.');
                $sourceValidation->setFormula1($sourcesList);

                // 3. Validation for Priority (Column O)
                $priorities = ['Low', 'Medium', 'High', 'Urgent'];
                $prioritiesList = '"' . implode(',', $priorities) . '"';

                $priorityValidation = $sheet->getCell('O2')->getDataValidation();
                $priorityValidation->setType(DataValidation::TYPE_LIST);
                $priorityValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $priorityValidation->setAllowBlank(true);
                $priorityValidation->setShowInputMessage(true);
                $priorityValidation->setShowErrorMessage(true);
                $priorityValidation->setShowDropDown(true);
                $priorityValidation->setErrorTitle('Input error');
                $priorityValidation->setError('Value is not in the list');
                $priorityValidation->setPromptTitle('Pick Priority');
                $priorityValidation->setPrompt('Please pick a priority level.');
                $priorityValidation->setFormula1($prioritiesList);

                // 4. Validation for Segment (Column P)
                $segments = ['SMB', 'Mid-Market', 'Enterprise'];
                $segmentsList = '"' . implode(',', $segments) . '"';

                $segmentValidation = $sheet->getCell('P2')->getDataValidation();
                $segmentValidation->setType(DataValidation::TYPE_LIST);
                $segmentValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $segmentValidation->setAllowBlank(true);
                $segmentValidation->setShowInputMessage(true);
                $segmentValidation->setShowErrorMessage(true);
                $segmentValidation->setShowDropDown(true);
                $segmentValidation->setErrorTitle('Input error');
                $segmentValidation->setError('Value is not in the list');
                $segmentValidation->setPromptTitle('Pick Segment');
                $segmentValidation->setPrompt('Please pick a business segment.');
                $segmentValidation->setFormula1($segmentsList);

                for ($i = 2; $i <= 100; $i++) {
                    $sheet->getCell("A{$i}")->setDataValidation(clone $typeValidation);
                    $sheet->getCell("N{$i}")->setDataValidation(clone $sourceValidation);
                    $sheet->getCell("O{$i}")->setDataValidation(clone $priorityValidation);
                    $sheet->getCell("P{$i}")->setDataValidation(clone $segmentValidation);
                }
            }
        ];
    }
}
