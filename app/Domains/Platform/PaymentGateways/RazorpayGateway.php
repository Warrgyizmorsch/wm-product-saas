<?php

namespace App\Domains\Platform\PaymentGateways;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
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
        $webhookSecret = config('services.razorpay.webhook_secret');
        $signatureHeader = $request->header('X-Razorpay-Signature');
        $rawBody = $request->getContent();

        if (empty($webhookSecret) || empty($signatureHeader)) {
            Log::warning('Razorpay webhook received with no signature header or no configured webhook secret — ignored.');

            return null;
        }

        try {
            $this->api()->utility->verifyWebhookSignature($rawBody, $signatureHeader, $webhookSecret);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay webhook signature verification failed.', ['error' => $e->getMessage()]);

            return null;
        }

        $payload = (array) $request->json()->all();

        if (($payload['event'] ?? null) !== 'payment.captured') {
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
}
