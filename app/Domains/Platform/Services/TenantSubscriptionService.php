<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\DTO\SubscriptionQuote;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantModule;
use App\Domains\Platform\Models\TenantSubscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recurring per-user subscriptions (Zoho-style billing, step 3): starts one
 * from a server-computed quote, activates it once the first payment is
 * verified, and mirrors the gateway's lifecycle webhooks (renewal charged,
 * payment pending/halted, cancelled). The one place a subscription changes
 * the tenant's plan, user limit and recurring add-ons.
 */
class TenantSubscriptionService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly TenantModuleService $modules,
    ) {
    }

    public function live(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', TenantSubscription::LIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    /**
     * Creates the subscription on the gateway and returns what its checkout
     * widget needs. Nothing changes for the tenant until activate().
     *
     * @throws RuntimeException when the tenant already has a live subscription
     */
    public function start(Tenant $tenant, SubscriptionQuote $quote, PaymentGateway $gateway): array
    {
        if ($this->live($tenant) !== null) {
            throw new RuntimeException('You already have an active subscription. Changing users or add-ons on it is coming next — contact support meanwhile.');
        }

        $subscription = TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $quote->planId,
            'cycle' => $quote->cycle,
            'seats' => $quote->seats,
            'modules' => $quote->addonModules(),
            'subtotal' => $quote->subtotal,
            'gst' => $quote->gst,
            'total' => $quote->total,
            'currency' => $quote->currency,
            'gateway' => $gateway->identifier(),
            'status' => TenantSubscription::STATUS_CREATED,
        ]);

        try {
            $result = $gateway->createSubscription($tenant, $subscription);
        } catch (\Throwable $e) {
            // e.g. Subscriptions not enabled on the gateway account, bad keys, network.
            $subscription->delete();
            report($e);

            throw new RuntimeException("{$gateway->label()} could not start the subscription: {$e->getMessage()}", 0, $e);
        }

        $subscription->update([
            'gateway_plan_id' => $result['gateway_plan_id'],
            'gateway_subscription_id' => $result['gateway_subscription_id'],
        ]);

        return $result['checkout'] + ['tenant_subscription_id' => $subscription->id];
    }

    /**
     * First payment confirmed (browser callback or `activated`/`charged`
     * webhook): switches the tenant onto the plan, sets its user limit to the
     * seats bought, installs the recurring add-ons and records the payment.
     * Idempotent — the callback and webhooks can all arrive for one payment.
     *
     * @throws RuntimeException if $expectedTenant doesn't own the subscription
     */
    public function activate(TenantSubscription $subscription, ?string $gatewayPaymentId, ?Tenant $expectedTenant = null): TenantSubscription
    {
        if ($expectedTenant !== null && $subscription->tenant_id !== $expectedTenant->id) {
            throw new RuntimeException('This subscription does not belong to the current tenant.');
        }

        return DB::transaction(function () use ($subscription, $gatewayPaymentId) {
            $subscription = TenantSubscription::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($subscription->id);

            if ($gatewayPaymentId !== null) {
                $this->recordPayment($subscription, $gatewayPaymentId, $subscription->total);
            }

            if ($subscription->activated_at !== null) {
                return $subscription;
            }

            $start = CarbonImmutable::now();
            $subscription->update([
                'status' => TenantSubscription::STATUS_ACTIVE,
                'activated_at' => $start,
                'current_start' => $start,
                'current_end' => $this->periodEnd($start, $subscription->cycle),
            ]);

            $tenant = Tenant::query()->findOrFail($subscription->tenant_id);
            $this->tenants->switchOwnPlan($tenant, $subscription->plan_id);

            $tenant->refresh()->update([
                'max_users' => $subscription->seats,
                'plan_expires_at' => $subscription->current_end,
            ]);

            $this->modules->install($tenant, $subscription->modules ?? [], null, null, TenantModule::BILLING_RECURRING);

            return $subscription;
        });
    }

    /**
     * Mirrors a normalised gateway webhook (PaymentGateway::resolveSubscriptionWebhook).
     *
     * @param array{event: string, subscription_id: string, payment_id: ?string, amount: ?int, current_start: ?int, current_end: ?int} $event
     */
    public function handleWebhook(array $event): void
    {
        $subscription = TenantSubscription::query()
            ->withoutGlobalScope('tenant')
            ->where('gateway_subscription_id', $event['subscription_id'])
            ->first();

        if ($subscription === null) {
            return;
        }

        match ($event['event']) {
            'activated' => $this->activate($subscription, null),
            'charged' => $this->charged($subscription, $event),
            'pending', 'halted' => $this->paymentFailing($subscription, $event['event']),
            'cancelled', 'completed' => $this->ended($subscription, $event['event']),
            default => null,
        };
    }

    /** A renewal (or the first charge) went through: extend the period, clear any grace. */
    private function charged(TenantSubscription $subscription, array $event): void
    {
        if (! empty($event['payment_id'])) {
            $this->recordPayment($subscription, $event['payment_id'], $event['amount'] ?? $subscription->total);
        }

        $subscription = $this->activate($subscription, null);

        $updates = [
            'status' => TenantSubscription::STATUS_ACTIVE,
            'grace_ends_at' => null,
        ];
        if (! empty($event['current_start'])) {
            $updates['current_start'] = CarbonImmutable::createFromTimestamp($event['current_start'], config('app.timezone'));
        }
        if (! empty($event['current_end'])) {
            $updates['current_end'] = CarbonImmutable::createFromTimestamp($event['current_end'], config('app.timezone'));
        }
        $subscription->update($updates);

        Tenant::query()->whereKey($subscription->tenant_id)->update([
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
            'plan_expires_at' => $subscription->current_end,
        ]);
    }

    /** Renewal charge failed: past due, with a grace period before suspension. */
    private function paymentFailing(TenantSubscription $subscription, string $status): void
    {
        $subscription->update([
            'status' => $status,
            'grace_ends_at' => $subscription->grace_ends_at ?? now()->addDays((int) config('billing.grace_days')),
        ]);

        Tenant::query()->whereKey($subscription->tenant_id)->update([
            'subscription_status' => Tenant::SUBSCRIPTION_PAST_DUE,
        ]);
    }

    /** Cancelled or ran its course: access continues until current_end. */
    private function ended(TenantSubscription $subscription, string $status): void
    {
        $subscription->update([
            'status' => $status,
            'cancelled_at' => $subscription->cancelled_at ?? now(),
        ]);

        Tenant::query()->whereKey($subscription->tenant_id)->update([
            'subscription_status' => Tenant::SUBSCRIPTION_CANCELLED,
        ]);
    }

    private function recordPayment(TenantSubscription $subscription, string $gatewayPaymentId, int $amount): void
    {
        $exists = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->exists();

        if ($exists) {
            return;
        }

        SubscriptionPayment::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'tenant_subscription_id' => $subscription->id,
            'purpose' => SubscriptionPayment::PURPOSE_SUBSCRIPTION,
            'modules' => $subscription->modules,
            'gateway' => $subscription->gateway,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount' => $amount,
            'currency' => $subscription->currency,
            'status' => SubscriptionPayment::STATUS_PAID,
        ]);
    }

    private function periodEnd(CarbonImmutable $start, string $cycle): CarbonImmutable
    {
        return $cycle === 'yearly' ? $start->addYear() : $start->addMonth();
    }
}
