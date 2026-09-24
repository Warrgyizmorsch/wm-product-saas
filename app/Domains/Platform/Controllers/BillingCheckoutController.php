<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantSubscription;
use App\Domains\Platform\Services\BillingCheckoutService;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SubscriptionPaymentService;
use App\Domains\Platform\Services\SubscriptionPricing;
use App\Domains\Platform\Services\TenantSubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantModuleAccess;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Zoho-style checkout for recurring per-user billing: Plan → Add-ons → Pay →
 * Confirmation. Always the CURRENT tenant (tenant() from context, never a
 * route param), same as SubscriptionController. Totals shown on the page come
 * from quote(), so what the tenant sees is what the server will charge. With a
 * live subscription the same wizard changes it instead (TenantSubscriptionService
 * ::change): upgrades charged pro rata now, downgrades from renewal.
 */
class BillingCheckoutController extends Controller
{
    public function __construct(
        private readonly BillingCheckoutService $checkout,
        private readonly SubscriptionPricing $pricing,
        private readonly TenantSubscriptionService $subscriptions,
        private readonly PaymentGatewayManager $gateways,
        private readonly SubscriptionPaymentService $payments,
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
        $live = $this->subscriptions->live($tenant);
        $live?->load('plan');
        $scheduled = $live?->scheduledChange();

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
                'cycle' => in_array($request->query('cycle'), $this->pricing->cycles(), true) ? $request->query('cycle') : ($live?->cycle ?? 'yearly'),
                'seats' => max($minimumSeats, $live?->seats ?? 0),
                'modules' => array_values(array_unique([
                    ...$this->checkout->recurringModules($tenant),
                    ...array_intersect((array) $request->query('modules', []), EnsureTenantModuleAccess::GATED_MODULES),
                ])),
            ],
            'startStep' => session()->has('subscribed') || session()->has('subscriptionChanged') ? 4 : ($request->filled('modules') ? 2 : 1),
            'subscribed' => session('subscribed'),
            'subscriptionChanged' => session('subscriptionChanged'),
            'liveSubscription' => $live,
            'scheduledChange' => $scheduled === null ? null : [
                'plan' => $plans->firstWhere('id', $scheduled['plan_id'])?->name,
                'seats' => $scheduled['seats'],
                'on' => $live->current_end?->format('d M Y'),
            ],
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

        $live = $this->subscriptions->live($tenant);

        return response()->json($this->checkout->describe($quote) + [
            'change' => $live === null ? null : $this->subscriptions->previewChange($live, $quote),
        ]);
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

    /**
     * Pay step: re-prices the selection on the server and starts a recurring
     * subscription on the active gateway. Nothing changes for the tenant until
     * verify() (or the gateway's webhook) confirms the first payment.
     */
    public function subscribe(Request $request): JsonResponse
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

        try {
            if ($this->subscriptions->live($tenant) === null) {
                return response()->json($this->subscriptions->start($tenant, $quote, $this->gateways->active()));
            }

            $result = $this->subscriptions->change($tenant, $quote);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Nothing to pay: the page reloads onto the confirmation step.
        if (! empty($result['scheduled'])) {
            session()->flash('subscriptionChanged', "Your change is booked — it takes effect when your subscription renews on {$result['effective_on']}. Until then nothing changes.");
        } elseif (! empty($result['applied'])) {
            session()->flash('subscriptionChanged', $this->changedMessage($tenant));
        }

        return response()->json($result);
    }

    /** Browser callback after paying for an upgrade (a one-time order) — verified server-side. */
    public function verifyChange(Request $request): RedirectResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'gateway_order_id' => ['required', 'string'],
            'gateway_payment_id' => ['required', 'string'],
            'gateway_signature' => ['required', 'string'],
        ]);

        $failed = fn () => redirect()->route('platform.billing.checkout')
            ->with('error', 'Payment verification failed — your subscription was not changed. If money was deducted, contact support.');

        $payment = SubscriptionPayment::query()
            ->where('gateway_order_id', $validated['gateway_order_id'])
            ->where('purpose', SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE)
            ->first();

        $gateway = $payment === null ? null : $this->gateways->resolveByIdentifier($payment->gateway);

        if ($gateway === null || ! $gateway->verifyCheckoutCallback($validated, $payment)) {
            return $failed();
        }

        try {
            $this->payments->markPaid($payment, $validated['gateway_payment_id'], $validated['gateway_signature'], $tenant);
        } catch (RuntimeException $e) {
            return $failed();
        }

        return redirect()->route('platform.billing.checkout')
            ->with('subscriptionChanged', $this->changedMessage($tenant->fresh()));
    }

    private function changedMessage(Tenant $tenant): string
    {
        $live = $this->subscriptions->live($tenant)?->load('plan');

        if ($live === null) {
            return 'Your subscription was updated.';
        }

        return sprintf(
            'You now have %s for %d users. Renews on %s at %s incl. GST.',
            $live->plan->name,
            $live->seats,
            $live->current_end?->format('d M Y') ?? '—',
            '₹'.number_format($live->total / 100, 2),
        );
    }

    /** Browser callback after the first subscription payment — verified server-side. */
    public function verify(Request $request): RedirectResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'gateway_subscription_id' => ['required', 'string'],
            'gateway_payment_id' => ['required', 'string'],
            'gateway_signature' => ['required', 'string'],
        ]);

        $failed = fn () => redirect()->route('platform.billing.checkout')
            ->with('error', 'Payment verification failed — your subscription was not started. If money was deducted, contact support.');

        $subscription = TenantSubscription::query()
            ->where('gateway_subscription_id', $validated['gateway_subscription_id'])
            ->first();

        if ($subscription === null) {
            return $failed();
        }

        $gateway = $this->gateways->resolveByIdentifier($subscription->gateway);

        if ($gateway === null || ! $gateway->verifySubscriptionCallback($validated, $subscription)) {
            return $failed();
        }

        try {
            $subscription = $this->subscriptions->activate($subscription, $validated['gateway_payment_id'], $tenant);
        } catch (RuntimeException $e) {
            return $failed();
        }

        $subscription->load('plan');

        return redirect()->route('platform.billing.checkout')->with('subscribed', [
            'plan' => $subscription->plan->name,
            'seats' => $subscription->seats,
            'cycle' => $subscription->cycle,
            'total' => $subscription->total,
            'renews' => $subscription->current_end?->format('d M Y'),
        ]);
    }
}
