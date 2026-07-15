<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Journal::class);

        $filters = $request->only(['status', 'source', 'search', 'sort', 'direction']);
        $journals = $this->journals->paginate($filters, 15);

        return view('modules.accounting.journals.index', [
            'journals' => $journals,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('post', Journal::class);

        return view('modules.accounting.journals.create', [
            'accounts' => $this->accounts->active(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.debit' => ['nullable', 'numeric', 'min:0'],
            'items.*.credit' => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $journal = $this->journals->post($validated['items'], [
                'journal_date' => $validated['journal_date'],
                'memo' => $validated['memo'] ?? null,
                'source' => Journal::SOURCE_MANUAL,
                'posted_by' => auth()->id(),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.journals.show', $journal)
            ->with('success', 'Journal posted successfully.');
    }

    public function show(Journal $journal): View
    {
        $this->authorize('view', $journal);

        return view('modules.accounting.journals.show', [
            'journal' => $this->journals->find($journal->id),
        ]);
    }

    public function reverse(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('reverse', $journal);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->journals->reverse($journal->id, $validated['reason'] ?? null, auth()->id());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.journals.show', $journal)->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.journals.show', $journal)
            ->with('success', 'Journal reversed successfully.');
    }
}
