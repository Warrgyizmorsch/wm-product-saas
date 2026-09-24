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
 * payment pending/halted, cancelled). Mid-cycle changes (step 4): an upgrade
 * is charged pro rata now and applies at once, a downgrade applies at renewal;
 * either way renewals bill the new amount. The one place a subscription
 * changes the tenant's plan, user limit and recurring add-ons.
 */
class TenantSubscriptionService
{
    /** Smallest one-time charge the gateway accepts (Rs 1); a smaller proration is waived. */
    public const MIN_CHARGE = 100;

    public function __construct(
        private readonly TenantService $tenants,
        private readonly TenantModuleService $modules,
        private readonly SubscriptionPricing $pricing,
        private readonly PaymentGatewayManager $gateways,
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
            throw new RuntimeException('You already have an active subscription — change it instead of starting another.');
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
     * What moving the live subscription to $quote means, for the checkout:
     * `effective` now (an upgrade, `due_now` charged pro rata for the rest of
     * the period) or at renewal (costs the same or less, nothing due now).
     * `error` is set when the change isn't allowed.
     *
     * @return array{effective: ?string, due_now: int, renews_on: ?string, current_total: int, error: ?string}
     */
    public function previewChange(TenantSubscription $live, SubscriptionQuote $quote): array
    {
        $preview = [
            'effective' => null,
            'due_now' => 0,
            'renews_on' => $live->current_end?->format('d M Y'),
            'current_total' => $live->total,
            'error' => null,
        ];

        if ($live->status !== TenantSubscription::STATUS_ACTIVE) {
            return ['error' => 'Your last renewal payment failed — settle it before changing your subscription.'] + $preview;
        }

        if ($quote->cycle !== $live->cycle) {
            return ['error' => "Your subscription is billed {$live->cycle}. Switching between monthly and yearly isn't available yet — keep {$live->cycle} billing to change the plan, users or add-ons."] + $preview;
        }

        if ($this->sameAs($live, $quote)) {
            return ['error' => 'This is your current subscription — change the plan, users or add-ons.'] + $preview;
        }

        if ($quote->subtotal <= $live->subtotal) {
            return ['effective' => 'renewal'] + $preview;
        }

        $due = $this->pricing->prorateSubtotals($live->subtotal, $quote->subtotal, $live->current_start, $live->current_end, now());

        return ['effective' => 'now', 'due_now' => $due] + $preview;
    }

    /**
     * Changes the live subscription to $quote. A downgrade is booked on the
     * gateway for renewal (`scheduled`); an upgrade whose proration is too small
     * to charge applies at once (`applied`); any other upgrade returns the
     * checkout for the prorated payment and applies once it's paid
     * (applyPaidChange()).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when there's no live subscription, the change isn't allowed, or the gateway refuses
     */
    public function change(Tenant $tenant, SubscriptionQuote $quote): array
    {
        $live = $this->live($tenant) ?? throw new RuntimeException('You have no active subscription to change.');
        $preview = $this->previewChange($live, $quote);

        if ($preview['error'] !== null) {
            throw new RuntimeException($preview['error']);
        }

        $gateway = $this->gatewayFor($live);
        $change = [
            'plan_id' => $quote->planId,
            'seats' => $quote->seats,
            'modules' => $quote->addonModules(),
            'subtotal' => $quote->subtotal,
            'gst' => $quote->gst,
            'total' => $quote->total,
        ];

        try {
            // A downgrade booked earlier is replaced by this change.
            if ($live->scheduledChange() !== null) {
                $gateway->cancelScheduledSubscriptionChange($live);
            }
            $live->update(['pending_change' => null]);

            if ($preview['effective'] === 'renewal') {
                $gatewayPlanId = $gateway->scheduleSubscriptionChange($live, intdiv($quote->total, $quote->seats), $quote->seats);
                $live->update(['pending_change' => $change + [
                    'gateway_plan_id' => $gatewayPlanId,
                    'effective_at' => $live->current_end?->toIso8601String(),
                ]]);

                return ['scheduled' => true, 'effective_on' => $preview['renews_on']];
            }

            if ($preview['due_now'] < self::MIN_CHARGE) {
                DB::transaction(fn () => $this->applyChange($live, $change));
                $this->syncRenewalAmount($live->fresh());

                return ['applied' => true];
            }

            $result = $gateway->createChangeCheckout($tenant, $live, $preview['due_now']);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            throw new RuntimeException("{$gateway->label()} could not update the subscription: {$e->getMessage()}", 0, $e);
        }

        $live->update(['pending_change' => $change + ['payment_id' => $result['payment']->id]]);

        return $result['checkout'];
    }

    /**
     * The prorated payment for an upgrade was confirmed (SubscriptionPaymentService
     * ::markPaid, inside its transaction): apply the change now and move renewals
     * to the new amount. A payment for a change since replaced applies nothing.
     */
    public function applyPaidChange(SubscriptionPayment $payment): void
    {
        $subscription = TenantSubscription::query()->withoutGlobalScope('tenant')->lockForUpdate()->find($payment->tenant_subscription_id);
        $change = $subscription?->pending_change;

        if ($change === null || ($change['payment_id'] ?? null) !== $payment->id) {
            report(new RuntimeException("Subscription change payment {$payment->id} was paid but its change was already replaced or applied."));

            return;
        }

        $this->applyChange($subscription, $change);
        DB::afterCommit(fn () => $this->syncRenewalAmount($subscription->fresh()));
    }

    /** Switches the subscription and the tenant to $change right away. */
    private function applyChange(TenantSubscription $subscription, array $change): void
    {
        $planChanged = $subscription->plan_id !== (int) $change['plan_id'];

        $subscription->update([
            'plan_id' => $change['plan_id'],
            'seats' => $change['seats'],
            'modules' => $change['modules'],
            'subtotal' => $change['subtotal'],
            'gst' => $change['gst'],
            'total' => $change['total'],
            'gateway_plan_id' => $change['gateway_plan_id'] ?? $subscription->gateway_plan_id,
            'pending_change' => null,
        ]);

        $tenant = Tenant::query()->findOrFail($subscription->tenant_id);

        if ($planChanged) {
            $this->tenants->switchOwnPlan($tenant, (int) $change['plan_id']);
        }

        $tenant->refresh()->update(['max_users' => $change['seats']]);

        // Recurring add-ons dropped from the bill stop working; lifetime ones never do.
        $tenant->addonModules()
            ->where('billing', TenantModule::BILLING_RECURRING)
            ->whereNull('uninstalled_at')
            ->whereNotIn('module', $change['modules'])
            ->update(['uninstalled_at' => now()]);
        $tenant->unsetRelation('addonModules');

        $this->modules->install($tenant, $change['modules'], null, null, TenantModule::BILLING_RECURRING);
    }

    /**
     * After an upgrade applied now: renewals must bill the new amount. The
     * tenant already paid, so a gateway refusal (e.g. a UPI mandate that can't
     * be updated) is reported for support to fix rather than undoing the change.
     */
    private function syncRenewalAmount(TenantSubscription $subscription): void
    {
        try {
            $gatewayPlanId = $this->gatewayFor($subscription)
                ->scheduleSubscriptionChange($subscription, $subscription->perSeatTotal(), $subscription->seats);
            $subscription->update(['gateway_plan_id' => $gatewayPlanId]);
        } catch (\Throwable $e) {
            report(new RuntimeException("Subscription {$subscription->id} was upgraded but its renewal amount could not be updated on the gateway: {$e->getMessage()}", 0, $e));
        }
    }

    private function sameAs(TenantSubscription $live, SubscriptionQuote $quote): bool
    {
        $current = $live->modules ?? [];
        $new = $quote->addonModules();
        sort($current);
        sort($new);

        return $live->plan_id === $quote->planId && $live->seats === $quote->seats && $current === $new;
    }

    private function gatewayFor(TenantSubscription $subscription): PaymentGateway
    {
        return $this->gateways->resolveByIdentifier($subscription->gateway)
            ?? throw new RuntimeException("The payment gateway this subscription was bought with ({$subscription->gateway}) is no longer available — contact support.");
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

        // A downgrade booked for this renewal: from now the gateway bills the new amount.
        $scheduled = $subscription->scheduledChange();
        if ($scheduled !== null && ! empty($event['current_start']) && ! empty($scheduled['effective_at'])
            && $event['current_start'] >= CarbonImmutable::parse($scheduled['effective_at'])->subHour()->getTimestamp()) {
            DB::transaction(fn () => $this->applyChange($subscription, $scheduled));
        }

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
