<?php

namespace App\Domains\Accounting\Services\StatementExtraction;

/**
 * A source of transaction-line extraction from an uploaded bank statement
 * (PDF, scanned image, …). Bound in AppServiceProvider so the actual
 * third-party API can be swapped without touching BankReconciliationService —
 * mirrors the ExchangeRateProvider pattern (App\Domains\Accounting\Services\ExchangeRates).
 */
interface StatementExtractionProvider
{
    /**
     * Extract transaction lines (and whatever statement-level metadata the
     * provider makes available) from a statement file already saved to local
     * disk at $absolutePath.
     *
     * @return array{
     *     lines: array<int, array{date: string, description: string, amount: float, suggested_ledger?: ?string}>,
     *     opening_balance: ?float,
     *     closing_balance: ?float,
     *     account_info: array<string, mixed>,
     *     raw: array<string, mixed>,
     * } lines[].amount is signed: positive = deposit/inflow, negative = withdrawal/outflow.
     *   opening_balance/closing_balance are the provider's own read of the
     *   statement (null if it doesn't report them) — not to be confused with
     *   the reconciliation's user-entered balances. raw is the provider's
     *   full response, kept for audit even though only part of it is used.
     *
     * @throws StatementExtractionException
     */
    public function extract(string $absolutePath, string $mimeType): array;
}
