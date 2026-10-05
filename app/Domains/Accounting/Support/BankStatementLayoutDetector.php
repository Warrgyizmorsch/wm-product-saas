<?php

namespace App\Domains\Accounting\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Reads a bank statement export (CSV, XLS, XLSX) as raw rows and finds its
 * header row and columns.
 *
 * Real exports rarely start with the header: HDFC puts the bank name, account
 * number and period above it and a row of asterisks below; SBI, ICICI, Axis
 * and Kotak do similar. We score every row in the top of the sheet against
 * known column names and take the first row that has a date column plus
 * amount column(s). Data then runs until the first blank row or summary
 * block after the transactions.
 */
class BankStatementLayoutDetector
{
    /** How far down the sheet a header row is looked for. */
    private const MAX_HEADER_SCAN = 60;

    /**
     * Canonical field => header names (normalised: lowercase, words only).
     * Order matters inside each list only for readability; matching is exact
     * on the normalised text, then "starts with" as a fallback.
     */
    public const FIELD_ALIASES = [
        'date' => ['date', 'txn date', 'tran date', 'transaction date', 'trans date', 'posting date', 'book date', 'txn posted date', 'transaction posted date'],
        'value_date' => ['value date', 'value dt'],
        'description' => ['narration', 'description', 'particulars', 'remarks', 'transaction details', 'transaction remarks', 'details', 'transaction description'],
        'reference' => ['chq ref no', 'chq no', 'cheque no', 'cheque number', 'ref no', 'reference', 'reference no', 'ref no cheque no', 'chq no ref no', 'utr', 'utr no', 'instrument no', 'cheque ref no', 'chequeno'],
        'withdrawal' => ['withdrawal amt', 'withdrawal', 'withdrawals', 'withdrawal amount', 'withdrawal amount inr', 'debit', 'debits', 'debit amount', 'dr amount', 'dr', 'paid out'],
        'deposit' => ['deposit amt', 'deposit', 'deposits', 'deposit amount', 'deposit amount inr', 'credit', 'credits', 'credit amount', 'cr amount', 'cr', 'paid in'],
        'amount' => ['amount', 'amount inr', 'transaction amount', 'txn amount'],
        'drcr' => ['dr cr', 'cr dr', 'drcr', 'crdr', 'debit credit', 'type', 'txn type', 'transaction type'],
        'balance' => ['closing balance', 'balance', 'balance inr', 'running balance', 'available balance', 'balance amt'],
    ];

    /**
     * @return list<list<mixed>> raw cell values of the first sheet that has any data
     */
    public function readRows(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (Throwable) {
            // Unknown extension (e.g. .txt): treat it as CSV.
            $reader = IOFactory::createReader('Csv');
        }

        $reader->setReadDataOnly(true);

        if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
            // PhpSpreadsheet's own guess is thrown off by banner lines such as
            // "HDFC BANK Ltd." (it can pick the space), so count real separators.
            $reader->setDelimiter($this->csvDelimiter($path));
        }

        $book = $reader->load($path);

        foreach ($book->getAllSheets() as $sheet) {
            // Raw values: Excel dates stay serial numbers, amounts stay numbers.
            $rows = $sheet->toArray(null, true, false, false);
            $rows = array_values(array_filter($rows, fn ($row) => $row !== null));

            if ($this->hasData($rows)) {
                return array_map(fn ($row) => array_values($row), $rows);
            }
        }

        return [];
    }

    /**
     * @param list<list<mixed>> $rows
     * @return array{header_row: int, column_map: array<string, int>, header_cells: list<string>}|null
     */
    public function detect(array $rows): ?array
    {
        $limit = min(count($rows), self::MAX_HEADER_SCAN);

        for ($i = 0; $i < $limit; $i++) {
            $map = $this->mapHeader($rows[$i]);

            if ($map !== null) {
                return [
                    'header_row' => $i,
                    'column_map' => $map,
                    'header_cells' => array_map(fn ($cell) => trim((string) $cell), $rows[$i]),
                ];
            }
        }

        return null;
    }

    /**
     * Canonical field => column index for a header row, or null when the row
     * doesn't look like a statement header (needs a date and an amount).
     *
     * @param list<mixed> $cells
     * @return array<string, int>|null
     */
    public function mapHeader(array $cells): ?array
    {
        $map = [];

        foreach ($cells as $index => $cell) {
            $text = $this->normalise($cell);
            if ($text === '') {
                continue;
            }

            $field = $this->fieldFor($text);
            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = $index;
            }
        }

        $hasAmount = isset($map['amount']) || isset($map['withdrawal']) || isset($map['deposit']);

        if (! isset($map['date']) && isset($map['value_date'])) {
            // Some banks only print a value date.
            $map['date'] = $map['value_date'];
        }

        return isset($map['date']) && $hasAmount && count($map) >= 3 ? $map : null;
    }

    /**
     * The data rows below the header, mapped to canonical keys. Stops at the
     * first blank row once transactions have started (summary blocks follow),
     * and skips decoration rows such as "********".
     *
     * @param list<list<mixed>> $rows
     * @param array{header_row: int, column_map: array<string, int>} $layout
     * @return list<array{row: int, values: array<string, mixed>}> row = 1-based sheet row number
     */
    public function dataRows(array $rows, array $layout): array
    {
        $out = [];
        $started = false;

        for ($i = $layout['header_row'] + 1, $n = count($rows); $i < $n; $i++) {
            $row = $rows[$i];

            if (! $this->hasData([$row])) {
                if ($started) {
                    break;
                }
                continue;
            }

            $values = [];
            foreach ($layout['column_map'] as $field => $index) {
                $values[$field] = $row[$index] ?? null;
            }

            $amountCells = array_filter(
                [$values['amount'] ?? null, $values['withdrawal'] ?? null, $values['deposit'] ?? null],
                fn ($v) => $v !== null && trim((string) $v) !== '' && preg_match('/\d/', (string) $v)
            );

            // Decoration rows ("********", "-----") and text-only rows carry no amount.
            if ($amountCells === []) {
                continue;
            }

            $started = true;
            $out[] = ['row' => $i + 1, 'values' => $values];
        }

        return $out;
    }

    /**
     * Stable fingerprint of a header row, so a saved layout is re-used only
     * for the same export format.
     *
     * @param list<mixed> $cells
     */
    public function signature(array $cells): string
    {
        $normalised = array_values(array_filter(array_map(fn ($cell) => $this->normalise($cell), $cells), fn ($t) => $t !== ''));

        return sha1(implode('|', $normalised));
    }

    /**
     * First rows of the sheet for the manual mapping screen.
     *
     * @param list<list<mixed>> $rows
     * @return list<list<string>>
     */
    public function preview(array $rows, int $count = 25): array
    {
        return array_map(
            fn ($row) => array_map(fn ($cell) => $cell === null ? '' : mb_substr(trim((string) $cell), 0, 60), $row),
            array_slice($rows, 0, $count)
        );
    }

    public function normalise(mixed $cell): string
    {
        if ($cell === null || is_array($cell)) {
            return '';
        }

        $text = strtolower(trim((string) $cell));
        $text = preg_replace('/\(.*?\)/', ' ', $text);   // "Withdrawal Amt. (INR)" → "withdrawal amt"
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function fieldFor(string $text): ?string
    {
        foreach (self::FIELD_ALIASES as $field => $aliases) {
            if (in_array($text, $aliases, true)) {
                return $field;
            }
        }

        // "Withdrawal Amount (INR)", "Transaction Date & Time"…
        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (strlen($alias) >= 4 && str_starts_with($text, $alias . ' ')) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * The separator used most often across the first lines of the file,
     * ignoring text inside quotes. Comma unless another one clearly wins.
     */
    private function csvDelimiter(string $path): string
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        $counts = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        for ($i = 0; $i < 40 && ($line = fgets($handle)) !== false; $i++) {
            $unquoted = preg_replace('/"[^"]*"/', '', $line);
            foreach ($counts as $delimiter => $count) {
                $counts[$delimiter] += substr_count($unquoted, $delimiter);
            }
        }
        fclose($handle);

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /** @param list<list<mixed>> $rows */
    private function hasData(array $rows): bool
    {
        foreach ($rows as $row) {
            foreach ((array) $row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
