<?php

namespace App\Domains\Platform\Services;

use App\Core\Tenant\TenantProvisioner;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The one place a SubscriptionPayment ever actually transitions to "paid" and
 * takes its effect — a plan switch, or (for a module add-on checkout) granting
 * the purchased modules — shared by every PaymentGateway implementation
 * (called from both the browser-callback verification path and each gateway's
 * webhook handler) so this business rule exists exactly once, regardless of
 * which gateway confirmed the payment or what the payment was for.
 */
class SubscriptionPaymentService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly TenantProvisioner $provisioner,
    ) {
    }

    /**
     * Idempotent — safe to call twice for the same payment (a browser
     * callback and a webhook can both fire for the same transaction).
     *
     * $expectedTenant is a defense-in-depth check for the authenticated
     * browser-callback path: a valid gateway signature only proves the
     * payment is genuine, not that it belongs to the tenant making this
     * request, so a mismatch is rejected rather than silently applying it to
     * a different tenant. Webhooks have no authenticated tenant to compare
     * against and pass null, trusting the row's own tenant_id.
     *
     * @throws \RuntimeException if $expectedTenant doesn't own this payment
     */
    public function markPaid(
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

            if ($payment->purpose === SubscriptionPayment::PURPOSE_MODULE_ADDON) {
                $payment->tenant->installModules($payment->modules ?? []);

                // Same as a plan switch: fill the new modules' starter masters.
                $this->provisioner->provision($payment->tenant->fresh());
            } else {
                $this->tenants->switchOwnPlan($payment->tenant, $payment->plan_id);
            }

            return $payment;
        });
    }
}
