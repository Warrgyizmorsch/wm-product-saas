<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\BankStatementUpload;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionException;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionProvider;
use App\Domains\Accounting\Services\StatementExtraction\SupportsStatementPassword;
use App\Domains\Accounting\Models\BankStatementLayout;
use App\Domains\Accounting\Services\StatementExtraction\StatementNeedsMappingException;
use App\Domains\Accounting\Support\BankStatementLayoutDetector;
use App\Domains\Accounting\Support\BankStatementRowParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Lifecycle of a bank reconciliation: start → import the bank statement →
 * match (see BankReconciliationMatcher) → complete once the Bank
 * Reconciliation Statement agrees (see BankReconciliationStatementService) →
 * optionally reopen.
 */
class BankReconciliationService
{
    public const PROVIDER_FILE_IMPORT = 'file_import';

    public function __construct(
        private readonly StatementExtractionProvider $extractor,
        private readonly BankReconciliationMatcher $matcher,
        private readonly BankReconciliationStatementService $statement,
        private readonly BankStatementRowParser $parser,
        private readonly BankStatementLayoutDetector $detector,
        private readonly BankReconciliationRuleService $rules,
    ) {
    }

    /**
     * @param array{tenant_id?: int, company_id?: int, branch_id?: int, chart_of_account_id: int, statement_date: string, statement_from_date?: ?string, opening_balance?: float|string|null, closing_balance?: float|string|null, notes?: ?string} $data
     */
    public function start(array $data): BankReconciliation
    {
        $tenantId = $data['tenant_id'] ?? tenant_id();

        $account = ChartOfAccount::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('id', $data['chart_of_account_id'])
            ->first();

        if ($account === null || ! $account->is_cash_or_bank) {
            throw new InvalidArgumentException('Reconciliation can only be started against a cash or bank account.');
        }

        $open = BankReconciliation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $account->id)
            ->where('status', BankReconciliation::STATUS_IN_PROGRESS)
            ->first();

        if ($open !== null) {
            throw new InvalidArgumentException("{$account->name} already has a reconciliation in progress (statement date {$open->statement_date->format('d M Y')}). Complete or continue that one first.");
        }

        $statementDate = Carbon::parse($data['statement_date'])->startOfDay();
        $previous = $this->latestCompleted($tenantId, $account->id);

        if ($previous !== null && $statementDate->lte($previous->statement_date)) {
            throw new InvalidArgumentException("{$account->name} is already reconciled up to {$previous->statement_date->format('d M Y')}. The new statement date must be after that.");
        }

        $fromDate = ! empty($data['statement_from_date'])
            ? Carbon::parse($data['statement_from_date'])->startOfDay()
            : $previous?->statement_date?->copy()->addDay();

        if ($fromDate !== null && $fromDate->gt($statementDate)) {
            throw new InvalidArgumentException('The statement period start must be on or before the statement date.');
        }

        // The bank's opening balance is the previous statement's closing balance.
        $opening = $this->nullableAmount($data['opening_balance'] ?? null)
            ?? ($previous ? (float) $previous->closing_balance : 0.0);

        return BankReconciliation::create([
            'tenant_id' => $tenantId,
            'company_id' => $data['company_id'] ?? company_id(),
            'branch_id' => $data['branch_id'] ?? branch_id(),
            'chart_of_account_id' => $account->id,
            'statement_from_date' => $fromDate?->toDateString(),
            'statement_date' => $statementDate->toDateString(),
            'opening_balance' => $opening,
            'closing_balance' => $this->nullableAmount($data['closing_balance'] ?? null) ?? 0,
            'notes' => $data['notes'] ?? null,
            'status' => BankReconciliation::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Change the statement period or balances while the reconciliation is open.
     *
     * @param array{statement_from_date?: ?string, statement_date: string, opening_balance: float|string, closing_balance: float|string, notes?: ?string} $data
     */
    public function updateDetails(BankReconciliation $reconciliation, array $data): BankReconciliation
    {
        $this->assertInProgress($reconciliation);

        $statementDate = Carbon::parse($data['statement_date'])->startOfDay();
        $fromDate = ! empty($data['statement_from_date']) ? Carbon::parse($data['statement_from_date'])->startOfDay() : null;

        if ($fromDate !== null && $fromDate->gt($statementDate)) {
            throw new InvalidArgumentException('The statement period start must be on or before the statement date.');
        }

        $previous = $this->latestCompleted($reconciliation->tenant_id, $reconciliation->chart_of_account_id, $reconciliation->id);
        if ($previous !== null && $statementDate->lte($previous->statement_date)) {
            throw new InvalidArgumentException("This account is already reconciled up to {$previous->statement_date->format('d M Y')}. The statement date must be after that.");
        }

        $outside = $reconciliation->statementLines()
            ->where(fn ($q) => $q->whereDate('transaction_date', '>', $statementDate)
                ->when($fromDate, fn ($q) => $q->orWhereDate('transaction_date', '<', $fromDate)))
            ->count();

        if ($outside > 0) {
            throw new InvalidArgumentException("{$outside} statement line(s) fall outside the new period. Delete them first or pick a period that covers them.");
        }

        $matchedAfter = $reconciliation->matches()
            ->whereHas('journalEntry.journal', fn ($q) => $q->withoutGlobalScope('tenant')->whereDate('journal_date', '>', $statementDate))
            ->count();

        if ($matchedAfter > 0) {
            throw new InvalidArgumentException("{$matchedAfter} matched ledger entr(y/ies) are dated after the new statement date. Unmatch them first.");
        }

        $reconciliation->update([
            'statement_from_date' => $fromDate?->toDateString(),
            'statement_date' => $statementDate->toDateString(),
            'opening_balance' => (float) $data['opening_balance'],
            'closing_balance' => (float) $data['closing_balance'],
            'notes' => $data['notes'] ?? $reconciliation->notes,
        ]);

        return $reconciliation->fresh();
    }

    /**
     * Backwards-compatible entry point: imports a CSV/Excel statement and
     * returns how many lines were added.
     */
    public function importStatementLines(BankReconciliation $reconciliation, UploadedFile $file, ?int $importedBy = null): int
    {
        return $this->importStatement($reconciliation, $file, $importedBy)['imported'];
    }

    /**
     * Imports a CSV/XLS/XLSX bank statement exactly as the bank exported it.
     * The header row and columns are found automatically (or taken from the
     * layout saved for this bank account); when they can't be, the upload is
     * kept with status "needs_mapping" and StatementNeedsMappingException
     * sends the user to the column-mapping screen.
     *
     * @return array{imported: int, duplicates: int, out_of_period: int, invalid: int, errors: list<string>, upload: BankStatementUpload, balance_mismatches: int}
     *
     * @throws StatementNeedsMappingException
     */
    public function importStatement(BankReconciliation $reconciliation, UploadedFile $file, ?int $importedBy = null): array
    {
        $this->assertInProgress($reconciliation);

        $rows = $this->detector->readRows((string) $file->getRealPath());

        if ($rows === []) {
            throw new InvalidArgumentException('The file is empty or could not be read as a spreadsheet.');
        }

        $upload = $this->storeUpload($reconciliation, $file, self::PROVIDER_FILE_IMPORT);
        $layout = $this->layoutFor($reconciliation, $rows);

        if ($layout === null) {
            $upload->update([
                'status' => BankStatementUpload::STATUS_NEEDS_MAPPING,
                'raw_response' => ['preview' => $this->detector->preview($rows)],
            ]);

            throw new StatementNeedsMappingException($upload->fresh());
        }

        return $this->importUsingLayout($reconciliation, $upload, $rows, $layout, $importedBy);
    }

    /**
     * Finish an upload that needed manual mapping: the user picked the header
     * row and which column holds what. The mapping is saved for the bank
     * account so the next file in this format imports straight away.
     *
     * @param array<string, int|string|null> $columnMap canonical field => zero-based column index
     * @return array{imported: int, duplicates: int, out_of_period: int, invalid: int, errors: list<string>, upload: BankStatementUpload, balance_mismatches: int}
     */
    public function applyMapping(BankReconciliation $reconciliation, int $uploadId, int $headerRow, array $columnMap, ?int $userId = null): array
    {
        $this->assertInProgress($reconciliation);

        $upload = $reconciliation->statementUploads()->whereKey($uploadId)->first();
        if ($upload === null || $upload->status !== BankStatementUpload::STATUS_NEEDS_MAPPING) {
            throw new InvalidArgumentException('This upload is not waiting for a column mapping.');
        }

        $map = collect($columnMap)
            ->filter(fn ($index) => $index !== null && $index !== '')
            ->map(fn ($index) => (int) $index)
            ->all();

        if (! isset($map['date']) || ! (isset($map['amount']) || isset($map['withdrawal']) || isset($map['deposit']))) {
            throw new InvalidArgumentException('Choose at least the Date column and either an Amount column or the Withdrawal/Deposit columns.');
        }

        $rows = $this->detector->readRows(Storage::disk($upload->disk)->path($upload->path));

        if (! isset($rows[$headerRow])) {
            throw new InvalidArgumentException('The chosen header row is outside the file.');
        }

        $layout = [
            'header_row' => $headerRow,
            'column_map' => $map,
            'header_cells' => array_map(fn ($cell) => trim((string) $cell), $rows[$headerRow]),
            'source' => BankStatementLayout::SOURCE_MANUAL,
        ];

        $this->rememberLayout($reconciliation, $rows, $layout, BankStatementLayout::SOURCE_MANUAL, $userId);

        return $this->importUsingLayout($reconciliation, $upload, $rows, $layout, $userId);
    }

    /**
     * @param list<list<mixed>> $rows
     * @param array{header_row: int, column_map: array<string, int>, header_cells?: list<string>} $layout
     * @return array{imported: int, duplicates: int, out_of_period: int, invalid: int, errors: list<string>, upload: BankStatementUpload, balance_mismatches: int}
     */
    private function importUsingLayout(BankReconciliation $reconciliation, BankStatementUpload $upload, array $rows, array $layout, ?int $importedBy): array
    {
        $parsed = [];
        $errors = [];
        $invalid = 0;

        foreach ($this->detector->dataRows($rows, $layout) as $dataRow) {
            $result = $this->parser->parse($dataRow['values']);

            if (! $result['ok']) {
                $invalid++;
                $errors[] = 'Row ' . $dataRow['row'] . ': ' . $result['error'];
                continue;
            }

            $parsed[] = $result;
        }

        try {
            if ($parsed === []) {
                throw new InvalidArgumentException('No statement lines could be read'
                    . ($errors ? ': ' . implode('; ', array_slice($errors, 0, 5)) : '')
                    . '. Expected columns: Date, Narration/Description, Reference, and either Amount or Withdrawal/Deposit.');
            }

            $result = $this->persistLines($reconciliation, $upload, $parsed);
        } catch (Throwable $e) {
            // Nothing was imported (duplicate file, wrong period…): don't leave
            // an empty upload and its stored file behind.
            Storage::disk($upload->disk)->delete($upload->path);
            $upload->delete();

            throw $e;
        }

        // Remember a detected layout; saved and just-mapped ones are stored already.
        if (! isset($layout['source'])) {
            $this->rememberLayout($reconciliation, $rows, $layout, BankStatementLayout::SOURCE_DETECTED, $importedBy);
        }

        $balances = $this->statementBalances($parsed);
        $this->prefillBalances($reconciliation, $balances['opening'], $balances['closing']);

        $upload->update([
            'status' => BankStatementUpload::STATUS_COMPLETED,
            'extracted_count' => $result['imported'],
            'raw_response' => [
                'rows_read' => count($parsed) + $invalid,
                'header_row' => $layout['header_row'] + 1,
                'columns' => $layout['column_map'],
                'duplicates' => $result['duplicates'],
                'out_of_period' => $result['out_of_period'],
                'invalid' => $invalid,
                'balance_mismatches' => $balances['mismatches'],
                'statement_opening' => $balances['opening'],
                'statement_closing' => $balances['closing'],
                'errors' => array_slice($errors, 0, 50),
            ],
            'extracted_by' => $importedBy,
            'extracted_at' => now(),
        ]);

        return $result + ['invalid' => $invalid, 'errors' => $errors, 'upload' => $upload->fresh(), 'balance_mismatches' => $balances['mismatches']];
    }

    /**
     * The saved layout for this bank account when the file's header matches
     * one, otherwise automatic detection.
     *
     * @param list<list<mixed>> $rows
     * @return array{header_row: int, column_map: array<string, int>, header_cells: list<string>, source?: string}|null
     */
    private function layoutFor(BankReconciliation $reconciliation, array $rows): ?array
    {
        $saved = BankStatementLayout::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
            ->orderByDesc('last_used_at')
            ->get();

        foreach ($saved as $layout) {
            $header = $rows[$layout->header_row] ?? null;

            if ($header !== null && $this->detector->signature($header) === $layout->signature) {
                $layout->update(['last_used_at' => now()]);

                return [
                    'header_row' => $layout->header_row,
                    'column_map' => $layout->column_map,
                    'header_cells' => $layout->header_cells ?? [],
                    'source' => 'saved',
                ];
            }
        }

        return $this->detector->detect($rows);
    }

    /**
     * @param list<list<mixed>> $rows
     * @param array{header_row: int, column_map: array<string, int>} $layout
     */
    private function rememberLayout(BankReconciliation $reconciliation, array $rows, array $layout, string $source, ?int $userId): void
    {
        $header = $rows[$layout['header_row']] ?? [];

        BankStatementLayout::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $reconciliation->tenant_id,
                'chart_of_account_id' => $reconciliation->chart_of_account_id,
                'signature' => $this->detector->signature($header),
            ],
            [
                'header_row' => $layout['header_row'],
                'column_map' => $layout['column_map'],
                'header_cells' => array_map(fn ($cell) => trim((string) $cell), $header),
                'source' => $source,
                'created_by' => $userId,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Opening and closing balance from the running balance column, in date
     * order (some banks list newest first), plus how many rows don't follow
     * on from the previous row's balance — a sign of a misread row.
     *
     * @param list<array{date: string, amount: float, balance?: ?float}> $rows
     * @return array{opening: ?float, closing: ?float, mismatches: int}
     */
    private function statementBalances(array $rows): array
    {
        $withBalance = array_values(array_filter($rows, fn ($row) => isset($row['balance'])));

        if ($withBalance === []) {
            return ['opening' => null, 'closing' => null, 'mismatches' => 0];
        }

        if ($withBalance[0]['date'] > $withBalance[count($withBalance) - 1]['date']) {
            $withBalance = array_reverse($withBalance);
        }

        $mismatches = 0;
        for ($i = 1, $n = count($withBalance); $i < $n; $i++) {
            $expected = (int) round(($withBalance[$i - 1]['balance'] + $withBalance[$i]['amount']) * 100);
            if ($expected !== (int) round($withBalance[$i]['balance'] * 100)) {
                $mismatches++;
            }
        }

        return [
            'opening' => round($withBalance[0]['balance'] - $withBalance[0]['amount'], 2),
            'closing' => round($withBalance[count($withBalance) - 1]['balance'], 2),
            'mismatches' => $mismatches,
        ];
    }

    /**
     * Fill the reconciliation's balances from the statement only while they
     * are still at their untouched default — never overwrite what the user typed.
     */
    private function prefillBalances(BankReconciliation $reconciliation, ?float $opening, ?float $closing): void
    {
        if ((float) $reconciliation->opening_balance !== 0.0 || (float) $reconciliation->closing_balance !== 0.0) {
            return;
        }

        $update = array_filter(['opening_balance' => $opening, 'closing_balance' => $closing], fn ($value) => $value !== null);

        if ($update !== []) {
            $reconciliation->update($update);
        }
    }

    /**
     * Stores an uploaded PDF statement, sends it to the configured
     * StatementExtractionProvider (with the PDF password, if any) and turns
     * the returned rows into statement lines. The raw file and response are
     * kept for audit.
     */
    public function extractStatementLines(BankReconciliation $reconciliation, UploadedFile $file, ?int $extractedBy = null, ?string $password = null): BankStatementUpload
    {
        $this->assertInProgress($reconciliation);

        $extractor = $this->extractor;
        if ($password !== null && $password !== '' && $extractor instanceof SupportsStatementPassword) {
            $extractor = $extractor->withPassword($password);
        }

        $upload = $this->storeUpload($reconciliation, $file, class_basename($this->extractor));

        try {
            // Resolve through the disk: the 'local' root is storage/app/private since Laravel 11.
            $result = $extractor->extract(Storage::disk($upload->disk)->path($upload->path), (string) $file->getMimeType());

            $rows = [];
            $invalid = 0;
            foreach ($result['lines'] ?? [] as $row) {
                $date = $this->parser->parseDate($row['date'] ?? null);
                $amount = isset($row['amount']) ? round((float) $row['amount'], 2) : 0.0;

                if ($date === null || $amount === 0.0) {
                    $invalid++;
                    continue;
                }

                $rows[] = [
                    'date' => $date->toDateString(),
                    'description' => isset($row['description']) ? mb_substr((string) $row['description'], 0, 255) : null,
                    'reference' => isset($row['reference']) && $row['reference'] !== '' ? mb_substr((string) $row['reference'], 0, 100) : null,
                    'suggested_ledger' => $row['suggested_ledger'] ?? null,
                    'amount' => $amount,
                ];
            }

            $persisted = $this->persistLines($reconciliation, $upload, $rows);

            $upload->update([
                'status' => BankStatementUpload::STATUS_COMPLETED,
                'extracted_count' => $persisted['imported'],
                'raw_response' => ($result['raw'] ?? $result) + ['_import' => [
                    'duplicates' => $persisted['duplicates'],
                    'out_of_period' => $persisted['out_of_period'],
                    'invalid' => $invalid,
                ]],
                'extracted_by' => $extractedBy,
                'extracted_at' => now(),
            ]);

            $this->prefillBalances($reconciliation, $result['opening_balance'] ?? null, $result['closing_balance'] ?? null);
        } catch (StatementExtractionException $e) {
            $upload->update(['status' => BankStatementUpload::STATUS_FAILED, 'error_message' => $e->getMessage()]);

            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        } catch (InvalidArgumentException $e) {
            $upload->update(['status' => BankStatementUpload::STATUS_FAILED, 'error_message' => $e->getMessage()]);

            throw $e;
        } catch (Throwable $e) {
            // Never leave an upload stuck on "pending".
            report($e);
            $upload->update(['status' => BankStatementUpload::STATUS_FAILED, 'error_message' => 'Could not read the extracted statement: ' . $e->getMessage()]);

            throw new InvalidArgumentException('Could not read the extracted statement: ' . $e->getMessage(), 0, $e);
        }

        return $upload->fresh('statementLines');
    }

    /**
     * Delete unmatched statement lines (a wrong or duplicate import).
     *
     * @param list<int> $lineIds
     */
    public function deleteLines(BankReconciliation $reconciliation, array $lineIds): int
    {
        $this->assertInProgress($reconciliation);

        $lines = $reconciliation->statementLines()->whereIn('id', $lineIds)->get();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('Select the statement lines to delete.');
        }

        if ($lines->contains('is_matched', true)) {
            throw new InvalidArgumentException('Matched lines cannot be deleted. Unmatch them first.');
        }

        return $reconciliation->statementLines()->whereIn('id', $lines->pluck('id'))->delete();
    }

    /**
     * Remove one import: its unmatched lines, the stored file and the upload record.
     */
    public function deleteUpload(BankReconciliation $reconciliation, int $uploadId): int
    {
        $this->assertInProgress($reconciliation);

        $upload = $reconciliation->statementUploads()->whereKey($uploadId)->first();

        if ($upload === null) {
            throw new InvalidArgumentException('Upload not found on this reconciliation.');
        }

        if ($upload->statementLines()->where('is_matched', true)->exists()) {
            throw new InvalidArgumentException('Some lines from this upload are matched. Unmatch them before removing the upload.');
        }

        return DB::transaction(function () use ($upload) {
            $deleted = $upload->statementLines()->delete();

            if ($upload->path && Storage::disk($upload->disk)->exists($upload->path)) {
                Storage::disk($upload->disk)->delete($upload->path);
            }

            $upload->delete();

            return $deleted;
        });
    }

    public function autoMatch(BankReconciliation $reconciliation, ?int $matchedBy = null): int
    {
        return $this->matcher->autoMatch($reconciliation, $matchedBy);
    }

    public function manualMatch(BankReconciliation $reconciliation, int $statementLineId, int $journalEntryId, ?int $matchedBy = null): void
    {
        $this->matcher->match($reconciliation, $statementLineId, [$journalEntryId], null, null, $matchedBy);
    }

    /**
     * Post a voucher for a bank-only line and match it. With a single ledger
     * the choice is remembered as a narration rule for next time.
     *
     * @param list<array{account_id: int, amount: float, narration?: ?string}>|null $splits
     */
    public function createAndMatch(
        BankReconciliation $reconciliation,
        int $statementLineId,
        ?int $chartOfAccountId,
        ?int $postedBy = null,
        ?string $description = null,
        ?string $partyName = null,
        ?array $splits = null,
    ): void {
        $this->matcher->postAdjustment($reconciliation, $statementLineId, $chartOfAccountId, $description, $postedBy, $partyName, $splits);

        $accountIds = $splits ? array_unique(array_map(fn ($split) => (int) $split['account_id'], $splits)) : [$chartOfAccountId];

        if (count($accountIds) === 1 && $accountIds[0]) {
            $line = $reconciliation->statementLines()->find($statementLineId);
            $this->rules->learnFrom($reconciliation, $line, (int) $accountIds[0], $partyName, $postedBy);
        }
    }

    /**
     * Best-effort match of a provider-suggested ledger name (e.g. "Paytm (Cr)")
     * to a postable Chart of Account, for defaulting the adjustment picker —
     * the user confirms or changes it before anything posts.
     */
    public function resolveLedgerByName(int $tenantId, ?string $name): ?ChartOfAccount
    {
        if (empty($name)) {
            return null;
        }

        $clean = trim(preg_replace('/\s*\((Dr|Cr)\)\s*$/i', '', $name));

        if ($clean === '') {
            return null;
        }

        return ChartOfAccount::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('chart_of_accounts as children')->whereColumn('children.parent_id', 'chart_of_accounts.id'))
            ->where(function ($q) use ($clean) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($clean)])
                    ->orWhere('name', 'like', "%{$clean}%");
            })
            ->orderByRaw('LOWER(name) = ? DESC', [strtolower($clean)])
            ->first();
    }

    /**
     * Locks the reconciliation once every statement line is matched, the
     * statement adds up (opening + lines = closing) and the Bank
     * Reconciliation Statement agrees with the bank's closing balance. The BRS
     * at this moment is stored with the reconciliation for audit.
     */
    public function complete(BankReconciliation $reconciliation, int $completedBy): BankReconciliation
    {
        $this->assertInProgress($reconciliation);

        if ($reconciliation->statementLines()->unmatched()->exists()) {
            throw new InvalidArgumentException('All statement lines must be matched before completing the reconciliation. Match them to ledger entries or post the bank charges/interest as adjustments.');
        }

        $brs = $this->statement->build($reconciliation);
        $check = $brs['statement_check'];

        if ($check['difference'] !== 0.0) {
            throw new InvalidArgumentException(
                'Statement does not balance: opening balance plus statement lines totals ' . number_format($check['expected_closing'], 2)
                . ', expected closing balance ' . number_format($check['closing'], 2) . '. Check the balances or look for missing/duplicate lines.'
            );
        }

        if ($brs['difference'] !== 0.0) {
            throw new InvalidArgumentException(
                'The bank reconciliation statement does not agree: balance as per books adjusted for outstanding items is '
                . number_format($brs['computed_bank_balance'], 2) . ' but the bank statement shows ' . number_format($brs['statement_balance'], 2)
                . ' (difference ' . number_format($brs['difference'], 2) . '). Usually the opening balance or an earlier period needs checking.'
            );
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'book_balance' => $brs['book_balance'],
            'brs_snapshot' => $this->snapshot($brs),
            'completed_by' => $completedBy,
            'completed_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    /**
     * Reopen the latest completed reconciliation of an account so matches can
     * be corrected. Older ones stay locked: a later statement has been
     * reconciled on top of them.
     */
    public function reopen(BankReconciliation $reconciliation, int $reopenedBy): BankReconciliation
    {
        if (! $reconciliation->isCompleted()) {
            throw new InvalidArgumentException('Only a completed reconciliation can be reopened.');
        }

        $later = BankReconciliation::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
            ->whereKeyNot($reconciliation->id)
            ->where(fn ($q) => $q->where('status', BankReconciliation::STATUS_IN_PROGRESS)
                ->orWhereDate('statement_date', '>', $reconciliation->statement_date))
            ->exists();

        if ($later) {
            throw new InvalidArgumentException('Only the latest reconciliation of this account can be reopened, and only when no other reconciliation is in progress.');
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_IN_PROGRESS,
            'completed_by' => null,
            'completed_at' => null,
            'reopened_by' => $reopenedBy,
            'reopened_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    public function latestCompleted(int $tenantId, int $accountId, ?int $exceptId = null): ?BankReconciliation
    {
        return BankReconciliation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $accountId)
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Saves validated rows, skipping ones outside the statement period and
     * ones already imported for this bank account. Two identical rows in the
     * same file (two ATM withdrawals of the same amount on one day) are both
     * kept: the fingerprint includes the row's occurrence number.
     *
     * @param list<array{date: string, description: ?string, reference: ?string, amount: float, suggested_ledger?: ?string}> $rows
     * @return array{imported: int, duplicates: int, out_of_period: int}
     */
    private function persistLines(BankReconciliation $reconciliation, BankStatementUpload $upload, array $rows): array
    {
        $from = $reconciliation->statement_from_date;
        $to = $reconciliation->statement_date;

        $occurrences = [];
        $prepared = [];
        $outOfPeriod = 0;

        foreach ($rows as $row) {
            $date = Carbon::parse($row['date']);

            if ($date->gt($to) || ($from !== null && $date->lt($from))) {
                $outOfPeriod++;
                continue;
            }

            $base = implode('|', [
                $reconciliation->chart_of_account_id,
                $date->toDateString(),
                number_format($row['amount'], 2, '.', ''),
                strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['description'] ?? '')))),
                strtolower(trim((string) ($row['reference'] ?? ''))),
            ]);
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
            $row['import_hash'] = hash('sha256', $base . '|' . $occurrences[$base]);
            $prepared[] = $row;
        }

        $existing = BankStatementLine::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->whereIn('import_hash', array_column($prepared, 'import_hash'))
            ->pluck('import_hash')
            ->flip();

        $toInsert = array_values(array_filter($prepared, fn ($row) => ! isset($existing[$row['import_hash']])));
        $duplicates = count($prepared) - count($toInsert);

        if ($prepared !== [] && $toInsert === [] && $duplicates > 0) {
            throw new InvalidArgumentException("All {$duplicates} line(s) in this file were already imported for this bank account.");
        }

        if ($toInsert === [] && $outOfPeriod > 0) {
            throw new InvalidArgumentException("None of the {$outOfPeriod} line(s) fall inside the statement period"
                . ($from ? ' (' . $from->format('d M Y') . ' – ' . $to->format('d M Y') . ')' : ' (up to ' . $to->format('d M Y') . ')')
                . '. Check the statement date or the file.');
        }

        DB::transaction(function () use ($reconciliation, $upload, $toInsert) {
            foreach ($toInsert as $row) {
                BankStatementLine::create([
                    'tenant_id' => $reconciliation->tenant_id,
                    'bank_reconciliation_id' => $reconciliation->id,
                    'bank_statement_upload_id' => $upload->id,
                    'transaction_date' => $row['date'],
                    'description' => $row['description'] ?? null,
                    'reference' => $row['reference'] ?? null,
                    'suggested_ledger' => $row['suggested_ledger'] ?? null,
                    'amount' => $row['amount'],
                    'balance' => $row['balance'] ?? null,
                    'import_hash' => $row['import_hash'],
                ]);
            }
        });

        return ['imported' => count($toInsert), 'duplicates' => $duplicates, 'out_of_period' => $outOfPeriod];
    }

    private function storeUpload(BankReconciliation $reconciliation, UploadedFile $file, string $provider): BankStatementUpload
    {
        $path = $file->store(
            "bank-statements/tenant_{$reconciliation->tenant_id}/reconciliation_{$reconciliation->id}",
            'local'
        );

        return BankStatementUpload::create([
            'tenant_id' => $reconciliation->tenant_id,
            'company_id' => $reconciliation->company_id,
            'branch_id' => $reconciliation->branch_id,
            'bank_reconciliation_id' => $reconciliation->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            'status' => BankStatementUpload::STATUS_PENDING,
            'provider' => $provider,
        ]);
    }

    /**
     * @param array<string, mixed> $brs
     * @return array<string, mixed>
     */
    private function snapshot(array $brs): array
    {
        $snapshot = $brs;
        unset($snapshot['account']);
        $snapshot['statement_date'] = $brs['statement_date']->toDateString();

        foreach (['cheques_not_presented', 'deposits_not_credited', 'bank_credits_not_in_books', 'bank_debits_not_in_books'] as $key) {
            $snapshot[$key] = $brs[$key]->values()->all();
        }

        $snapshot['computed_at'] = now()->toIso8601String();

        return $snapshot;
    }

    private function nullableAmount(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : round((float) $value, 2);
    }

    private function assertInProgress(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->isCompleted()) {
            throw new InvalidArgumentException('This reconciliation is already completed and locked.');
        }
    }
}
