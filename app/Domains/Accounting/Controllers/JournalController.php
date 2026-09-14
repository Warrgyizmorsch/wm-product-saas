<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\CurrencyService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountsService $accounts,
        private readonly CurrencyService $currencies,
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
            'costCenters' => CostCenter::active()->orderBy('code')->get(),
            'baseCurrency' => company_currency(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol', 'decimals']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:500'],
            'currency_code' => ['nullable', Rule::exists('currencies', 'code')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
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
                'currency_code' => $validated['currency_code'] ?? null,
                'exchange_rate' => $validated['exchange_rate'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.journals.show', $journal)
            ->with('success', 'Journal posted successfully.');
    }

    /**
     * Rate lookup for the New Journal form: 1 {currency} = rate {company base}.
     */
    public function exchangeRate(Request $request): JsonResponse
    {
        $this->authorize('post', Journal::class);

        $validated = $request->validate([
            'currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'date' => ['required', 'date'],
        ]);

        $base = company_currency()['code'];

        try {
            $rate = $this->currencies->rate($validated['currency'], $base, $validated['date']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['rate' => null, 'base' => $base, 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'rate' => round($rate, 10),
            'base' => $base,
            'currency' => strtoupper($validated['currency']),
        ]);
    }

    public function show(Journal $journal): View
    {
        $this->authorize('view', $journal);

        $journal = $this->journals->find($journal->id);

        return view('modules.accounting.journals.show', [
            'journal' => $journal,
            'baseCurrency' => $this->currencies->baseCurrencyForTenant($journal->tenant_id),
            'foreignDecimals' => $journal->currency_code ? $this->currencies->decimalsFor($journal->currency_code) : 2,
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
