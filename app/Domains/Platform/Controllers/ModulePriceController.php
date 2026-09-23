<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Platform-wide per-user add-on price list (Zoho-style "Add-Ons" step, see
 * SubscriptionPricing). Same permission as the plan catalog.
 */
class ModulePriceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Plan::class);

        $prices = ModulePrice::query()->get()->keyBy('module');

        return view('modules.platform.module-prices.index', [
            'rows' => collect(EnsureTenantModuleAccess::GATED_MODULES)->map(fn (string $module) => [
                'module' => $module,
                'label' => config("navigation.apps.$module.label", ucfirst($module)),
                'price' => $prices->get($module) ?? new ModulePrice(['module' => $module, 'is_active' => true]),
            ]),
            'currency' => config('billing.currency'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', new Plan());

        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*.monthly_price_per_user' => ['nullable', 'integer', 'min:0'],
            'prices.*.yearly_price_per_user' => ['nullable', 'integer', 'min:0'],
            'prices.*.is_active' => ['nullable', 'boolean'],
        ]);

        $errors = [];
        foreach ($validated['prices'] as $module => $input) {
            $error = self::yearlyTotalMistake($input['monthly_price_per_user'] ?? null, $input['yearly_price_per_user'] ?? null);

            if ($error !== null) {
                $errors["prices.$module.yearly_price_per_user"] = config("navigation.apps.$module.label", ucfirst((string) $module)).': '.$error;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach (EnsureTenantModuleAccess::GATED_MODULES as $module) {
            $input = $validated['prices'][$module] ?? null;

            if ($input === null) {
                continue;
            }

            ModulePrice::query()->updateOrCreate(['module' => $module], [
                'monthly_price_per_user' => $this->price($input['monthly_price_per_user'] ?? null),
                'yearly_price_per_user' => $this->price($input['yearly_price_per_user'] ?? null),
                'is_active' => (bool) ($input['is_active'] ?? false),
            ]);
        }

        return redirect()->route('platform.module-prices.index')->with('success', 'Add-on prices saved.');
    }

    /**
     * The billed-yearly price is per user PER MONTH. A figure above the monthly
     * price is almost always a yearly total typed into the wrong unit (₹360
     * instead of ₹30), which would bill 12× too much — reject it.
     */
    public static function yearlyTotalMistake(mixed $monthly, mixed $yearly): ?string
    {
        if ($monthly === null || $monthly === '' || $yearly === null || $yearly === '' || (int) $yearly <= (int) $monthly) {
            return null;
        }

        return sprintf(
            'the billed-yearly price is per user per month and cannot be more than the monthly price (₹%s). For ₹%s per user per year, enter ₹%s.',
            number_format((int) $monthly),
            number_format((int) $yearly),
            number_format(intdiv((int) $yearly, 12)),
        );
    }

    private function price(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
