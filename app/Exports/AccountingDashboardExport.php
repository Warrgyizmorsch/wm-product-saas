<?php

namespace App\Exports;

use App\Domains\Accounting\Support\DashboardReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccountingDashboardExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function title(): string
    {
        return 'Accounting Dashboard';
    }

    public function array(): array
    {
        $report = DashboardReport::build($this->data);
        $rows = [['Accounting Dashboard']];

        foreach ($report['meta'] as $line) {
            $rows[] = $line;
        }

        foreach ($report['sections'] as $section) {
            $rows[] = [];
            $rows[] = [$section['title']];
            $rows[] = $section['header'];

            foreach ($section['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
