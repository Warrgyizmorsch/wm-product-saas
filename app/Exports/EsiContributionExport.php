<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * ESIC monthly contribution upload, in the column order of the portal's
 * "MC template": IP number, IP name, days paid, total monthly wages, reason
 * code for zero workings days, last working day.
 */
class EsiContributionExport implements FromArray, WithHeadings, WithStrictNullComparison
{
    public function __construct(
        private readonly array $rows,
    ) {
    }

    public function headings(): array
    {
        return [
            'IP Number (10 Digits)',
            'IP Name (Only alphabets and space)',
            'No of Days for which wages paid/payable during the month',
            'Total Monthly Wages',
            'Reason Code for Zero workings days',
            'Last Working Day',
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
