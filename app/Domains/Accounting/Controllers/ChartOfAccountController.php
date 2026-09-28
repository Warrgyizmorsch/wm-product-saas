<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\LedgerGroup;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $tree = $this->accounts->tree();

        $summary = [
            'total' => count($tree),
            'active' => collect($tree)->filter(fn ($row) => $row['account']->is_active)->count(),
            'by_type' => collect($tree)->countBy(fn ($row) => $row['account']->type),
        ];

        $filters = $request->only(['type']);

        // Filtered by type only (server-side, since it's a discrete small set and
        // a COA hierarchy's children normally share their parent's type). Free-text
        // search stays client-side (see the view's script) because the tree's
        // parent/child indentation can't be sliced by a backend LIKE query without
        // either orphaning matched children or hiding their ancestors' context.
        $visibleTree = empty($filters['type'])
            ? $tree
            : collect($tree)->filter(fn ($row) => $row['account']->type === $filters['type'])->values()->all();

        return view('modules.accounting.chart-of-accounts.index', [
            'tree' => $visibleTree,
            'summary' => $summary,
            'filters' => $filters,
            'parentOptions' => $this->parentOptions(),
            'ledgerGroups' => LedgerGroup::active()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', ChartOfAccount::TYPES)],
            'subtype' => ['nullable', 'string', 'in:' . implode(',', ChartOfAccount::allSubtypes())],
            'normal_balance' => ['required', 'string', 'in:' . ChartOfAccount::BALANCE_DEBIT . ',' . ChartOfAccount::BALANCE_CREDIT],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'ledger_group_id' => ['nullable', 'integer', 'exists:ledger_groups,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_cash_or_bank' => ['nullable', 'boolean'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'string', 'in:' . implode(',', ChartOfAccount::OPENING_BALANCE_TYPES)],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_cash_or_bank'] = $request->boolean('is_cash_or_bank');
        $validated['opening_balance'] = (float) ($validated['opening_balance'] ?? 0);
        $validated['opening_balance_type'] = $validated['opening_balance_type'] ?? $validated['normal_balance'];
        $validated['created_by'] = auth()->id();

        try {
            $account = $this->accounts->create($validated);
            $this->accounts->syncOpeningBalance($account);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function update(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', ChartOfAccount::TYPES)],
            'subtype' => ['nullable', 'string', 'in:' . implode(',', ChartOfAccount::allSubtypes())],
            'normal_balance' => ['required', 'string', 'in:' . ChartOfAccount::BALANCE_DEBIT . ',' . ChartOfAccount::BALANCE_CREDIT],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'ledger_group_id' => ['nullable', 'integer', 'exists:ledger_groups,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_cash_or_bank' => ['nullable', 'boolean'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'string', 'in:' . implode(',', ChartOfAccount::OPENING_BALANCE_TYPES)],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_cash_or_bank'] = $request->boolean('is_cash_or_bank');
        $validated['opening_balance'] = (float) ($validated['opening_balance'] ?? 0);
        $validated['opening_balance_type'] = $validated['opening_balance_type'] ?? $validated['normal_balance'];

        try {
            $updated = $this->accounts->update($account->id, $validated);
            $this->accounts->syncOpeningBalance($updated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->accounts->delete($account->id);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.chart-of-accounts.index')->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    /**
     * Flat, indentation-prefixed options for a parent-account <select>, built
     * from the same tree() the index page uses so ordering/depth always match.
     *
     * @return array<int, array{account: ChartOfAccount, label: string}>
     */
    private function parentOptions(?int $excludeId = null): array
    {
        return collect($this->accounts->tree())
            ->reject(fn ($row) => $excludeId !== null && $row['account']->id === $excludeId)
            ->map(fn ($row) => [
                'account' => $row['account'],
                'label' => str_repeat('— ', $row['depth']) . $row['account']->code . ' ' . $row['account']->name,
            ])
            ->values()
            ->all();
    }
}
