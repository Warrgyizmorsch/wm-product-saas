<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a bank statement export (CSV/XLS/XLSX) into raw rows keyed by
 * header. It does not create anything: BankReconciliationService validates,
 * de-duplicates and saves the rows inside one transaction, so a bad file
 * never leaves half a statement behind. Column meaning is worked out by
 * App\Domains\Accounting\Support\BankStatementRowParser.
 */
class BankStatementLineImport implements ToCollection, WithHeadingRow
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $values = $row instanceof Collection ? $row->all() : (array) $row;

            // Skip fully empty rows (trailing blank lines, spacer rows).
            if (collect($values)->filter(fn ($value) => $value !== null && trim((string) $value) !== '')->isEmpty()) {
                continue;
            }

            $this->rows[] = $values;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
