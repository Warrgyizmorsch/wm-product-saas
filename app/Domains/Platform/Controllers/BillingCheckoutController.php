<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\BillingCheckoutService;
use App\Domains\Platform\Services\SubscriptionPricing;
use App\Domains\Platform\Services\UsageLimitService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Zoho-style checkout for recurring per-user billing: Plan → Add-ons → Pay →
 * Confirmation. Always the CURRENT tenant (tenant() from context, never a
 * route param), same as SubscriptionController. Totals shown on the page come
 * from quote(), so what the tenant sees is what the server will charge.
 */
class BillingCheckoutController extends Controller
{
    public function __construct(
        private readonly BillingCheckoutService $checkout,
        private readonly SubscriptionPricing $pricing,
        private readonly UsageLimitService $usage,
    ) {
    }

    /**
     * ?plan=, ?cycle= and ?modules[]= preselect the checkout (links from the
     * Subscription page's plan cards and module tiles); anything invalid is
     * just ignored — quote() still validates the final pick.
     */
    public function show(Request $request): View
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $plans = $this->checkout->plans();
        $lifetime = $this->checkout->lifetimeModules($tenant);
        $minimumSeats = $this->checkout->minimumSeats($tenant);
        $currentPlan = $plans->firstWhere('id', $tenant->plan_id);

        return view('modules.platform.subscription.checkout', [
            'tenant' => $tenant,
            'plans' => $plans->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features,
                'prices' => [
                    'monthly' => $this->pricing->planPricePerUser($plan, 'monthly'),
                    'yearly' => $this->pricing->planPricePerUser($plan, 'yearly'),
                ],
            ])->values(),
            'modules' => collect(EnsureTenantModuleAccess::GATED_MODULES)->map(fn (string $module) => [
                'key' => $module,
                'lifetime' => in_array($module, $lifetime, true),
                'requires' => config("navigation.module_requires.$module", []),
                'prices' => [
                    'monthly' => $this->pricing->modulePricePerUser($module, 'monthly'),
                    'yearly' => $this->pricing->modulePricePerUser($module, 'yearly'),
                ],
            ] + config("navigation.apps.$module", ['label' => ucfirst($module), 'icon' => 'feather-grid', 'description' => '', 'color' => '#3B82F6']))->values(),
            'cycles' => collect(config('billing.cycles'))->map(fn (array $cycle, string $key) => ['key' => $key, 'label' => $cycle['label']])->values(),
            'selection' => [
                'plan_id' => $plans->firstWhere('id', (int) $request->query('plan'))?->id ?? $currentPlan?->id ?? $plans->first()?->id,
                'cycle' => in_array($request->query('cycle'), $this->pricing->cycles(), true) ? $request->query('cycle') : 'yearly',
                'seats' => max($minimumSeats, (int) $this->usage->maxUsers($tenant)),
                'modules' => array_values(array_unique([
                    ...$this->checkout->recurringModules($tenant),
                    ...array_intersect((array) $request->query('modules', []), EnsureTenantModuleAccess::GATED_MODULES),
                ])),
            ],
            'startStep' => $request->filled('modules') ? 2 : 1,
            'canManagePrices' => $request->user()?->can('viewAny', Plan::class) ?? false,
            'minimumSeats' => $minimumSeats,
            'gstRate' => $this->pricing->gstRate(),
            'currency' => config('billing.currency'),
        ]);
    }

    /** Live price for the current selection — called on every change in the checkout. */
    public function quote(Request $request): JsonResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
            'cycle' => ['required', 'string'],
            'seats' => ['required', 'integer', 'min:1', 'max:100000'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string'],
        ]);

        $quote = $this->checkout->quoteFor(
            $tenant,
            (int) $validated['plan_id'],
            $validated['cycle'],
            (int) $validated['seats'],
            array_values($validated['modules'] ?? []),
        );

        return response()->json($this->checkout->describe($quote));
    }

    /** Pay step: who the tax invoice is made out to. */
    public function saveBillingDetails(Request $request): JsonResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $request->merge(['billing_gstin' => strtoupper(trim((string) $request->input('billing_gstin'))) ?: null]);

        $validated = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_gstin' => ['nullable', 'string', 'size:15', 'regex:'.BillingCheckoutService::GSTIN_PATTERN],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'billing_state' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $request->filled('billing_gstin'))],
        ], [
            'billing_gstin.regex' => 'That is not a valid GSTIN (e.g. 27ABCDE1234F1Z5).',
            'billing_gstin.size' => 'A GSTIN has 15 characters.',
            'billing_state.required' => 'Pick the state your GSTIN is registered in.',
        ]);

        $this->checkout->saveBillingDetails($tenant, $validated);

        return response()->json(['message' => 'Billing details saved.']);
    }
}
