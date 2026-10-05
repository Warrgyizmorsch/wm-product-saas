<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementUpload;
use App\Domains\Accounting\Services\StatementExtraction\StatementNeedsMappingException;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationMatcher;
use App\Domains\Accounting\Services\BankReconciliationPartySettler;
use App\Domains\Accounting\Services\BankReconciliationRuleService;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\BankReconciliationStatementService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Exports\AccountingReportExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BankReconciliationController extends Controller
{
    private const SORTABLE = ['statement_date', 'opening_balance', 'closing_balance', 'status'];

    public function __construct(
        private readonly BankReconciliationService $reconciliations,
        private readonly BankReconciliationMatcher $matcher,
        private readonly BankReconciliationStatementService $statement,
        private readonly ChartOfAccountsService $accounts,
        private readonly BankReconciliationRuleService $rules,
        private readonly BankReconciliationPartySettler $settler,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'statement_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $reconciliations = BankReconciliation::with('chartOfAccount')
            ->withCount([
                'statementLines',
                'statementLines as matched_lines_count' => fn ($q) => $q->where('is_matched', true),
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas(
                'chartOfAccount',
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $canCreate = auth()->user()?->can('create', BankReconciliation::class) ?? false;

        return view('modules.accounting.bank-reconciliation.index', [
            'reconciliations' => $reconciliations,
            'filters' => $filters,
            'canCreate' => $canCreate,
            'cashBankAccounts' => $canCreate ? $this->cashBankAccounts() : collect(),
            'lastReconciled' => $canCreate ? $this->lastReconciledByAccount() : [],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BankReconciliation::class);

        return view('modules.accounting.bank-reconciliation.create', [
            'cashBankAccounts' => $this->cashBankAccounts(),
            'lastReconciled' => $this->lastReconciledByAccount(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'statement_from_date' => ['nullable', 'date'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['nullable', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $reconciliation = $this->reconciliations->start($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['chart_of_account_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.bank-reconciliation.show', $reconciliation)
            ->with('success', 'Bank reconciliation started. Upload the bank statement for this period to begin matching.');
    }

    public function show(BankReconciliation $reconciliation): View
    {
        $this->authorize('view', $reconciliation);

        $reconciliation->load([
            'chartOfAccount',
            'completedBy',
            'reopenedBy',
            'statementUploads' => fn ($q) => $q->latest(),
            'statementLines' => fn ($q) => $q->orderBy('transaction_date')->orderBy('id'),
            'statementLines.matches.journalEntry.journal',
        ]);

        $brs = $this->statement->build($reconciliation);
        $candidates = $reconciliation->isCompleted() ? collect() : $this->matcher->candidates($reconciliation);
        $suggestions = $this->matcher->suggestions($reconciliation->statementLines, $candidates);

        $postableAccounts = $this->postableAccounts($reconciliation);

        // Default ledger/party for posting a line: a narration rule first, then
        // the PDF parser's ledger guess.
        $ruleSuggestions = $reconciliation->isCompleted()
            ? []
            : $this->rules->suggestions($reconciliation->statementLines, $this->rules->rulesFor($reconciliation));

        $suggestedAccounts = $reconciliation->statementLines
            ->reject(fn ($line) => $line->is_matched || ! $line->suggested_ledger)
            ->mapWithKeys(fn ($line) => [
                $line->id => $this->reconciliations->resolveLedgerByName($reconciliation->tenant_id, $line->suggested_ledger)?->id,
            ])
            ->filter()
            ->replace(collect($ruleSuggestions)->map(fn ($rule) => $rule->target_account_id));

        $user = auth()->user();
        $canUpdate = ! $reconciliation->isCompleted() && ($user?->can('update', $reconciliation) ?? false);

        return view('modules.accounting.bank-reconciliation.show', [
            'reconciliation' => $reconciliation,
            'brs' => $brs,
            'candidates' => $candidates,
            'candidateMap' => $candidates->keyBy('id'),
            'candidatesJson' => $this->candidatesForJs($candidates),
            'suggestions' => $suggestions,
            'suggestedAccounts' => $suggestedAccounts,
            'ruleSuggestions' => $ruleSuggestions,
            'customers' => $canUpdate ? $this->settler->parties(BankReconciliationPartySettler::CUSTOMER) : collect(),
            'vendors' => $canUpdate ? $this->settler->parties(BankReconciliationPartySettler::VENDOR) : collect(),
            'postableAccounts' => $postableAccounts,
            'canUpdate' => $canUpdate,
            'canComplete' => ! $reconciliation->isCompleted() && ($user?->can('complete', $reconciliation) ?? false),
            'canReopen' => $reconciliation->isCompleted() && ($user?->can('reopen', $reconciliation) ?? false),
            'readiness' => $this->readiness($brs),
        ]);
    }

    public function updateDetails(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'statement_from_date' => ['nullable', 'date'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $this->reconciliations->updateDetails($reconciliation, $validated), 'Statement details updated.');
    }

    public function downloadTemplate(): Response
    {
        $this->authorize('create', BankReconciliation::class);

        $rows = [
            ['Date', 'Description', 'Reference', 'Withdrawal', 'Deposit'],
            ['02/09/2026', 'NEFT Credit - Customer', 'UTR2609021234', '', '45000.00'],
            ['09/09/2026', 'Cheque paid - Vendor', '000123', '12500.00', ''],
            ['30/09/2026', 'Bank charges', '', '450.00', ''],
        ];

        $csv = implode("\n", array_map(
            fn (array $row) => implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', $value) . '"', $row)),
            $rows
        ));

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bank-statement-template.csv"',
        ]);
    }

    public function import(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv,txt,pdf'],
            'password' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];

        try {
            if ($file->getClientMimeType() === 'application/pdf' || strtolower($file->getClientOriginalExtension()) === 'pdf') {
                $upload = $this->reconciliations->extractStatementLines($reconciliation, $file, auth()->id(), $validated['password'] ?? null);
                $info = $upload->raw_response['_import'] ?? [];

                return back()->with('success', "Extracted {$upload->extracted_count} statement line(s) from the PDF." . $this->skippedNote($info['duplicates'] ?? 0, $info['out_of_period'] ?? 0, $info['invalid'] ?? 0));
            }

            $result = $this->reconciliations->importStatement($reconciliation, $file, auth()->id());
        } catch (StatementNeedsMappingException $e) {
            return redirect()->route('accounting.bank-reconciliation.mapping', [$reconciliation, $e->upload->id])
                ->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $this->importMessage($result))->with('import_errors', array_slice($result['errors'], 0, 10));
    }

    /**
     * Column-mapping screen for a statement whose layout wasn't recognised.
     */
    public function mapping(BankReconciliation $reconciliation, int $upload): View|RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $record = $reconciliation->statementUploads()->whereKey($upload)->firstOrFail();

        if ($record->status !== BankStatementUpload::STATUS_NEEDS_MAPPING) {
            return redirect()->route('accounting.bank-reconciliation.show', $reconciliation);
        }

        $preview = $record->raw_response['preview'] ?? [];

        return view('modules.accounting.bank-reconciliation.mapping', [
            'reconciliation' => $reconciliation->load('chartOfAccount'),
            'upload' => $record,
            'preview' => $preview,
            'columnCount' => collect($preview)->map(fn ($row) => count($row))->max() ?? 0,
            'fields' => [
                'date' => 'Transaction date *',
                'description' => 'Narration / description',
                'reference' => 'Cheque / reference no.',
                'withdrawal' => 'Withdrawal (debit)',
                'deposit' => 'Deposit (credit)',
                'amount' => 'Single amount column',
                'drcr' => 'Dr/Cr indicator',
                'balance' => 'Running balance',
            ],
        ]);
    }

    public function applyMapping(Request $request, BankReconciliation $reconciliation, int $upload): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'header_row' => ['required', 'integer', 'min:0'],
            'columns' => ['required', 'array'],
            'columns.*' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->reconciliations->applyMapping($reconciliation, $upload, (int) $validated['header_row'], $validated['columns'], auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('accounting.bank-reconciliation.show', $reconciliation)
            ->with('success', $this->importMessage($result) . ' The column layout is saved for this bank account.')
            ->with('import_errors', array_slice($result['errors'], 0, 10));
    }

    /**
     * @param array{imported: int, duplicates: int, out_of_period: int, invalid: int, balance_mismatches?: int} $result
     */
    private function importMessage(array $result): string
    {
        $message = "Imported {$result['imported']} statement line(s)." . $this->skippedNote($result['duplicates'], $result['out_of_period'], $result['invalid']);

        if (! empty($result['balance_mismatches'])) {
            $message .= " Warning: {$result['balance_mismatches']} row(s) don't follow on from the previous row's running balance — check the file for misread amounts.";
        }

        return $message;
    }

    public function autoMatch(BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        try {
            $count = $this->reconciliations->autoMatch($reconciliation, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $count > 0
            ? "Auto-matched {$count} line(s). Review the remaining lines — pick a suggestion, match several entries, or post an adjustment."
            : 'No new lines could be matched automatically. Use the suggestions or Match… on each line.');
    }

    public function match(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'statement_line_id' => ['required', 'integer'],
            'journal_entry_id' => ['nullable', 'integer', 'required_without:journal_entry_ids'],
            'journal_entry_ids' => ['nullable', 'array', 'required_without:journal_entry_id'],
            'journal_entry_ids.*' => ['integer'],
            'difference_account_id' => ['nullable', 'integer'],
            'difference_description' => ['nullable', 'string', 'max:255'],
        ]);

        $entryIds = $validated['journal_entry_ids'] ?? [$validated['journal_entry_id']];

        return $this->attempt(fn () => $this->matcher->match(
            $reconciliation,
            (int) $validated['statement_line_id'],
            array_map('intval', $entryIds),
            isset($validated['difference_account_id']) ? (int) $validated['difference_account_id'] : null,
            $validated['difference_description'] ?? null,
            auth()->id(),
        ), 'Line matched.');
    }

    public function createAndMatch(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'statement_line_id' => ['required', 'integer'],
            'chart_of_account_id' => ['nullable', 'integer', 'required_without:splits'],
            'description' => ['nullable', 'string', 'max:255'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'splits' => ['nullable', 'array', 'max:20'],
            'splits.*.account_id' => ['required_with:splits', 'integer'],
            'splits.*.amount' => ['required_with:splits', 'numeric', 'min:0'],
            'splits.*.narration' => ['nullable', 'string', 'max:255'],
        ]);

        $splits = collect($validated['splits'] ?? [])
            ->filter(fn ($split) => ! empty($split['account_id']) && (float) ($split['amount'] ?? 0) > 0)
            ->values()
            ->all();

        return $this->attempt(fn () => $this->reconciliations->createAndMatch(
            $reconciliation,
            (int) $validated['statement_line_id'],
            isset($validated['chart_of_account_id']) ? (int) $validated['chart_of_account_id'] : null,
            auth()->id(),
            $validated['description'] ?? null,
            $validated['party_name'] ?? null,
            count($splits) > 1 ? $splits : null,
        ), count($splits) > 1 ? 'Voucher posted across ' . count($splits) . ' ledgers and matched.' : 'Voucher posted and matched.');
    }

    /**
     * Post many unmatched lines at once, each as its own Receipt/Payment voucher.
     */
    public function bulkPost(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.account_id' => ['nullable', 'integer'],
            'items.*.party_name' => ['nullable', 'string', 'max:255'],
            'items.*.narration' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->matcher->postMany($reconciliation, $validated['items'], auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $response = back()->with('success', "Posted {$result['posted']} voucher(s) and matched them.");

        if ($result['errors'] !== []) {
            $lines = $reconciliation->statementLines()->whereIn('id', array_keys($result['errors']))->get()->keyBy('id');
            $response->with('import_errors', collect($result['errors'])->map(fn ($message, $id) => ($lines[$id]?->transaction_date?->format('d M') ?? '') . ' ' . \Illuminate\Support\Str::limit((string) ($lines[$id]?->description), 40) . ': ' . $message)->values()->all());
        }

        return $response;
    }

    /**
     * Open invoices of a customer / open bills of a vendor, for the settle dialog.
     */
    public function partyDocuments(Request $request, BankReconciliation $reconciliation): JsonResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'type' => ['required', 'in:customer,vendor'],
            'party_id' => ['required', 'integer'],
        ]);

        return response()->json(['documents' => $this->settler->openDocuments($validated['type'], (int) $validated['party_id'])]);
    }

    /**
     * Receive a deposit against customer invoices, or pay vendor bills from a
     * withdrawal, and match it — the payment is recorded in Sales / Purchase.
     */
    public function settle(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'statement_line_id' => ['required', 'integer'],
            'party_type' => ['required', 'in:customer,vendor'],
            'party_id' => ['required', 'integer'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocations = collect($validated['allocations'] ?? [])->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0)->all();

        return $this->attempt(fn () => $this->settler->settle(
            $reconciliation,
            (int) $validated['statement_line_id'],
            $validated['party_type'],
            (int) $validated['party_id'],
            $allocations,
            auth()->id(),
        ), $validated['party_type'] === 'customer'
            ? 'Receipt recorded against the customer\'s invoices and matched.'
            : 'Payment recorded against the vendor\'s bills and matched.');
    }

    public function unmatch(BankReconciliation $reconciliation, int $line): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        return $this->attempt(fn () => $this->matcher->unmatch($reconciliation, $line), 'Match removed. The line and its ledger entries are open again.');
    }

    public function deleteLines(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        $validated = $request->validate([
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer'],
        ]);

        try {
            $count = $this->reconciliations->deleteLines($reconciliation, array_map('intval', $validated['line_ids']));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Deleted {$count} statement line(s).");
    }

    public function deleteUpload(BankReconciliation $reconciliation, int $upload): RedirectResponse
    {
        $this->authorize('update', $reconciliation);

        try {
            $count = $this->reconciliations->deleteUpload($reconciliation, $upload);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Upload removed along with its {$count} statement line(s).");
    }

    public function complete(BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('complete', $reconciliation);

        try {
            $this->reconciliations->complete($reconciliation, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.bank-reconciliation.show', $reconciliation)
            ->with('success', 'Reconciliation completed and locked. The bank reconciliation statement has been saved.');
    }

    public function reopen(BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('reopen', $reconciliation);

        return $this->attempt(fn () => $this->reconciliations->reopen($reconciliation, auth()->id()), 'Reconciliation reopened. Matches can be changed again; complete it once more when done.');
    }

    /**
     * Bank Reconciliation Statement as PDF or Excel, using the shared
     * accounting report export layout.
     */
    public function exportStatement(BankReconciliation $reconciliation, string $format): SymfonyResponse
    {
        $this->authorize('view', $reconciliation);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $brs = $this->statement->build($reconciliation);
        $table = $this->brsTable($reconciliation, $brs);
        $filename = 'BRS_' . preg_replace('/[^A-Za-z0-9]+/', '_', $reconciliation->chartOfAccount->name) . '_' . $reconciliation->statement_date->format('Ymd');

        if ($format === 'pdf') {
            return Pdf::loadView('modules.accounting.reports.export-pdf', ['report' => $table])
                ->setPaper('a4', 'portrait')
                ->download($filename . '.pdf');
        }

        return Excel::download(new AccountingReportExport($table), $filename . '.xlsx');
    }

    /**
     * @param array<string, mixed> $brs
     * @return array<string, mixed>
     */
    private function brsTable(BankReconciliation $reconciliation, array $brs): array
    {
        $account = $reconciliation->chartOfAccount;
        $rows = fn (Collection $items) => $items->map(fn ($row) => [
            $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') : '',
            $row['journal_number'] ?? '',
            trim(($row['description'] ?? '') . (! empty($row['reference']) ? ' (Ref ' . $row['reference'] . ')' : '')),
            (float) $row['amount'],
        ])->all();

        $meta = [
            ['Bank account', $account->code . ' - ' . $account->name],
            ['Statement period', ($reconciliation->statement_from_date ? $reconciliation->statement_from_date->format('d M Y') . ' – ' : 'Up to ') . $reconciliation->statement_date->format('d M Y')],
            ['Status', $reconciliation->isCompleted() ? 'Completed ' . $reconciliation->completed_at?->format('d M Y') . ' by ' . ($reconciliation->completedBy?->name ?? '—') : 'In progress'],
        ];
        if (company()) {
            $meta[] = ['Amounts', 'In ' . company_currency()['code']];
        }
        $meta[] = ['Generated', now()->format('d M Y H:i')];

        $header = ['Date', 'Voucher', 'Particulars', 'Amount'];

        return [
            'title' => 'Bank Reconciliation Statement as on ' . $reconciliation->statement_date->format('d M Y'),
            'meta' => $meta,
            'orientation' => 'portrait',
            'sections' => [
                [
                    'title' => 'Summary',
                    'header' => ['Particulars', 'Amount'],
                    'rows' => [
                        ['Balance as per company books', $brs['book_balance']],
                        ['Add: Cheques issued / payments not yet presented', $brs['cheques_not_presented_total']],
                        ['Less: Cheques deposited / receipts not yet credited', $brs['deposits_not_credited_total']],
                        ['Add: Amounts credited by bank, not in books', $brs['bank_credits_not_in_books_total']],
                        ['Less: Amounts debited by bank, not in books', $brs['bank_debits_not_in_books_total']],
                        ['Balance as per bank (computed)', $brs['computed_bank_balance']],
                        ['Balance as per bank statement', $brs['statement_balance']],
                    ],
                    'footer' => [['Difference', $brs['difference']]],
                ],
                ['title' => 'Cheques issued / payments not yet presented', 'header' => $header, 'rows' => $rows($brs['cheques_not_presented']), 'footer' => [['', '', 'Total', $brs['cheques_not_presented_total']]]],
                ['title' => 'Cheques deposited / receipts not yet credited', 'header' => $header, 'rows' => $rows($brs['deposits_not_credited']), 'footer' => [['', '', 'Total', $brs['deposits_not_credited_total']]]],
                ['title' => 'Amounts credited by bank, not in books', 'header' => $header, 'rows' => $rows($brs['bank_credits_not_in_books']), 'footer' => [['', '', 'Total', $brs['bank_credits_not_in_books_total']]]],
                ['title' => 'Amounts debited by bank, not in books', 'header' => $header, 'rows' => $rows($brs['bank_debits_not_in_books']), 'footer' => [['', '', 'Total', $brs['bank_debits_not_in_books_total']]]],
            ],
        ];
    }

    /**
     * What still stands between this reconciliation and "Complete".
     *
     * @param array<string, mixed> $brs
     * @return list<array{ok: bool, label: string, detail: string}>
     */
    private function readiness(array $brs): array
    {
        $opening = $brs['opening_check'];
        $check = $brs['statement_check'];
        $counts = $brs['line_counts'];

        return [
            [
                'ok' => $counts['total'] > 0 && $counts['unmatched'] === 0,
                'label' => 'Every statement line is matched',
                'detail' => $counts['total'] === 0 ? 'No statement imported yet.' : "{$counts['matched']} of {$counts['total']} matched.",
            ],
            [
                'ok' => $check['difference'] === 0.0,
                'label' => 'Statement adds up',
                'detail' => 'Opening ' . number_format($check['opening'], 2) . ' + lines ' . number_format($check['lines_total'], 2) . ' = ' . number_format($check['expected_closing'], 2) . ' vs closing ' . number_format($check['closing'], 2) . '.',
            ],
            [
                'ok' => $opening['difference'] === null || $opening['difference'] === 0.0,
                'label' => 'Opening balance continues from the last reconciliation',
                'detail' => $opening['previous_closing'] === null
                    ? 'First reconciliation for this account.'
                    : 'Last closing ' . number_format($opening['previous_closing'], 2) . ' on ' . \Illuminate\Support\Carbon::parse($opening['previous_date'])->format('d M Y') . '.',
            ],
            [
                'ok' => $brs['difference'] === 0.0,
                'label' => 'Books agree with the bank',
                'detail' => 'Difference ' . number_format($brs['difference'], 2) . '.',
            ],
        ];
    }

    /**
     * @param Collection<int, JournalEntry> $candidates
     * @return list<array<string, mixed>>
     */
    private function candidatesForJs(Collection $candidates): array
    {
        return $candidates->map(fn (JournalEntry $entry) => [
            'id' => $entry->id,
            'number' => $entry->journal?->journal_number,
            'date' => $entry->journal?->journal_date?->format('d M Y'),
            'iso' => $entry->journal?->journal_date?->toDateString(),
            'text' => \Illuminate\Support\Str::limit((string) ($entry->description ?: $entry->journal?->memo), 90),
            'ref' => $entry->journal?->voucherDetail?->reference_no,
            'amount' => $entry->signedAmount(),
        ])->values()->all();
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function postableAccounts(BankReconciliation $reconciliation): Collection
    {
        $active = $this->accounts->active();
        $parentIds = ChartOfAccount::query()->whereNotNull('parent_id')->distinct()->pluck('parent_id')->flip();

        return $active
            ->reject(fn ($account) => isset($parentIds[$account->id]) || $account->id === $reconciliation->chart_of_account_id)
            ->sortBy('code')
            ->values();
    }

    private function cashBankAccounts(): Collection
    {
        return $this->accounts->active()->where('is_cash_or_bank', true)->values();
    }

    /**
     * account id => [statement_date, closing_balance] of its last completed reconciliation,
     * so the start form can pre-fill the period and opening balance.
     *
     * @return array<int, array{date: string, next: string, closing: float}>
     */
    private function lastReconciledByAccount(): array
    {
        return BankReconciliation::query()
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->orderBy('statement_date')
            ->orderBy('id')
            ->get(['chart_of_account_id', 'statement_date', 'closing_balance'])
            ->mapWithKeys(fn ($r) => [$r->chart_of_account_id => [
                'date' => $r->statement_date->format('d M Y'),
                'next' => $r->statement_date->copy()->addDay()->toDateString(),
                'closing' => round((float) $r->closing_balance, 2),
            ]])
            ->all();
    }

    private function skippedNote(int $duplicates, int $outOfPeriod, int $invalid): string
    {
        $parts = array_filter([
            $duplicates ? "{$duplicates} already imported" : null,
            $outOfPeriod ? "{$outOfPeriod} outside the statement period" : null,
            $invalid ? "{$invalid} unreadable" : null,
        ]);

        return $parts ? ' Skipped: ' . implode(', ', $parts) . '.' : '';
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
