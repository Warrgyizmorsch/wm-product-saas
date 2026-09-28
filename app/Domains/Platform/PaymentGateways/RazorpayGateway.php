<?php

namespace App\Domains\Platform\PaymentGateways;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\GatewayPlan;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantSubscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayGateway implements PaymentGateway
{
    public function identifier(): string
    {
        return 'razorpay';
    }

    public function label(): string
    {
        return 'Razorpay';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    private function api(): Api
    {
        return new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    public function createCheckout(Tenant $tenant, Plan $plan): array
    {
        // Razorpay expects the amount in the currency's smallest unit
        // (paise for INR); Plan::price is stored in whole rupees.
        $amountInPaise = (int) round($plan->price * 100);

        $razorpayOrder = $this->api()->order->create([
            'amount' => $amountInPaise,
            'currency' => $plan->currency ?: 'INR',
            'receipt' => 'tenant-' . $tenant->id . '-plan-' . $plan->id . '-' . now()->timestamp,
            'notes' => [
                'tenant_id' => (string) $tenant->id,
                'plan_id' => (string) $plan->id,
                'plan_slug' => (string) $plan->slug,
            ],
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => $this->identifier(),
            'gateway_order_id' => $razorpayOrder['id'],
            'amount' => $amountInPaise,
            'currency' => $plan->currency ?: 'INR',
            'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        return [
            'payment' => $payment,
            'checkout' => [
                'gateway' => $this->identifier(),
                'order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'key' => config('services.razorpay.key'),
                'tenant_name' => $tenant->name,
                'tenant_email' => $tenant->billing_email,
            ],
        ];
    }

    public function createModuleCheckout(Tenant $tenant, array $modules, int $amountInSmallestUnit, string $currency): array
    {
        $razorpayOrder = $this->api()->order->create([
            'amount' => $amountInSmallestUnit,
            'currency' => $currency,
            'receipt' => 'tenant-' . $tenant->id . '-modules-' . now()->timestamp,
            'notes' => [
                'tenant_id' => (string) $tenant->id,
                'modules' => implode(',', $modules),
            ],
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'purpose' => SubscriptionPayment::PURPOSE_MODULE_ADDON,
            'modules' => $modules,
            'gateway' => $this->identifier(),
            'gateway_order_id' => $razorpayOrder['id'],
            'amount' => $amountInSmallestUnit,
            'currency' => $currency,
            'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        return [
            'payment' => $payment,
            'checkout' => [
                'gateway' => $this->identifier(),
                'order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'key' => config('services.razorpay.key'),
                'tenant_name' => $tenant->name,
                'tenant_email' => $tenant->billing_email,
            ],
        ];
    }

    public function verifyCheckoutCallback(array $callbackInput, SubscriptionPayment $payment): bool
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $payment->gateway_order_id,
                'razorpay_payment_id' => $callbackInput['gateway_payment_id'] ?? '',
                'razorpay_signature' => $callbackInput['gateway_signature'] ?? '',
            ]);

            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    public function resolveWebhookPayment(Request $request): ?array
    {
        $payload = $this->verifiedWebhookPayload($request);

        if ($payload === null || ($payload['event'] ?? null) !== 'payment.captured') {
            return null;
        }

        $paymentEntity = $payload['payload']['payment']['entity'] ?? null;

        if (! is_array($paymentEntity) || empty($paymentEntity['order_id']) || empty($paymentEntity['id'])) {
            return null;
        }

        return [
            'order_id' => $paymentEntity['order_id'],
            'payment_id' => $paymentEntity['id'],
        ];
    }

    public function createSubscription(Tenant $tenant, TenantSubscription $subscription): array
    {
        $gatewayPlanId = $this->gatewayPlanFor($subscription->cycle, $subscription->perSeatTotal(), $subscription->currency);

        // Razorpay needs an end: 10 years of renewals either way.
        $totalCount = $subscription->cycle === 'yearly' ? 10 : 120;

        $razorpaySubscription = $this->api()->subscription->create([
            'plan_id' => $gatewayPlanId,
            'quantity' => $subscription->seats,
            'total_count' => $totalCount,
            'customer_notify' => 1,
            'notes' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_subscription_id' => (string) $subscription->id,
                'plan_id' => (string) $subscription->plan_id,
                'modules' => implode(',', $subscription->modules ?? []),
            ],
        ]);

        return [
            'gateway_plan_id' => $gatewayPlanId,
            'gateway_subscription_id' => $razorpaySubscription['id'],
            'checkout' => [
                'gateway' => $this->identifier(),
                'subscription_id' => $razorpaySubscription['id'],
                'amount' => $subscription->total,
                'currency' => $subscription->currency,
                'key' => config('services.razorpay.key'),
                'tenant_name' => $tenant->billing_name ?? $tenant->name,
                'tenant_email' => $tenant->billing_email,
            ],
        ];
    }

    public function verifySubscriptionCallback(array $callbackInput, TenantSubscription $subscription): bool
    {
        if ($subscription->gateway_subscription_id === null) {
            return false;
        }

        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_subscription_id' => $subscription->gateway_subscription_id,
                'razorpay_payment_id' => $callbackInput['gateway_payment_id'] ?? '',
                'razorpay_signature' => $callbackInput['gateway_signature'] ?? '',
            ]);

            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    public function resolveSubscriptionWebhook(Request $request): ?array
    {
        $payload = $this->verifiedWebhookPayload($request);
        $event = (string) ($payload['event'] ?? '');

        if ($payload === null || ! str_starts_with($event, 'subscription.')) {
            return null;
        }

        $entity = $payload['payload']['subscription']['entity'] ?? null;
        $payment = $payload['payload']['payment']['entity'] ?? null;

        if (! is_array($entity) || empty($entity['id'])) {
            return null;
        }

        return [
            'event' => substr($event, strlen('subscription.')),
            'subscription_id' => $entity['id'],
            'payment_id' => is_array($payment) ? ($payment['id'] ?? null) : null,
            'amount' => is_array($payment) && isset($payment['amount']) ? (int) $payment['amount'] : null,
            'current_start' => isset($entity['current_start']) ? (int) $entity['current_start'] : null,
            'current_end' => isset($entity['current_end']) ? (int) $entity['current_end'] : null,
        ];
    }

    public function createChangeCheckout(Tenant $tenant, TenantSubscription $subscription, int $amountInSmallestUnit): array
    {
        $razorpayOrder = $this->api()->order->create([
            'amount' => $amountInSmallestUnit,
            'currency' => $subscription->currency,
            'receipt' => 'tenant-' . $tenant->id . '-sub-' . $subscription->id . '-change-' . now()->timestamp,
            'notes' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_subscription_id' => (string) $subscription->id,
                'purpose' => SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE,
            ],
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $subscription->plan_id,
            'tenant_subscription_id' => $subscription->id,
            'purpose' => SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE,
            'gateway' => $this->identifier(),
            'gateway_order_id' => $razorpayOrder['id'],
            'amount' => $amountInSmallestUnit,
            'currency' => $subscription->currency,
            'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        return [
            'payment' => $payment,
            'checkout' => [
                'gateway' => $this->identifier(),
                'order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'key' => config('services.razorpay.key'),
                'tenant_name' => $tenant->billing_name ?? $tenant->name,
                'tenant_email' => $tenant->billing_email,
            ],
        ];
    }

    public function scheduleSubscriptionChange(TenantSubscription $subscription, int $perSeatTotal, int $seats): string
    {
        $gatewayPlanId = $this->gatewayPlanFor($subscription->cycle, $perSeatTotal, $subscription->currency);

        // Razorpay only allows this on card subscriptions (a UPI/eMandate one refuses).
        $this->api()->subscription->fetch($subscription->gateway_subscription_id)->update([
            'plan_id' => $gatewayPlanId,
            'quantity' => $seats,
            'schedule_change_at' => 'cycle_end',
            'customer_notify' => 1,
        ]);

        return $gatewayPlanId;
    }

    public function cancelScheduledSubscriptionChange(TenantSubscription $subscription): void
    {
        $this->api()->subscription->fetch($subscription->gateway_subscription_id)->cancelScheduledChanges();
    }

    /**
     * Razorpay plans are immutable, so one per (period, per-seat amount) is
     * created once and reused from gateway_plans.
     */
    private function gatewayPlanFor(string $period, int $amount, string $currency): string
    {
        $cached = GatewayPlan::query()
            ->where('gateway', $this->identifier())
            ->where('period', $period)
            ->where('amount', $amount)
            ->where('currency', $currency)
            ->first();

        if ($cached !== null) {
            return $cached->gateway_plan_id;
        }

        $razorpayPlan = $this->api()->plan->create([
            'period' => $period,
            'interval' => 1,
            'item' => [
                'name' => sprintf('Per user, %s (Rs %s incl. GST)', $period, number_format($amount / 100, 2)),
                'amount' => $amount,
                'currency' => $currency,
            ],
        ]);

        GatewayPlan::query()->create([
            'gateway' => $this->identifier(),
            'period' => $period,
            'amount' => $amount,
            'currency' => $currency,
            'gateway_plan_id' => $razorpayPlan['id'],
        ]);

        return $razorpayPlan['id'];
    }

    /** The webhook's decoded body, or null when the signature doesn't check out. */
    private function verifiedWebhookPayload(Request $request): ?array
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $signatureHeader = $request->header('X-Razorpay-Signature');

        if (empty($webhookSecret) || empty($signatureHeader)) {
            Log::warning('Razorpay webhook received with no signature header or no configured webhook secret — ignored.');

            return null;
        }

        try {
            $this->api()->utility->verifyWebhookSignature($request->getContent(), $signatureHeader, $webhookSecret);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay webhook signature verification failed.', ['error' => $e->getMessage()]);

            return null;
        }

        return (array) $request->json()->all();
    }
}
