<?php

namespace App\Domains\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Currency::class);

        return view('modules.platform.currencies.index', [
            'currencies' => Currency::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Currency::class);

        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', 'unique:currencies,code'],
        ] + $this->detailRules());

        $validated['is_active'] = true;

        $currency = Currency::create($validated);

        return redirect()->route('platform.currencies.index')
            ->with('success', "Currency {$currency->code} created successfully.");
    }

    /**
     * The code is deliberately not updatable: companies, journals and exchange
     * rates reference it by value.
     */
    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $currency->update($request->validate($this->detailRules()));

        return redirect()->route('platform.currencies.index')
            ->with('success', "Currency {$currency->code} updated successfully.");
    }

    /**
     * Deactivate instead of delete. An inactive currency disappears from pickers
     * and validation, but existing companies, journals and rates keep their code.
     */
    public function toggleStatus(Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $currency->update(['is_active' => ! $currency->is_active]);

        $state = $currency->is_active ? 'activated' : 'deactivated';

        return redirect()->route('platform.currencies.index')
            ->with('success', "Currency {$currency->code} {$state}.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimals' => ['required', 'integer', 'between:0,4'],
        ];
    }
}
