<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SubscriptionPaymentService;
use App\Domains\Platform\Services\SubscriptionPricing;
use App\Domains\Platform\Services\TenantModuleService;
use App\Domains\Platform\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Self-service subscription management for the CURRENT tenant only — always
 * resolves tenant() from context, never a {tenant} route param, so a request
 * can never target another tenant's subscription.
 *
 * Gateway-agnostic: this controller only ever talks to PaymentGatewayManager
 * and the PaymentGateway contract, never a concrete gateway class — the
 * active gateway (Razorpay today) is a stored setting, changeable from the
 * Payment Gateway settings screen, not a code change here.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly PaymentGatewayManager $gateways,
        private readonly SubscriptionPaymentService $payments,
        private readonly TenantModuleService $tenantModules,
        private readonly SubscriptionPricing $pricing,
    ) {
    }

    public function index(): View
    {
        $tenant = tenant();

        $this->authorize('viewSubscription', $tenant);

        $apps = config('navigation.apps');

        $modules = [];
        foreach ($this->tenantModules->states($tenant) as $module => $state) {
            $requires = $this->tenantModules->requirements($module);

            $modules[] = [
                'key' => $module,
                'state' => $state,
                'installed' => in_array($state, ['plan', 'addon'], true),
                'requires' => $requires === [] ? null : $this->tenantModules->labels($requires),
                // Per user per month; set → a recurring add-on bought in the plan checkout.
                'prices' => [
                    'monthly' => $this->pricing->modulePricePerUser($module, 'monthly'),
                    'yearly' => $this->pricing->modulePricePerUser($module, 'yearly'),
                ],
            ] + ($apps[$module] ?? ['label' => ucfirst($module), 'icon' => 'feather-grid', 'description' => '', 'color' => '#3B82F6']);
        }

        return view('modules.platform.subscription.index', [
            'tenant' => $tenant,
            'currentPlan' => $tenant->planCatalog,
            'plans' => $plans = Plan::query()
                ->where('is_active', true)
                ->where('is_demo', false)
                ->orderBy('sort_order')
                ->get(),
            // plan id => per user per month on each cycle (null = not sold on it)
            'planPrices' => $plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
                'monthly' => $this->pricing->planPricePerUser($plan, 'monthly'),
                'yearly' => $this->pricing->planPricePerUser($plan, 'yearly'),
            ]])->all(),
            'modules' => $modules,
            'modulePrice' => config('navigation.module_addon_price'),
            'moduleCurrency' => 'INR',
            'payments' => SubscriptionPayment::query()
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Free-plan switches only (price == 0) — no payment involved. Paid plans
     * must go through checkout()/verify() instead; this rejects a paid
     * plan_id so the switch can never be triggered without payment.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->price > 0 || $this->pricing->sellsPerUser($plan)) {
            return redirect()->route('platform.subscription.index')
                ->with('error', "{$plan->name} is a paid plan — use the checkout flow, not a direct switch.");
        }

        $this->tenants->switchOwnPlan($tenant, $plan->id);

        return redirect()->route('platform.subscription.index')
            ->with('success', "Your plan has been switched to {$plan->name}.");
    }

    /**
     * Starts a checkout for a paid plan through whichever gateway is
     * currently active. The amount is always computed server-side inside
     * the gateway implementation, never trusted from the client.
     */
    public function checkout(Request $request): JsonResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        if ($this->pricing->sellsPerUser($plan)) {
            return response()->json(['message' => "{$plan->name} is billed per user — choose it in the plan checkout."], 422);
        }

        if ($plan->price <= 0) {
            return response()->json(['message' => 'This plan is free — no checkout needed.'], 422);
        }

        try {
            $gateway = $this->gateways->active();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $result = $gateway->createCheckout($tenant, $plan);

        return response()->json($result['checkout']);
    }

    /**
     * Server-side verification of a checkout success callback, routed to
     * whichever gateway actually created this payment (read off the
     * SubscriptionPayment row, not the active-gateway setting — a payment
     * must always be verified by the gateway that issued it, even if the
     * active gateway has since been changed). A plan switch only ever
     * happens here (or from a gateway's webhook) — never on the client-side
     * "success" event alone.
     */
    public function verify(Request $request): RedirectResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'gateway_order_id' => ['required', 'string'],
            'gateway_payment_id' => ['required', 'string'],
            'gateway_signature' => ['required', 'string'],
        ]);

        $failed = fn () => redirect()->route('platform.subscription.index')
            ->with('error', 'Payment verification failed — your plan was not changed. If money was deducted, contact support.');

        $payment = SubscriptionPayment::query()
            ->where('gateway_order_id', $validated['gateway_order_id'])
            ->first();

        if ($payment === null) {
            return $failed();
        }

        $gateway = $this->gateways->resolveByIdentifier($payment->gateway);

        if ($gateway === null || ! $gateway->verifyCheckoutCallback($validated, $payment)) {
            return $failed();
        }

        try {
            $this->payments->markPaid(
                $payment,
                $validated['gateway_payment_id'],
                $validated['gateway_signature'],
                $tenant,
            );
        } catch (RuntimeException $e) {
            return $failed();
        }

        return redirect()->route('platform.subscription.index')
            ->with('success', 'Payment verified — your plan has been updated.');
    }
}
