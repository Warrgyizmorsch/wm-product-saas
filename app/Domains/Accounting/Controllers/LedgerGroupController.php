<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\LedgerGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LedgerGroupController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LedgerGroup::class);

        return view('modules.accounting.ledger-groups.index', [
            'ledgerGroups' => LedgerGroup::with('parent')->orderBy('code')->get(),
            'parentOptions' => LedgerGroup::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LedgerGroup::class);

        $validated = $this->validated($request);
        $validated['is_active'] = true;

        LedgerGroup::create($validated);

        return redirect()->route('accounting.ledger-groups.index')
            ->with('success', 'Ledger group created successfully.');
    }

    public function update(Request $request, LedgerGroup $ledgerGroup): RedirectResponse
    {
        $this->authorize('update', $ledgerGroup);

        $validated = $this->validated($request, $ledgerGroup->id);
        $validated['is_active'] = $request->boolean('is_active');

        $ledgerGroup->update($validated);

        return redirect()->route('accounting.ledger-groups.index')
            ->with('success', 'Ledger group updated successfully.');
    }

    public function destroy(LedgerGroup $ledgerGroup): RedirectResponse
    {
        $this->authorize('delete', $ledgerGroup);

        if (ChartOfAccount::where('ledger_group_id', $ledgerGroup->id)->exists() || $ledgerGroup->children()->exists()) {
            return redirect()->route('accounting.ledger-groups.index')
                ->with('error', "Cannot delete '{$ledgerGroup->name}' — it has ledgers or sub-groups assigned to it.");
        }

        $ledgerGroup->delete();

        return redirect()->route('accounting.ledger-groups.index')
            ->with('success', 'Ledger group deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('ledger_groups', 'code')->where('tenant_id', auth()->user()->tenant_id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'nature' => ['required', 'string', 'in:' . implode(',', ChartOfAccount::TYPES)],
            'parent_id' => ['nullable', 'integer', 'exists:ledger_groups,id'],
        ]);
    }
}
