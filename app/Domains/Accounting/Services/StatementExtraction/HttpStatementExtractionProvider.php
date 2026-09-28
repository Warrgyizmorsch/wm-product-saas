<?php

namespace App\Domains\Accounting\Services\StatementExtraction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client for the "Bank Statement Parser & Tally Automation API" (FastAPI,
 * see GET /openapi.json on the configured host) — POST /parse takes a
 * multipart `file` (+ optional `password` for protected PDFs) and returns a
 * `transactions[]` array with separate `debit`/`credit` fields per row (only
 * one of the two is set) plus ledger/voucher-type predictions we don't use
 * yet. No API key is required by this service today; `services.statement_extraction.key`
 * is sent as a Bearer token only when configured, so a future auth layer in
 * front of the API doesn't need a code change here.
 */
class HttpStatementExtractionProvider implements StatementExtractionProvider
{
    public function extract(string $absolutePath, string $mimeType): array
    {
        $url = config('services.statement_extraction.url');

        if (empty($url)) {
            throw new StatementExtractionException(
                'Statement extraction API is not configured — set STATEMENT_EXTRACTION_API_URL in .env.'
            );
        }

        $request = Http::acceptJson()->timeout(60);

        $key = config('services.statement_extraction.key');
        if (!empty($key)) {
            $request = $request->withToken($key);
        }

        try {
            $response = $request
                ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
                ->post(rtrim($url, '/') . '/parse');
        } catch (ConnectionException $e) {
            throw new StatementExtractionException('Could not reach the statement extraction API: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $detail = $response->json('detail');
            $message = is_array($detail) ? json_encode($detail) : (string) ($detail ?? $response->body());
            throw new StatementExtractionException("Statement extraction API returned HTTP {$response->status()}: {$message}");
        }

        $body = (array) $response->json();
        $transactions = $body['transactions'] ?? null;

        if (!is_array($transactions)) {
            throw new StatementExtractionException('Unexpected response shape from the statement extraction API — expected a "transactions" array.');
        }

        $lines = array_map(function (array $row): array {
            $credit = (float) ($row['credit'] ?? 0);
            $debit = (float) ($row['debit'] ?? 0);

            return [
                'date' => (string) ($row['date'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                // API reports debit/credit as separate nullable fields; our
                // convention is one signed amount (positive = inflow/credit).
                'amount' => $credit > 0 ? $credit : -$debit,
                'suggested_ledger' => $row['ledger'] ?? null,
            ];
        }, $transactions);

        $summary = (array) ($body['summary'] ?? []);

        return [
            'lines' => $lines,
            'opening_balance' => isset($summary['opening_balance']) ? (float) $summary['opening_balance'] : null,
            'closing_balance' => isset($summary['closing_balance']) ? (float) $summary['closing_balance'] : null,
            'account_info' => (array) ($body['account_info'] ?? []),
            'raw' => $body,
        ];
    }
}
