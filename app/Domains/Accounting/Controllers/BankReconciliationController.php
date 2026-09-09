<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $reconciliations,
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', BankReconciliation::class);

        return view('modules.accounting.bank-reconciliation.index', [
            'reconciliations' => BankReconciliation::with('chartOfAccount')
                ->latest('statement_date')
                ->paginate(15),
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

        return view('modules.accounting.bank-reconciliation.show', [
            'reconciliation' => $reconciliation->load('chartOfAccount', 'statementLines.matchedJournalEntry'),
            'unreconciledEntries' => JournalEntry::where('chart_of_account_id', $reconciliation->chart_of_account_id)
                ->where('is_reconciled', false)
                ->whereHas('journal', fn ($query) => $query->where('status', 'posted'))
                ->with('journal')
                ->orderBy('created_at')
                ->get(),
            'canComplete' => $this->authorizeOptional('complete', $reconciliation),
        ]);
    }

    public function import(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('create', BankReconciliation::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        try {
            $count = $this->reconciliations->importStatementLines($reconciliation, $validated['file']);
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
