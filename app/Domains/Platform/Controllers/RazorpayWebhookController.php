<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SubscriptionPaymentService;
use App\Domains\Platform\Services\TenantSubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint — Razorpay's own servers POST here directly, with no
 * session, no tenant header, no CSRF token. Authenticity is established
 * entirely by RazorpayGateway::resolveWebhookPayment() verifying the
 * X-Razorpay-Signature header against the raw body; nothing here trusts the
 * payload before that check passes.
 *
 * The webhook URL is inherently gateway-specific (each provider needs its
 * own registered callback URL — that's not something a "which gateway is
 * active" toggle can abstract away), but this controller still resolves the
 * RazorpayGateway instance through PaymentGatewayManager rather than
 * constructing the Razorpay SDK directly, and hands the confirmed
 * payment off to the same gateway-agnostic SubscriptionPaymentService every
 * other completion path uses. A future StripeWebhookController would look
 * near-identical, just resolving 'stripe' instead.
 */
class RazorpayWebhookController extends Controller
{
    private const GATEWAY_IDENTIFIER = 'razorpay';

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly SubscriptionPaymentService $payments,
        private readonly TenantSubscriptionService $subscriptions,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $gateway = $this->gateways->resolveByIdentifier(self::GATEWAY_IDENTIFIER);

        if ($gateway === null) {
            return response()->json(['status' => 'ignored']);
        }

        // Recurring subscriptions: activated / charged / pending / halted / cancelled.
        $subscriptionEvent = $gateway->resolveSubscriptionWebhook($request);

        if ($subscriptionEvent !== null) {
            $this->subscriptions->handleWebhook($subscriptionEvent);

            return response()->json(['status' => 'ok']);
        }

        $resolved = $gateway->resolveWebhookPayment($request);

        if ($resolved === null) {
            // Invalid signature, unrecognized event, or malformed payload —
            // always 200 either way so Razorpay doesn't retry a payload it's
            // never going to like better on a retry.
            return response()->json(['status' => 'ignored']);
        }

        $payment = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('gateway_order_id', $resolved['order_id'])
            ->where('gateway', self::GATEWAY_IDENTIFIER)
            ->first();

        if ($payment !== null) {
            // No authenticated tenant on a webhook request — trust the row's
            // own tenant_id (see SubscriptionPaymentService's doc comment).
            $this->payments->markPaid($payment, $resolved['payment_id'], null);
        }

        return response()->json(['status' => 'ok']);
    }
}
