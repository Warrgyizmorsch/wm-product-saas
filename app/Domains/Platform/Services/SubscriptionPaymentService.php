<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The one place a SubscriptionPayment ever actually transitions to "paid"
 * and switches the tenant's plan — shared by every PaymentGateway
 * implementation (called from both the browser-callback verification path
 * and each gateway's webhook handler) so this business rule exists exactly
 * once, regardless of which gateway confirmed the payment.
 */
class SubscriptionPaymentService
{
    public function __construct(
        private readonly TenantService $tenants,
    ) {
    }

    /**
     * Idempotent — safe to call twice for the same payment (a browser
     * callback and a webhook can both fire for the same transaction).
     *
     * $expectedTenant is a defense-in-depth check for the authenticated
     * browser-callback path: a valid gateway signature only proves the
     * payment is genuine, not that it belongs to the tenant making this
     * request, so a mismatch is rejected rather than silently switching a
     * different tenant's plan. Webhooks have no authenticated tenant to
     * compare against and pass null, trusting the row's own tenant_id.
     *
     * @throws \RuntimeException if $expectedTenant doesn't own this payment
     */
    public function markPaidAndSwitchPlan(
        SubscriptionPayment $payment,
        string $gatewayPaymentId,
        ?string $gatewaySignature,
        ?Tenant $expectedTenant = null,
    ): SubscriptionPayment {
        if ($expectedTenant !== null && $payment->tenant_id !== $expectedTenant->id) {
            throw new \RuntimeException('This payment does not belong to the current tenant.');
        }

        if ($payment->status === SubscriptionPayment::STATUS_PAID) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $gatewayPaymentId, $gatewaySignature) {
            $payment->refresh();

            if ($payment->status === SubscriptionPayment::STATUS_PAID) {
                return $payment;
            }

            $payment->update([
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_signature' => $gatewaySignature,
                'status' => SubscriptionPayment::STATUS_PAID,
            ]);

            $this->tenants->switchOwnPlan($payment->tenant, $payment->plan_id);

            return $payment;
        });
    }
}
