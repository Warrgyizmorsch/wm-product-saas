<?php

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * One implementation per payment provider (RazorpayGateway today; a future
 * StripeGateway/PaypalGateway implements the same contract). Nothing outside
 * this contract — SubscriptionController, the settings screen, the webhook
 * dispatch — should ever reference a concrete gateway class or its SDK
 * directly; they only ever talk to whichever gateway PaymentGatewayManager
 * resolves as active. That's what makes switching or adding a gateway a
 * config change (which one is "active") instead of a code change.
 */
interface PaymentGateway
{
    /**
     * Stable machine key, e.g. 'razorpay' — used as the PlatformSetting
     * value and to route the webhook to the right gateway.
     */
    public function identifier(): string;

    /**
     * Human label for the settings screen, e.g. 'Razorpay'.
     */
    public function label(): string;

    /**
     * Whether this gateway's required credentials (config/.env) are present.
     * A gateway can be registered without being configured — the settings
     * screen shows it as unavailable rather than letting it be selected.
     */
    public function isConfigured(): bool;

    /**
     * Creates the order/intent on the gateway's side for this exact plan's
     * price (computed here, server-side — never accept an amount from the
     * caller) and returns whatever the frontend checkout widget needs
     * (order id, amount, currency, gateway-specific public key, etc.).
     * Implementations create and return the SubscriptionPayment row too, so
     * callers never construct one themselves.
     *
     * @return array{payment: SubscriptionPayment, checkout: array<string, mixed>}
     */
    public function createCheckout(Tenant $tenant, Plan $plan): array;

    /**
     * Same idea as createCheckout(), for a self-service module add-on purchase
     * instead of a plan switch — the amount is computed by the caller (see
     * TenantModuleController), never trusted from the client. Implementations
     * create the SubscriptionPayment row with purpose=module_addon and the
     * given $modules recorded on it, so SubscriptionPaymentService::markPaid()
     * knows to grant those modules rather than switch the tenant's plan.
     *
     * @param list<string> $modules
     * @return array{payment: SubscriptionPayment, checkout: array<string, mixed>}
     */
    public function createModuleCheckout(Tenant $tenant, array $modules, int $amountInSmallestUnit, string $currency): array;

    /**
     * Verifies a browser-side checkout success callback's authenticity
     * against the given SubscriptionPayment (e.g. Razorpay's
     * order_id/payment_id/signature triad). Returns true only if genuinely
     * signed by this gateway for this exact payment — never trust the
     * caller's own "it succeeded" claim.
     *
     * @param array<string, mixed> $callbackInput
     */
    public function verifyCheckoutCallback(array $callbackInput, SubscriptionPayment $payment): bool;

    /**
     * Verifies an inbound webhook request's authenticity (signature over the
     * raw body) and, if valid, returns the {order_id, payment_id} pair it
     * refers to for a completed payment — or null if the event isn't a
     * completed-payment event, or the signature doesn't check out.
     *
     * @return array{order_id: string, payment_id: string}|null
     */
    public function resolveWebhookPayment(Request $request): ?array;
}
