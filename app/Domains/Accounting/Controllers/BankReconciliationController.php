<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class BankReconciliationController extends Controller
{
    private const SORTABLE = ['statement_date', 'opening_balance', 'closing_balance', 'status'];

    public function __construct(
        private readonly BankReconciliationService $reconciliations,
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'statement_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $reconciliations = BankReconciliation::with('chartOfAccount')
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
            'cashBankAccounts' => $canCreate ? $this->accounts->active()->where('is_cash_or_bank', true)->values() : collect(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BankReconciliation::class);

        return view('modules.accounting.bank-reconciliation.create', [
            'cashBankAccounts' => $this->accounts->active()->where('is_cash_or_bank', true)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
        ]);

        try {
            $reconciliation = $this->reconciliations->start($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['chart_of_account_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.bank-reconciliation.show', $reconciliation)
            ->with('success', 'Bank reconciliation started.');
    }

    public function show(BankReconciliation $reconciliation): View
    {
        $this->authorize('view', $reconciliation);

        $reconciliation->load([
            'chartOfAccount',
            'statementLines.matchedJournalEntry',
            'statementUploads' => fn ($q) => $q->latest(),
        ]);

        // Best-effort suggestion for the "Create & Match" picker, keyed by
        // statement line id — never authoritative, just a default the user
        // can override before anything posts.
        $suggestedAccounts = $reconciliation->statementLines
            ->reject(fn ($line) => $line->is_matched || !$line->suggested_ledger)
            ->mapWithKeys(fn ($line) => [
                $line->id => $this->reconciliations->resolveLedgerByName($reconciliation->tenant_id, $line->suggested_ledger)?->id,
            ]);

        return view('modules.accounting.bank-reconciliation.show', [
            'reconciliation' => $reconciliation,
            'unreconciledEntries' => JournalEntry::where('chart_of_account_id', $reconciliation->chart_of_account_id)
                ->where('is_reconciled', false)
                ->whereHas('journal', fn ($query) => $query->where('status', 'posted'))
                ->with('journal')
                ->orderBy('created_at')
                ->get(),
            'allAccounts' => $this->accounts->active(),
            'suggestedAccounts' => $suggestedAccounts,
            'canComplete' => $this->authorizeOptional('complete', $reconciliation),
        ]);
    }

    public function downloadTemplate(): Response
    {
        $this->authorize('create', BankReconciliation::class);

        $rows = [
            ['date', 'description', 'amount'],
            ['2026-09-02', 'NEFT Credit - Customer', '45000.00'],
            ['2026-09-09', 'Vendor payment debit', '-12500.00'],
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
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt,pdf'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];

        try {
            if ($file->getClientMimeType() === 'application/pdf' || $file->getClientOriginalExtension() === 'pdf') {
                $upload = $this->reconciliations->extractStatementLines($reconciliation, $file, auth()->id());

                return back()->with('success', "Extracted {$upload->extracted_count} statement line(s) from the PDF.");
            }

            $count = $this->reconciliations->importStatementLines($reconciliation, $file);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Imported {$count} statement line(s).");
    }

    public function autoMatch(BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        try {
            $count = $this->reconciliations->autoMatch($reconciliation);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Auto-matched {$count} line(s).");
    }

    public function match(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'statement_line_id' => ['required', 'integer'],
            'journal_entry_id' => ['required', 'integer'],
        ]);

        try {
            $this->reconciliations->manualMatch($reconciliation, $validated['statement_line_id'], $validated['journal_entry_id']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Line matched.');
    }

    public function createAndMatch(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'statement_line_id' => ['required', 'integer'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        try {
            $this->reconciliations->createAndMatch(
                $reconciliation,
                $validated['statement_line_id'],
                $validated['chart_of_account_id'],
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Journal entry created and matched.');
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
            ->with('success', 'Reconciliation completed and locked.');
    }

    private function authorizeOptional(string $ability, $model): bool
    {
        return auth()->user()?->can($ability, $model) ?? false;
    }
}
