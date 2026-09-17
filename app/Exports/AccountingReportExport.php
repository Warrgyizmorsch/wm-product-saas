<?php

namespace App\Exports;

use App\Domains\Accounting\Support\ReportTables;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One accounting report as a single sheet: title, filter lines, then each
 * table with its heading, rows and totals. See ReportTables::build().
 */
class AccountingReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly array $report,
    ) {
    }

    public function title(): string
    {
        // Excel sheet names are limited to 31 characters and a few symbols.
        return mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], ' ', $this->report['title']), 0, 31);
    }

    public function array(): array
    {
        $rows = [[$this->report['title']]];

        foreach ($this->report['meta'] as $line) {
            $rows[] = $line;
        }

        foreach ($this->report['sections'] as $section) {
            $rows[] = [];
            $rows[] = [$section['title']];
            $rows[] = $section['header'];

            foreach ([...$section['rows'], ...$section['footer']] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
