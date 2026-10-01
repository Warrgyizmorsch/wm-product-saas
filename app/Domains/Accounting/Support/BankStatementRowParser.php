<?php

namespace App\Domains\Accounting\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Turns one row of a bank statement export into a normalised statement line.
 *
 * Indian bank exports differ a lot: HDFC gives "Date / Narration / Chq./Ref.No.
 * / Withdrawal Amt. / Deposit Amt.", SBI "Txn Date / Description / Ref No. /
 * Debit / Credit", others a single signed "Amount". Dates arrive as Excel
 * serial numbers, 02/09/2026 (day first, never US month-first), 02-Sep-26 or
 * ISO. This class accepts all of those and says why a row was rejected
 * instead of guessing.
 */
class BankStatementRowParser
{
    /** Header keys (as Laravel Excel slugs them) for each field, most specific first. */
    private const DATE_KEYS = ['transaction_date', 'txn_date', 'tran_date', 'date', 'value_date', 'value_dt', 'posting_date'];
    private const DESCRIPTION_KEYS = ['description', 'narration', 'particulars', 'remarks', 'transaction_details', 'details'];
    private const REFERENCE_KEYS = ['reference', 'ref_no', 'refno', 'reference_no', 'chq_ref_no', 'chqrefno', 'cheque_no', 'chq_no', 'chequeno', 'utr', 'utr_no', 'instrument_no'];
    private const AMOUNT_KEYS = ['amount', 'amount_inr', 'transaction_amount', 'signed_amount'];
    private const WITHDRAWAL_KEYS = ['withdrawal', 'withdrawals', 'withdrawal_amt', 'withdrawal_amount', 'debit', 'debits', 'debit_amount', 'dr', 'dr_amount', 'paid_out'];
    private const DEPOSIT_KEYS = ['deposit', 'deposits', 'deposit_amt', 'deposit_amount', 'credit', 'credits', 'credit_amount', 'cr', 'cr_amount', 'paid_in'];
    /** A separate "Dr/Cr" column that gives the direction of a single Amount column. */
    private const DRCR_KEYS = ['drcr', 'dr_cr', 'cr_dr', 'crdr', 'debit_credit', 'type', 'txn_type', 'transaction_type'];
    private const BALANCE_KEYS = ['balance', 'closing_balance', 'running_balance', 'available_balance', 'balance_amt', 'balance_inr'];

    /** Day-first formats tried in order. US month-first is deliberately absent. */
    private const DATE_FORMATS = [
        'Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y',
        'd M Y', 'd-M-Y', 'd M y', 'd-M-y', 'd/M/Y', 'd/M/y', 'j M Y', 'd F Y', 'j F Y', 'Y/m/d', 'M d, Y',
    ];

    /**
     * @param array<string|int, mixed> $row keyed by slugged header
     * @return array{ok: true, date: string, description: ?string, reference: ?string, amount: float}|array{ok: false, error: string}
     */
    public function parse(array $row): array
    {
        $row = $this->normaliseKeys($row);

        $rawDate = $this->first($row, self::DATE_KEYS);
        if ($this->isBlank($rawDate)) {
            return ['ok' => false, 'error' => 'no date'];
        }

        $date = $this->parseDate($rawDate);
        if ($date === null) {
            return ['ok' => false, 'error' => "unreadable date \"{$this->stringify($rawDate)}\""];
        }

        $amount = $this->amountFrom($row);
        if ($amount === null) {
            return ['ok' => false, 'error' => 'no amount'];
        }

        if (round($amount, 2) === 0.0) {
            return ['ok' => false, 'error' => 'zero amount'];
        }

        $description = $this->first($row, self::DESCRIPTION_KEYS);
        $reference = $this->first($row, self::REFERENCE_KEYS);

        return [
            'ok' => true,
            'date' => $date->toDateString(),
            'description' => $this->isBlank($description) ? null : mb_substr(trim($this->stringify($description)), 0, 255),
            'reference' => $this->isBlank($reference) ? null : mb_substr(trim($this->stringify($reference)), 0, 100),
            'amount' => round($amount, 2),
            'balance' => $this->balanceFrom($row),
        ];
    }

    /**
     * Running balance printed on the statement, if the export has one.
     * "1,234.00 Cr" is positive, "1,234.00 Dr" (overdrawn) negative.
     *
     * @param array<string, mixed> $row
     */
    private function balanceFrom(array $row): ?float
    {
        $value = $this->first($row, self::BALANCE_KEYS);

        if ($this->isBlank($value)) {
            return null;
        }

        $balance = $this->parseAmount($value);

        return $balance === null ? null : round($balance, 2);
    }

    /**
     * Excel serial numbers, day-first strings and ISO dates. Returns null
     * rather than guessing: a wrong date silently breaks matching.
     */
    public function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{4,6}(\.\d+)?$/', trim($value)))) {
            $serial = (float) $value;

            // Excel serials 20000–80000 cover 1954–2119; anything else is not a date.
            if ($serial < 20000 || $serial > 80000) {
                return null;
            }

            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($text === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $text);
            } catch (Throwable) {
                continue;
            }

            if ($parsed === false || $parsed->year < 1950 || $parsed->year > 2150) {
                continue;
            }

            if ($parsed->format($format) === $text || $this->looseFormatMatch($parsed, $format, $text)) {
                return $parsed->startOfDay();
            }
        }

        return null;
    }

    /**
     * Amount strings like "1,23,456.00", "(500.00)", "500.00 Dr", "₹ 1,000".
     */
    public function parseAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }

        $negative = false;

        if (preg_match('/^\((.*)\)$/', $text, $m)) {
            $negative = true;
            $text = $m[1];
        }

        if (preg_match('/\b(dr|debit)\.?$/i', $text)) {
            $negative = true;
            $text = preg_replace('/\b(dr|debit)\.?$/i', '', $text);
        } elseif (preg_match('/\b(cr|credit)\.?$/i', $text)) {
            $text = preg_replace('/\b(cr|credit)\.?$/i', '', $text);
        }

        $clean = preg_replace('/[^\d.\-]/', '', $text);

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        $amount = (float) $clean;

        return $negative ? -abs($amount) : $amount;
    }

    /**
     * Signed amount: a single Amount column wins; otherwise deposit minus
     * withdrawal from separate columns.
     *
     * @param array<string, mixed> $row
     */
    private function amountFrom(array $row): ?float
    {
        $single = $this->first($row, self::AMOUNT_KEYS);
        if (! $this->isBlank($single)) {
            $amount = $this->parseAmount($single);
            $indicator = strtolower(trim((string) ($this->first($row, self::DRCR_KEYS) ?? '')));

            if ($amount !== null && $indicator !== '') {
                if (preg_match('/^(dr|d|debit|withdrawal|wdl)\b/', $indicator)) {
                    return -abs($amount);
                }
                if (preg_match('/^(cr|c|credit|deposit|dep)\b/', $indicator)) {
                    return abs($amount);
                }
            }

            return $amount;
        }

        $withdrawal = $this->parseAmount($this->first($row, self::WITHDRAWAL_KEYS) ?? '');
        $deposit = $this->parseAmount($this->first($row, self::DEPOSIT_KEYS) ?? '');

        if ($withdrawal === null && $deposit === null) {
            return null;
        }

        return abs($deposit ?? 0.0) - abs($withdrawal ?? 0.0);
    }

    /**
     * @param array<string|int, mixed> $row
     * @return array<string, mixed>
     */
    private function normaliseKeys(array $row): array
    {
        $normalised = [];

        foreach ($row as $key => $value) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $key)), '_');
            $normalised[$slug] ??= $value;
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && ! $this->isBlank($row[$key])) {
                return $row[$key];
            }
        }

        return null;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : json_encode($value);
    }

    /**
     * createFromFormat accepts "2/9/2026" for "d/m/Y" but reformats it as
     * "02/09/2026"; compare numerically so single-digit days still parse,
     * while rejecting overflow like 31/02/2026 → 03/03/2026.
     */
    private function looseFormatMatch(Carbon|CarbonImmutable $parsed, string $format, string $text): bool
    {
        $partsText = preg_split('/[^A-Za-z0-9]+/', strtolower($text));
        $partsParsed = preg_split('/[^A-Za-z0-9]+/', strtolower($parsed->format($format)));

        if (count($partsText) !== count($partsParsed)) {
            return false;
        }

        foreach ($partsText as $i => $part) {
            $other = $partsParsed[$i];

            if (ctype_digit($part) && ctype_digit($other)) {
                if ((int) $part !== (int) $other) {
                    return false;
                }
            } elseif (strncmp($part, $other, 3) !== 0) {
                return false;
            }
        }

        return true;
    }
}
