<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\TaxRate;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\TaxRateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function __construct(
        private readonly TaxRateService $taxRates,
        private readonly ChartOfAccountsService $accounts,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', TaxRate::class);

        return view('modules.accounting.tax-rates.index', [
            'taxRates' => $this->taxRates->list(),
            'payableAccounts' => $this->accounts->active(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TaxRate::class);

        $validated = $this->validated($request);
        $validated['is_active'] = true;

        $this->taxRates->create($validated);

        return redirect()->route('accounting.tax-rates.index')
            ->with('success', 'Tax rate created successfully.');
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $this->authorize('update', $taxRate);

        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active');

        $this->taxRates->update($taxRate->id, $validated);

        return redirect()->route('accounting.tax-rates.index')
            ->with('success', 'Tax rate updated successfully.');
    }

    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $this->authorize('delete', $taxRate);

        $this->taxRates->delete($taxRate->id);

        return redirect()->route('accounting.tax-rates.index')
            ->with('success', 'Tax rate deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:sales_tax,gst,vat,withholding'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_compound' => ['nullable', 'boolean'],
            'tax_payable_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $validated['is_compound'] = $request->boolean('is_compound');

        return $validated;
    }
}
