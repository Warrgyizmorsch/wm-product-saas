<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SubscriptionPaymentService;
use App\Domains\Platform\Services\SubscriptionPricing;
use App\Domains\Platform\Services\TenantModuleService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Self-service "install a module" (like Odoo's Apps screen): a tenant can turn on
 * any module that already exists in the codebase without changing their Plan row
 * (which every other tenant on that plan shares). Each not-yet-installed module
 * costs a flat one-time fee (config('navigation.module_addon_price')) paid via
 * whichever PaymentGateway is active — same checkout/verify pattern as
 * SubscriptionController, just for a module-add-on order instead of a plan
 * switch (see PaymentGateway::createModuleCheckout, SubscriptionPaymentService).
 * Paying unlocks access and provisions the new modules' starter masters (same
 * TenantProvisioner run as a plan switch). A bought module can be uninstalled
 * (hidden, data kept) and reinstalled for free — see TenantModuleService.
 * The catalog itself lives on the Subscription page
 * (SubscriptionController::index()) — this controller is just the checkout/verify
 * endpoints behind it.
 */
class TenantModuleController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly SubscriptionPaymentService $payments,
        private readonly TenantModuleService $modules,
        private readonly SubscriptionPricing $pricing,
    ) {
    }

    /**
     * Starts a checkout for the selected not-yet-installed modules. The amount is
     * always computed here from config('navigation.module_addon_price'), never
     * trusted from the client — mirrors SubscriptionController::checkout().
     */
    public function checkout(Request $request): JsonResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        $validated = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'in:'.implode(',', EnsureTenantModuleAccess::GATED_MODULES)],
        ]);

        $installed = tenant_allowed_modules() ?? EnsureTenantModuleAccess::GATED_MODULES;
        $toBuy = array_values(array_diff($validated['modules'], $installed));

        if ($toBuy === []) {
            return response()->json(['message' => 'The selected modules are already installed.'], 422);
        }

        // Modules sold per user are recurring add-ons now — bought in the plan
        // checkout, never with the old one-time fee.
        $recurring = array_values(array_filter($toBuy, fn (string $m) => $this->pricing->modulePricePerUser($m, 'monthly') !== null
            || $this->pricing->modulePricePerUser($m, 'yearly') !== null));

        if ($recurring !== []) {
            return response()->json([
                'message' => "{$this->modules->labels($recurring)} is billed per user — add it in the plan checkout.",
            ], 422);
        }

        $states = $this->modules->states($tenant);
        $alreadyPaid = array_values(array_filter($toBuy, fn (string $m) => $states[$m] === 'uninstalled'));

        if ($alreadyPaid !== []) {
            return response()->json([
                'message' => "{$this->modules->labels($alreadyPaid)} was paid for already — use Reinstall, it's free.",
            ], 422);
        }

        try {
            $this->modules->assertRequirementsMet($tenant, $toBuy);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $pricePerModule = (int) config('navigation.module_addon_price');
        $amountInPaise = $pricePerModule * 100 * count($toBuy);

        try {
            $gateway = $this->gateways->active();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $result = $gateway->createModuleCheckout($tenant, $toBuy, $amountInPaise, 'INR');

        return response()->json($result['checkout']);
    }

    /** Server-side verification of a checkout success callback — mirrors SubscriptionController::verify(). */
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
            ->with('error', 'Payment verification failed — no module was installed. If money was deducted, contact support.');

        $payment = SubscriptionPayment::query()
            ->where('gateway_order_id', $validated['gateway_order_id'])
            ->where('purpose', SubscriptionPayment::PURPOSE_MODULE_ADDON)
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

        $labels = collect($payment->modules)
            ->map(fn (string $module) => config("navigation.apps.$module.label", ucfirst($module)))
            ->implode(', ');

        return redirect()->route('platform.subscription.index')
            ->with('success', "Payment received — {$labels} installed for your workspace.");
    }

    /** Hides a bought module; its data stays and reinstalling is free. */
    public function uninstall(string $module): RedirectResponse
    {
        return $this->change($module, fn ($tenant) => $this->modules->uninstall($tenant, $module),
            '%s uninstalled. Its data is kept — reinstall any time for free.');
    }

    /** Brings back a module this tenant bought and later uninstalled, at no charge. */
    public function reinstall(string $module): RedirectResponse
    {
        return $this->change($module, fn ($tenant) => $this->modules->reinstall($tenant, $module),
            '%s reinstalled.');
    }

    private function change(string $module, callable $action, string $success): RedirectResponse
    {
        $tenant = tenant();

        $this->authorize('updateSubscription', $tenant);

        abort_unless(in_array($module, EnsureTenantModuleAccess::GATED_MODULES, true), 404);

        try {
            $action($tenant);
        } catch (RuntimeException $e) {
            return redirect()->route('platform.subscription.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.subscription.index')
            ->with('success', sprintf($success, $this->modules->label($module)));
    }
}
