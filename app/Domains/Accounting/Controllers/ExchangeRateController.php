<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\ExchangeRateSyncSetting;
use App\Domains\Accounting\Services\ExchangeRates\ExchangeRateSyncService;
use App\Domains\Accounting\Services\ExchangeRateService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly ExchangeRateSyncService $sync,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $tenantId = require_tenant_id();
        $filters = $request->only(['currency', 'source']);
        $currencies = Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']);

        return view('modules.accounting.exchange-rates.index', [
            'rates' => $this->exchangeRates->list($filters)->withQueryString(),
            'filters' => $filters,
            'currencies' => $currencies,
            'currencyOptions' => $currencies->mapWithKeys(fn ($c) => [$c->code => "{$c->code} — {$c->name}"])->all(),
            'settings' => $this->exchangeRates->settingsFor($tenantId),
            'baseCurrencies' => $this->sync->baseCurrenciesFor($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExchangeRate::class);

        $this->exchangeRates->create($this->validated($request), $request->user()?->id);

        return redirect()->route('accounting.exchange-rates.index')
            ->with('success', 'Exchange rate added.');
    }

    public function update(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $this->authorize('update', $exchangeRate);

        $this->exchangeRates->update($exchangeRate, $this->validated($request), $request->user()?->id);

        return redirect()->route('accounting.exchange-rates.index')
            ->with('success', 'Exchange rate updated.');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $this->authorize('delete', $exchangeRate);

        $this->exchangeRates->delete($exchangeRate);

        return redirect()->route('accounting.exchange-rates.index')
            ->with('success', 'Exchange rate deleted.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('sync', ExchangeRate::class);

        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => [Rule::exists('currencies', 'code')->where('is_active', true)],
        ]);

        $this->exchangeRates->updateSettings(
            require_tenant_id(),
            $request->boolean('is_enabled'),
            $validated['currencies'] ?? [],
        );

        return redirect()->route('accounting.exchange-rates.index')
            ->with('success', 'Auto-sync settings saved.');
    }

    public function syncNow(): RedirectResponse
    {
        $this->authorize('sync', ExchangeRate::class);

        $result = $this->sync->syncTenant(require_tenant_id());

        $flash = in_array($result['status'], [ExchangeRateSyncSetting::STATUS_FAILED, ExchangeRateSyncSetting::STATUS_SKIPPED], true)
            ? 'error'
            : 'success';

        return redirect()->route('accounting.exchange-rates.index')->with($flash, $result['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'from_currency' => strtoupper((string) $request->input('from_currency')),
            'to_currency' => strtoupper((string) $request->input('to_currency')),
        ]);

        return $request->validate([
            'from_currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'to_currency' => ['required', 'different:from_currency', Rule::exists('currencies', 'code')->where('is_active', true)],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
        ]);
    }
}
