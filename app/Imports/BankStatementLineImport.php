<?php

namespace App\Imports;

use App\Domains\Accounting\Models\BankStatementLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Expects a Date/Description/Amount statement export — amount signed
 * (positive = deposit, negative = withdrawal), matching how
 * JournalEntry::signedAmount() reads a debit-normal bank/cash account.
 */
class BankStatementLineImport implements ToCollection, WithHeadingRow
{
    private int $imported = 0;

    public function __construct(
        private readonly int $bankReconciliationId,
        private readonly int $tenantId,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $date = $row['date'] ?? $row['transaction_date'] ?? null;
            $amount = $row['amount'] ?? null;

            if (empty($date) || !is_numeric($amount)) {
                continue;
            }

            BankStatementLine::create([
                'tenant_id' => $this->tenantId,
                'bank_reconciliation_id' => $this->bankReconciliationId,
                'transaction_date' => Carbon::parse($date),
                'description' => $row['description'] ?? null,
                'amount' => (float) $amount,
            ]);

            $this->imported++;
        }
    }

    public function importedCount(): int
    {
        return $this->imported;
    }
}
