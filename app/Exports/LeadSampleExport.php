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
            'Company Name',
            'Contact Person',
            'Email',
            'Phone',
            'Expected Amount',
            'Expected Sale Date',
            'Requirement',
            'Industry Type',
            'Source',
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
                '50000.00',
                '2026-08-31',
                'Interested in ERP software license and deployment',
                'Technology',
                'Web Search',
                'India',
                'Delhi',
                'New Delhi',
                '123, Tech Park, Okhla Phase 3',
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

                $sources = ['Cold Call', 'Employee Referral', 'Partner', 'Web Search', 'Advertisement', 'Trade Show'];
                $sourcesList = '"' . implode(',', $sources) . '"';

                // Validation for Source (Column I)
                $validation = $sheet->getCell('I2')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Input error');
                $validation->setError('Value is not in the list');
                $validation->setPromptTitle('Pick Source');
                $validation->setPrompt('Please pick a lead source.');
                $validation->setFormula1($sourcesList);

                for ($i = 3; $i <= 100; $i++) {
                    $sheet->getCell("I{$i}")->setDataValidation(clone $validation);
                }
            }
        ];
    }
}
