<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
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

    public function index(): View
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $tree = $this->accounts->tree();

        $summary = [
            'total' => count($tree),
            'active' => collect($tree)->filter(fn ($row) => $row['account']->is_active)->count(),
            'by_type' => collect($tree)->countBy(fn ($row) => $row['account']->type),
        ];

        return view('modules.accounting.chart-of-accounts.index', [
            'tree' => $tree,
            'summary' => $summary,
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', ChartOfAccount::TYPES)],
            'subtype' => ['nullable', 'string', 'max:100'],
            'normal_balance' => ['required', 'string', 'in:' . ChartOfAccount::BALANCE_DEBIT . ',' . ChartOfAccount::BALANCE_CREDIT],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['created_by'] = auth()->id();

        try {
            $this->accounts->create($validated);
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
            'subtype' => ['nullable', 'string', 'max:100'],
            'normal_balance' => ['required', 'string', 'in:' . ChartOfAccount::BALANCE_DEBIT . ',' . ChartOfAccount::BALANCE_CREDIT],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        try {
            $this->accounts->update($account->id, $validated);
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
